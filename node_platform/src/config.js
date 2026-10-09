'use strict';

/**
 * Environment-driven configuration for the realtime gateway.
 *
 * Environment-agnostic by design: the exact same code runs on localhost and on
 * the HostAfrica Passenger Node Selector deployment; only env values differ.
 * Secrets belong only in deployment env (never in source).
 *
 * Values load from `node_platform/.env` (stdlib parser — zero packages) and
 * are merged UNDER the process environment, so a real env var always wins.
 */

const fs = require('node:fs');
const path = require('node:path');

const DEFAULTS = Object.freeze({
  host: '127.0.0.1',
  port: 3000,
  jwtSecret: '',
  jwtIssuer: 'kingsway-prep-school',
  jwtAudience: 'kingsway-staff',
  publishSecret: '',
  webOrigin: '',
  maxBodyBytes: 16 * 1024,
  maxTokenTtlSeconds: 5 * 60,
  tokenClockSkewSeconds: 30,
  heartbeatMs: 20000,
  maxPayloadEntries: 20,
  maxChannels: 100,
  // Capacity ceilings. Defaults are sized for the documented 400-session target
  // with headroom; raise only alongside a measured load test, never on a hunch.
  maxConnections: 5000,
  queueLimit: 50,
  slowClientTimeoutMs: 5000,
  presenceTtlMs: 45000,
  presenceSweepMs: 15000,
  // Leading-edge coalescing: a lone write is delivered with zero added latency,
  // and only the trailing part of a burst is merged.
  coalesceWindowMs: 250,
  coalesceMaxWaitMs: 1000,
  // Optional cross-instance bus. Dormant (in-process) unless REDIS_URL is set.
  redisUrl: '',
  metricsPrefix: 'kingsway_',
  nodeEnv: 'development',
  instanceId: '',
  publishRatePerMinute: 6000,
});

/** Parse a KEY=VALUE env file (# comments, quotes). Missing file = {}. */
function loadEnvFile(filePath) {
  let raw;
  try {
    raw = fs.readFileSync(filePath, 'utf8');
  } catch (_) {
    return {};
  }
  const parsed = {};
  for (const line of raw.split(/\r?\n/)) {
    const trimmed = line.trim();
    if (!trimmed || trimmed.startsWith('#')) continue;
    const eq = trimmed.indexOf('=');
    if (eq <= 0) continue;
    const key = trimmed.slice(0, eq).trim();
    let value = trimmed.slice(eq + 1).trim();
    if (
      (value.startsWith('"') && value.endsWith('"') && value.length >= 2)
      || (value.startsWith("'") && value.endsWith("'") && value.length >= 2)
    ) {
      value = value.slice(1, -1);
    }
    if (key) parsed[key] = value;
  }
  return parsed;
}

/**
 * Merge the .env file UNDER the process environment. A non-empty process env
 * value always wins (deployment override safety); an empty one never shadows
 * a file value.
 */
function mergedEnv(env = process.env, filePath = path.join(__dirname, '..', '.env')) {
  const fileEnv = loadEnvFile(filePath);
  const merged = { ...fileEnv };
  for (const [key, value] of Object.entries(env)) {
    if (value !== undefined && value !== '') merged[key] = value;
  }
  return merged;
}

/**
 * Pure configuration resolution over already-collected env values. This never
 * touches the filesystem, which is what keeps the test suite hermetic: a test
 * that passes an env object cannot be influenced by `node_platform/.env`.
 */
