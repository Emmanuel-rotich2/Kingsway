import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import sqlite3 from 'sqlite3';
import { getConfig } from '../config/index.js';
import { getLogger } from '../logger/index.js';
import { getBus } from '../realtime/bus.js';

/**
 * Security threat detector.
 *
 * Every counter, cooldown and block decision is persisted in SQLite beneath the
 * shared Node storage namespace. Nothing that affects a decision lives only in
 * process memory: Passenger can recycle this worker at any time and the next
 * worker must see the same brute-force, credential-stuffing and blocked-IP
 * state. The only in-process state is the database handle itself.
 */
class ThreatDetector {
  constructor() {
    this.config = getConfig();
    this.logger = getLogger().child({ component: 'threatDetector' });
    this.bus = getBus();

    const storageRoot = this.config.storage?.root || path.resolve(process.cwd(), 'storage/node');
    const directory = path.join(storageRoot, 'security');
    fs.mkdirSync(directory, { recursive: true });
    this.dbPath = path.join(directory, 'threat_detection.sqlite');
    this.db = null;

    const configured = this.config.security?.threatDetection || {};
    this.thresholds = {
      bruteForce: {
        count: configured.bruteForce?.count ?? 5,
        windowMs: configured.bruteForce?.windowMs ?? 15 * 60 * 1000,
        uniqueUsers: configured.bruteForce?.uniqueUsers ?? 3,
        alertCooldownMs: configured.bruteForce?.alertCooldownMs ?? 30 * 60 * 1000,
      },
      credentialStuffing: {
        count: configured.credentialStuffing?.count ?? 10,
        windowMs: configured.credentialStuffing?.windowMs ?? 60 * 60 * 1000,
        alertCooldownMs: configured.credentialStuffing?.alertCooldownMs ?? 60 * 60 * 1000,
      },
      suspiciousQuery: {
        count: configured.suspiciousQuery?.count ?? 3,
        windowMs: configured.suspiciousQuery?.windowMs ?? 60 * 60 * 1000,
        alertCooldownMs: configured.suspiciousQuery?.alertCooldownMs ?? 60 * 60 * 1000,
      },
      offTopic: {
        minLength: configured.offTopic?.minLength ?? 20,
      },
    };

    this.ready = this.init();
    this.cleanupTimer = setInterval(() => {
      this.cleanup().catch((err) => {
        this.logger.warn({ err: err.message }, 'Threat detector cleanup failed');
      });
    }, 60 * 60 * 1000);
    this.cleanupTimer.unref?.();
  }

