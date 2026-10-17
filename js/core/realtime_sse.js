/**
 * Node realtime SSE consumer. The PHP token endpoint must return a short-lived
 * capability JWT containing { realtime: true, channels: [...] }.
 *
 * Activation is controlled by the authenticated shell feature flag. The
 * static-buffer path remains active as the fallback.
 *
 * Degradation contract:
 *   - A capability is minted once per activation and REUSED for every
 *     reconnect until near expiry (renewed 30s early). A connection-level
 *     failure never triggers a fresh PHP token request.
 *   - Gateway downtime is handled by capped exponential backoff that KEEPS
 *     re-probing forever (1s→60s). While the stream is down the RealtimeManager
 *     sees "reconnecting" (not "connected") and its buffer polling resumes, so
 *     events keep arriving by the older path and the transport reconnects the
 *     moment the gateway returns. Nothing is disabled.
 *   - Only an explicit authentication rejection (401/403) stops retrying —
 *     there is genuinely no capability to obtain.
 */
(function (window) {
  'use strict';

  let source = null;
  let started = false;
  let retryTimer = 0;
  let retryAttempt = 0;
  let capability = null;

  // Capped backoff for connection-level failures. The transport never gives up
  // (a re-probed gateway reconnects on its own), but it also never spams: each
  // attempt reuses the cached capability, so a dead gateway costs zero PHP
  // calls and one socket open per interval.
  const MAX_BACKOFF_MS = 60000;
  const RENEW_BEFORE_MS = 30000;

  function emit(name, detail) {
    window.dispatchEvent(new CustomEvent(name, { detail }));
  }

  /**
   * Transport only: parse the frame and hand it to the single dispatcher.
   *
   * All routing, cache invalidation and refresh decisions live in
   * RealtimeDispatch so this transport and the static-buffer transport cannot
   * drift apart or both act on the same change.
   */
  function deliver(event) {
    let message;
    try { message = JSON.parse(event.data); } catch (_) { return; }
    window.RealtimeDispatch?.dispatch?.({
      type: message.type,
      scope: message.scope || 'all',
      payload: message.payload || {},
      source: 'node-sse',
    });
  }

  /**
   * Declare (or release) an active edit so the engine withholds row patches for
   * this row on this connection and sends a CONFLICT_HINT instead. This is what
   * stops a peer's save from overwriting text the user is still typing.
   */
  async function declarePresence(activityKey, state = 'editing', scope = 'all') {
    if (!capability?.url || !capability?.token || typeof fetch !== 'function') return false;
    try {
      const response = await fetch(new URL('/presence', capability.url).toString(), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Kingsway-SSE-Token': capability.token },
        body: JSON.stringify({ activity_key: activityKey, state, scope }),
        credentials: 'omit',
      });
      return response.ok;
    } catch (_) {
      // Presence is an optimisation. Losing it degrades to ordinary refreshes.
      return false;
    }
  }

  function usableCapability() {
    if (!capability?.url || !capability?.token) return false;
    // No expiry known → treat as usable (the server enforces the real bound).
    if (!Number.isFinite(capability.expiresAt)) return true;
    return capability.expiresAt - Date.now() > RENEW_BEFORE_MS;
  }

  function scheduleReconnect() {
    if (retryTimer) return;
    const delay = Math.min(MAX_BACKOFF_MS, 1000 * (2 ** retryAttempt));
    retryAttempt = Math.min(retryAttempt + 1, 6); // 1s→2s→4s→8s→16s→32s→60s cap
    retryTimer = window.setTimeout(() => {
      retryTimer = 0;
      connect();
    }, delay);
  }

  /**
   * Mint a capability exactly once per activation. Callers reuse the cached
   * token on every reconnect until it nears expiry, so a dead gateway never
   * produces repeat PHP token requests.
   */
  async function obtainCapability() {
    let result;
    try {
      result = await window.API.apiCall('realtime/stream-token', 'GET');
    } catch (error) {
      // Mirror the buffer handshake's failure policy, which follows this
      // project's token-refresh philosophy: only an explicit authentication
      // rejection proves the request can never succeed. Retrying a 401/403
      // forever just hammers PHP for a capability it will never grant.
      const code = Number(error?.code || error?.status || 0);
      if (code === 401 || code === 403) {
        emit('kingsway:realtime-status', { detail: { state: 'disabled', reason: `token_${code}` } });
        return null;
      }
      return false; // transient mint failure: caller schedules a retry
    }
    const token = result?.data?.token || result?.token;
    const url = result?.data?.url || result?.url;
    const expiresAt = Number(new Date(result?.data?.expires_at || 0));
    if (typeof token !== 'string' || !token || typeof url !== 'string' || !url) {
      return false;
    }
    capability = { url, token, expiresAt };
    return capability;
  }

  async function connect() {
    if (!window.API?.apiCall || typeof window.EventSource !== 'function') return false;

    // Reuse the minted capability while it is still valid. This is the load
    // guard: reconnects re-open the same socket URL instead of requesting a
    // fresh token per attempt.
    if (!usableCapability()) {
      const cap = await obtainCapability();
      if (!cap) {
        if (cap === null) return false; // auth rejection — stop for good
        scheduleReconnect();
        return false;
      }
    }

    const endpoint = new URL('/events', capability.url);
    endpoint.searchParams.set('token', capability.token);
    if (source) {
      source.close();
      source = null;
    }
    source = new EventSource(endpoint.toString());
    source.addEventListener('kingsway', deliver);
    const onStreamOpen = () => {
      const isReconnect = retryAttempt > 0;
      retryAttempt = 0;
      // Gateway restart catch-up: after a reconnect the socket missed every
      // event published while it was down, so silently re-fetch all endpoints
      // this page depends on. The static-buffer manager does the same on
      // BUFFER_ROTATED.
      if (isReconnect && window.APIRealtime?.schedule) {
        window.APIRealtime.schedule([...(window.APIRealtime.dependencies ? window.APIRealtime.dependencies() : [])]);
      }
      emit('kingsway:realtime-status', { detail: { state: 'connected' } });
    };
    // The gateway announces the stream with a `connected` frame; an older
    // server build used `ready`. Accept both so liveness detection never
    // depends on which build is deployed.
    source.addEventListener('connected', onStreamOpen);
    source.addEventListener('ready', onStreamOpen);
    // Heartbeats are the ONLY proof the peer is still writing. EventSource's
    // readyState stays OPEN on a socket the peer has silently stopped writing
    // to, so forwarding the typed HEARTBEAT lets RealtimeManager keep buffer
    // polling suppressed while the stream is genuinely alive and resume it
    // within the staleness window once the peer goes quiet.
    source.addEventListener('heartbeat', (event) => {
      let payload = {};
      try {
        payload = JSON.parse(event.data) || {};
      } catch (_) {
        payload = {};
      }
      window.RealtimeDispatch?.dispatch?.({
        type: 'HEARTBEAT',
        scope: 'all',
        payload,
        source: 'node-sse',
      });
    });
    source.onerror = () => {
      // Take control of reconnection (EventSource would loop the same URL on
      // its own, including after expiry). Reuse the cached capability so a down
      // gateway costs one socket open per backoff interval and never re-mints.
      source?.close();
      source = null;
      emit('kingsway:realtime-status', { detail: { state: 'reconnecting' } });
      scheduleReconnect();
    };
    return true;
  }

  function init() {
    if (started) return;
    started = true;
    connect();
    window.addEventListener('pagehide', () => {
      source?.close();
      source = null;
      if (retryTimer) window.clearTimeout(retryTimer);
      retryTimer = 0;
    }, { once: true });
  }

  window.RealtimeSSE = Object.freeze({
    initialize: init,
    reconnect: connect,
    close: () => source?.close(),
    declarePresence,
  });
})(window);