function createConfig(env = mergedEnv(), overrides = {}) {
  const read = (key, fallback) => {
    const value = env[key];
    return value === undefined || value === '' ? fallback : value;
  };
  const int = (value, fallback) => {
    const parsed = Number(value);
    return Number.isInteger(parsed) && parsed > 0 ? parsed : fallback;
  };
  return Object.freeze({
    ...DEFAULTS,
    host: String(read('NODE_REALTIME_HOST', DEFAULTS.host)),
    port: Number(read('NODE_REALTIME_PORT', read('PORT', DEFAULTS.port))),
    jwtSecret: String(read('JWT_SECRET', '')),
    jwtIssuer: String(read('JWT_ISSUER', DEFAULTS.jwtIssuer)),
    jwtAudience: String(read('JWT_AUDIENCE', DEFAULTS.jwtAudience)),
    // Node and PHP must hold the SAME publish secret value; PHP reads it from
    // NODE_REALTIME_PUBLISH_SECRET with the KINGSWAY_WORKER_SECRET fallback.
    publishSecret: String(read('NODE_REALTIME_PUBLISH_SECRET', read('KINGSWAY_WORKER_SECRET', ''))),
    // Browser-facing ORIGIN allowlist — the origins that are ALLOWED TO OPEN A
    // STREAM. This is the PHP site's origin, NOT this gateway's own public URL.
    // Conflating the two (mapping the gateway URL here) rejects every real
    // browser with 403, because the EventSource is opened from the PHP site.
    // Comma-separated so dev (localhost) and production can both be listed.
    webOrigins: String(read('NODE_REALTIME_WEB_ORIGIN', '')),
    // This gateway's own public URL. Informational/self-referential only: it
    // grants no access and must never be used as an origin check.
    publicUrl: String(read('NODE_REALTIME_URL', read('REAL_TIME_URL', ''))),
    // Extra addresses allowed to publish beyond loopback (the roadmap's
    // "loopback/IP allowlist"); comma-separated, empty = loopback only.
    publishAllowIps: String(read('NODE_REALTIME_PUBLISH_ALLOW_IPS', '')),
    // Reserved passthrough keys from node_platform/.env. The current engine
    // NEVER calls PHP or Python (it only receives publishes and serves SSE);
    // these exist for future health/reporting surfaces and stay inert.
    pythonUrl: String(read('PYTHON_URL', '')),
    pythonSecret: String(read('PYTHON_SECRET', '')),
    phpBaseUrl: String(read('PHP_BASE_URL', '')),
    maxConnections: int(read('NODE_REALTIME_MAX_CONNECTIONS', DEFAULTS.maxConnections), DEFAULTS.maxConnections),
    queueLimit: int(read('NODE_REALTIME_QUEUE_LIMIT', DEFAULTS.queueLimit), DEFAULTS.queueLimit),
    slowClientTimeoutMs: int(read('NODE_REALTIME_SLOW_CLIENT_MS', DEFAULTS.slowClientTimeoutMs), DEFAULTS.slowClientTimeoutMs),
    presenceTtlMs: int(read('NODE_REALTIME_PRESENCE_TTL_MS', DEFAULTS.presenceTtlMs), DEFAULTS.presenceTtlMs),
    heartbeatMs: int(read('NODE_REALTIME_HEARTBEAT_MS', DEFAULTS.heartbeatMs), DEFAULTS.heartbeatMs),
    presenceSweepMs: int(read('NODE_REALTIME_PRESENCE_SWEEP_MS', DEFAULTS.presenceSweepMs), DEFAULTS.presenceSweepMs),
    coalesceWindowMs: int(read('NODE_REALTIME_COALESCE_WINDOW_MS', DEFAULTS.coalesceWindowMs), DEFAULTS.coalesceWindowMs),
    coalesceMaxWaitMs: int(read('NODE_REALTIME_COALESCE_MAX_WAIT_MS', DEFAULTS.coalesceMaxWaitMs), DEFAULTS.coalesceMaxWaitMs),
    redisUrl: String(read('REDIS_URL', '')),
    metricsPrefix: String(read('METRICS_PREFIX', DEFAULTS.metricsPrefix)),
    nodeEnv: String(read('NODE_ENV', DEFAULTS.nodeEnv)),
    instanceId: String(read('NODE_REALTIME_INSTANCE_ID', '')),
    publishRatePerMinute: int(read('NODE_REALTIME_PUBLISH_RATE', DEFAULTS.publishRatePerMinute), DEFAULTS.publishRatePerMinute),
    ...overrides,
  });
}

/**
 * Origins permitted to open an SSE stream.
 *
 * An empty list means NO origin check is enforced, which is only acceptable for
 * loopback development. In production the list is derived from the PHP site
 * rather than guessed, because an origin check pointed at the wrong host denies
 * every legitimate browser — a failure that looks like a broken gateway.
 *
 * @returns {string[]}
 */
function allowedOrigins(config) {
  const configured = String(config.webOrigins || '')
    .split(',')
    .map((value) => originOf(value))
    .filter(Boolean);

  if (configured.length) return [...new Set(configured)];

  const derived = originOf(config.phpBaseUrl);
  if (derived) return [derived];

  // The local PHP site is served from https://localhost while the development
  // gateway listens on loopback. Keep this narrow fallback development-only;
  // production must explicitly configure the PHP site's origin.
  if (config.nodeEnv === 'development') {
    return ['https://localhost', 'http://localhost'];
  }

  return [];
}

/** Extract `scheme://host[:port]` from a URL or origin string. */
function originOf(value) {
  const raw = String(value || '').trim();
  if (!raw) return '';
  try {
    return new URL(raw).origin;
  } catch (_) {
    return '';
  }
}

/** Fail fast on startup when secrets are missing or malformed. */
function validate(config) {
  const problems = [];
  if (String(config.jwtSecret).length < 32) problems.push('JWT_SECRET must contain at least 32 characters');
  if (!config.publishSecret) problems.push('NODE_REALTIME_PUBLISH_SECRET or KINGSWAY_WORKER_SECRET is required');
  if (!Number.isInteger(config.port) || config.port < 1 || config.port > 65535) {
    problems.push('NODE_REALTIME_PORT must be a valid port');
  }
  if (problems.length) throw new Error(problems.join('; '));
  return config;
}

module.exports = { createConfig, validate, mergedEnv, loadEnvFile, allowedOrigins, originOf, DEFAULTS };
