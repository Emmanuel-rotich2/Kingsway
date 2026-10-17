/**
 * Realtime presence & collaboration widget.
 *
 * Activates the engine's collaboration-sync surface across every page:
 *  - declares that the current user is editing a named activity (row/form)
 *    so the gateway withholds that row's patch from this connection and
 *    sends a CONFLICT_HINT instead of overwriting in-progress input;
 *  - renders "staff #N is editing" badges on elements marked with
 *    `data-realtime-activity="<activity key>"` when a PEER declares the
 *    same activity, and removes them when the peer stops;
 *  - turns CONFLICT_HINT events into a visible, non-blocking warning so the
 *    user reconciles deliberately before saving.
 *
 * Contract with the server (node_platform/src/routes.js / dispatcher.js):
 *  - POST /presence { activity_key, state: editing|viewing|idle, scope }
 *    with the capability header; declarations expire server-side after 45s,
 *    so this widget renews every 15s while an edit stays open.
 *  - PRESENCE events arrive on the SSE stream with
 *    { user_id, activity_key, state } and nothing else.
 *
 * Transport-agnostic: it only listens to the DOM events RealtimeDispatch
 * emits, so it behaves identically on SSE and static-buffer deployments
 * (presence itself only flows over SSE; the widget simply goes quiet when
 * the stream is down, which degrades to today's no-presence behaviour).
 */
