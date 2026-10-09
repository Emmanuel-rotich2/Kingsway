/**
 * Single normalization point for realtime changes.
 *
 * Why this file exists
 * --------------------
 * There are two realtime transports (the Node SSE stream and the static-buffer
 * service worker). Both were independently doing the same four things:
 * invalidate the cache, schedule a loader refresh, fire a notification, and
 * route by event type. That produced the two failure modes that duplication of
 * this kind always produces:
 *
 *   1. A change arriving while both transports were active was processed twice,
 *      so every mutation refreshed the page twice.
 *   2. A global `kingsway:data-mutated` listener re-scheduled work that the
 *      dispatcher had already scheduled, so even a single transport double-fired.
 *
 * Neither was visible as an error: the refresh scheduler debounces, so the
 * symptom was "the UI feels slow and does redundant work", not a crash.
 *
 * The contract here
 * -----------------
 * Transports call `RealtimeDispatch.dispatch(...)` and NOTHING else. This module
 * is the only place that decides whether a change refreshes the page. Type
 * routing lives here so the two transports cannot disagree about it.
 *
 * `APIRealtime` (js/api.js) owns the refresh scheduler and the patch registry
 * only. It never listens to events, so there is no cycle between them.
 */
(function (window) {
  'use strict';

  /** Types that are pure notifications and must never refresh the page. */
  const LIVENESS_TYPES = new Set(['HEARTBEAT']);
  const PRESENCE_TYPES = new Set(['PRESENCE']);
  const CONFLICT_TYPES = new Set(['CONFLICT_HINT']);
  const JOB_TYPES = new Set(['JOB_PROGRESS', 'JOB_COMPLETE', 'JOB_FAILED']);
  const ROW_PATCH_TYPES = new Set(['ROW_UPDATED', 'ROW_DELETED']);

  function emit(name, detail) {
    window.dispatchEvent(new CustomEvent(name, { detail }));
  }

  function targetsOf(payload) {
    return Array.isArray(payload?.targets) ? payload.targets.filter((t) => typeof t === 'string' && t) : [];
  }

  /**
   * Apply the one correct reaction to a change.
   *
   * @param {object} change
   * @param {string} change.type      e.g. ROW_UPDATED, CACHE_INVALIDATE
   * @param {string} [change.scope]   audience scope
   * @param {object} [change.payload] descriptor body
   * @param {string} [change.source]   which transport delivered it
   * @returns {{refreshed: boolean, patched: boolean, targets: string[]}}
   */
  function dispatch(change = {}) {
    const payload = change.payload || {};
    const type = String(change.type || '').toUpperCase();
    const scope = change.scope || 'all';
    const source = change.source || 'unknown';
    const at = Number.isFinite(change.at) ? change.at : Date.now();
    const targets = targetsOf(payload);
    const detail = { scope, payload, type, at, source };

    // Everyone may observe the raw event; it carries no obligation to act.
    emit('kingsway:realtime', detail);

    if (LIVENESS_TYPES.has(type)) {
      emit('kingsway:realtime-pulse', { at });
      return { refreshed: false, patched: false, targets };
    }

    if (CONFLICT_TYPES.has(type)) {
      // Never auto-refresh: the user is mid-edit on this row and a refresh
      // would discard what they typed. Surface it and let them reconcile.
      emit('kingsway:row-conflict', { ...detail });
      return { refreshed: false, patched: false, targets };
    }

    if (PRESENCE_TYPES.has(type)) {
      emit('kingsway:presence', { ...detail });
      return { refreshed: false, patched: false, targets };
    }

    if (JOB_TYPES.has(type)) {
      emit('kingsway:job', { ...detail });
      // Job events do not by themselves change cached school data, so they do
      // not refresh anything.
      return { refreshed: false, patched: false, targets };
    }

    if (ROW_PATCH_TYPES.has(type) && window.APIRealtime?.applyPatch) {
      if (window.APIRealtime.applyPatch({ ...payload, type, scope, at, source })) {
        emit('kingsway:row-patched', { ...detail });
        return { refreshed: false, patched: true, targets };
      }
      // No handler claimed the row. Fall through to a full refresh rather than
      // leaving stale data on screen.
    }

    if (targets.length && window.DataStore?.invalidateMany) {
      window.DataStore.invalidateMany(targets).catch((error) => {
        console.warn('[RealtimeDispatch] Cache invalidation failed:', error);
      });
    }
    // One refresh, here. Nothing downstream re-schedules it.
    window.APIRealtime?.schedule?.(targets);

    emit('kingsway:data-mutated', {
      source, scope, targets, events: [{ type, scope, payload }], patched: false,
    });

    return { refreshed: true, patched: false, targets };
  }

  window.RealtimeDispatch = Object.freeze({ dispatch });
})(window);