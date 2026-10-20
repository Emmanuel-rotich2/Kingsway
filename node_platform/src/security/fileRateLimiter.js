import fs from 'node:fs';
import path from 'node:path';
import { DatabaseSync } from 'node:sqlite';
import { getConfig } from '../config/index.js';
import { getLogger } from '../logger/index.js';

/**
 * File-backed rate limiter for anonymous public traffic.
 *
 * Shared hosting has no Redis. Counters therefore live in SQLite beneath the
 * Node storage namespace (never in process memory), which also means limits
 * survive Passenger worker restarts and are shared by every worker process
 * using the same STORAGE_ROOT.
 *
 * Uses Node's built-in `node:sqlite` module rather than the npm `sqlite3`
 * package: the latter ships a native addon that cannot run on older glibc
 * hosts (ERR_DLOPEN_FAILED / GLIBC_2.38), which breaks Passenger startup.
 */
export class FileRateLimiter {
  constructor({
    name = 'public',
    windowMs = 60_000,
    limit = 20,
    burstWindowMs = 10 * 60_000,
    burstLimit = 50,
  } = {}) {
    this.name = name;
    this.windowMs = windowMs;
    this.limit = limit;
    this.burstWindowMs = burstWindowMs;
    this.burstLimit = burstLimit;
    this.logger = getLogger().child({ component: 'fileRateLimiter', limiter: name });

    const config = getConfig();
    const storageRoot = config.storage?.root || path.resolve(process.cwd(), 'storage/node');
    const directory = path.join(storageRoot, 'ratelimit');
    fs.mkdirSync(directory, { recursive: true });
    this.dbPath = path.join(directory, `${name}.sqlite`);

    this.db = null;
    try {
      this.open();
      this.ready = Promise.resolve();
    } catch (err) {
      this.ready = Promise.reject(err);
    }

    this.cleanupTimer = setInterval(() => {
      this.cleanup().catch((err) => {
        this.logger.warn({ err: err.message }, 'Rate limiter cleanup failed');
      });
    }, 10 * 60_000);
    this.cleanupTimer.unref?.();
  }

  open() {
    try {
      this.db = new DatabaseSync(this.dbPath);
      this.db.exec('PRAGMA journal_mode = WAL');
      this.db.exec('PRAGMA synchronous = NORMAL');
      this.db.exec('PRAGMA busy_timeout = 5000');
      this.db.exec(
        `CREATE TABLE IF NOT EXISTS rate_limits (
           bucket_key TEXT PRIMARY KEY,
           window_start INTEGER NOT NULL,
           window_count INTEGER NOT NULL,
           burst_start INTEGER NOT NULL,
           burst_count INTEGER NOT NULL
         )`,
      );
    } catch (err) {
      this.logger.error({ err: err.message, dbPath: this.dbPath }, 'Failed to open rate limit database');
      throw err;
    }
  }

  run(sql, params = []) {
    return this.db.prepare(sql).run(...params);
  }

  get(sql, params = []) {
    return this.db.prepare(sql).get(...params);
  }

  async check(bucketKey) {
    try {
      await this.ready;
    } catch {
      // Fail open: the PHP edge still rate-limits public AI traffic.
      return { allowed: true };
    }

    const key = String(bucketKey || 'unknown');
    const now = Date.now();

    try {
      const row = this.get('SELECT * FROM rate_limits WHERE bucket_key = ?', [key]);
      let windowStart = row?.window_start ?? now;
      let windowCount = row?.window_count ?? 0;
      let burstStart = row?.burst_start ?? now;
      let burstCount = row?.burst_count ?? 0;

      if (now - windowStart >= this.windowMs) {
        windowStart = now;
        windowCount = 0;
      }
      if (now - burstStart >= this.burstWindowMs) {
        burstStart = now;
        burstCount = 0;
      }

      windowCount += 1;
      burstCount += 1;

      this.run(
        `INSERT INTO rate_limits (bucket_key, window_start, window_count, burst_start, burst_count)
         VALUES (?, ?, ?, ?, ?)
         ON CONFLICT(bucket_key) DO UPDATE SET
           window_start = excluded.window_start,
           window_count = excluded.window_count,
           burst_start = excluded.burst_start,
           burst_count = excluded.burst_count`,
        [key, windowStart, windowCount, burstStart, burstCount],
      );

      if (windowCount > this.limit) {
        return {
          allowed: false,
          retryAfter: Math.max(1, Math.ceil((windowStart + this.windowMs - now) / 1000)),
        };
      }
      if (burstCount > this.burstLimit) {
        return {
          allowed: false,
          retryAfter: Math.max(1, Math.ceil((burstStart + this.burstWindowMs - now) / 1000)),
        };
      }
      return { allowed: true };
    } catch (err) {
      this.logger.warn({ err: err.message }, 'Rate limit check failed; failing open');
      return { allowed: true };
    }
  }

  async cleanup() {
    await this.ready;
    const cutoff = Date.now() - Math.max(this.windowMs, this.burstWindowMs) * 2;
    this.run('DELETE FROM rate_limits WHERE window_start < ? AND burst_start < ?', [cutoff, cutoff]);
  }

  close() {
    if (this.cleanupTimer) clearInterval(this.cleanupTimer);
    if (this.db) {
      this.db.close();
      this.db = null;
    }
  }
}
