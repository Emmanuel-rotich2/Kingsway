/**
 * Storage Monitor
 * 
 * Monitors storage usage across localStorage, sessionStorage, IndexedDB, and Cache Storage.
 * Provides alerts when approaching quota limits and offers cleanup options.
 */

const StorageMonitor = (function() {
  'use strict';

  let subscribers = new Set();
  let monitoringInterval = null;
  const CHECK_INTERVAL = 60000; // Check every minute

  /**
   * Initialize storage monitoring
   */
  function initialize() {
    console.log('[StorageMonitor] Initializing...');
    
    // Start periodic monitoring
    startMonitoring();
    
    // Listen for storage events
    window.addEventListener('storage', handleStorageEvent);
    
    console.log('[StorageMonitor] Initialized');
  }

  /**
   * Start periodic monitoring
   */
  function startMonitoring() {
    if (monitoringInterval) {
      return;
    }
    
    monitoringInterval = setInterval(async () => {
      await checkStorageUsage();
    }, CHECK_INTERVAL);
  }

  /**
   * Stop monitoring
   */
  function stopMonitoring() {
    if (monitoringInterval) {
      clearInterval(monitoringInterval);
      monitoringInterval = null;
    }
    
    console.log('[StorageMonitor] Stopped');
  }

  /**
   * Check storage usage
   */
  async function checkStorageUsage() {
    try {
      const stats = await getStorageStats();
      
      // Check for quota exceeded
      if (stats.indexedDB && stats.indexedDB.available > 0) {
        const usagePercent = stats.indexedDB.used / stats.indexedDB.available;
        
        if (usagePercent > 0.9) {
          emit('QUOTA_WARNING', {
            type: 'indexedDB',
            usage: usagePercent,
            used: stats.indexedused,
            available: stats.indexedDB.available
          });
        }
      }
      
      // Check localStorage usage
      const localStorageUsage = getLocalStorageUsage();
      if (localStorageUsage.usage / localStorageUsage.available > 0.9) {
        emit('QUOTA_WARNING', {
          type: 'localStorage',
          usage: localStorageUsage.usage / localStorageUsage.available,
          used: localStorageUsage.used,
          available: localStorageUsage.available
        });
      }
      
      // Check sessionStorage usage
      const sessionStorageUsage = getSessionStorageUsage();
      if (sessionStorageUsage.usage / sessionStorageUsage.available > 0.9) {
        emit('QUOTA_WARNING', {
          type: 'sessionStorage',
          usage: sessionStorageUsage.usage / sessionStorageUsage.available,
          used: sessionStorageUsage.used,
          available: sessionStorageUsage.available
        });
      }
      
      emit('USAGE_UPDATE', stats);
    } catch (error) {
      console.error('[StorageMonitor] Failed to check storage usage:', error);
    }
  }

  /**
   * Get comprehensive storage statistics
   */
  async function getStorageStats() {
    const stats = {
      localStorage: getLocalStorageUsage(),
      sessionStorage: getSessionStorageUsage(),
      indexedDB: await getIndexedDBUsage(),
      cacheStorage: await getCacheStorageUsage(),
      memory: getMemoryUsage()
    };
    
    return stats;
  }

  /**
   * Get localStorage usage
   */
  function getLocalStorageUsage() {
    try {
      let used = 0;
      const keys = Object.keys(localStorage);
      
      for (const key of keys) {
        used += localStorage.getItem(key).length;
      }
      
      return {
        used,
        available: 5 * 1024 * 1024, // ~5MB typical
        keys: keys.length
      };
    } catch (e) {
      return { used: 0, available: 0, keys: 0, error: e.message };
    }
  }

  /**
   * Get sessionStorage usage
   */
  function getSessionStorageUsage() {
    try {
      let used = 0;
      const keys = Object.keys(sessionStorage);
      
      for (const key of keys) {
        used += sessionStorage.getItem(key).length;
      }
      
      return {
        used,
        available: 5 * 1024 * 1024, // ~5MB typical
        keys: keys.length
      };
    } catch (e) {
      return { used: 0, available: 0, keys: 0, error: e }
    }
  }

  /**
   * Get IndexedDB usage
   */
  async function getIndexedDBUsage() {
    try {
      if (typeof navigator.storage !== 'undefined' && navigator.storage.estimate) {
        const estimate = await navigator.storage.estimate();
        
        // Get KingswayDB specific stats
        const dbStats = await KingswayDB.getStats();
        
        return {
          used: estimate.usage || 0,
          available: estimate.quota || 0,
          databases: estimate.usageDetails?.databases?.length || 0,
          kingswayDB: dbStats
        };
      }
      
      return { used: 0, available: 0, databases: 0 };
    } catch (e) {
      return { used: 0, available: 0, databases: 0, error: e.message };
    }
  }

  /**
   * Get Cache Storage usage
   */
  async function getCacheStorageUsage() {
    try {
      if (typeof caches !== 'undefined') {
        const cacheNames = await caches.keys();
        let totalSize = 0;
        
        for (const cacheName of cacheNames) {
          const cache = await caches.open(cacheName);
          const keys = await cache.keys();
          
          for (const request of keys) {
            const response = await cache.match(request);
            if (response) {
              const blob = await response.blob();
              totalSize += blob.size;
            }
          }
        }
        
        return {
          used: totalSize,
          available: 'Not available', // Cache Storage doesn't provide quota info
          caches: cacheNames.length
        };
      }
      
      return { used: 0, available: 0, caches: 0 };
    } catch (e) {
      return { used: 0, available: 0, caches: 0, error: e.message };
    }
  }

  /**
   * Get memory usage estimate
   */
  function getMemoryUsage() {
    try {
      if (typeof DataStore !== 'undefined') {
        return DataStore.getStats();
      }
      
      return { memory: 0 };
    } catch (e) {
      return { memory: 0, error: e.message };
    }
  }

  /**
   * Clear specific storage type
   */
  async function clearStorage(type) {
    console.log('[StorageMonitor] Clearing storage:', type);
    
    switch (type) {
      case 'localStorage':
        localStorage.clear();
        break;
      
      case 'sessionStorage':
        sessionStorage.clear();
        break;
      
      case 'indexedDB':
        await KingswayDB.clearUserData(getCurrentUserId());
        break;
      
      case 'cacheStorage':
        if (typeof ServiceWorkerManager !== 'undefined') {
          await ServiceWorkerManager.clearCache('kingsway-api-v1');
        }
        break;
      
      case 'all':
        localStorage.clear();
        sessionStorage.clear();
        await KingswayDB.clearUserData(getCurrentUserId());
        if (typeof ServiceWorkerManager !== 'undefined') {
          await ServiceWorkerManager.clearCache('kingsway-api-v1');
          await ServiceWorkerManager.clearCache('kingsway-static-v1');
        }
        break;
      
      default:
        console.warn('[StorageMonitor] Unknown storage type:', type);
    }
    
    emit('STORAGE_CLEARED', { type });
  }

  /**
   * Clear expired cache entries
   */
  async function clearExpired() {
    console.log('[StorageMonitor] Clearing expired cache entries');
    
    let totalCleared = 0;
    
    // Clear expired IndexedDB entries
    const storeNames = Object.keys(KingswayDB.schema);
    for (const storeName of storeNames) {
      const cleared = await KingswayDB.invalidateExpired(storeName);
      totalCleared += cleared;
    }
    
    // Clear expired service worker cache (requires re-implementation)
    // For now, just log
    console.log('[StorageMonitor] Cleared', totalCleared, 'expired IndexedDB entries');
    
    emit('EXPIRED_CLEARED', { count: totalCleared });
    return totalCleared;
  }

  /**
   * Get cleanup recommendations
   */
  async function getCleanupRecommendations() {
    const stats = await getStorageStats();
    const recommendations = [];
    
    // Check for high memory usage
    if (stats.memory && stats.memory.usage / stats.memory.limit > 0.8) {
      recommendations.push({
        type: 'memory',
        priority: 'medium',
        action: 'clear_memory_cache',
        message: 'Memory cache is nearly full. Consider clearing old entries.'
      });
    }
    
    // Check for high localStorage usage
    if (stats.localStorage && stats.localStorage.used / stats.localStorage.available > 0.8) {
      recommendations.push({
        type: 'localStorage',
        priority: 'low',
        action: 'clear_old_preferences',
        message: 'localStorage is nearly full. Consider clearing old preferences.'
      });
    }
    
    // Check for failed sync operations
    const queueStats = await SyncQueue.getQueueStats();
    if (queueStats && queueStats.failed > 10) {
      recommendations.push({
        type: 'sync',
        priority: 'high',
        action: 'clear_failed_operations',
        message: `${queueStats.failed} failed sync operations. Consider clearing or retrying.`
      });
    }
    
    // Check for old conflicts
    const conflictStats = await ConflictManager.getConflictStats();
    if (conflictStats && conflictStats.resolved > 30) {
      recommendations.push({
        type: 'conflicts',
        priority: 'low',
        action: 'clear_old_conflicts',
        message: `${conflictStats.resolved} resolved conflicts. Consider clearing old ones.`
      });
    }
    
    return recommendations;
  }

  /**
   * Handle storage event
   */
  function handleStorageEvent(event) {
    if (event.key && event.newValue === null) {
      // Item was removed
      console.log('[StorageMonitor] Storage item removed:', event.key);
      emit('STORAGE_CHANGED', { key: event.key, oldValue: event.oldValue });
    } else if (event.key && event.newValue) {
      // Item was added/changed
      console.log('[StorageMonitor] Storage item changed:', event.key);
      emit('STORAGE_CHANGED', { key: event.key, newValue: event.newValue });
    }
  }

  /**
   * Subscribe to storage events
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
          console.error('[StorageMonitor] Event callback error:', error);
        }
      }
    });
  }

  /**
   * Get current user ID
   */
  function getCurrentUserId() {
    if (typeof SessionManager !== 'undefined' && SessionManager.isAuthenticated()) {
      const user = SessionManager.getCurrentUser();
      return user ? user.id : null;
    }
    return null;
  }

  // Public API
  return {
    initialize,
    getStorageStats,
    clearStorage,
    clearExpired,
    getCleanupRecommendations,
    subscribe,
    stop
  };

})();

// Export for use in other modules
if (typeof window !== 'undefined') {
  window.StorageMonitor = StorageMonitor;
}
