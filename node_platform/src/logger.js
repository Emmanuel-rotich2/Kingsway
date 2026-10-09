'use strict';

/**
 * Structured logging for the realtime gateway.
 *
 * pino writes one JSON object per line to stdout, which is what Passenger
 * captures with its own log retention. Secrets are redacted at the logger, not
 * at each call site, so a future call site cannot leak a token by omission.
 *
 * `journal(event, fields)` is kept as the documented operational entry point
 * (see AGENTS.md) and is a thin wrapper over the same structured logger.
 */

const pino = require('pino');

const REDACT_PATHS = [
  'req.headers.authorization',
  'req.headers.cookie',
  'req.headers["x-kingsway-sse-token"]',
  'req.headers["x-kingsway-worker-secret"]',
  'headers.authorization',
  'headers.cookie',
  'headers["x-kingsway-sse-token"]',
  'headers["x-kingsway-worker-secret"]',
  'token',
  '*.token',
  'jwtSecret',
  '*.jwtSecret',
  'publishSecret',
  '*.publishSecret',
  'password',
  '*.password',
];

const NOOP = () => {};

function prettyDestination() {
  // pino-pretty is a development-only dependency; production never needs it.
  try {
    const { PrettyStream } = require('pino-pretty');
    return PrettyStream({ colorize: true, translateTime: 'SYS:standard', ignore: 'pid,hostname' });
  } catch (_) {
    return process.stdout;
  }
}

/**
 * @param {object} options
 * @param {boolean} options.enabled  NODE_REALTIME_JOURNAL=off silences output
 * @param {boolean} options.pretty    human-readable stream for local work
 * @param {string}  options.level     pino level name
 * @param {object}  options.base      extra bound fields (port, host, pid…)
 */
function createLogger({ enabled = true, pretty = false, level = 'info', base = {} } = {}) {
  if (!enabled) {
    const silent = { level: 'silent' };
    const noopLogger = {
      trace: NOOP, debug: NOOP, info: NOOP, warn: NOOP, error: NOOP, fatal: NOOP,
      child: () => noopLogger,
    };
    // journal() stays callable so call sites never branch on logging state.
    noopLogger.journal = NOOP;
    return Object.assign(silent, noopLogger);
  }

  const logger = pino(
    {
      name: 'kingsway-realtime',
      level,
      base: { service: 'kingsway-realtime', ...base },
      redact: { paths: REDACT_PATHS, censor: '[redacted]', remove: false },
      timestamp: pino.stdTimeFunctions.isoTime,
      formatters: { level: (label) => ({ level: label }) },
    },
    pretty ? prettyDestination() : process.stdout,
  );

  /**
   * Operational metadata line. `event` is the stable machine-readable name
   * documented in AGENTS.md (publish.accepted, sse.connected, …). Values passed
   * here must be metadata only — never record bodies.
   */
  logger.journal = (event, fields = {}) => {
    logger.info({ journal: event, ...fields }, event);
  };

  return logger;
}

module.exports = { createLogger, REDACT_PATHS };