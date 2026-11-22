/**
 * Node realtime stream — the only live-update transport.
 *
 * PHP mints a short-lived capability JWT ({ realtime: true, channels: [...] })
 * at GET /api/realtime/stream-token. The stream is read with fetch() instead of
 * EventSource so the capability travels in the Authorization header: a token
 * in the URL would be written to every proxy and access log on the way.
 *
 * Every frame goes to RealtimeDispatch, which alone decides what to refresh.
 *
 * Degradation contract (live updates are an enhancement, never a dependency):
 *   - A capability is minted once and reused for every reconnect until 30s
 *     before it expires. A connection failure never re-mints, so a dead
 *     gateway costs no PHP calls.
 *   - Connection failures back off with full jitter (1s→60s cap) and retry
 *     for as long as the page is open. Streams that the edge drops within a
 *     few seconds of opening jump straight to the cap.
 *   - The gateway writes a heartbeat every 10s. Silence for STALL_MS means the
 *     socket died without telling anyone, so the stream is aborted and
 *     reopened.
 *   - After a reconnect the current page refreshes its registered data once,
 *     to cover changes missed while the stream was down.
 *   - Only an explicit 401/403 when minting the capability stops retrying.
 *   - Nothing is shown to the user and nothing is logged: the page works the
 *     same without the stream, it just refreshes on saves and navigation.
 */
