import { Registry, collectDefaultMetrics, Counter, Gauge, Histogram } from 'prom-client';
import { getConfig } from '../config/index.js';

let metricsCache = null;
let registerCache = null;

export function createMetrics(options = {}) {
  if (metricsCache) return metricsCache;

  const config = getConfig();
  const prefix = options.prefix || config.metrics.prefix;

  const register = new Registry();
  if (options.collectDefaults !== false && config.metrics.collectDefault) {
    collectDefaultMetrics({ register, prefix });
  }

  const metrics = {
    register,
    prefix,

    jobsQueued: new Counter({
      name: `${prefix}jobs_queued_total`,
      help: 'Total jobs queued by type and priority',
      labelNames: ['job_type', 'priority'],
      registers: [register],
    }),

    jobsProcessing: new Gauge({
      name: `${prefix}jobs_processing`,
      help: 'Jobs currently being processed',
      labelNames: ['job_type'],
      registers: [register],
    }),

    jobsCompleted: new Counter({
      name: `${prefix}jobs_completed_total`,
      help: 'Total jobs completed by type and status',
      labelNames: ['job_type', 'status'],
      registers: [register],
    }),

    jobDuration: new Histogram({
      name: `${prefix}job_duration_seconds`,
      help: 'Job processing duration in seconds',
      labelNames: ['job_type'],
      buckets: [0.1, 0.5, 1, 2, 5, 10, 30, 60, 120, 300],
      registers: [register],
    }),

    sseConnections: new Gauge({
      name: `${prefix}sse_connections_active`,
      help: 'Active SSE connections',
      labelNames: ['channel_type'],
      registers: [register],
    }),

    sseMessagesSent: new Counter({
      name: `${prefix}sse_messages_sent_total`,
      help: 'Total SSE messages sent',
      labelNames: ['channel_type'],
      registers: [register],
    }),

    internalHttpRequests: new Counter({
      name: `${prefix}internal_http_requests_total`,
      help: 'Internal HTTP requests',
      labelNames: ['service', 'endpoint', 'status'],
      registers: [register],
    }),

    cacheHits: new Counter({
      name: `${prefix}cache_hits_total`,
      help: 'Cache hits by type',
      labelNames: ['cache_type'],
      registers: [register],
    }),

    viewResolves: new Counter({
      name: `${prefix}view_resolves_total`,
      help: 'View model resolutions',
      labelNames: ['entity', 'view', 'status'],
      registers: [register],
    }),

    aggregationDuration: new Histogram({
      name: `${prefix}aggregation_duration_seconds`,
      help: 'Aggregation engine duration',
      labelNames: ['operation'],
      buckets: [0.01, 0.05, 0.1, 0.5, 1, 2, 5, 10],
      registers: [register],
    }),
  };

  metricsCache = metrics;
  registerCache = register;
  return metrics;
}

export function getMetrics() {
  return metricsCache || createMetrics();
}

export function getRegistry() {
  return registerCache || getMetrics().register;
}

export function resetMetrics() {
  metricsCache = null;
  registerCache = null;
}