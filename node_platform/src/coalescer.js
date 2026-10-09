'use strict';

/**
 * Refresh-signal coalescer (leading edge + trailing merge).
 *
 * A single staff action frequently mutates several rows — a teacher saving 40
 * assessment marks, a bulk import touching 500 records, a projection refresh
 * touching many projections. Broadcasting one browser refresh per row would run
 * 40–500 page loaders for what the user perceives as a single change.
 *
 * The window is TRAILING, not leading: the first event of a burst is delivered
 * immediately, and only events arriving inside the following window are merged
 * into one follow-up carrying the union of their targets. That ordering matters
 * — a naive leading-edge-delayed coalescer would add its whole window to the
 * latency of every isolated write, which is the common case, and would make
 * realtime feel slower than it needs to. Here an isolated write has ZERO added
 * latency and a burst still collapses to at most two refreshes.
 *
 * Only refresh signals are coalesced. Row patches, job telemetry and presence
 * are observable state transitions: merging them away would lose information,
 * so they pass straight through.
 */

const COALESCABLE = new Set(['CACHE_INVALIDATE', 'DATA_CHANGED', 'SYNC']);

const DEFAULTS = {
  windowMs: 250,
  maxWaitMs: 1000,
};

function createCoalescer({
  windowMs = DEFAULTS.windowMs,
  maxWaitMs = DEFAULTS.maxWaitMs,
  deliver,
  metrics,
  logger,
} = {}) {
  const pending = new Map();

  function keyFor(event) {
    const targets = Array.isArray(event.payload?.targets) ? event.payload.targets : [];
    return `${event.type}:${event.scope}:${[...targets].sort().join(',')}`;
  }

  function flush(key) {
    const entry = pending.get(key);
    if (!entry) return 0;
    pending.delete(key);
    clearTimeout(entry.timer);

    const merged = {
      ...entry.event,
      payload: { ...entry.event.payload, targets: [...entry.targets] },
      emitted_at: new Date().toISOString(),
    };
    if (entry.count > 1) merged.coalesced_count = entry.count;

    if (entry.count > 1) {
      if (metrics) metrics.coalesced(entry.event.type);
      if (logger) {
        logger.journal('publish.coalesced', {
          type: entry.event.type,
          scope: entry.event.scope,
          merged: entry.count,
          targets: entry.targets.length,
        });
      }
    }
    return deliver(merged);
  }

  /** Open (or extend) the trailing window for this key. */
  function openWindow(key, event) {
    const entry = {
      event,
      targets: [...(event.payload.targets || [])],
      count: 1,
      firstSeen: Date.now(),
      timer: null,
    };
    entry.timer = setTimeout(() => flush(key), windowMs);
    if (typeof entry.timer.unref === 'function') entry.timer.unref();
    pending.set(key, entry);
  }

  /**
   * @param {object} event validated protocol event
   * @returns {{delivered: number, queued: boolean}} immediate writes report their
   *   own fan-out count; a merged follow-up reports 0 delivered and queued.
   */
  function submit(event) {
    if (!COALESCABLE.has(event.type)) {
      return { delivered: deliver(event), queued: false };
    }

    const key = keyFor(event);

    if (pending.has(key)) {
      const existing = pending.get(key);
      for (const target of event.payload.targets || []) {
        if (!existing.targets.includes(target)) existing.targets.push(target);
      }
      existing.count += 1;
      // Never let a continuous write stream starve the browser of an update.
      if (Date.now() - existing.firstSeen >= maxWaitMs) {
        return { delivered: flush(key), queued: false };
      }
      return { delivered: 0, queued: true };
    }

    // Leading edge: deliver now, and arm the window so a burst that follows is
    // merged into a single follow-up.
    const delivered = deliver(event);
    openWindow(key, event);
    return { delivered, queued: false };
  }

  function size() {
    return pending.size;
  }

  function flushAll() {
    for (const key of [...pending.keys()]) flush(key);
  }

  return { submit, size, flushAll, COALESCABLE };
}

module.exports = { createCoalescer, COALESCABLE, DEFAULTS };