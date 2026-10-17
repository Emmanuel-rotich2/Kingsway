import fastify from 'fastify';
import fastifyCors from '@fastify/cors';
import fastifySSE from '@fastify/sse';

import { loadConfig, getConfig } from './src/config/index.js';
import { createLogger, getLogger } from './src/logger/index.js';
import { createMetrics, getMetrics } from './src/metrics/index.js';
import { registerHealthRoutes } from './src/health/index.js';
import { createInternalClient, getInternalClient } from './src/clients/internal.js';
import { createCache, getCache } from './src/clients/cache.js';
import { createBus, getBus } from './src/realtime/bus.js';
import { createSSE, getSSE } from './src/realtime/sse.js';
import { registerWebSocket } from './src/realtime/websocket.js';
import { getAggregationEngine } from './src/aggregation/engine.js';
import { registerViewRoutes } from './src/routes/views.js';
import { registerPublicRoutes } from './src/routes/public.js';
import { registerInternalRoutes } from './src/routes/internal.js';

function safeRequestPath(url) {
  return String(url || '').split('?', 1)[0].slice(0, 256);
}

function isTruthy(value) {
  return /^(1|true|yes|on)$/i.test(String(value || ''));
}

async function createApp(env = {}) {
  const config = loadConfig(env);
  const startedAt = Date.now();

  const logger = createLogger({});
  const metrics = createMetrics({});

  const internalClient = createInternalClient();
  const cache = createCache();

  const bus = createBus();
  const sse = createSSE();
  const aggregationEngine = getAggregationEngine();
  await aggregationEngine.init();

  // Cache invalidation pipeline: when a CACHE_INVALIDATE domain event lands on
  // the broadcast channel (PHP publisher or a peer gateway), purge the matching
  // view/job/agg namespaces from BOTH cache layers so every worker re-resolves
  // from PHP on the next request rather than serving a stale descriptor.
  bus.subscribe('broadcast', (msg) => {
    const type = String(msg.payload?.type || msg.type || '').toUpperCase();
    if (type !== 'CACHE_INVALIDATE' && type !== 'CACHE_INVALIDATED') return;
    const targets = Array.isArray(msg.payload?.payload?.targets) ? msg.payload.payload.targets : [];
    for (const pattern of targets) {
      if (typeof pattern === 'string' && pattern) {
        cache.delPattern(pattern).catch(() => {});
      }
    }
  });

  const app = fastify({
    logger: false,
    bodyLimit: 1024 * 1024,
    trustProxy: true,
    ajv: {
      customOptions: {
        removeAdditional: false,
        coerceTypes: false,
        useDefaults: false,
        allErrors: false,
      },
    },
    requestTimeout: 0,
    keepAliveTimeout: 72000,
  });

  app.decorate('kingsway', { config, logger, metrics, internalClient, cache, bus, sse, aggregationEngine, startedAt });

  await app.register(fastifyCors, {
    origin: config.security.webOrigins,
    methods: ['GET', 'POST', 'OPTIONS'],
    allowedHeaders: ['content-type', 'x-kingsway-sse-token', 'last-event-id'],
    credentials: true,
    maxAge: 600,
  });

  await app.register(fastifySSE);

  // Register this BEFORE any route: Fastify only attaches hooks to routes that
  // exist at the moment addHook() runs. CORS has to be set in an onRequest hook
  // rather than relying on @fastify/cors, whose hook runs late enough that the
  // SSE plugin has already flushed the response headers.
  app.addHook('onRequest', async (req, reply) => {
    reply.header('cache-control', 'no-store');
    reply.header('x-content-type-options', 'nosniff');

    const requestOrigin = req.headers.origin;
    if (requestOrigin && config.security.webOrigins.includes(requestOrigin)) {
      reply.header('access-control-allow-origin', requestOrigin);
      reply.header('access-control-allow-credentials', 'true');
      reply.header('vary', 'Origin');
    }

    if (req.method === 'OPTIONS') {
      reply.header('access-control-allow-methods', 'GET, POST, OPTIONS');
      reply.header(
        'access-control-allow-headers',
        'content-type, x-kingsway-sse-token, last-event-id'
      );
      reply.header('access-control-max-age', '600');
      if (requestOrigin && config.security.webOrigins.includes(requestOrigin)) {
        return reply.code(204).send();
      }
    }
  });

  await registerWebSocket(app);

  registerHealthRoutes(app);
  registerPublicRoutes(app);
  registerViewRoutes(app);
  registerInternalRoutes(app);

  app.setNotFoundHandler(async (req, reply) => {
    return reply.code(404).send({ error: 'not found' });
  });

  app.setErrorHandler(async (error, req, reply) => {
    const status = error.validation ? 400 : (error.statusCode || 500);
    if (status >= 400 && status < 500) {
      logger.info({ code: error.code, status, path: safeRequestPath(req.url) }, 'request rejected');
      return reply.code(status).send({ error: error.validation ? 'invalid request body' : 'invalid request' });
    }
    logger.error({ err: error?.message, code: error?.code, path: safeRequestPath(req.url) }, 'request failed');
    return reply.code(500).send({ error: 'internal error' });
  });

  async function close() {
    try {
      await bus.close();
      await internalClient.close();
      cache.close();
      // Allow time for better-sqlite3 to finalize statements
      await new Promise(r => setTimeout(r, 100));
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
    internalClient,
    cache,
    bus,
    sse,
    aggregationEngine,
    startedAt,
    close,
    async start() {
      await bus.init();
      await app.ready();
      return app;
    },
  };
}

if (import.meta.url === `file://${process.argv[1]}`) {
  const underPassenger = typeof PhusionPassenger !== 'undefined';
  if (underPassenger && typeof PhusionPassenger.configure === 'function') {
    PhusionPassenger.configure({ autoInstall: false });
  }

  let gateway;
  try {
    gateway = await createApp();
  } catch (error) {
    process.stderr.write(`Kingsway realtime failed to start: ${error.message}\n`);
    process.exit(1);
  }

  await gateway.start()
    .then(() => {
      if (underPassenger) {
        gateway.server.listen('passenger', () => {
          process.stdout.write(
            `Kingsway realtime listening on the Passenger socket `
            + `(bus: jsonl-file, pool: ${gateway.config.sse.maxConnections})\n`,
          );
        });
        return;
      }
      gateway.server.listen(gateway.config.port, gateway.config.host, () => {
        process.stdout.write(
          `Kingsway realtime listening on ${gateway.config.host}:${gateway.config.port} `
          + `(bus: jsonl-file, pool: ${gateway.config.sse.maxConnections})\n`,
        );
      });
    })
    .catch((error) => {
      process.stderr.write(`Kingsway realtime failed to start: ${error.message}\n`);
      process.exit(1);
    });

  const shutdown = (signal) => {
    process.stdout.write(`\nKingsway realtime draining (${signal})\n`);
    // Bounded drain: never block Passenger from stopping the instance. If
    // outstanding keep-alive sockets or in-flight polls outlive the grace
    // window, fall through to a hard exit rather than hanging forever.
    const watchdog = setTimeout(() => {
      process.stdout.write('Kingsway realtime drain watchdog: forcing exit\n');
      process.exit(0);
    }, 8000);
    gateway.close({ reason: signal })
      .then(() => {
        clearTimeout(watchdog);
        process.exit(0);
      })
      .catch(() => {
        clearTimeout(watchdog);
        process.exit(1);
      });
  };
  process.on('SIGTERM', () => shutdown('SIGTERM'));
  process.on('SIGINT', () => shutdown('SIGINT'));
}

export { createApp, isTruthy };
