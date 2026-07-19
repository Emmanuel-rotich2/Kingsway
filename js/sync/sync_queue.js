/**
 * Offline Sync Queue
 * 
 * Manages offline operations queue for synchronization when connection is restored.
 * Follows the storage ownership matrix for sync_outbox store.
 */

const SyncQueue = (function() {
  'use strict';

  let isProcessing = false;
  let processingTimer = null;
  let subscribers = new Set();

  /**
   * Add operation to sync queue
   */
  async function addOperation(operation) {
    const queueItem = {
      id: generateUUID(),
      operation_id: generateUUID(),
      module: operation.module,
      endpoint: operation.endpoint,
      method: operation.method || 'POST',
      payload: operation.payload || {},
      entity_type: operation.entity_type,
      entity_id: operation.entity_id,
      created_at: Date.now(),
      updated_at: Date.now(),
      retry_count: 0,
      last_error: null,
      status: 'pending',
      user_id: getCurrentUserId(),
      school_id: getSchoolId(),
      idempotency_key: generateUUID(),
      dependency_ids: operation.dependency_ids || [],
      priority: operation.priority || 5
    };

    try {
      await KingswayDB.add('sync_outbox', queueItem);
      console.log('[SyncQueue] Operation added to queue:', queueItem.operation_id);
      emit('OPERATION_QUEUED', queueItem);
      
      // Register background sync if supported
      if ('serviceWorker' in navigator && 'sync' in ServiceWorkerRegistration.prototype) {
        const registration = await navigator.serviceWorker.getRegistration();
        if (registration) {
          await registration.sync.register('sync-outbox');
        }
      }
      
      return queueItem;
    } catch (error) {
      console.error('[SyncQueue] Failed to add operation to queue:', error);
      throw error;
    }
  }

  /**
   * Get pending operations
   */
  async function getPendingOperations() {
    try {
      const operations = await KingswayDB.getByIndex('sync_outbox', 'status', 'pending');
      
      // Sort by priority (higher = more important) and created_at (older first)
      return operations.sort((a, b) => {
        if (a.priority !== b.priority) {
          return b.priority - a.priority;
        }
        return a.created_at - b.created_at;
      });
    } catch (error) {
      console.error('[SyncQueue] Failed to get pending operations:', error);
      return [];
    }
  }

  /**
   * Process pending operations
   */
  async function processQueue() {
    if (isProcessing) {
      console.log('[SyncQueue] Queue already processing');
      return;
    }

    if (!navigator.onLine) {
      console.log('[SyncQueue] Offline, skipping queue processing');
      return;
    }

    isProcessing = true;
    console.log('[SyncQueue] Processing sync queue...');

    try {
      const operations = await getPendingOperations();
      console.log('[SyncQueue] Found', operations.length, 'pending operations');

      for (const operation of operations) {
        await processOperation(operation);
      }

      emit('QUEUE_PROCESSED', { count: operations.length });
    } catch (error) {
      console.error('[SyncQueue] Failed to process queue:', error);
      emit('QUEUE_ERROR', { error });
    } finally {
      isProcessing = false;
    }
  }

  /**
   * Process single operation
   */
  async function processOperation(operation) {
    console.log('[SyncQueue] Processing operation:', operation.operation_id);

    try {
      // Update status to processing
      await updateOperationStatus(operation.id, 'processing');

      // Check dependencies
      if (operation.dependency_ids && operation.dependency_ids.length > 0) {
        const dependenciesResolved = await checkDependencies(operation.dependency_ids);
        if (!dependenciesResolved) {
          await updateOperationStatus(operation.id, 'pending');
          console.log('[SyncQueue] Dependencies not resolved, skipping:', operation.operation_id);
          return;
        }
      }

      // Execute operation via API client
      const response = await executeOperation(operation);

      if (response.success) {
        // Operation succeeded
        await markOperationSuccess(operation.id);
        console.log('[SyncQueue] Operation succeeded:', operation.operation_id);
        emit('OPERATION_SUCCESS', { operation, response });
      } else {
        // Operation failed
        await markOperationFailed(operation.id, response.message);
        console.error('[SyncQueue] Operation failed:', operation.operation_id, response.message);
        emit('OPERATION_FAILED', { operation, error: response.message });
      }
    } catch (error) {
      // Operation errored
      await markOperationFailed(operation.id, error.message);
      console.error('[SyncQueue] Operation errored:', operation.operation_id, error);
      emit('OPERATION_ERROR', { operation, error });
    }
  }

  /**
   * Execute operation via centralized API
   */
  async function executeOperation(operation) {
    const { endpoint, method, payload } = operation;

    // Use centralized API from /js/api.js
    if (typeof window.API !== 'undefined' && typeof window.API.apiCall === 'function') {
      return await window.API.apiCall(endpoint, method, payload);
    }

    throw new Error('Centralized API (window.API.apiCall) not available');
  }

  /**
   * Check if dependencies are resolved
   */
  async function checkDependencies(dependencyIds) {
    for (const depId of dependencyIds) {
      const dep = await KingswayDB.get('sync_outbox', depId);
      if (dep && dep.status !== 'success') {
        return false;
      }
    }
    return true;
  }

  /**
   * Update operation status
   */
  async function updateOperationStatus(id, status) {
    const operation = await KingswayDB.get('sync_outbox', id);
    if (operation) {
      operation.status = status;
      operation.updated_at = Date.now();
      await KingswayDB.put('sync_outbox', operation);
    }
  }

  /**
   * Mark operation as successful
   */
  async function markOperationSuccess(id) {
    const operation = await KingswayDB.get('sync_outbox', id);
    if (operation) {
      operation.status = 'success';
      operation.updated_at = Date.now();
      await KingswayDB.put('sync_outbox', operation);
      
      // Remove from queue after successful sync (cleanup)
      setTimeout(() => {
        KingswayDB.delete('sync_outbox', id);
      }, 60000); // Keep for 1 minute for debugging
    }
  }

  /**
   * Mark operation as failed
   */
  async function markOperationFailed(id, errorMessage) {
    const operation = await KingswayDB.get('sync_outbox', id);
    if (operation) {
      operation.status = 'failed';
      operation.last_error = errorMessage;
      operation.retry_count = (operation.retry_count || 0) + 1;
      operation.updated_at = Date.now();
      
      // Max retry count: 5
      if (operation.retry_count >= 5) {
        operation.status = 'permanently_failed';
      } else {
        // Reset to pending for retry
        operation.status = 'pending';
      }
      
      await KingswayDB.put('sync_outbox', operation);
    }
  }

  /**
   * Get queue statistics
   */
  async function getQueueStats() {
    try {
      const allOperations = await KingswayDB.getAll('sync_outbox');
      
      const stats = {
        total: allOperations.length,
        pending: 0,
        processing: 0,
        success: 0,
        failed: 0,
        permanently_failed: 0,
        byModule: {}
      };

      for (const op of allOperations) {
        stats[op.status]++;
        
        if (!stats.byModule[op.module]) {
          stats.byModule[op.module] = 0;
        }
        stats.byModule[op.module]++;
      }

      return stats;
    } catch (error) {
      console.error('[SyncQueue] Failed to get queue stats:', error);
      return null;
    }
  }

  /**
   * Clear failed operations
   */
  async function clearFailedOperations() {
    try {
      const failedOps = await KingswayDB.getByIndex('sync_outbox', 'status', 'failed');
      
      for (const op of failedOps) {
        await KingswayDB.delete('sync_outbox', op.id);
      }
      
      console.log('[SyncQueue] Cleared', failedOps.length, 'failed operations');
      return failedOps.length;
    } catch (error) {
      console.error('[SyncQueue] Failed to clear failed operations:', error);
      return 0;
    }
  }

  /**
   * Retry failed operations
   */
  async function retryFailedOperations() {
    try {
      const failedOps = await KingswayDB.getByIndex('sync_outbox', 'status', 'failed');
      
      for (const op of failedOps) {
        op.status = 'pending';
        op.retry_count = 0;
        op.last_error = null;
        await KingswayDB.put('sync_outbox', op);
      }
      
      console.log('[SyncQueue] Reset', failedOps.length, 'failed operations for retry');
      
      // Trigger queue processing
      await processQueue();
      
      return failedOps.length;
    } catch (error) {
      console.error('[SyncQueue] Failed to retry failed operations:', error);
      return 0;
    }
  }

  /**
   * Subscribe to queue events
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
          console.error('[SyncQueue] Event callback error:', error);
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

  /**
   * Get school ID
   */
  function getSchoolId() {
    // TODO: Implement when multi-tenant support is added
    return 1; // Default school ID
  }

  /**
   * Pause queue processing
   */
  function pause() {
    isProcessing = false;
    processingTimer = null;
    console.log('[SyncQueue] Queue paused');
  }

  /**
   * Resume queue processing
   */
  function resume() {
    if (!isProcessing) {
      isProcessing = true;
      processQueue();
      console.log('[SyncQueue] Queue resumed');
    }
  }

  /**
   * Stop queue processing
   */
  function stop() {
    isProcessing = false;
    processingTimer = null;
    console.log('[SyncQueue] Queue stopped');
  }

  // Public API
  return {
    addOperation,
    getPendingOperations,
    processQueue,
    getQueueStats,
    clearFailedOperations,
    retryFailedOperations,
    pause,
    resume,
    stop,
    subscribe
  };

})();

// Export for use in other modules
if (typeof window !== 'undefined') {
  window.SyncQueue = SyncQueue;
}
