/**
 * Node realtime SSE consumer. The PHP token endpoint must return a short-lived
 * capability JWT containing { realtime: true, channels: [...] }.
 *
 * Activation is controlled by the authenticated shell feature flag. The
 * static-buffer path remains active as the fallback.
 */
(function (window) {
  'use strict';

  let source = null;
  let started = false;
  let retryTimer = 0;
  let retryAttempt = 0;
  let capability = null;

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

  // How many consecutive transport failures to tolerate before handing the page
  // over to the static-buffer fallback for good. A gateway that is down should
  // not be probed forever by every open tab.
  const MAX_RETRY_ATTEMPTS = 8;

  function scheduleReconnect(delayOverride) {
    if (retryTimer) return;
    if (retryAttempt >= MAX_RETRY_ATTEMPTS) {
      // Give up: the polling supervisor sees no heartbeat and resumes buffer
      // polling, so events keep arriving by the older path.
      emit('kingsway:realtime-status', { detail: { state: 'disabled', reason: 'retries_exhausted' } });
      return;
    }
    const delay = Number.isFinite(delayOverride)
      ? delayOverride
      : Math.min(30000, 1000 * (2 ** retryAttempt));
    retryAttempt = Math.min(retryAttempt + 1, MAX_RETRY_ATTEMPTS);
    retryTimer = window.setTimeout(() => {
      retryTimer = 0;
      connect();
    }, delay);
  }

  async function connect() {
    if (!window.API?.apiCall || typeof window.EventSource !== 'function') return false;
    let result;
    try {
      result = await window.API.apiCall('realtime/stream-token', 'GET');
    } catch (error) {
      // Mirror the buffer handshake's failure policy, which follows this
      // project's token-refresh philosophy: only an explicit authentication
      // rejection proves the request can never succeed. Retrying a 401/403
      // forever just hammers PHP for a capability it will never grant — the
      // symptom is an endless stream-token retry loop in the console.
      const code = Number(error?.code || error?.status || 0);
      if (code === 401 || code === 403) {
        retryAttempt = MAX_RETRY_ATTEMPTS;
        emit('kingsway:realtime-status', {
          detail: { state: 'disabled', reason: `token_${code}` },
        });
        return false;
      }
      scheduleReconnect();
      return false;
    }
    const token = result?.data?.token || result?.token;
    const base = result?.data?.url || result?.url;
    if (typeof token !== 'string' || !token || typeof base !== 'string' || !base) {
      scheduleReconnect();
      return false;
    }

    capability = { url: base, token };
    const endpoint = new URL('/events', base);
    endpoint.searchParams.set('token', token);
    source?.close();
    source = new EventSource(endpoint.toString());
    source.addEventListener('kingsway', deliver);
    source.addEventListener('ready', () => {
      const isReconnect = retryAttempt > 0;
      retryAttempt = 0;
      // Gateway restart catch-up: after a reconnect the socket missed every
      // event published while it was down, so silently re-fetch all endpoints
      // this page depends on (empty targets match nothing by design, hence the
      // explicit dependency list). The static-buffer manager does the same on
      // BUFFER_ROTATED.
      if (isReconnect && window.APIRealtime?.schedule) {
        window.APIRealtime.schedule([...(window.APIRealtime.dependencies ? window.APIRealtime.dependencies() : [])]);
      }
      window.dispatchEvent(new CustomEvent('kingsway:realtime-status', { detail: { state: 'connected' } }));
    });
    source.onerror = () => {
      // EventSource reconnects with the same URL, so an expired capability
      // would otherwise loop forever. Close it and obtain a fresh capability.
      source?.close();
      source = null;
      window.dispatchEvent(new CustomEvent('kingsway:realtime-status', { detail: { state: 'reconnecting' } }));
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
