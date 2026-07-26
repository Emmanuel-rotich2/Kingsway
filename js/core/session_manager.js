/**
 * SessionManager
 *
 * Thin session facade. AuthContext is the only owner of tokens, user data,
 * roles, permissions and authentication state.
 */
const SessionManager = (() => {
  'use strict';

  const subscribers = new Map();
  let channel = null;
  let initialized = false;
  let monitorTimer = null;
  let refreshPromise = null;

  function auth() {
    return window.AuthContext || null;
  }

  async function initialize() {
    if (initialized) return getSessionState();
    initialized = true;

    const context = auth();
    if (context && typeof context.ready === 'function') {
      try { await context.ready(); } catch (error) {
        console.warn('[SessionManager] AuthContext boot failed:', error);
      }
    }

    initCrossTab();
    startMonitoring();
    emit('INITIALIZED', getSessionState());
    return getSessionState();
  }

  function isAuthenticated() {
    const context = auth();
    return Boolean(context && context.isAuthenticated());
  }

  function getCurrentUser() {
    return auth()?.getUser?.() || null;
  }

  function getRoles() {
    return auth()?.getRoles?.() || [];
  }

  function getPermissions() {
    return auth()?.getPermissions?.() || [];
  }

  function hasPermission(code) {
    return Boolean(auth()?.hasPermission?.(code));
  }

  function hasAnyPermission(codes = []) {
    return Boolean(auth()?.hasAnyPermission?.(codes));
  }

  function hasAllPermissions(codes = []) {
    return Boolean(auth()?.hasAllPermissions?.(codes));
  }

  function getSessionState() {
    return {
      authenticated: isAuthenticated(),
      user: getCurrentUser(),
      roles: getRoles(),
      permissions: getPermissions(),
      lastCheckedAt: new Date().toISOString(),
    };
  }

  async function checkSession() {
    const context = auth();
    if (!context) return false;
    if (typeof context.ready === 'function') await context.ready();
    return context.isAuthenticated();
  }

  async function refreshSession() {
    if (refreshPromise) return refreshPromise;
    const context = auth();
    if (!context || typeof context.refreshToken !== 'function') return false;

    refreshPromise = Promise.resolve(context.refreshToken())
      .then((ok) => {
        if (ok) {
          emit('SESSION_REFRESHED', getSessionState());
          broadcast('SESSION_CHANGED', getSessionState());
        }
        return Boolean(ok);
      })
      .catch((error) => {
        console.warn('[SessionManager] Refresh unavailable:', error);
        return false;
      })
      .finally(() => { refreshPromise = null; });

    return refreshPromise;
  }

  async function login(credentials = {}) {
    if (!window.API?.auth?.login) {
      throw new Error('The canonical API login method is unavailable.');
    }
    const response = await window.API.auth.login(credentials);
    emit('LOGGED_IN', getSessionState());
    broadcast('SESSION_CHANGED', getSessionState());
    return response;
  }

  async function logout() {
    try {
      if (window.API?.auth?.logout) await window.API.auth.logout();
    } finally {
      auth()?.clearUser?.();
      emit('LOGGED_OUT', {});
      broadcast('LOGGED_OUT', {});
    }
  }

  function onSessionExpired() {
    // api.js already clears AuthContext only after refresh receives 401/403.
    emit('SESSION_EXPIRED', {});
    broadcast('SESSION_EXPIRED', {});
    window.dispatchEvent(new CustomEvent('SESSION_EXPIRED_CONFIRMED'));
    redirectToLogin();
  }

  function subscribe(event, callback) {
    if (!subscribers.has(event)) subscribers.set(event, new Set());
    subscribers.get(event).add(callback);
    return () => subscribers.get(event)?.delete(callback);
  }

  function emit(event, data) {
    [event, '*'].forEach((key) => {
      subscribers.get(key)?.forEach((callback) => {
        try { callback(data, event); } catch (error) {
          console.error('[SessionManager] Subscriber failed:', error);
        }
      });
    });
  }

  function initCrossTab() {
    if ('BroadcastChannel' in window) {
      channel = new BroadcastChannel('kingsway-session');
      channel.onmessage = ({ data }) => handleRemoteMessage(data);
    }
    window.addEventListener('storage', (event) => {
      if (event.key === 'kingsway_session_event' && event.newValue) {
        try { handleRemoteMessage(JSON.parse(event.newValue)); } catch (_) {}
      }
    });
  }

  function broadcast(type, data = {}) {
    const message = { type, data, timestamp: Date.now() };
    channel?.postMessage(message);
    try {
      localStorage.setItem('kingsway_session_event', JSON.stringify(message));
      localStorage.removeItem('kingsway_session_event');
    } catch (_) {}
  }

  function handleRemoteMessage(message) {
    if (!message?.type) return;
    if (message.type === 'LOGGED_OUT' || message.type === 'SESSION_EXPIRED') {
      auth()?.clearUser?.();
      if (message.type === 'SESSION_EXPIRED') redirectToLogin();
    } else if (message.type === 'SESSION_CHANGED') {
      auth()?.initialize?.();
    }
    emit(message.type, message.data || {});
  }

  function broadcastCacheInvalidation(keys) {
    broadcast('CACHE_INVALIDATED', {
      keys: Array.isArray(keys) ? keys : [keys],
    });
  }

  function startMonitoring() {
    if (monitorTimer) return;
    monitorTimer = window.setInterval(async () => {
      emit('SESSION_CHECK', getSessionState());
      if (
        isAuthenticated() &&
        window.KingswaySessionActivity?.shouldRefreshSoon?.()
      ) {
        await refreshSession();
      }
    }, 60000);
  }

  function redirectToLogin() {
    if (sessionStorage.getItem('_session_expired_redirect')) return;
    sessionStorage.setItem('_session_expired_redirect', '1');
    window.setTimeout(() => {
      sessionStorage.removeItem('_session_expired_redirect');
      window.location.replace(`${window.APP_BASE || ''}/index.php`);
    }, 800);
  }

  function stop() {
    if (monitorTimer) clearInterval(monitorTimer);
    monitorTimer = null;
    channel?.close();
    channel = null;
    initialized = false;
  }

  return {
    initialize,
    isAuthenticated,
    getSessionState,
    getCurrentUser,
    getRoles,
    getPermissions,
    hasPermission,
    hasAnyPermission,
    hasAllPermissions,
    checkSession,
    login,
    logout,
    refreshSession,
    onSessionExpired,
    subscribe,
    broadcast,
    broadcastCacheInvalidation,
    stop,
  };
})();
window.SessionManager = SessionManager;
