/**
 * Optional speculative loader.
 * Disabled by default because it must never compete with required page data.
 */
const SpeculativeLoader = (() => {
  'use strict';
  let initialized = false;
  let enabled = false;
  let idleHandle = null;
  let running = false;
  const prefetchedRoutes = new Set();
  const rules = new Map();

  function initialize(options = {}) {
    if (initialized) return getState();
    initialized = true;
    enabled = options.enabled === true;
    if (enabled) schedule();
    return getState();
  }

  function schedule() {
    if (!enabled || running || idleHandle !== null) return;
    const run = () => { idleHandle = null; void prefetchCurrentRoute(); };
    idleHandle = 'requestIdleCallback' in window
      ? window.requestIdleCallback(run, { timeout: 3000 })
      : window.setTimeout(run, 1500);
  }

  async function prefetchCurrentRoute() {
    if (!enabled || running) return;
    const route = new URLSearchParams(location.search).get('route');
    if (!route || prefetchedRoutes.has(route)) return;
    running = true;
    try {
      const targets = rules.get(route) || [];
      for (const target of targets) {
        if (!target?.url || prefetchedRoutes.has(target.url)) continue;
        await fetch(target.url, { method: 'GET', credentials: 'same-origin', cache: 'no-store' });
        prefetchedRoutes.add(target.url);
      }
      prefetchedRoutes.add(route);
    } catch (error) {
      console.debug('[SpeculativeLoader] Prefetch skipped', error?.message || error);
    } finally {
      running = false;
    }
  }

  function addPrefetchRule(route, targets) {
    rules.set(route, Array.isArray(targets) ? targets : []);
  }

  function setEnabled(value) {
    enabled = Boolean(value);
    if (enabled) schedule(); else stop();
  }

  function stop() {
    enabled = false;
    if (idleHandle !== null) {
      if ('cancelIdleCallback' in window) window.cancelIdleCallback(idleHandle);
      else clearTimeout(idleHandle);
      idleHandle = null;
    }
  }

  function getState() {
    return { initialized, enabled, running, prefetchedRoutes: [...prefetchedRoutes] };
  }

  return { initialize, addPrefetchRule, setEnabled, stop, getState };
})();
window.SpeculativeLoader = SpeculativeLoader;
