'use strict';

/**
 * Cross-instance event bus.
 *
 * With one process, an in-process emitter is sufficient and correct. The moment
 * there are two or more Node instances (Passenger workers, or the same app
 * behind the nginx pool), an event published on instance A is invisible to the
 * clients held by instance B — a silent, partial-delivery bug that looks like
 * "realtime is flaky" and is very hard to diagnose.
 *
 * So: `bus.publish()` always emits locally, and additionally forwards to Redis
 * Pub/Sub when REDIS_URL is configured. Every instance subscribes and fans
 * received events out to its OWN clients, skipping anything it published
 * itself (tagged with this instance's id) to avoid double delivery.
 *
 * Redis is DORMANT unless REDIS_URL is set — the same configuration-gated
 * pattern used elsewhere in this system (AI_PYTHON_URL, KICD_POLICY_SOURCE).
 * Pub/Sub is at-most-once by design: it is right for ephemeral live updates,
 * and the authoritative resync after a gap is always PHP's catch-up reader.
 */

const { hostname } = require('node:os');
const crypto = require('node:crypto');

const CHANNEL = 'kingsway:realtime:events';
const STATE = { DOWN: 0, IN_PROCESS: 1, REDIS: 2 };

function createBus({ redisUrl = '', logger, metrics, instanceId } = {}) {
  const id = instanceId || `${hostname()}-${crypto.randomBytes(4).toString('hex')}`;
  const handlers = new Set();
  const received = new Set();

  let mode = STATE.IN_PROCESS;
  let publisher = null;
  let subscriber = null;

  function setMode(next) {
    mode = next;
    if (metrics) metrics.busUp.set(next);
  }

  setMode(STATE.IN_PROCESS);

  /**
   * Fan an event out to this process's handlers.
   * @returns {{delivered: number, queued: number}} aggregated handler results
   */
  function emitLocal(event, receivedFromBus) {
    if (receivedFromBus && event.origin === id) return { delivered: 0, queued: 0 };
    let delivered = 0;
    let queued = 0;
    for (const handler of handlers) {
      try {
        const result = handler(event);
        if (result && typeof result.delivered === 'number') delivered += result.delivered;
        if (result && result.queued) queued += 1;
      } catch (error) {
        if (logger) logger.error({ err: error?.message }, 'bus handler failed');
      }
    }
    return { delivered, queued };
  }

  /**
   * The SINGLE publish path: emit locally, then forward to siblings when Redis
   * is configured. Handlers (the dispatcher) do the coalescing and fan-out, so
   * an event cannot be delivered twice by two different code paths.
   *
   * @returns {Promise<{delivered: number, queued: number, forwarded: boolean, mode: string}>}
   */
  async function publish(event) {
    const local = emitLocal({ ...event, origin: id }, false);
    let forwarded = false;
    if (publisher) {
      try {
        await publisher.publish(CHANNEL, JSON.stringify({ ...event, origin: id }));
        forwarded = true;
      } catch (error) {
        if (logger) logger.error({ err: error?.message }, 'redis publish failed');
        setMode(STATE.DOWN);
      }
    }
    return { ...local, forwarded, mode: mode === STATE.REDIS ? 'redis' : 'in_process' };
  }

  function subscribe(handler) {
    handlers.add(handler);
    return () => handlers.delete(handler);
  }

  /**
   * Connect to Redis if configured. Never throws: a bus failure degrades to
   * single-instance delivery rather than taking realtime down.
   */
  async function start() {
    if (!redisUrl) {
      if (logger) logger.journal('bus.mode', { mode: 'in_process' });
      return { connected: false, reason: 'no_redis_url' };
    }
    try {
      const Redis = require('ioredis');
      const options = {
        lazyConnect: true,
        maxRetriesPerRequest: 2,
        // Exponential backoff; give up rather than spin if Redis is down.
        retryStrategy: (times) => Math.min(times * 500, 10000),
      };
      publisher = new Redis(redisUrl, options);
      subscriber = new Redis(redisUrl, options);

      await publisher.connect();
      await subscriber.connect();

      publisher.on('error', (error) => {
        if (logger) logger.error({ err: error?.message }, 'redis publisher error');
        setMode(STATE.DOWN);
      });
      subscriber.on('error', (error) => {
        if (logger) logger.error({ err: error?.message }, 'redis subscriber error');
        setMode(STATE.DOWN);
      });

      await subscriber.psubscribe(`${CHANNEL}*`);
      subscriber.on('pmessage', (_pattern, _channel, message) => {
        // Bounded replay guard: a misconfigured producer must not grow this set.
        if (received.size > 5000) received.clear();
        if (received.has(message)) return;
        received.add(message);
        try {
          emitLocal(JSON.parse(message), true);
        } catch (error) {
          if (logger) logger.error({ err: error?.message }, 'redis message not valid JSON');
        }
      });

      setMode(STATE.REDIS);
      if (logger) logger.journal('bus.mode', { mode: 'redis', channel: CHANNEL, instance: id });
      return { connected: true };
    } catch (error) {
      publisher = null;
      subscriber = null;
      setMode(STATE.DOWN);
      if (logger) logger.error({ err: error?.message }, 'redis unavailable; single-instance delivery');
      return { connected: false, reason: error?.message || 'connect_failed' };
    }
  }

  async function stop() {
    for (const client of [subscriber, publisher]) {
      if (!client) continue;
      try { await client.quit(); } catch (_) { /* already closing */ }
    }
    subscriber = null;
    publisher = null;
    setMode(STATE.IN_PROCESS);
  }

  return {
    id,
    CHANNEL,
    STATE,
    publish,
    subscribe,
    start,
    stop,
    get mode() { return mode; },
    get modeName() {
      return mode === STATE.REDIS ? 'redis' : (mode === STATE.DOWN ? 'unavailable' : 'in_process');
    },
  };
}

module.exports = { createBus, STATE, CHANNEL };