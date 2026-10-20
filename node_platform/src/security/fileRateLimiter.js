import fs from 'node:fs';
import path from 'node:path';
import sqlite3 from 'sqlite3';
import { getConfig } from '../config/index.js';
import { getLogger } from '../logger/index.js';

/**
 * File-backed rate limiter for anonymous public traffic.
 *
 * Shared hosting has no Redis. Counters therefore live in SQLite beneath the
 * Node storage namespace (never in process memory), which also means limits
 * survive Passenger worker restarts and are shared by every worker process
 * using the same STORAGE_ROOT.
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

    this.ready = this.open();
    this.cleanupTimer = setInterval(() => {
      this.cleanup().catch((err) => {
        this.logger.warn({ err: err.message }, 'Rate limiter cleanup failed');
      });
    }, 10 * 60_000);
    this.cleanupTimer.unref?.();
  }

  open() {
    return new Promise((resolve, reject) => {
      this.db = new sqlite3.Database(this.dbPath, (err) => {
        if (err) {
          this.logger.error({ err: err.message, dbPath: this.dbPath }, 'Failed to open rate limit database');
          reject(err);
          return;
        }
        this.db.run(
          `CREATE TABLE IF NOT EXISTS rate_limits (
             bucket_key TEXT PRIMARY KEY,
             window_start INTEGER NOT NULL,
             window_count INTEGER NOT NULL,
             burst_start INTEGER NOT NULL,
             burst_count INTEGER NOT NULL
           )`,
          (createErr) => {
            if (createErr) {
              this.logger.error({ err: createErr.message }, 'Failed to create rate limit table');
              reject(createErr);
              return;
            }
            resolve();
          },
        );
      });
    });
  }

  run(sql, params = []) {
    return new Promise((resolve, reject) => {
      this.db.run(sql, params, function onRun(err) {
        if (err) reject(err);
        else resolve(this);
      });
    });
  }

  get(sql, params = []) {
    return new Promise((resolve, reject) => {
      this.db.get(sql, params, (err, row) => {
        if (err) reject(err);
        else resolve(row);
      });
    });
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
      const row = await this.get('SELECT * FROM rate_limits WHERE bucket_key = ?', [key]);
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

      await this.run(
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
    await this.run('DELETE FROM rate_limits WHERE window_start < ? AND burst_start < ?', [cutoff, cutoff]);
  }

  close() {
    if (this.cleanupTimer) clearInterval(this.cleanupTimer);
    if (this.db) this.db.close();
  }
}
