import { getConfig } from '../config/index.js';
import { getLogger } from '../logger/index.js';
import { getBus } from '../realtime/bus.js';
import { broadcast as broadcastDomain } from '../publish/domain.js';
import { verifyWorkerSecret } from '../utils/verifySecret.js';

export function registerInternalRoutes(app) {
  const config = getConfig();
  const logger = getLogger().child({ component: 'routes:internal' });
  const bus = getBus();

  function check(req) {
    return verifyWorkerSecret(req, config);
  }

  app.get('/internal/stats', async (req, reply) => {
    if (!check(req)) return reply.code(403).send({ error: 'Forbidden' });

    const state = app.kingsway?.state;
    const registry = app.kingsway?.registry;
    const sse = app.kingsway?.sse;
    const processor = app.kingsway?.processor;
    const cache = app.kingsway?.cache;

    return {
      uptime: process.uptime(),
      memory: process.memoryUsage(),
      connections: sse?.getStats?.() || {},
      bus: bus.isHealthy?.() ? 'jsonl-file' : 'unhealthy',
      queues: processor ? {
        registeredHandlers: processor.getRegisteredHandlers?.() || [],
        load: processor.getLoad?.() || null,
      } : {},
      registry: (await import('../queue/jobRegistry.js')).getJobRegistry().stats(),
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
      return { ok: true, channel: result.channel };
    }

    // Raw bus push shape: { channel, event, payload } — used by gateway-local
    // and test clients that already know their channel.
    const { channel, event, payload } = body;
    if (!channel || !event) return reply.code(422).send({ error: 'channel and event required' });

    await bus.publish(channel, { type: event, payload, correlationId, source: 'internal-api' });
    return { ok: true };
  });
}