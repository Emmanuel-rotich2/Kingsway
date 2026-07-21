/** ConnectivityManager: reachability only; never owns or refreshes authentication. */
const ConnectivityManager = (() => {
  'use strict';
  const subscribers = new Set();
  const OFFLINE_CONFIRM_TRIES = 3;
  const CHECK_INTERVAL = 60000;
  let online = true;
  let failures = 0;
  let timer = null;
  let initialized = false;
  let probePromise = null;

  function probeUrl() {
    const base = (window.APP_BASE || '').replace(/\/+$/, '');
    return `${base}/`;
  }

  async function checkConnectivity() {
    if (probePromise) return probePromise;
    probePromise = (async () => {
      try {
        const response = await fetch(probeUrl(), {
          method: 'HEAD',
          credentials: 'same-origin',
          cache: 'no-store',
          headers: { 'Cache-Control': 'no-store' },
        });
        // Any normal HTTP response proves that the browser reached the server.
        // 401/403/404 are routing/auth issues, not an offline condition.
        if (response.status >= 500 || response.status === 408 || response.status === 429) {
          throw new Error(`Reachability probe returned ${response.status}`);
        }
        failures = 0;
        setOnline(true);
        return true;
      } catch (error) {
        failures += 1;
        console.warn(`[ConnectivityManager] Probe failed (${failures}/${OFFLINE_CONFIRM_TRIES}):`, error.message || error);
        if (failures >= OFFLINE_CONFIRM_TRIES && navigator.onLine === false) setOnline(false);
        return false;
      } finally {
        probePromise = null;
      }
    })();
    return probePromise;
  }

  function setOnline(value) {
    if (online === value) return;
    online = value;
    emit(value ? 'ONLINE' : 'OFFLINE', { online });
    if (typeof window.showNotification === 'function') {
      window.showNotification(
        value ? 'Connection restored.' : 'You are offline. Cached information may still be available.',
        value ? 'success' : 'warning',
      );
    }
    if (value) window.SyncQueue?.processQueue?.().catch(console.warn);
  }

  function initialize() {
    if (initialized) return true;
    initialized = true;
    online = navigator.onLine !== false;
    window.addEventListener('online', checkConnectivity);
    window.addEventListener('offline', () => {
      failures = OFFLINE_CONFIRM_TRIES;
      setOnline(false);
    });
    timer = window.setInterval(checkConnectivity, CHECK_INTERVAL);
    window.setTimeout(checkConnectivity, 1500);
    return true;
  }

  function subscribe(event, callback) {
    const entry = { event, callback };
    subscribers.add(entry);
    return () => subscribers.delete(entry);
  }
  function emit(event, data) {
    subscribers.forEach((entry) => {
      if (entry.event === event || entry.event === '*') {
        try { entry.callback(data); } catch (error) { console.error(error); }
      }
    });
  }
  function stop() {
    if (timer) clearInterval(timer);
    timer = null;
    initialized = false;
  }
  return { initialize, checkConnectivity, updateStatus: checkConnectivity,
    getStatus: () => online, isOnline: () => online, subscribe, stop };
})();
window.ConnectivityManager = ConnectivityManager;
