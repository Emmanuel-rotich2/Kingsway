import jwt from 'jsonwebtoken';
import { getConfig } from '../config/index.js';
import { getLogger } from '../logger/index.js';
import { getMetrics } from '../metrics/index.js';
import { getBus } from './bus.js';

let sseCache = null;

const CHANNEL_TYPES = {
  USER: 'user',
  JOB: 'job',
  BROADCAST: 'broadcast',
  PRESENCE: 'presence',
};
const MAX_PRESENCE_ENTRIES = 10000;
const MAX_PRESENCE_KEYS_PER_USER = 50;

export function createSSE() {
  if (sseCache) return sseCache;

  const config = getConfig();
  const logger = getLogger().child({ component: 'sse' });
  const metrics = getMetrics();
  const bus = getBus();

  const connections = new Map();
  const userConnections = new Map();
  const presence = new Map();
  const presenceKeysByUser = new Map();

  function deletePresence(key) {
    const record = presence.get(key);
    if (!record) return;
    presence.delete(key);
    const keys = presenceKeysByUser.get(record.userId);
    keys?.delete(key);
    if (keys?.size === 0) presenceKeysByUser.delete(record.userId);
  }

  function verifyCapability(token) {
    try {
      const decoded = jwt.verify(token, config.security.jwtSecret, {
        issuer: config.security.jwtIssuer,
        audience: config.security.jwtAudience,
        clockTolerance: 30,
      });
      const userId = String(decoded?.sub ?? '');
      const channels = decoded?.channels;
      if (decoded?.realtime !== true
        || !/^[1-9]\d{0,18}$/.test(userId)
        || !Array.isArray(channels)
        || channels.length > 100
        || channels.some(channel => typeof channel !== 'string' || channel.length > 128)) {
        return null;
      }
      return decoded;
    } catch {
      return null;
    }
  }

  function getChannelType(channel) {
    if (channel.startsWith('user:')) return CHANNEL_TYPES.USER;
    if (channel.startsWith('job:')) return CHANNEL_TYPES.JOB;
    if (channel === 'broadcast') return CHANNEL_TYPES.BROADCAST;
    if (channel.startsWith('presence:')) return CHANNEL_TYPES.PRESENCE;
    return 'unknown';
  }

  async function handleConnection(req, reply) {
    const token = req.query.token;
    if (!token) return reply.code(401).send({ error: 'Missing token' });

    const decoded = verifyCapability(token);
    if (!decoded) return reply.code(403).send({ error: 'Invalid token' });

    const userId = decoded.sub;
    // PHP mints scopes (for example `all`, `users:74`, `family:128`), while
    // domain events use their corresponding bus channels. Subscribe only to
    // channels derived from this signed capability; never add broadcast or a
    // user channel implicitly.
    const channels = [];
    for (const scope of decoded.channels) {
      if (scope === 'all') {
        channels.push('broadcast');
      } else if (scope.startsWith('users:')) {
        if (scope !== `users:${userId}`) {
          return reply.code(403).send({ error: 'Invalid realtime scope' });
        }
        channels.push(`user:${userId}`);
      } else {
        channels.push(scope);
      }
    }
    const authorizedChannels = Array.from(new Set(channels));

    const userConnCount = (userConnections.get(userId) || new Set()).size;
    if (userConnCount >= config.sse.userMaxConnections) {
      return reply.code(429).send({ error: 'Too many connections for user' });
    }
    if (connections.size >= config.sse.maxConnections) {
      return reply.code(503).send({ error: 'Realtime connection capacity reached' });
    }

    const headers = {
      'Content-Type': 'text/event-stream',
      'Cache-Control': 'no-cache, no-store, private',
      'Connection': 'keep-alive',
      'X-Accel-Buffering': 'no',
    };

    // This writeHead call is what actually hits the socket: the handler never
    // runs reply.send(), so any header added via reply.header() in an earlier
    // hook is silently dropped. CORS must therefore be set here, based on the
    // page origin, or Firefox refuses to read the stream cross-origin.
    const requestOrigin = req.headers.origin;
    if (requestOrigin && config.security.webOrigins.includes(requestOrigin)) {
      headers['Access-Control-Allow-Origin'] = requestOrigin;
      headers['Access-Control-Allow-Credentials'] = 'true';
      headers['Vary'] = 'Origin';
    }

    // This route owns the raw streaming response; prevent Fastify from trying
    // to auto-send after this handler resolves.
    reply.hijack();
    reply.raw.writeHead(200, headers);

    const connId = `${userId}:${Date.now()}:${Math.random().toString(36).slice(2, 9)}`;
    const queue = [];
    const unsubscribers = [];

    const connection = {
      id: connId,
      userId,
      channels: new Set(authorizedChannels),
      queue,
      raw: reply.raw,
      lastEventId: req.headers['last-event-id'],
      createdAt: Date.now(),
      unsubscribers,
      heartbeat: null,
      slowClientTimer: null,
      backpressured: false,
      write: (data) => {
        if (connection.closed) return false;
        if (queue.length >= config.sse.queueLimit) {
          closeConnection(connId, true);
          return false;
        }
        queue.push(data);
        processQueue(connId);
        return true;
      },
    };

    connections.set(connId, connection);
    if (!userConnections.has(userId)) userConnections.set(userId, new Set());
    userConnections.get(userId).add(connId);

    metrics.sseConnections.inc({ channel_type: 'total' });
    for (const ch of authorizedChannels) {
      metrics.sseConnections.inc({ channel_type: getChannelType(ch) });
    }

    for (const ch of authorizedChannels) {
      unsubscribers.push(bus.subscribe(ch, (msg) => writeEvent(connId, msg.type, msg.payload)));
    }

    if (connection.lastEventId) {
      for (const ch of authorizedChannels) {
        if (ch.startsWith('job:')) {
          bus.replay(ch, parseInt(connection.lastEventId) || 0, (msg) => writeEvent(connId, msg.type, msg.payload));
        }
      }
    }

    writeEvent(connId, 'connected', { connectionId: connId, channels: Array.from(connection.channels) });
    connection.heartbeat = startHeartbeat(connId);

    // IncomingMessage `close` can fire when the GET request body is complete,
    // which is immediately for SSE. Track the response socket instead so the
    // subscription lives until the client disconnects.
    reply.raw.on('close', () => closeConnection(connId));
    reply.raw.on('error', () => closeConnection(connId));
  }

  function writeEvent(connId, event, data) {
    const conn = connections.get(connId);
    if (!conn || conn.closed) return;
    const frame = `event: ${event}\ndata: ${JSON.stringify(data)}\n\n`;
    conn.write(frame);
  }

  function processQueue(connId) {
    const conn = connections.get(connId);
    if (!conn || conn.closed || conn.backpressured) return;
    while (conn.queue.length > 0 && !conn.backpressured) {
      const frame = conn.queue.shift();
      try {
        if (conn.raw?.write(frame) === false) {
          conn.backpressured = true;
          conn.slowClientTimer = setTimeout(
            () => closeConnection(connId, true),
            config.sse.slowClientTimeoutMs,
          );
          conn.raw.once('drain', () => {
            const current = connections.get(connId);
            if (!current || current.closed) return;
            clearTimeout(current.slowClientTimer);
            current.slowClientTimer = null;
            current.backpressured = false;
            processQueue(connId);
          });
        }
      } catch {
        closeConnection(connId, true);
        return;
      }
    }
  }

  function startHeartbeat(connId) {
    const interval = setInterval(() => {
      const conn = connections.get(connId);
      if (!conn || conn.closed) {
        clearInterval(interval);
        return;
      }
      writeEvent(connId, 'heartbeat', { timestamp: Date.now() });
    }, config.sse.heartbeatMs);
    return interval;
  }

  function closeConnection(connId, endStream = false) {
    const conn = connections.get(connId);
    if (!conn) return;
    conn.closed = true;
    if (conn.heartbeat) clearInterval(conn.heartbeat);
    if (conn.slowClientTimer) clearTimeout(conn.slowClientTimer);
    for (const unsubscribe of conn.unsubscribers) {
      try { unsubscribe(); } catch {}
    }
    conn.unsubscribers.length = 0;
    connections.delete(connId);
    const userConns = userConnections.get(conn.userId);
    if (userConns) {
      userConns.delete(connId);
      if (userConns.size === 0) userConnections.delete(conn.userId);
    }
    metrics.sseConnections.dec({ channel_type: 'total' });
    for (const ch of conn.channels) {
      metrics.sseConnections.dec({ channel_type: getChannelType(ch) });
    }
    if (endStream && !conn.raw.destroyed && !conn.raw.writableEnded) conn.raw.end();
  }

  async function publish(channel, event, data) {
    await bus.publish(channel, { type: event, payload: data });
    metrics.sseMessagesSent.inc({ channel_type: getChannelType(channel) });
  }

  async function publishToUser(userId, event, data) {
    await publish(`user:${userId}`, event, data);
  }

  async function publishToJob(jobId, event, data) {
    await publish(`job:${jobId}`, event, data);
  }

  async function publishBroadcast(event, data) {
    await publish('broadcast', event, data);
  }

  /**
   * Deliver a browser domain event (RealtimeDispatch shape) to the SSE
   * connections whose capability includes the scope. The frame is emitted as
   * `event: kingsway` with the { type, scope, payload } envelope as data, which
   * is exactly what js/core/realtime_sse.js hands to RealtimeDispatch.
   */
  async function emitKingsway(type, scope, payload = {}) {
    const { events } = await import('../publish/domain.js');
    return events.broadcast({ type, scope, payload });
  }

  function getStats() {
    return {
      totalConnections: connections.size,
      uniqueUsers: userConnections.size,
      channels: Array.from(connections.values()).flatMap(c => Array.from(c.channels)),
      presence: Array.from(presence.values()).filter(r => Date.now() - r.ts < config.sse.presenceTtlMs).length,
    };
  }

  async function reportPresence(userId, body = {}) {
    const now = Date.now();
    const userKeys = presenceKeysByUser.get(userId) || new Set();
    for (const key of userKeys) {
      const record = presence.get(key);
      if (!record || now - record.ts >= config.sse.presenceTtlMs) deletePresence(key);
    }
    const activityKey = String(body.activity_key || 'default').slice(0, 128);
    const state = String(body.state || 'editing').slice(0, 32);
    const scope = String(body.scope || 'all').slice(0, 128);
    const presenceKey = `${userId}:${activityKey}`;
    if (!presence.has(presenceKey)) {
      if (userKeys.size >= MAX_PRESENCE_KEYS_PER_USER) {
        const oldestKey = Array.from(userKeys).reduce((current, key) => (
          presence.get(key)?.ts < presence.get(current)?.ts ? key : current
        ));
        deletePresence(oldestKey);
      }
      if (presence.size >= MAX_PRESENCE_ENTRIES) {
        const oldestKey = Array.from(presence.entries()).reduce((current, entry) => (
          entry[1].ts < current[1].ts ? entry[0] : current
        ), presence.keys().next().value);
        if (oldestKey !== undefined) deletePresence(oldestKey);
      }
    }
    presence.set(presenceKey, { userId, activityKey, state, scope, ts: now });
    if (!presenceKeysByUser.has(userId)) presenceKeysByUser.set(userId, userKeys);
    userKeys.add(presenceKey);
    // Presence is a liveness signal, not a data change. Delivered as a
    // `kingsway:presence` dispatch to the owning user's connections only.
    await emitKingsway('PRESENCE', `users:${userId}`, { userId, activityKey, state, scope });
    return { ok: true };
  }

  sseCache = {
    handleConnection,
    publish,
    publishToUser,
    publishToJob,
    publishBroadcast,
    emitKingsway,
    reportPresence,
    getStats,
    verifyCapability,
  };

  return sseCache;
}

export function getSSE() {
  return sseCache || createSSE();
}

export function resetSSE() {
  sseCache = null;
}
