/**
 * Conflict Manager
 * 
 * Handles optimistic concurrency conflicts between offline and server data.
 * Provides user-mediated conflict resolution UI and logic.
 */

const ConflictManager = (function() {
  'use strict';

  let subscribers = new Set();

  /**
   * Detect conflict between local and server versions
   */
  async function detectConflict(localData, serverData, entityType, entityId) {
    // Check if versions differ
    if (localData.version && serverData.version && localData.version !== serverData.version) {
      return {
        type: 'version',
        detected_at: Date.now(),
        resolved_at: null,
        resolution: null
      };
    }

    // Check if updated_at differs
    if (localData.updated_at && serverData.updated_at && localData.updated_at !== serverData.updated_at) {
      return {
        type: 'field',
        detected_at: Date.now(),
        resolved_at: null,
        resolution: null
      };
    }

    // Check for deleted entity
    if (serverData.deleted_at && !localData.deleted_at) {
      return {
        type: 'delete',
        detected_at: Date.now(),
        resolved_at: null,
        resolution: null
      };
    }

    return null;
  }

  /**
   * Store conflict for user resolution
   */
  async function storeConflict(conflict) {
    const conflictRecord = {
      id: generateUUID(),
      operation_id: conflict.operation_id,
      entity_type: conflict.entity_type,
      entity_id: conflict.entity_id,
      server_version: conflict.serverData,
      local_version: conflict.localData,
      conflict_type: conflict.type,
      detected_at: Date.now(),
      resolved_at: null,
      resolution: null,
      user_id: getCurrentUserId(),
      metadata: conflict.metadata || {}
    };

    try {
      await KingswayDB.add('sync_conflicts', conflictRecord);
      console.log('[ConflictManager] Conflict stored:', conflictRecord.id);
      emit('CONFLICT_DETECTED', conflictRecord);
      return conflictRecord;
    } catch (error) {
      console.error('[ConflictManager] Failed to store conflict:', error);
      throw error;
    }
  }

  /**
   * Get unresolved conflicts for user
   */
  async function getUnresolvedConflicts() {
    try {
      const allConflicts = await KingswayDB.getAll('sync_conflicts');
      
      return allConflicts
        .filter(conflict => conflict.resolved_at === null)
        .filter(conflict => conflict.user_id === getCurrentUserId())
        .sort((a, b) => b.detected_at - a.detected_at); // Most recent first
    } catch (error) {
      console.error('[ConflictManager] Failed to get conflicts:', error);
      return [];
    }
  }

  /**
   * Resolve conflict with user's choice
   */
  async function resolveConflict(conflictId, resolution, mergedData = null) {
    try {
      const conflict = await KingswayDB.get('sync_conflicts', conflictId);
      
      if (!conflict) {
        throw new Error('Conflict not found');
      }

      conflict.resolved_at = Date.now();
      conflict.resolution = resolution;

      if (resolution === 'merge' && mergedData) {
        conflict.merged_version = mergedData;
      }

      await KingswayDB.put('sync_conflicts', conflict);
      
      console.log('[ConflictManager] Conflict resolved:', conflictId, resolution);
      emit('CONFLICT_RESOLVED', { conflict, resolution, mergedData });
      
      return conflict;
    } catch (error) {
      console.error('[ConflictManager] Failed to resolve conflict:', error);
      throw error;
    }
  }

  /**
   * Apply resolution to server
   */
  async function applyResolution(conflict) {
    const { resolution, merged_version, local_version, server_version, entity_type, entity_id } = conflict;

    switch (resolution) {
      case 'keep_server':
        // Accept server version, discard local changes
        console.log('[ConflictManager] Keeping server version for', entity_type, entity_id);
        // No action needed - server version is already authoritative
        break;

      case 'keep_local':
        // Re-apply local changes to server
        console.log('[ConflictManager] Re-applying local version for', entity_type, entity_id);
        return await reapplyLocalChanges(entity_type, entity_id, local_version);

      case 'merge':
        // Apply merged version to server
        console.log('[ConflictManager] Applying merged version for', entity_type, entity_id);
        return await applyMergedChanges(entity_type, entity_id, merged_version);

      default:
        throw new Error(`Unknown resolution: ${resolution}`);
    }
  }

  /**
   * Re-apply local changes to server
   */
  async function reapplyLocalChanges(entityType, entityId, localData) {
    const endpoint = getEndpointForEntity(entityType, entityId);
    
    try {
      // Use centralized API from /js/api.js
      if (typeof window.API !== 'undefined' && typeof window.API.apiCall === 'function') {
        const response = await window.API.apiCall(endpoint, 'PUT', localData);
        
        if (response.success) {
          console.log('[ConflictManager] Local changes reapplied successfully');
          return response;
        } else {
          throw new Error(response.message || 'Failed to reapply local changes');
        }
      }
      
      throw new Error('Centralized API (window.API.apiCall) not available');
    } catch (error) {
      console.error('[ConflictManager] Failed to reapply local changes:', error);
      throw error;
    }
  }

  /**
   * Apply merged changes to server
   */
  async function applyMergedChanges(entityType, entityId, mergedData) {
    const endpoint = getEndpointForEntity(entityType, entityId);
    
    try {
      // Use centralized API from /js/api.js
      if (typeof window.API !== 'undefined' && typeof window.API.apiCall === 'function') {
        const response = await window.API.apiCall(endpoint, 'PUT', mergedData);
        
        if (response.success) {
          console.log('[ConflictManager] Merged changes applied successfully');
          return response;
        } else {
          throw new Error(response.message || 'Failed to apply merged changes');
        }
      }
      
      throw new Error('Centralized API (window.API.apiCall) not available');
    } catch (error) {
      console.error('[ConflictManager] Failed to apply merged changes:', error);
      throw error;
    }
  }

  /**
   * Get endpoint for entity type
   */
  function getEndpointForEntity(entityType, entityId) {
    const endpoints = {
      'student': `/students/${entityId}`,
      'attendance': `/attendance/${entityId}`,
      'admission': `/admissions/${entityId}`,
      'class': `/classes/${entityId}`,
      'staff': `/staff/${entityId}`
    };

    return endpoints[entityType] || `/${entityType}/${entityId}`;
  }

  /**
   * Get conflict statistics
   */
  async function getConflictStats() {
    try {
      const allConflicts = await KingswayDB.getAll('sync_conflicts');
      
      const stats = {
        total: allConflicts.length,
        unresolved: 0,
        resolved: 0,
        by_type: {
          version: 0,
          field: 0,
          delete: 0
        },
        by_resolution: {
          keep_server: 0,
          keep_local: 0,
          merge: 0
        }
      };

      for (const conflict of allConflicts) {
        if (conflict.resolved_at) {
          stats.resolved++;
          if (stats.by_resolution[conflict.resolution]) {
            stats.by_resolution[conflict.resolution]++;
          }
        } else {
          stats.unresolved++;
        }
        
        if (stats.by_type[conflict.conflict_type]) {
          stats.by_type[conflict.conflict_type]++;
        }
      }

      return stats;
    } catch (error) {
      console.error('[ConflictManager] Failed to get conflict stats:', error);
      return null;
    }
  }

  /**
   * Clear old resolved conflicts
   */
  async function clearOldConflicts(daysOld = 30) {
    try {
      const allConflicts = await KingswayDB.getAll('sync_conflicts');
      const cutoffDate = Date.now() - (daysOld * 24 * 60 * 60 * 1000);
      
      let clearedCount = 0;
      
      for (const conflict of allConflicts) {
        if (conflict.resolved_at && conflict.resolved_at < cutoffDate) {
          await KingswayDB.delete('sync_conflicts', conflict.id);
          clearedCount++;
        }
      }
      
      console.log('[ConflictManager] Cleared', clearedCount, 'old conflicts');
      return clearedCount;
    } catch (error) {
      console.error('[ConflictManager] Failed to clear old conflicts:', error);
      return 0;
    }
  }

  /**
   * Subscribe to conflict events
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
          console.error('[ConflictManager] Event callback error:', error);
        }
      }
    });
  }

  /**
   * Generate UUID
   */
  function generateUUID() {
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
      const r = Math.random() * 16 | 0;
      const v = c === 'x' ? r : (r & 0x3 | 0x8);
      return v.toString(16);
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
    detectConflict,
    storeConflict,
    getUnresolvedConflicts,
    resolveConflict,
    applyResolution,
    getConflictStats,
    clearOldConflicts,
    subscribe,
    unsubscribeAll: () => subscribers.clear()
  };

})();

// Export for use in other modules
if (typeof window !== 'undefined') {
  window.ConflictManager = ConflictManager;
}
