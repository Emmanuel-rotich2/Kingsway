import { readFileSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import yaml from 'yaml';
import { z } from 'zod';

const __dirname = dirname(fileURLToPath(import.meta.url));
const APP_ROOT = resolve(__dirname, '../../..');

const ConfigSchema = z.object({
  app: z.object({
    name: z.string(),
    env: z.enum(['development', 'production', 'test']),
    instanceId: z.string().optional(),
  }),
  host: z.string(),
  port: z.number().int().min(1).max(65535),
  storage: z.object({
    root: z.string(),
    cache: z.string(),
    bus: z.string(),
    logs: z.string(),
  }),
  sse: z.object({
    maxConnections: z.number().int().min(1),
    userMaxConnections: z.number().int().min(1),
    heartbeatMs: z.number().int().min(1000),
    queueLimit: z.number().int().min(1),
    slowClientTimeoutMs: z.number().int().min(100),
    presenceTtlMs: z.number().int().min(1000),
    capabilityTtlMinutes: z.number().int().min(1),
  }),
  cache: z.object({
    memoryMaxEntries: z.number().int().min(1),
    defaultTtlSeconds: z.number().int().min(1),
    sqlitePragma: z.string(),
  }),
  internalHttp: z.object({
    php: z.object({
      baseUrl: z.string().url(),
      hostHeader: z.string(),
      apiPrefix: z.string().default('/api'),
      tlsInsecure: z.boolean().default(false),
      timeoutMs: z.number().int().min(1000),
      poolSize: z.number().int().min(1),
    }),
    python: z.object({
      baseUrl: z.string().url(),
      hostHeader: z.string(),
      apiPrefix: z.string().default(''),
      tlsInsecure: z.boolean().default(false),
      timeoutMs: z.number().int().min(1000),
      poolSize: z.number().int().min(1),
    }),
  }),
  security: z.object({
    jwtSecret: z.string().min(32),
    jwtIssuer: z.string(),
    jwtAudience: z.string(),
    workerSecret: z.string().min(1),
    webOrigins: z.array(z.string().url()),
    publishAllowIps: z.array(z.string()),
  }),
  metrics: z.object({
    prefix: z.string(),
    collectDefault: z.boolean(),
  }),
  logging: z.object({
    level: z.enum(['trace', 'debug', 'info', 'warn', 'error', 'fatal']),
    pretty: z.boolean(),
    redactionPaths: z.array(z.string()),
  }),
});

let configCache = null;

function loadEnvFile(filePath) {
  try {
    const raw = readFileSync(filePath, 'utf8');
    const parsed = {};
    for (const line of raw.split(/\r?\n/)) {
      const trimmed = line.trim();
      if (!trimmed || trimmed.startsWith('#')) continue;
      const eq = trimmed.indexOf('=');
      if (eq <= 0) continue;
      const key = trimmed.slice(0, eq).trim();
      let value = trimmed.slice(eq + 1).trim();
      if ((value.startsWith('"') && value.endsWith('"') && value.length >= 2) ||
          (value.startsWith("'") && value.endsWith("'") && value.length >= 2)) {
        value = value.slice(1, -1);
      }
      if (key) parsed[key] = value;
    }
    return parsed;
  } catch {
    return {};
  }
}

function mergeEnv(fileEnv, processEnv) {
  const merged = { ...fileEnv };
  for (const [key, value] of Object.entries(processEnv)) {
    if (value !== undefined && value !== '') merged[key] = value;
  }
  return merged;
}

function resolveStoragePaths(config) {
  // All three runtimes share a root and keep their own files in separate
  // namespaces. When STORAGE_ROOT is absent the repository default applies;
  // PHP provisions the directory tree at runtime, so no manual setup and no
  // startup failure is required for any of the three runtimes.
  const storageRoot = config.storage.root || resolve(APP_ROOT, 'storage');
  if (!storageRoot.startsWith('/')) {
    throw new Error(`storage.root must be absolute path, got: ${storageRoot}`);
  }
  const nodeRoot = resolve(storageRoot, 'node');
  return {
    ...config,
    storage: {
      ...config.storage,
      root: nodeRoot,
      cache: resolve(nodeRoot, config.storage.cache),
      bus: resolve(nodeRoot, config.storage.bus),
      logs: resolve(nodeRoot, config.storage.logs),
    },
  };
}

export function loadConfig(overrides = {}) {
  if (configCache) return configCache;

  const env = process.env.NODE_ENV || 'development';
  const configPath = resolve(__dirname, `../../config/${env}.yaml`);
  const fileConfig = loadEnvFile(resolve(__dirname, '../../.env'));
  const mergedEnv = mergeEnv(fileConfig, process.env);

  let yamlConfig = {};
  try {
    const content = readFileSync(configPath, 'utf-8');
    yamlConfig = yaml.parse(content);
  } catch (e) {
    console.warn(`Config file not found: ${configPath}, using env vars only`);
  }

  const rawConfig = {
    ...yamlConfig,
    host: yamlConfig.app?.host,
    port: yamlConfig.app?.port,
    security: {
      ...yamlConfig.security,
      jwtSecret: mergedEnv.JWT_SECRET || yamlConfig.security?.jwtSecret || '',
      workerSecret: mergedEnv.KINGSWAY_WORKER_SECRET || yamlConfig.security?.workerSecret || '',
      webOrigins: (mergedEnv.NODE_REALTIME_WEB_ORIGIN || yamlConfig.security?.webOrigins?.join(',') || '')
        .split(',')
        .map(s => s.trim())
        .filter(Boolean),
    },
    app: {
      ...yamlConfig.app,
      instanceId: mergedEnv.NODE_REALTIME_INSTANCE_ID || yamlConfig.app?.instanceId || '',
    },
    storage: {
      ...yamlConfig.storage,
      root: mergedEnv.STORAGE_ROOT || resolve(APP_ROOT, 'storage'),
    },
    sse: {
      ...yamlConfig.sse,
      heartbeatMs: mergedEnv.NODE_REALTIME_HEARTBEAT_MS
        ? Number.parseInt(mergedEnv.NODE_REALTIME_HEARTBEAT_MS, 10)
        : yamlConfig.sse?.heartbeatMs,
    },
    internalHttp: {
      ...yamlConfig.internalHttp,
      php: overrideHttpService(
        yamlConfig.internalHttp?.php,
        mergedEnv.PHP_BASE_URL,
        '/api',
      ),
      python: overrideHttpService(
        yamlConfig.internalHttp?.python,
        mergedEnv.PYTHON_BASE_URL,
        '',
      ),
    },
  };

  const validated = ConfigSchema.parse(rawConfig);
  const resolved = resolveStoragePaths(validated);
  configCache = { ...resolved, ...overrides };
  return configCache;
}

/**
 * Apply the shared PHP_BASE_URL / PYTHON_BASE_URL contract (same env names as
 * the PHP backend) to an internalHttp service descriptor. The origin and Host
 * header are derived from the URL, and apiPrefix is the URL path up to and
 * including /api (PHP router) or empty (raw FastAPI root). When the base URL
 * already carries /api (production style), apiPrefix stays exactly that path.
 *
 * @param {{baseUrl?:string, hostHeader?:string}|undefined} base
 * @param {string} envBase Env override, e.g. https://app.example/api or http://127.0.0.1:8077
 * @param {string} fallbackPrefix Used when the parsed pathname is empty.
 * @returns {{baseUrl?:string, hostHeader?:string, apiPrefix?:string}}
 */
function overrideHttpService(base, envBase, fallbackPrefix) {
  const effective = envBase || base?.baseUrl || '';
  if (!effective) {
    return {
      ...(base || {}),
      apiPrefix: base?.apiPrefix || fallbackPrefix || '',
    };
  }
  try {
    const url = new URL(effective);
    const pathname = url.pathname.replace(/\/+$/, '');
    let prefix;
    if (pathname === '') {
      prefix = fallbackPrefix;
    } else if (pathname.endsWith('/api')) {
      prefix = pathname;
    } else {
      prefix = `${pathname}/api`;
    }
    return {
      ...(base || {}),
      baseUrl: url.origin,
      hostHeader: url.host,
      apiPrefix: prefix,
    };
  } catch {
    return { ...(base || {}), apiPrefix: fallbackPrefix };
  }
}

export function getConfig() {
  return configCache || loadConfig();
}

export function resetConfig() {
  configCache = null;
}
