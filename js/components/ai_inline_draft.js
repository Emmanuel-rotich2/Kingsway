/**
 * AiInlineDraft — context-bound AI drafting inside the page/component/modal
 * where the staff member is already working.
 *
 * Unlike the global staff assistant (which opens the offcanvas panel and
 * collides with open Bootstrap modals), this renders a small status strip
 * inside the host element you give it and streams the draft straight into
 * the form field(s) you choose — the Cowork-style "draft appears where you
 * type" behaviour. Nothing navigates, nothing opens another modal.
 *
 * Usage:
 *   AiInlineDraft.mount({
 *     button: '#btn-draft',          // element or selector that starts it
 *     host: '#draftHost',            // element/selector inside the same
 *                                    // modal/page where the status strip goes
 *     prompt: () => 'Draft …',       // built at click time from form state
 *     route: 'manage_website',       // REQUESTED_ROUTE by default
 *     module: 'website',
 *     liveTarget: '#storyBody',      // selector/element/fn(acc) — gets the
 *                                    // streamed text live while generating
 *     apply: (fullText) => { … },    // called once with the final answer;
 *                                    // use it to distribute into several fields
 *     label: 'Draft with AI',
 *   });
 *
 * The component uses window.API.dashboard.agentAssistStream, so it inherits
 * the gateway/PHP fallback, auth and permission envelope of the assistant.
 */
