import { getConfig } from '../config/index.js';
import { getLogger } from '../logger/index.js';
import { getBus } from '../realtime/bus.js';
import { broadcast as broadcastDomain } from '../publish/domain.js';
import { getCache } from '../clients/cache.js';
import { getInternalClient } from '../clients/internal.js';
import { verifyWorkerSecret } from '../utils/verifySecret.js';

/**
 * Map a PHP mutation descriptor (domain + target names) onto the entity keys
 * used by composed view cache keys (`view:<entity>/...`). Every listed name is
 * invalidated verbatim; aliases cover plural/singular and sub-domain drift.
 */
const ENTITY_ALIASES = {
  assessments: ['academic'],
  admission: ['admissions'],
  admissions: ['admission'],
  payments: ['finance'],
  student: ['students'],
  users: ['system'],
  communication: ['communications'],
};

export function entitiesForMutation(domain, targets) {
  const names = new Set();
  for (const name of [domain, ...(Array.isArray(targets) ? targets : [])]) {
    if (typeof name !== 'string' || name.trim() === '') continue;
    const clean = name.trim().toLowerCase().replace(/[^a-z0-9_-]/g, '');
    if (!clean) continue;
    names.add(clean);
    for (const alias of ENTITY_ALIASES[clean] || []) names.add(alias);
  }
  return names;
}

/**
 * In-memory write job progress tracker (non-authoritative).
 * PHP owns the durable queue; Node only mirrors progress for SSE relay.
 * Keyed by jobId for O(1) lookup; scoped to operator for privacy.
 */
const jobProgress = new Map();

