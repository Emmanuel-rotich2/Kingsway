'use strict';

/**
 * SSE connection registry.
 *
 * Stateless operating rule: the ONLY durable state is the set of open response
 * sockets. A Passenger recycle wipes this and browsers auto-reconnect, then
 * resync through PHP's catch-up reader. No database, no disk, no event log.
 *
 * Production concerns this module owns, all of which a naive `res.write()` loop
 * gets wrong:
 *
 *  - Backpressure — `res.write()` returning false means the kernel send buffer
 *    is full. Ignoring it grows the heap without bound until OOM. We queue with
 *    a hard depth limit, drop the OLDEST frames (a stale KPI value is worth less
 *    than a fresh one), and release the backlog gauge as frames drain.
 *  - Slow-client eviction — a client that never drains is evicted after a
 *    timeout so it cannot pin memory. `EventSource` reconnects on its own.
 *  - Pool exhaustion — refuse with 503 + Retry-After instead of queuing
 *    connections until the process dies.
 *  - Presence — per-connection edit declarations with TTL expiry. Used to
 *    withhold a row patch from the user currently editing it and send them a
 *    CONFLICT_HINT instead. Ephemeral by nature; a lost declaration degrades to
 *    a normal refresh, which is safe.
 */

const { randomUUID } = require('node:crypto');

const DEFAULTS = {
  maxConnections: 5000,
  queueLimit: 50,
  slowClientTimeoutMs: 5000,
  presenceTtlMs: 45000,
};

