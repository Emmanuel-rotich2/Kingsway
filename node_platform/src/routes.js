'use strict';

/**
 * HTTP surface.
 *
 * Publicly reachable: `/events` only, and only with a valid PHP-minted
 * capability. Everything under `/internal/*` requires the shared worker secret
 * AND a permitted source address — the secret alone is deliberately not
 * sufficient from an arbitrary network peer. There is no public health or
 * metrics endpoint; observability stays internal, like PHP's.
 */

const { hasPublishCredential, verifyCapability } = require('./auth');
const { buildEvent, publishSchema, presenceSchema } = require('./protocol');
const { allowedOrigins } = require('./config');
const { createEventsHandler } = require('./sse');

function createRoutes({ config, logger, metrics, registry, state, dispatcher, bus }) {
  const { handleEvents } = createEventsHandler({
    registry,
    // Bound once here so the SSE handler verifies against this process's
    // secrets without having to thread config through every call.
    config: {
      ...config,
      allowedOrigins: allowedOrigins(config),
      verifyCapability: (token) => verifyCapability(token, config),
    },
    logger,
    metrics,
    serverEventId: () => dispatcher.nextId() - 1,
  });

  // Fastify cannot register a catch-all method over an existing route (it throws
  // FST_ERR_DUPLICATED_ROUTE), so a wrong method surfaces through the
  // not-found handler. Answering 405 instead of a misleading 404 matters here:
  // a publisher using GET must be told its METHOD is wrong, not that the
  // endpoint does not exist.
  const ROUTE_METHODS = new Map([
    ['/events', new Set(['GET'])],
    ['/presence', new Set(['POST'])],
    ['/internal/publish', new Set(['POST'])],
    ['/internal/stats', new Set(['GET'])],
    ['/internal/metrics', new Set(['GET'])],
  ]);

  /** @returns {boolean} true when the request should become a 405. */
  function wrongMethod(req) {
    const pathname = String(req.url || '').split('?')[0];
    const allowed = ROUTE_METHODS.get(pathname);
    if (!allowed) return false;
    if (allowed.has(req.method)) return false;
    // HEAD is served by Fastify wherever GET is.
    if (req.method === 'HEAD' && allowed.has('GET')) return false;
    return true;
  }

  function guarded(reply) {
    if (hasPublishCredential(reply.request, config)) return false;
    if (metrics) metrics.connectionRefused('worker_secret');
    return reply.code(403).header('cache-control', 'no-store').send({ error: 'forbidden' });
  }

  function capabilityOf(req) {
    return verifyCapability(req.headers['x-kingsway-sse-token'] || '', config);
  }

  function register(fastify) {
    // ---- Browser-facing SSE stream --------------------------------------
    fastify.get('/events', handleEvents);

    // ---- Presence: capability-authenticated, ephemeral edit declarations --
    fastify.post('/presence', { schema: presenceSchema }, async (req, reply) => {
      const capability = capabilityOf(req);
      if (!capability) {
        if (metrics) metrics.connectionRefused('auth');
        return reply.code(401).send({ error: 'valid realtime capability required' });
      }
      const { activity_key: activityKey, state: presenceState, scope } = req.body || {};

      let updated = 0;
      for (const client of registry.clients) {
        if (client.userId !== capability.userId) continue;
        if (presenceState === 'idle') registry.clearActivity(client, activityKey);
        else registry.setActivity(client, activityKey, presenceState, scope);
        updated += 1;
      }

      // Announced only for a real edit; 'idle' is a local un-declare and the
      // expiry sweeper is the backstop if the browser vanishes mid-edit.
      if (presenceState !== 'idle') {
        dispatcher.announcePresence({
          userId: capability.userId,
          activityKey,
          state: presenceState,
          scope: scope || 'all',
        });
      }
      return reply.code(200).send({ ok: true, connections: updated });
    });

    // ---- Internal publisher ---------------------------------------------
    fastify.post('/internal/publish', { schema: publishSchema }, async (req, reply) => {
      if (guarded(reply)) return reply;
      if (metrics) metrics.publishRejected('unauthorized');

      let event;
      try {
        event = buildEvent(req.body);
      } catch (error) {
        if (error?.code === 'INVALID_DESCRIPTOR') {
          if (metrics) metrics.publishRejected('descriptor');
          if (logger) logger.journal('publish.rejected', { reason: error.message });
          return reply.code(400).send({ error: error.message });
        }
        throw error;
      }

      if (!event) {
        if (metrics) metrics.publishRejected('descriptor');
        return reply.code(400).send({ error: 'invalid event descriptor' });
      }

      if (logger) logger.journal('publish.accepted', { type: event.type, scope: event.scope });

      // One path: the bus emits locally and forwards to sibling instances, and
      // the dispatcher subscribed to the bus does the coalescing and fan-out.
      const outcome = await bus.publish(event);
      return reply.code(202).send({
        accepted: true,
        delivered: outcome.delivered,
        coalesced: outcome.queued > 0,
        forwarded: outcome.forwarded,
      });
    });

    // ---- Deterministic counters (kept: PHP/ops already read this) --------
    fastify.get('/internal/stats', async (req, reply) => {
      if (guarded(reply)) return reply;
      let queueDepth = 0;
      let backlogBytes = 0;
      for (const client of registry.clients) {
        queueDepth += client.queue.length;
        backlogBytes += client.queuedBytes;
      }
      return reply.send({
        ok: true,
        uptime_seconds: state.stats.started_at
          ? Math.floor((Date.now() - state.stats.started_at) / 1000)
          : 0,
        ...state.stats,
        connections_active: registry.size,
        queue_depth: queueDepth,
        backlog_bytes: backlogBytes,
        bus_mode: bus.modeName,
        coalesce_pending: dispatcher.coalescer.size(),
      });
    });

    // ---- Prometheus exposition -------------------------------------------
    fastify.get('/internal/metrics', async (req, reply) => {
      if (guarded(reply)) return reply;
      reply.header('content-type', metrics.contentType);
      return reply.send(await metrics.render());
    });
  }

  return { register, wrongMethod };
}

module.exports = { createRoutes };