'use strict';

/**
 * Prometheus metrics for the realtime gateway.
 *
 * The architecture document (§9) requires that capacity claims come from
 * measurements, not assumptions. These are the four signals that actually
 * predict an SSE server's health, and that standard HTTP request metrics do
 * NOT capture because every stream is one multi-hour "request":
 *
 *   1. open connections            — gauge   (grows unbounded if sockets leak)
 *   2. events published / delivered — counters (fan-out ratio; a gap means a
 *                                            scope-routing bug, not load)
 *   3. per-client backlog + slow-client evictions — histogram/counter
 *                                            (the silent memory killer)
 *   4. delivery latency             — histogram (publish → socket write)
 *
 * Registered on a private Registry (not the global default) so tests can create
 * isolated metrics instances without duplicate-registration errors.
 */

const client = require('prom-client');

function createMetrics({ collectDefaults = false, prefix = 'kingsway_' } = {}) {
  const registry = new client.Registry();
  if (collectDefaults) {
    client.collectDefaultMetrics({ register: registry, prefix: `${prefix}process_` });
  }

  const connectionsActive = new client.Gauge({
    name: `${prefix}realtime_connections_active`,
    help: 'Currently open SSE connections held by this process',
    registers: [registry],
  });

  const connectionsOpened = new client.Counter({
    name: `${prefix}realtime_connections_opened_total`,
    help: 'SSE connections accepted since process start',
    registers: [registry],
  });

  const connectionsRefused = new client.Counter({
    name: `${prefix}realtime_connections_refused_total`,
    help: 'SSE connections refused (pool full or auth failure)',
    labelNames: ['reason'],
    registers: [registry],
  });

  const eventsPublished = new client.Counter({
    name: `${prefix}realtime_events_published_total`,
    help: 'Events accepted from PHP/Python publishers',
    labelNames: ['type'],
    registers: [registry],
  });

  const eventsDelivered = new client.Counter({
    name: `${prefix}realtime_events_delivered_total`,
    help: 'Events actually written to a client socket',
    labelNames: ['type'],
    registers: [registry],
  });

  const eventsSuppressed = new client.Counter({
    name: `${prefix}realtime_events_suppressed_total`,
    help: 'Events withheld from a connection because that user is actively editing the row',
    labelNames: ['type'],
    registers: [registry],
  });

  const coalesced = new client.Counter({
    name: `${prefix}realtime_events_coalesced_total`,
    help: 'Duplicate refresh signals collapsed before fan-out',
    labelNames: ['type'],
    registers: [registry],
  });

  const publishRejected = new client.Counter({
    name: `${prefix}realtime_publish_rejected_total`,
    help: 'Publish requests rejected before fan-out',
    labelNames: ['reason'],
    registers: [registry],
  });

  const slowClientEvictions = new client.Counter({
    name: `${prefix}realtime_slow_client_evictions_total`,
    help: 'Connections destroyed because the socket did not drain within the timeout',
    registers: [registry],
  });

  const backlogBytes = new client.Gauge({
    name: `${prefix}realtime_client_backlog_bytes`,
    help: 'Total unsent bytes buffered across all client sockets',
    registers: [registry],
  });

  const deliveryLatency = new client.Histogram({
    name: `${prefix}realtime_delivery_latency_seconds`,
    help: 'Time from publish acceptance to socket write',
    labelNames: ['type'],
    // Buckets tuned for sub-second in-process delivery; anything over 1s means
    // the event loop was blocked.
    buckets: [0.001, 0.005, 0.01, 0.05, 0.1, 0.5, 1, 5],
    registers: [registry],
  });

  const busUp = new client.Gauge({
    name: `${prefix}realtime_bus_up`,
    help: 'Cross-instance event bus state (1 = in-process, 2 = redis, 0 = unavailable)',
    registers: [registry],
  });

  const presenceActive = new client.Gauge({
    name: `${prefix}realtime_presence_active`,
    help: 'Active edit/presence declarations currently held',
    registers: [registry],
  });

  let backlog = 0;

  return {
    registry,
    busUp,
    backlogBytes,
    presenceActive,

    connectionOpened() {
      connectionsOpened.inc();
    },
    connectionClosed() {
      connectionsActive.dec();
    },
    connectionRefused(reason) {
      connectionsRefused.inc({ reason: String(reason || 'unknown') });
    },
    published(type) {
      eventsPublished.inc({ type });
    },
    delivered(type, seconds) {
      eventsDelivered.inc({ type });
      if (Number.isFinite(seconds)) deliveryLatency.observe({ type }, seconds);
    },
    suppressed(type) {
      eventsSuppressed.inc({ type });
    },
    coalesced(type) {
      coalesced.inc({ type });
    },
    publishRejected(reason) {
      publishRejected.inc({ reason: String(reason || 'unknown') });
    },
    slowClientEvicted() {
      slowClientEvictions.inc();
    },
    setConnections(n) {
      connectionsActive.set(n);
    },
    addBacklog(bytes) {
      backlog += bytes;
      backlogBytes.set(Math.max(0, backlog));
    },
    clearBacklog(bytes) {
      backlog = Math.max(0, backlog - bytes);
      backlogBytes.set(backlog);
    },
    setPresence(n) {
      presenceActive.set(n);
    },
    async render() {
      return registry.metrics();
    },
    get contentType() {
      return registry.contentType;
    },
  };
}

module.exports = { createMetrics };