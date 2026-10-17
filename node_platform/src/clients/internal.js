import { Pool, Agent } from 'undici';
import { getConfig } from '../config/index.js';
import { getLogger } from '../logger/index.js';
import { getMetrics } from '../metrics/index.js';

let clientCache = null;

const CircuitState = { CLOSED: 'closed', OPEN: 'open', HALF_OPEN: 'half_open' };

class CircuitBreaker {
  constructor(name, threshold = 5, timeout = 30000) {
    this.name = name;
    this.threshold = threshold;
    this.timeout = timeout;
    this.failures = 0;
    this.lastFailure = 0;
    this.state = CircuitState.CLOSED;
  }

  async execute(fn) {
    if (this.state === CircuitState.OPEN) {
      if (Date.now() - this.lastFailure > this.timeout) {
        this.state = CircuitState.HALF_OPEN;
      } else {
        throw new Error(`Circuit breaker OPEN for ${this.name}`);
      }
    }

    try {
      const result = await fn();
      this.onSuccess();
      return result;
    } catch (e) {
      this.onFailure();
      throw e;
    }
  }

  onSuccess() {
    this.failures = 0;
    this.state = CircuitState.CLOSED;
  }

  onFailure() {
    this.failures++;
    this.lastFailure = Date.now();
    if (this.failures >= this.threshold) {
      this.state = CircuitState.OPEN;
    }
  }
}

function createPool(baseUrl, poolSize, tlsInsecure = false, keepAliveTimeout = 60000) {
  const url = new URL(baseUrl);
  const connect = url.protocol === 'https:'
    ? { rejectUnauthorized: !tlsInsecure }
    : undefined;
  return new Pool(url.origin, {
    connections: poolSize,
    keepAliveTimeout,
    keepAliveMaxTimeout: 300000,
    pipelining: 10,
    connect,
  });
}

