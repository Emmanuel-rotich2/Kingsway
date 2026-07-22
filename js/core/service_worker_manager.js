/** ServiceWorkerManager: safe, opt-in updates without reload loops. */
const ServiceWorkerManager = (() => {
  'use strict';
  const subscribers = new Set();
  let registration = null;
  let waitingWorker = null;
  let initialized = false;
  let userApprovedReload = false;

  async function initialize() {
    if (initialized) return Boolean(registration);
    initialized = true;
    if (!('serviceWorker' in navigator)) return false;
    try {
      const base = (window.APP_BASE || '').replace(/\/+$/, '');
      registration = await navigator.serviceWorker.register(base + '/service-worker.js', {
        scope: (base || '') + '/', updateViaCache: 'none'
      });
      registration.addEventListener('updatefound', onUpdateFound);
      navigator.serviceWorker.addEventListener('controllerchange', () => {
        emit('CONTROLLER_CHANGED', {});
        if (userApprovedReload) window.location.reload();
      });
      navigator.serviceWorker.addEventListener('message', (event) => {
        emit(event.data?.type || 'MESSAGE', event.data?.data || event.data);
      });
      if (registration.waiting) setWaiting(registration.waiting);
      registration.update().catch((error) => console.warn('[SW] update check failed', error));
      return true;
    } catch (error) {
      console.error('[ServiceWorkerManager] Registration failed:', error);
      initialized = false;
      return false;
    }
  }

  function onUpdateFound() {
    const worker = registration?.installing;
    if (!worker) return;
    worker.addEventListener('statechange', () => {
      if (worker.state === 'installed' && navigator.serviceWorker.controller) setWaiting(worker);
    });
  }
  function setWaiting(worker) { waitingWorker = worker; emit('UPDATE_AVAILABLE', { scriptURL: worker.scriptURL }); }
  function applyUpdate() {
    const worker = waitingWorker || registration?.waiting;
    if (!worker) return false;
    userApprovedReload = true;
    worker.postMessage({ type: 'SKIP_WAITING' });
    return true;
  }
  function skipWaiting() { return applyUpdate(); }
  async function getCacheStats() {
    const worker = registration?.active;
    if (!worker) return null;
    return new Promise((resolve) => {
      const channel = new MessageChannel();
      const timeout = setTimeout(() => resolve(null), 5000);
      channel.port1.onmessage = (event) => { clearTimeout(timeout); resolve(event.data?.data || event.data); };
      worker.postMessage({ type: 'GET_CACHE_STATS' }, [channel.port2]);
    });
  }
  async function clearCache(cacheName) {
    const worker = registration?.active;
    if (!worker) return false;
    worker.postMessage({ type: 'CLEAR_CACHE', data: { cacheName } });
    return true;
  }
  function subscribe(event, callback) {
    const entry = { event, callback }; subscribers.add(entry); return () => subscribers.delete(entry);
  }
  function emit(event, data) { subscribers.forEach((e) => { if (e.event === event || e.event === '*') e.callback(data); }); }
  return { initialize, applyUpdate, skipWaiting, getCacheStats, clearCache,
    hasUpdate: () => Boolean(waitingWorker || registration?.waiting),
    getRegistration: () => registration, subscribe };
})();
window.ServiceWorkerManager = ServiceWorkerManager;
