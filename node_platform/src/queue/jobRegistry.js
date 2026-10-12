import Database from 'better-sqlite3';
import { getConfig } from '../config/index.js';
import { getLogger } from '../logger/index.js';

let registryCache = null;

const DEFAULT_TTL_SECONDS = 3600;
const STATUSES = new Set(['queued', 'processing', 'progress', 'done', 'failed']);

/**
 * Job status mirror (doc: src/queue/jobRegistry.js).
 *
 * In-memory Map is the fast path for the gateway process; a SQLite table in
 * the shared cache file (WAL) keeps status visible to other gateway workers on
 * the same node so the jobs widget can read state across Passenger processes.
 * Records auto-expire after the TTL (default 1h).
 */
function createJobRegistry() {
  if (registryCache) return registryCache;

  const config = getConfig();
  const logger = getLogger().child({ component: 'job-registry' });

  const db = new Database(config.storage.cache);
  db.pragma('journal_mode');
  db.pragma('synchronous = NORMAL');
  db.pragma('busy_timeout = 5000');

  db.exec(`
    CREATE TABLE IF NOT EXISTS job_registry (
      id INTEGER PRIMARY KEY,
      name TEXT NOT NULL,
      user_id INTEGER NOT NULL DEFAULT 0,
      channels TEXT NOT NULL DEFAULT '[]',
      priority TEXT NOT NULL DEFAULT 'normal',
      status TEXT NOT NULL DEFAULT 'queued',
      progress INTEGER NOT NULL DEFAULT 0,
      result TEXT,
      error TEXT,
      created_at INTEGER NOT NULL,
      updated_at INTEGER NOT NULL,
      expires_at INTEGER NOT NULL
    );
    CREATE INDEX IF NOT EXISTS idx_job_registry_user ON job_registry(user_id, status);
    CREATE INDEX IF NOT EXISTS idx_job_registry_expires ON job_registry(expires_at);
  `);

  const stmtInsert = db.prepare(`
    INSERT OR REPLACE INTO job_registry
      (id, name, user_id, channels, priority, status, progress, result, error, created_at, updated_at, expires_at)
    VALUES (@id, @name, @userId, @channels, @priority, @status, @progress, @result, @error, @createdAt, @updatedAt, @expiresAt)
  `);
  const stmtGet = db.prepare('SELECT * FROM job_registry WHERE id = ? AND expires_at > ?');
  const stmtByUser = db.prepare('SELECT id, status FROM job_registry WHERE user_id = ? AND status != ? AND expires_at > ? ORDER BY updated_at DESC');
  const stmtCleanup = db.prepare('DELETE FROM job_registry WHERE expires_at <= ?');

  const records = new Map();     // id -> record
  const userIdIndex = new Map(); // userId -> Set<id>

  function persist(record) {
    try {
      stmtInsert.run({
        ...record,
        channels: JSON.stringify(record.channels || []),
        result: record.result === undefined ? null : JSON.stringify(record.result),
      });
    } catch (e) {
      logger.error({ err: e.message, jobId: record.id }, 'Job registry persist failed');
    }
  }

  function indexAdd(record) {
    if (!userIdIndex.has(record.userId)) userIdIndex.set(record.userId, new Set());
    userIdIndex.get(record.userId).add(record.id);
  }

  function indexRemove(userId, id) {
    const set = userIdIndex.get(userId);
    if (set) {
      set.delete(id);
      if (set.size === 0) userIdIndex.delete(userId);
    }
  }

  function touch(record, now = Date.now()) {
    record.updatedAt = now;
    record.expiresAt = now + (config.queue.jobRegistryTtlSeconds || DEFAULT_TTL_SECONDS) * 1000;
  }

  function register({ id, name, userId = 0, channels = [], priority = 'normal' }) {
    if (!Number.isInteger(id) || id < 1 || typeof name !== 'string' || name === '') {
      return null;
    }
    const now = Date.now();
    const record = {
      id,
      name,
      userId,
      channels: Array.isArray(channels) ? channels : [],
      priority: String(priority || 'normal'),
      status: 'queued',
      progress: 0,
      result: undefined,
      error: null,
      createdAt: now,
      updatedAt: now,
      expiresAt: now + (config.queue.jobRegistryTtlSeconds || DEFAULT_TTL_SECONDS) * 1000,
    };
    records.set(id, record);
    indexAdd(record);
    persist(record);
    return record;
  }

  function get(id) {
    const mem = records.get(id);
    if (mem) return { ...mem, channels: Array.from(mem.channels || []) };
    const row = stmtGet.get(id, Date.now());
    if (!row) return null;
    return {
      id: row.id,
      name: row.name,
      userId: row.user_id,
      channels: safeParseArray(row.channels),
      priority: row.priority,
      status: row.status,
      progress: row.progress,
      result: safeParse(row.result),
      error: row.error,
      createdAt: row.created_at,
      updatedAt: row.updated_at,
      expiresAt: row.expires_at,
    };
  }

  function byUser(userId) {
    const set = userIdIndex.get(userId);
    const ids = set ? Array.from(set) : [];
    if (ids.length === 0) {
      return stmtByUser.all(userId, 'done', Date.now()).map(r => ({
        id: r.id,
        status: r.status,
      }));
    }
    return ids
      .map(id => get(id))
      .filter(r => r && r.status !== 'done')
      .sort((a, b) => b.updatedAt - a.updatedAt);
  }

  function active() {
    return Array.from(records.values())
      .filter(r => r.status === 'queued' || r.status === 'processing' || r.status === 'progress')
      .sort((a, b) => b.updatedAt - a.updatedAt);
  }

  function update(id, patch = {}) {
    const record = records.get(id);
    if (!record) return null;

    if (patch.status !== undefined && !STATUSES.has(patch.status)) {
      return null;
    }
    if (patch.status) {
      record.status = patch.status;
    }
    if (Number.isInteger(patch.progress)) {
      record.progress = Math.max(0, Math.min(100, patch.progress));
    }
    if (patch.result !== undefined) {
      record.result = patch.result;
    }
    if (patch.error !== undefined) {
      record.error = patch.error;
    }
    touch(record);
    persist(record);
    return { ...record, channels: Array.from(record.channels || []) };
  }

  function markProcessing(id) { return update(id, { status: 'processing', progress: 0 }); }
  function markProgress(id, progress) { return update(id, { status: 'progress', progress }); }
  function storeResult(id, result) { return update(id, { result }); }
  function markComplete(id) { return update(id, { status: 'done', progress: 100 }); }
  function markFailed(id, error) { return update(id, { status: 'failed', error }); }

  function stats() {
    const counts = { queued: 0, processing: 0, progress: 0, done: 0, failed: 0 };
    for (const r of records.values()) {
      if (counts[r.status] !== undefined) counts[r.status] += 1;
    }
    return { total: records.size, ...counts };
  }

  function sweep() {
    try {
      const result = stmtCleanup.run(Date.now());
      for (const [id, r] of records.entries()) {
        if (r.expiresAt <= Date.now()) {
          records.delete(id);
          indexRemove(r.userId, id);
        }
      }
      if (result.changes > 0) logger.debug({ removed: result.changes }, 'Job registry swept');
    } catch (e) {
      logger.error({ err: e.message }, 'Job registry sweep failed');
    }
  }

  const sweepInterval = setInterval(sweep, 60000);
  sweepInterval.unref?.();

  function close() {
    clearInterval(sweepInterval);
    db.close();
  }

  registryCache = {
    register,
    get,
    byUser,
    active,
    update,
    markProcessing,
    markProgress,
    storeResult,
    markComplete,
    markFailed,
    stats,
    sweep,
    close,
  };

  return registryCache;
}

function safeParse(value) {
  if (value === null || value === undefined) return undefined;
  try {
    return JSON.parse(value);
  } catch {
    return value;
  }
}

function safeParseArray(value) {
  const parsed = safeParse(value);
  return Array.isArray(parsed) ? parsed : [];
}

export function getJobRegistry() {
  return registryCache || createJobRegistry();
}

export function resetJobRegistry() {
  if (registryCache) {
    try { registryCache.close(); } catch {}
    registryCache = null;
  }
}

export { createJobRegistry };