  async init() {
    await new Promise((resolve, reject) => {
      this.db = new sqlite3.Database(this.dbPath, (err) => {
        if (err) reject(err);
        else resolve();
      });
    });

    await this.run(`CREATE TABLE IF NOT EXISTS failed_logins (
      ip TEXT PRIMARY KEY,
      count INTEGER NOT NULL DEFAULT 0,
      first_attempt INTEGER NOT NULL,
      usernames TEXT NOT NULL DEFAULT '[]',
      last_alert INTEGER NOT NULL DEFAULT 0,
      updated_at INTEGER NOT NULL
    )`);
    await this.run(`CREATE TABLE IF NOT EXISTS suspicious_queries (
      ip TEXT PRIMARY KEY,
      count INTEGER NOT NULL DEFAULT 0,
      window_start INTEGER NOT NULL,
      topics TEXT NOT NULL DEFAULT '[]',
      last_alert INTEGER NOT NULL DEFAULT 0,
      updated_at INTEGER NOT NULL
    )`);
    await this.run(`CREATE TABLE IF NOT EXISTS blocked_ips (
      ip TEXT PRIMARY KEY,
      reason TEXT NOT NULL,
      blocked_at INTEGER NOT NULL,
      expires_at INTEGER
    )`);
    await this.run(`CREATE TABLE IF NOT EXISTS alerts (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      type TEXT NOT NULL,
      severity TEXT NOT NULL,
      ip TEXT NOT NULL,
      user_id TEXT,
      details TEXT NOT NULL,
      timestamp INTEGER NOT NULL,
      hostname TEXT
    )`);
    await this.run(`CREATE TABLE IF NOT EXISTS alert_cooldowns (
      cooldown_key TEXT PRIMARY KEY,
      last_alert INTEGER NOT NULL
    )`);
    await this.run('CREATE INDEX IF NOT EXISTS idx_alerts_timestamp ON alerts(timestamp)');
    await this.run('CREATE INDEX IF NOT EXISTS idx_alerts_type ON alerts(type)');

    this.logger.info({ dbPath: this.dbPath }, 'Threat detector ready');
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

  all(sql, params = []) {
    return new Promise((resolve, reject) => {
      this.db.all(sql, params, (err, rows) => {
        if (err) reject(err);
        else resolve(rows);
      });
    });
  }

  normalizeIp(ip) {
    return String(ip || 'unknown').slice(0, 64);
  }

  parseJsonArray(value) {
    try {
      const parsed = JSON.parse(value || '[]');
      return Array.isArray(parsed) ? parsed : [];
    } catch {
      return [];
    }
  }

  async cooldownAllows(type, ip, cooldownMs) {
    const key = `${type}:${ip}`;
    const now = Date.now();
    const row = await this.get('SELECT last_alert FROM alert_cooldowns WHERE cooldown_key = ?', [key]);
    if (row && now - Number(row.last_alert) < cooldownMs) return false;
    await this.run(
      `INSERT INTO alert_cooldowns (cooldown_key, last_alert) VALUES (?, ?)
       ON CONFLICT(cooldown_key) DO UPDATE SET last_alert = excluded.last_alert`,
      [key, now],
    );
    return true;
  }

  async recordFailedLogin(ip, username) {
    try {
      await this.ready;
      const key = this.normalizeIp(ip);
      const now = Date.now();
      const bf = this.thresholds.bruteForce;
      const cs = this.thresholds.credentialStuffing;

      const existing = await this.get('SELECT * FROM failed_logins WHERE ip = ?', [key]);
      let count = 1;
      let firstAttempt = now;
      let usernames = [];
      let lastAlert = 0;

      if (existing) {
        const withinWindow = now - Number(existing.first_attempt) < cs.windowMs;
        count = withinWindow ? Number(existing.count) + 1 : 1;
        firstAttempt = withinWindow ? Number(existing.first_attempt) : now;
        usernames = withinWindow ? this.parseJsonArray(existing.usernames) : [];
        lastAlert = Number(existing.last_alert || 0);
      }
      const cleanUsername = String(username || '').slice(0, 120);
      if (cleanUsername && !usernames.includes(cleanUsername)) usernames.push(cleanUsername);
      if (usernames.length > 20) usernames = usernames.slice(-20);

      await this.run(
        `INSERT INTO failed_logins (ip, count, first_attempt, usernames, last_alert, updated_at)
         VALUES (?, ?, ?, ?, ?, ?)
         ON CONFLICT(ip) DO UPDATE SET
           count = excluded.count,
           first_attempt = excluded.first_attempt,
           usernames = excluded.usernames,
           last_alert = excluded.last_alert,
           updated_at = excluded.updated_at`,
        [key, count, firstAttempt, JSON.stringify(usernames), lastAlert, now],
      );

      if (count >= bf.count
        && usernames.length >= bf.uniqueUsers
        && now - firstAttempt < bf.windowMs
        && await this.cooldownAllows('brute_force', key, bf.alertCooldownMs)) {
        await this.markAlerted(key, now);
        await this.alert('brute_force', { ip: key, count, usernames });
      }

      if (count >= cs.count
        && now - firstAttempt < cs.windowMs
        && await this.cooldownAllows('credential_stuffing', key, cs.alertCooldownMs)) {
        await this.markAlerted(key, now);
        await this.alert('credential_stuffing', { ip: key, count });
        await this.blockIp(key, 'credential_stuffing');
      }
    } catch (err) {
      this.logger.warn({ err: err.message, ip }, 'Failed to record failed login');
    }
  }

  async markAlerted(ip, timestamp) {
    await this.run(
      'UPDATE failed_logins SET last_alert = ?, updated_at = ? WHERE ip = ?',
      [timestamp, timestamp, ip],
    );
  }

  async recordQuery(ip, userId, question) {
    try {
      await this.ready;
      const key = this.normalizeIp(ip);
      if (await this.isBlocked(key)) {
        this.logger.warn({ ip: key }, 'Blocked IP attempted an agent query');
        return;
      }

      const text = String(question || '').slice(0, 1000);
      const lower = text.toLowerCase();
      const patterns = [
        { pattern: /password|api.?key|secret|token|credential/i, topic: 'credentials' },
        { pattern: /database|schema|table|select\s+\*|union\s+select|drop\s+table/i, topic: 'database' },
        { pattern: /system.?prompt|ignore.?instruction|bypass|jailbreak/i, topic: 'prompt_injection' },
        { pattern: /ssh|shell|exec|eval|base64|decode/i, topic: 'code_execution' },
        { pattern: /internal|infrastructure|server|container|kubernetes/i, topic: 'infrastructure' },
      ];
      const matched = patterns.filter((entry) => entry.pattern.test(lower));
      if (matched.length > 0) {
        const now = Date.now();
        const sq = this.thresholds.suspiciousQuery;
        const existing = await this.get('SELECT * FROM suspicious_queries WHERE ip = ?', [key]);
        const withinWindow = existing && now - Number(existing.window_start) < sq.windowMs;
        const count = withinWindow ? Number(existing.count) + 1 : 1;
        const windowStart = withinWindow ? Number(existing.window_start) : now;
        const topics = withinWindow ? this.parseJsonArray(existing.topics) : [];
        for (const entry of matched) {
          if (!topics.includes(entry.topic)) topics.push(entry.topic);
        }
        if (topics.length > 20) topics.splice(0, topics.length - 20);

        await this.run(
          `INSERT INTO suspicious_queries (ip, count, window_start, topics, last_alert, updated_at)
           VALUES (?, ?, ?, ?, 0, ?)
           ON CONFLICT(ip) DO UPDATE SET
             count = excluded.count,
             window_start = excluded.window_start,
             topics = excluded.topics,
             updated_at = excluded.updated_at`,
          [key, count, windowStart, JSON.stringify(topics), now],
        );

        if (count >= sq.count
          && await this.cooldownAllows('suspicious_query', key, sq.alertCooldownMs)) {
          await this.alert('suspicious_query', {
            ip: key,
            userId: userId || null,
            question: text.slice(0, 200),
            topics,
          });
        }
      }

      const schoolKeywords = [
        'fee', 'admission', 'timetable', 'transport', 'uniform', 'curriculum',
        'boarding', 'catering', 'health', 'staff', 'student', 'parent',
        'exam', 'assessment', 'lesson', 'scheme', 'grade', 'class', 'stream',
        'attendance', 'payroll', 'reconciliation', 'inventory', 'procurement',
        'kingsway', 'school', 'learner', 'guardian', 'term', 'holiday',
        'pay', 'salary', 'leave', 'schedule', 'calendar', 'event', 'activity',
        'meal', 'welfare', 'counseling', 'stock', 'maintenance', 'facility',
        'route', 'vehicle', 'driver', 'fuel', 'dormitory', 'enrollment',
        'cbc', 'competency', 'rubric', 'report', 'analytics', 'dashboard',
        'kpi', 'briefing', 'insight',
      ];
      const schoolRelated = schoolKeywords.some((word) => lower.includes(word));
      if (!schoolRelated && text.length > this.thresholds.offTopic.minLength) {
        this.logger.debug({ ip: key, userId: userId || null }, 'Off-topic agent query');
      }
    } catch (err) {
      this.logger.warn({ err: err.message, ip }, 'Failed to record agent query');
    }
  }

  async alert(type, details) {
    const alert = {
      type,
      severity: type === 'brute_force' || type === 'credential_stuffing' ? 'critical' : 'warning',
      timestamp: new Date().toISOString(),
      details,
      hostname: os.hostname(),
    };

    await this.storeAlert(alert);
    this.bus.publish('admin-alerts', alert);
    this.logger.warn(alert, `Security alert: ${type}`);
    await this.sendEmailAlert(alert);
  }

  async sendEmailAlert(alert) {
    try {
      const phpBaseUrl = this.config.internalHttp?.php?.baseUrl || '';
      const phpApiPrefix = this.config.internalHttp?.php?.apiPrefix || '/api';
      const workerSecret = this.config.security?.workerSecret;

      if (!phpBaseUrl || !workerSecret) {
        this.logger.warn('PHP base URL or worker secret not configured; security alert email skipped');
        return;
      }

      const response = await fetch(`${phpBaseUrl}${phpApiPrefix}/system/send-security-alert`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Kingsway-Worker-Secret': workerSecret,
        },
        body: JSON.stringify(alert),
      });

      if (!response.ok) {
        this.logger.error({ status: response.status }, 'Failed to send security alert email');
      }
    } catch (err) {
      this.logger.error({ err: err.message }, 'Failed to send security alert email');
    }
  }

  async storeAlert(alert) {
    await this.run(
      `INSERT INTO alerts (type, severity, ip, user_id, details, timestamp, hostname)
       VALUES (?, ?, ?, ?, ?, ?, ?)`,
      [
        alert.type,
        alert.severity,
        alert.details?.ip || '',
        alert.details?.userId || null,
        JSON.stringify(alert.details || {}),
        Date.now(),
        alert.hostname,
      ],
    );
  }

  async blockIp(ip, reason) {
    await this.run(
      `INSERT INTO blocked_ips (ip, reason, blocked_at, expires_at)
       VALUES (?, ?, ?, ?)
       ON CONFLICT(ip) DO UPDATE SET
         reason = excluded.reason,
         blocked_at = excluded.blocked_at,
         expires_at = excluded.expires_at`,
      [ip, reason, Date.now(), Date.now() + 24 * 60 * 60 * 1000],
    );
    this.logger.warn({ ip, reason }, 'IP auto-blocked');
  }

  async isBlocked(ip) {
    try {
      await this.ready;
      const row = await this.get(
        'SELECT 1 AS blocked FROM blocked_ips WHERE ip = ? AND (expires_at IS NULL OR expires_at > ?)',
        [this.normalizeIp(ip), Date.now()],
      );
      return Boolean(row);
    } catch {
      return false;
    }
  }

  async getRecentAlerts(limit = 50) {
    await this.ready;
    const rows = await this.all('SELECT * FROM alerts ORDER BY timestamp DESC LIMIT ?', [limit]);
    return rows.map((row) => ({ ...row, details: JSON.parse(row.details || '{}') }));
  }

  async getBlockedIps() {
    await this.ready;
    return this.all(
      'SELECT * FROM blocked_ips WHERE expires_at IS NULL OR expires_at > ? ORDER BY blocked_at DESC',
      [Date.now()],
    );
  }

  async cleanup() {
    await this.ready;
    const now = Date.now();
    const dayAgo = now - 24 * 60 * 60 * 1000;
    const monthAgo = now - 30 * 24 * 60 * 60 * 1000;

    await this.run('DELETE FROM failed_logins WHERE updated_at < ?', [dayAgo]);
    await this.run('DELETE FROM suspicious_queries WHERE updated_at < ?', [dayAgo]);
    await this.run('DELETE FROM blocked_ips WHERE expires_at IS NOT NULL AND expires_at < ?', [now]);
    await this.run('DELETE FROM alert_cooldowns WHERE last_alert < ?', [dayAgo]);
    await this.run('DELETE FROM alerts WHERE timestamp < ?', [monthAgo]);
  }

  shutdown() {
    if (this.cleanupTimer) clearInterval(this.cleanupTimer);
    if (this.db) this.db.close();
  }
}

let instance = null;

export function getThreatDetector() {
  if (!instance) instance = new ThreatDetector();
  return instance;
}
