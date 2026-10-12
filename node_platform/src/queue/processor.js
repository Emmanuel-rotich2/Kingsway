import { getConfig } from '../config/index.js';
import { getLogger } from '../logger/index.js';
import { getMetrics } from '../metrics/index.js';
import { getInternalClient } from '../clients/internal.js';
import { getCache } from '../clients/cache.js';
import { getBus } from '../realtime/bus.js';
import { getSSE } from '../realtime/sse.js';
import { getAggregationEngine } from '../aggregation/engine.js';
import { getJobRegistry } from './jobRegistry.js';
import { viewSpecs } from '../views/specs/index.js';

let processorCache = null;

export function createJobProcessor() {
  if (processorCache) return processorCache;

  const config = getConfig();
  const logger = getLogger().child({ component: 'job-processor' });
  const metrics = getMetrics();
  const internalClient = getInternalClient();
  const cache = getCache();
  const bus = getBus();
  const sse = getSSE();
  const aggregationEngine = getAggregationEngine();
  const registry = getJobRegistry();

  const handlers = new Map();
  let running = false;
  let pollTimers = {};
  const laneInFlight = { critical: 0, normal: 0, batch: 0 };
  const typedRunning = new Map();
  const pendingByType = new Map();

  function registerHandler(jobType, handler, options = {}) {
    handlers.set(jobType, { handler, options });
    logger.info({ jobType }, 'Registered job handler');
  }

  function laneKey(priority) {
    return ['critical', 'normal'].includes(priority) ? priority : 'batch';
  }

  async function pollQueue(priority) {
    if (!running) return;

    const lane = laneKey(priority);
    const pollInterval = config.queue.pollIntervalMs[lane] || 5000;
    const concurrency = config.queue.concurrency[lane] || 5;
    const available = Math.max(0, concurrency - laneInFlight[lane]);
    if (available <= 0) {
      pollTimers[priority] = setTimeout(() => pollQueue(priority), pollInterval);
      return;
    }

    try {
      const response = await internalClient.post('php', '/node/internal-queue/claim', {
        lane,
        priority: priority === 'critical' ? 10 : priority === 'normal' ? 5 : 1,
        limit: Math.min(config.queue.concurrency[priority] || 5, available),
      });

      const jobs = response?.data?.jobs || [];
      laneInFlight[lane] += jobs.length;
      for (const job of jobs) {
        processJob(job).catch(e => logger.error({ err: e.message, jobId: job.id }, 'Job processing failed'));
      }
    } catch (e) {
      logger.debug({ err: e.message, lane }, 'Queue poll failed');
    }

    pollTimers[priority] = setTimeout(() => pollQueue(priority), pollInterval);
  }

  function runNextForType(jobType) {
    const pending = pendingByType.get(jobType);
    if (!pending || pending.length === 0) return;
    const job = pending.shift();
    if (pending.length === 0) pendingByType.delete(jobType);
    invokeHandler(job).catch(e => logger.error({ err: e.message, jobId: job.id }, 'Job handling failed'));
  }

  async function processJob(job) {
    const jobType = job.job_type;
    const handlerEntry = handlers.get(jobType);
    const cap = Number.isInteger(handlerEntry?.options?.concurrency) && handlerEntry.options.concurrency > 0
      ? handlerEntry.options.concurrency
      : Number.POSITIVE_INFINITY; // per-type rate limit (default: unbounded)
    const current = typedRunning.get(jobType) || 0;
    if (current >= cap) {
      if (!pendingByType.has(jobType)) pendingByType.set(jobType, []);
      pendingByType.get(jobType).push(job);
      return;
    }
    await invokeHandler(job);
  }

  async function invokeHandler(job) {
    const jobId = job.id;
    const jobType = job.job_type;
    const payload = job.payload;
    const operatorId = payload.operator_id || payload.userId || 0;
    const attempts = job.attempts || 1;
    // The durable PHP queue owns the priority. Ignore any similarly named
    // field inside the user payload when releasing the claimed lane slot.
    const lane = laneKey(job.priority || 'normal');

    typedRunning.set(jobType, (typedRunning.get(jobType) || 0) + 1);
    const releaseSlot = () => {
      typedRunning.set(jobType, Math.max(0, (typedRunning.get(jobType) || 1) - 1));
      laneInFlight[lane] = Math.max(0, laneInFlight[lane] - 1);
      runNextForType(jobType);
    };

    const handlerEntry = handlers.get(jobType);
    if (!handlerEntry) {
      logger.warn({ jobId, jobType }, 'No handler for job type');
      registry?.markFailed(jobId, `No handler for job type: ${jobType}`);
      releaseSlot();
      return;
    }

    const { handler } = handlerEntry;
    const startTime = Date.now();
    registry?.register({
      id: jobId,
      name: jobType,
      userId: operatorId,
      channels: Array.isArray(payload.channels) ? payload.channels : [],
      priority: payload.priority || job.priority || handlerEntry.options?.priority || 'normal',
    });
    metrics.jobsProcessing.inc({ job_type: jobType });

    try {
      logger.info({ jobId, jobType, operatorId, attempt: attempts }, 'Processing job');

      await internalClient.post('php', '/node/internal-queue/processing', { job_id: jobId });
      registry?.markProcessing(jobId);
      registry?.markProgress(jobId, 5);
      if (operatorId > 0) {
        try {
          await sse.publishToUser(operatorId, `job:${jobId}:progress`, { progress: 5, status: 'processing', jobId });
        } catch (error) {
          logger.warn({ err: error.message, jobId }, 'Initial job progress notification failed');
        }
      }

      const deps = {
        internalClient,
        cache,
        sse,
        bus,
        aggregationEngine,
        viewSpecs,
        logger: logger.child({ jobId, jobType }),
        metrics,
        config,
        jobData: { jobId, jobType, payload, operatorId, attempts },
        progress: async (fraction) => {
          const progress = Math.max(0, Math.min(100, Math.round(Number(fraction) * 100)));
          registry?.markProgress(jobId, progress);
          if (operatorId > 0) {
            try {
              await sse.publishToUser(operatorId, `job:${jobId}:progress`, { jobId, status: 'progress', progress });
              await sse.emitKingsway('JOB_PROGRESS', `users:${operatorId}`, { job_id: jobId, progress });
            } catch (error) {
              logger.warn({ err: error.message, jobId }, 'Job progress notification failed');
            }
          }
          return progress;
        },
      };

      const result = await handler({ ...payload, jobId, operatorId }, deps);

      // Keep the result private in Node's owner-scoped registry. Completion
      // events carry only a signal; PHP authorizes a later result request.
      registry?.storeResult(jobId, result);
      await internalClient.post('php', '/node/internal-queue/complete', { job_id: jobId });
      registry?.markComplete(jobId);

      metrics.jobsCompleted.inc({ job_type: jobType, status: 'done' });
      metrics.jobDuration.observe({ job_type: jobType }, (Date.now() - startTime) / 1000);
      logger.info({ jobId, jobType, durationMs: Date.now() - startTime }, 'Job completed');

      // PHP's completion is authoritative. A realtime transport failure after
      // that commit must not reclassify a completed job as failed.
      try {
        if (operatorId > 0) {
          await sse.emitKingsway('JOB_COMPLETE', `users:${operatorId}`, {
            job_id: jobId,
            status: 'done',
            domain: 'queue',
          });
        }
      } catch (error) {
        logger.warn({ err: error.message, jobId }, 'Job completed but realtime notification failed');
      }
    } catch (e) {
      logger.error({ err: e.message, jobId, jobType, attempt: attempts }, 'Job failed');

      const terminalFailure = attempts >= config.queue.maxAttempts;
      if (terminalFailure) {
        registry?.markFailed(jobId, e.message);
        await markFailed(jobId, e.message);
        metrics.jobsCompleted.inc({ job_type: jobType, status: 'failed' });
      } else {
        registry?.markProgress(jobId, 0);
        try {
          await internalClient.post('php', '/node/internal-queue/failed', { job_id: jobId, reason: e.message });
        } catch (queueError) {
          logger.error({ err: queueError.message, jobId }, 'Failed to return job to the PHP retry queue');
        }
      }

      metrics.jobDuration.observe({ job_type: jobType }, (Date.now() - startTime) / 1000);
      const publicError = 'This operation could not be completed. Try again later.';
      try {
        if (operatorId > 0) {
          if (terminalFailure) {
            await sse.emitKingsway('JOB_FAILED', `users:${operatorId}`, {
              job_id: jobId,
              status: 'failed',
              domain: 'queue',
              error: publicError,
            });
          } else {
            await sse.emitKingsway('JOB_PROGRESS', `users:${operatorId}`, {
              job_id: jobId,
              progress: 0,
              status: 'retrying',
              domain: 'queue',
            });
          }
        }
      } catch (notifyError) {
        logger.warn({ err: notifyError.message, jobId }, 'Job failure notification failed');
      }
    } finally {
      metrics.jobsProcessing.dec({ job_type: jobType });
      releaseSlot();
    }
  }

  async function markFailed(jobId, reason) {
    try {
      await internalClient.post('php', '/node/internal-queue/failed', { job_id: jobId, reason });
    } catch (e) {
      logger.error({ err: e.message, jobId }, 'Failed to mark job as failed');
    }
  }

  async function start() {
    if (running) return;
    running = true;
    logger.info('Starting job processor');

    for (const priority of ['critical', 'normal', 'batch']) {
      pollQueue(priority);
    }
  }

  async function stop() {
    running = false;
    for (const timer of Object.values(pollTimers)) {
      clearTimeout(timer);
    }
    logger.info('Job processor stopped');
  }

  processorCache = {
    registerHandler,
    start,
    stop,
    getRegisteredHandlers: () => Array.from(handlers.keys()),
    getLoad: () => ({
      lanes: { ...laneInFlight },
      typed: Object.fromEntries(typedRunning),
      pendingByType: Object.fromEntries(Array.from(pendingByType).map(([k, v]) => [k, v.length])),
    }),
  };

  return processorCache;
}

export function getJobProcessor() {
  return processorCache || createJobProcessor();
}

export function resetJobProcessor() {
  processorCache = null;
}
