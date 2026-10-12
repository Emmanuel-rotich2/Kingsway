import { getConfig } from '../config/index.js';
import { getLogger } from '../logger/index.js';
import { viewSpecs } from '../views/specs/index.js';
import { getAggregationEngine } from '../aggregation/engine.js';
import { getInternalClient } from '../clients/internal.js';

import { verifyWorkerSecret } from '../utils/verifySecret.js';

export function registerViewRoutes(app) {
  const config = getConfig();
  const logger = getLogger().child({ component: 'routes:views' });
  const aggregationEngine = getAggregationEngine();
  const internalClient = getInternalClient();

  function check(req) {
    return verifyWorkerSecret(req, config);
  }

  function normalizeFilters(value) {
    if (value === undefined || value === null) return {};
    if (!value || typeof value !== 'object' || Array.isArray(value)) {
      const error = new Error('filters must be an object');
      error.statusCode = 422;
      throw error;
    }

    const entries = Object.entries(value);
    if (entries.length > 32) {
      const error = new Error('too many filter fields');
      error.statusCode = 422;
      throw error;
    }

    return Object.fromEntries(entries.map(([key, item]) => {
      if (!/^[a-z][a-z0-9_]{0,63}$/i.test(key)
        || !['string', 'number', 'boolean'].includes(typeof item)) {
        const error = new Error('invalid filter field');
        error.statusCode = 422;
        throw error;
      }
      const text = String(item);
      if (text.length > 200) {
        const error = new Error('filter value is too long');
        error.statusCode = 422;
        throw error;
      }
      return [key, text];
    }));
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
        pagination: rawData?._viewMeta?.pagination ?? null,
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
    if (!spec) return reply.code(404).send({ error: `View spec not found: ${specKey}` });

    if (spec.permissions?.length && !spec.permissions.some(p => permissions.includes(p))) {
      return reply.code(403).send({ error: 'Insufficient permissions' });
    }

    try {
      if (typeof req.headers.authorization !== 'string' || !/^Bearer\s+\S+$/i.test(req.headers.authorization)) {
        return reply.code(401).send({ error: 'Authenticated browser session required' });
      }

      const filters = normalizeFilters(input.filters);
      const rawData = await internalClient.fetchViewData(
        spec,
        filters,
        operator_id,
        contract === 'data' ? req.headers.authorization : '',
      );
      const aggregated = await aggregationEngine.compute(rawData, spec.aggregations);

      // Composed data currently bypasses the shared cache until the operation
      // has an explicit scope-safe key and mutation invalidation contract.
      return {
        entity,
        view,
        ...buildDataEnvelope(aggregated, entity, view, request_id, operator_id, rawData),
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

  app.get('/internal/views', async (req, reply) => {
    if (!check(req)) return reply.code(403).send({ error: 'Forbidden' });
    return { views: Array.from(viewSpecs.keys()) };
  });
}
