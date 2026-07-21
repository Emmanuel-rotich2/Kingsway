/**
 * Connectivity Manager
 * 
 * Monitors online/offline status and triggers appropriate actions.
 * Manages user-facing connectivity UI and coordinates with other systems.
 */

const ConnectivityManager = (function() {
  'use strict';

  let isOnline = true;
  let subscribers = new Set();
  let retryInterval = null;
  // Suspicious-or-failed probe counter; only declare OFFLINE after the remote
  // probe fails repeatedly, so a single transient error (e.g. a stale SW-
  // intercepted HEAD) can't flip the app to "offline" at boot.
  const OFFLINE_CONFIRM_TRIES = 2;
  let consecutiveFailures = 0;

  /**
   * Initialize connectivity monitoring
   */
  function initialize() {
    console.log('[ConnectivityManager] Initializing...');

    // navigator.onLine is treated as a HINT, not truth. Embedded/headless/iframe
    // contexts routinely report false at startup, and a browser can report
    // "online" while the actual server is unreachable. We always confirm with a
    // real probe (see checkConnectivity) before showing any offline UI.
    isOnline = true;
    
    // Listen for online/offline events
    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);
    
    // Periodic connectivity check
    startConnectivityCheck();
    
    console.log('[ConnectivityManager] Initialized, current status:', isOnline ? 'online' : 'offline');
    emit('INITIAL_STATUS', { online: isOnline });
  }

  /**
   * Handle online event
   */
  function handleOnline() {
    if (!isOnline) {
      isOnline = true;
      console.log('[ConnectivityManager] Connection restored');
      emit('ONLINE', {});
      
      // Trigger sync queue processing
      if (typeof SyncQueue !== 'undefined') {
        SyncQueue.processQueue();
      }
      
      // Trigger session refresh
      if (typeof SessionManager !== 'undefined') {
        SessionManager.refreshSession();
      }
      
      // Show online notification
      showOnlineNotification();
    }
  }

  /**
   * Handle offline event
   */
  function handleOffline() {
    if (isOnline) {
      isOnline = false;
      console.log('[ConnectivityManager] Connection lost');
      emit('OFFLINE', {});
      
      // Show offline notification
      showOfflineNotification();
    }
  }

  /**
   * Start periodic connectivity check
   */
  function startConnectivityCheck() {
    // Check every 30 seconds
    retryInterval = setInterval(checkConnectivity, 30000);
  }

  /**
   * Check actual connectivity (not just browser state).
   *
   * Probes /api/session, which is PUBLIC and returns 200 JSON even when the
   * user is logged out. We route the request through API.callAPI so it gets the
   * same token/credentials handling as every other request and is NEVER served
   * a stale cached page (e.g. home.php) by the service worker. A single failed
   * probe does not flip us to offline; we require OFFLINE_CONFIRM_TRIES
   * consecutive failures before declaring the connection lost.
   */
  async function checkConnectivity() {
    try {
      await API.callAPI('/session', 'GET', null, {}, { checkPermission: false });
      consecutiveFailures = 0;

      const wasOnline = isOnline;
      isOnline = true;

      if (!wasOnline) {
        handleOnline();
      }
      return isOnline;
    } catch (error) {
      consecutiveFailures += 1;
      console.warn(
        '[ConnectivityManager] Probe failed (' + consecutiveFailures + '/' + OFFLINE_CONFIRM_TRIES + '):',
        error && error.message
      );

      // Only declare offline after repeated, confirmed failures.
      if (consecutiveFailures >= OFFLINE_CONFIRM_TRIES && isOnline) {
        handleOffline();
      }
      return false;
    }
  }

  /**
   * Get current connectivity status
   */
  function getStatus() {
    return isOnline;
  }

  /**
   * Update connectivity status (manual trigger)
   */
  function updateStatus() {
    checkConnectivity();
  }

  /**
   * Check if online
   */
  function isOnlineStatus() {
    return isOnline;
  }

  /**
   * Show online notification
   */
  function showOnlineNotification() {
    if (typeof showNotification === 'function') {
      showNotification('Connection restored. Syncing pending changes...', 'success');
    }
  }

  /**
   * Show offline notification
   */
  function showOfflineNotification() {
    if (typeof showNotification === 'function') {
      showNotification('You are offline. Some features may be unavailable.', 'warning');
    }
  }

  /**
   * Subscribe to connectivity events
   */
  function subscribe(event, callback) {
    const entry = { event, callback };
    subscribers.add(entry);

    // Return an unsubscribe that removes the SAME object reference, so it
    // actually matches the stored entry (creating a fresh object here would
    // never equal the stored one and silently leak the listener).
    return () => {
      subscribers.delete(entry);
    };
  }

  /**
   * Emit event to subscribers
   */
  function emit(event, data) {
    subscribers.forEach(({ event: subscribedEvent, callback }) => {
      if (subscribedEvent === event || subscribedEvent === '*') {
        try {
          callback(data);
        } catch (error) {
          console.error('[ConnectivityManager] Event callback error:', error);
        }
      }
    });
  }

  /**
   * Stop connectivity monitoring
   */
  function stop() {
    window.removeEventListener('online', handleOnline);
    window.removeEventListener('offline', handleOffline);
    
    if (retryInterval) {
      clearInterval(retryInterval);
      retryInterval = null;
    }
    
    console.log('[ConnectivityManager] Stopped');
  }

  // Public API
  return {
    initialize,
    getStatus,
    updateStatus,
    isOnline: isOnlineStatus,
    checkConnectivity,
    subscribe,
    stop
  };

})();

// Export for use in other modules
if (typeof window !== 'undefined') {
  window.ConnectivityManager = ConnectivityManager;
}
