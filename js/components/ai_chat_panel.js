/**
 * Kingsway AI chat panel — the visible staff co-worker surface.
 *
 * This component deliberately reuses the durable chat-session endpoints that
 * already exist behind /api/dashboard/assistant-session*. The older universal
 * panel only rendered one answer at a time and discarded it on the next send,
 * which broke continuity. Here every turn carries `session_id`, prior turns are
 * restored from the server transcript, and the transcript is only cleared when
 * the operator explicitly starts a new conversation.
 *
 * The panel is a non-modal workspace: it must never fight an open Bootstrap
 * modal, and it never writes to a school record.
 */
(function () {
  'use strict';

  const STYLE_ID = 'kw-ai-chat-styles';
  const FAB_ID = 'kwAiAssistFab';
  const PANEL_ID = 'kwAiAssistPanel';
  const MAX_QUESTION = 500;

  function ensureStyles() {
    if (document.getElementById(STYLE_ID)) return;
    const style = document.createElement('style');
    style.id = STYLE_ID;
    style.textContent = `
      #${FAB_ID} { position: fixed; right: 1.35rem; bottom: 1.35rem; z-index: 1080; width: 3.4rem; height: 3.4rem; border-radius: 50%; border: 0; background: linear-gradient(135deg,#1E7A4E,#155C3B 60%,#0F4A2F); color: #fff; font-size: 1.3rem; box-shadow: 0 .65rem 1.9rem rgba(15,38,28,.34), 0 0 0 .35rem rgba(46,139,87,.12); display: flex; align-items: center; justify-content: center; cursor: pointer; }
      #${FAB_ID}[hidden] { display: none !important; }
      #${FAB_ID}:hover { transform: translateY(-2px); }
      #${PANEL_ID} { position: fixed; inset: 0 0 0 auto; z-index: 1081; width: min(70rem, calc(100vw - 1.5rem)); height: 100vh; height: 100dvh; display: flex; flex-direction: column; background: #FBFCFA; border-left: 1px solid #DCE5DE; border-radius: 1.25rem 0 0 1.25rem; box-shadow: -1.5rem 0 4.5rem rgba(15,38,28,.22), -.25rem 0 .9rem rgba(15,38,28,.10); overflow: hidden; }
      #${PANEL_ID}[hidden] { display: none !important; }
      .kw-ai-head { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .9rem 1.15rem; background: linear-gradient(135deg,#1E7A4E,#155C3B 55%,#0F4A2F); color: #fff; flex: none; }
      .kw-ai-heading { display: flex; align-items: center; gap: .75rem; min-width: 0; }
      .kw-ai-mark { width: 2.2rem; height: 2.2rem; border-radius: .8rem; background: rgba(255,255,255,.14); display: grid; place-items: center; flex: none; }
      .kw-ai-heading strong { display: block; font-size: 1rem; line-height: 1.2; }
      .kw-ai-heading small { display: block; color: #C9E5D5; font-size: .75rem; margin-top: .15rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 36rem; }
      .kw-ai-head-actions { display: flex; align-items: center; gap: .35rem; flex: none; }
      .kw-ai-icon-btn { border: 1px solid rgba(255,255,255,.28); background: rgba(255,255,255,.12); color: #fff; border-radius: .65rem; width: 2.25rem; height: 2.25rem; display: grid; place-items: center; cursor: pointer; }
      .kw-ai-icon-btn:hover { background: rgba(255,255,255,.2); }
      .kw-ai-head .btn-close { filter: brightness(0) invert(1); opacity: .8; }
      .kw-ai-body-wrap { display: grid; grid-template-columns: minmax(15rem, 18.5rem) minmax(0, 1fr); flex: 1; min-height: 0; }
      #${PANEL_ID}.sidebar-closed .kw-ai-body-wrap { grid-template-columns: minmax(0, 1fr); }
      #${PANEL_ID}.sidebar-closed .kw-ai-sidebar { display: none; }
      .kw-ai-sidebar { display: flex; flex-direction: column; min-height: 0; border-right: 1px solid #E1E9E3; background: #F3F7F4; padding: .8rem; gap: .6rem; }
      .kw-ai-new { width: 100%; border: 1px solid #2E8B57; border-radius: .75rem; background: #fff; color: #155C3B; font-weight: 650; padding: .65rem .8rem; display: flex; align-items: center; justify-content: center; gap: .45rem; cursor: pointer; }
      .kw-ai-new:hover { background: #EAF5EE; }
      .kw-ai-search { width: 100%; border: 1px solid #D5E0D9; border-radius: .7rem; padding: .55rem .7rem; font-size: .85rem; background: #fff; }
      .kw-ai-history { flex: 1; min-height: 0; overflow-y: auto; display: flex; flex-direction: column; gap: .3rem; }
      .kw-ai-history-row { display: flex; align-items: stretch; gap: .2rem; border-radius: .7rem; }
      .kw-ai-history-row:hover { background: #E8F1EB; }
      .kw-ai-history-row.is-active { background: #DCEDE3; }
      .kw-ai-session-open { flex: 1; min-width: 0; border: 0; background: transparent; text-align: left; padding: .55rem .6rem; cursor: pointer; }
      .kw-ai-session-title { display: block; font-size: .84rem; color: #1B3528; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
      .kw-ai-session-meta { display: block; font-size: .7rem; color: #6E7D74; margin-top: .15rem; }
      .kw-ai-session-icon { width: 1.85rem; border: 0; background: transparent; color: #6E7D74; border-radius: .5rem; cursor: pointer; }
      .kw-ai-session-icon:hover { background: #fff; color: #B4362B; }
      .kw-ai-sidebar-foot { border-top: 1px solid #DCE5DE; padding-top: .6rem; display: flex; flex-wrap: wrap; gap: .35rem; }
      .kw-ai-sidebar-foot button { flex: 1 1 auto; border: 1px solid #D5E0D9; background: #fff; color: #3F4D45; border-radius: .6rem; font-size: .72rem; padding: .4rem .5rem; cursor: pointer; }
      .kw-ai-sidebar-foot button:hover { border-color: #2E8B57; color: #155C3B; }
      .kw-ai-main { display: flex; flex-direction: column; min-width: 0; min-height: 0; background: #FBFCFA; }
      .kw-ai-chipbar { display: flex; flex-wrap: wrap; gap: .45rem; padding: .8rem 1.15rem .35rem; flex: none; }
      .kw-ai-chipbar[hidden] { display: none !important; }
      .kw-ai-chip { border: 1px solid #CFE0D5; background: #fff; border-radius: 999rem; padding: .4rem .75rem; font-size: .78rem; color: #24503A; cursor: pointer; }
      .kw-ai-chip:hover { border-color: #2E8B57; background: #F0F7F2; }
      .kw-ai-thread { flex: 1; min-height: 0; overflow-y: auto; padding: .9rem 1.15rem 1.25rem; scroll-behavior: smooth; overscroll-behavior: contain; }
      .kw-ai-empty { max-width: 40rem; margin: 2rem auto; text-align: center; color: #5E6B64; }
      .kw-ai-empty .kw-ai-empty-mark { width: 3rem; height: 3rem; margin: 0 auto .8rem; border-radius: 1rem; background: #E8F3EC; color: #155C3B; display: grid; place-items: center; font-size: 1.3rem; }
      .kw-ai-empty h2 { font-size: 1.1rem; color: #123527; margin: 0 0 .35rem; }
      .kw-ai-empty p { font-size: .88rem; margin: 0; }
      .kw-ai-msg { display: flex; gap: .65rem; margin-bottom: 1rem; }
      .kw-ai-msg.user { justify-content: flex-end; }
      .kw-ai-avatar { width: 1.9rem; height: 1.9rem; border-radius: 50%; background: #E8F3EC; color: #155C3B; display: grid; place-items: center; flex: none; }
      .kw-ai-bubble { max-width: min(48rem, 78%); border-radius: 1rem; padding: .75rem .9rem; font-size: .92rem; line-height: 1.6; overflow-wrap: anywhere; }
      .kw-ai-msg.user .kw-ai-bubble { background: #155C3B; color: #fff; border-bottom-right-radius: .35rem; }
      .kw-ai-msg.assistant .kw-ai-bubble { background: #fff; border: 1px solid #E1E9E3; color: #1B2620; border-bottom-left-radius: .35rem; box-shadow: 0 .15rem .5rem rgba(15,38,28,.05); }
      .kw-ai-msg.assistant.has-error .kw-ai-bubble { border-color: #E4B8B2; background: #FFF7F6; color: #7E2A21; }
      .kw-ai-msg .kw-ai-bubble p:last-child, .kw-ai-msg .kw-ai-bubble ul:last-child, .kw-ai-msg .kw-ai-bubble ol:last-child { margin-bottom: 0; }
      .kw-ai-msg-stack { min-width: 0; max-width: min(48rem, 82%); flex: 1; }
      .kw-ai-msg-meta { font-size: .72rem; color: #6E7D74; margin-bottom: .25rem; display: flex; gap: .45rem; align-items: center; }
      .kw-ai-msg-actions { display: flex; flex-wrap: wrap; gap: .25rem; margin-top: .35rem; opacity: .55; }
      .kw-ai-msg:hover .kw-ai-msg-actions, .kw-ai-msg:focus-within .kw-ai-msg-actions { opacity: 1; }
      .kw-ai-msg-actions button { border: 1px solid #DCE5DE; background: #fff; color: #3F4D45; border-radius: .5rem; font-size: .7rem; padding: .25rem .45rem; cursor: pointer; }
      .kw-ai-msg-actions button:hover { border-color: #2E8B57; color: #155C3B; }
      .kw-ai-typing { display: inline-block; width: .5rem; height: .5rem; border-radius: 50%; background: #2E8B57; animation: kwAiPulse 1s ease-in-out infinite; }
      @keyframes kwAiPulse { 50% { opacity: .3; } }
      .kw-ai-rich { margin-top: .55rem; display: grid; gap: .55rem; }
      .kw-ai-chart, .kw-ai-art { margin: 0; border: 1px solid #E3E9E4; background: #fff; border-radius: .8rem; padding: .6rem .75rem; }
      .kw-ai-chart figcaption, .kw-ai-art figcaption { font-size: .75rem; font-weight: 650; color: #2D3A33; margin-bottom: .35rem; }
      .kw-ai-chart-canvas { position: relative; height: 14rem; }
      .kw-ai-art img, .kw-ai-art video { max-width: 100%; border-radius: .6rem; display: block; }
      .kw-ai-art audio { width: 100%; }
      .kw-ai-dl { display: inline-flex; align-items: center; gap: .35rem; margin-top: .45rem; font-size: .72rem; color: #155C3B; border: 1px solid #CFE0D5; border-radius: 999rem; padding: .3rem .6rem; text-decoration: none; }
      .kw-ai-composer { flex: none; border-top: 1px solid #E3E9E4; background: #fff; padding: .65rem 1.15rem .8rem; }
      .kw-ai-status { font-size: .76rem; color: #5E6B64; min-height: 1.15rem; margin-bottom: .35rem; }
      .kw-ai-status.is-busy { color: #155C3B; }
      .kw-ai-status.is-error { color: #B4362B; }
      .kw-ai-compose-row { display: flex; align-items: flex-end; gap: .55rem; }
      .kw-ai-compose-row textarea { flex: 1; min-width: 0; border: 1px solid #C9D8CF; border-radius: .9rem; padding: .7rem .8rem; font: inherit; font-size: .9rem; resize: none; max-height: 9rem; line-height: 1.45; }
      .kw-ai-compose-row textarea:focus-visible { outline: 2px solid #2E8B57; outline-offset: 1px; }
      .kw-ai-send { border: 0; border-radius: .9rem; background: linear-gradient(135deg,#1E7A4E,#155C3B); color: #fff; font-weight: 650; padding: .7rem .95rem; display: flex; align-items: center; gap: .4rem; cursor: pointer; }
      .kw-ai-send:hover { filter: brightness(1.06); }
      .kw-ai-disclaimer { font-size: .68rem; color: #7C8982; margin: .35rem 0 0; }
      .kw-ai-scrim { display: none; }
      @media (max-width: 60rem) {
        #${PANEL_ID} { width: 100vw; border-radius: 0; }
        .kw-ai-body-wrap { position: relative; grid-template-columns: minmax(0, 1fr); }
        .kw-ai-sidebar { position: absolute; inset: 0 auto 0 0; width: min(18rem, 86vw); z-index: 2; transform: translateX(-102%); transition: transform .18s ease; box-shadow: .8rem 0 2rem rgba(15,38,28,.18); }
        #${PANEL_ID}.sidebar-open .kw-ai-sidebar { transform: translateX(0); }
        #${PANEL_ID}.sidebar-closed .kw-ai-sidebar { display: flex; }
        .kw-ai-scrim:not([hidden]) { display: block; position: absolute; inset: 0; z-index: 1; background: rgba(15,38,28,.35); border: 0; }
        .kw-ai-bubble { max-width: 90%; }
        .kw-ai-msg-stack { max-width: 94%; }
      }
      @media (hover: none) { .kw-ai-msg-actions { opacity: 1; } }
      @media (prefers-reduced-motion: reduce) {
        .kw-ai-thread { scroll-behavior: auto; }
        .kw-ai-sidebar { transition: none; }
        .kw-ai-typing { animation: none; }
      }
    `;
    document.head.appendChild(style);
  }

  function resolve(ref) {
    return typeof ref === 'string' ? document.querySelector(ref) : ref || null;
  }

  function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = String(value ?? '');
    return div.innerHTML;
  }

  function renderMarkdown(text) {
    const body = String(text ?? '');
    if (window.KingswayMarkdown?.render) {
      try { return window.KingswayMarkdown.render(body); } catch (_) { /* fall through */ }
    }
    return `<p>${escapeHtml(body)}</p>`;
  }

  function answerText(result, fallback) {
    return String(
      result?.answer?.body
      || result?.answer?.title
      || result?.body
      || result?.message
      || fallback
      || ''
    );
  }

  function unwrapEnvelope(value) {
    let out = value;
    for (let i = 0; i < 3; i += 1) {
      if (out && typeof out === 'object' && !Array.isArray(out)
        && typeof out.success === 'boolean' && 'data' in out) {
        out = out.data;
        continue;
      }
      break;
    }
    return out;
  }

  function normalizeRoute(route) {
    const raw = String(route || window.REQUESTED_ROUTE || '').trim();
    if (!raw) return '';
    return raw.replace(/^\/+/, '').replace(/\.php$/i, '').split(/[?#]/)[0];
  }

  function currentRoute() {
    return normalizeRoute(window.REQUESTED_ROUTE || location.pathname);
  }

  function routeLabel() {
    return (currentRoute() || 'this page').replace(/_/g, ' ');
  }

  function userKey() {
    const user = window.AuthContext?.getUser?.() || {};
    return String(user.id || user.user_id || user.username || 'anon');
  }

  function activeStorageKey() {
    return `kw_ai_chat_active_${userKey()}`;
  }

  function memoryStorageKey() {
    const user = window.AuthContext?.getUser?.() || {};
    return `kw_ai_ctx_${userKey()}_${user.role_id || user.role || 'role'}`;
  }

  function rememberActive(id) {
    try {
      if (id) localStorage.setItem(activeStorageKey(), String(id));
      else localStorage.removeItem(activeStorageKey());
    } catch (_) { /* storage unavailable */ }
  }

  function loadRememberedActive() {
    try { return localStorage.getItem(activeStorageKey()) || null; } catch (_) { return null; }
  }

  function loadUserMemory() {
    try { return JSON.parse(localStorage.getItem(memoryStorageKey()) || '{}') || {}; }
    catch (_) { return {}; }
  }

  function saveUserMemory(patch) {
    try {
      const current = loadUserMemory();
      localStorage.setItem(memoryStorageKey(), JSON.stringify({ ...current, ...patch, updated_at: Date.now() }));
    } catch (_) { /* storage unavailable */ }
  }

  function clearUserMemory() {
    try { localStorage.removeItem(memoryStorageKey()); } catch (_) { /* storage unavailable */ }
    try { window.API?.dashboard?.clearAgentContext?.(); } catch (_) { /* optional */ }
  }

  function mount() {
    if (window.__kwAiChatMounted) return;
    window.__kwAiChatMounted = true;
    ensureStyles();

    const fab = document.createElement('button');
    fab.id = FAB_ID;
    fab.type = 'button';
    fab.title = 'Open AI chat';
    fab.setAttribute('aria-label', 'Open AI chat');
    fab.innerHTML = '<i class="bi bi-stars" aria-hidden="true"></i>';

    const panel = document.createElement('div');
    panel.id = PANEL_ID;
    panel.hidden = true;
    panel.setAttribute('aria-label', 'AI co-worker chat');
    panel.innerHTML = `
      <div class="kw-ai-head">
        <div class="kw-ai-heading">
          <span class="kw-ai-mark" aria-hidden="true"><i class="bi bi-stars"></i></span>
          <span><strong>AI co-worker</strong><small id="kwAiSessionTitle">New chat · ${escapeHtml(routeLabel())}</small></span>
        </div>
        <div class="kw-ai-head-actions">
          <button type="button" class="kw-ai-icon-btn" id="kwAiHistoryToggle" aria-controls="kwAiSidebar" aria-expanded="true" title="Toggle conversation history" aria-label="Toggle conversation history"><i class="bi bi-list" aria-hidden="true"></i></button>
          <button type="button" class="btn-close btn-close-white" id="kwAiClose" aria-label="Close AI chat"></button>
        </div>
      </div>
      <div class="kw-ai-body-wrap">
        <button type="button" class="kw-ai-scrim" id="kwAiScrim" aria-label="Close conversation history" hidden></button>
        <aside class="kw-ai-sidebar" id="kwAiSidebar" aria-label="Conversation history">
          <button type="button" class="kw-ai-new" id="kwAiNewChat"><i class="bi bi-plus-lg" aria-hidden="true"></i>New chat</button>
          <label class="visually-hidden" for="kwAiSessionSearch">Search conversations</label>
          <input class="kw-ai-search" id="kwAiSessionSearch" type="search" placeholder="Search chats…" autocomplete="off">
          <div class="kw-ai-history" id="kwAiHistory" aria-label="Previous conversations"></div>
          <div class="kw-ai-sidebar-foot">
            <button type="button" id="kwAiExport">Export transcript</button>
            <button type="button" id="kwAiClearCtx">Reset context</button>
          </div>
        </aside>
        <section class="kw-ai-main" aria-label="Conversation">
          <div class="kw-ai-chipbar" id="kwAiChips" aria-label="Suggested questions"></div>
          <div class="kw-ai-thread" id="kwAiThread" role="log" aria-live="polite" aria-relevant="additions text"></div>
          <div class="kw-ai-composer">
            <div class="kw-ai-status" id="kwAiStatus" role="status" aria-live="polite">Ready.</div>
            <form class="kw-ai-compose-row" id="kwAiForm" novalidate>
              <label class="visually-hidden" for="kwAiInput">Message the AI co-worker</label>
              <textarea id="kwAiInput" rows="1" maxlength="500" placeholder="Ask about this page or your school work…"></textarea>
              <button type="submit" class="kw-ai-send" id="kwAiSend"><i class="bi bi-send-fill" aria-hidden="true"></i><span>Send</span></button>
            </form>
            <p class="kw-ai-disclaimer">Answers are advisory. Nothing is written to a school record without your confirmation.</p>
          </div>
        </section>
      </div>`;

    document.body.appendChild(fab);
    document.body.appendChild(panel);

    const $ = (id) => panel.querySelector('#' + id);
    const $$ = (selector) => Array.from(panel.querySelectorAll(selector));
    const thread = $('kwAiThread');
    const input = $('kwAiInput');
    const sendButton = $('kwAiSend');
    const historyHost = $('kwAiHistory');
    const chips = $('kwAiChips');
    const status = $('kwAiStatus');
    const toggle = $('kwAiHistoryToggle');
    const scrim = $('kwAiScrim');

    const state = {
      sessions: [],
      filter: '',
      activeId: null,
      messages: [],
      busy: false,
      stream: null,
      generation: 0,
      hydrated: false,
      sidebarOpen: true,
      backendSuggestions: [],
      suggestionsFor: '',
      chartSpecs: [],
      chartInstances: [],
      lastEditable: null,
    };

    const isNarrow = () => !!(window.matchMedia && window.matchMedia('(max-width: 60rem)').matches);

    function setStatus(text, kind = '') {
      status.textContent = String(text || '');
      status.classList.toggle('is-busy', kind === 'busy');
      status.classList.toggle('is-error', kind === 'error');
    }

    function setBusy(on) {
      state.busy = !!on;
      sendButton.querySelector('i').className = on ? 'bi bi-stop-fill' : 'bi bi-send-fill';
      sendButton.querySelector('span').textContent = on ? 'Stop' : 'Send';
      sendButton.title = on ? 'Stop generating' : 'Send message';
      sendButton.setAttribute('aria-label', on ? 'Stop generating' : 'Send message');
      setStatus(on ? 'Working…' : 'Ready.', on ? 'busy' : '');
    }

    function scrollThread(force = false) {
      const nearBottom = thread.scrollHeight - thread.scrollTop - thread.clientHeight < 140;
      if (force || nearBottom) thread.scrollTop = thread.scrollHeight;
    }

    function setSidebar(open, moveFocus = false) {
      state.sidebarOpen = !!open;
      panel.classList.toggle('sidebar-open', state.sidebarOpen);
      panel.classList.toggle('sidebar-closed', !state.sidebarOpen);
      toggle.setAttribute('aria-expanded', String(state.sidebarOpen));
      scrim.hidden = !(isNarrow() && state.sidebarOpen);
      if (moveFocus && state.sidebarOpen) historyHost.querySelector('.kw-ai-session-open')?.focus();
    }

    function setHeaderTitle() {
      const active = state.sessions.find((s) => String(s.id) === String(state.activeId));
      const title = active?.title || (state.activeId ? 'Conversation' : 'New chat');
      $('kwAiSessionTitle').textContent = `${title} · ${routeLabel()}`;
    }

    function renderGreeting() {
      state.chartSpecs = [];
      thread.innerHTML = `
        <div class="kw-ai-empty">
          <div class="kw-ai-empty-mark" aria-hidden="true"><i class="bi bi-stars"></i></div>
          <h2>What would you like to work through?</h2>
          <p>Ask about ${escapeHtml(routeLabel())}, or continue a previous conversation from the history panel.</p>
        </div>`;
    }

    function localSuggestions() {
      const route = currentRoute();
      const out = [];
      document.querySelectorAll('[data-ai-suggestion]').forEach((node) => {
        const label = String(node.getAttribute('data-ai-suggestion') || '').trim();
        const prompt = String(node.getAttribute('data-ai-suggestion-prompt') || label).trim();
        if (label && prompt) out.push({ label: label.slice(0, 80), prompt: prompt.slice(0, MAX_QUESTION) });
      });
      const external = window.KW_AI_CONTEXT?.suggestions || window.AI_CHAT_SUGGESTIONS;
      if (Array.isArray(external)) {
        external.forEach((item) => {
          const label = String(item?.label || item?.title || '').trim();
          const prompt = String(item?.prompt || label).trim();
          if (label && prompt) out.push({ label: label.slice(0, 80), prompt: prompt.slice(0, MAX_QUESTION) });
        });
      }
      if (route === 'term_transition') {
        const stateNode = document.getElementById('ttStatePanel') || document.querySelector('[data-ai-page-state]');
        const key = String(
          stateNode?.dataset?.state
          || stateNode?.dataset?.aiPageState
          || window.termTransitionController?.state?.handover?.key
          || ''
        );
        const byState = {
          waiting: [
            ['Why are we waiting?', 'Explain why the handover is waiting for the automatic switch and what date it will happen.'],
            ['Check before cutover', 'List the checks a school administrator should complete before the automatic cutover.'],
          ],
          schedule: [
            ['What is missing?', 'Explain what is missing before the year transition can be scheduled, using only the current page state.'],
            ['Scheduling risks', 'What should be verified before scheduling this transition? Do not write any records.'],
          ],
          holiday: [
            ['Holiday handover', 'Which completed and incoming terms does this holiday handover show, and why?'],
            ['Boarder implications', 'Explain what the holiday state means for boarders and weekend attendance.'],
          ],
          pair: [
            ['Verify this pair', 'Summarize the outgoing and incoming term pair and list the evidence to verify before confirmation.'],
            ['Cutover meaning', 'Explain the cutover date and what changes when it is reached.'],
          ],
          error: [
            ['Read failure', 'Why could the handover state not be read, and what should be checked first?'],
          ],
        };
        (byState[key] || []).forEach(([label, prompt]) => out.push({ label, prompt }));
      }
      return out;
    }

    function roleKeys() {
      const keys = new Set();
      try {
        const user = window.AuthContext?.getUser?.() || {};
        const add = (value) => { const s = String(value ?? '').trim(); if (s) keys.add(s); };
        (user.role_ids || user.roles_ids || []).forEach(add);
        (user.roles || []).forEach((role) => {
          if (role && typeof role === 'object') { add(role.id); add(role.role_id); add(role.name); }
          else add(role);
        });
        (window.AuthContext?.getRoles?.() || []).forEach(add);
        add(user.role); add(user.role_id); add(user.role_name);
      } catch (_) { /* guest */ }
      return Array.from(keys);
    }

    function fallbackSuggestions() {
      // Term Transition suggestions must describe the live handover state, not
      // generic role tasks that read like permanent action buttons.
      if (currentRoute() === 'term_transition') return [];
      if (!window.AiInlineDraft?.__debug?.pageTasksFor) return [];
      const tasks = window.AiInlineDraft.__debug.pageTasksFor(currentRoute(), roleKeys());
      return tasks.slice(0, 5).map((task) => {
        const label = String(task?.label || task?.[0] || '').trim();
        const prompt = String(task?.prompt || task?.[1] || label).trim();
        return { label, prompt };
      }).filter((task) => task.label && task.prompt);
    }

    function renderChips() {
      const seen = new Set();
      const all = [...localSuggestions(), ...state.backendSuggestions, ...fallbackSuggestions()];
      const useful = all.filter((item) => {
        const key = String(item?.prompt || '').trim().toLowerCase();
        if (!key || seen.has(key)) return false;
        seen.add(key);
        return true;
      }).slice(0, 6);
      chips.innerHTML = '';
      useful.forEach((item) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'kw-ai-chip';
        button.textContent = String(item.label).slice(0, 90);
        button.title = String(item.prompt);
        button.addEventListener('click', () => {
          input.value = String(item.prompt).slice(0, MAX_QUESTION);
          input.focus();
          growInput();
        });
        chips.appendChild(button);
      });
      chips.hidden = useful.length === 0;
    }

    async function refreshSuggestions(force = false) {
      const route = currentRoute();
      if (!force && state.suggestionsFor === route) { renderChips(); return; }
      state.suggestionsFor = route;
      const api = window.API?.dashboard?.agentSuggest;
      if (typeof api !== 'function') { renderChips(); return; }
      try {
        const response = await api(route, 'dashboard', collectPageContext());
        const payload = unwrapEnvelope(response);
        const list = Array.isArray(payload?.suggestions) ? payload.suggestions : [];
        state.backendSuggestions = list.map((item) => ({
          label: String(item?.title || item?.label || '').slice(0, 90),
          prompt: String(item?.prompt || '').slice(0, MAX_QUESTION),
        })).filter((item) => item.label && item.prompt);
      } catch (_) {
        state.backendSuggestions = [];
      }
      renderChips();
    }

    function collectPageContext() {
      const base = window.AiInlineDraft?.__debug?.getPageContext?.() || { route: currentRoute() };
      const stateNode = document.getElementById('ttStatePanel') || document.querySelector('[data-ai-page-state]');
      const pageState = String(
        stateNode?.dataset?.state
        || stateNode?.dataset?.aiPageState
        || window.termTransitionController?.state?.handover?.key
        || ''
      );
      return {
        ...base,
        route: base.route || currentRoute(),
        ...(pageState ? { page_state: pageState } : {}),
      };
    }

    function messageRecord(message) {
      const role = String(message?.role || 'assistant').toLowerCase() === 'user' ? 'user' : 'assistant';
      const meta = message?.meta && typeof message.meta === 'object' ? message.meta : {};
      return {
        role,
        content: String(message?.content || ''),
        created_at: Number(message?.created_at || 0) || Math.floor(Date.now() / 1000),
        agent_name: String(message?.agent?.name || message?.agent_name || meta.agent_name || '').trim(),
        charts: Array.isArray(message?.charts) ? message.charts : (Array.isArray(meta.charts) ? meta.charts : []),
        artifacts: Array.isArray(message?.artifacts) ? message.artifacts : (Array.isArray(meta.artifacts) ? meta.artifacts : []),
        pending: false,
        error: false,
      };
    }

    function richHtml(message) {
      const parts = [];
      (message.charts || []).slice(0, 3).forEach((item) => {
        const spec = item?.chart || item;
        if (!spec || typeof window.Chart !== 'function') return;
        const index = state.chartSpecs.push(spec) - 1;
        parts.push(`<figure class="kw-ai-chart"><figcaption>${escapeHtml(String(spec.title || 'Chart'))}</figcaption><div class="kw-ai-chart-canvas" data-chart-index="${index}"><canvas></canvas></div></figure>`);
      });
      (message.artifacts || []).slice(0, 4).forEach((item) => {
        if (!item || typeof item !== 'object') return;
        const url = String(item.url || item.image || '');
        const mime = String(item.mime_type || '').toLowerCase();
        const title = String(item.title || 'Attachment').slice(0, 120);
        const download = url
          ? `<a class="kw-ai-dl" href="${escapeHtml(url)}" download="${escapeHtml(String(item.download_name || 'download'))}"><i class="bi bi-download" aria-hidden="true"></i>Download</a>`
          : '';
        let inner = '';
        if (url && (item.image || mime.startsWith('image/'))) inner = `<img src="${escapeHtml(url)}" alt="${escapeHtml(title)}" loading="lazy">`;
        else if (url && mime.startsWith('audio/')) inner = `<audio controls preload="none" src="${escapeHtml(url)}"></audio>`;
        else if (url && mime.startsWith('video/')) inner = `<video controls preload="none" src="${escapeHtml(url)}"></video>`;
        parts.push(`<figure class="kw-ai-art"><figcaption>${escapeHtml(title)}</figcaption>${inner}${download}</figure>`);
      });
      return parts.length ? `<div class="kw-ai-rich">${parts.join('')}</div>` : '';
    }

    function paintCharts() {
      state.chartInstances.forEach((chart) => { try { chart.destroy(); } catch (_) { /* noop */ } });
      state.chartInstances = [];
      $$('.kw-ai-chart-canvas').forEach((canvasHost) => {
        const index = Number(canvasHost.getAttribute('data-chart-index'));
        const spec = state.chartSpecs[index];
        if (!spec || typeof window.Chart !== 'function') return;
        const type = spec.type === 'area' ? 'line' : spec.type;
        if (!['bar', 'line', 'pie', 'doughnut'].includes(type)) return;
        const chart = new window.Chart(canvasHost.querySelector('canvas'), {
          type,
          data: {
            labels: (Array.isArray(spec.labels) ? spec.labels : []).slice(0, 25).map((v) => String(v).slice(0, 120)),
            datasets: (Array.isArray(spec.datasets) ? spec.datasets : []).slice(0, 4).map((d) => ({
              label: String(d?.label || 'Values').slice(0, 80),
              data: (Array.isArray(d?.data) ? d.data : []).slice(0, 25).map((v) => (Number.isFinite(Number(v)) ? Number(v) : null)),
            })),
          },
          options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } },
        });
        state.chartInstances.push(chart);
      });
    }

    function messageHtml(message, index) {
      if (message.role === 'user') {
        return `<div class="kw-ai-msg user" data-message-index="${index}"><div class="kw-ai-bubble">${escapeHtml(message.content)}</div></div>`;
      }
      const label = message.agent_name || 'AI co-worker';
      const actions = message.pending ? '' : `
        <div class="kw-ai-msg-actions" aria-label="Answer actions">
          <button type="button" data-chat-action="copy" aria-label="Copy this answer"><i class="bi bi-clipboard" aria-hidden="true"></i> Copy</button>
          <button type="button" data-chat-action="insert" aria-label="Insert this answer into a field"><i class="bi bi-input-text" aria-hidden="true"></i> Insert</button>
          <button type="button" data-chat-action="share" aria-label="Share this answer"><i class="bi bi-share" aria-hidden="true"></i> Share</button>
          <button type="button" data-chat-action="again" aria-label="Ask this question again"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i> Ask again</button>
        </div>`;
      const body = message.pending && !message.content
        ? '<span class="kw-ai-typing" aria-hidden="true"></span> Preparing an answer…'
        : renderMarkdown(message.content);
      return `
        <div class="kw-ai-msg assistant${message.error ? ' has-error' : ''}" data-message-index="${index}">
          <span class="kw-ai-avatar" aria-hidden="true"><i class="bi bi-stars"></i></span>
          <div class="kw-ai-msg-stack">
            <div class="kw-ai-msg-meta">${escapeHtml(label)}</div>
            <div class="kw-ai-bubble">${body}</div>
            ${richHtml(message)}
            ${actions}
          </div>
        </div>`;
    }

    function renderThread() {
      state.chartSpecs = [];
      if (!state.messages.length) {
        renderGreeting();
        return;
      }
      thread.innerHTML = state.messages.map(messageHtml).join('');
      paintCharts();
      scrollThread();
    }

    function updateMessage(index) {
      const node = thread.querySelector(`[data-message-index="${index}"]`);
      if (!node) { renderThread(); return; }
      const message = state.messages[index];
      if (!message) return;
      if (message.role === 'user') {
        node.querySelector('.kw-ai-bubble').textContent = message.content;
        return;
      }
      const bubble = node.querySelector('.kw-ai-bubble');
      if (bubble) bubble.innerHTML = message.pending && !message.content
        ? '<span class="kw-ai-typing" aria-hidden="true"></span> Preparing an answer…'
        : renderMarkdown(message.content);
      node.classList.toggle('has-error', !!message.error);
      if (!message.pending && !node.querySelector('.kw-ai-msg-actions')) {
        node.querySelector('.kw-ai-msg-stack')?.insertAdjacentHTML('beforeend', `
          <div class="kw-ai-msg-actions" aria-label="Answer actions">
            <button type="button" data-chat-action="copy" aria-label="Copy this answer"><i class="bi bi-clipboard" aria-hidden="true"></i> Copy</button>
            <button type="button" data-chat-action="insert" aria-label="Insert this answer into a field"><i class="bi bi-input-text" aria-hidden="true"></i> Insert</button>
            <button type="button" data-chat-action="share" aria-label="Share this answer"><i class="bi bi-share" aria-hidden="true"></i> Share</button>
            <button type="button" data-chat-action="again" aria-label="Ask this question again"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i> Ask again</button>
          </div>`);
      }
      scrollThread();
    }

    function formatSessionDate(value) {
      const stamp = Number(value);
      if (!stamp) return '';
      try {
        const date = new Date(stamp > 1e12 ? stamp : stamp * 1000);
        return date.toLocaleDateString([], { month: 'numeric', day: 'numeric' });
      } catch (_) { return ''; }
    }

    function renderSessions() {
      const query = state.filter.trim().toLowerCase();
      const sessions = state.sessions.filter((session) => {
        const haystack = `${session.title || ''} ${session.route || ''}`.toLowerCase();
        return !query || haystack.includes(query);
      });
      if (!sessions.length) {
        historyHost.innerHTML = `<p class="small text-muted px-2 mb-0">${state.sessions.length ? 'No matching conversations.' : 'Your conversations will appear here.'}</p>`;
        return;
      }
      historyHost.innerHTML = sessions.map((session) => {
        const id = String(session.id);
        const title = String(session.title || 'New chat');
        const meta = [formatSessionDate(session.updated_at || session.created_at), session.route?.replace(/_/g, ' ')].filter(Boolean).join(' · ');
        const active = id === String(state.activeId);
        return `
          <div class="kw-ai-history-row${active ? ' is-active' : ''}">
            <button type="button" class="kw-ai-session-open" data-session-id="${escapeHtml(id)}" title="${escapeHtml(title)}">
              <span class="kw-ai-session-title">${escapeHtml(title)}</span>
              ${meta ? `<span class="kw-ai-session-meta">${escapeHtml(meta)}</span>` : ''}
            </button>
            <button type="button" class="kw-ai-session-icon" data-session-rename="${escapeHtml(id)}" title="Rename conversation" aria-label="Rename ${escapeHtml(title)}"><i class="bi bi-pencil" aria-hidden="true"></i></button>
            <button type="button" class="kw-ai-session-icon" data-session-delete="${escapeHtml(id)}" title="Delete conversation" aria-label="Delete ${escapeHtml(title)}"><i class="bi bi-trash" aria-hidden="true"></i></button>
          </div>`;
      }).join('');
    }

    async function loadSessions() {
      const api = window.API?.dashboard?.getAssistantSessions;
      if (typeof api !== 'function') return [];
      try {
        const response = await api({ limit: 50 });
        const list = Array.isArray(response)
          ? response
          : (Array.isArray(response?.data) ? response.data : []);
        state.sessions = list;
      } catch (_) {
        state.sessions = [];
      }
      renderSessions();
      setHeaderTitle();
      return state.sessions;
    }

    async function openSession(sessionId, options = {}) {
      const api = window.API?.dashboard?.getAssistantSession;
      if (typeof api !== 'function' || !sessionId) return;
      if (state.busy) stopGeneration();
      try {
        const response = await api({ session_id: sessionId, limit: 200 });
        const session = unwrapEnvelope(response);
        if (!session || typeof session !== 'object') return;
        state.activeId = String(sessionId);
        rememberActive(state.activeId);
        state.messages = (Array.isArray(session.messages) ? session.messages : []).map(messageRecord);
        renderThread();
        renderSessions();
        setHeaderTitle();
        if (!options.silent) setStatus(`Resumed “${session.title || 'Conversation'}”.`);
        if (isNarrow()) setSidebar(false);
        scrollThread(true);
      } catch (_) {
        if (!options.silent) setStatus('That conversation could not be opened.', 'error');
      }
    }

    function newSession() {
      if (state.busy) stopGeneration();
      state.activeId = null;
      state.messages = [];
      rememberActive(null);
      renderThread();
      renderSessions();
      setHeaderTitle();
      setStatus('New chat. Previous conversations are still in the history panel.');
      if (isNarrow()) setSidebar(false);
      input.focus();
    }

    async function ensureSession(firstQuestion) {
      if (state.activeId) return state.activeId;
      const api = window.API?.dashboard?.postAssistantSession;
      if (typeof api !== 'function') return null;
      try {
        const response = await api({
          route: currentRoute(),
          title: String(firstQuestion || '').trim().slice(0, 80),
        });
        const session = unwrapEnvelope(response);
        if (session?.id) {
          state.activeId = String(session.id);
          rememberActive(state.activeId);
          state.sessions = [session, ...state.sessions.filter((s) => String(s.id) !== state.activeId)];
          renderSessions();
          setHeaderTitle();
          return state.activeId;
        }
      } catch (_) { /* the turn still works; the engine creates a session */ }
      return null;
    }

    async function renameSession(sessionId) {
      const session = state.sessions.find((s) => String(s.id) === String(sessionId));
      const next = window.prompt?.('Rename this conversation', String(session?.title || ''));
      if (!next || !String(next).trim()) return;
      try {
        await window.API?.dashboard?.patchAssistantSession?.({ session_id: sessionId, title: String(next).trim().slice(0, 80) });
        await loadSessions();
        setStatus('Conversation renamed.');
      } catch (_) {
        setStatus('The conversation could not be renamed.', 'error');
      }
    }

    async function deleteSession(sessionId) {
      if (!window.confirm?.('Delete this conversation permanently?')) return;
      try {
        await window.API?.dashboard?.deleteAssistantSession?.({ session_id: sessionId });
        state.sessions = state.sessions.filter((s) => String(s.id) !== String(sessionId));
        if (String(state.activeId) === String(sessionId)) newSession();
        renderSessions();
        setHeaderTitle();
        setStatus('Conversation deleted.');
      } catch (_) {
        setStatus('The conversation could not be deleted.', 'error');
      }
    }

    function growInput() {
      input.style.height = 'auto';
      input.style.height = `${Math.min(input.scrollHeight, 144)}px`;
    }

    function currentQuestionFor(index) {
      for (let i = index - 1; i >= 0; i -= 1) {
        if (state.messages[i]?.role === 'user') return state.messages[i].content;
      }
      return '';
    }

    async function copyText(text) {
      try {
        await navigator.clipboard.writeText(text);
        setStatus('Answer copied.');
      } catch (_) {
        setStatus('Copy is not available in this browser.', 'error');
      }
    }

    function insertText(text) {
      const target = state.lastEditable && document.contains(state.lastEditable) ? state.lastEditable : null;
      if (!target || !text) {
        setStatus('Click into a text field first, then use Insert.', 'error');
        return;
      }
      target.focus();
      if (target.isContentEditable) {
        try { document.execCommand('insertText', false, text); }
        catch (_) { target.textContent = `${target.textContent || ''}\n${text}`; }
      } else {
        const value = String(target.value || '');
        target.value = value ? `${value}\n${text}` : text;
        target.dispatchEvent(new Event('input', { bubbles: true }));
        target.dispatchEvent(new Event('change', { bubbles: true }));
      }
      setStatus('Answer inserted — review it where it landed.');
    }

    async function shareText(text) {
      const title = 'Kingsway AI co-worker answer';
      if (navigator.share) {
        try {
          await navigator.share({ title, text: String(text).slice(0, 2000) });
          setStatus('Answer shared.');
          return;
        } catch (error) {
          if (error?.name === 'AbortError') return;
        }
      }
      await copyText(text);
      if (!String(text || '')) return;
      setStatus('Sharing is unavailable here — the answer was copied instead.');
    }

    function downloadTranscript() {
      if (!state.messages.length) { setStatus('There is no transcript to export yet.'); return; }
      const lines = state.messages.map((message) => `${message.role.toUpperCase()}: ${message.content}`).join('\n\n');
      const blob = new Blob([`Kingsway AI co-worker transcript\nRoute: ${currentRoute()}\n\n${lines}\n`], { type: 'text/plain;charset=utf-8' });
      const url = URL.createObjectURL(blob);
      const anchor = document.createElement('a');
      anchor.href = url;
      anchor.download = `kingsway-ai-${state.activeId || 'new-chat'}.txt`;
      document.body.appendChild(anchor);
      anchor.click();
      anchor.remove();
      setTimeout(() => URL.revokeObjectURL(url), 1000);
      setStatus('Transcript downloaded.');
    }

    function stopGeneration() {
      if (!state.busy) return;
      state.generation += 1;
      try { state.stream?.cancel?.(); } catch (_) { /* already settled */ }
      state.stream = null;
      const index = state.messages.length - 1;
      const last = state.messages[index];
      if (last?.role === 'assistant' && last.pending) {
        last.pending = false;
        last.content = last.content ? `${last.content}\n\n_Stopped._` : '_Stopped._';
        updateMessage(index);
      }
      setBusy(false);
      setStatus('Stopped. The partial answer is kept.');
    }

    async function send(rawQuestion) {
      if (state.busy) return;
      const question = String(rawQuestion || '').trim().slice(0, MAX_QUESTION);
      if (!question) { setStatus('Type a question first.'); input.focus(); return; }
      if (question.startsWith('/')) {
        const command = question.slice(1).split(/\s+/)[0]?.toLowerCase();
        if (command === 'new') { newSession(); return; }
        if (command === 'help') {
          state.messages.push(messageRecord({ role: 'assistant', content: 'Commands: `/new` starts a chat, `/clear` empties this chat, `/help` shows this note. Ordinary questions continue the current conversation.' }));
          renderThread();
          setStatus('Command help added.');
          return;
        }
        if (command === 'clear') {
          if (state.activeId) {
            try { await window.API?.dashboard?.clearAssistantSessionMessages?.({ session_id: state.activeId }); } catch (_) { /* keep UI usable */ }
          }
          state.messages = [];
          renderThread();
          setStatus('This conversation was cleared.');
          return;
        }
      }

      const api = window.API?.dashboard?.agentAssistStream;
      if (typeof api !== 'function') { setStatus('The assistant is not available right now.', 'error'); return; }

      await ensureSession(question);
      const generation = ++state.generation;
      const isCurrent = () => generation === state.generation;

      state.messages.push(messageRecord({ role: 'user', content: question }));
      const answerIndex = state.messages.length;
      state.messages.push({
        role: 'assistant', content: '', created_at: Math.floor(Date.now() / 1000),
        agent_name: '', charts: [], artifacts: [], pending: true, error: false,
      });
      renderThread();
      scrollThread(true);
      input.value = '';
      growInput();
      setBusy(true);

      const memory = loadUserMemory();
      const recent = Array.isArray(memory.recent) ? memory.recent.slice(-6) : [];
      saveUserMemory({
        last_route: currentRoute(),
        last_prompt: question.slice(0, 200),
        recent: [...recent, { route: currentRoute(), q: question.slice(0, 160), at: Date.now() }].slice(-10),
      });

      let full = '';
      let settled = false;
      const finish = (result, errorText) => {
        if (settled || !isCurrent()) return;
        settled = true;
        const message = state.messages[answerIndex];
        if (!message) return;
        message.pending = false;
        message.content = errorText || answerText(result, full);
        message.error = !!errorText;
        message.charts = Array.isArray(result?.charts) ? result.charts : [];
        message.artifacts = Array.isArray(result?.artifacts) ? result.artifacts : [];
        message.agent_name = String(result?.agent?.name || '').trim();
        if (result?.session_id && !state.activeId) {
          state.activeId = String(result.session_id);
          rememberActive(state.activeId);
        }
        updateMessage(answerIndex);
        setBusy(false);
        setStatus(errorText ? (errorText.slice(0, 160) || 'The answer could not be completed.') : 'Answer ready. Use the actions under it to copy, insert or share.', errorText ? 'error' : '');
        saveUserMemory({ last_answer: message.content.slice(0, 400) });
        void loadSessions();
        void refreshSuggestions(true);
      };

      const context = collectPageContext();
      const route = context.route || currentRoute();
      state.stream = api(question, route, 'dashboard', {
        session_id: state.activeId || undefined,
        page_context: context,
        user_memory: { last_route: memory.last_route, recent },
        onDelta: (text) => {
          if (!isCurrent() || settled) return;
          full += String(text || '');
          state.messages[answerIndex].content = full;
          updateMessage(answerIndex);
        },
        onStatus: (text) => {
          if (!isCurrent() || settled) return;
          setStatus(String(text || 'Working…').slice(0, 180), 'busy');
        },
        onFinal: (result) => finish(result, ''),
        onError: (message) => finish(null, String(message || 'The answer could not be completed.')),
      });
    }

    function openPanel(prefill) {
      panel.hidden = false;
      fab.hidden = true;
      document.body.classList.add('kw-ai-open');
      $('kwAiSessionTitle').textContent = `${state.activeId ? 'Conversation' : 'New chat'} · ${routeLabel()}`;
      renderChips();
      if (!state.hydrated) {
        state.hydrated = true;
        void loadSessions().then(() => {
          const remembered = loadRememberedActive();
          if (remembered) void openSession(remembered, { silent: true });
          else { renderThread(); setHeaderTitle(); }
        });
      }
      void refreshSuggestions();
      if (prefill) input.value = String(prefill).slice(0, MAX_QUESTION);
      growInput();
      input.focus();
      scrollThread(true);
    }

    function closePanel() {
      panel.hidden = true;
      fab.hidden = false;
      document.body.classList.remove('kw-ai-open');
      if (isNarrow()) setSidebar(false);
      fab.focus();
    }

    function togglePanel() {
      if (panel.hidden) openPanel();
      else closePanel();
    }

    document.addEventListener('focusin', (event) => {
      const target = event.target;
      if (!target || target.closest?.(`#${PANEL_ID}`)) return;
      if (target.matches?.('textarea, input:not([type="checkbox"]):not([type="radio"]):not([type="file"]):not([type="hidden"]):not([type="button"]):not([type="submit"]), [contenteditable="true"]')) {
        state.lastEditable = target;
      }
    }, true);

    fab.addEventListener('click', togglePanel);
    document.addEventListener('click', (event) => {
      const trigger = event.target?.closest?.('[data-kw-ai-toggle]');
      if (!trigger) return;
      event.preventDefault();
      togglePanel();
    });
    $('kwAiClose').addEventListener('click', closePanel);
    toggle.addEventListener('click', () => {
      setSidebar(!state.sidebarOpen, true);
      if (isNarrow() && state.sidebarOpen) historyHost.querySelector('.kw-ai-session-open')?.focus();
      else input.focus();
    });
    scrim.addEventListener('click', () => setSidebar(false));

    $('kwAiNewChat').addEventListener('click', newSession);
    $('kwAiSessionSearch').addEventListener('input', (event) => {
      state.filter = event.target.value || '';
      renderSessions();
    });
    historyHost.addEventListener('click', (event) => {
      const open = event.target.closest('[data-session-id]');
      if (open) { void openSession(open.getAttribute('data-session-id')); return; }
      const rename = event.target.closest('[data-session-rename]');
      if (rename) { void renameSession(rename.getAttribute('data-session-rename')); return; }
      const remove = event.target.closest('[data-session-delete]');
      if (remove) void deleteSession(remove.getAttribute('data-session-delete'));
    });
    $('kwAiExport').addEventListener('click', downloadTranscript);
    $('kwAiClearCtx').addEventListener('click', () => {
      clearUserMemory();
      setStatus('Remembered writing preferences and recent prompts were reset. This chat is unchanged.');
    });

    input.addEventListener('input', growInput);
    input.addEventListener('keydown', (event) => {
      // IME-safe: composition and keyCode 229 must never submit a draft.
      if (event.isComposing || event.keyCode === 229) return;
      if (event.key === 'Escape' && state.busy) { event.preventDefault(); stopGeneration(); return; }
      if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        $('kwAiForm').requestSubmit?.();
      }
    });
    $('kwAiForm').addEventListener('submit', (event) => {
      event.preventDefault();
      if (state.busy) { stopGeneration(); return; }
      void send(input.value);
    });

    thread.addEventListener('click', (event) => {
      const button = event.target.closest('[data-chat-action]');
      if (!button) return;
      const row = button.closest('[data-message-index]');
      const index = Number(row?.getAttribute('data-message-index'));
      const message = state.messages[index];
      if (!message || message.role !== 'assistant') return;
      const action = button.getAttribute('data-chat-action');
      if (action === 'copy') void copyText(message.content);
      else if (action === 'insert') insertText(message.content);
      else if (action === 'share') void shareText(message.content);
      else if (action === 'again') {
        const question = currentQuestionFor(index);
        if (!question) { setStatus('The original question is not in this transcript.', 'error'); return; }
        if (state.busy) stopGeneration();
        void send(question);
      }
    });
    thread.addEventListener('load', () => scrollThread(), true);

    window.addEventListener('kingsway:page-loaded', () => {
      $('kwAiSessionTitle').textContent = `${state.activeId ? 'Conversation' : 'New chat'} · ${routeLabel()}`;
      void refreshSuggestions(true);
    });
    let wasNarrow = isNarrow();
    window.addEventListener('resize', () => {
      growInput();
      const narrow = isNarrow();
      if (narrow !== wasNarrow) {
        wasNarrow = narrow;
        setSidebar(!narrow);
      } else {
        setSidebar(state.sidebarOpen);
      }
    });

    setSidebar(!isNarrow());
    renderGreeting();
    growInput();
    setBusy(false);

    window.KingswayAssistant = Object.assign(window.KingswayAssistant || {}, {
      ask: (question) => {
        const text = String(question || '').slice(0, MAX_QUESTION);
        openPanel(text);
        if (text) void send(text);
      },
      open: (question) => openPanel(question),
      close: closePanel,
      toggle: togglePanel,
      newChat: newSession,
      resume: (sessionId) => openSession(sessionId),
      __state: state,
      __debug: {
        state,
        send,
        newSession,
        openSession,
        messageRecord,
        collectPageContext,
        localSuggestions,
        fallbackSuggestions,
        renderThread,
      },
    });
  }

  const boot = () => mount();
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();
