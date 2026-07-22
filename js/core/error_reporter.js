/**
 * Client Error Reporting and Telemetry
 *
 * Safe, non-blocking browser diagnostics for Kingsway.
 */

const ErrorReporter = (() => {
  "use strict";

  const DEFAULTS = Object.freeze({
    enabled: true,
    localCaptureEnabled: true,
    remoteErrorsEnabled: false,
    remoteTelemetryEnabled: false,
    errorEndpoint: "/telemetry/errors",
    telemetryEndpoint: "/telemetry/data",
    batchSize: 10,
    flushInterval: 60000,
    maxErrorQueueSize: 100,
    maxTelemetryQueueSize: 100,
    requestTimeout: 8000,
    disableEndpointOnStatuses: [404, 405, 410, 501],
    debug: false,
  });

  const state = {
    config: { ...DEFAULTS },
    initialized: false,
    listenersInstalled: false,
    performanceInstalled: false,
    flushTimer: null,
    flushInProgress: false,
    errorQueue: [],
    telemetryQueue: [],
    sessionId: null,
    userId: null,
    remoteErrorsUnavailable: false,
    remoteTelemetryUnavailable: false,
    lastFlushAt: null,
    lastErrorFlushAt: null,
    lastTelemetryFlushAt: null,
  };

  function log(...args) {
    if (state.config.debug) {
      console.debug("[ErrorReporter]", ...args);
    }
  }

  function warn(...args) {
    console.warn("[ErrorReporter]", ...args);
  }

  function normalizeConfig(options = {}) {
    return {
      ...state.config,
      ...options,
      disableEndpointOnStatuses: Array.isArray(options.disableEndpointOnStatuses)
        ? options.disableEndpointOnStatuses
        : state.config.disableEndpointOnStatuses,
    };
  }

  function generateId() {
    if (typeof crypto !== "undefined" && typeof crypto.randomUUID === "function") {
      return crypto.randomUUID();
    }

    return Date.now().toString(36) + Math.random().toString(36).slice(2);
  }

  function generateSessionId() {
    return `session_${generateId()}`;
  }

  function getRoute() {
    try {
      return new URLSearchParams(window.location.search).get("route") || null;
    } catch {
      return null;
    }
  }

  function getUserId() {
    try {
      const auth = window.AuthContext;

      if (
        auth &&
        typeof auth.isAuthenticated === "function" &&
        auth.isAuthenticated() &&
        typeof auth.getUser === "function"
      ) {
        return auth.getUser()?.id ?? null;
      }

      const session = window.SessionManager;

      if (
        session &&
        typeof session.isAuthenticated === "function" &&
        session.isAuthenticated() &&
        typeof session.getCurrentUser === "function"
      ) {
        return session.getCurrentUser()?.id ?? null;
      }
    } catch (error) {
      log("Unable to resolve current user ID.", error);
    }

    return null;
  }

  function serializeError(error) {
    if (!error) return null;

    if (error instanceof Error) {
      return {
        name: error.name,
        message: error.message,
        stack: error.stack || null,
        code: error.code || null,
        status: error.status || null,
      };
    }

    if (typeof error === "object") {
      try {
        return JSON.parse(JSON.stringify(error));
      } catch {
        return { message: String(error) };
      }
    }

    return { message: String(error) };
  }

  function createContext() {
    return {
      sessionId: state.sessionId,
      userId: state.userId,
      timestamp: Date.now(),
      url: window.location.href,
      route: getRoute(),
      userAgent: navigator.userAgent,
      viewport: `${window.innerWidth}x${window.innerHeight}`,
    };
  }

  function trimQueue(queue, maximum) {
    if (queue.length > maximum) {
      queue.splice(0, queue.length - maximum);
    }
  }

  function captureError(errorData = {}) {
    if (!state.config.enabled || !state.config.localCaptureEnabled) return null;

    const normalized = {
      id: generateId(),
      ...createContext(),
      message:
        errorData.message ||
        errorData.reason?.message ||
        "Unknown client error",
      filename: errorData.filename || null,
      lineno: errorData.lineno || null,
      colno: errorData.colno || null,
      source: errorData.source || "window",
      error: serializeError(errorData.error || errorData.reason),
      resource: errorData.resource || null,
    };

    state.errorQueue.push(normalized);
    trimQueue(state.errorQueue, state.config.maxErrorQueueSize);

    if (
      state.config.remoteErrorsEnabled &&
      state.errorQueue.length >= state.config.batchSize
    ) {
      void flushErrors();
    }

    return normalized;
  }

  function recordTelemetry(type, data = {}) {
    if (!state.config.enabled || !state.config.localCaptureEnabled) return null;

    const entry = {
      id: generateId(),
      ...createContext(),
      type: String(type || "event"),
      ...data,
    };

    state.telemetryQueue.push(entry);
    trimQueue(state.telemetryQueue, state.config.maxTelemetryQueueSize);

    if (
      state.config.remoteTelemetryEnabled &&
      state.telemetryQueue.length >= state.config.batchSize
    ) {
      void flushTelemetry();
    }

    return entry;
  }

  function handleWindowError(event) {
    if (event.target && event.target !== window) {
      const target = event.target;

      captureError({
        message: "Resource Load Error",
        source: "resource",
        resource: {
          tagName: target.tagName || null,
          src: target.src || target.href || null,
        },
      });
      return;
    }

    captureError({
      message: event.message,
      filename: event.filename,
      lineno: event.lineno,
      colno: event.colno,
      error: event.error,
      source: "javascript",
    });
  }

  function handleUnhandledRejection(event) {
    captureError({
      message: event.reason?.message || "Unhandled Promise Rejection",
      reason: event.reason,
      source: "promise",
    });
  }

  function installErrorHandlers() {
    if (state.listenersInstalled) return;

    window.addEventListener("error", handleWindowError, true);
    window.addEventListener("unhandledrejection", handleUnhandledRejection);

    state.listenersInstalled = true;
  }

  function getNavigationMetrics(entry) {
    return {
      name: entry.name,
      duration: Math.round(entry.duration || 0),
      transferSize: entry.transferSize || 0,
      encodedBodySize: entry.encodedBodySize || 0,
      decodedBodySize: entry.decodedBodySize || 0,
      domContentLoaded: entry.domContentLoadedEventEnd || null,
      loadEventEnd: entry.loadEventEnd || null,
      type: entry.type || null,
    };
  }

  function installPerformanceMonitoring() {
    if (state.performanceInstalled) return;

    state.performanceInstalled = true;

    window.addEventListener(
      "load",
      () => {
        window.setTimeout(() => {
          try {
            const navigation = performance.getEntriesByType?.("navigation")?.[0];
            if (navigation) {
              recordTelemetry("page_load", getNavigationMetrics(navigation));
            }

            const paintEntries = performance.getEntriesByType?.("paint") || [];
            const firstPaint = paintEntries.find(
              (entry) => entry.name === "first-paint",
            );
            const firstContentfulPaint = paintEntries.find(
              (entry) => entry.name === "first-contentful-paint",
            );

            if (firstPaint || firstContentfulPaint) {
              recordTelemetry("paint", {
                firstPaint: firstPaint?.startTime ?? null,
                firstContentfulPaint:
                  firstContentfulPaint?.startTime ?? null,
              });
            }
          } catch (error) {
            log("Performance metrics unavailable.", error);
          }
        }, 0);
      },
      { once: true },
    );

    if (typeof PerformanceObserver === "function") {
      try {
        const observer = new PerformanceObserver((list) => {
          list.getEntries().forEach((entry) => {
            if (entry.entryType === "navigation") {
              recordTelemetry("navigation", getNavigationMetrics(entry));
            }
          });
        });

        observer.observe({ type: "navigation", buffered: true });
      } catch (error) {
        log("PerformanceObserver unavailable.", error);
      }
    }
  }

  function getApiMethod() {
    if (window.API && typeof window.API.callAPI === "function") {
      return (endpoint, method, payload) =>
        window.API.callAPI(endpoint, method, payload, {}, {
          checkPermission: false,
          skipAuthRefresh: true,
          cache: "no-store",
        });
    }

    if (window.API && typeof window.API.apiCall === "function") {
      return (endpoint, method, payload) =>
        window.API.apiCall(endpoint, method, payload, {}, {
          checkPermission: false,
          skipAuthRefresh: true,
          cache: "no-store",
        });
    }

    return null;
  }

  function statusFromError(error) {
    return Number(
      error?.status ||
        error?.response?.status ||
        error?.code ||
        0,
    );
  }

  function shouldDisableEndpoint(error) {
    return state.config.disableEndpointOnStatuses.includes(
      statusFromError(error),
    );
  }

  function isActuallyOffline(error) {
    if (navigator.onLine === false) return true;

    const message = String(error?.message || error || "");

    return /Failed to fetch|NetworkError|Load failed|network request failed/i.test(
      message,
    );
  }

  async function withTimeout(promise, timeout) {
    let timer = null;

    try {
      return await Promise.race([
        promise,
        new Promise((_, reject) => {
          timer = window.setTimeout(() => {
            const error = new Error("Telemetry request timed out.");
            error.code = "TELEMETRY_TIMEOUT";
            reject(error);
          }, timeout);
        }),
      ]);
    } finally {
      if (timer !== null) clearTimeout(timer);
    }
  }

  async function flushQueue({
    queueName,
    endpoint,
    payloadKey,
    remoteEnabled,
    remoteUnavailableKey,
    lastFlushKey,
  }) {
    if (!state.config.enabled || !remoteEnabled) {
      return { skipped: true, reason: "remote reporting disabled" };
    }

    if (state[remoteUnavailableKey]) {
      return { skipped: true, reason: "endpoint unavailable" };
    }

    const queue = state[queueName];
    if (!queue.length) {
      return { skipped: true, reason: "queue empty" };
    }

    if (state.flushInProgress) {
      return { skipped: true, reason: "flush already in progress" };
    }

    const apiMethod = getApiMethod();
    if (!apiMethod) {
      return { skipped: true, reason: "API utility unavailable" };
    }

    const batch = queue.slice(0, state.config.batchSize);
    state.flushInProgress = true;

    try {
      await withTimeout(
        apiMethod(endpoint, "POST", { [payloadKey]: batch }),
        state.config.requestTimeout,
      );

      queue.splice(0, batch.length);
      state[lastFlushKey] = Date.now();
      state.lastFlushAt = Date.now();

      return { success: true, flushed: batch.length };
    } catch (error) {
      if (shouldDisableEndpoint(error)) {
        state[remoteUnavailableKey] = true;
        warn(
          `${endpoint} is unavailable. Remote ${payloadKey} reporting has been disabled for this page session.`,
        );
      } else if (!isActuallyOffline(error)) {
        warn(
          `Unable to flush ${payloadKey}. Entries remain queued.`,
          error?.message || error,
        );
      }

      return { success: false, error };
    } finally {
      state.flushInProgress = false;
    }
  }

  function flushErrors() {
    return flushQueue({
      queueName: "errorQueue",
      endpoint: state.config.errorEndpoint,
      payloadKey: "errors",
      remoteEnabled: state.config.remoteErrorsEnabled,
      remoteUnavailableKey: "remoteErrorsUnavailable",
      lastFlushKey: "lastErrorFlushAt",
    });
  }

  function flushTelemetry() {
    return flushQueue({
      queueName: "telemetryQueue",
      endpoint: state.config.telemetryEndpoint,
      payloadKey: "telemetry",
      remoteEnabled: state.config.remoteTelemetryEnabled,
      remoteUnavailableKey: "remoteTelemetryUnavailable",
      lastFlushKey: "lastTelemetryFlushAt",
    });
  }

  async function flush() {
    const results = [];

    if (state.config.remoteErrorsEnabled) {
      results.push(await flushErrors());
    }

    if (state.config.remoteTelemetryEnabled) {
      results.push(await flushTelemetry());
    }

    return results;
  }

  function stopPeriodicFlush() {
    if (state.flushTimer !== null) {
      clearInterval(state.flushTimer);
      state.flushTimer = null;
    }
  }

  function startPeriodicFlush() {
    stopPeriodicFlush();

    if (
      !state.config.remoteErrorsEnabled &&
      !state.config.remoteTelemetryEnabled
    ) {
      return;
    }

    state.flushTimer = window.setInterval(
      () => void flush(),
      state.config.flushInterval,
    );
  }

  function initialize(options = {}) {
    state.config = normalizeConfig(options);

    if (state.initialized) {
      if (
        state.config.remoteErrorsEnabled ||
        state.config.remoteTelemetryEnabled
      ) {
        startPeriodicFlush();
      } else {
        stopPeriodicFlush();
      }

      return getState();
    }

    state.initialized = true;
    state.sessionId = generateSessionId();
    state.userId = getUserId();

    installErrorHandlers();
    installPerformanceMonitoring();

    if (
      state.config.remoteErrorsEnabled ||
      state.config.remoteTelemetryEnabled
    ) {
      startPeriodicFlush();
    }

    return getState();
  }

  function destroy() {
    stopPeriodicFlush();

    if (state.listenersInstalled) {
      window.removeEventListener("error", handleWindowError, true);
      window.removeEventListener(
        "unhandledrejection",
        handleUnhandledRejection,
      );
      state.listenersInstalled = false;
    }

    state.initialized = false;
  }

  function setEnabled(enabled) {
    state.config.enabled = Boolean(enabled);

    if (!state.config.enabled) {
      stopPeriodicFlush();
    } else if (
      state.config.remoteErrorsEnabled ||
      state.config.remoteTelemetryEnabled
    ) {
      startPeriodicFlush();
    }
  }

  function setRemoteErrorsEnabled(enabled) {
    state.config.remoteErrorsEnabled = Boolean(enabled);

    if (enabled) {
      state.remoteErrorsUnavailable = false;
      startPeriodicFlush();
    } else if (!state.config.remoteTelemetryEnabled) {
      stopPeriodicFlush();
    }
  }

  function setRemoteTelemetryEnabled(enabled) {
    state.config.remoteTelemetryEnabled = Boolean(enabled);

    if (enabled) {
      state.remoteTelemetryUnavailable = false;
      startPeriodicFlush();
    } else if (!state.config.remoteErrorsEnabled) {
      stopPeriodicFlush();
    }
  }

  function clearQueues() {
    state.errorQueue.length = 0;
    state.telemetryQueue.length = 0;
  }

  function getState() {
    return {
      initialized: state.initialized,
      enabled: state.config.enabled,
      localCaptureEnabled: state.config.localCaptureEnabled,
      remoteErrorsEnabled: state.config.remoteErrorsEnabled,
      remoteTelemetryEnabled: state.config.remoteTelemetryEnabled,
      remoteErrorsUnavailable: state.remoteErrorsUnavailable,
      remoteTelemetryUnavailable: state.remoteTelemetryUnavailable,
      errorQueueSize: state.errorQueue.length,
      telemetryQueueSize: state.telemetryQueue.length,
      sessionId: state.sessionId,
      userId: state.userId,
      flushTimerActive: state.flushTimer !== null,
      flushInProgress: state.flushInProgress,
      lastFlushAt: state.lastFlushAt,
      lastErrorFlushAt: state.lastErrorFlushAt,
      lastTelemetryFlushAt: state.lastTelemetryFlushAt,
    };
  }

  return {
    initialize,
    destroy,
    captureError,
    recordTelemetry,
    flush,
    flushErrors,
    flushTelemetry,
    startPeriodicFlush,
    stopPeriodicFlush,
    setEnabled,
    setRemoteErrorsEnabled,
    setRemoteTelemetryEnabled,
    clearQueues,
    getState,
  };
})();

window.ErrorReporter = ErrorReporter;

if (typeof document !== "undefined") {
  const initializeReporter = () => {
    ErrorReporter.initialize({
      enabled: true,
      localCaptureEnabled: true,
      remoteErrorsEnabled: false,
      remoteTelemetryEnabled: false,
    });
  };

  if (document.readyState === "loading") {
    document.addEventListener(
      "DOMContentLoaded",
      initializeReporter,
      { once: true },
    );
  } else {
    initializeReporter();
  }
}
