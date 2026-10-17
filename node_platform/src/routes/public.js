import { getConfig } from '../config/index.js';
import { getLogger } from '../logger/index.js';
import { getSSE } from '../realtime/sse.js';

export function registerPublicRoutes(app) {
  const config = getConfig();
  const logger = getLogger().child({ component: 'routes:public' });
  const sse = getSSE();

  const allowedOrigins = config.security.webOrigins;

  app.addHook('onRequest', async (req, reply) => {
    const origin = req.headers.origin;
    if (origin && allowedOrigins.includes(origin)) {
      reply.header('access-control-allow-origin', origin);
      reply.header('vary', 'Origin');
      reply.header('access-control-allow-headers', 'content-type, x-kingsway-sse-token, last-event-id');
      reply.header('access-control-allow-methods', 'GET, POST, OPTIONS');
      reply.header('access-control-max-age', '600');
    }

    if (origin && allowedOrigins.includes(origin)
      && req.headers['access-control-request-private-network'] === 'true') {
      reply.header('access-control-allow-private-network', 'true');
      reply.header('vary', 'Origin, Access-Control-Request-Private-Network');
    }
  });

  app.get('/events', async (req, reply) => {
    const token = req.query.token;
    if (!token) return reply.code(401).send({ error: 'Missing token' });

    const decoded = sse.verifyCapability(token);
    if (!decoded) return reply.code(403).send({ error: 'Invalid token' });

    return sse.handleConnection(req, reply);
  });

  app.post('/presence', async (req, reply) => {
    const token = req.headers['x-kingsway-sse-token'];
    if (!token) return reply.code(401).send({ error: 'Missing token' });

    const decoded = sse.verifyCapability(token);
    if (!decoded) return reply.code(403).send({ error: 'Invalid token' });

    const result = await sse.reportPresence(decoded.sub, req.body || {});
    return result.ok ? { ok: true } : reply.code(422).send({ error: 'invalid presence body' });
  });
}