export function createInternalClient() {
  if (clientCache) return clientCache;

  const config = getConfig();
  const logger = getLogger().child({ component: 'internal-client' });
  const metrics = getMetrics();

  const phpPool = createPool(config.internalHttp.php.baseUrl, config.internalHttp.php.poolSize, config.internalHttp.php.tlsInsecure);
  const pythonPool = createPool(config.internalHttp.python.baseUrl, config.internalHttp.python.poolSize, config.internalHttp.python.tlsInsecure);

  const phpBreaker = new CircuitBreaker('php', 5, 30000);
  const pythonBreaker = new CircuitBreaker('python', 3, 60000);

  const workerSecret = config.security.workerSecret;

  async function request(service, method, path, options = {}) {
    const start = Date.now();
    const pool = service === 'php' ? phpPool : pythonPool;
    const breaker = service === 'php' ? phpBreaker : pythonBreaker;
    const timeout = Number.isFinite(options.timeoutMs) && options.timeoutMs > 0
      ? options.timeoutMs
      : (service === 'php' ? config.internalHttp.php.timeoutMs : config.internalHttp.python.timeoutMs);
    const hostHeader = service === 'php' ? config.internalHttp.php.hostHeader : config.internalHttp.python.hostHeader;
    const apiPrefix = service === 'php' ? config.internalHttp.php.apiPrefix : config.internalHttp.python.apiPrefix;

    const targetPath = apiPrefix ? `${apiPrefix}${path}` : path;
    const queryString = options.params
      ? `?${new URLSearchParams(options.params).toString()}`
      : '';

    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), timeout);

    const headers = {
      'content-type': 'application/json',
      'x-kingsway-worker-secret': workerSecret,
      'x-request-id': options.requestId || crypto.randomUUID(),
      'host': hostHeader,
      ...(options.operatorId && { 'x-internal-user-id': String(options.operatorId) }),
      ...(options.permissions && { 'x-internal-permissions': JSON.stringify(options.permissions) }),
      ...options.headers,
    };

    const body = options.body ? JSON.stringify(options.body) : undefined;

    try {
      const result = await breaker.execute(async () => {
        const res = await pool.request({
          method,
          path: `${targetPath}${queryString}`,
          headers,
          body,
          signal: controller.signal,
        });

        const responseBody = await res.body.text();
        let data;
        try {
          data = responseBody ? JSON.parse(responseBody) : null;
        } catch {
          data = responseBody;
        }

        metrics.internalHttpRequests.inc({ service, endpoint: path, status: res.statusCode });

        if (res.statusCode >= 400) {
          const error = new Error(`HTTP ${res.statusCode}`);
          error.statusCode = res.statusCode;
          error.response = data;
          throw error;
        }

        return data;
      });

      clearTimeout(timeoutId);
      return result;
    } catch (e) {
      clearTimeout(timeoutId);
      logger.error({ err: e.message, service, path, method }, 'Internal HTTP request failed');
      throw e;
    }
  }

  async function fetchViewData(spec, operatorId, authorization = '', input = {}) {
    const view = spec.view;
    if (!view) {
      throw new Error(`View spec is missing the canonical view key (spec.view)`);
    }

    // Interactive browser reads fan out to the registered, existing PHP API
    // endpoints. Their normal Auth/RBAC/controller path remains authoritative.
    // The bearer is request-scoped and is never included in cache keys/results.
    if (authorization && Array.isArray(spec.queries) && spec.queries.length) {
      const datasets = await Promise.all(spec.queries.map(async (query) => {
        const endpoint = String(query.endpoint || '').replace(/^\/api(?=\/)/, '');
        if (!endpoint.startsWith('/')) throw new Error(`Invalid registered endpoint for ${view}`);

        const params = {
          ...(query.staticParams && typeof query.staticParams === 'object' ? query.staticParams : {}),
          ...(input && typeof input === 'object' ? input : {}),
        };
        const readPage = async (page) => {
          const pageParams = { ...params };
          if (query.pagination) {
            pageParams[query.pagination.pageParam || 'page'] = String(page);
            pageParams[query.pagination.limitParam || 'limit'] = String(query.pagination.pageSize);
          }
          const response = await request('php', 'GET', endpoint, {
            params: pageParams,
            headers: { authorization },
          });
          const first = response?.data ?? response;
          return first && typeof first === 'object' && !Array.isArray(first)
            && typeof first.success === 'boolean' && Object.hasOwn(first, 'data')
            ? first.data
            : first;
        };

        let value = await readPage(1);
        if (query.pagination) {
          const collection = query.pagination.collection;
          const pageSize = Number(query.pagination.pageSize);
          const maxPages = Number(query.pagination.maxPages);
          const totalPages = Number(value?.pagination?.total_pages ?? value?.pagination?.pages ?? 1);
          if (typeof collection !== 'string' || !/^[a-z][a-z0-9_]{0,63}$/i.test(collection)
            || !Number.isSafeInteger(pageSize) || pageSize < 1 || pageSize > 100
            || !Number.isSafeInteger(maxPages) || maxPages < 1 || maxPages > 100
            || !Number.isSafeInteger(totalPages) || totalPages < 0) {
            const error = new Error(`Invalid pagination contract for ${view}`);
            error.statusCode = 502;
            throw error;
          }
          if (totalPages > maxPages) {
            const error = new Error(`Source exceeds the registered page limit for ${view}`);
            error.statusCode = 422;
            throw error;
          }

          const remainingPages = Array.from(
            { length: Math.max(0, totalPages - 1) },
            (_, index) => index + 2,
          );
          // Keep a large authorized roster from creating an unbounded burst
          // against PHP/DB workers. Pages remain concurrent in small batches.
          const laterPages = [];
          const pageConcurrency = 8;
          for (let index = 0; index < remainingPages.length; index += pageConcurrency) {
            const pageBatch = remainingPages.slice(index, index + pageConcurrency);
            laterPages.push(...await Promise.all(pageBatch.map(readPage)));
          }
          const rows = [value, ...laterPages].flatMap((pageData) => (
            Array.isArray(pageData?.[collection]) ? pageData[collection] : []
          ));
          const uniqueRows = [];
          const seenIds = new Set();
          for (const row of rows) {
            const id = row && row.id !== undefined ? String(row.id) : null;
            if (id !== null && seenIds.has(id)) continue;
            if (id !== null) seenIds.add(id);
            uniqueRows.push(row);
          }

          const sourceValue = value && typeof value === 'object' && !Array.isArray(value)
            ? { ...value }
            : {};
          // Source pages are transport details only. The page receives the
          // complete assembled collection and never a server-paging contract.
          delete sourceValue.pagination;
          value = {
            ...sourceValue,
            [collection]: uniqueRows,
          };
        }
        return [query.key, value];
      }));

      return Object.fromEntries(datasets);
    }

    throw new Error(`Interactive view ${view} requires a bearer and registered PHP source endpoints`);
  }

  clientCache = {
    request,
    get: (service, path, options = {}) => request(service, 'GET', path, options),
    post: (service, path, body, options = {}) => request(service, 'POST', path, { ...options, body }),
    fetchViewData,
    close: async () => {
      await phpPool.close();
      await pythonPool.close();
    },
  };

  return clientCache;
}

export function getInternalClient() {
  return clientCache || createInternalClient();
}

export function resetInternalClient() {
  clientCache = null;
}