(function (window) {
  'use strict';

  const MAX_BACKOFF_MS = 60000;
  const RENEW_BEFORE_MS = 30000;
  const FAST_FAIL_MS = 5000;
  const FAST_FAIL_THRESHOLD = 3;
  const STALL_MS = 45000;

  let started = false;
  let stopped = false;
  let controller = null;
  let retryTimer = 0;
  let stallTimer = 0;
  let retryAttempt = 0;
  // Fresh capabilities the gateway refused in a row. Repeated refusal of a
  // token PHP just minted is a configuration fault (signing key, issuer, or a
  // proxy stripping the header), not something retrying can fix.
  let rejectedCapabilities = 0;
  const MAX_REJECTED_CAPABILITIES = 3;
  let fastFailures = 0;
  let capability = null;
  let hasConnectedBefore = false;

  // ---- Capability --------------------------------------------------------

  function usableCapability() {
    if (!capability?.url || !capability?.token) return false;
    // Unknown expiry: the server enforces the real bound.
    if (!Number.isFinite(capability.expiresAt)) return true;
    return capability.expiresAt - Date.now() > RENEW_BEFORE_MS;
  }

  /**
   * Resolves to the capability, `false` for a transient failure (retry later)
   * or `null` when PHP explicitly refuses one (stop for good).
   */
  async function obtainCapability() {
    let result;
    try {
      result = await window.API.apiCall('realtime/stream-token', 'GET');
    } catch (error) {
      const code = Number(error?.code || error?.status || 0);
      return code === 401 || code === 403 ? null : false;
    }
    const data = result?.data || result || {};
    // No realtime gateway on this deployment: stop for good, quietly.
    if (data.enabled === false) return null;
    const token = data.token;
    const url = data.url;
    if (typeof token !== 'string' || !token || typeof url !== 'string' || !url) return false;
    // An absent or unparseable expiry must be NaN (unknown), not 0 (expired),
    // or every reconnect would re-mint.
    const parsedExpiry = data.expires_at ? Number(new Date(data.expires_at)) : NaN;
    capability = { url, token, expiresAt: Number.isFinite(parsedExpiry) ? parsedExpiry : NaN };
    return capability;
  }

  // ---- Frames --------------------------------------------------------------

  function deliver(eventName, data) {
    if (eventName === 'connected' || eventName === 'ready') {
      onStreamReady();
      return;
    }
    if (eventName === 'heartbeat') return; // the stall watchdog was already fed
    if (eventName !== 'kingsway') return;
    let message;
    try { message = JSON.parse(data); } catch (_) { return; }
    window.RealtimeDispatch?.dispatch?.({
      type: message.type,
      scope: message.scope || 'all',
      payload: message.payload || {},
      source: 'node-sse',
    });
  }

  /**
   * Minimal text/event-stream parser: `event:` and `data:` fields, comments
   * and blank-line dispatch. `id:` and `retry:` are not used by the gateway.
   */
  function createParser(onEvent) {
    let buffer = '';
    let eventName = '';
    let dataLines = [];
    return (chunk) => {
      buffer += chunk;
      let newline;
      while ((newline = buffer.search(/\r\n|\r|\n/)) !== -1) {
        const line = buffer.slice(0, newline);
        buffer = buffer.slice(newline + (buffer.startsWith('\r\n', newline) ? 2 : 1));
        if (line === '') {
          if (dataLines.length) onEvent(eventName || 'message', dataLines.join('\n'));
          eventName = '';
          dataLines = [];
          continue;
        }
        if (line.startsWith(':')) continue;
        const colon = line.indexOf(':');
        const field = colon === -1 ? line : line.slice(0, colon);
        let value = colon === -1 ? '' : line.slice(colon + 1);
        if (value.startsWith(' ')) value = value.slice(1);
        if (field === 'event') eventName = value;
        else if (field === 'data') dataLines.push(value);
      }
    };
  }

  /**
   * Read one text/event-stream response with fetch(), so credentials go in a
   * header instead of the URL. Resolves when the server ends the stream;
   * rejects on a network failure, an abort, or a non-2xx answer (the error
   * carries `status`). `onChunk` runs on every received chunk (liveness).
   */
  async function readEventStream(url, { headers = {}, signal, onEvent, onOpen, onChunk } = {}) {
    const response = await fetch(url, {
      method: 'GET',
      headers: { Accept: 'text/event-stream', ...headers },
      credentials: 'omit',
      cache: 'no-store',
      signal,
    });
    if (!response.ok || !response.body) {
      const error = new Error(`Event stream answered ${response.status}`);
      error.status = response.status;
      throw error;
    }
    onOpen?.();
    const parse = createParser(onEvent || (() => {}));
    const reader = response.body.pipeThrough(new TextDecoderStream()).getReader();
    for (;;) {
      const { value, done } = await reader.read();
      if (done) return;
      onChunk?.();
      parse(value);
    }
  }

  // ---- Connection ------------------------------------------------------------

  function feedStallWatchdog() {
    window.clearTimeout(stallTimer);
    stallTimer = window.setTimeout(() => controller?.abort(), STALL_MS);
  }

  function onStreamReady() {
    retryAttempt = 0;
    rejectedCapabilities = 0;
    fastFailures = 0;
    // Changes may have been missed while the stream was down: refresh the
    // current page's registered data dependencies once.
    if (hasConnectedBefore) {
      const dependencies = window.APIRealtime?.dependencies?.() || [];
      if (dependencies.length) window.APIRealtime?.schedule?.(dependencies);
    }
    hasConnectedBefore = true;
  }

  function scheduleReconnect() {
    if (retryTimer || stopped) return;
    const base = Math.min(MAX_BACKOFF_MS, 1000 * (2 ** retryAttempt));
    const delay = Math.round(base * (0.5 + Math.random() * 0.5));
    retryAttempt = Math.min(retryAttempt + 1, 6);
    retryTimer = window.setTimeout(() => {
      retryTimer = 0;
      void connect();
    }, delay);
  }

  async function connect() {
    if (stopped || !window.API?.apiCall || typeof fetch !== 'function' || !window.ReadableStream) return;

    if (!usableCapability()) {
      const minted = await obtainCapability();
      if (minted === null) { stopped = true; return; }
      if (!minted) { scheduleReconnect(); return; }
    }

    controller?.abort();
    const current = new AbortController();
    controller = current;
    const openedAt = Date.now();

    try {
      await readEventStream(new URL('/events', capability.url).toString(), {
        headers: { Authorization: `Bearer ${capability.token}`,
          // Same capability for gateways whose proxy strips Authorization.
          'X-Kingsway-SSE-Token': capability.token },
        signal: current.signal,
        onEvent: deliver,
        onOpen: feedStallWatchdog,
        onChunk: feedStallWatchdog,
      });
    } catch (error) {
      // Expired or revoked capability: mint a fresh one on the next attempt.
      if (error?.status === 401 || error?.status === 403) {
        capability = null;
        rejectedCapabilities += 1;
        if (rejectedCapabilities >= MAX_REJECTED_CAPABILITIES) {
          // Live updates stay off for this page view; pages still work and
          // refresh on navigation. Reported once, silently to the user.
          stopped = true;
          window.AppLogger?.warn?.('realtime', 'Gateway refused freshly issued stream capabilities; live updates paused for this page.', { status: error.status });
        }
      }
      // Anything else (network drop, stall or page-hide abort) just retries.
    } finally {
      window.clearTimeout(stallTimer);
      if (controller === current) controller = null;
    }

    if (stopped) return;
    // A stream the edge drops within seconds is a rate/capacity signal, not
    // a down gateway: escalate to the cap instead of hammering it.
    fastFailures = Date.now() - openedAt < FAST_FAIL_MS ? fastFailures + 1 : 0;
    if (fastFailures >= FAST_FAIL_THRESHOLD) {
      retryAttempt = 6;
      fastFailures = 0;
    }
    scheduleReconnect();
  }

  function stop() {
    stopped = true;
    window.clearTimeout(retryTimer);
    retryTimer = 0;
    controller?.abort();
  }

  // ---- Presence --------------------------------------------------------------

  /**
   * Declare (or release) an active edit so the gateway withholds row patches for
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
      // Presence is an optimisation; losing it degrades to ordinary refreshes.
      return false;
    }
  }

  function initialize() {
    if (started) return;
    started = true;
    void connect();
    window.addEventListener('pagehide', stop);
    // A page restored from the back/forward cache needs its stream back.
    window.addEventListener('pageshow', (event) => {
      if (!event.persisted) return;
      stopped = false;
      void connect();
    });
  }

  // Shared with other stream consumers (js/components/agent_task_panel.js).
  window.KingswayEventStream = Object.freeze({ read: readEventStream });

  window.RealtimeSSE = Object.freeze({
    initialize,
    reconnect: () => { stopped = false; void connect(); },
    close: stop,
    declarePresence,
  });
})(window);
