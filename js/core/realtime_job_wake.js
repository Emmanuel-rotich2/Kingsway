/**
 * RealtimeJobWake - let waiting panels react the instant their job finishes.
 *
 * AI draft/review panels and other requesters poll their endpoints on fixed
 * sleep loops (the fallback that always works). When the minute-worker
 * finishes a job the requester is waiting on, WorkerJobTelemetry publishes a
 * JOB_COMPLETE/JOB_FAILED descriptor to the operator's OWN users:<id> channel
 * — so the browser that queued the work hears it immediately, and nobody
 * else's does. Panels register a callback here to short-circuit their sleep.
 *
 * Wake coalescing: a burst of completions (e.g. queued reviews draining in one
 * worker batch) must trigger ONE refresh per subscriber, not one per event.
 */
(function (window) {
  'use strict';

  const COALESCE_MS = 1500;
  const subscribers = new Map(); // domain -> Set<callback>
  const pending = new Map(); // domain -> timer

  function onJobEvent(event) {
    const payload = event.detail?.payload || {};
    const domain = String(payload.domain || '');
    if (!domain || !subscribers.has(domain)) return;
    const type = String(event.detail?.type || '').toUpperCase();
    if (type !== 'JOB_COMPLETE' && type !== 'JOB_FAILED') return;

    if (pending.has(domain)) return; // Already scheduled — coalesce the burst.
    pending.set(domain, window.setTimeout(() => {
      pending.delete(domain);
      for (const callback of subscribers.get(domain) || []) {
        try {
          callback(payload);
        } catch (error) {
          console.warn('[RealtimeJobWake] subscriber failed:', error);
        }
      }
    }, COALESCE_MS));
  }

  function init() {
    window.addEventListener('kingsway:job', onJobEvent);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
  } else {
    init();
  }

  window.RealtimeJobWake = Object.freeze({
    /**
     * @param {string} domain e.g. 'ai' (matches the descriptor's domain field)
     * @param {Function} callback invoked (coalesced) when a matching job
     *                          completes or fails on this browser's channel.
     * @returns {Function} deregister
     */
    onDomain(domain, callback) {
      if (typeof callback !== 'function' || !domain) return () => {};
      if (!subscribers.has(domain)) subscribers.set(domain, new Set());
      const set = subscribers.get(domain);
      set.add(callback);
      return () => set.delete(callback);
    },
  });
})(window);