(function () {
  'use strict';

  const STYLE_ID = 'ai-inline-draft-styles';

  function ensureStyles() {
    if (document.getElementById(STYLE_ID)) return;
    const style = document.createElement('style');
    style.id = STYLE_ID;
    style.textContent = `
      .ai-draft { border: 1px solid #E3E9E4; border-radius: 10px; background: #F7F9F6; padding: 8px 10px; margin-top: 8px; font-size: .85rem; }
      .ai-draft[hidden] { display: none !important; }
      .ai-draft-bar { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
      .ai-draft-status { display: inline-flex; align-items: center; gap: 7px; color: #3F4D45; }
      .ai-draft-status.is-error { color: #B4362B; }
      .ai-draft-status.is-done { color: #155C3B; font-weight: 600; }
      .ai-draft-stop { border: 1px solid #D5DED8; background: #fff; color: #5E6B64; border-radius: 8px; padding: 2px 10px; font-size: .78rem; font-weight: 600; }
      .ai-draft-stop:hover { color: #B4362B; border-color: #B4362B; }
      .ai-draft-note { color: #7C8982; font-size: .78rem; margin-top: 4px; }
    `;
    document.head.appendChild(style);
  }

  function resolve(ref) {
    return typeof ref === 'string' ? document.querySelector(ref) : ref || null;
  }

  function answerText(result, fallback) {
    return result?.answer?.body
      || result?.answer?.title
      || result?.body
      || result?.message
      || fallback
      || '';
  }

  /**
   * Mount one inline draft control. Returns { destroy, start }.
   * Safe to call once per host; repeated calls return the existing handle.
   */
  function mount(options = {}) {
    ensureStyles();
    const button = resolve(options.button);
    const host = resolve(options.host);
    if (!button || !host) {
      console.warn('[AiInlineDraft] button and host are required');
      return { destroy() {}, start() {} };
    }
    if (button.dataset.aiDraftBound === '1') {
      return button._aiDraftHandle || { destroy() {}, start() {} };
    }
    button.dataset.aiDraftBound = '1';

    const route = options.route || window.REQUESTED_ROUTE || '';
    const moduleName = options.module || 'dashboard';
    const label = options.label || 'Draft with AI';
    const buildPrompt = typeof options.prompt === 'function' ? options.prompt : () => String(options.prompt || '');
    const apply = typeof options.apply === 'function' ? options.apply : null;
    const liveTarget = options.liveTarget || null;

    let panel = null;
    let statusEl = null;
    let stopBtn = null;
    let stream = null;
    let acc = '';

    function writeLive(text) {
      if (!liveTarget) return;
      if (typeof liveTarget === 'function') { liveTarget(text); return; }
      const el = resolve(liveTarget);
      if (!el) return;
      el.value = text;
      el.dispatchEvent(new Event('input', { bubbles: true }));
      el.dispatchEvent(new Event('change', { bubbles: true }));
      if (el.scrollHeight) el.scrollTop = el.scrollHeight;
    }

    function setStatus(text, state) {
      if (!statusEl) return;
      statusEl.textContent = text;
      statusEl.classList.toggle('is-error', state === 'error');
      statusEl.classList.toggle('is-done', state === 'done');
    }

    function showPanel() {
      if (!panel) {
        panel = document.createElement('div');
        panel.className = 'ai-draft';
        panel.innerHTML = `
          <div class="ai-draft-bar">
            <span class="ai-draft-status" role="status" aria-live="polite"></span>
            <span class="d-flex gap-2">
              <button type="button" class="ai-draft-stop" hidden>Stop</button>
              <button type="button" class="ai-draft-stop" data-ai-draft-retry hidden>Retry</button>
            </span>
          </div>
          <div class="ai-draft-note">The assistant writes straight into this form. Nothing is saved or published until you do.</div>`;
        host.appendChild(panel);
        statusEl = panel.querySelector('.ai-draft-status');
        stopBtn = panel.querySelector('.ai-draft-stop');
        const retryBtn = panel.querySelector('[data-ai-draft-retry]');
        stopBtn.addEventListener('click', () => {
          try { stream?.cancel?.(); } catch (_) { /* already settled */ }
          setStatus('Stopped — what was written stays in the form for you to keep or clear.', 'done');
          stopBtn.hidden = true;
          retryBtn.hidden = false;
        });
        retryBtn.addEventListener('click', () => { retryBtn.hidden = true; start(); });
      }
      panel.hidden = false;
    }

    function start() {
      const api = window.API?.dashboard?.agentAssistStream;
      if (typeof api !== 'function') {
        setStatus('The assistant is not available right now.', 'error');
        return;
      }
      showPanel();
      acc = '';
      panel.querySelector('[data-ai-draft-retry]').hidden = true;
      stopBtn.hidden = false;
      setStatus('Drafting…', '');

      const question = buildPrompt();
      if (!question) {
        setStatus('There is nothing to draft from yet — fill in a title or a note first.', 'error');
        stopBtn.hidden = true;
        return;
      }

      stream = api(question, route, moduleName, {
        onDelta: (text) => {
          acc += text;
          writeLive(acc);
        },
        onStatus: (text) => setStatus(text || 'Drafting…', ''),
        onFinal: (result) => {
          stopBtn.hidden = true;
          const full = answerText(result, acc);
          if (apply && full) {
            try { apply(full); } catch (e) { console.warn('[AiInlineDraft] apply failed', e); }
          } else if (full && !liveTarget) {
            writeLive(full);
          }
          setStatus('Draft ready — read it, adjust anything, then save.', 'done');
        },
        onError: (message) => {
          stopBtn.hidden = true;
          const retryBtn = panel?.querySelector('[data-ai-draft-retry]');
          if (retryBtn) retryBtn.hidden = false;
          setStatus(message || 'The draft could not be finished. Try again.', 'error');
        },
      });
    }

    button.addEventListener('click', (event) => {
      event.preventDefault();
      start();
    });

    const handle = {
      start,
      destroy() {
        try { stream?.cancel?.(); } catch (_) { /* noop */ }
        panel?.remove();
        panel = null;
        delete button._aiDraftHandle;
        delete button.dataset.aiDraftBound;
      },
    };
    button._aiDraftHandle = handle;
    return handle;
  }

  /* ── Universal page assist ────────────────────────────────────────────────
     One floating panel on every authenticated page. Role-aware suggested
     tasks come from window.AI_PAGE_TASKS (route × role). The streamed answer
     can be inserted into the field the user last focused — Cowork-style —
     or copied. It is a plain card, never a modal, so it cannot collide with
     open Bootstrap modals; inside modals use AiInlineDraft.mount instead. */

  let lastEditable = null;
  function trackEditable() {
    if (trackEditable.bound) return;
    trackEditable.bound = true;
    document.addEventListener('focusin', (e) => {
      const t = e.target;
      if (t && (t.tagName === 'TEXTAREA' || (t.tagName === 'INPUT' && !/^(checkbox|radio|file|hidden|button|submit|range|color|date|time)/.test(t.type)) || t.isContentEditable)) {
        lastEditable = t;
      }
    }, true);
  }

  function userRoleKeys() {
    const keys = new Set();
    try {
      const u = window.AuthContext?.getUser?.() || {};
      (u.role_ids || []).forEach((id) => keys.add(String(id)));
      (window.AuthContext?.getRoles?.() || []).forEach((r) => keys.add(String(r).toLowerCase()));
    } catch (_) { /* guest */ }
    return keys;
  }

  /* Role-flavoured tasks generated for ANY route, so every page gives each
     role suggestions that match their job — not just the explicitly listed
     routes in AI_PAGE_TASKS. */
  const ROLE_FLAVOR = {
    '2': { name: 'system administrator', tasks: [
      ['Explain this page', 'Explain what this system page shows, its health, and whether anything needs attention.'],
      ['Ops note', 'Draft a short operations note about this page for the sysadmin log.'],
      ['Security check', 'Review this page from a security standpoint: risks, anomalies, recommended checks.'],
      ['Incident draft', 'Draft an incident or maintenance note based on what this page shows.'],
    ]},
    '3': { name: 'director', tasks: [
      ['Director summary', 'Summarise what this page shows at director level: outcomes, risks, decisions needed.'],
      ['Briefing paragraph', 'Write a short briefing paragraph from this page for the senior team.'],
      ['Exception digest', 'What on this page needs my decision or attention as director? List it.'],
    ]},
    '4': { name: 'school administrator', tasks: [
      ['Ops summary', 'Summarise what this page shows and any operational issues to resolve.'],
      ['Draft a circular', 'Draft a short staff or parent circular based on this page.'],
      ['Action list', 'List the actions this page implies, with owners if visible.'],
    ]},
    '5': { name: 'headteacher', tasks: [
      ['Leadership summary', 'Summarise this page for headteacher review: what is fine, what needs action.'],
      ['Approval note', 'Draft a short approval or review note based on this page.'],
      ['Announcement draft', 'Draft a whole-school announcement related to what this page shows.'],
    ]},
    '6': { name: 'deputy head – academic', tasks: [
      ['Academic brief', 'Summarise this page academically: coverage, gaps, and recommended actions.'],
      ['Department note', 'Draft a note to departments based on what this page shows.'],
      ['Quality check', 'What academic-quality issues does this page reveal? Suggest fixes.'],
    ]},
    '63': { name: 'deputy head – discipline', tasks: [
      ['Conduct summary', 'Summarise what this page shows about learner conduct and any cases needing follow-up.'],
      ['Case note draft', 'Draft a case or incident note based on this page.'],
      ['Parent letter', 'Draft a pastoral letter to a parent related to what this page shows.'],
    ]},
    '7': { name: 'class teacher', tasks: [
      ['Class note', 'Help me write a note about my class based on this page.'],
      ['Parent message', 'Draft a friendly message to parents related to what this page shows.'],
      ['Quick explainer', 'Explain what this page means for my class in plain language.'],
    ]},
    '8': { name: 'subject teacher', tasks: [
      ['Subject note', 'Relate what this page shows to my teaching subjects and suggest one improvement.'],
      ['Resource idea', 'Suggest a classroom resource or activity based on this page.'],
      ['Marking comment', 'Draft a marking comment or feedback statement related to this page.'],
    ]},
    default: { name: 'staff member', tasks: [
      ['Explain this page', 'Explain what this page shows and the most important things to check.'],
      ['Draft a note', 'Help me draft a short professional note based on what is on this page.'],
      ['My action list', 'What should I do next based on this page? List the steps.'],
    ]},
  };

  function generatedRoleTasks(roles, route) {
    const label = (route || 'page').replace(/_/g, ' ');
    const out = [];
    for (const k of roles) {
      const flavor = ROLE_FLAVOR[k] || (k === 'staff' ? ROLE_FLAVOR.default : null);
      if (!flavor) continue;
      flavor.tasks.forEach(([tlabel, body]) => out.push({
        label: `${tlabel} (${flavor.name})`,
        prompt: `${body} Page: ${label}. If the page content is not visible to you, say what you would need and give a template instead.`,
      }));
      break; // first matching role only — keeps chips focused
    }
    if (!out.length) {
      ROLE_FLAVOR.default.tasks.forEach(([tlabel, body]) => out.push({
        label: tlabel,
        prompt: `${body} Page: ${label}.`,
      }));
    }
    return out;
  }

  /* Prefix rules: any route whose name starts with one of these keywords
     inherits role-specific tasks — this is what takes coverage from a few
     dozen hand-listed pages to effectively every page in the system. */
  const PREFIX_TASKS = [
    ['system_', 'security_', 'api_', 'log', 'audit', 'session', 'backup', 'permission', 'role_', 'manage_user', 'error', 'maintenance', 'metric', 'monitor', 'health', 'notification_log',
      { '2': [['Explain this system page', 'Explain what this system page shows, its health, and whether anything needs attention.'], ['Ops note', 'Draft a short operations note about this page for the sysadmin log.'], ['Security check', 'Review this page from a security standpoint: risks, anomalies, recommended checks.']] }],
    ['report_', 'analytics', 'dashboard', 'kpi', 'metric', 'trend', 'comparative',
      { '3': [['Director summary', 'Write a director-level summary of this report: outcomes, risks, decisions needed.'], ['Briefing paragraph', 'Write a short briefing paragraph from this report for the senior team.']],
        '5': [['Leadership summary', 'Summarise this report for headteacher review: what is fine, what needs action.'], ['Action list', 'Turn this report into a prioritised action list.']],
        '6': [['Academic brief', 'Summarise this report academically: coverage, gaps, recommended actions.']],
        '7': [['Class takeaway', 'Write talking points from this report I can use with my class or their parents.']],
        '8': [['Subject takeaway', 'Summarise what this report means for my subject areas and suggest one improvement.']],
        '4': [['Ops summary', 'Summarise this report and list any operational issues to resolve.']] }],
    ['finance', 'payment', 'fee', 'budget', 'account', 'bank', 'invoice', 'reconcil', 'expense', 'payroll',
      { '10': [['Variance explanation', 'Explain the largest variances and suggest causes to verify.'], ['Reconciliation note', 'Draft a reconciliation note explaining differences found.']],
        '4': [['Finance note', 'Explain this finance page in plain language and list actions needed.']],
        '3': [['Finance briefing', 'Write a director-level finance briefing: position, variances, decisions needed.']] }],
    ['staff', 'hr', 'leave', 'appraisal', 'recruit', 'onboard', 'teacher',
      { '4': [['HR note', 'Summarise what this page shows for staff administration and list actions.'], ['Letter draft', 'Draft a professional staff letter based on this page.']],
        '5': [['Staff briefing', 'Summarise this for headteacher review: concerns, decisions needed.']] }],
    ['attendance', 'absentee',
      { '7': [['Parent letters', 'Draft short absence-notification letters to parents for learners with repeated absences.'], ['Class note', 'Write a class attendance summary I can share with my head of department.']],
        '6': [['Department brief', 'Write a department-level attendance brief: trends, concerns, recommended actions.']],
        '5': [['School alert', 'Summarise school-wide attendance for leadership: who is affected and what to do.']],
        '63': [['Home-visit points', 'Prepare talking points for a home visit about a learner\'s attendance.']] }],
    ['discipline', 'conduct', 'behavior', 'behaviour', 'incident', 'case',
      { '63': [['Case summary', 'Draft a clear case summary: facts, dates, actions taken, recommended next step.'], ['Parent letter', 'Write a firm but pastoral letter to a parent about this case.'], ['Hearing minutes', 'Draft minutes for a hearing: attendees, statements, decision, follow-up.']],
        '5': [['Case review', 'Summarise this case for headteacher review: severity, pattern, recommendation.']],
        '7': [['Classroom follow-up', 'Suggest classroom-level follow-up steps after this incident.']] }],
    ['transport', 'vehicle', 'driver', 'route', 'trip',
      { '23': [['Route note', 'Draft a note about today\'s routes or any transport issues for the log.']],
        '4': [['Transport brief', 'Summarise transport operations on this page and list issues to resolve.']] }],
    ['boarding', 'hostel', 'matron', 'exeat',
      { '18': [['Boarding note', 'Draft a brief boarding note for the log based on this page.']],
        '5': [['Boarding briefing', 'Summarise boarding matters on this page for leadership.']] }],
    ['health', 'clinic', 'counsel', 'pastoral', 'chaplain', 'welfare',
      { '24': [['Welfare note', 'Draft a confidential pastoral note based on this page.']],
        '6': [['Welfare brief', 'Summarise welfare matters for the deputy head: concerns, referrals needed.']] }],
    ['inventory', 'store', 'asset', 'purchase', 'stock', 'catalog', 'product',
      { '14': [['Stock note', 'Summarise stock levels or issues on this page and suggest reorders.'], ['Purchase justification', 'Draft a purchase justification note for items flagged here.']],
        '4': [['Inventory brief', 'Summarise inventory matters and list actions for the school administrator.']] }],
    ['catering', 'kitchen', 'meal', 'food', 'diet',
      { '16': [['Catering note', 'Draft a catering note about meals, consumption or issues on this page.']],
        '4': [['Catering brief', 'Summarise catering operations and flag issues for the administrator.']] }],
    ['exam', 'assessment', 'grade', 'result', 'mark', 'test', 'quiz',
      { '8': [['Question draft', 'Draft exam-style questions with mark allocations for the topic I name.'], ['Rubric descriptors', 'Write rubric descriptors: beginning, developing, secure, outstanding.']],
        '7': [['Class test draft', 'Draft a short class test: 10 questions covering this topic, with answers.']],
        '6': [['Assessment blueprint', 'Propose an assessment blueprint: topics, weightings, question types, duration.']],
        '5': [['Exam readiness', 'Write an exam-readiness note for leadership: coverage, risks, invigilation needs.']] }],
    ['lesson', 'scheme', 'cbc', 'curriculum', 'academic', 'syllabus', 'strand',
      { '8': [['Lesson outline', 'Outline a 40-minute CBC lesson on the topic I describe: objectives, activities, assessment.'], ['Differentiation', 'Suggest 3 ways to differentiate this lesson for mixed-ability learners.']],
        '7': [['Class lesson plan', 'Draft a simple lesson plan for my class: starter, main activity, plenary, homework.']],
        '6': [['Coverage check', 'Review which CBC strands this term\'s plans cover and what is missing.']],
        '5': [['Coverage report', 'Summarise academic coverage across the school and flag gaps.']] }],
    ['admission', 'enrol', 'enroll', 'application', 'interview', 'placement', 'prospect',
      { '5': [['Admission note', 'Draft an admission decision or interview note based on this page.']],
        '4': [['Placement brief', 'Summarise admission/placement matters and list actions.']],
        '6': [['Class fit', 'Suggest class/stream placement considerations based on this application.']] }],
    ['communicat', 'announce', 'message', 'sms', 'email', 'notification', 'newsletter', 'circular',
      { '4': [['Staff circular', 'Draft a staff circular with clear sections: purpose, actions required, deadline.']],
        '5': [['Official announcement', 'Draft an official headteacher announcement with appropriate tone and formality.']],
        '7': [['Class parent note', 'Draft a friendly note to parents of my class about an upcoming activity or request.']],
        '8': [['Subject update', 'Draft a short update to parents about my subject this week.']] }],
    ['student', 'learner', 'pupil', 'class_', 'stream', 'grade', 'school_',
      { '7': [['Class note', 'Help me write a note about my class or a learner based on this page.'], ['Parent message', 'Draft a friendly message to parents related to what this page shows.']],
        '8': [['Subject note', 'Relate what this page shows to my subject and suggest one improvement.']],
        '5': [['Leadership summary', 'Summarise this for headteacher review: what is fine, what needs action.']] }],
    ['parent', 'guardian',
      { '4': [['Parent comms', 'Draft a parent-facing message based on this page.']],
        '7': [['Class parent note', 'Draft a note to parents of my class.']] }],
    ['website', 'news', 'event', 'gallery', 'download', 'job', 'career', 'inquir', 'vacanc',
      { '4': [['Parent update', 'Draft a parent-facing update in plain, reassuring language.'], ['Announcement', 'Draft a short announcement for the website or newsletter.']],
        '5': [['Headteacher message', 'Draft a headteacher message for the website or newsletter.']] }],
  ];

  function prefixTasksFor(route, roles) {
    const r = String(route || '');
    for (const [prefixes, byRole] of PREFIX_TASKS) {
      if (!prefixes.some((p) => r === p || r.startsWith(p))) continue;
      const out = [];
      for (const k of roles) {
        const list = byRole[k];
        if (list) { list.forEach(([l, p]) => out.push({ label: l, prompt: `${p} Page: ${r.replace(/_/g, ' ')}.` })); break; }
      }
      if (!out.length) (byRole['*'] || []).forEach(([l, p]) => out.push({ label: l, prompt: `${p} Page: ${r.replace(/_/g, ' ')}.` }));
      return out;
    }
    return [];
  }

  function pageTasksFor(route, roles) {
    const reg = window.AI_PAGE_TASKS || {};
    const page = reg[route] && route !== '*' ? reg[route] : null;
    const out = [];
    const push = (list) => (list || []).forEach((t) => out.push(t));
    if (page) {
      roles.forEach((k) => push(page[k]));
      push(page['*']);
    }
    // Route-family tasks (reports, finance, HR…) apply even without a
    // hand-written entry for this exact page.
    prefixTasksFor(route, roles).forEach((t) => out.push(t));
    generatedRoleTasks(roles, route).forEach((t) => out.push(t));
    if (page !== reg['*']) push(reg['*'] && reg['*']['*']);
    // de-dupe by label
    const seen = new Set();
    return out.filter((t) => (seen.has(t.label) ? false : (seen.add(t.label), true)));
  }

  function mountPageAssist() {
    trackEditable();
    if (document.getElementById('kwAiAssistFab')) return;
    const style = document.createElement('style');
    style.textContent = `
      #kwAiAssistFab { position: fixed; right: 18px; bottom: 18px; z-index: 1040; width: 46px; height: 46px; border-radius: 50%; border: 0; background: #155C3B; color: #fff; font-size: 1.15rem; box-shadow: 0 8px 24px rgba(21,92,59,.35); display: flex; align-items: center; justify-content: center; }
      #kwAiAssistFab:hover { background: #0F4A2F; }
      #kwAiAssistPanel { position: fixed; right: 18px; bottom: 74px; z-index: 1040; width: min(400px, calc(100vw - 36px)); max-height: min(70vh, 560px); display: flex; flex-direction: column; background: #fff; border: 1px solid #E3E9E4; border-radius: 16px; box-shadow: 0 18px 50px rgba(21,38,32,.25); overflow: hidden; }
      #kwAiAssistPanel[hidden] { display: none !important; }
      .kw-ai-head { display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; background: #155C3B; color: #fff; font-weight: 600; font-size: .9rem; }
      .kw-ai-head small { color: #CFE3D8; font-weight: 500; }
      .kw-ai-chips { display: flex; flex-wrap: wrap; gap: 6px; padding: 10px 14px 0; }
      .kw-ai-chip { border: 1px solid #D5DED8; background: #F7F9F6; border-radius: 999px; padding: 4px 11px; font-size: .78rem; color: #2D3A33; cursor: pointer; }
      .kw-ai-chip:hover { border-color: #2E8B57; color: #155C3B; }
      .kw-ai-body { padding: 10px 14px; overflow-y: auto; flex: 1; }
      .kw-ai-answer { font-size: .88rem; white-space: pre-wrap; color: #1B2620; }
      .kw-ai-status { font-size: .8rem; color: #5E6B64; }
      .kw-ai-foot { display: flex; gap: 8px; padding: 10px 14px; border-top: 1px solid #E3E9E4; }
      .kw-ai-foot textarea { flex: 1; border: 1px solid #D5DED8; border-radius: 10px; padding: 8px 10px; font-size: .88rem; resize: none; height: 42px; }
      .kw-ai-foot textarea:focus { outline: none; border-color: #2E8B57; box-shadow: 0 0 0 3px rgba(46,139,87,.15); }
      .kw-ai-btn { border: 0; border-radius: 10px; background: #155C3B; color: #fff; font-weight: 600; font-size: .85rem; padding: 0 14px; }
      .kw-ai-btn:hover { background: #0F4A2F; }
      .kw-ai-btn.ghost { background: #fff; color: #2D3A33; border: 1px solid #D5DED8; }
      .kw-ai-btn:disabled { opacity: .5; }
    `;
    document.head.appendChild(style);

    const fab = document.createElement('button');
    fab.id = 'kwAiAssistFab';
    fab.type = 'button';
    fab.title = 'AI assistant';
    fab.setAttribute('aria-label', 'Open AI assistant');
    fab.innerHTML = '<i class="bi bi-stars"></i>';

    const panel = document.createElement('div');
    panel.id = 'kwAiAssistPanel';
    panel.hidden = true;
    panel.innerHTML = `
      <div class="kw-ai-head"><span>AI assistant <small id="kwAiRoute"></small></span>
        <button type="button" class="btn-close btn-close-white btn-sm" id="kwAiClose" aria-label="Close"></button></div>
      <div class="kw-ai-chips" id="kwAiChips"></div>
      <div class="kw-ai-body"><div class="kw-ai-status" id="kwAiStatus">Ask anything, or pick a suggestion for this page.</div>
        <div class="kw-ai-answer" id="kwAiAnswer"></div></div>
      <div class="kw-ai-foot">
        <textarea id="kwAiInput" rows="1" placeholder="What would you like help with?"></textarea>
        <button type="button" class="kw-ai-btn" id="kwAiSend">Send</button>
      </div>
      <div class="kw-ai-foot" style="border-top:0;padding-top:0">
        <button type="button" class="kw-ai-btn ghost" id="kwAiInsert" disabled>Insert into field</button>
        <button type="button" class="kw-ai-btn ghost" id="kwAiCopy" disabled>Copy</button>
        <button type="button" class="kw-ai-btn ghost" id="kwAiStop" hidden>Stop</button>
      </div>`;

    document.body.appendChild(fab);
    document.body.appendChild(panel);

    const $ = (id) => panel.querySelector('#' + id) || document.getElementById(id);
    const routeLabel = () => (window.REQUESTED_ROUTE || 'page').replace(/_/g, ' ');
    let stream = null;
    let acc = '';
    let finalText = '';

    function setStatus(t) { $('kwAiStatus').textContent = t || ''; }
    function renderChips() {
      const host = $('kwAiChips');
      const route = window.REQUESTED_ROUTE || '';
      const tasks = pageTasksFor(route, userRoleKeys());
      host.innerHTML = '';
      tasks.slice(0, 6).forEach((t) => {
        const b = document.createElement('button');
        b.type = 'button'; b.className = 'kw-ai-chip'; b.textContent = t.label;
        b.addEventListener('click', () => run(typeof t.prompt === 'function' ? t.prompt({ route }) : t.prompt));
        host.appendChild(b);
      });
      host.hidden = !tasks.length;
    }

    function run(question) {
      const api = window.API?.dashboard?.agentAssistStream;
      if (typeof api !== 'function') { setStatus('The assistant is not available right now.'); return; }
      if (!question || !String(question).trim()) { setStatus('Type what you need first.'); return; }
      acc = ''; finalText = '';
      $('kwAiAnswer').textContent = '';
      $('kwAiInsert').disabled = true; $('kwAiCopy').disabled = true;
      $('kwAiStop').hidden = false;
      setStatus('Drafting…');
      stream = api(String(question).slice(0, 4000), window.REQUESTED_ROUTE || '', 'dashboard', {
        onDelta: (t) => { acc += t; $('kwAiAnswer').textContent = acc; const body = panel.querySelector('.kw-ai-body'); body.scrollTop = body.scrollHeight; },
        onStatus: (t) => setStatus(t || 'Working…'),
        onFinal: (res) => {
          $('kwAiStop').hidden = true;
          finalText = answerText(res, acc) || acc;
          $('kwAiAnswer').textContent = finalText;
          $('kwAiInsert').disabled = !finalText; $('kwAiCopy').disabled = !finalText;
          setStatus('Done — insert it into a field or copy it.');
        },
        onError: (m) => { $('kwAiStop').hidden = true; setStatus(m || 'Could not finish. Try again.'); },
      });
    }

    function insertIntoField() {
      const text = finalText || acc;
      const el = lastEditable && document.contains(lastEditable) ? lastEditable : null;
      if (!el || !text) { setStatus('Click into a text field first, then Insert.'); return; }
      if (el.isContentEditable) { el.focus(); document.execCommand?.('insertText', false, text); }
      else { el.value = (el.value ? el.value + '\n' : '') + text; el.dispatchEvent(new Event('input', { bubbles: true })); el.dispatchEvent(new Event('change', { bubbles: true })); }
      el.focus();
      setStatus('Inserted — review it where it landed.');
    }

    async function copyAnswer() {
      const text = finalText || acc;
      try { await navigator.clipboard.writeText(text); setStatus('Copied.'); }
      catch (_) { setStatus('Copy is not available here.'); }
    }

    fab.addEventListener('click', () => {
      panel.hidden = !panel.hidden;
      $('kwAiRoute').textContent = '· ' + routeLabel();
      if (!panel.hidden) { renderChips(); $('kwAiInput').focus(); }
    });
    $('kwAiClose').addEventListener('click', () => { panel.hidden = true; });
    $('kwAiSend').addEventListener('click', () => { const v = $('kwAiInput').value; $('kwAiInput').value = ''; run(v); });
    $('kwAiInput').addEventListener('keydown', (e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); $('kwAiSend').click(); } });
    $('kwAiInsert').addEventListener('click', insertIntoField);
    $('kwAiCopy').addEventListener('click', copyAnswer);
    $('kwAiStop').addEventListener('click', () => { try { stream?.cancel?.(); } catch (_) {} $('kwAiStop').hidden = true; setStatus('Stopped — partial text kept.'); });

    const show = () => { if (window.AuthContext?.getUser?.()) { fab.hidden = false; } };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', show); else show();
    window.addEventListener('kingsway:page-loaded', () => { $('kwAiRoute').textContent = '· ' + routeLabel(); renderChips(); });
  }

  // Auto-mount the universal panel once per document.
  if (!window.__kwAiAssistMounted) {
    window.__kwAiAssistMounted = true;
    const boot = () => mountPageAssist();
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
  }

  window.AiInlineDraft = { mount, mountPageAssist };
})();
