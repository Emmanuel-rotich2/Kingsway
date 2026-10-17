import { mkdir, appendFile, readdir, stat, unlink, open } from 'node:fs/promises';
import { resolve, basename } from 'node:path';
import { getConfig } from '../config/index.js';
import { getLogger } from '../logger/index.js';

let busCache = null;

const ROTATE_BYTES = 4 * 1024 * 1024;
const CLEANUP_INTERVAL = 3600000;
const MESSAGE_TTL = 24 * 3600000;

export function createBus() {
  if (busCache) return busCache;

  const config = getConfig();
  const logger = getLogger().child({ component: 'bus' });
  const busDir = resolve(config.storage.bus);

  const subscribers = new Map();
  const fileHandles = new Map();
  let initialized = false;

  async function init() {
    if (initialized) return;
    await mkdir(busDir, { recursive: true });
    await cleanupOldFiles();
    setInterval(cleanupOldFiles, CLEANUP_INTERVAL);
    initialized = true;
  }

  function getChannelFile(channel) {
    const safeChannel = channel.replace(/[^a-zA-Z0-9_:.-]/g, '_');
    return resolve(busDir, `${safeChannel}.jsonl`);
  }

  async function getFileHandle(channel) {
    const filePath = getChannelFile(channel);
    let handle = fileHandles.get(channel);
    if (!handle) {
      handle = await open(filePath, 'a');
      fileHandles.set(channel, handle);
    }
    return handle;
  }

  async function publish(channel, event) {
    await init();
    const message = {
      id: `${Date.now()}-${Math.random().toString(36).slice(2, 9)}`,
      channel,
      type: event.type || 'message',
      payload: event.payload || event,
      timestamp: Date.now(),
      source: event.source || 'gateway',
      correlationId: event.correlationId,
    };

    const line = JSON.stringify(message) + '\n';
    const handle = await getFileHandle(channel);
    await handle.write(line);

    const stats = await stat(getChannelFile(channel));
    if (stats.size > ROTATE_BYTES) {
      await rotateChannel(channel);
    }

    const subs = subscribers.get(channel) || [];
    for (const cb of subs) {
      try { cb(message); } catch (e) { logger.error({ err: e.message }, 'Bus subscriber error'); }
    }

    return message.id;
  }

  async function rotateChannel(channel) {
    const handle = fileHandles.get(channel);
    if (handle) {
      await handle.close();
      fileHandles.delete(channel);
    }
    const oldPath = getChannelFile(channel);
    const newPath = `${oldPath}.${Date.now()}`;
    await import('node:fs/promises').then(fs => fs.rename(oldPath, newPath));
  }

  function subscribe(channel, callback) {
    if (!subscribers.has(channel)) subscribers.set(channel, new Set());
    subscribers.get(channel).add(callback);
    return () => {
      const subs = subscribers.get(channel);
      if (subs) subs.delete(callback);
    };
  }

  async function replay(channel, fromOffset = 0, callback) {
    await init();
    const filePath = getChannelFile(channel);
    try {
      const { createReadStream } = await import('node:fs');
      const { createInterface } = await import('node:readline');
      const stream = createReadStream(filePath);
      const rl = createInterface({ input: stream, crlfDelay: Infinity });
      let lineNum = 0;
      for await (const line of rl) {
        if (lineNum++ < fromOffset) continue;
        try {
          const msg = JSON.parse(line);
          callback(msg);
        } catch {}
      }
    } catch (e) {
      if (e.code !== 'ENOENT') logger.error({ err: e.message }, 'Replay failed');
    }
  }

  async function cleanupOldFiles() {
    try {
      const files = await readdir(busDir);
      const now = Date.now();
      for (const file of files) {
        const filePath = resolve(busDir, file);
        const stats = await stat(filePath);
        if (now - stats.mtimeMs > MESSAGE_TTL) {
          await unlink(filePath);
        }
      }
    } catch (e) {
      logger.error({ err: e.message }, 'Bus cleanup failed');
    }
  }

  function isHealthy() {
    return initialized;
  }

  async function close() {
    for (const handle of fileHandles.values()) {
      await handle.close();
    }
    fileHandles.clear();
    subscribers.clear();
  }

  busCache = { publish, subscribe, replay, isHealthy, close, init };
  return busCache;
}

export function getBus() {
  return busCache || createBus();
}

export function resetBus() {
  busCache = null;
}