function createRegistry({
  maxConnections = DEFAULTS.maxConnections,
  queueLimit = DEFAULTS.queueLimit,
  slowClientTimeoutMs = DEFAULTS.slowClientTimeoutMs,
  presenceTtlMs = DEFAULTS.presenceTtlMs,
  metrics,
  logger,
  state,
  onEvict,
} = {}) {
  const clients = new Set();
  let sequence = 0;

  function refreshGauge() {
    if (metrics) metrics.setConnections(clients.size);
    if (state) state.stats.connections_active = clients.size;
  }

  /**
   * Bookkeeping only: mark closed, drop queued frames, deregister, count.
   * Kept separate from socket termination because the two ends differ —
   * a hard destroy and a graceful end must not share one code path (see
   * `destroy` and `closeAll`). Returns false if the client was already gone, so
   * the socket 'close' listener that fires after a graceful end cannot
   * double-count the disconnect.
   */
  function release(client, reason) {
    if (client.closed) return false;
    client.closed = true;
    clearTimeout(client.slowTimer);
    if (client.queuedBytes > 0 && metrics) metrics.clearBacklog(client.queuedBytes);
    client.queue.length = 0;
    client.queuedBytes = 0;
    client.activity.clear();
    clients.delete(client);
    refreshGauge();
    if (metrics) metrics.connectionClosed();
    if (logger) {
      logger.journal('sse.closed', {
        user_id: client.userId,
        reason,
        queued_at_close: reason === 'slow_client' ? 1 : 0,
      });
    }
    if (onEvict) onEvict(client, reason);
    return true;
  }

  /** Hard drop. Used for errors and slow-client eviction, where dropping queued
   *  data is the point: the peer is already not reading. */
  function destroy(client, reason) {
    if (!release(client, reason)) return;
    try {
      if (!client.res.writableEnded) client.res.destroy();
    } catch (_) {
      /* socket already gone */
    }
  }

  function watchSlowClient(client) {
    clearTimeout(client.slowTimer);
    client.slowTimer = setTimeout(() => {
      if (client.closed || client.queuedBytes === 0) return;
      if (metrics) metrics.slowClientEvicted();
      destroy(client, 'slow_client');
    }, slowClientTimeoutMs);
    if (typeof client.slowTimer.unref === 'function') client.slowTimer.unref();
  }

  function drain(client) {
    if (client.draining || client.closed) return;
    client.draining = true;
    try {
      while (client.queue.length > 0 && !client.closed) {
        const frame = client.queue[0];
        let flushed;
        try {
          flushed = client.res.write(frame);
        } catch (_) {
          destroy(client, 'write_error');
          return;
        }
        if (!flushed) {
          // Socket buffer full. Stop the loop, wait for drain, and arm the
          // eviction timer so a permanently stuck client cannot hold memory.
          client.draining = false;
          watchSlowClient(client);
          client.res.once('drain', () => {
            clearTimeout(client.slowTimer);
            drain(client);
          });
          return;
        }
        client.queue.shift();
        client.queuedBytes -= frame.length;
        if (metrics) metrics.clearBacklog(frame.length);
      }
      client.draining = false;
      clearTimeout(client.slowTimer);
    } catch (error) {
      destroy(client, `drain_error:${error?.message || 'unknown'}`);
    }
  }

  /**
   * @returns {{ok: true, client: object}|{ok: false, reason: string}}
   */
  function add(res, { userId, channels }) {
    if (clients.size >= maxConnections) {
      return { ok: false, reason: 'pool_full' };
    }
    sequence += 1;
    const client = {
      id: `${sequence}-${randomUUID().slice(0, 8)}`,
      res,
      userId,
      channels,
      queue: [],
      queuedBytes: 0,
      draining: false,
      closed: false,
      slowTimer: null,
      activity: new Map(),
      openedAt: Date.now(),
    };
    clients.add(client);
    refreshGauge();
    if (metrics) metrics.connectionOpened();
    if (state) state.openedConnection();
    res.on('close', () => destroy(client, 'client_close'));
    res.on('error', () => destroy(client, 'socket_error'));
    return { ok: true, client };
  }

  /** Queue a frame. Returns false when the connection is already gone. */
  function send(client, frame, type) {
    if (client.closed) return false;
    if (client.queue.length >= queueLimit) {
      const dropped = client.queue.shift();
      client.queuedBytes -= dropped.length;
      if (metrics) metrics.clearBacklog(dropped.length);
      if (metrics && type) metrics.coalesced(type);
    }
    client.queue.push(frame);
    client.queuedBytes += frame.length;
    if (metrics) metrics.addBacklog(frame.length);
    drain(client);
    return !client.closed;
  }

  function setActivity(client, activityKey, state, scope) {
    if (client.closed) return;
    client.activity.set(activityKey, { state, scope: scope || null, ts: Date.now() });
    if (metrics) {
      let total = 0;
      for (const other of clients) total += other.activity.size;
      metrics.setPresence(total);
    }
  }

  function clearActivity(client, activityKey) {
    if (client.closed) return;
    if (activityKey) client.activity.delete(activityKey);
    else client.activity.clear();
    if (metrics) {
      let total = 0;
      for (const other of clients) total += other.activity.size;
      metrics.setPresence(total);
    }
  }

  /** Drop presence declarations that stopped being refreshed. */
  function sweepPresence() {
    const cutoff = Date.now() - presenceTtlMs;
    let changed = false;
    for (const client of clients) {
      for (const [key, entry] of client.activity) {
        if (entry.ts < cutoff) {
          client.activity.delete(key);
          changed = true;
          if (logger) logger.journal('presence.expired', { user_id: client.userId, activity_key: key });
        }
      }
    }
    if (changed && metrics) {
      let total = 0;
      for (const other of clients) total += other.activity.size;
      metrics.setPresence(total);
    }
    return changed;
  }

  /**
   * Graceful drain: FLUSH a final frame, then END the socket.
   *
   * This must not reuse the hard-destroy path. `res.destroy()` tears the socket
   * down immediately and discards whatever is still in the write buffer, so a
   * drain notice sent that way is silently lost and the browser sees a bare TCP
   * close — exactly the abrupt disconnect the drain exists to prevent.
   *
   * @returns {number} connections drained
   */
  function closeAll(frameForClient) {
    const open = [...clients];
    for (const client of open) {
      if (!release(client, 'server_restart')) continue;
      try {
        client.res.end(frameForClient);
      } catch (_) {
        try { client.res.destroy(); } catch (__) { /* socket already gone */ }
      }
    }
    return open.length;
  }

  return {
    clients,
    get size() { return clients.size; },
    maxConnections,
    add,
    destroy,
    send,
    setActivity,
    clearActivity,
    sweepPresence,
    closeAll,
    refreshGauge,
  };
}

module.exports = { createRegistry, DEFAULTS };