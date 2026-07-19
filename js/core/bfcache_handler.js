/**
 * bfcache-Safe Lifecycle Handler
 * 
 * Handles pageshow/pagehide events for proper page lifecycle management.
 * Ensures that pages cached in bfcache (back-forward cache) work correctly
 * with the new storage and synchronization infrastructure.
 * 
 * Key behaviors:
 * - Detects when page is restored from bfcache (pageshow event with persisted=true)
 * - Re-initializes components when page is restored
 * - Pauses ongoing operations when page is hidden (pagehide event)
 * - Cleans up resources when page is discarded (pagehide event with persisted=false)
 */

const BFCacheHandler = {
  initialized: false,
  state: {
    isPersisted: false,
    hiddenTime: null,
    operationsPaused: false
  },

  /**
   * Initialize bfcache lifecycle handling
   */
  initialize() {
    if (this.initialized) return;
    this.initialized = true;

    console.log('[BFCacheHandler] Initializing...');

    // Listen for pageshow event (when page is shown, including from bfcache)
    window.addEventListener('pageshow', (event) => this.handlePageShow(event));

    // Listen for pagehide event (when page is hidden, including for bfcache)
    window.addEventListener('pagehide', (event) => this.handlePageHide(event));

    // Also listen for visibilitychange as fallback for browsers without bfcache
    document.addEventListener('visibilitychange', () => this.handleVisibilityChange());

    console.log('[BFCacheHandler] Initialized');
  },

  /**
   * Handle pageshow event
   * @param {PageTransitionEvent} event - The pageshow event
   */
  handlePageShow(event) {
    this.state.isPersisted = event.persisted;
    
    if (event.persisted) {
      console.log('[BFCacheHandler] Page restored from bfcache');
      this.onPageRestored();
    } else {
      console.log('[BFCacheHandler] Page loaded normally (not from bfcache)');
      this.onPageLoad();
    }
  },

  /**
   * Handle pagehide event
   * @param {PageTransitionEvent} event - The pagehide event
   */
  handlePageHide(event) {
    this.state.hiddenTime = Date.now();
    
    if (event.persisted) {
      console.log('[BFCacheHandler] Page entering bfcache');
      this.onPageEnterBFCache();
    } else {
      console.log('[BFCacheHandler] Page being discarded (not entering bfcache)');
      this.onPageDiscard();
    }
  },

  /**
   * Handle visibilitychange event (fallback for browsers without bfcache)
   */
  handleVisibilityChange() {
    if (document.hidden) {
      console.log('[BFCacheHandler] Page hidden (visibilitychange)');
      this.onPageHidden();
    } else {
      console.log('[BFCacheHandler] Page visible (visibilitychange)');
      this.onPageVisible();
    }
  },

  /**
   * Called when page is loaded normally (not from bfcache)
   */
  onPageLoad() {
    // Normal initialization - already handled by existing init code
    console.log('[BFCacheHandler] Normal page load, no special action needed');
  },

  /**
   * Called when page is restored from bfcache
   */
  onPageRestored() {
    console.log('[BFCacheHandler] Re-initializing components after bfcache restore');
    
    // Re-initialize DataStore subscriptions if needed
    if (typeof DataStore !== 'undefined') {
      console.log('[BFCacheHandler] DataStore is available, no re-init needed');
    }
    
    // Resume SyncQueue if it was paused
    if (typeof SyncQueue !== 'undefined' && this.state.operationsPaused) {
      console.log('[BFCacheHandler] Resuming SyncQueue');
      SyncQueue.resume();
      this.state.operationsPaused = false;
    }
    
    // Refresh session if needed
    if (typeof SessionManager !== 'undefined') {
      SessionManager.checkSession().catch(error => {
        console.warn('[BFCacheHandler] Session check failed:', error);
      });
    }
    
    // Update connectivity status
    if (typeof ConnectivityManager !== 'undefined') {
      ConnectivityManager.updateStatus();
    }
  },

  /**
   * Called when page is entering bfcache
   */
  onPageEnterBFCache() {
    console.log('[BFCacheHandler] Pausing operations for bfcache');
    
    // Pause SyncQueue to prevent operations while in bfcache
    if (typeof SyncQueue !== 'undefined') {
      console.log('[BFCacheHandler] Pausing SyncQueue');
      SyncQueue.pause();
      this.state.operationsPaused = true;
    }
    
    // Save any pending drafts
    if (typeof DataStore !== 'undefined') {
      console.log('[BFCacheHandler] DataStore will persist in bfcache');
    }
  },

  /**
   * Called when page is being discarded (not entering bfcache)
   */
  onPageDiscard() {
    console.log('[BFCacheHandler] Cleaning up before page discard');
    
    // Ensure SyncQueue is stopped
    if (typeof SyncQueue !== 'undefined') {
      console.log('[BFCacheHandler] Stopping SyncQueue');
      SyncQueue.stop();
    }
    
    // Unsubscribe from DataStore events
    if (typeof DataStore !== 'undefined' && typeof DataStore.unsubscribeAll === 'function') {
      console.log('[BFCacheHandler] Unsubscribing from DataStore events');
      DataStore.unsubscribeAll();
    }
    
    // Unsubscribe from conflict events
    if (typeof ConflictManager !== 'undefined' && typeof ConflictManager.unsubscribeAll === 'function') {
      console.log('[BFCacheHandler] Unsubscribing from conflict events');
      ConflictManager.unsubscribeAll();
    }
  },

  /**
   * Called when page is hidden (visibilitychange fallback)
   */
  onPageHidden() {
    console.log('[BFCacheHandler] Page hidden, pausing operations');
    
    // Pause SyncQueue
    if (typeof SyncQueue !== 'undefined') {
      SyncQueue.pause();
      this.state.operationsPaused = true;
    }
  },

  /**
   * Called when page is visible (visibilitychange fallback)
   */
  onPageVisible() {
    console.log('[BFCacheHandler] Page visible, resuming operations');
    
    // Resume SyncQueue
    if (typeof SyncQueue !== 'undefined' && this.state.operationsPaused) {
      SyncQueue.resume();
      this.state.operationsPaused = false;
    }
    
    // Update connectivity status
    if (typeof ConnectivityManager !== 'undefined') {
      ConnectivityManager.updateStatus();
    }
  },

  /**
   * Get current state
   */
  getState() {
    return { ...this.state };
  },

  /**
   * Check if page is currently persisted in bfcache
   */
  isPersisted() {
    return this.state.isPersisted;
  },

  /**
   * Get time since page was hidden
   */
  getTimeSinceHidden() {
    if (!this.state.hiddenTime) return null;
    return Date.now() - this.state.hiddenTime;
  }
};

// Auto-initialize when DOM is ready
if (typeof document !== 'undefined') {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => BFCacheHandler.initialize());
  } else {
    BFCacheHandler.initialize();
  }
}
