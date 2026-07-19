/**
 * Connectivity Manager
 * 
 * Monitors online/offline status and triggers appropriate actions.
 * Manages user-facing connectivity UI and coordinates with other systems.
 */

const ConnectivityManager = (function() {
  'use strict';

  let isOnline = navigator.onLine;
  let subscribers = new Set();
  let retryInterval = null;

  /**
   * Initialize connectivity monitoring
   */
  function initialize() {
    console.log('[ConnectivityManager] Initializing...');
    
    // Set initial state
    isOnline = navigator.onLine;
    
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
   * Check actual connectivity (not just browser state)
   */
  async function checkConnectivity() {
    try {
      // Try to fetch a small resource
      const response = await fetch('./home.php', {
        method: 'HEAD',
        cache: 'no-cache',
        signal: AbortSignal.timeout(5000)
      });
      
      const wasOnline = isOnline;
      isOnline = response.ok;
      
      if (isOnline && !wasOnline) {
        handleOnline();
      } else if (!isOnline && wasOnline) {
        handleOffline();
      }
      
      return isOnline;
    } catch (error) {
      if (isOnline) {
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
    subscribers.add({ event, callback });
    
    return () => {
      subscribers.delete({ event, callback });
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
