'use strict';

/**
 * Kingsway realtime gateway — entry point (Passenger main file).
 *
 * Node.js 24 is the Real-Time Data Engine & State Orchestrator: it streams
 * change descriptors to logged-in browsers over SSE, eliminates page
 * reloads/polling, and carries job telemetry. Strict rules: stateless (holds
 * only open SSE sockets and ephemeral presence declarations), zero database
 * access, `/events` needs a short-lived PHP-issued capability JWT, and
 * `/internal/*` needs the shared worker secret plus a permitted source address.
 *
 * Wiring order matters and is deliberate:
 *   config → validate → logger → metrics → connections → dispatcher → bus
 * The bus is the ONLY publish path, and the dispatcher is its only subscriber,
 * so an event can never be fanned out twice by two different code paths.
 *
 * All composition lives here; `src/` holds the modules. Nothing in this process
 * opens a MySQL connection, reads a business record, or holds a durable log.
 */

const fastify = require('fastify');

const { createConfig, validate, mergedEnv, allowedOrigins } = require('./src/config');
const { createLogger } = require('./src/logger');
const { createMetrics } = require('./src/metrics');
const { createState } = require('./src/state');
const { createRegistry } = require('./src/connections');
const { createDispatcher } = require('./src/dispatcher');
const { createBus } = require('./src/bus');
const { createRoutes } = require('./src/routes');
const { verifyCapability } = require('./src/auth');

function isTruthy(value) {
  return /^(1|true|yes|on)$/i.test(String(value || ''));
}

