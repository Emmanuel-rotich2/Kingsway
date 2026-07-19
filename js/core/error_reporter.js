/**
 * Client Error Reporting and Telemetry
 * 
 * Captures client-side errors, performance metrics, and telemetry data.
 * Reports errors to the server for monitoring and debugging.
 * 
 * Features:
- Capture JavaScript errors and unhandled rejections
- Track performance metrics (page load, API response times)
- Collect user context (browser, user agent, screen size)
- Batch error reporting to reduce network overhead
- Respect user privacy (no PII collected by default)
- Respect offline status (queue errors when offline)
 */

const ErrorReporter = {
  initialized: false,
  state: {
    enabled: true,
    // Relative to API_BASE_URL (which already includes /api), so apiCall() builds
    // /Kingsway/api/telemetry/... — NOT /Kingsway/api/api/telemetry/...
    endpoint: '/telemetry/errors',
    telemetryEndpoint: '/telemetry/data',
    batchSize: 10,
    flushInterval: 30000, // 30 seconds
    errorQueue: [],
    telemetryQueue: [],
    sessionId: null,
    userId: null
  },

  /**
   * Initialize error reporting
   */
  initialize() {
    if (this.initialized) return;
    this.initialized = true;

    console.log('[ErrorReporter] Initializing...');

    // Generate session ID
    this.state.sessionId = this.generateSessionId();

    // Get user ID if available
    this.state.userId = this.getUserId();

    // Setup error handlers
    this.setupErrorHandlers();

    // Setup performance monitoring
    this.setupPerformanceMonitoring();

    // Start periodic flush
    this.startPeriodicFlush();

    console.log('[ErrorReporter] Initialized');
  },

  /**
   * Setup error handlers
   */
  setupErrorHandlers() {
    // Capture JavaScript errors
    window.addEventListener('error', (event) => {
      this.captureError({
        message: event.message,
        filename: event.filename,
        lineno: event.lineno,
        colno: event.colno,
        error: event.error
      });
    });

    // Capture unhandled promise rejections
    window.addEventListener('unhandledrejection', (event) => {
      this.captureError({
        message: 'Unhandled Promise Rejection',
        reason: event.reason,
        promise: event.promise
      });
    });

    // Capture resource loading errors
    window.addEventListener('error', (event) => {
      if (event.target !== window) {
        this.captureError({
          message: 'Resource Load Error',
          target: event.target.tagName,
          src: event.target.src || event.target.href
        });
      }
    }, true);
  },

  /**
   * Setup performance monitoring
   */
  setupPerformanceMonitoring() {
    // Track page load time
    if ('performance' in window && 'timing' in performance) {
      window.addEventListener('load', () => {
        setTimeout(() => {
          const timing = performance.timing;
          const pageLoadTime = timing.loadEventEnd - timing.navigationStart;
          
          this.recordTelemetry('page_load', {
            loadTime: pageLoadTime,
            domReady: timing.domContentLoadedEventEnd - timing.navigationStart,
            firstPaint: this.getFirstPaintTime()
          });
        }, 0);
      });
    }

    // Track Navigation Timing API
    if ('PerformanceObserver' in window) {
      try {
        const observer = new PerformanceObserver((list) => {
          for (const entry of list.getEntries()) {
            if (entry.entryType === 'navigation') {
              this.recordTelemetry('navigation', {
                name: entry.name,
                duration: entry.duration,
                transferSize: entry.transferSize
              });
            }
          }
        });
        observer.observe({ entryTypes: ['navigation'] });
      } catch (error) {
        console.warn('[ErrorReporter] PerformanceObserver not available');
      }
    }
  },

  /**
   * Get first paint time
   */
  getFirstPaintTime() {
    if ('performance' in window && 'getEntriesByType' in performance) {
      const paintEntries = performance.getEntriesByType('paint');
      const firstPaint = paintEntries.find(entry => entry.name === 'first-paint');
      return firstPaint ? firstPaint.startTime : null;
    }
    return null;
  },

  /**
   * Capture an error
   */
  captureError(errorData) {
    if (!this.state.enabled) return;

    const error = {
      id: this.generateId(),
      sessionId: this.state.sessionId,
      userId: this.state.userId,
      timestamp: Date.now(),
      url: window.location.href,
      userAgent: navigator.userAgent,
      screen: `${window.screen.width}x${window.screen.height}`,
      viewport: `${window.innerWidth}x${window.innerHeight}`,
      ...errorData
    };

    // Add to queue
    this.state.errorQueue.push(error);

    // Flush if batch size reached
    if (this.state.errorQueue.length >= this.state.batchSize) {
      this.flushErrors();
    }

    console.error('[ErrorReporter] Captured error:', error.message);
  },

  /**
   * Record telemetry data
   */
  recordTelemetry(type, data) {
    if (!this.state.enabled) return;

    const telemetry = {
      id: this.generateId(),
      sessionId: this.state.sessionId,
      userId: this.state.userId,
      timestamp: Date.now(),
      type: type,
      url: window.location.href,
      ...data
    };

    // Add to queue
    this.state.telemetryQueue.push(telemetry);

    // Flush if batch size reached
    if (this.state.telemetryQueue.length >= this.state.batchSize) {
      this.flushTelemetry();
    }

    console.log('[ErrorReporter] Recorded telemetry:', type);
  },

  /**
   * Flush error queue to server
   */
  async flushErrors() {
    if (this.state.errorQueue.length === 0) return;

    const errors = [...this.state.errorQueue];
    this.state.errorQueue = [];

    try {
      // Check if offline
      if (!navigator.onLine) {
        console.log('[ErrorReporter] Offline, queueing errors for later');
        this.state.errorQueue = errors.concat(this.state.errorQueue);
        return;
      }

      // Send to server
      if (typeof window.API !== 'undefined' && typeof window.API.apiCall === 'function') {
        await window.API.apiCall(this.state.endpoint, 'POST', { errors });
        console.log('[ErrorReporter] Flushed', errors.length, 'errors');
      }
    } catch (error) {
      console.error('[ErrorReporter] Failed to flush errors:', error);
      // Re-queue on failure
      this.state.errorQueue = errors.concat(this.state.errorQueue);
    }
  },

  /**
   * Flush telemetry queue to server
   */
  async flushTelemetry() {
    if (this.state.telemetryQueue.length === 0) return;

    const telemetry = [...this.state.telemetryQueue];
    this.state.telemetryQueue = [];

    try {
      // Check if offline
      if (!navigator.onLine) {
        console.log('[ErrorReporter] Offline, queueing telemetry for later');
        this.state.telemetryQueue = telemetry.concat(this.state.telemetryQueue);
        return;
      }

      // Send to server
      if (typeof window.API !== 'undefined' && typeof window.API.apiCall === 'function') {
        await window.API.apiCall(this.state.telemetryEndpoint, 'POST', { telemetry });
        console.log('[ErrorReporter] Flushed', telemetry.length, 'telemetry entries');
      }
    } catch (error) {
      console.error('[ErrorReporter] Failed to flush telemetry:', error);
      // Re-queue on failure
      this.state.telemetryQueue = telemetry.concat(this.state.telemetryQueue);
    }
  },

  /**
   * Start periodic flush
   */
  startPeriodicFlush() {
    setInterval(() => {
      this.flushErrors();
      this.flushTelemetry();
    }, this.state.flushInterval);
  },

  /**
   * Manually trigger flush
   */
  flush() {
    this.flushErrors();
    this.flushTelemetry();
  },

  /**
   * Enable/disable error reporting
   */
  setEnabled(enabled) {
    this.state.enabled = enabled;
    console.log('[ErrorReporter]', enabled ? 'Enabled' : 'Disabled');
  },

  /**
   * Get user ID
   */
  getUserId() {
    if (typeof SessionManager !== 'undefined' && SessionManager.isAuthenticated()) {
      const user = SessionManager.getCurrentUser();
      return user ? user.id : null;
    }
    if (window.AuthContext && window.AuthContext.isAuthenticated()) {
      const user = window.AuthContext.getUser();
      return user ? user.id : null;
    }
    return null;
  },

  /**
   * Generate unique ID
   */
  generateId() {
    return Date.now().toString(36) + Math.random().toString(36).substr(2);
  },

  /**
   * Generate session ID
   */
  generateSessionId() {
    return 'session_' + this.generateId();
  },

  /**
   * Get current state
   */
  getState() {
    return {
      enabled: this.state.enabled,
      errorQueueSize: this.state.errorQueue.length,
      telemetryQueueSize: this.state.telemetryQueue.length,
      sessionId: this.state.sessionId,
      userId: this.state.userId
    };
  }
};

// Auto-initialize when DOM is ready
if (typeof document !== 'undefined') {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => ErrorReporter.initialize());
  } else {
    ErrorReporter.initialize();
  }
}
