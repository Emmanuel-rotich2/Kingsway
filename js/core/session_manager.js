/**
 * SessionManager
 *
 * Canonical app-level session facade. AuthContext remains the only owner of
 * access tokens, refresh tokens, user data, roles and permissions.
 */
const SessionManager = (() => {
  'use strict';

  const subscribers = new Map();
  const sessionConfig = window.AUTH_SESSION_CONFIG || {};
  const monitorIntervalMs =
    Math.max(
      15,
      Number(sessionConfig.monitorIntervalSeconds) || 30
    ) * 1000;

  let channel = null;
  let initialized = false;
  let monitorTimer = null;
  let refreshPromise = null;
  let expiryHandled = false;

  function auth() {
    return window.AuthContext || null;
  }

  async function initialize() {
    if (initialized) return getSessionState();
    initialized = true;

    const context = auth();
    if (context && typeof context.ready === 'function') {
      try {
        await context.ready();
      } catch (error) {
        console.warn('[SessionManager] AuthContext boot failed:', error);
      }
    }

    initCrossTab();
    startMonitoring();
    emit('INITIALIZED', getSessionState());
    return getSessionState();
  }

  function hasStoredSession() {
    const context = auth();
    if (!context) return false;

    if (typeof context.hasSession === 'function') {
      return Boolean(context.hasSession());
    }

    return Boolean(context.getUser?.() && context.getToken?.());
  }

  function isAuthenticated() {
    const context = auth();
    return Boolean(context && context.isAuthenticated?.());
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
      hasStoredSession: hasStoredSession(),
      user: getCurrentUser(),
      roles: getRoles(),
      permissions: getPermissions(),
      lastActivityAt:
        window.KingswaySessionActivity?.lastActivityAt?.() || null,
      secondsUntilExpiry:
        window.KingswaySessionActivity?.secondsUntilExpiry?.() ?? null,
      lastCheckedAt: new Date().toISOString(),
    };
  }

  async function checkSession() {
    const context = auth();
    if (!context) return false;
    if (typeof context.ready === 'function') await context.ready();
    return Boolean(context.isAuthenticated?.());
  }

  async function refreshSession() {
    if (refreshPromise) return refreshPromise;

    const context = auth();
    if (!context || typeof context.refreshToken !== 'function') {
      return false;
    }

    refreshPromise = Promise.resolve(context.refreshToken())
      .then((refreshed) => {
        const ok = Boolean(refreshed);
        if (ok) {
          expiryHandled = false;
          emit('SESSION_REFRESHED', getSessionState());
          broadcast('SESSION_CHANGED', getSessionState());
        }
        return ok;
      })
      .catch((error) => {
        console.warn('[SessionManager] Refresh unavailable:', error);
        return false;
      })
      .finally(() => {
        refreshPromise = null;
      });

    return refreshPromise;
  }

  async function login(credentials = {}) {
    if (!window.API?.auth?.login) {
      throw new Error('The canonical API login method is unavailable.');
    }

    const response = await window.API.auth.login(credentials);
    expiryHandled = false;
    sessionStorage.removeItem('_session_expired_redirect');
    emit('LOGGED_IN', getSessionState());
    broadcast('SESSION_CHANGED', getSessionState());
    return response;
  }

  async function logout() {
    try {
      if (window.API?.auth?.logout) {
        await window.API.auth.logout();
      }
    } finally {
      auth()?.clearUser?.();
      expiryHandled = true;
      emit('LOGGED_OUT', {});
      broadcast('LOGGED_OUT', {});
      redirectToLogin(0);
    }
  }

  function onSessionExpired(reason = 'session_expired') {
    if (expiryHandled) return;
    expiryHandled = true;

    auth()?.clearUser?.();

    const detail = { reason };
    emit('SESSION_EXPIRED', detail);
    broadcast('SESSION_EXPIRED', detail);

    window.dispatchEvent(
      new CustomEvent('SESSION_EXPIRED_CONFIRMED', { detail })
    );

    if (typeof window.API?.showNotification === 'function') {
      const message = reason === 'idle_timeout'
        ? 'Your session expired after 30 minutes of inactivity. Please sign in again.'
        : 'Your session has expired. Please sign in again.';
      window.API.showNotification(message, 'warning');
    }

    redirectToLogin(350);
  }

  function subscribe(event, callback) {
    if (!subscribers.has(event)) subscribers.set(event, new Set());
    subscribers.get(event).add(callback);
    return () => subscribers.get(event)?.delete(callback);
  }

  function emit(event, data) {
    [event, '*'].forEach((key) => {
      subscribers.get(key)?.forEach((callback) => {
        try {
          callback(data, event);
        } catch (error) {
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
        try {
          handleRemoteMessage(JSON.parse(event.newValue));
        } catch (_) {}
      }
    });
  }

  function broadcast(type, data = {}) {
    const message = { type, data, timestamp: Date.now() };
    channel?.postMessage(message);

    try {
      localStorage.setItem(
        'kingsway_session_event',
        JSON.stringify(message)
      );
      localStorage.removeItem('kingsway_session_event');
    } catch (_) {}
  }

  function handleRemoteMessage(message) {
    if (!message?.type) return;

    if (message.type === 'LOGGED_OUT') {
      expiryHandled = true;
      auth()?.clearUser?.();
      redirectToLogin(0);
    } else if (message.type === 'SESSION_EXPIRED') {
      if (!expiryHandled) {
        expiryHandled = true;
        auth()?.clearUser?.();
        redirectToLogin(0);
      }
    } else if (message.type === 'SESSION_CHANGED') {
      expiryHandled = false;
      auth()?.initialize?.();
    }

    emit(message.type, message.data || {});
  }

  function broadcastCacheInvalidation(keys) {
    broadcast('CACHE_INVALIDATED', {
      keys: Array.isArray(keys) ? keys : [keys],
    });
  }

  async function monitorSession() {
    emit('SESSION_CHECK', getSessionState());

    if (!hasStoredSession()) return;

    const activity = window.KingswaySessionActivity;
    if (activity?.isIdleExpired?.()) {
      onSessionExpired('idle_timeout');
      return;
    }

    const secondsUntilExpiry = activity?.secondsUntilExpiry?.();
    const shouldRefresh = Boolean(activity?.shouldRefreshSoon?.());

    if (shouldRefresh) {
      const refreshed = await refreshSession();
      if (
        !refreshed &&
        (secondsUntilExpiry === null || secondsUntilExpiry <= 0)
      ) {
        onSessionExpired('expired_token_refresh_failed');
      }
      return;
    }

    if (secondsUntilExpiry !== null && secondsUntilExpiry <= 0) {
      onSessionExpired('access_token_expired');
    }
  }

  function startMonitoring() {
    if (monitorTimer) return;

    void monitorSession();
    monitorTimer = window.setInterval(
      () => void monitorSession(),
      monitorIntervalMs
    );
  }

  function redirectToLogin(delayMs = 0) {
    if (sessionStorage.getItem('_session_expired_redirect')) return;

    sessionStorage.setItem('_session_expired_redirect', '1');
    window.setTimeout(() => {
      window.location.replace(`${window.APP_BASE || ''}/index.php`);
    }, Math.max(0, Number(delayMs) || 0));
  }

  function stop() {
    if (monitorTimer) clearInterval(monitorTimer);
    monitorTimer = null;
    channel?.close();
    channel = null;
    initialized = false;
    refreshPromise = null;
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