(function (window, document) {
  'use strict';

  const RENEW_INTERVAL_MS = 15000;
  const PEER_EXPIRY_MS = 60000;
  const BADGE_CLASS = 'rt-presence-badge';
  const OTHER_EDITING_CLASS = 'rt-editing-by-other';

  /** Peers' declarations: activityKey -> { userId, state, at, expiryTimer }. */
  const peers = new Map();
  /** My own open activities: activityKey -> { renewTimer }. */
  const mine = new Map();

  function myUserId() {
    const user = window.AuthContext?.getCurrentUser?.() || window.AuthContext?.getUser?.();
    return Number(user?.user_id ?? user?.id ?? 0) || 0;
  }

  function css() {
    const style = document.createElement('style');
    style.id = 'kingsway-realtime-presence-css';
    style.textContent = `
      .${OTHER_EDITING_CLASS} { outline: 1px solid rgba(13, 110, 253, .45); outline-offset: -1px; position: relative; }
      .${BADGE_CLASS} {
        display: inline-block; margin-left: .4rem; padding: .1rem .45rem; border-radius: 1rem;
        background: #0d6efd; color: #fff; font-size: .7rem; font-weight: 600; line-height: 1.4;
        vertical-align: middle; white-space: nowrap;
      }
      .rt-conflict-row { animation: rt-flash 1.2s ease-out 2; }
      @keyframes rt-flash { 0% { background-color: rgba(255, 193, 7, .45); } 100% { background-color: transparent; } }
      @media print { .${BADGE_CLASS}, .${OTHER_EDITING_CLASS} { display: none !important; } }
    `;
    document.head.appendChild(style);
  }

  /** Set/unset the badge on every element bound to this activity. */
  function paint(activityKey, entry) {
    const bound = document.querySelectorAll(`[data-realtime-activity="${CSS.escape(activityKey)}"]`);
    for (const el of bound) {
      const existing = el.querySelector(`:scope > .${BADGE_CLASS}`) || el.closest('tr')?.querySelector(`.${BADGE_CLASS}`);
      if (entry) {
        el.classList.add(OTHER_EDITING_CLASS);
        const badge = existing || (() => {
          const span = document.createElement('span');
          span.className = BADGE_CLASS;
          (el.closest('tr') || el).appendChild(span);
          return span;
        })();
        badge.textContent = entry.userId ? `Staff #${entry.userId} editing…` : 'Someone is editing…';
        badge.title = 'Another user has this record open for editing. Your saves are protected by conflict checks.';
      } else {
        el.classList.remove(OTHER_EDITING_CLASS);
        if (existing) existing.remove();
      }
    }
  }

  function trackPeer({ user_id: userId, activity_key: activityKey, state }) {
    if (!activityKey) return;
    // My own declaration echoes back to me on the stream; never badge myself.
    if (Number(userId) === myUserId()) return;

    const previous = peers.get(activityKey);
    if (previous?.expiryTimer) window.clearTimeout(previous.expiryTimer);

    if (state === 'idle' || state === 'viewing') {
      peers.delete(activityKey);
      paint(activityKey, null);
      return;
    }
    const entry = { userId: Number(userId) || 0, state, at: Date.now(), expiryTimer: 0 };
    entry.expiryTimer = window.setTimeout(() => {
      peers.delete(activityKey);
      paint(activityKey, null);
    }, PEER_EXPIRY_MS);
    peers.set(activityKey, entry);
    paint(activityKey, entry);
  }

  async function declare(activityKey, state) {
    try {
      await window.RealtimeSSE?.declarePresence?.(activityKey, state, 'all');
    } catch (_) {
      // Presence is an optimisation; losing it degrades to ordinary refreshes.
    }
  }

  /**
   * Declare that the current user is editing a named activity. Safe to call
   * repeatedly for the same key; renewal is idempotent.
   * @returns {Function} release() — call when the edit ends (save/cancel/close).
   */
  function begin(activityKey) {
    if (!activityKey || !window.RealtimeSSE?.declarePresence) return () => {};
    if (!mine.has(activityKey)) {
      declare(activityKey, 'editing');
      const renewTimer = window.setInterval(() => {
        if (mine.has(activityKey)) declare(activityKey, 'editing');
      }, RENEW_INTERVAL_MS);
      mine.set(activityKey, { renewTimer });
    }
    return () => end(activityKey);
  }

  function end(activityKey) {
    const entry = mine.get(activityKey);
    if (!entry) return;
    if (entry.renewTimer) window.clearInterval(entry.renewTimer);
    mine.delete(activityKey);
    declare(activityKey, 'idle');
  }

  function handleConflict(detail) {
    const payload = detail?.payload || {};
    const key = payload.activity_key || '';
    const label = key ? key.replace(/^[a-z_]+:/, '').replace(/:/g, ' / ') : 'the record';
    const responder = Number(payload.responder_id) ? ` by staff #${payload.responder_id}` : '';
    if (typeof window.showNotification === 'function') {
      window.showNotification(
        `This record was saved${responder} while you were editing. Review "${label}" before saving to avoid overwriting their change.`,
        'warning',
      );
    }
    const row = key ? document.querySelector(`[data-realtime-activity="${CSS.escape(key)}"]`)?.closest('tr')
      : document.querySelector(`[data-entity-id="${CSS.escape(String(payload.entity_id ?? ''))}"]`)?.closest('tr');
    if (row) {
      row.classList.remove('rt-conflict-row');
      // Restart the flash animation even if the class is still attached.
      void row.offsetWidth;
      row.classList.add('rt-conflict-row');
    }
  }

  function init() {
    if (document.getElementById('kingsway-realtime-presence-css')) return;
    css();
    window.addEventListener('kingsway:presence', (event) => trackPeer(event.detail?.payload || {}));
    window.addEventListener('kingsway:row-conflict', (event) => handleConflict(event.detail || {}));
    // Best-effort release; the server's presence sweeper is the backstop.
    window.addEventListener('pagehide', () => {
      for (const key of [...mine.keys()]) end(key);
    }, { once: true });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
  } else {
    init();
  }

  window.RealtimePresence = Object.freeze({
    begin,
    end,
    /** Whether a PEER (not me) is currently editing this activity. */
    editingByOther: (activityKey) => peers.has(activityKey),
    peers: () => [...peers.keys()],
  });
})(window, document);
