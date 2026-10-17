import Database from 'better-sqlite3';
import { mkdirSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { getConfig } from '../config/index.js';
import { getLogger } from '../logger/index.js';
import { getMetrics } from '../metrics/index.js';

let cacheCache = null;

class LRUCache {
  constructor(maxEntries = 10000) {
    this.maxEntries = maxEntries;
    this.map = new Map();
  }

  get(key) {
    const entry = this.map.get(key);
    if (!entry) return null;
    if (entry.expiresAt && Date.now() > entry.expiresAt) {
      this.map.delete(key);
      return null;
    }
    this.map.delete(key);
    this.map.set(key, entry);
    return entry.value;
  }

  set(key, value, ttlSeconds) {
    if (this.map.size >= this.maxEntries) {
      const firstKey = this.map.keys().next().value;
      this.map.delete(firstKey);
    }
    this.map.set(key, {
      value,
      expiresAt: ttlSeconds ? Date.now() + ttlSeconds * 1000 : null,
    });
  }

  delete(key) {
    this.map.delete(key);
  }

  clear() {
    this.map.clear();
  }

  size() {
    return this.map.size;
  }
}

function createCache() {
  if (cacheCache) return cacheCache;

  const config = getConfig();
  const logger = getLogger().child({ component: 'cache' });
  const metrics = getMetrics();

  const dbPath = config.storage.cache;
  mkdirSync(dirname(dbPath), { recursive: true, mode: 0o700 });
  const db = new Database(dbPath);
  for (const pragma of config.cache.sqlitePragma.split(';').map(s => s.trim()).filter(Boolean)) {
    db.pragma(pragma);
  }

  db.prepare("CREATE TABLE IF NOT EXISTS cache (\n    key TEXT PRIMARY KEY,\n    value TEXT NOT NULL,\n    expires_at INTEGER,\n    created_at INTEGER DEFAULT (strftime('%s', 'now'))\n  )").run();
  db.prepare("CREATE INDEX IF NOT EXISTS idx_cache_expires ON cache(expires_at)").run();

  const lru = new LRUCache(config.cache.memoryMaxEntries);
  const defaultTtl = config.cache.defaultTtlSeconds;

  let statements = [];

  function prepareStatement(sql) {
    const stmt = db.prepare(sql);
    statements.push(stmt);
    return stmt;
  }

  const stmtGet = prepareStatement('SELECT value, expires_at FROM cache WHERE key = ?');
  const stmtSet = prepareStatement('INSERT OR REPLACE INTO cache (key, value, expires_at) VALUES (?, ?, ?)');
  const stmtDel = prepareStatement('DELETE FROM cache WHERE key = ?');
  const stmtDelPattern = prepareStatement('DELETE FROM cache WHERE key LIKE ?');
  const stmtCleanup = prepareStatement('DELETE FROM cache WHERE expires_at IS NOT NULL AND expires_at < ?');

  async function get(key) {
    const lruVal = lru.get(key);
    if (lruVal !== null) {
      metrics.cacheHits.inc({ cache_type: 'memory' });
      return lruVal;
    }

    const row = stmtGet.get(key);
    if (!row) return null;

    // SQLite stores expiry in Unix seconds; the in-memory cache uses ms.
    if (row.expires_at && Math.floor(Date.now() / 1000) > row.expires_at) {
      stmtDel.run(key);
      return null;
    }

    let value;
    try {
      value = JSON.parse(row.value);
    } catch {
      value = row.value;
    }

    lru.set(key, value, defaultTtl);
    metrics.cacheHits.inc({ cache_type: 'sqlite' });
    return value;
  }

  async function set(key, value, ttlSeconds = defaultTtl) {
    const serialized = JSON.stringify(value);
    const expiresAt = ttlSeconds ? Date.now() + ttlSeconds * 1000 : null;
    stmtSet.run(key, serialized, expiresAt ? Math.floor(expiresAt / 1000) : null);
    lru.set(key, value, ttlSeconds);
  }

  async function del(key) {
    stmtDel.run(key);
    lru.delete(key);
  }

  async function delPattern(pattern) {
    const sqlPattern = pattern.replace(/\*/g, '%');
    stmtDelPattern.run(sqlPattern);
    for (const key of lru.map.keys()) {
      if (key.match(pattern.replace(/\*/g, '.*'))) {
        lru.delete(key);
      }
    }
  }

  let cleanupInterval = null;

  async function cleanup() {
    const now = Math.floor(Date.now() / 1000);
    const result = stmtCleanup.run(now);
    logger.debug({ deleted: result.changes }, 'Cache cleanup');
  }

  cleanupInterval = setInterval(cleanup, 60000);
  cleanupInterval.unref?.();

  cacheCache = {
    get,
    set,
    del,
    delPattern,
    getStats: () => ({
      memory: lru.size(),
      sqlite: db.prepare('SELECT COUNT(*) as count FROM cache').get().count,
    }),
    close: () => {
      if (cleanupInterval) {
        clearInterval(cleanupInterval);
        cleanupInterval = null;
      }
      // Explicitly finalize all statements before closing database
      for (const stmt of statements) {
        try { stmt.finalize(); } catch {}
      }
      statements.length = 0;
      statements = null;
      db.close();
    },
  };

  return cacheCache;
}

function getCache() {
  return cacheCache || createCache();
}

function resetCache() {
  cacheCache = null;
}

export { createCache, getCache, resetCache };
