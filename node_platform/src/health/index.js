import { getConfig } from '../config/index.js';
import { getLogger } from '../logger/index.js';
import { getMetrics } from '../metrics/index.js';
import { verifyWorkerSecret } from '../utils/verifySecret.js';

export function registerHealthRoutes(app) {
  const config = getConfig();
  const logger = getLogger();

  app.get('/health', async () => ({
    status: 'ok',
    service: config.app.name,
    version: '2.0.0',
    timestamp: new Date().toISOString(),
  }));

  app.get('/internal/health', async (req, reply) => {
    if (!check(req, config)) {
      return reply.code(403).send({ error: 'Forbidden' });
    }

    const checks = {
      php: await checkPHP(config),
      python: await checkPython(config),
      cache: await checkCache(),
      bus: await checkBus(),
      storage: await checkStorage(config),
    };

    const healthy = Object.values(checks).every(c => c.healthy);
    const status = healthy ? 'healthy' : 'degraded';

    return reply.code(healthy ? 200 : 503).send({
      status,
      service: config.app.name,
      timestamp: new Date().toISOString(),
      checks,
    });
  });
}

function check(req, config) {
  return verifyWorkerSecret(req, config);
}

async function checkPHP(config) {
  try {
    const { getInternalClient } = await import('../clients/internal.js');
    const internalClient = getInternalClient();
    // Liveness + worker-secret round-trip probe: the RBAC read proxy answers
    // reachability and secret auth without needing a live operator. Any DB is
    // offline for the whole stack, so a failed probe marks php unhealthy.
    const body = await internalClient.post('php', '/node/internal-read', {
      view: 'system/health',
      operator_id: 1,
      input: {},
    }, { timeout: 5000 });
    if (body?.success !== true) {
      const detail = typeof body === 'string' ? body.slice(0, 160) : JSON.stringify(body).slice(0, 160);
      return { healthy: false, component: 'php', error: detail || 'non-success PHP read probe' };
    }
    return { healthy: true, component: 'php' };
  } catch (e) {
    return { healthy: false, component: 'php', error: e.message };
  }
}

async function checkPython(config) {
  try {
    const { getInternalClient } = await import('../clients/internal.js');
    const internalClient = getInternalClient();
    const res = await internalClient.get('python', '/health', {}, { timeout: 5000 });
    return { healthy: res?.status === 'ok', component: 'python' };
  } catch (e) {
    return { healthy: false, component: 'python', error: e.message };
  }
}

async function checkCache() {
  try {
    const { getCache } = await import('../clients/cache.js');
    const cache = getCache();
    await cache.set('health:check', 'ok', 10);
    const val = await cache.get('health:check');
    return { healthy: val === 'ok', component: 'cache' };
  } catch (e) {
    return { healthy: false, component: 'cache', error: e.message };
  }
}

async function checkBus() {
  try {
    const { getBus } = await import('../realtime/bus.js');
    const bus = getBus();
    return { healthy: bus.isHealthy?.() !== false, component: 'bus' };
  } catch (e) {
    return { healthy: false, component: 'bus', error: e.message };
  }
}

async function checkStorage(config) {
  try {
    const { stat } = await import('node:fs/promises');
    const { resolve } = await import('node:path');
    await stat(resolve(config.storage.root));
    return { healthy: true, component: 'storage' };
  } catch (e) {
    return { healthy: false, component: 'storage', error: e.message };
  }
}