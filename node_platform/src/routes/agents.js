import jwt from 'jsonwebtoken';
import { getConfig } from '../config/index.js';
import { getLogger } from '../logger/index.js';
import { getThreatDetector } from '../security/threatDetector.js';
import { FileRateLimiter } from '../security/fileRateLimiter.js';

export function registerAgentRoutes(app) {
  const config = getConfig();
  const logger = getLogger().child({ component: 'routes:agents' });

  // Build service URLs from config (environment-agnostic)
  const phpBaseUrl = config.internalHttp?.php?.baseUrl || '';
  const phpApiPrefix = config.internalHttp?.php?.apiPrefix || '/api';
  const pythonBaseUrl = config.ai?.pythonUrl || config.internalHttp?.python?.baseUrl || '';
  const internalSecret = config.security?.internalSecret || config.security?.aiPythonSecret;
  // Python authenticates service-to-service calls with its own bearer
  // (AI_PYTHON_SECRET / KINGSWAY_AI_SECRET), which is not necessarily the
  // generic internal secret used between Node and PHP.
  const pythonSecret = config.security?.aiPythonSecret || internalSecret;

  if (!phpBaseUrl) {
    logger.warn('PHP base URL not configured in internalHttp.php.baseUrl');
  }
  if (!pythonBaseUrl) {
    logger.warn('Python base URL not configured in internalHttp.python.baseUrl or ai.pythonUrl');
  }
  if (!internalSecret) {
    logger.warn('Internal secret not configured (internalSecret or aiPythonSecret)');
  }

  const phpApiBase = `${phpBaseUrl}${phpApiPrefix}`;

  function checkAuth(req) {
    const token = req.headers.authorization?.replace('Bearer ', '');
    if (!token) return null;
    try {
      const decoded = jwt.verify(token, config.security.jwtSecret, {
        issuer: config.security.jwtIssuer,
        audience: config.security.jwtAudience,
        clockTolerance: 30,
      });
      const userId = String(decoded?.sub ?? decoded?.user_id ?? decoded?.id ?? '');
      if (!/^[1-9]\d{0,18}$/.test(userId)) return null;
      // Restricted MFA-enrollment tokens only prove the password step. The
      // PHP middleware confines them to the two-factor setup surface, so the
      // gateway must never treat one as a full application session.
      if (decoded?.onboarding) return null;
      return { userId, roles: decoded.roles || [], permissions: decoded.permissions || [], scopes: decoded.channels || [] };
    } catch {
      getThreatDetector().recordFailedLogin(req.ip, 'invalid_token').catch(() => {});
      return null;
    }
  }

  // Anonymous public assistant limits are persisted in SQLite beneath the
  // shared Node storage namespace (no Redis and no process-memory counters).
  const publicRateLimiter = new FileRateLimiter({
    name: 'public-assistant',
    windowMs: 60 * 1000,
    limit: config.security?.publicRateLimit?.requestsPerMinute || 20,
    burstWindowMs: 10 * 60 * 1000,
    burstLimit: config.security?.publicRateLimit?.burstLimit || 50,
  });

  async function checkPublicRateLimit(ip) {
    return publicRateLimiter.check(ip);
  }

  // Forward only supported conversation context from the browser. PHP derives
  // identity, permissions and assistant domain from the authenticated user;
  // these fields are never accepted from a client payload.
  function assistantRequestContext(body) {
    const nested = body.options && typeof body.options === 'object' ? body.options : {};
    const value = (key) => body[key] !== undefined ? body[key] : nested[key];
    const context = {};
    for (const key of ['history', 'topics', 'sidebar_state', 'preferences']) {
      const item = value(key);
      if (item !== undefined) context[key] = item;
    }
    return context;
  }

  /**
   * POST /api/agents/call
   * Proxy to existing PHP agent-assist endpoint (which routes to Python with all guardrails/tools/sessions)
   */
  app.post('/api/agents/call', async (req, reply) => {
    const auth = checkAuth(req);
    if (!auth) return reply.code(401).send({ error: 'Unauthorized' });

    const body = req.body || {};
    const { question, route = 'dashboard', module = 'dashboard', session_id, options = {} } = body;

    if (!question || typeof question !== 'string') {
      return reply.code(422).send({ error: 'question required' });
    }

    // Threat detection: record query for analysis
    const detector = getThreatDetector();
    void detector.recordQuery(req.ip, auth.userId, question).catch(() => {});

    // Forward to existing PHP agent-assist endpoint (which has all guardrails, tools, sessions, charts, drafts)
    try {
      const phpUrl = `${phpApiBase}/dashboard/agent-assist`;
      const phpResponse = await fetch(phpUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': req.headers.authorization,
          'X-CSRF-Token': req.headers['x-csrf-token'] || '',
          'X-Internal-User-Id': auth.userId,
          'X-Internal-Permissions': JSON.stringify(auth.permissions),
          'X-Internal-Roles': JSON.stringify(auth.roles),
          'X-Internal-Secret': internalSecret,
          ...(typeof req.headers['x-request-id'] === 'string'
            ? { 'X-Request-ID': req.headers['x-request-id'].slice(0, 100) }
            : {}),
        },
        body: JSON.stringify({
          question,
          route,
          module,
          session_id,
          ...assistantRequestContext(body),
        }),
      });

      if (!phpResponse.ok) {
        const error = await phpResponse.text();
        throw new Error(`PHP agent error: ${phpResponse.status} ${error}`);
      }

      const result = await phpResponse.json();
      return result.data || result;
    } catch (e) {
      return reply.code(502).send({ error: 'Agent service is temporarily unavailable' });
    }
  });

  /**
   * POST /api/agents/stream
   * Node owns the browser stream and connects directly to Python. PHP supplies
   * the identity and permission envelope; governed tools still return to PHP.
   */
  app.post('/api/agents/stream', async (req, reply) => {
    const auth = checkAuth(req);
    if (!auth) return reply.code(401).send({ error: 'Unauthorized' });
    const body = req.body || {};
    const { question, route = 'dashboard', module = 'dashboard', session_id } = body;
    if (typeof question !== 'string' || !question.trim() || question.length > 500) {
      return reply.code(422).send({ error: 'A question of up to 500 characters is required' });
    }
    if (!pythonBaseUrl || !internalSecret) {
      return reply.code(503).send({ error: 'Assistant streaming is not configured' });
    }
    void getThreatDetector().recordQuery(req.ip, auth.userId, question).catch(() => {});
    const origin = req.headers.origin;
    const headers = {
      'Content-Type': 'text/event-stream',
      'Cache-Control': 'no-cache, no-store, private',
      'Connection': 'keep-alive',
      'X-Accel-Buffering': 'no',
    };
    // writeHead() is what actually reaches the socket after hijack; headers
    // added via reply.header() are dropped. CORS must be set here or Firefox
    // refuses to read the stream cross-origin.
    if (origin && config.security.webOrigins.includes(origin)) {
      headers['Access-Control-Allow-Origin'] = origin;
      headers['Access-Control-Allow-Credentials'] = 'true';
      headers['Vary'] = 'Origin';
    }
    reply.hijack();
    reply.raw.writeHead(200, headers);
    const sendEvent = (name, data) => {
      if (reply.raw.destroyed || reply.raw.writableEnded) return false;
      return reply.raw.write(`event: ${name}\ndata: ${JSON.stringify(data)}\n\n`);
    };
    const abortController = new AbortController();
    sendEvent('start', { status: 'authorizing' });
    const respectBackpressure = async (writable) => {
      if (writable || reply.raw.destroyed || abortController.signal.aborted) return;
      await new Promise((resolve) => {
        const done = () => {
          reply.raw.off('drain', done);
          reply.raw.off('close', done);
          abortController.signal.removeEventListener('abort', done);
          resolve();
        };
        reply.raw.once('drain', done);
        reply.raw.once('close', done);
        abortController.signal.addEventListener('abort', done, { once: true });
      });
    };
    let disconnected = false;
    req.raw.on('aborted', () => { disconnected = true; abortController.abort(); });
    reply.raw.on('close', () => { disconnected = true; abortController.abort(); });
    try {
      // PHP validates the browser JWT and derives permissions/domain from its
      // authenticated user record. Never forward browser-supplied RBAC claims.
      const contextResponse = await fetch(`${phpApiBase}/dashboard/agent-stream-context`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': req.headers.authorization,
          'X-CSRF-Token': req.headers['x-csrf-token'] || '',
          'X-Internal-User-Id': auth.userId,
          'X-Internal-Permissions': JSON.stringify(auth.permissions),
          'X-Internal-Roles': JSON.stringify(auth.roles),
          'X-Internal-Secret': internalSecret,
          ...(typeof req.headers['x-request-id'] === 'string'
            ? { 'X-Request-ID': req.headers['x-request-id'].slice(0, 100) }
            : {}),
        },
        body: JSON.stringify({ question, route, module, session_id, ...assistantRequestContext(body) }),
        signal: abortController.signal,
      });
      if (!contextResponse.ok) {
        const detail = await contextResponse.text().catch(() => '');
        throw new Error(`PHP authorization returned ${contextResponse.status} ${detail.slice(0, 200)}`);
      }
      const contextEnvelope = await contextResponse.json();
      const context = contextEnvelope?.data?.context;
      if (!context || Number(context.user_id) !== Number(auth.userId)) throw new Error('PHP authorization context missing');
      const response = await fetch(`${pythonBaseUrl}/api/agents/assist/stream`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'text/event-stream', 'Authorization': `Bearer ${pythonSecret}` },
        body: JSON.stringify({ context, question }),
        signal: abortController.signal,
      });
      if (!response.ok || !response.body) throw new Error(`Python stream returned ${response.status}`);
      const reader = response.body.getReader();
      const decoder = new TextDecoder();
      let buffer = '';
      while (!disconnected) {
        const { done, value } = await reader.read();
        if (done) break;
        buffer += decoder.decode(value, { stream: true });
        const frames = buffer.split('\n\n');
        buffer = frames.pop() || '';
        for (const frame of frames) {
          const eventLine = frame.split('\n').find((line) => line.startsWith('event:'));
          const name = eventLine ? eventLine.slice(6).trim() : '';
          const dataLines = frame.split('\n').filter((line) => line.startsWith('data:'));
          if (!name || !dataLines.length) continue;
          try {
            const data = JSON.parse(dataLines.map((line) => line.slice(5).trim()).join(''));
            if (['start', 'status', 'delta', 'final', 'error', 'done'].includes(name)) {
              const writable = sendEvent(name, data);
              await respectBackpressure(writable);
            }
          } catch { /* ignore malformed upstream event */ }
        }
      }
      if (buffer.trim() && !disconnected) {
        const eventLine = buffer.split('\n').find((line) => line.startsWith('event:'));
        const dataLine = buffer.split('\n').find((line) => line.startsWith('data:'));
        if (eventLine && dataLine) {
          try {
            const writable = sendEvent(eventLine.slice(6).trim(), JSON.parse(dataLine.slice(5).trim()));
            await respectBackpressure(writable);
          } catch { /* ignore */ }
        }
      }
    } catch (error) {
      if (!disconnected) {
        logger.error(
          { errorType: error.name, errorMessage: error.message },
          'Direct Node to Python assistant stream failed',
        );
        sendEvent('error', { message: 'The assistant could not connect to its service. Please try again.', terminal: true });
      }
    } finally {
      if (!reply.raw.destroyed && !reply.raw.writableEnded) reply.raw.end();
    }
  });

  /**
   * POST /api/agents/task
   * Create async task (for polling fallback pages)
   */
  const createAgentTask = async (req, reply) => {
    const auth = checkAuth(req);
    if (!auth) return reply.code(401).send({ error: 'Unauthorized' });

    const body = req.body || {};
    const { tool, question, route = 'dashboard', module = 'dashboard', session_id, options = {} } = body;

    if (!tool || !question) {
      return reply.code(422).send({ error: 'tool and question required' });
    }

    // Threat detection: record query for analysis
    const detector = getThreatDetector();
    void detector.recordQuery(req.ip, auth.userId, question).catch(() => {});

    // Forward to PHP /dashboard/agent-task-create
    try {
      const phpUrl = `${phpApiBase}/dashboard/agent-task-create`;
      const phpResponse = await fetch(phpUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': req.headers.authorization,
          'X-CSRF-Token': req.headers['x-csrf-token'] || '',
          'X-Internal-User-Id': auth.userId,
          'X-Internal-Permissions': JSON.stringify(auth.permissions),
          'X-Internal-Roles': JSON.stringify(auth.roles),
          'X-Internal-Secret': internalSecret,
          ...(typeof req.headers['x-request-id'] === 'string'
            ? { 'X-Request-ID': req.headers['x-request-id'].slice(0, 100) }
            : {}),
        },
        body: JSON.stringify({ tool, question, route, module, session_id, ...options }),
      });

      if (!phpResponse.ok) {
        const error = await phpResponse.text();
        throw new Error(`PHP agent task create error: ${phpResponse.status} ${error}`);
      }

      const result = await phpResponse.json();
      return result.data || result;
    } catch (e) {
      return reply.code(502).send({ error: 'Agent task service is temporarily unavailable' });
    }
  };

  app.post('/api/agents/task', createAgentTask);
  app.post('/api/agents/tasks', createAgentTask);

  /**
   * GET /api/agents/task/:id
   * Poll task status
   */
  const getAgentTask = async (req, reply) => {
    const auth = checkAuth(req);
    if (!auth) return reply.code(401).send({ error: 'Unauthorized' });

    try {
      const phpUrl = `${phpApiBase}/dashboard/agent-task/${req.params.id}`;
      const phpResponse = await fetch(phpUrl, {
        headers: {
          'Authorization': req.headers.authorization,
          'X-Internal-User-Id': auth.userId,
          'X-Internal-Permissions': JSON.stringify(auth.permissions),
          'X-Internal-Roles': JSON.stringify(auth.roles),
          'X-Internal-Secret': internalSecret,
        },
      });

      if (!phpResponse.ok) {
        const error = await phpResponse.text();
        throw new Error(`PHP agent task get error: ${phpResponse.status} ${error}`);
      }

      const result = await phpResponse.json();
      return result.data || result;
    } catch (e) {
      return reply.code(502).send({ error: 'Agent task service is temporarily unavailable' });
    }
  };

  app.get('/api/agents/task/:id', getAgentTask);
  app.get('/api/agents/tasks/:id', getAgentTask);

  /**
   * GET /api/agents/tasks
   * List user tasks
   */
  const listAgentTasks = async (req, reply) => {
    const auth = checkAuth(req);
    if (!auth) return reply.code(401).send({ error: 'Unauthorized' });

    const limit = req.query.limit ? parseInt(req.query.limit, 10) : 30;

    try {
      const phpUrl = `${phpApiBase}/dashboard/agent-tasks?limit=${limit}`;
      const phpResponse = await fetch(phpUrl, {
        headers: {
          'Authorization': req.headers.authorization,
          'X-Internal-User-Id': auth.userId,
          'X-Internal-Permissions': JSON.stringify(auth.permissions),
          'X-Internal-Roles': JSON.stringify(auth.roles),
          'X-Internal-Secret': internalSecret,
        },
      });

      if (!phpResponse.ok) {
        const error = await phpResponse.text();
        throw new Error(`PHP agent tasks list error: ${phpResponse.status} ${error}`);
      }

      const result = await phpResponse.json();
      return result.data || result;
    } catch (e) {
      return reply.code(502).send({ error: 'Agent task service is temporarily unavailable' });
    }
  };

  app.get('/api/agents/tasks', listAgentTasks);

  /**
   * DELETE /api/agents/task/:id
   * Cancel task
   */
  const cancelAgentTask = async (req, reply) => {
    const auth = checkAuth(req);
    if (!auth) return reply.code(401).send({ error: 'Unauthorized' });

    try {
      const phpUrl = `${phpApiBase}/dashboard/agent-task-cancel/${req.params.id}`;
      const phpResponse = await fetch(phpUrl, {
        method: 'DELETE',
        headers: {
          'Authorization': req.headers.authorization,
          'X-CSRF-Token': req.headers['x-csrf-token'] || '',
          'X-Internal-User-Id': auth.userId,
          'X-Internal-Permissions': JSON.stringify(auth.permissions),
          'X-Internal-Roles': JSON.stringify(auth.roles),
          'X-Internal-Secret': internalSecret,
        },
      });

      if (!phpResponse.ok) {
        const error = await phpResponse.text();
        throw new Error(`PHP agent task cancel error: ${phpResponse.status} ${error}`);
      }

      const result = await phpResponse.json();
      return result.data || result;
    } catch (e) {
      return reply.code(502).send({ error: 'Agent task service is temporarily unavailable' });
    }
  };

  app.delete('/api/agents/task/:id', cancelAgentTask);
  app.delete('/api/agents/tasks/:id', cancelAgentTask);

  /**
   * GET /api/agents/task/:id/stream
   * SSE for task progress (component-level subscription)
   */
  const streamAgentTask = async (req, reply) => {
    // Validate JWT from query param (SSE can't use headers easily)
    const token = req.query.token?.replace('Bearer ', '');
    if (!token) return reply.code(401).send({ error: 'Unauthorized' });

    let auth;
    try {
      const decoded = jwt.verify(token, config.security.jwtSecret, {
        issuer: config.security.jwtIssuer,
        audience: config.security.jwtAudience,
        clockTolerance: 30,
      });
      const userId = String(decoded?.sub ?? decoded?.user_id ?? decoded?.id ?? '');
      if (!/^[1-9]\d{0,18}$/.test(userId)) return reply.code(401).send({ error: 'Unauthorized' });
      if (decoded?.onboarding) return reply.code(401).send({ error: 'Unauthorized' });
      auth = { userId, roles: decoded.roles || [], permissions: decoded.permissions || [], scopes: decoded.channels || [] };
    } catch {
      return reply.code(401).send({ error: 'Unauthorized' });
    }

    // SSE response setup
    const headers = {
      'Content-Type': 'text/event-stream',
      'Cache-Control': 'no-cache, no-store, private',
      'Connection': 'keep-alive',
      'X-Accel-Buffering': 'no',
    };

    const requestOrigin = req.headers.origin;
    if (requestOrigin && config.security.webOrigins.includes(requestOrigin)) {
      headers['Access-Control-Allow-Origin'] = requestOrigin;
      headers['Access-Control-Allow-Credentials'] = 'true';
      headers['Vary'] = 'Origin';
    }

    reply.hijack();
    reply.raw.writeHead(200, headers);

    let completed = false;

    const sendEvent = (event, data) => {
      if (reply.raw.destroyed || reply.raw.writableEnded) return false;
      const frame = `event: ${event}\ndata: ${JSON.stringify(data)}\n\n`;
      return reply.raw.write(frame);
    };

    const closeStream = () => {
      if (!completed) {
        completed = true;
        if (!reply.raw.destroyed && !reply.raw.writableEnded) {
          reply.raw.end();
        }
      }
    };

    const taskId = String(req.params.id || '');
    if (!/^\d{1,18}$/.test(taskId)) {
      sendEvent('error', { message: 'Task not found' });
      return closeStream();
    }
    let disconnected = false;
    reply.raw.on('close', () => { disconnected = true; });
    const phpHeaders = {
      'Authorization': `Bearer ${token}`,
      'X-Internal-User-Id': auth.userId,
      'X-Internal-Permissions': JSON.stringify(auth.permissions),
      'X-Internal-Roles': JSON.stringify(auth.roles),
      'X-Internal-Secret': internalSecret,
    };
    let lastStatus = '';
    sendEvent('start', { status: 'streaming', task_id: taskId });
    try {
      while (!disconnected && !reply.raw.destroyed && !reply.raw.writableEnded) {
        const response = await fetch(`${phpApiBase}/dashboard/agent-task/${encodeURIComponent(taskId)}`, {
          method: 'GET', headers: phpHeaders, signal: AbortSignal.timeout(12000),
        });
        if (response.status === 404) {
          sendEvent('error', { message: 'Task not found' });
          break;
        }
        if (!response.ok) throw new Error(`PHP task status returned ${response.status}`);
        const envelope = await response.json();
        const task = envelope?.data ?? envelope;
        const status = String(task?.status || 'unknown');
        if (status !== lastStatus) {
          sendEvent('status', { text: task?.message || status, status, progress: task?.progress ?? 0 });
          lastStatus = status;
        }
        if (['completed', 'failed', 'cancelled'].includes(status)) {
          if (status === 'completed') sendEvent('final', { result: task.result });
          else if (status === 'failed') sendEvent('error', { message: task?.error || 'The assistant could not complete this task.' });
          else sendEvent('error', { message: 'Task was cancelled.', terminal: true });
          break;
        }
        await new Promise((resolve) => setTimeout(resolve, 1200));
      }
    } catch (e) {
      if (!disconnected) {
        logger.error({ errorType: e.name }, 'Agent task status stream failed');
        sendEvent('error', { message: 'Task progress is temporarily unavailable', retryable: true });
      }
    } finally {
      closeStream();
    }
  };

  app.get('/api/agents/task/:id/stream', streamAgentTask);
  app.get('/api/agents/tasks/:id/stream', streamAgentTask);

  /**
   * POST /api/agents/public-call
   * Public assistant (NO AUTH, rate-limited)
   * Proxies to PHP /api/public/ai-faq
   */
  app.post('/api/agents/public-call', async (req, reply) => {
    if (await getThreatDetector().isBlocked(req.ip)) {
      return reply.code(403).send({ error: 'Access temporarily blocked' });
    }

    // Rate limit
    const rateLimit = await checkPublicRateLimit(req.ip);
    if (!rateLimit.allowed) {
      return reply.code(429).send({ error: 'Rate limit exceeded', retryAfter: rateLimit.retryAfter });
    }

    const body = req.body || {};
    const { question } = body;
    const conversation = Array.isArray(body.conversation) ? body.conversation.slice(-6) : [];

    if (!question || typeof question !== 'string' || question.trim() === '' || question.length > 500) {
      return reply.code(422).send({ error: 'A question of up to 500 characters is required' });
    }

    // Threat detection: record query for analysis (anonymous user)
    void getThreatDetector().recordQuery(req.ip, 'anonymous', question).catch(() => {});

    // Proxy to PHP /api/public/ai-faq (public endpoint, no auth)
    try {
      const phpUrl = `${phpApiBase}/public/ai-faq`;
      const phpResponse = await fetch(phpUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ question, conversation }),
      });

      if (!phpResponse.ok) {
        const error = await phpResponse.text();
        throw new Error(`PHP public assistant error: ${phpResponse.status} ${error}`);
      }

      const result = await phpResponse.json();
      return result.data || result;
    } catch (e) {
      return reply.code(502).send({ error: 'Public assistant is temporarily unavailable' });
    }
  });

  /**
* GET /api/agents/capabilities
 * Proxy to Python agent catalog (which has all guardrails)
 */
app.get('/api/agents/capabilities', async (req, reply) => {
  const auth = checkAuth(req);
  if (!auth) return reply.code(401).send({ error: 'Unauthorized' });

  try {
    const response = await fetch(`${pythonBaseUrl}/api/agents/catalog`, {
      headers: {
        'Authorization': `Bearer ${pythonSecret}`,
      },
    });
    const data = await response.json();
    return data;
  } catch (e) {
    logger.error({ err: e.message }, 'Failed to fetch agent catalog');
    return reply.code(502).send({ error: 'Failed to fetch capabilities' });
  }
});
}
