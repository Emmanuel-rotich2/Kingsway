/**
 * Smart Storage Manager
 * Uses different storage mechanisms for different purposes with automatic fallbacks
 * 
 * Strategy:
 * - Auth tokens: HttpOnly cookies (set by server) + localStorage fallback
 * - User preferences: localStorage with sessionStorage fallback
 * - Session/UI state: sessionStorage with memory fallback
 * - Cache data: IndexedDB with localStorage fallback
 */

class StorageManager {
  constructor() {
    this.memoryStorage = new Map(); // In-memory fallback
    this.initialized = false;
  }

  /**
   * Initialize storage manager and detect available storage
   */
  initialize() {
    if (this.initialized) return;

    this.testStorage('localStorage');
    this.testStorage('sessionStorage');
    this.testStorage('indexedDB');
    this.testCookies();

    this.initialized = true;
    console.log('StorageManager initialized with capabilities:', this.capabilities);
  }

  /**
   * Test if a storage mechanism is available
   */
  testStorage(type) {
    try {
      if (type === 'indexedDB') {
        return new Promise((resolve) => {
          const request = indexedDB.open('storage-test', 1);
          request.onerror = () => resolve(false);
          request.onsuccess = () => {
            request.result.close();
            resolve(true);
          };
        });
      } else if (type === 'cookies') {
        this.testCookies();
      } else {
        const test = '__storage_test__';
        window[type].setItem(test, test);
        window[type].removeItem(test);
        this.capabilities[type] = true;
        return true;
      }
    } catch (e) {
      this.capabilities[type] = false;
      return false;
    }
  }

  /**
   * Test if cookies are enabled
   */
  testCookies() {
    try {
      document.cookie = 'cookietest=1; SameSite=Strict';
      const ret = document.cookie.indexOf('cookietest=') !== -1;
      document.cookie = 'cookietest=1; SameSite=Strict; expires=Thu, 01 Jan 1970 00:00:01 GMT';
      this.capabilities.cookies = ret;
      return ret;
    } catch (e) {
      this.capabilities.cookies = false;
      return false;
    }
  }

  /**
   * Check if storage type is available
   */
  isAvailable(type) {
    if (!this.initialized) this.initialize();
    return this.capabilities[type] === true;
  }

  /**
   * Store user preferences (persistent across sessions)
   * Priority: localStorage > sessionStorage > memory
   */
  setPreference(key, value) {
    const data = JSON.stringify(value);
    
    if (this.isAvailable('localStorage')) {
      try {
        localStorage.setItem(`pref_${key}`, data);
        return true;
      } catch (e) {
        console.warn('localStorage quota exceeded, falling back to sessionStorage', e);
      }
    }
    
    if (this.isAvailable('sessionStorage')) {
      try {
        sessionStorage.setItem(`pref_${key}`, data);
        return true;
      } catch (e) {
        console.warn('sessionStorage quota exceeded, falling back to memory', e);
      }
    }
    
    this.memoryStorage.set(`pref_${key}`, value);
    return true;
  }

  /**
   * Get user preference
   */
  getPreference(key, defaultValue = null) {
    // Try localStorage first
    if (this.isAvailable('localStorage')) {
      try {
        const data = localStorage.getItem(`pref_${key}`);
        if (data !== null) return JSON.parse(data);
      } catch (e) {
        console.warn('Failed to read from localStorage', e);
      }
    }
    
    // Fallback to sessionStorage
    if (this.isAvailable('sessionStorage')) {
      try {
        const data = sessionStorage.getItem(`pref_${key}`);
        if (data !== null) return JSON.parse(data);
      } catch (e) {
        console.warn('Failed to read from sessionStorage', e);
      }
    }
    
    // Fallback to memory
    if (this.memoryStorage.has(`pref_${key}`)) {
      return this.memoryStorage.get(`pref_${key}`);
    }
    
    return defaultValue;
  }

  /**
   * Store session state (tab-specific, cleared on close)
   * Priority: sessionStorage > memory
   */
  setSessionState(key, value) {
    const data = JSON.stringify(value);
    
    if (this.isAvailable('sessionStorage')) {
      try {
        sessionStorage.setItem(`state_${key}`, data);
        return true;
      } catch (e) {
        console.warn('sessionStorage quota exceeded, falling back to memory', e);
      }
    }
    
    this.memoryStorage.set(`state_${key}`, value);
    return true;
  }

