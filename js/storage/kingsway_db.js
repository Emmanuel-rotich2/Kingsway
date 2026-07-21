/**
 * Kingsway IndexedDB Wrapper
 * 
 * Provides a structured interface for IndexedDB operations
 * following the storage ownership matrix defined in docs/CLIENT_DATA_OWNERSHIP_MATRIX.md
 */

const KingswayDB = (function() {
  'use strict';

  const DB_NAME = 'KingswayDB';
  const DB_VERSION = 5;

  let db = null;

  /**
   * Database schema definition
   */
  const schema = {
    // Reference metadata stores
    reference_classes: {
      keyPath: 'id',
      indexes: [
        { name: 'name', keyPath: 'name', unique: false },
        { name: 'cached_at', keyPath: 'cached_at', unique: false },
        { name: 'expires_at', keyPath: 'expires_at', unique: false },
        { name: 'user_id', keyPath: 'user_id', unique: false }
      ]
    },
    reference_streams: {
      keyPath: 'id',
      indexes: [
        { name: 'name', keyPath: 'name', unique: false },
        { name: 'cached_at', keyPath: 'cached_at', unique: false },
        { name: 'expires_at', keyPath: 'expires_at', unique: false }
      ]
    },
    reference_terms: {
      keyPath: 'id',
      indexes: [
        { name: 'name', keyPath: 'name', unique: false },
        { name: 'cached_at', keyPath: 'cached_at', unique: false },
        { name: 'expires_at', keyPath: 'expires_at', unique: false }
      ]
    },
    reference_academic_years: {
      keyPath: 'id',
      indexes: [
        { name: 'name', keyPath: 'name', unique: false },
        { name: 'cached_at', keyPath: 'cached_at', unique: false },
        { name: 'expires_at', keyPath: 'expires_at', unique: false }
      ]
    },
    reference_subjects: {
      keyPath: 'id',
      indexes: [
        { name: 'name', keyPath: 'name', unique: false },
        { name: 'cached_at', keyPath: 'cached_at', unique: false },
        { name: 'expires_at', keyPath: 'expires_at', unique: false }
      ]
    },
    reference_departments: {
      keyPath: 'id',
      indexes: [
        { name: 'name', keyPath: 'name', unique: false },
        { name: 'cached_at', keyPath: 'cached_at', unique: false },
        { name: 'expires_at', keyPath: 'expires_at', unique: false }
      ]
    },
    reference_dormitories: {
      keyPath: 'id',
      indexes: [
        { name: 'name', keyPath: 'name', unique: false },
        { name: 'cached_at', keyPath: 'cached_at', unique: false },
        { name: 'expires_at', keyPath: 'expires_at', unique: false }
      ]
    },
    reference_transport_routes: {
      keyPath: 'id',
      indexes: [
        { name: 'name', keyPath: 'name', unique: false },
        { name: 'cached_at', keyPath: 'cached_at', unique: false },
        { name: 'expires_at', keyPath: 'expires_at', unique: false }
      ]
    },
    reference_activity_types: {
      keyPath: 'id',
      indexes: [
        { name: 'name', keyPath: 'name', unique: false },
        { name: 'cached_at', keyPath: 'cached_at', unique: false },
        { name: 'expires_at', keyPath: 'expires_at', unique: false }
      ]
    },

    // Cached read models
    student_directory_cache: {
      keyPath: 'id',
      indexes: [
        { name: 'admission_number', keyPath: 'admission_number', unique: false },
        { name: 'name', keyPath: 'name', unique: false },
        { name: 'class_id', keyPath: 'class_id', unique: false },
        { name: 'cached_at', keyPath: 'cached_at', unique: false },
        { name: 'expires_at', keyPath: 'expires_at', unique: false },
        { name: 'user_id', keyPath: 'user_id', unique: false },
        { name: 'scope_hash', keyPath: 'scope_hash', unique: false }
      ]
    },
    staff_directory_cache: {
      keyPath: 'id',
      indexes: [
        { name: 'name', keyPath: 'name', unique: false },
        { name: 'cached_at', keyPath: 'cached_at', unique: false },
        { name: 'expires_at', keyPath: 'expires_at', unique: false },
        { name: 'user_id', keyPath: 'user_id', unique: false }
      ]
    },
    class_list_cache: {
      keyPath: 'id',
      indexes: [
        { name: 'class_id', keyPath: 'class_id', unique: false },
        { name: 'cached_at', keyPath: 'cached_at', unique: false },
        { name: 'expires_at', keyPath: 'expires_at', unique: false },
        { name: 'user_id', keyPath: 'user_id', unique: false }
      ]
    },
    admission_queue_cache: {
      keyPath: 'id',
      indexes: [
        { name: 'status', keyPath: 'status', unique: false },
        { name: 'cached_at', keyPath: 'cached_at', unique: false },
        { name: 'expires_at', keyPath: 'expires_at', unique: false }
      ]
    },
    attendance_roster_cache: {
      keyPath: 'id',
      indexes: [
        { name: 'class_id', keyPath: 'class_id', unique: false },
        { name: 'date', keyPath: 'date', unique: false },
        { name: 'cached_at', keyPath: 'cached_at', unique: false },
        { name: 'expires_at', keyPath: 'expires_at', unique: false }
      ]
    },

    // Offline drafts
    offline_drafts: {
      keyPath: 'id',
      indexes: [
        { name: 'module', keyPath: 'module', unique: false },
        { name: 'form_type', keyPath: 'form_type', unique: false },
        { name: 'user_id', keyPath: 'user_id', unique: false },
        { name: 'status', keyPath: 'status', unique: false },
        { name: 'created_at', keyPath: 'created_at', unique: false },
        { name: 'updated_at', keyPath: 'updated_at', unique: false }
      ]
    },

    // Pending mutations
    sync_outbox: {
      keyPath: 'id',
      indexes: [
        { name: 'operation_id', keyPath: 'operation_id', unique: true },
        { name: 'module', keyPath: 'module', unique: false },
        { name: 'status', keyPath: 'status', unique: false },
        { name: 'user_id', keyPath: 'user_id', unique: false },
        { name: 'created_at', keyPath: 'created_at', unique: false },
        { name: 'priority', keyPath: 'priority', unique: false },
        { name: 'idempotency_key', keyPath: 'idempotency_key', unique: true }
      ]
    },

    // Sync conflicts
    sync_conflicts: {
      keyPath: 'id',
      indexes: [
        { name: 'operation_id', keyPath: 'operation_id', unique: false },
        { name: 'entity_type', keyPath: 'entity_type', unique: false },
        { name: 'entity_id', keyPath: 'entity_id', unique: false },
        { name: 'detected_at', keyPath: 'detected_at', unique: false },
        { name: 'resolved_at', keyPath: 'resolved_at', unique: false },
        { name: 'user_id', keyPath: 'user_id', unique: false }
      ]
    },

    // Notifications
    notification_inbox: {
      keyPath: 'id',
      indexes: [
        { name: 'type', keyPath: 'type', unique: false },
        { name: 'read', keyPath: 'read', unique: false },
        { name: 'created_at', keyPath: 'created_at', unique: false },
        { name: 'expires_at', keyPath: 'expires_at', unique: false },
        { name: 'user_id', keyPath: 'user_id', unique: false },
        { name: 'priority', keyPath: 'priority', unique: false }
      ]
    },

    // Dashboard cache
    dashboard_cache: {
      keyPath: 'id',
      indexes: [
        { name: 'dashboard_type', keyPath: 'dashboard_type', unique: false },
        { name: 'user_id', keyPath: 'user_id', unique: false },
        { name: 'cached_at', keyPath: 'cached_at', unique: false },
        { name: 'expires_at', keyPath: 'expires_at', unique: false },
        { name: 'role_id', keyPath: 'role_id', unique: false }
      ]
    },

    // File upload queue
    pending_uploads: {
      keyPath: 'id',
      indexes: [
        { name: 'status', keyPath: 'status', unique: false },
        { name: 'user_id', keyPath: 'user_id', unique: false },
        { name: 'entity_type', keyPath: 'entity_type', unique: false },
        { name: 'created_at', keyPath: 'created_at', unique: false }
      ]
    },

    // Public website cache (anonymous read-through via PublicCache). These
    // mirror the admin reference_* / *_cache stores so DataStore.fetchPage can
    // persist them. Names must stay in sync with RESOURCES in public.js.
    public_news: {
      keyPath: 'id',
      indexes: [
        { name: 'cached_at', keyPath: 'cached_at', unique: false },
        { name: 'expires_at', keyPath: 'expires_at', unique: false }
      ]
    },
    public_events: {
      keyPath: 'id',
      indexes: [
        { name: 'cached_at', keyPath: 'cached_at', unique: false },
        { name: 'expires_at', keyPath: 'expires_at', unique: false }
      ]
    },
    public_downloads: {
      keyPath: 'id',
      indexes: [
        { name: 'cached_at', keyPath: 'cached_at', unique: false },
        { name: 'expires_at', keyPath: 'expires_at', unique: false }
      ]
    },
    public_jobs: {
      keyPath: 'id',
      indexes: [
        { name: 'cached_at', keyPath: 'cached_at', unique: false },
        { name: 'expires_at', keyPath: 'expires_at', unique: false }
      ]
    },
    public_leadership: {
      keyPath: 'id',
      indexes: [
        { name: 'cached_at', keyPath: 'cached_at', unique: false },
        { name: 'expires_at', keyPath: 'expires_at', unique: false }
      ]
    },
    public_programs: {
      keyPath: 'id',
      indexes: [
        { name: 'cached_at', keyPath: 'cached_at', unique: false },
        { name: 'expires_at', keyPath: 'expires_at', unique: false }
      ]
    },
    public_facilities: {
      keyPath: 'id',
      indexes: [
        { name: 'cached_at', keyPath: 'cached_at', unique: false },
        { name: 'expires_at', keyPath: 'expires_at', unique: false }
      ]
    },
    public_history: {
      keyPath: 'id',
      indexes: [
        { name: 'cached_at', keyPath: 'cached_at', unique: false },
        { name: 'expires_at', keyPath: 'expires_at', unique: false }
      ]
    },
    public_values: {
      keyPath: 'id',
      indexes: [
        { name: 'cached_at', keyPath: 'cached_at', unique: false },
        { name: 'expires_at', keyPath: 'expires_at', unique: false }
      ]
    },
    public_departments: {
      keyPath: 'id',
      indexes: [
        { name: 'cached_at', keyPath: 'cached_at', unique: false },
        { name: 'expires_at', keyPath: 'expires_at', unique: false }
      ]
    },
    public_steps: {
      keyPath: 'id',
      indexes: [
        { name: 'cached_at', keyPath: 'cached_at', unique: false },
        { name: 'expires_at', keyPath: 'expires_at', unique: false }
      ]
    },
    public_benefits: {
      keyPath: 'id',
      indexes: [
        { name: 'cached_at', keyPath: 'cached_at', unique: false },
        { name: 'expires_at', keyPath: 'expires_at', unique: false }
      ]
    },
    public_gallery: {
      keyPath: 'id',
      indexes: [
        { name: 'cached_at', keyPath: 'cached_at', unique: false },
        { name: 'expires_at', keyPath: 'expires_at', unique: false }
      ]
    },
    public_categories: {
      keyPath: 'id',
      indexes: [
        { name: 'cached_at', keyPath: 'cached_at', unique: false },
        { name: 'expires_at', keyPath: 'expires_at', unique: false }
      ]
    },
    public_content: {
      keyPath: 'id',
      indexes: [
        { name: 'cached_at', keyPath: 'cached_at', unique: false },
        { name: 'expires_at', keyPath: 'expires_at', unique: false }
      ]
    },
    public_settings: {
      keyPath: 'id',
      indexes: [
        { name: 'cached_at', keyPath: 'cached_at', unique: false },
        { name: 'expires_at', keyPath: 'expires_at', unique: false }
      ]
    }
  };

  /**
   * Initialize database
   */
  async function initialize() {
    if (db) {
      return db;
    }

    return new Promise((resolve, reject) => {
      const request = indexedDB.open(DB_NAME, DB_VERSION);

      request.onerror = () => {
        console.error('[KingswayDB] Failed to open database:', request.error);
        reject(request.error);
      };

      request.onsuccess = () => {
        db = request.result;
        console.log('[KingswayDB] Database opened successfully');
        resolve(db);
      };

      request.onupgradeneeded = (event) => {
        const database = event.target.result;
        console.log('[KingswayDB] Database upgrade needed:', event.oldVersion, '→', event.newVersion);

        // Create object stores
        for (const [storeName, storeConfig] of Object.entries(schema)) {
          if (!database.objectStoreNames.contains(storeName)) {
            const store = database.createObjectStore(storeName, {
              keyPath: storeConfig.keyPath
            });

            // Create indexes
            for (const indexConfig of storeConfig.indexes) {
              store.createIndex(indexConfig.name, indexConfig.keyPath, {
                unique: indexConfig.unique || false
              });
            }

            console.log('[KingswayDB] Created store:', storeName);
          }
        }
      };
    });
  }

  /**
   * Get store reference
   */
  function getStore(storeName, mode = 'readonly') {
    if (!db) {
      throw new Error('Database not initialized. Call initialize() first.');
    }

    const transaction = db.transaction(storeName, mode);
    return transaction.objectStore(storeName);
  }

  /**
   * Generic CRUD operations
   */
  async function get(storeName, key) {
    await initialize();
    return new Promise((resolve, reject) => {
      try {
        const store = getStore(storeName);
        const request = store.get(key);

        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
      } catch (error) {
        reject(error);
      }
    });
  }

  async function getAll(storeName) {
    await initialize();
    return new Promise((resolve, reject) => {
      try {
        const store = getStore(storeName);
        const request = store.getAll();

        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
      } catch (error) {
        reject(error);
      }
    });
  }

  async function put(storeName, data) {
    await initialize();
    return new Promise((resolve, reject) => {
      try {
        const store = getStore(storeName, 'readwrite');
        const request = store.put(data);

        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
      } catch (error) {
        reject(error);
      }
    });
  }

  async function add(storeName, data) {
    await initialize();
    return new Promise((resolve, reject) => {
      try {
        const store = getStore(storeName, 'readwrite');
        const request = store.add(data);

        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
      } catch (error) {
        reject(error);
      }
    });
  }

  async function remove(storeName, key) {
    await initialize();
    return new Promise((resolve, reject) => {
      try {
        const store = getStore(storeName, 'readwrite');
        const request = store.delete(key);

        request.onsuccess = () => resolve(true);
        request.onerror = () => reject(request.error);
      } catch (error) {
        reject(error);
      }
    });
  }

  async function clear(storeName) {
    await initialize();
    return new Promise((resolve, reject) => {
      try {
        const store = getStore(storeName, 'readwrite');
        const request = store.clear();

        request.onsuccess = () => resolve(true);
        request.onerror = () => reject(request.error);
      } catch (error) {
        reject(error);
      }
    });
  }

  /**
   * Query operations
   */
  async function getByIndex(storeName, indexName, value) {
    await initialize();
    return new Promise((resolve, reject) => {
      try {
        const store = getStore(storeName);
        const index = store.index(indexName);
        const request = index.getAll(value);

        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
      } catch (error) {
        reject(error);
      }
    });
  }

  async function getByIndexRange(storeName, indexName, range) {
    await initialize();
    return new Promise((resolve, reject) => {
      try {
        const store = getStore(storeName);
        const index = store.index(indexName);
        const request = index.getAll(range);

        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
      } catch (error) {
        reject(error);
      }
    });
  }

  /**
   * Cache-specific operations
   */
  async function getCached(storeName, key, userId = null) {
    await initialize();
    
    try {
      const data = await get(storeName, key);
      
      if (!data) {
        return null;
      }

      // Check if expired
      if (data.expires_at && Date.now() > data.expires_at) {
        await remove(storeName, key);
        return null;
      }

      // Check user scope
      if (userId && data.user_id && data.user_id !== userId) {
        return null;
      }

      return data;
    } catch (error) {
      console.error('[KingswayDB] Failed to get cached data:', error);
      return null;
    }
  }

  async function setCached(storeName, data, ttl, userId = null, roleId = null) {
    await initialize();
    
    const now = Date.now();
    const cacheData = {
      ...data,
      cached_at: now,
      expires_at: now + ttl,
      user_id: userId,
      role_id: roleId,
      scope_hash: generateScopeHash(userId, roleId)
    };

    return await put(storeName, cacheData);
  }

  /**
   * Invalidate expired cache entries
   */
  async function invalidateExpired(storeName) {
    await initialize();
    
    try {
      const store = getStore(storeName, 'readwrite');
      const index = store.index('expires_at');
      const now = Date.now();
      
      const request = index.openCursor(IDBKeyRange.upperBound(now));
      
      return new Promise((resolve, reject) => {
        const deletedCount = { count: 0 };
        
        request.onsuccess = (event) => {
          const cursor = event.target.result;
          if (cursor) {
            cursor.remove();
            deletedCount.count++;
            cursor.continue();
          } else {
            resolve(deletedCount.count);
          }
        };
        
        request.onerror = () => reject(request.error);
      });
    } catch (error) {
      console.error('[KingswayDB] Failed to invalidate expired entries:', error);
      return 0;
    }
  }

  /**
   * Clear user-scoped data on logout
   */
  async function clearUserData(userId) {
    await initialize();
    
    try {
      const storesToClear = [
        'student_directory_cache',
        'staff_directory_cache',
        'class_list_cache',
        'admission_queue_cache',
        'attendance_roster_cache',
        'dashboard_cache',
        'offline_drafts',
        'sync_outbox',
        'notification_inbox',
        'pending_uploads'
      ];

      for (const storeName of storesToClear) {
        const store = getStore(storeName, 'readwrite');
        const index = store.index('user_id');
        const request = index.openCursor(IDBKeyRange.only(userId));
        
        await new Promise((resolve, reject) => {
          request.onsuccess = (event) => {
            const cursor = event.target.result;
            if (cursor) {
              cursor.remove();
              cursor.continue();
            } else {
              resolve();
            }
          };
          
          request.onerror = () => reject(request.error);
        });
      }

      console.log('[KingswayDB] Cleared user data for user:', userId);
      return true;
    } catch (error) {
      console.error('[KingswayDB] Failed to clear user data:', error);
      return false;
    }
  }

  /**
   * Generate scope hash for user/role combination
   */
  function generateScopeHash(userId, roleId) {
    const data = `${userId}:${roleId}`;
    let hash = 0;
    for (let i = 0; i < data.length; i++) {
      const char = data.charCodeAt(i);
      hash = ((hash << 5) - hash) + char;
      hash = hash & hash; // Convert to 32bit integer
    }
    return hash.toString(16);
  }

  /**
   * Get database statistics
   */
  async function getStats() {
    await initialize();
    
    const stats = {};
    
    for (const storeName of Object.keys(schema)) {
      try {
        const store = getStore(storeName);
        const countRequest = store.count();
        
        stats[storeName] = await new Promise((resolve, reject) => {
          countRequest.onsuccess = () => resolve(countRequest.result);
          countRequest.onerror = () => reject(countRequest.error);
        });
      } catch (error) {
        stats[storeName] = { error: error.message };
      }
    }
    
    return stats;
  }

  // Public API
  return {
    initialize,
    get,
    getAll,
    put,
    add,
    remove,
    clear,
    getByIndex,
    getByIndexRange,
    getCached,
    setCached,
    invalidateExpired,
    clearUserData,
    getStats,
    schema
  };

})();

// Export for use in other modules
if (typeof window !== 'undefined') {
  window.KingswayDB = KingswayDB;
}
