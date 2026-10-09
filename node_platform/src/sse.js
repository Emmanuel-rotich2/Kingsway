'use strict';

/**
 * SSE stream handling for GET /events.
 *
 * Contract:
 *  - browser-facing and capability-authenticated: PHP mints the short-lived
 *    JWT after resolving RBAC and row scope, Node verifies signature + time
 *    bounds locally and never calls PHP or the DB;
 *  - the response is hijacked and written raw, because a stream must not be
 *    buffered, compressed or completed by the framework;
 *  - `X-Accel-Buffering: no` plus a comment heartbeat defeat proxy buffering and
 *    idle timeouts, which are the two classic reasons a browser sees a "live"
 *    SSE endpoint that silently delivers nothing;
 *  - the stream carries change DESCRIPTORS only, never record bodies.
 */

const { frameFor } = require('./protocol');

function createEventsHandler({ registry, config, logger, metrics, onRegistered, serverEventId }) {
  const allowedOrigins = config.allowedOrigins || [];

  async function handleEvents(req, reply) {
    // Origin check before anything else. A capability in a query string is
    // enough to authenticate on its own, so the browser origin is a second,
    // independent gate against a leaked token being replayed from elsewhere.
    // An empty allowlist means the check is disabled (loopback development only).
    const origin = req.headers.origin;
    if (allowedOrigins.length && !allowedOrigins.includes(origin)) {
      if (metrics) metrics.connectionRefused('origin');
      if (logger) logger.journal('sse.rejected', { reason: 'origin' });
      return reply.code(403).header('cache-control', 'no-store')
        .send({ error: 'origin denied' });
    }

    // The capability normally arrives in a header; the query parameter is a
    // fallback for EventSource, which cannot set headers. Both are short-lived
    // signed tokens, so neither is a weaker credential.
    const token = req.headers['x-kingsway-sse-token'] || (req.query && req.query.token) || '';
    const verified = config.verifyCapability(token);

    if (!verified) {
      if (metrics) metrics.connectionRefused('auth');
      if (logger) logger.journal('sse.rejected', { reason: 'auth' });
      return reply.code(401).header('cache-control', 'no-store')
        .send({ error: 'valid realtime capability required' });
    }

    const added = registry.add(reply.raw, { userId: verified.userId, channels: verified.channels });
    if (!added.ok) {
      if (metrics) metrics.connectionRefused(added.reason);
      if (logger) logger.journal('sse.rejected', { reason: added.reason, user_id: verified.userId });
      return reply.code(503)
        .header('retry-after', '5')
        .header('cache-control', 'no-store')
        .send({ error: 'realtime connection capacity reached' });
    }

    const client = added.client;

    reply.hijack();
    reply.raw.writeHead(200, {
      'content-type': 'text/event-stream; charset=utf-8',
      'cache-control': 'no-cache, no-transform',
      connection: 'keep-alive',
      // Defeats nginx/ALB response buffering, without which the browser
      // receives nothing until the buffer fills.
      'x-accel-buffering': 'no',
      ...(allowedOrigins.length && origin && allowedOrigins.includes(origin) ? {
        'access-control-allow-origin': origin,
        vary: 'Origin',
      } : {}),
    });

    // Reconnect catch-up hint. The gateway keeps no event log, so a browser
    // that reconnects with a Last-Event-ID must resync through PHP. Handing it
    // the current engine id lets it reason about the size of the gap.
    const lastEventId = Number(req.headers['last-event-id'] || 0);
    reply.raw.write(`event: ready\ndata: ${JSON.stringify({
      user_id: verified.userId,
      channels: verified.channels.length,
      server_event_id: typeof serverEventId === 'function' ? serverEventId() : 0,
      ...(Number.isSafeInteger(lastEventId) && lastEventId > 0
        ? { last_event_id: lastEventId, resync_required: true }
        : {}),
    })}\n\n`);
    // Ask the EventSource to wait 3s before reconnecting after a silent drop,
    // so a gateway restart does not produce a reconnect storm.
    reply.raw.write('retry: 3000\n\n');

    if (logger) logger.journal('sse.connected', { user_id: verified.userId, channels: verified.channels.length });
    if (onRegistered) onRegistered(client, verified);

    const heartbeat = setInterval(() => {
      if (client.closed) {
        clearInterval(heartbeat);
        return;
      }
      try {
        // Both frames are sent deliberately: the SSE comment is what most
        // proxies treat as traffic, and the typed event is the only liveness
        // signal JS can observe. Either alone leaves a blind spot.
        reply.raw.write(`: heartbeat ${Date.now()}\n\n`);
        reply.raw.write(frameFor({
          type: 'HEARTBEAT',
          scope: 'all',
          payload: {},
          emitted_at: new Date().toISOString(),
        }));
      } catch (_) {
        clearInterval(heartbeat);
      }
    }, config.heartbeatMs);
    if (typeof heartbeat.unref === 'function') heartbeat.unref();

    reply.raw.on('close', () => clearInterval(heartbeat));
  }

  return { handleEvents };
}

module.exports = { createEventsHandler };