  /**
   * Get session state
   */
  getSessionState(key, defaultValue = null) {
    if (this.isAvailable('sessionStorage')) {
      try {
        const data = sessionStorage.getItem(`state_${key}`);
        if (data !== null) return JSON.parse(data);
      } catch (e) {
        console.warn('Failed to read from sessionStorage', e);
      }
    }
    
    if (this.memoryStorage.has(`state_${key}`)) {
      return this.memoryStorage.get(`state_${key}`);
    }
    
    return defaultValue;
  }

  /**
   * Clear session state
   */
  clearSessionState() {
    if (this.isAvailable('sessionStorage')) {
      const keys = Object.keys(sessionStorage);
      keys.forEach(key => {
        if (key.startsWith('state_')) {
          sessionStorage.removeItem(key);
        }
      });
    }
    
    // Clear memory session state
    for (const key of this.memoryStorage.keys()) {
      if (key.startsWith('state_')) {
        this.memoryStorage.delete(key);
      }
    }
  }

  /**
   * Store cached data (larger datasets, API responses)
   * Priority: IndexedDB > localStorage > memory
   */
  async setCache(key, value, ttl = 3600000) {
    const entry = {
      value,
      timestamp: Date.now(),
      ttl
    };
    
    if (this.isAvailable('indexedDB')) {
      try {
        await this.setIndexedDB('cache', key, entry);
        return true;
      } catch (e) {
        console.warn('IndexedDB failed, falling back to localStorage', e);
      }
    }
    
    if (this.isAvailable('localStorage')) {
      try {
        localStorage.setItem(`cache_${key}`, JSON.stringify(entry));
        return true;
      } catch (e) {
        console.warn('localStorage quota exceeded, falling back to memory', e);
      }
    }
    
    this.memoryStorage.set(`cache_${key}`, entry);
    return true;
  }

  /**
   * Get cached data
   */
  async getCache(key, defaultValue = null) {
    // Try IndexedDB first
    if (this.isAvailable('indexedDB')) {
      try {
        const entry = await this.getIndexedDB('cache', key);
        if (entry && !this.isCacheExpired(entry)) {
          return entry.value;
        }
      } catch (e) {
        console.warn('Failed to read from IndexedDB', e);
      }
    }
    
    // Fallback to localStorage
    if (this.isAvailable('localStorage')) {
      try {
        const data = localStorage.getItem(`cache_${key}`);
        if (data) {
          const entry = JSON.parse(data);
          if (!this.isCacheExpired(entry)) {
            return entry.value;
          }
          // Remove expired cache
          localStorage.removeItem(`cache_${key}`);
        }
      } catch (e) {
        console.warn('Failed to read from localStorage', e);
      }
    }
    
    // Fallback to memory
    if (this.memoryStorage.has(`cache_${key}`)) {
      const entry = this.memoryStorage.get(`cache_${key}`);
      if (!this.isCacheExpired(entry)) {
        return entry.value;
      }
      this.memoryStorage.delete(`cache_${key}`);
    }
    
    return defaultValue;
  }

  /**
   * Check if cache entry is expired
   */
  isCacheExpired(entry) {
    if (!entry.ttl) return false;
    return Date.now() - entry.timestamp > entry.ttl;
  }

  /**
   * Clear expired cache entries
   */
  async clearExpiredCache() {
    const now = Date.now();
    
    // Clear localStorage expired entries
    if (this.isAvailable('localStorage')) {
      const keys = Object.keys(localStorage);
      keys.forEach(key => {
        if (key.startsWith('cache_')) {
          try {
            const data = localStorage.getItem(key);
            if (data) {
              const entry = JSON.parse(data);
              if (entry.ttl && now - entry.timestamp > entry.ttl) {
                localStorage.removeItem(key);
              }
            }
          } catch (e) {
            // Remove corrupt entries
            localStorage.removeItem(key);
          }
        }
      });
    }
    
    // Clear memory expired entries
    for (const key of this.memoryStorage.keys()) {
      if (key.startsWith('cache_')) {
        const entry = this.memoryStorage.get(key);
        if (entry.ttl && now - entry.timestamp > entry.ttl) {
          this.memoryStorage.delete(key);
        }
      }
    }
    
    // IndexedDB would need a more complex cleanup, could be done periodically
  }

