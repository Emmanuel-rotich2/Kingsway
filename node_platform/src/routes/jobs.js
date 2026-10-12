import { getConfig } from '../config/index.js';
import { getLogger } from '../logger/index.js';
import { getJobRegistry } from '../queue/jobRegistry.js';
import { verifyWorkerSecret } from '../utils/verifySecret.js';

export function registerJobRoutes(app) {
  const config = getConfig();
  const logger = getLogger().child({ component: 'routes:jobs' });

  function check(req) {
    return verifyWorkerSecret(req, config);
  }

  app.post('/internal/queue/enqueue', async (req, reply) => {
    if (!check(req)) return reply.code(403).send({ error: 'Forbidden' });

    const { job_type, payload = {}, priority = 'normal', operator_id = 0, idempotency_key } = req.body;
    if (!job_type) return reply.code(422).send({ error: 'job_type required' });

    try {
      const result = await app.kingsway.internalClient.post('php', '/node/internal-queue/enqueue', {
        job_type,
        payload,
        priority,
        operator_id,
        idempotency_key,
      });

      const jobId = result?.data?.job_id;
      if (jobId && Number(operator_id) > 0) {
        await app.kingsway.sse.emitKingsway('JOB_PROGRESS', `users:${operator_id}`, {
          job_id: jobId,
          progress: 0,
          status: 'queued',
          domain: 'queue',
        });
      }

      return reply.code(202).send({ job_id: jobId, status: 'queued', deduplicated: !!result?.data?.deduplicated });
    } catch (e) {
      logger.error({ err: e.message }, 'Enqueue failed');
      return reply.code(502).send({ error: 'Failed to enqueue job' });
    }
  });

  app.post('/internal/queue/recover', async (req, reply) => {
    if (!check(req)) return reply.code(403).send({ error: 'Forbidden' });

    const { lease_minutes } = req.body || {};
    try {
      const result = await app.kingsway.internalClient.post('php', '/node/internal-queue-recover', {
        lease_minutes: Math.max(1, Math.min(60, Number(lease_minutes) || 15)),
      });
      return { ok: true, recovered: result?.data?.recovered || 0 };
    } catch (e) {
      logger.error({ err: e.message }, 'Recover failed');
      return reply.code(502).send({ error: 'Failed to recover stale jobs' });
    }
  });

  app.post('/internal/jobs/enqueue', async (req, reply) => {
    return app.inject({ method: 'POST', url: '/internal/queue/enqueue', payload: req.body, headers: req.headers });
  });

  app.get('/internal/jobs/:jobId/status', async (req, reply) => {
    if (!check(req)) return reply.code(403).send({ error: 'Forbidden' });

    const { jobId } = req.params;
    try {
      const result = await app.kingsway.internalClient.get('php', '/node/internal-queue-status', {
        params: { job_id: jobId },
      });
      return result;
    } catch (e) {
      return reply.code(502).send({ error: 'Failed to get job status' });
    }
  });

  app.get('/internal/jobs/:jobId/result', async (req, reply) => {
    if (!check(req)) return reply.code(403).send({ error: 'Forbidden' });

    const jobId = Number(req.params.jobId);
    const operatorId = Number(req.query?.operator_id);
    if (!Number.isSafeInteger(jobId) || jobId < 1
      || !Number.isSafeInteger(operatorId) || operatorId < 1) {
      return reply.code(422).send({ error: 'Invalid job result request' });
    }

    const record = getJobRegistry().get(jobId);
    if (!record || Number(record.userId) !== operatorId || record.result === undefined) {
      return reply.code(404).send({ error: 'Job result not found' });
    }
    return { job_id: jobId, result: record.result };
  });

  app.post('/internal/jobs/complete', async (req, reply) => {
    if (!check(req)) return reply.code(403).send({ error: 'Forbidden' });

    const { job_id, result } = req.body;
    if (!job_id) return reply.code(422).send({ error: 'job_id required' });

    try {
      await app.kingsway.internalClient.post('php', '/node/internal-queue/complete', { job_id, result });
      return { ok: true };
    } catch (e) {
      return reply.code(502).send({ error: 'Failed to complete job' });
    }
  });

  app.post('/internal/jobs/failed', async (req, reply) => {
    if (!check(req)) return reply.code(403).send({ error: 'Forbidden' });

    const { job_id, reason } = req.body;
    if (!job_id) return reply.code(422).send({ error: 'job_id required' });

    try {
      await app.kingsway.internalClient.post('php', '/node/internal-queue/failed', { job_id, reason });
      return { ok: true };
    } catch (e) {
      return reply.code(502).send({ error: 'Failed to mark job failed' });
    }
  });

  app.post('/internal/cache/invalidate', async (req, reply) => {
    if (!check(req)) return reply.code(403).send({ error: 'Forbidden' });

    const { patterns } = req.body;
    const list = Array.isArray(patterns) ? patterns : (typeof patterns === 'string' ? [patterns] : []);

    for (const pattern of list) {
      if (pattern) await app.kingsway.cache.delPattern(pattern);
    }

    return { ok: true, cleared: list.length };
  });
}
