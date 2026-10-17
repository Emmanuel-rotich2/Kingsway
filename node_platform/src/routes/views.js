import { createHash } from 'node:crypto';
import { getConfig } from '../config/index.js';
import { getLogger } from '../logger/index.js';
import { viewSpecs } from '../views/specs/index.js';
import { getAggregationEngine } from '../aggregation/engine.js';
import { getInternalClient } from '../clients/internal.js';
import { getCache } from '../clients/cache.js';

import { verifyWorkerSecret } from '../utils/verifySecret.js';

/**
 * A composed view is cached only when its result is bound to the caller's
 * authorization context. The bearer is hashed and never stored, so two
 * operators with different row-scope/role resolution never share an entry.
 * Mutations invalidate by entity via /internal/publish (DATA_CHANGED).
 */
export function scopeFingerprint(authorization, operatorId, permissions) {
  const token = typeof authorization === 'string' ? authorization.replace(/^Bearer\s+/i, '') : '';
  const permissionScope = Array.isArray(permissions) ? [...permissions].sort().join(',') : '';
  return createHash('sha256')
    .update(`${Number(operatorId) || 0}|${token}|${permissionScope}`)
    .digest('hex')
    .slice(0, 32);
}

export function viewCacheKey(entity, view, spec, fingerprint, input = {}) {
  const salt = createHash('sha256')
    .update(JSON.stringify((spec.queries || []).map(query => ({
      key: query.key,
      endpoint: query.endpoint,
      staticParams: query.staticParams || {},
      pagination: query.pagination || null,
    }))))
    .digest('hex')
    .slice(0, 16);
  const inputHash = createHash('sha256')
    .update(JSON.stringify(input || {}))
    .digest('hex')
    .slice(0, 16);
  return `view:${entity}/${view}:${salt}:${fingerprint}:${inputHash}`;
}

/**
 * Universal read proxy: accepts any registered view spec or falls back to
 * a generic pass-through for pages not yet migrated to explicit specs.
 * All browser reads route through Node for pooling, caching, aggregation, realtime.
 */