export function registerInternalRoutes(app) {
  const config = getConfig();
  const logger = getLogger().child({ component: 'routes:internal' });
  const bus = getBus();
  const cache = getCache();
  const internalClient = getInternalClient();

  function check(req) {
    return verifyWorkerSecret(req, config);
  }

  app.get('/internal/stats', async (req, reply) => {
    if (!check(req)) return reply.code(403).send({ error: 'Forbidden' });

    const state = app.kingsway?.state;
    const registry = app.kingsway?.registry;
    const sse = app.kingsway?.sse;
    const cache = app.kingsway?.cache;

    return {
      uptime: process.uptime(),
      memory: process.memoryUsage(),
      connections: sse?.getStats?.() || {},
      bus: bus.isHealthy?.() ? 'jsonl-file' : 'unhealthy',
      cache: cache?.getStats?.() || null,
    };
  });

  app.get('/internal/metrics', async (req, reply) => {
    if (!check(req)) return reply.code(403).send({ error: 'Forbidden' });

    const { getRegistry } = await import('../metrics/index.js');
    const registry = getRegistry();
    reply.header('Content-Type', registry.contentType);
    return registry.metrics();
  });

  app.post('/internal/publish', async (req, reply) => {
    if (!check(req)) return reply.code(403).send({ error: 'Forbidden' });

    const body = req.body || {};
    const { correlationId } = body;

    // PHP domain event shape: { type, scope, payload, emitted_at }. The scope
    // is what PHP's RealtimeScopeResolver hands to each connection's capability,
    // so it routes through channelForScope and lands as an `event: kingsway`
    // frame that js/core/realtime_sse.js can forward to RealtimeDispatch.
    if (typeof body.type === 'string' && body.type !== '' && typeof body.scope === 'string') {
      const result = await broadcastDomain({ type: body.type, scope: body.scope, payload: body.payload || {}, correlationId });
      if (!result.ok) return reply.code(422).send({ error: result.reason });

      // A mutation invalidates the composed-view cache for every affected
      // entity so the next read recomputes instead of serving stale data.
      // PHP sends DATA_CHANGED / CACHE_INVALIDATE with { domain, targets }.
      const payload = body.payload || {};
      const isMutationEvent = body.type === 'DATA_CHANGED'
        || body.type === 'CACHE_INVALIDATE'
        || typeof payload.domain === 'string';
      if (isMutationEvent) {
        const entities = entitiesForMutation(payload.domain, payload.targets);
        await Promise.all(
          Array.from(entities, entity => cache.delPattern(`view:${entity}/*`).catch(() => {})),
        );
        logger.debug({ type: body.type, entities: Array.from(entities) }, 'Composed-view cache invalidated');
      }

      return { ok: true, channel: result.channel };
    }

    // Raw bus push shape: { channel, event, payload } — used by gateway-local
    // and test clients that already know their channel.
    const { channel, event, payload } = body;
    if (!channel || !event) return reply.code(422).send({ error: 'channel and event required' });

    await bus.publish(channel, { type: event, payload, correlationId, source: 'internal-api' });
    return { ok: true };
  });

  /**
   * Browser → Node: validate form data, forward to PHP enqueue endpoint.
   * Returns jobId from PHP. Node tracks progress for SSE relay.
   */
  app.post('/internal/jobs/enqueue', async (req, reply) => {
    if (!check(req)) return reply.code(403).send({ error: 'Forbidden' });

    const body = req.body || {};
    const { formType, data, operatorId, targets = [], idempotencyKey } = body;

    if (!formType || !data || !Number.isSafeInteger(Number(operatorId))) {
      return reply.code(422).send({ error: 'formType, data, operatorId required' });
    }

    // Validate & clean form data (basic sanitization)
    const cleaned = {};
    for (const [key, value] of Object.entries(data || {})) {
      if (typeof value === 'string') {
        cleaned[key] = value.trim();
      } else {
        cleaned[key] = value;
      }
    }

    try {
      // Forward to PHP enqueue endpoint (PHP owns durable queue)
      const phpResponse = await internalClient.post('php', '/node/internal-queue/enqueue', {
        formType,
        data: cleaned,
        operator_id: operatorId,
        targets,
        idempotency_key: idempotencyKey,
      }, {
        headers: { 'x-internal-user-id': String(operatorId) },
      });

      const jobId = phpResponse?.jobId || phpResponse?.job_id;
      if (!jobId) {
        throw new Error('PHP did not return jobId');
      }

      // Track progress in-memory for SSE relay
      const key = `${operatorId}:${jobId}`;
      jobProgress.set(key, {
        jobId,
        operatorId,
        state: 'queued',
        progress: 0,
        message: 'Job queued',
        updatedAt: Date.now(),
      });

      logger.info({ jobId, formType, operatorId }, 'Job enqueued via PHP, tracking progress');

      return { jobId, status: 'queued' };
    } catch (e) {
      logger.error({ err: e.message, formType }, 'Job enqueue failed');
      return reply.code(502).send({ error: 'Failed to enqueue job' });
    }
  });

  /**
   * PHP posts job progress updates here. Node mirrors and relays via SSE.
   * Payload: { jobId, operatorId, state, progress, message, result, error, targets, domain }
   * State: queued | processing | completed | failed
   */
  app.post('/internal/jobs/progress', async (req, reply) => {
    if (!check(req)) return reply.code(403).send({ error: 'Forbidden' });

    const body = req.body || {};
    const { jobId, operatorId, state, progress, message, result, error } = body;

    if (!jobId || !Number.isSafeInteger(Number(operatorId)) || !state) {
      return reply.code(422).send({ error: 'jobId, operatorId, state required' });
    }

    const key = `${operatorId}:${jobId}`;
    const now = Date.now();

    const progressEntry = {
      jobId,
      operatorId,
      state,
      progress: Number.isFinite(Number(progress)) ? Number(progress) : 0,
      message: message || '',
      result: result || null,
      error: error || null,
      updatedAt: now,
    };

    jobProgress.set(key, progressEntry);

    // Relay to browser via SSE (scoped to users:<operatorId>)
    try {
      const { broadcast } = await import('../publish/domain.js');
      await broadcast({
        type: 'JOB_PROGRESS',
        scope: `users:${operatorId}`,
        payload: progressEntry,
      });
    } catch (e) {
      logger.warn({ err: e.message, jobId, operatorId }, 'Failed to relay job progress via SSE');
    }

    // On completion/failure, invalidate caches for affected entities
    if (state === 'completed' || state === 'failed') {
      if (body.targets && Array.isArray(body.targets)) {
        const entities = entitiesForMutation(body.domain || '', body.targets);
        await Promise.all(
          Array.from(entities, entity => cache.delPattern(`view:${entity}/*`).catch(() => {})),
        );
        logger.debug({ entities: Array.from(entities) }, 'Cache invalidated on job completion');
      }
    }

    return { ok: true };
  });

  /**
   * Browser polls or reconnects to get current job progress.
   */
  app.get('/internal/jobs/:jobId/progress', async (req, reply) => {
    if (!check(req)) return reply.code(403).send({ error: 'Forbidden' });

    const { jobId } = req.params;
    const { operatorId } = req.query;

    if (!jobId || !Number.isSafeInteger(Number(operatorId))) {
      return reply.code(422).send({ error: 'jobId and operatorId required' });
    }

    const key = `${operatorId}:${jobId}`;
    const entry = jobProgress.get(key);

    if (!entry) {
      return reply.code(404).send({ error: 'Job progress not found' });
    }

    return entry;
  });
}
