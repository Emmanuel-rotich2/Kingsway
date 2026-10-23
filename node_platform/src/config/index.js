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
    internalSecret: z.string().min(1),
    aiPythonSecret: z.string().min(1),
    // Threat detection configuration
    threatDetection: z.object({
      bruteForce: z.object({
        count: z.number().int().min(1).default(5),
        windowMs: z.number().int().min(1000).default(900000), // 15 min
        uniqueUsers: z.number().int().min(1).default(3),
        alertCooldownMs: z.number().int().min(1000).default(1800000), // 30 min
      }).optional(),
      credentialStuffing: z.object({
        count: z.number().int().min(1).default(10),
        windowMs: z.number().int().min(1000).default(3600000), // 1 hour
        alertCooldownMs: z.number().int().min(1000).default(3600000), // 1 hour
      }).optional(),
      suspiciousQuery: z.object({
        count: z.number().int().min(1).default(3),
        windowMs: z.number().int().min(1000).default(3600000), // 1 hour
        alertCooldownMs: z.number().int().min(1000).default(3600000), // 1 hour
      }).optional(),
      offTopic: z.object({
        minLength: z.number().int().min(1).default(20),
      }).optional(),
    }).optional(),
    // Public assistant rate limiting
    publicRateLimit: z.object({
      requestsPerMinute: z.number().int().min(1).default(20),
      burstLimit: z.number().int().min(1).default(50),
    }).optional(),
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

  // Priority: process.env (Passenger SetEnv, Docker, systemd) > .env file > YAML defaults
  // .env file is only for local development; production uses Passenger SetEnv / Docker env / systemd env
  const fileConfig = loadEnvFile(resolve(__dirname, '.env'));
  // process.env takes precedence (Passenger SetEnv in production, Docker env, systemd)
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
        .filter(Boolean)
        .concat(['https://localhost', 'http://localhost']),
      internalSecret: mergedEnv.KINGSWAY_INTERNAL_SECRET || mergedEnv.NODE_REALTIME_PUBLISH_SECRET || mergedEnv.KINGSWAY_WORKER_SECRET || yamlConfig.security?.internalSecret || '',
      aiPythonSecret: mergedEnv.AI_PYTHON_SECRET || mergedEnv.NODE_REALTIME_PUBLISH_SECRET || mergedEnv.KINGSWAY_WORKER_SECRET || yamlConfig.security?.aiPythonSecret || '',
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
      // Memory-safety soft cap per user (oldest connections are evicted,
      // never a 429 lockout). Tunable from the host panel without redeploy.
      userMaxConnections: mergedEnv.NODE_REALTIME_USER_MAX_CONNECTIONS
        ? Number.parseInt(mergedEnv.NODE_REALTIME_USER_MAX_CONNECTIONS, 10)
        : yamlConfig.sse?.userMaxConnections,
      maxConnections: mergedEnv.NODE_REALTIME_MAX_CONNECTIONS
        ? Number.parseInt(mergedEnv.NODE_REALTIME_MAX_CONNECTIONS, 10)
        : yamlConfig.sse?.maxConnections,
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