  /**
   * IndexedDB helper methods
   */
  async getIndexedDB(storeName, key) {
    return new Promise((resolve, reject) => {
      const request = indexedDB.open('KingswayStorage', 1);
      
      request.onerror = () => reject(request.error);
      request.onsuccess = () => {
        const db = request.result;
        const transaction = db.transaction(storeName, 'readonly');
        const store = transaction.objectStore(storeName);
        const getRequest = store.get(key);
        
        getRequest.onerror = () => reject(getRequest.error);
        getRequest.onsuccess = () => {
          db.close();
          resolve(getRequest.result);
        };
      };
      
      request.onupgradeneeded = (event) => {
        const db = event.target.result;
        if (!db.objectStoreNames.contains(storeName)) {
          db.createObjectStore(storeName);
        }
      };
    });
  }

  async setIndexedDB(storeName, key, value) {
    return new Promise((resolve, reject) => {
      const request = indexedDB.open('KingswayStorage', 1);
      
      request.onerror = () => reject(request.error);
      request.onsuccess = () => {
        const db = request.result;
        const transaction = db.transaction(storeName, 'readwrite');
        const store = transaction.objectStore(storeName);
        const putRequest = store.put(value, key);
        
        putRequest.onerror = () => reject(putRequest.error);
        putRequest.onsuccess = () => {
          db.close();
          resolve(true);
        };
      };
      
      request.onupgradeneeded = (event) => {
        const db = event.target.result;
        if (!db.objectStoreNames.contains(storeName)) {
          db.createObjectStore(storeName);
        }
      };
    });
  }

  /**
   * Get storage usage statistics
   */
  async getStorageStats() {
    const stats = {
      localStorage: { used: 0, available: 0, keys: 0 },
      sessionStorage: { used: 0, available: 0, keys: 0 },
      indexedDB: { used: 0, available: 0, databases: 0 },
      memory: { used: 0, entries: this.memoryStorage.size }
    };

    if (this.isAvailable('localStorage')) {
      stats.localStorage.keys = Object.keys(localStorage).length;
      stats.localStorage.used = JSON.stringify(localStorage).length;
      stats.localStorage.available = 5 * 1024 * 1024; // ~5MB typical
    }

    if (this.isAvailable('sessionStorage')) {
      stats.sessionStorage.keys = Object.keys(sessionStorage).length;
      stats.sessionStorage.used = JSON.stringify(sessionStorage).length;
      stats.sessionStorage.available = 5 * 1024 * 1024; // ~5MB typical
    }

    if (this.isAvailable('indexedDB')) {
      // IndexedDB size estimation is complex, using approximation
      try {
        const estimate = await navigator.storage.estimate();
        stats.indexedDB.used = estimate.usage || 0;
        stats.indexedDB.available = estimate.quota || 0;
      } catch (e) {
        console.warn('Could not get storage estimate', e);
      }
    }

    return stats;
  }

  /**
   * Clear all application data (for logout)
   */
  clearAll() {
    // Clear localStorage (except auth if using cookies)
    if (this.isAvailable('localStorage')) {
      const keys = Object.keys(localStorage);
      keys.forEach(key => {
        if (key.startsWith('pref_') || key.startsWith('cache_') || key.startsWith('state_')) {
          localStorage.removeItem(key);
        }
      });
    }
    
    // Clear sessionStorage
    if (this.isAvailable('sessionStorage')) {
      sessionStorage.clear();
    }
    
    // Clear memory
    this.memoryStorage.clear();
    
    // Note: Auth tokens in HttpOnly cookies are cleared by server-side logout
  }
}

// Global instance - only create if not already exists
if (typeof StorageManager === 'undefined') {
  const StorageManager = new StorageManager();
  
  // Auto-initialize on load
  if (typeof window !== 'undefined') {
    StorageManager.initialize();
  }
}
