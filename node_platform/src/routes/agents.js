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
      const userId = String(decoded?.sub ?? '');
      if (!/^[1-9]\d{0,18}$/.test(userId)) return null;
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
        },
        body: JSON.stringify({ question, route, module, session_id, ...body.options }),
      });

      if (!phpResponse.ok) {
        const error = await phpResponse.text();
        throw new Error(`PHP agent error: ${phpResponse.status} ${error}`);
      }

      const result = await phpResponse.json();
      return result.data || result;
    } catch (e) {
      return reply.code(502).send({ error: 'Agent call failed', details: e.message });
    }
  });

  /**
   * POST /api/agents/stream
   * Proxy to existing PHP agent-assist-stream endpoint with SSE relay
   */
  app.post('/api/agents/stream', async (req, reply) => {
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

    // SSE response setup
    const headers = {
      'Content-Type': 'text/event-stream',
      'Cache-Control': 'no-cache, no-store, private',
      'Connection': 'keep-alive',
      'X-Accel-Buffering': 'no',
    };

    const requestOrigin = req.headers.origin;
    if (requestOrigin && config.security.webOrigins.includes(requestOrigin)) {
      reply.header('Access-Control-Allow-Origin', requestOrigin);
      reply.header('Access-Control-Allow-Credentials', 'true');
      reply.header('Vary', 'Origin');
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

    try {
      // Proxy to existing PHP agent-assist-stream endpoint
      const phpUrl = `${phpApiBase}/dashboard/agent-assist-stream`;
      const phpResponse = await fetch(phpUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'text/event-stream',
          'Authorization': req.headers.authorization,
          'X-CSRF-Token': req.headers['x-csrf-token'] || '',
          'X-Internal-User-Id': auth.userId,
          'X-Internal-Permissions': JSON.stringify(auth.permissions),
          'X-Internal-Roles': JSON.stringify(auth.roles),
          'X-Internal-Secret': internalSecret,
        },
        body: JSON.stringify({ question, route, module, session_id, ...body.options }),
      });

      if (!phpResponse.ok) {
        const error = await phpResponse.text();
        throw new Error(`PHP agent stream error: ${phpResponse.status} ${error}`);
      }

      const reader = phpResponse.body.getReader();
      const decoder = new TextDecoder();
      let buffer = '';

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

      sendEvent('task:start', { type: 'agent_stream' });

      try {
        let buffer = '';

        while (true) {
          const { done, value } = await reader.read();
          if (done) break;
          buffer += decoder.decode(value, { stream: true });
          const lines = buffer.split('\n\n');
          buffer = lines.pop() || '';

          for (const line of lines) {
            if (line.startsWith('data: ')) {
              try {
                const data = JSON.parse(line.slice(6));
                // Forward all PHP SSE events to browser
                if (data.kind === 'delta') {
                  sendEvent('delta', { text: data.text });
                } else if (data.kind === 'status') {
                  sendEvent('status', { text: data.text });
                } else if (data.kind === 'final') {
                  sendEvent('final', { result: data });
                } else if (data.kind === 'status') {
                  sendEvent('status', { text: data.text });
                } else if (data.kind === 'error') {
                  sendEvent('error', { message: data.message });
                }
              } catch {
                // Ignore parse errors
              }
            }
          }
        }

        // Stream completed
        if (!reply.raw.destroyed && !reply.raw.writableEnded) {
          reply.raw.end();
        }
      } catch (e) {
        logger.error({ err: e.message }, 'Agent stream proxy failed');
        sendEvent('error', { message: e.message });
        closeStream();
      }
    } catch (e) {
      logger.error({ err: e.message }, 'Agent stream proxy failed');
      sendEvent('error', { message: e.message });
      if (!reply.raw.destroyed && !reply.raw.writableEnded) {
        reply.raw.end();
      }
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
    const { tool, question, route = 'dashboard', module = 'dashboard', options = {} } = body;

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
        },
        body: JSON.stringify({ tool, question, route, module, ...options }),
      });

      if (!phpResponse.ok) {
        const error = await phpResponse.text();
        throw new Error(`PHP agent task create error: ${phpResponse.status} ${error}`);
      }

      const result = await phpResponse.json();
      return result.data || result;
    } catch (e) {
      return reply.code(502).send({ error: 'Agent task create failed', details: e.message });
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
      return reply.code(502).send({ error: 'Agent task get failed', details: e.message });
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
      return reply.code(502).send({ error: 'Agent tasks list failed', details: e.message });
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
      return reply.code(502).send({ error: 'Agent task cancel failed', details: e.message });
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
      const userId = String(decoded?.sub ?? '');
      if (!/^[1-9]\d{0,18}$/.test(userId)) return reply.code(401).send({ error: 'Unauthorized' });
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
      reply.header('Access-Control-Allow-Origin', requestOrigin);
      reply.header('Access-Control-Allow-Credentials', 'true');
      reply.header('Vary', 'Origin');
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

    try {
      // Proxy to PHP /dashboard/agent-task-stream/:id
      const phpUrl = `${phpApiBase}/dashboard/agent-task-stream/${req.params.id}`;
      const phpResponse = await fetch(phpUrl, {
        method: 'POST', // Use POST to send context in body
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'text/event-stream',
          'Authorization': `Bearer ${token}`,
          'X-Internal-User-Id': auth.userId,
          'X-Internal-Permissions': JSON.stringify(auth.permissions),
          'X-Internal-Roles': JSON.stringify(auth.roles),
          'X-Internal-Secret': internalSecret,
        },
        body: JSON.stringify({}), // context is derived from token on PHP side
      });

      if (!phpResponse.ok) {
        const error = await phpResponse.text();
        throw new Error(`PHP agent task stream error: ${phpResponse.status} ${error}`);
      }

      const reader = phpResponse.body.getReader();
      const decoder = new TextDecoder();
      let buffer = '';

      sendEvent('task:start', { type: 'task_stream', taskId: req.params.id });

      try {
        while (true) {
          const { done, value } = await reader.read();
          if (done) break;
          buffer += decoder.decode(value, { stream: true });
          const lines = buffer.split('\n\n');
          buffer = lines.pop() || '';

          for (const line of lines) {
            if (line.startsWith('data: ')) {
              try {
                const data = JSON.parse(line.slice(6));
                // Forward all PHP SSE events to browser
                if (data.kind === 'delta') {
                  sendEvent('delta', { text: data.text });
                } else if (data.kind === 'status') {
                  sendEvent('status', { text: data.text });
                } else if (data.kind === 'final') {
                  sendEvent('final', { result: data });
                } else if (data.kind === 'error') {
                  sendEvent('error', { message: data.message });
                }
              } catch {
                // Ignore parse errors
              }
            }
          }
        }

        if (!reply.raw.destroyed && !reply.raw.writableEnded) {
          reply.raw.end();
        }
      } catch (e) {
        logger.error({ err: e.message }, 'Agent task stream proxy failed');
        sendEvent('error', { message: e.message });
        closeStream();
      }
    } catch (e) {
      logger.error({ err: e.message }, 'Agent task stream proxy failed');
      sendEvent('error', { message: e.message });
      if (!reply.raw.destroyed && !reply.raw.writableEnded) {
        reply.raw.end();
      }
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
      return reply.code(502).send({ error: 'Public assistant failed', details: e.message });
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
          'X-Internal-Secret': internalSecret,
          'X-User-Id': auth.userId,
          'X-Permissions': JSON.stringify(auth.permissions),
          'X-Roles': JSON.stringify(auth.roles),
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