export function registerViewRoutes(app) {
  const config = getConfig();
  const logger = getLogger().child({ component: 'routes:views' });
  const aggregationEngine = getAggregationEngine();
  const internalClient = getInternalClient();
  const cache = getCache();

  function check(req) {
    return verifyWorkerSecret(req, config);
  }

  function buildDataEnvelope(aggregated, entity, view, requestId, operatorId, rawData) {
    const sources = Object.fromEntries(
      Object.entries(rawData || {}).filter(([key]) => key !== '_viewMeta'),
    );
    const groups = Object.fromEntries(
      Object.entries(aggregated.tables || {}).map(([key, table]) => [key, table.rows || []]),
    );

    return {
      data: {
        sources,
        summary: aggregated.summary || {},
        groups,
        filterOptions: aggregated.filterOptions || {},
      },
      meta: {
        generatedAt: Date.now(),
        requestId,
        operatorId,
      },
    };
  }

  app.post('/internal/views/:entity/:view', async (req, reply) => {
    if (!check(req)) return reply.code(403).send({ error: 'Forbidden' });

    const { entity, view } = req.params;
    const { operator_id, permissions, request_id, input = {}, contract = 'data' } = req.body || {};

    if (contract !== 'data') {
      return reply.code(422).send({ error: 'Only the data response contract is supported' });
    }

    if (!Number.isSafeInteger(Number(operator_id)) || Number(operator_id) < 1) {
      return reply.code(422).send({ error: 'valid operator_id required' });
    }
    if (!Array.isArray(permissions) || permissions.some(permission => typeof permission !== 'string')) {
      return reply.code(422).send({ error: 'valid permissions required' });
    }

    const specKey = `${entity}/${view}`;
    const spec = viewSpecs.get(specKey);
    if (!spec) {
      // Generic pass-through for pages not yet migrated to explicit specs
      // Node acts as universal proxy: fan out to the single endpoint with pooling/caching
      return handleGenericRead(req, reply, entity, view, operator_id, permissions, request_id, input, contract);
    }

    if (spec.permissions?.length && !spec.permissions.some(p => permissions.includes(p))) {
      return reply.code(403).send({ error: 'Insufficient permissions' });
    }

    try {
      if (typeof req.headers.authorization !== 'string' || !/^Bearer\s+\S+$/i.test(req.headers.authorization)) {
        return reply.code(401).send({ error: 'Authenticated browser session required' });
      }

      const ttl = Number.isFinite(Number(spec.cacheTtlSeconds))
        ? Number(spec.cacheTtlSeconds)
        : config.cache.defaultTtlSeconds;
      const fingerprint = scopeFingerprint(
        req.headers.authorization,
        operator_id,
        permissions,
      );
      const cacheKey = viewCacheKey(entity, view, spec, fingerprint, input);

      if (ttl > 0) {
        const cached = await cache.get(cacheKey);
        if (cached && cached.data) {
          return {
            entity,
            view,
            data: cached.data,
            meta: {
              generatedAt: Date.now(),
              requestId: request_id,
              operatorId: operator_id,
              cache: 'hit',
            },
          };
        }
      }

      const rawData = await internalClient.fetchViewData(
        spec,
        operator_id,
        contract === 'data' ? req.headers.authorization : '',
        input,
      );
      const aggregated = await aggregationEngine.compute(rawData, spec.aggregations);
      const envelope = buildDataEnvelope(aggregated, entity, view, request_id, operator_id, rawData);

      if (ttl > 0) {
        await cache.set(cacheKey, envelope, ttl);
      }

      return {
        entity,
        view,
        ...envelope,
        meta: { ...envelope.meta, cache: ttl > 0 ? 'miss' : 'off' },
      };
    } catch (e) {
      logger.error({ err: e.message, specKey }, 'View resolution failed');
      const status = [401, 403, 422].includes(Number(e.statusCode)) ? Number(e.statusCode) : 502;
      return reply.code(status).send({
        entity, view,
        error: status === 403 ? 'View access denied' : (status === 422 ? e.message : 'View resolution failed'),
      });
    }
  });

  async function handleGenericRead(req, reply, entity, view, operator_id, permissions, request_id, input, contract) {
    const logger = getLogger().child({ component: 'routes:views:generic' });
    logger.warn({ entity, view }, 'Using generic pass-through for unregistered view');

    // Allow any endpoint matching the entity pattern for gradual migration
    const allowedEndpoints = [
      `/api/${entity}/*`,
      `/api/${entity}s/*`,
    ];
    // In production, validate against a route registry instead of pattern matching

    try {
      const ttl = config.cache.defaultTtlSeconds;
      const fingerprint = scopeFingerprint(
        req.headers.authorization,
        operator_id,
        permissions,
      );
      // Generic cache key includes the full input for safety
      const cacheKey = `view:${entity}/${view}:generic:${fingerprint}:${createHash('sha256').update(JSON.stringify(input || {})).digest('hex').slice(0, 16)}`;

      if (ttl > 0) {
        const cached = await cache.get(cacheKey);
        if (cached && cached.data) {
          return {
            entity,
            view,
            data: cached.data,
            meta: {
              generatedAt: Date.now(),
              requestId: request_id,
              operatorId: operator_id,
              cache: 'hit',
            },
          };
        }
      }

      // Fan out to registered queries in spec or single endpoint
      const rawData = await internalClient.fetchViewData(
        {
          view: `${entity}/${view}`,
          service: 'php',
          queries: [
            {
              key: view,
              endpoint: `/api/${entity}/${view}`,
              staticParams: input,
            },
          ],
          aggregations: {},
        },
        operator_id,
        contract === 'data' ? req.headers.authorization : '',
        input,
      );

      const aggregated = await aggregationEngine.compute(rawData, {});
      const envelope = buildDataEnvelope(aggregated, entity, view, request_id, operator_id, rawData);

      if (ttl > 0) {
        await cache.set(cacheKey, envelope, ttl);
      }

      return {
        entity,
        view,
        ...envelope,
        meta: { ...envelope.meta, cache: ttl > 0 ? 'miss' : 'off' },
      };
    } catch (e) {
      logger.error({ err: e.message, entity, view }, 'Generic read failed');
      const status = [401, 403, 422].includes(Number(e.statusCode)) ? Number(e.statusCode) : 502;
      return reply.code(status).send({
        entity, view,
        error: status === 403 ? 'View access denied' : (status === 422 ? e.message : 'Read failed'),
      });
    }
  }

  app.get('/internal/views', async (req, reply) => {
    if (!check(req)) return reply.code(403).send({ error: 'Forbidden' });
    return { views: Array.from(viewSpecs.keys()) };
  });
}
