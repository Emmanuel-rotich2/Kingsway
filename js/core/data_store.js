/**
 * Smart Data Store
 * 
 * Unified caching layer combining memory cache, IndexedDB, and network fetching.
 * Implements stale-while-revalidate strategy with subscriptions and invalidation.
 * 
 * Data Flow:
 * Read: Page → DataStore → memory cache → IndexedDB → network → update cache → notify subscribers
 * Write: Page → ApiClient → backend → invalidate cache → update local → broadcast
 * Offline: outbox → IndexedDB → Background Sync → backend → invalidate cache → notify
 */

const DataStore = (function() {
  'use strict';

  // Memory cache (fastest, limited size)
  const memoryCache = new Map();
  const MEMORY_CACHE_SIZE_LIMIT = 100; // Max entries
  const MEMORY_CACHE_TTL = 60000; // 1 minute default

  // Subscribers for data changes
  const subscribers = new Map();

  // Invalidation listeners
  const invalidationListeners = new Map();

  // Default cache policies
  const DEFAULT_TTL = {
    REFERENCE: 86400000,   // 24 hours — classes, streams, subjects, terms
    LONG: 604800000,       // 7 days   — academic years, departments, profiles
    DIRECTORY: 300000      // 5 minutes — students, staff
  };
  const defaultPolicies = {
    'classes': { ttl: 86400000, strategy: 'stale-while-revalidate' },        // 24 hours
    'streams': { ttl: 86400000, strategy: 'stale-while-revalidate' },       // 24 hours
    'subjects': { ttl: 86400000, strategy: 'stale-while-revalidate' },      // 24 hours
    'terms': { ttl: 86400000, strategy: 'stale-while-revalidate' },         // 24 hours
    'academic_years': { ttl: 604800000, strategy: 'stale-while-revalidate' }, // 7 days
    'departments': { ttl: 604800000, strategy: 'stale-while-revalidate' },   // 7 days
    'students': { ttl: 300000, strategy: 'network-first' },               // 5 minutes
    'staff': { ttl: 1800000, strategy: 'network-first' },                   // 30 minutes
    'attendance': { ttl: 60000, strategy: 'network-first' },                // 1 minute
    'admissions': { ttl: 60000, strategy: 'network-first' },                // 1 minute
    'school_profile': { ttl: 3600000, strategy: 'stale-while-revalidate' }   // 1 hour
  };

  /**
   * Get data with caching strategy
   */
  async function get(key, options = {}) {
    const {
      strategy = 'stale-while-revalidate',
      ttl = defaultPolicies[key]?.ttl || MEMORY_CACHE_TTL,
      forceRefresh = false,
      storeName = null,
      endpoint = null,
      params = {},
      useMemory = true,
      useIndexedDB = true,
      bypassCache = false
    } = options;

    // Determine cache key
    const cacheKey = generateCacheKey(key, params);

    console.log('[DataStore] Getting data:', key, { strategy, ttl, forceRefresh });

    // Bypass cache if requested
    if (bypassCache) {
      return await fetchFromNetwork(key, endpoint, params);
    }

    // Try memory cache first
    if (useMemory && !forceRefresh) {
      const memoryData = getFromMemory(cacheKey);
      if (memoryData && !isExpired(memoryData)) {
        console.log('[DataStore] Memory cache hit:', key);
        
        // For stale-while-revalidate, refresh in background
        if (strategy === 'stale-while-revalidate') {
          refreshInBackground(key, cacheKey, endpoint, params, ttl, storeName);
        }
        
        return memoryData.data;
      }
    }

    // Try IndexedDB cache
    if (useIndexedDB && !forceRefresh) {
      try {
        const indexedDBData = await KingswayDB.getCached(
          storeName || getStoreNameForKey(key),
          cacheKey,
          getCurrentUserId()
        );
        if (indexedDBData && !isExpired(indexedDBData)) {
          console.log('[DataStore] IndexedDB cache hit:', key);
          
          // Update memory cache
          if (useMemory) {
            setInMemory(cacheKey, indexedDBData, ttl);
          }
          
          // For stale-while-revalidate, refresh in background
          if (strategy === 'stale-while-revalidate') {
            refreshInBackground(key, cacheKey, endpoint, params, ttl, storeName);
          }

          // Cache wrapper stores { id, data, ... }; tolerate stale writes that
          // stored the raw payload directly (no wrapper).
          return indexedDBData.data !== undefined ? indexedDBData.data : indexedDBData;
        }
      } catch (error) {
        console.warn('[DataStore] IndexedDB access failed:', error);
      }
    }

    // Fetch from network
    console.log('[DataStore] Cache miss, fetching from network:', key);
    return await fetchFromNetwork(key, endpoint, params, ttl, storeName, useMemory);
  }

  /**
   * Fetch a page's primary dataset with caching.
   *
   * Thin wrapper around get() that follows the proven pattern of the three
   * already-cached pages (admissions_workspace, students, mark_attendance):
   * try cache -> (fallback) direct API -> cache, with stale-while-revalidate
   * refreshing in the background. Differs from raw get() in three ways:
   *   1. The endpoint is REQUIRED and explicit (the default getEndpointForKey
   *      map is aspirational and does NOT match this app's real /academic/*
   *      routing), so callers must pass the exact path.
   *   2. If DataStore is unavailable, it transparently falls back to a direct
   *      API.GET so callers never need a local try/catch.
   *   3. Returns the raw payload (response.data) for drop-in replacement of
   *      existing `const r = await API.GET(path); this.data = r.data;` calls.
   *
   * Usage:
   *   const classes = await DataStore.fetchPage('classes', {
   *     endpoint: '/academic/classes',
   *     storeName: 'reference_classes',
   *     ttl: DEFAULT_TTL.REFERENCE,           // 24h
   *     strategy: 'stale-while-revalidate'
   *   });
   */
  async function fetchPage(key, options = {}) {
    const {
      endpoint,
      storeName = null,
      ttl = defaultPolicies[key]?.ttl || MEMORY_CACHE_TTL,
      strategy = 'network-first',
      params = {},
      forceRefresh = false
    } = options;

    if (!endpoint) {
      console.error('[DataStore] fetchPage requires an explicit endpoint for key:', key);
      throw new Error('DataStore.fetchPage: endpoint is required');
    }

    // Fast path: DataStore present (memory + IndexedDB + SWR).
    if (typeof window.API !== 'undefined' && typeof window.API.apiCall === 'function') {
      try {
        return await get(key, { endpoint, storeName, ttl, strategy, params, forceRefresh });
      } catch (dsError) {
        console.warn('[DataStore] fetchPage fell back to direct API for', key, dsError);
      }
    }

    // Fallback: direct network call, no caching layer.
    // apiCall() returns the unwrapped payload, so return it as-is.
    const response = await window.API.apiCall(endpoint, 'GET', null, params);
    if (response !== undefined && response !== null) {
      return response;
    }
    throw new Error('Failed to fetch ' + key);
  }

  /**
   * Write a payload to IndexedDB using KingswayDB.setCached().
   *
   * setCached spreads the payload and put()s it into an id-keyed store
   * (keyPath: 'id'). Arrays have no 'id' field, so a bare array write throws
   * DataError and was previously swallowed silently — meaning data never
   * persisted across loads. We wrap in an envelope {id, data, ...} so the
   * single-record put always has a key. Read paths unwrap via unwrapCached().
   */
  async function persist(storeName, key, payload, ttl) {
    if (!storeName) return;
    try {
      await KingswayDB.setCached(
        storeName,
        { id: generateCacheKey(key, {}), data: payload },
        ttl,
        getCurrentUserId(),
        getCurrentRoleId()
      );
    } catch (error) {
      console.warn('[DataStore] Failed to cache in IndexedDB:', error);
    }
  }

  /**
   * Unwrap a KingswayDB record into the original payload.
   * Envelope {id, data} -> data; bare records pass through.
   */
  function unwrapCached(record) {
    if (record && typeof record === 'object' && 'data' in record && record.data !== undefined) {
      return record.data;
    }
    return record;
  }

  /**
   * Fetch data from network
   */
  async function fetchFromNetwork(key, endpoint, params, ttl, storeName, useMemory = true) {
    try {
      const apiEndpoint = endpoint || getEndpointForKey(key);

      // Use centralized API from /js/api.js
      let response;
      if (typeof window.API !== 'undefined' && typeof window.API.apiCall === 'function') {
        response = await window.API.apiCall(apiEndpoint, 'GET', null, params);
      } else {
        throw new Error('Centralized API (window.API.apiCall) not available');
      }

      // apiCall() unwraps the envelope via handleApiResponse() and returns the
      // raw payload (response.data), NOT the {success,data} envelope. So `response`
      // is already the dataset (array, {items}, settings object, etc.). Handle
      // both shapes: if the envelope somehow arrives, unwrap it.
      const payload = (response && response.data !== undefined && response.success !== undefined)
        ? response.data
        : response;

      if (payload !== undefined && payload !== null) {
        const cacheKey = generateCacheKey(key, params);
        const cacheData = {
          key: cacheKey,
          data: payload,
          cached_at: Date.now(),
          expires_at: Date.now() + (ttl || defaultPolicies[key]?.ttl || MEMORY_CACHE_TTL),
          etag: response && response.etag,
          version: response && response.version
        };

        // Store in memory cache
        if (useMemory) {
          setInMemory(cacheKey, cacheData, ttl);
        }

        // Store in IndexedDB (envelope so arrays persist)
        await persist(storeName, cacheKey, payload, ttl);

        console.log('[DataStore] Fetched from network:', key);
        return payload;
      } else {
        throw new Error((response && response.message) || 'Failed to fetch data');
      }
    } catch (error) {
      console.error('[DataStore] Network fetch failed:', key, error);

      // Try to return stale data from IndexedDB as fallback
      if (storeName) {
        try {
          const staleData = await KingswayDB.get(storeName, generateCacheKey(key, params));
          if (staleData) {
            console.log('[DataStore] Returning stale data from IndexedDB:', key);
            return unwrapCached(staleData);
          }
        } catch (e) {
          console.warn('[DataStore] Stale data fallback failed:', e);
        }
      }

      throw error;
    }
  }

  /**
   * Refresh data in background
   */
  async function refreshInBackground(key, cacheKey, endpoint, params, ttl, storeName) {
    try {
      console.log('[DataStore] Refreshing in background:', key);
      
      const apiEndpoint = endpoint || getEndpointForKey(key);
      
      // Use centralized API from /js/api.js
      let response;
      if (typeof window.API !== 'undefined' && typeof window.API.apiCall === 'function') {
        response = await window.API.apiCall(apiEndpoint, 'GET', null, params);
      } else {
        console.warn('[DataStore] Centralized API not available, skipping refresh');
        return;
      }
      
      // apiCall() returns the unwrapped payload (see fetchFromNetwork): treat a
      // truthy result as success and persist either shape.
      const payload = (response && response.data !== undefined && response.success !== undefined)
        ? response.data
        : response;
      if (payload !== undefined && payload !== null) {
        const cacheData = {
          key: cacheKey,
          data: payload,
          cached_at: Date.now(),
          expires_at: Date.now() + (ttl || defaultPolicies[key]?.ttl || MEMORY_CACHE_TTL),
          etag: response && response.etag,
          version: response && response.version
        };

        // Update memory cache
        setInMemory(cacheKey, cacheData, ttl);

        // Update IndexedDB (envelope so arrays persist)
        await persist(storeName, cacheKey, payload, ttl);

        // Notify subscribers
        emit(key, cacheData);
      }
    } catch (error) {
      console.warn('[DataStore] Background refresh failed:', key, error);
    }
  }

  /**
   * Set data in cache
   */
  async function set(key, data, options = {}) {
    const {
      ttl = defaultPolicies[key]?.ttl || MEMORY_CACHE_TTL,
      storeName = null,
      invalidate = true
    } = options;

    const cacheKey = generateCacheKey(key, {});
    const cacheData = {
      key: cacheKey,
      data: data,
      cached_at: Date.now(),
      expires_at: Date.now() + ttl,
      etag: null,
      version: null
    };

    // Store in memory
    setInMemory(cacheKey, cacheData, ttl);

    // Store in IndexedDB as an {id, data} envelope so arrays persist.
    await persist(storeName, cacheKey, data, ttl);

    // Invalidate related caches
    if (invalidate) {
      await invalidateRelated(key);
    }

    // Notify subscribers
    emit(key, cacheData);
  }

  /**
   * Invalidate cached data
   */
  async function invalidate(key, options = {}) {
    const {
      storeName = null,
      params = {}
    } = options;

    const cacheKey = generateCacheKey(key, params);

    // Remove from memory cache
    memoryCache.delete(cacheKey);

    // Remove from IndexedDB
    if (storeName) {
      try {
        await KingswayDB.remove(storeName, cacheKey);
      } catch (error) {
        console.warn('[DataStore] Failed to invalidate in IndexedDB:', error);
      }
    }

    console.log('[DataStore] Invalidated:', key);
    emit('INVALIDATED', { key, cacheKey });
  }

  /**
   * Invalidate multiple keys
   */
  async function invalidateMany(keys) {
    for (const key of keys) {
      await invalidate(key);
    }
  }

  /**
   * Invalidate related caches based on data relationships
   */
  async function invalidateRelated(key) {
    const invalidationRules = {
      'students': ['students', 'student_directory_cache', 'class_list_cache'],
      'classes': ['classes', 'student_directory_cache', 'class_list_cache'],
      'attendance': ['attendance', 'attendance_roster_cache'],
      'admissions': ['admissions', 'admission_queue_cache'],
      'staff': ['staff', 'staff_directory_cache']
    };

    const relatedKeys = invalidationRules[key] || [];
    for (const relatedKey of relatedKeys) {
      await invalidate(relatedKey);
    }
  }

  /**
   * Subscribe to data changes
   */
  function subscribe(key, callback) {
    if (!subscribers.has(key)) {
      subscribers.set(key, new Set());
    }
    subscribers.get(key).add(callback);

    // Return unsubscribe function
    return () => {
      const callbacks = subscribers.get(key);
      if (callbacks) {
        callbacks.delete(callback);
      }
    };
  }

  /**
   * Emit event to subscribers
   */
  function emit(key, data) {
    const callbacks = subscribers.get(key);
    if (callbacks) {
      callbacks.forEach(callback => {
        try {
          callback(data);
        } catch (error) {
          console.error('[DataStore] Subscriber callback error:', error);
        }
      });
    }
  }

  /**
   * Clear all caches
   */
  async function clearAll() {
    // Clear memory cache
    memoryCache.clear();

    // Clear IndexedDB caches
    const storesToClear = [
      'student_directory_cache',
      'staff_directory_cache',
      'class_list_cache',
      'admission_queue_cache',
      'attendance_roster_cache',
      'dashboard_cache'
    ];

    for (const storeName of storesToClear) {
      try {
        await KingswayDB.clear(storeName);
      } catch (error) {
        console.warn('[DataStore] Failed to clear store:', storeName, error);
      }
    }

    console.log('[DataStore] All caches cleared');
    emit('CLEARED', {});
  }

  /**
   * Get cache statistics
   */
  function getStats() {
    return {
      memory: {
        size: memoryCache.size,
        limit: MEMORY_CACHE_SIZE_LIMIT,
        usage: memoryCache.size / MEMORY_CACHE_SIZE_LIMIT
      },
      indexedDB: 'Use KingswayDB.getStats() for IndexedDB stats'
    };
  }

  /**
   * Memory cache operations
   */
  function getFromMemory(key) {
    return memoryCache.get(key);
  }

  function setInMemory(key, data, ttl) {
    // Enforce size limit
    if (memoryCache.size >= MEMORY_CACHE_SIZE_LIMIT) {
      // Remove oldest entry
      const firstKey = memoryCache.keys().next().value;
      memoryCache.delete(firstKey);
    }

    memoryCache.set(key, data);
  }

  function isExpired(cacheData) {
    if (!cacheData.expires_at) {
      return false;
    }
    return Date.now() > cacheData.expires_at;
  }

  /**
   * Generate cache key
   */
  function generateCacheKey(key, params) {
    if (Object.keys(params).length === 0) {
      return key;
    }
    return `${key}:${JSON.stringify(params)}`;
  }

  /**
   * Get IndexedDB store name for key
   */
  function getStoreNameForKey(key) {
    const storeMapping = {
      'classes': 'reference_classes',
      'streams': 'reference_streams',
      'subjects': 'reference_subjects',
      'terms': 'reference_terms',
      'academic_years': 'reference_academic_years',
      'departments': 'reference_departments',
      'students': 'student_directory_cache',
      'staff': 'staff_directory_cache',
      'attendance': 'attendance_roster_cache',
      'admissions': 'admission_queue_cache'
    };

    return storeMapping[key] || 'dashboard_cache';
  }

  /**
   * Get API endpoint for key.
   * Endpoints are RELATIVE — apiCall() prepends API_BASE_URL (/Kingsway/api),
   * so including '/api' here would double it to '/Kingsway/api/api/...'.
   */
  function getEndpointForKey(key) {
    const endpointMapping = {
      'classes': '/attendance/classes',
      'subjects': '/academic/subjects',
      'terms': '/academic/terms',
      'departments': '/website/departments',
      'students': '/students',
      'staff': '/staff',
      'attendance': '/attendance',
      'admissions': '/admission/queues'
    };

    return endpointMapping[key] || `/${key}`;
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

  /**
   * Get current role ID
   */
  function getCurrentRoleId() {
    if (typeof SessionManager !== 'undefined' && SessionManager.isAuthenticated()) {
      const roles = SessionManager.getRoles();
      if (roles && roles.length > 0) {
        return typeof roles[0] === 'object' ? roles[0].id : roles[0];
      }
    }
    return null;
  }

  // Public API
  return {
    get,
    fetchPage,
    set,
    DEFAULT_TTL,
    invalidate,
    invalidateMany,
    subscribe,
    unsubscribeAll: () => {
      subscribers.clear();
      invalidationListeners.clear();
    },
    clearAll,
    getStats,
    invalidateRelated
  };

})();

// Export for use in other modules
if (typeof window !== 'undefined') {
  window.DataStore = DataStore;
}