function createApp(env = mergedEnv()) {
  const config = validate(createConfig(env));
  const startedAt = Date.now();
  // NODE_REALTIME_JOURNAL=off silences output during a maintenance window. The
  // journal() API stays callable either way, so no call site branches on it.
  const journalOff = String(env.NODE_REALTIME_JOURNAL || '').toLowerCase() === 'off';

  const logger = createLogger({
    enabled: !journalOff,
    pretty: config.nodeEnv !== 'production',
    level: config.nodeEnv === 'production' ? 'info' : 'debug',
    base: {
      host: config.host,
      port: config.port,
      pid: process.pid,
      ...(config.instanceId ? { instance: config.instanceId } : {}),
    },
  });

  const metrics = createMetrics({
    prefix: config.metricsPrefix,
    collectDefaults: config.nodeEnv === 'production',
  });

  const state = createState();
  state.stats.started_at = startedAt;

  const registry = createRegistry({
    state,
    maxConnections: config.maxConnections,
    queueLimit: config.queueLimit,
    slowClientTimeoutMs: config.slowClientTimeoutMs,
    presenceTtlMs: config.presenceTtlMs,
    metrics,
    logger,
  });

  const dispatcher = createDispatcher({
    registry,
    state,
    metrics,
    logger,
    coalesceWindowMs: config.coalesceWindowMs,
    coalesceMaxWaitMs: config.coalesceMaxWaitMs,
  });

  const bus = createBus({ redisUrl: config.redisUrl, logger, metrics, instanceId: config.instanceId });
  bus.subscribe((event) => dispatcher.dispatch(event));

  const app = fastify({
    // pino is constructed above so the redaction rules and journal wrapper are
    // shared; Fastify's own logger would be a second, unreconciled sink.
    logger: false,
    bodyLimit: config.maxBodyBytes,
    trustProxy: true,
    // The descriptor allowlist must REJECT unknown keys, not silently strip them.
    // Fastify's default AJV ships removeAdditional: true, which would quietly
    // delete `{sql: "..."}` from a publish and answer 202 — an allowlist that
    // hides its own failures is worse than no allowlist, because a publisher bug
    // or a probe would look like a success.
    ajv: {
      customOptions: {
        removeAdditional: false,
        coerceTypes: false,
        useDefaults: false,
        allErrors: false,
      },
    },
    // Node's default request timeout destroys a response that has not completed
    // within 300s, which would kill every SSE stream every five minutes. SSE is
    // long-lived by design, so this must be disabled; per-connection liveness is
    // enforced by the pool cap and the client-eviction path instead.
    requestTimeout: 0,
    keepAliveTimeout: 72000,
  });

  app.decorate('kingsway', { config, logger, metrics, state, registry, dispatcher, bus });

  const origins = allowedOrigins(config);

  app.addHook('onRequest', async (req, reply) => {
    // CORS is required, not cosmetic: the gateway is served from a different
    // origin than the PHP site (realtime.… vs kingsway…), so every browser
    // request to /presence is cross-origin and needs the headers below or the
    // browser blocks it before it reaches the handler.
    const origin = req.headers.origin;
    if (!origin || !origins.includes(origin)) return undefined;
    reply.header('access-control-allow-origin', origin);
    reply.header('vary', 'Origin');
    reply.header('access-control-allow-headers', 'content-type, x-kingsway-sse-token');
    reply.header('access-control-allow-methods', 'GET, POST, OPTIONS');
    reply.header('access-control-max-age', '600');

    // Private Network Access: a SECURE page (https://localhost, or the
    // production https site) asking for a LOOPBACK address is a
    // secure-context -> local-network request, which Chrome blocks unless the
    // server opts in. EventSource cannot send a preflight, so the opt-in has to
    // ride on the actual response or the stream silently never opens.
    //
    // Only echoed when the browser actually asks, and only for origins already
    // on the allowlist, so this grants nothing extra. In production the gateway
    // is a public HTTPS hostname and the browser never sends this header.
    if (req.headers['access-control-request-private-network'] === 'true') {
      reply.header('access-control-allow-private-network', 'true');
      reply.header('vary', 'Origin, Access-Control-Request-Private-Network');
    }
    return undefined;
  });

  app.addHook('onSend', async (req, reply, payload) => {
    // Live state must never be cached by an intermediary.
    reply.header('cache-control', 'no-store');
    reply.header('x-content-type-options', 'nosniff');
    return payload;
  });

  const routing = createRoutes({
    config,
    logger,
    metrics,
    registry,
    state,
    dispatcher,
    bus,
  });
  routing.register(app);

  // Preflight responses for the cross-origin browser surface.
  //
  // /presence sends the capability header and therefore genuinely needs one.
  // /events also needs one, contrary to the "EventSource is a simple request"
  // assumption: Chrome's Private Network Access treats a secure page reaching
  // loopback as requiring a preflight, and a 404 on OPTIONS fails the whole
  // stream silently. Answering every preflight is simpler than enumerating
  // which browser quirk applies where, and the onRequest hook has already
  // enforced the origin allowlist by the time this runs.
  app.options('/*', async (req, reply) => reply.code(204).send());

  app.setNotFoundHandler(async (req, reply) => {
    if (routing.wrongMethod(req)) {
      return reply.code(405).header('allow', 'GET').send({ error: 'method not allowed' });
    }
    return reply.code(404).send({ error: 'not found' });
  });
  app.setErrorHandler(async (error, req, reply) => {
    // A client-side rejection (schema violation, unparseable body, wrong method)
    // gets an accurate status and a safe generic message. The raw error text is
    // journalled, never returned, so internals are not echoed to a caller.
    const status = error.validation ? 400 : (error.statusCode || 500);
    if (status >= 400 && status < 500) {
      logger.journal('request.rejected', { path: req.url, code: error.code, status });
      return reply.code(status).send({
        error: error.validation ? 'invalid request body' : 'invalid request',
      });
    }
    logger.error({ err: error?.message, code: error?.code, path: req.url }, 'request failed');
    return reply.code(500).send({ error: 'internal error' });
  });

  const sweeper = setInterval(() => registry.sweepPresence(), config.presenceSweepMs);
  if (typeof sweeper.unref === 'function') sweeper.unref();

  app.addHook('onClose', async () => {
    clearInterval(sweeper);
  });

  /**
   * Graceful drain for a deploy or a self-restart: flush anything still
   * coalesced, tell every stream to come straight back, then close. The browser
   * sees a disconnect, reconnects, and resyncs through PHP's catch-up reader —
   * which is why no event log needs to be persisted here.
   */
  async function close({ reason = 'shutdown' } = {}) {
    try {
      dispatcher.coalescer.flushAll();
      const frame = `event: server_restart\ndata: ${JSON.stringify({ reason })}\n\n`;
      const closed = registry.closeAll(frame);
      if (closed) logger.journal('sse.drain', { connections: closed, reason });
      await bus.stop();
    } catch (error) {
      logger.error({ err: error?.message }, 'drain failed');
    }
    await app.close();
  }

  return {
    app,
    server: app.server,
    config,
    logger,
    metrics,
    state,
    registry,
    dispatcher,
    bus,
    startedAt,
    close,
    verifyCapability: (token) => verifyCapability(token, config),
    /** Bring async subsystems (the bus) up and resolve routes. */
    async start() {
      await bus.start();
      await app.ready();
      return app;
    },
  };
}

if (require.main === module) {
  const gateway = createApp();
  gateway.start()
    .then(() => gateway.server.listen(gateway.config.port, gateway.config.host, () => {
      process.stdout.write(
        `Kingsway realtime listening on ${gateway.config.host}:${gateway.config.port} `
        + `(bus: ${gateway.bus.modeName}, pool: ${gateway.config.maxConnections})\n`,
      );
    }))
    .catch((error) => {
      process.stderr.write(`Kingsway realtime failed to start: ${error.message}\n`);
      process.exit(1);
    });

  const shutdown = (signal) => {
    process.stdout.write(`\nKingsway realtime draining (${signal})\n`);
    gateway.close({ reason: signal })
      .then(() => process.exit(0))
      .catch(() => process.exit(1));
  };
  process.on('SIGTERM', () => shutdown('SIGTERM'));
  process.on('SIGINT', () => shutdown('SIGINT'));
}

module.exports = { createApp, isTruthy };