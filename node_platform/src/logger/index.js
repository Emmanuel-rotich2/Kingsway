import pino from 'pino';
import { getConfig } from '../config/index.js';

let loggerCache = null;

export function createLogger(options = {}) {
  if (loggerCache) return loggerCache;

  const config = getConfig();
  const logConfig = config.logging;

  const streams = [
    {
      level: options.level || logConfig.level,
      stream: process.stdout,
    },
  ];

  if (logConfig.pretty) {
    streams[0].stream = pino.transport({
      target: 'pino-pretty',
      options: { colorize: true, translateTime: 'SYS:standard', ignore: 'pid,hostname' },
    });
  }

  const logger = pino({
    level: options.level || logConfig.level,
    redact: {
      paths: logConfig.redactionPaths || [],
      censor: '[REDACTED]',
    },
    base: {
      host: config.app.host,
      port: config.app.port,
      pid: process.pid,
      instance: config.app.instanceId,
    },
  }, pino.multistream(streams));

  loggerCache = logger;
  return logger;
}

export function getLogger() {
  return loggerCache || createLogger();
}

export function resetLogger() {
  loggerCache = null;
}