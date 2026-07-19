/**
 * Speculative Loading Manager
 * 
 * Prefetches likely navigation paths and data during idle time.
 * Improves perceived performance by preloading data before user requests it.
 * 
 * Features:
 * - Predicts likely next pages based on current page and user role
 * - Prefetches data during idle time (requestIdleCallback)
 * - Respects connectivity and battery status
 * - Uses DataStore for caching prefetched data
 * - Configurable prefetch rules per module
 */

const SpeculativeLoader = {
  initialized: false,
  state: {
    enabled: true,
    idleTimeMs: 2000, // Minimum idle time before prefetching
    lastNavigationTime: null,
    prefetchQueue: [],
    prefetching: false
  },

  // Prefetch rules: current page -> likely next pages
  prefetchRules: {
    // Admissions workflow
    'manage_students_admissions': [
      { page: 'new_applications', probability: 0.8 },
      { page: 'manage_students', probability: 0.3 }
    ],
    'new_applications': [
      { page: 'manage_students_admissions', probability: 0.9 }
    ],
    
    // Students workflow
    'all_students': [
      { page: 'student_profile', probability: 0.4 },
      { page: 'student_fees', probability: 0.3 },
      { page: 'student_attendance', probability: 0.2 }
    ],
    
    // Attendance workflow
    'mark_attendance': [
      { page: 'view_attendance', probability: 0.7 },
      { page: 'attendance_reports', probability: 0.3 }
    ],
    
    // Finance workflow
    'manage_finance': [
      { page: 'financial_reports', probability: 0.5 },
      { page: 'manage_fees', probability: 0.4 }
    ],
    
    // Staff workflow
    'manage_staff': [
      { page: 'staff_profile', probability: 0.5 },
      { page: 'staff_attendance', probability: 0.3 }
    ]
  },

  // Data prefetch rules: endpoint -> DataStore key
  dataPrefetchRules: {
    'manage_students_admissions': [
      { endpoint: '/admission/queues', key: 'admissions', ttl: 60000 }
    ],
    'all_students': [
      { endpoint: '/api/students', key: 'students', ttl: 300000 }
    ],
    'mark_attendance': [
      { endpoint: '/students/transport-meta', key: 'transport_meta', ttl: 600000 }
    ]
  },

  /**
   * Initialize speculative loading
   */
  initialize() {
    if (this.initialized) return;
    this.initialized = true;

    console.log('[SpeculativeLoader] Initializing...');

    // Check if speculative loading should be enabled
    this.state.enabled = this.shouldEnable();

    if (!this.state.enabled) {
      console.log('[SpeculativeLoader] Disabled (battery saver or slow connection)');
      return;
    }

    // Listen for navigation events
    this.setupNavigationListeners();

    // Start idle detection
    this.startIdleDetection();

    console.log('[SpeculativeLoader] Initialized');
  },

  /**
   * Check if speculative loading should be enabled
   */
  shouldEnable() {
    // Disable if battery saver is on
    if (navigator.getBattery) {
      navigator.getBattery().then(battery => {
        if (battery.savingMode) {
          console.log('[SpeculativeLoader] Battery saver mode detected, disabling');
          this.state.enabled = false;
        }
      });
    }

    // Disable if connection is slow (2G or less)
    if (navigator.connection) {
      const connection = navigator.connection;
      if (connection.effectiveType === '2g' || connection.effectiveType === 'slow-2g') {
        console.log('[SpeculativeLoader] Slow connection detected, disabling');
        return false;
      }
    }

    return true;
  },

  /**
   * Setup navigation event listeners
   */
  setupNavigationListeners() {
    // Track page navigation
    window.addEventListener('popstate', () => this.onNavigation());
    
    // Also track when user navigates via link clicks
    document.addEventListener('click', (event) => {
      const link = event.target.closest('a');
      if (link && link.href && link.href.includes(window.location.origin)) {
        this.onNavigation();
      }
    });
  },

  /**
   * Handle navigation event
   */
  onNavigation() {
    this.state.lastNavigationTime = Date.now();
    this.state.prefetchQueue = [];
    
    // Schedule prefetch after navigation
    this.schedulePrefetch();
  },

  /**
   * Start idle detection
   */
  startIdleDetection() {
    let idleTimer = null;
    let lastActivity = Date.now();

    const resetIdleTimer = () => {
      lastActivity = Date.now();
      if (idleTimer) {
        clearTimeout(idleTimer);
      }
      idleTimer = setTimeout(() => this.onIdle(), this.state.idleTimeMs);
    };

    // Track user activity
    ['mousedown', 'keydown', 'scroll', 'touchstart'].forEach(event => {
      document.addEventListener(event, resetIdleTimer, { passive: true });
    });

    // Start initial timer
    resetIdleTimer();
  },

  /**
   * Handle idle state
   */
  onIdle() {
    if (!this.state.enabled) return;
    if (this.state.prefetching) return;

    console.log('[SpeculativeLoader] User idle, starting prefetch');
    this.processPrefetchQueue();
  },

  /**
   * Schedule prefetch using requestIdleCallback
   */
  schedulePrefetch() {
    if (!this.state.enabled) return;

    if ('requestIdleCallback' in window) {
      requestIdleCallback(() => this.processPrefetchQueue(), { timeout: 5000 });
    } else {
      // Fallback: setTimeout
      setTimeout(() => this.processPrefetchQueue(), 1000);
    }
  },

  /**
   * Process prefetch queue
   */
  async processPrefetchQueue() {
    if (!this.state.enabled) return;
    if (this.state.prefetching) return;

    this.state.prefetching = true;

    try {
      const currentPage = this.getCurrentPage();
      console.log('[SpeculativeLoader] Prefetching for page:', currentPage);

      // Prefetch likely next pages
      const rules = this.prefetchRules[currentPage] || [];
      for (const rule of rules) {
        if (Math.random() < rule.probability) {
          await this.prefetchPage(rule.page);
        }
      }

      // Prefetch data for current page
      const dataRules = this.dataPrefetchRules[currentPage] || [];
      for (const rule of dataRules) {
        await this.prefetchData(rule);
      }

      console.log('[SpeculativeLoader] Prefetch complete');
    } catch (error) {
      console.error('[SpeculativeLoader] Prefetch failed:', error);
    } finally {
      this.state.prefetching = false;
    }
  },

  /**
   * Get current page identifier
   */
  getCurrentPage() {
    const urlParams = new URLSearchParams(window.location.search);
    const route = urlParams.get('route') || 'dashboard';
    return route;
  },

  /**
   * Prefetch a page
   */
  async prefetchPage(page) {
    try {
      const url = `${window.APP_BASE || ''}/home.php?route=${page}`;
      
      // Use prefetch hint
      const link = document.createElement('link');
      link.rel = 'prefetch';
      link.href = url;
      document.head.appendChild(link);
      
      console.log('[SpeculativeLoader] Prefetched page:', page);
    } catch (error) {
      console.warn('[SpeculativeLoader] Failed to prefetch page:', page, error);
    }
  },

  /**
   * Prefetch data
   */
  async prefetchData(rule) {
    if (typeof DataStore === 'undefined') {
      console.warn('[SpeculativeLoader] DataStore not available, skipping data prefetch');
      return;
    }

    try {
      // Check if data is already cached
      const cached = await DataStore.get(rule.key);
      if (cached) {
        console.log('[SpeculativeLoader] Data already cached:', rule.key);
        return;
      }

      // Prefetch using DataStore
      await DataStore.get(rule.key, {
        strategy: 'network-first',
        ttl: rule.ttl,
        endpoint: rule.endpoint,
        forceRefresh: true
      });

      console.log('[SpeculativeLoader] Prefetched data:', rule.key);
    } catch (error) {
      console.warn('[SpeculativeLoader] Failed to prefetch data:', rule.key, error);
    }
  },

  /**
   * Add custom prefetch rule
   */
  addPrefetchRule(page, rules) {
    this.prefetchRules[page] = rules;
  },

  /**
   * Add custom data prefetch rule
   */
  addDataPrefetchRule(page, rules) {
    this.dataPrefetchRules[page] = rules;
  },

  /**
   * Enable/disable speculative loading
   */
  setEnabled(enabled) {
    this.state.enabled = enabled;
    console.log('[SpeculativeLoader]', enabled ? 'Enabled' : 'Disabled');
  },

  /**
   * Get current state
   */
  getState() {
    return { ...this.state };
  }
};

// Auto-initialize when DOM is ready
if (typeof document !== 'undefined') {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => SpeculativeLoader.initialize());
  } else {
    SpeculativeLoader.initialize();
  }
}
