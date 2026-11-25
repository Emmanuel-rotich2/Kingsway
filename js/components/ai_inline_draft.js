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
    try { const lt = resolve(options.liveTarget); if (lt?.id) button.dataset.aiDraftTarget = lt.id; } catch(_){}

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
      if (el.isContentEditable) {
        el.innerHTML = String(text || '').replace(/\n/g, '<br>');
      } else {
        el.value = text;
      }
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
        page_context: getPageContext(),
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
      if (t && !t.closest('#kwAiAssistPanel') && (t.tagName === 'TEXTAREA' || (t.tagName === 'INPUT' && !/^(checkbox|radio|file|hidden|button|submit|range|color|date|time)/.test(t.type)) || t.isContentEditable)) {
        lastEditable = t;
      }
    }, true);
  }

  const ROLE_NAME_TO_ID = {
    'system administrator': '2', 'director': '3', 'school administrator': '4', 'headteacher': '5',
    'deputy head - academic': '6', 'deputy head academic': '6', 'class teacher': '7', 'subject teacher': '8',
    'intern/student teacher': '9', 'intern/ student teacher': '9', 'accountant': '10', 'inventory manager': '14',
    'cateress': '16', 'boarding master': '18', 'talent development': '21', 'driver': '23', 'chaplain': '24',
    'kitchen staff': '32', 'security staff': '33', 'janitor': '34', 'deputy head - discipline': '63',
    'deputy head discipline': '63', 'staff': '64', 'food store manager': '71', 'librarian': '72', 'parent': '73'
  };

  function userRoleKeys() {
    const keys = new Set();
    try {
      const u = window.AuthContext?.getUser?.() || {};
      const add = (v) => { const s = String(v == null ? '' : v).trim(); if (s) keys.add(s); };
      (u.role_ids || u.roles_ids || []).forEach(add);
      (u.roles || []).forEach((r) => {
        if (r && typeof r === 'object') { add(r.id); add(r.role_id); add(r.name); }
        else add(r);
      });
      (window.AuthContext?.getRoles?.() || []).forEach(add);
      add(window.AuthContext?.getRole?.());
      add(u.role); add(u.role_id); add(u.role_name); add(u.main_role);
      // normalize names to IDs
      Array.from(keys).forEach((k) => {
        const low = k.toLowerCase();
        if (ROLE_NAME_TO_ID[low]) keys.add(ROLE_NAME_TO_ID[low]);
      });
    } catch (_) { /* guest */ }
    return Array.from(keys);
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
    '9': { name: 'intern teacher', tasks: [
      ['Lesson idea', 'Suggest a lesson activity or resource based on what this page shows.'],
      ['Observation note', 'Help me write a short teaching observation or reflection note from this page.'],
      ['Question draft', 'Draft a few questions I could ask learners about this topic.'],
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
        '63': [['Conduct brief', 'Summarise what this report shows about learner conduct and any cases needing follow-up.']],
        '7': [['Class takeaway', 'Write talking points from this report I can use with my class or their parents.']],
        '8': [['Subject takeaway', 'Summarise what this report means for my subject areas and suggest one improvement.']],
        '9': [['Placement note', 'Explain what this report means for my teaching placement and what to focus on.']],
        '4': [['Ops summary', 'Summarise this report and list any operational issues to resolve.']],
        '2': [['Ops health', 'Review this report for system or data-health anomalies and recommend checks.']] }],
    ['finance', 'payment', 'fee', 'budget', 'account', 'bank', 'invoice', 'reconcil', 'expense', 'payroll',
      { '10': [['Variance explanation', 'Explain the largest variances and suggest causes to verify.'], ['Reconciliation note', 'Draft a reconciliation note explaining differences found.']],
        '5': [['Finance briefing', 'Write a headteacher-level finance briefing: position, variances, decisions needed.']],
        '4': [['Finance note', 'Explain this finance page in plain language and list actions needed.']],
        '3': [['Finance briefing', 'Write a director-level finance briefing: position, variances, decisions needed.']] }],
    ['staff', 'hr', 'leave', 'appraisal', 'recruit', 'onboard', 'teacher',
      { '6': [['Staff brief', 'Summarise what this page shows for academic staff oversight and list actions.']],
        '63': [['Conduct brief', 'Summarise staff matters on this page needing disciplinary or welfare follow-up.']],
        '3': [['People briefing', 'Write a director-level briefing on staffing matters shown here.']],
        '4': [['HR note', 'Summarise what this page shows for staff administration and list actions.'], ['Letter draft', 'Draft a professional staff letter based on this page.']],
        '5': [['Staff briefing', 'Summarise this for headteacher review: concerns, decisions needed.']],
        '2': [['Access review', 'Check this staff page for permission or access anomalies and recommend fixes.']] }],
    ['attendance', 'absentee',
      { '7': [['Parent letters', 'Draft short absence-notification letters to parents for learners with repeated absences.'], ['Class note', 'Write a class attendance summary I can share with my head of department.']],
        '8': [['Class register note', 'Summarise attendance patterns for my classes and suggest follow-up.']],
        '6': [['Department brief', 'Write a department-level attendance brief: trends, concerns, recommended actions.']],
        '63': [['Home-visit points', 'Prepare talking points for a home visit about a learner\'s attendance.']],
        '5': [['School alert', 'Summarise school-wide attendance for leadership: who is affected and what to do.']],
        '4': [['Ops summary', 'Summarise attendance operations on this page and list issues to resolve.']],
        '3': [['Leadership brief', 'Write a director-level attendance briefing: trends, concerns, decisions needed.']] }],
    ['discipline', 'conduct', 'behavior', 'behaviour', 'incident', 'case',
      { '63': [['Case summary', 'Draft a clear case summary: facts, dates, actions taken, recommended next step.'], ['Parent letter', 'Write a firm but pastoral letter to a parent about this case.'], ['Hearing minutes', 'Draft minutes for a hearing: attendees, statements, decision, follow-up.']],
        '6': [['Case review', 'Review this case for academic-impact considerations and recommended support.']],
        '5': [['Case review', 'Summarise this case for headteacher review: severity, pattern, recommendation.']],
        '4': [['Conduct brief', 'Summarise conduct matters on this page and list administrative actions.']],
        '3': [['Leadership brief', 'Write a director-level summary of serious cases and decisions needed.']],
        '7': [['Classroom follow-up', 'Suggest classroom-level follow-up steps after this incident.']] }],
    ['transport', 'vehicle', 'driver', 'route', 'trip',
      { '23': [['Route note', 'Draft a note about today\'s routes or any transport issues for the log.']],
        '5': [['Transport briefing', 'Summarise transport operations for leadership and list safety or service issues.']],
        '4': [['Transport brief', 'Summarise transport operations on this page and list issues to resolve.']],
        '3': [['Director brief', 'Write a director-level transport briefing: fleet status, risks, decisions needed.']] }],
    ['boarding', 'hostel', 'matron', 'exeat',
      { '18': [['Boarding note', 'Draft a brief boarding note for the log based on this page.']],
        '63': [['Boarding conduct', 'Summarise boarding conduct matters needing follow-up.']],
        '5': [['Boarding briefing', 'Summarise boarding matters on this page for leadership.']],
        '4': [['Boarding brief', 'Summarise boarding operations and list administrative actions.']],
        '3': [['Director brief', 'Write a director-level boarding briefing: welfare, incidents, decisions needed.']],
        '7': [['Class note', 'Draft a note to my boarder learners\' parents based on this page.']] }],
    ['health', 'clinic', 'counsel', 'pastoral', 'chaplain', 'welfare',
      { '24': [['Welfare note', 'Draft a confidential pastoral note based on this page.']],
        '7': [['Class welfare', 'Draft a discreet note to parents or staff about a learner\'s welfare matter shown here.']],
        '63': [['Welfare follow-up', 'Summarise welfare matters on this page needing disciplinary or care follow-up.']],
        '6': [['Welfare brief', 'Summarise welfare matters for the deputy head: concerns, referrals needed.']],
        '5': [['Welfare briefing', 'Summarise learner welfare matters on this page for headteacher review.']],
        '4': [['Welfare admin', 'Summarise welfare administration on this page and list actions.']],
        '3': [['Director brief', 'Write a director-level welfare briefing: serious matters, referrals, decisions needed.']] }],
    ['inventory', 'store', 'asset', 'purchase', 'stock', 'catalog', 'product',
      { '14': [['Stock note', 'Summarise stock levels or issues on this page and suggest reorders.'], ['Purchase justification', 'Draft a purchase justification note for items flagged here.']],
        '6': [['Department needs', 'Summarise resource needs visible here for academic departments.']],
        '5': [['Stock briefing', 'Summarise inventory and procurement matters for headteacher review.']],
        '4': [['Inventory brief', 'Summarise inventory matters and list actions for the school administrator.']],
        '3': [['Director brief', 'Write a director-level procurement briefing: spend, risks, approvals needed.']],
        '2': [['Stock audit', 'Review this inventory page for anomalies and recommend verification checks.']] }],
    ['catering', 'kitchen', 'meal', 'food', 'diet',
      { '16': [['Catering note', 'Draft a catering note about meals, consumption or issues on this page.']],
        '7': [['Dietary note', 'Draft a note about a learner\'s dietary or meal-related matter shown here.']],
        '5': [['Catering briefing', 'Summarise catering operations for headteacher review and list issues.']],
        '4': [['Catering brief', 'Summarise catering operations and flag issues for the administrator.']],
        '3': [['Director brief', 'Write a director-level catering briefing: costs, nutrition, issues.']] }],
    ['exam', 'assessment', 'grade', 'result', 'mark', 'test', 'quiz',
      { '8': [['Question draft', 'Draft exam-style questions with mark allocations for the topic I name.'], ['Rubric descriptors', 'Write rubric descriptors: beginning, developing, secure, outstanding.']],
        '9': [['Question draft', 'Draft a few exam-style questions with answers for the topic I name.']],
        '7': [['Class test draft', 'Draft a short class test: 10 questions covering this topic, with answers.']],
        '6': [['Assessment blueprint', 'Propose an assessment blueprint: topics, weightings, question types, duration.']],
        '5': [['Exam readiness', 'Write an exam-readiness note for leadership: coverage, risks, invigilation needs.']],
        '4': [['Exam admin', 'Summarise examination administration on this page and list actions needed.']],
        '3': [['Director brief', 'Write a director-level examination briefing: results outlook, risks, decisions needed.']],
        '2': [['Results integrity', 'Review this results page for anomalies and recommend verification checks.']] }],
    ['lesson', 'scheme', 'cbc', 'curriculum', 'academic', 'syllabus', 'strand',
      { '8': [['Lesson outline', 'Outline a 40-minute CBC lesson on the topic I describe: objectives, activities, assessment.'], ['Differentiation', 'Suggest 3 ways to differentiate this lesson for mixed-ability learners.']],
        '9': [['Lesson outline', 'Outline a simple lesson on the topic I describe: starter, main activity, plenary.']],
        '7': [['Class lesson plan', 'Draft a simple lesson plan for my class: starter, main activity, plenary, homework.']],
        '6': [['Coverage check', 'Review which CBC strands this term\'s plans cover and what is missing.']],
        '5': [['Coverage report', 'Summarise academic coverage across the school and flag gaps.']],
        '4': [['Academic admin', 'Summarise academic administration on this page and list actions needed.']],
        '3': [['Director brief', 'Write a director-level academic briefing: coverage, quality, decisions needed.']] }],
    ['admission', 'enrol', 'enroll', 'application', 'interview', 'placement', 'prospect',
      { '5': [['Admission note', 'Draft an admission decision or interview note based on this page.']],
        '6': [['Class fit', 'Suggest class/stream placement considerations based on this application.']],
        '4': [['Placement brief', 'Summarise admission/placement matters and list actions.']],
        '3': [['Enrollment briefing', 'Write a director-level enrollment briefing: numbers, trends, decisions needed.']],
        '2': [['Data check', 'Review this admissions page for duplicate or inconsistent records and recommend checks.']] }],
    ['communicat', 'announce', 'message', 'sms', 'email', 'notification', 'newsletter', 'circular',
      { '4': [['Staff circular', 'Draft a staff circular with clear sections: purpose, actions required, deadline.']],
        '5': [['Official announcement', 'Draft an official headteacher announcement with appropriate tone and formality.']],
        '3': [['Leadership comms', 'Draft a director-approved communication based on this page.']],
        '6': [['Department memo', 'Draft a memo to departments based on what this page shows.']],
        '63': [['Pastoral notice', 'Draft a pastoral notice to parents or staff related to this matter.']],
        '2': [['Comms ops', 'Review this communications page for delivery or template issues and recommend fixes.']],
        '7': [['Class parent note', 'Draft a friendly note to parents of my class about an upcoming activity or request.']],
        '8': [['Subject update', 'Draft a short update to parents about my subject this week.']],
        '9': [['Class note', 'Draft a short note to my class or their parents related to this page.']] }],
    ['student', 'learner', 'pupil', 'class_', 'stream', 'grade', 'school_',
      { '7': [['Class note', 'Help me write a note about my class or a learner based on this page.'], ['Parent message', 'Draft a friendly message to parents related to what this page shows.']],
        '8': [['Subject note', 'Relate what this page shows to my subject and suggest one improvement.']],
        '9': [['Class note', 'Help me write a note about learners or my class based on this page.']],
        '6': [['Learner brief', 'Summarise learner matters on this page for academic oversight and list actions.']],
        '63': [['Learner brief', 'Summarise learner matters on this page needing conduct or welfare follow-up.']],
        '5': [['Leadership summary', 'Summarise this for headteacher review: what is fine, what needs action.']],
        '4': [['Student admin', 'Summarise student administration on this page and list actions needed.']],
        '3': [['Director brief', 'Write a director-level briefing on learner matters shown here.']] }],
    ['parent', 'guardian',
      { '5': [['Parent comms', 'Draft an official headteacher message to parents based on this page.']],
        '8': [['Subject note', 'Draft a note to parents about my subject based on this page.']],
        '7': [['Class parent note', 'Draft a note to parents of my class.']],
        '4': [['Parent comms', 'Draft a parent-facing message based on this page.']] }],
    ['website', 'news', 'event', 'gallery', 'download', 'job', 'career', 'inquir', 'vacanc',
      { '5': [['Headteacher message', 'Draft a headteacher message for the website or newsletter.']],
        '3': [['Director message', 'Draft a director message for the website or newsletter.']],
        '6': [['Academic notice', 'Draft an academic notice for the website based on this page.']],
        '7': [['Class event', 'Draft a note about a class event or activity for the website.']],
        '8': [['Subject update', 'Draft a subject update or resource note for the website.']],
        '9': [['Class note', 'Draft a short classroom or event note for the website.']],
        '2': [['Content check', 'Review this public content for accuracy, tone, and policy compliance.']],
        '4': [['Parent update', 'Draft a parent-facing update in plain, reassuring language.'], ['Announcement', 'Draft a short announcement for the website or newsletter.']] }],
    ['manage_', 'settings', 'config',
      { '2': [['System note', 'Explain this admin page and any system-health issues it shows.'], ['Ops check', 'Review this page for configuration or security issues and recommend checks.']],
        '5': [['Admin briefing', 'Summarise what this admin page controls and whether anything needs headteacher attention.']],
        '4': [['Admin note', 'Explain this admin page in plain language and list actions needed.'], ['Policy draft', 'Draft a short policy or procedure note based on this page.']],
        '3': [['Leadership brief', 'Write a director-level summary of what this admin area controls and any risks.']],
        '6': [['Academic admin', 'Summarise the academic-administration aspects of this page and list actions.']],
        '63': [['Admin brief', 'Summarise the conduct or welfare administration aspects of this page.']] }],
  ];

  function prefixTasksFor(route, roles) {
    const r = String(route || '');
    for (const entry of PREFIX_TASKS) {
      const byRole = entry[entry.length - 1];
      const prefixes = Array.isArray(entry[0]) ? entry[0] : entry.slice(0, -1);
      if (!prefixes.some((p) => typeof p === 'string' && (r === p || r.startsWith(p) || r.includes(p)))) continue;
      const out = [];
      for (const k of roles) {
        const list = byRole && byRole[k];
        if (list) { list.forEach(([l, p]) => out.push({ label: l, prompt: `${p} Page: ${r.replace(/_/g, ' ')}.` })); break; }
      }
      if (!out.length && byRole) (byRole['*'] || []).forEach(([l, p]) => out.push({ label: l, prompt: `${p} Page: ${r.replace(/_/g, ' ')}.` }));
      if (out.length) return out;
    }
    return [];
  }

  function normalizeRoute(route) {
    const raw = String(route || window.REQUESTED_ROUTE || '').trim();
    if (!raw) return '';
    return raw.replace(/^\/+/, '').replace(/\.php$/i, '').split(/[?#]/)[0];
  }

  function resolvePageEntry(reg, route) {
    const key = normalizeRoute(route);
    if (!key) return null;
    const candidates = [key, key.replace(/\//g, '_'), key.split('/').pop()];
    for (const c of candidates) {
      if (c && reg[c] && c !== '*') return reg[c];
    }
    // subtemplate fallback: try parent-like key by dropping suffix tokens
    if (key.includes('/')) {
      const base = key.split('/').pop();
      if (reg[base] && base !== '*') return reg[base];
    }
    return null;
  }

  function pageTasksFor(route, roles) {
    const reg = window.AI_PAGE_TASKS || {};
    const key = normalizeRoute(route);
    const page = resolvePageEntry(reg, key);
    const out = [];
    const push = (list) => (list || []).forEach((t) => out.push(t));
    if (page) {
      roles.forEach((k) => push(page[k]));
      push(page['*']);
    }
    prefixTasksFor(key, roles).forEach((t) => out.push(t));
    generatedRoleTasks(roles, key).forEach((t) => out.push(t));
    if (!out.length && reg['*'] && reg['*']['*']) push(reg['*']['*']);
    const seen = new Set();
    return out.filter((t) => {
      const lab = String(t?.label || t?.[0] || '').trim();
      if (!lab || seen.has(lab)) return false;
      seen.add(lab);
      return true;
    }).map((t)=> Array.isArray(t) ? ({label:t[0], prompt:t[1]}) : t);
  }

  function getPageContext() {
    const route = normalizeRoute(window.REQUESTED_ROUTE || location.pathname);
    const title = (document.querySelector('h1,h2,.page-title,.card-title')?.textContent || '').trim().slice(0, 120);
    const modal = document.querySelector('.modal.show .modal-title, .modal.show h5, .modal.show h4');
    const modalTitle = (modal?.textContent || '').trim().slice(0, 120);
    const active = document.activeElement;
    const field = active && (active.matches?.('input,textarea,[contenteditable="true"]')) ? {
      id: active.id || '', name: active.name || '', label: fieldLabel(active), type: active.type || active.tagName.toLowerCase()
    } : null;
    const filters = Array.from(document.querySelectorAll('select[name],input[type="search"],input[name*="date" i]')).slice(0,8).map(el=>({
      name: el.name || el.id || 'filter', value: (el.value||'').toString().slice(0,60)
    })).filter(f=>f.name);
    return { route, title, modalTitle, field, filters };
  }

  function mountPageAssist() {
    trackEditable();
    if (document.getElementById('kwAiAssistFab')) return;
    const style = document.createElement('style');
    style.textContent = `
      #kwAiAssistFab { position: fixed; right: 22px; bottom: 22px; z-index: 1080; width: 54px; height: 54px; border-radius: 50%; border: 0; background: linear-gradient(135deg,#1E7A4E 0%,#155C3B 60%,#0F4A2F 100%); color: #fff; font-size: 1.3rem; box-shadow: 0 10px 30px rgba(21,92,59,.4), 0 0 0 6px rgba(46,139,87,.12); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: transform .15s ease, box-shadow .15s ease; }
      #kwAiAssistFab:hover { transform: translateY(-2px) scale(1.04); box-shadow: 0 14px 36px rgba(21,92,59,.45), 0 0 0 6px rgba(46,139,87,.16); }
      #kwAiAssistPanel { position: fixed; top: 0; right: 0; bottom: 0; height: 100vh; z-index: 1080; width: min(720px, calc(100vw - 48px)); display: flex; flex-direction: column; background: #FBFCFA; border-left: 1px solid #DCE5DE; border-radius: 24px 0 0 24px; box-shadow: -24px 0 70px rgba(15,38,28,.22), -4px 0 14px rgba(15,38,28,.10); overflow: hidden; }
      #kwAiAssistPanel[hidden] { display: none !important; }
      .kw-ai-head { display: flex; align-items: center; justify-content: space-between; padding: 18px 24px; background: linear-gradient(135deg,#1E7A4E 0%,#155C3B 55%,#0F4A2F 100%); color: #fff; }
      .kw-ai-head .kw-ai-title { display: flex; align-items: center; gap: 10px; font-weight: 700; font-size: 1rem; letter-spacing: .01em; }
      .kw-ai-head .kw-ai-title i { font-size: 1.15rem; opacity: .9; }
      .kw-ai-head small { display: block; color: #BFE0CE; font-weight: 500; font-size: .75rem; margin-top: 2px; }
      .kw-ai-head .btn-close { filter: brightness(0) invert(1); opacity: .75; padding: 10px; }
      .kw-ai-head .btn-close:hover { opacity: 1; }
      .kw-ai-chips { display: flex; flex-wrap: wrap; gap: 8px; padding: 16px 24px 8px; }
      .kw-ai-chip { border: 1px solid #CFE0D5; background: #fff; border-radius: 999px; padding: 7px 15px; font-size: .82rem; color: #24503A; cursor: pointer; transition: all .12s ease; }
      .kw-ai-chip:hover { border-color: #2E8B57; color: #155C3B; background: #F0F7F2; transform: translateY(-1px); box-shadow: 0 2px 8px rgba(21,92,59,.12); }
      .kw-ai-body { padding: 18px 24px; overflow-y: auto; flex: 1; }
      .kw-ai-body::-webkit-scrollbar { width: 8px; }
      .kw-ai-body::-webkit-scrollbar-thumb { background: #C9D8CF; border-radius: 8px; }
      .kw-ai-answer { font-size: .95rem; line-height: 1.7; color: #1B2620; }
      .kw-ai-answer p { margin: 0 0 10px; }
      .kw-ai-answer ul, .kw-ai-answer ol { margin: 0 0 10px; padding-left: 22px; }
      .kw-ai-answer li { margin: 3px 0; }
      .kw-ai-answer strong { color: #123527; }
      .kw-ai-answer code { background: #EEF3EF; border-radius: 5px; padding: 1px 6px; font-size: .85em; }
      .kw-ai-rich { margin-top: 14px; }
      .kw-ai-chart { margin: 0 0 14px; }
      .kw-ai-chart figcaption { font-size: .82rem; font-weight: 600; color: #2D3A33; margin-bottom: 6px; }
      .kw-ai-chart-canvas { position: relative; height: 260px; background: #fff; border: 1px solid #E3E9E4; border-radius: 14px; padding: 10px; }
      .kw-ai-art { border: 1px solid #E3E9E4; border-radius: 14px; background: #fff; padding: 12px 14px; margin: 0 0 12px; }
      .kw-ai-art figcaption { font-size: .85rem; font-weight: 600; color: #123527; margin-bottom: 4px; }
      .kw-ai-art img, .kw-ai-art video { max-width: 100%; border-radius: 10px; display: block; }
      .kw-ai-art audio { width: 100%; }
      .kw-ai-art .kw-ai-dl { display: inline-flex; align-items: center; gap: 6px; margin-top: 8px; font-size: .78rem; color: #155C3B; text-decoration: none; border: 1px solid #CFE0D5; border-radius: 999px; padding: 5px 12px; }
      .kw-ai-art .kw-ai-dl:hover { background: #F0F7F2; }
      .kw-ai-status { font-size: .83rem; color: #5E6B64; display: flex; align-items: center; gap: 8px; padding: 4px 0 10px; }
      .kw-ai-status::before { content: ''; width: 8px; height: 8px; border-radius: 50%; background: #2E8B57; flex: none; }
      .kw-ai-status.kw-ai-busy::before { background: #D99A2B; animation: kwAiPulse 1s ease-in-out infinite; }
      @keyframes kwAiPulse { 50% { opacity: .35; } }
      .kw-ai-foot { display: flex; gap: 10px; padding: 16px 24px; border-top: 1px solid #E3E9E4; background: #fff; }
      .kw-ai-foot textarea { flex: 1; border: 1px solid #C9D8CF; border-radius: 14px; padding: 12px 15px; font-size: .92rem; resize: none; min-height: 48px; max-height: 140px; font-family: inherit; }
      .kw-ai-foot textarea:focus { outline: none; border-color: #2E8B57; box-shadow: 0 0 0 3px rgba(46,139,87,.15); }
      .kw-ai-btn { border: 0; border-radius: 12px; background: linear-gradient(135deg,#1E7A4E,#155C3B); color: #fff; font-weight: 600; font-size: .88rem; padding: 11px 20px; cursor: pointer; transition: filter .12s ease; }
      .kw-ai-btn:hover { filter: brightness(1.08); }
      .kw-ai-btn.ghost { background: #fff; color: #2D3A33; border: 1px solid #D5DED8; }
      .kw-ai-btn.ghost:hover { border-color: #2E8B57; color: #155C3B; filter: none; }
      .kw-ai-btn:disabled { opacity: .5; cursor: default; }
      .kw-ai-actions { display: flex; gap: 10px; padding: 0 24px 18px; background: #fff; }
      .kw-ai-actions .kw-ai-btn { flex: 1; padding: 8px 12px; font-size: .82rem; }
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
      <div class="kw-ai-head"><span class="kw-ai-title"><i class="bi bi-stars"></i><span>AI assistant<small id="kwAiRoute"></small></span></span>
        <button type="button" class="btn-close btn-close-white btn-sm" id="kwAiClose" aria-label="Close"></button></div>
      <div class="kw-ai-chips" id="kwAiChips"></div>
      <div class="kw-ai-body"><div class="kw-ai-status" id="kwAiStatus">Ask anything, or pick a suggestion for this page.</div>
        <div class="kw-ai-answer" id="kwAiAnswer"></div><div class="kw-ai-rich" id="kwAiRich"></div></div>
      <div class="kw-ai-foot">
        <textarea id="kwAiInput" rows="1" placeholder="What would you like help with?"></textarea>
        <button type="button" class="kw-ai-btn" id="kwAiSend">Send</button>
      </div>
      <div class="kw-ai-actions">
        <button type="button" class="kw-ai-btn ghost" id="kwAiInsert" disabled>Insert into field</button>
        <button type="button" class="kw-ai-btn ghost" id="kwAiCopy" disabled>Copy</button>
        <button type="button" class="kw-ai-btn ghost" id="kwAiClearCtx" title="Clear remembered AI context">Clear context</button>
        <button type="button" class="kw-ai-btn ghost" id="kwAiStop" hidden>Stop</button>
      </div>`;

    document.body.appendChild(fab);
    document.body.appendChild(panel);

    const $ = (id) => panel.querySelector('#' + id) || document.getElementById(id);
    const routeLabel = () => (window.REQUESTED_ROUTE || 'page').replace(/_/g, ' ');
    let stream = null;
    let acc = '';
    let finalText = '';
    let chartInstances = [];

    const escapeHtml = (s) => {
      const d = document.createElement('div');
      d.textContent = String(s ?? '');
      return d.innerHTML;
    };
    // Markdown answers render like a chat surface; the raw markdown stays in
    // acc/finalText so Copy/Insert hand over clean .md source.
    const renderMd = (t) => (
      window.KingswayMarkdown?.render
        ? window.KingswayMarkdown.render(t)
        : `<p>${escapeHtml(t)}</p>`
    );
    const setAnswerHtml = (t) => { $('kwAiAnswer').innerHTML = renderMd(t); };

    function clearRich() {
      chartInstances.forEach((c) => { try { c.destroy(); } catch (_) {} });
      chartInstances = [];
      $('kwAiRich').innerHTML = '';
    }

    function renderRich(res) {
      clearRich();
      const host = $('kwAiRich');
      const charts = Array.isArray(res?.charts) ? res.charts.slice(0, 3) : [];
      charts.forEach((item) => {
        const spec = item?.chart;
        if (!spec || typeof window.Chart !== 'function') return;
        if (!['bar', 'line', 'pie', 'doughnut', 'area'].includes(spec.type)) return;
        const fig = document.createElement('figure');
        fig.className = 'kw-ai-chart';
        fig.innerHTML = `<figcaption>${escapeHtml(String(spec.title || 'Chart'))}</figcaption><div class="kw-ai-chart-canvas"><canvas></canvas></div>`;
        host.appendChild(fig);
        const type = spec.type === 'area' ? 'line' : spec.type;
        chartInstances.push(new window.Chart(fig.querySelector('canvas'), {
          type,
          data: {
            labels: (Array.isArray(spec.labels) ? spec.labels : []).slice(0, 25).map((v) => String(v).slice(0, 120)),
            datasets: (Array.isArray(spec.datasets) ? spec.datasets : []).slice(0, 4).map((d) => ({
              label: String(d?.label || 'Values').slice(0, 80),
              data: (Array.isArray(d?.data) ? d.data : []).slice(0, 25).map((v) => (Number.isFinite(Number(v)) ? Number(v) : null)),
            })),
          },
          options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } },
        }));
      });
      const arts = Array.isArray(res?.artifacts) ? res.artifacts.slice(0, 4) : [];
      arts.forEach((a) => {
        if (!a || typeof a !== 'object') return;
        const url = String(a.url || a.image || '');
        const mime = String(a.mime_type || '').toLowerCase();
        const title = escapeHtml(String(a.title || 'Attachment').slice(0, 120));
        const dl = url ? `<a class="kw-ai-dl" href="${escapeHtml(url)}" download="${escapeHtml(String(a.download_name || 'download'))}"><i class="bi bi-download"></i>Download</a>` : '';
        let inner = '';
        if (url && (a.image || mime.startsWith('image/'))) {
          inner = `<img src="${escapeHtml(url)}" alt="${title}" loading="lazy">`;
        } else if (url && mime.startsWith('audio/')) {
          inner = `<audio controls preload="none" src="${escapeHtml(url)}"></audio>`;
        } else if (url && mime.startsWith('video/')) {
          inner = `<video controls preload="none" src="${escapeHtml(url)}"></video>`;
        }
        const fig = document.createElement('figure');
        fig.className = 'kw-ai-art';
        fig.innerHTML = `<figcaption>${title}</figcaption>${inner}${dl}`;
        host.appendChild(fig);
      });
    }

    function setStatus(t, busy) {
      const el = $('kwAiStatus');
      el.textContent = t || '';
      el.classList.toggle('kw-ai-busy', !!busy);
    }
    // Auto-grow the composer up to the CSS max-height.
    const input = $('kwAiInput');
    const growInput = () => {
      input.style.height = 'auto';
      input.style.height = Math.min(input.scrollHeight, 120) + 'px';
    };
    input.addEventListener('input', growInput);
    input.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); $('kwAiSend').click(); }
    });
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

    function userMemoryKey() {
      const u = window.AuthContext?.getUser?.() || {};
      const uid = u.id || u.user_id || 'anon';
      const rid = u.role_id || (u.role_ids && u.role_ids[0]) || 'role';
      return `kw_ai_ctx_${uid}_${rid}`;
    }
    function loadUserMemory() { try { return JSON.parse(localStorage.getItem(userMemoryKey()) || '{}'); } catch (_) { return {}; } }
    function saveUserMemory(patch) {
      try { const cur = loadUserMemory(); localStorage.setItem(userMemoryKey(), JSON.stringify({ ...cur, ...patch, updated_at: Date.now() })); } catch (_) {}
    }
    function clearUserMemory() {
      try { localStorage.removeItem(userMemoryKey()); } catch(_){}
      try { window.API?.dashboard?.clearAgentContext?.(); } catch(_){}
      setStatus('AI context cleared for this user.');
    }

    function run(question) {
      const api = window.API?.dashboard?.agentAssistStream;
      if (typeof api !== 'function') { setStatus('The assistant is not available right now.'); return; }
      if (!question || !String(question).trim()) { setStatus('Type what you need first.'); return; }
      acc = ''; finalText = '';
      setAnswerHtml('');
      clearRich();
      $('kwAiInsert').disabled = true; $('kwAiCopy').disabled = true;
      $('kwAiStop').hidden = false;
      setStatus('Drafting…', true);
      const ctx = getPageContext();
      const route = normalizeRoute(ctx.route || window.REQUESTED_ROUTE || '');
      const mem = loadUserMemory();
      const history = Array.isArray(mem.recent) ? mem.recent.slice(-6) : [];
      const q = String(question).slice(0, 4000);
      saveUserMemory({ last_route: route, last_prompt: q.slice(0,200), recent: [...history, {route, q:q.slice(0,160), at:Date.now()}].slice(-10) });
      stream = api(q, route, 'dashboard', {
        page_context: ctx,
        user_memory: { last_route: mem.last_route, recent: history },
        onDelta: (t) => { acc += t; setAnswerHtml(acc); const body = panel.querySelector('.kw-ai-body'); body.scrollTop = body.scrollHeight; },
        onStatus: (t) => setStatus(t || 'Working…', true),
        onFinal: (res) => {
          $('kwAiStop').hidden = true;
          finalText = answerText(res, acc) || acc;
          setAnswerHtml(finalText);
          renderRich(res);
          $('kwAiInsert').disabled = !finalText; $('kwAiCopy').disabled = !finalText;
          setStatus('Done — insert it into a field or copy it.', false);
          saveUserMemory({ last_answer: String(finalText||'').slice(0,400) });
        },
        onError: (m) => { $('kwAiStop').hidden = true; setStatus(m || 'Could not finish. Try again.', false); },
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

    function openPanel(prefill) {
      panel.hidden = false;
      fab.style.display = 'none'; // the launcher steps aside while chat is open
      $('kwAiRoute').textContent = '· ' + routeLabel();
      renderChips();
      if (prefill) $('kwAiInput').value = String(prefill).slice(0, 4000);
      $('kwAiInput').focus();
      if (prefill) $('kwAiSend').click();
    }
    function closePanel() { panel.hidden = true; fab.style.display = ''; }
    function togglePanel() { panel.hidden ? openPanel() : closePanel(); }

    fab.addEventListener('click', togglePanel);
    // Header button / external hooks
    document.addEventListener('click', (e) => {
      const t = e.target?.closest?.('[data-kw-ai-toggle]');
      if (!t) return;
      e.preventDefault();
      togglePanel();
    });
    window.KingswayAssistant = Object.assign(window.KingswayAssistant || {}, {
      ask: (question) => openPanel(question),
      open: (question) => openPanel(question),
      close: closePanel,
      toggle: togglePanel,
    });
    $('kwAiClose').addEventListener('click', closePanel);
    $('kwAiSend').addEventListener('click', () => { const v = $('kwAiInput').value; $('kwAiInput').value = ''; run(v); });
    $('kwAiInput').addEventListener('keydown', (e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); $('kwAiSend').click(); } });
    $('kwAiInsert').addEventListener('click', insertIntoField);
    $('kwAiCopy').addEventListener('click', copyAnswer);
    $('kwAiClearCtx').addEventListener('click', clearUserMemory);
    $('kwAiStop').addEventListener('click', () => { try { stream?.cancel?.(); } catch (_) {} $('kwAiStop').hidden = true; setStatus('Stopped — partial text kept.'); });

    const show = () => { if (window.AuthContext?.getUser?.()) { fab.hidden = false; } };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', show); else show();
    window.addEventListener('kingsway:page-loaded', () => { $('kwAiRoute').textContent = '· ' + routeLabel(); renderChips(); });
  }

  /* ── Generic modal/form drafting coverage ───────────────────────────────
     Any authenticated page modal with a meaningful text field gets a
     "Draft with AI" control automatically. This gives Layer 3 coverage
     across the system without per-page wiring; explicit mounts still win
     because mount() marks buttons data-ai-draft-bound. */
  function isDraftableField(el) {
    if (!el || el.disabled || el.readOnly) return false;
    if (el.closest('#kwAiAssistPanel')) return false;
    if (el.dataset.aiDraftAuto === '1' || el.dataset.aiDraftSkip === '1') return false;
    const tag = el.tagName;
    if (tag === 'TEXTAREA') return true;
    if (tag === 'INPUT') {
      const t = (el.type || 'text').toLowerCase();
      return ['text','search','email','url','tel'].includes(t);
    }
    return !!el.isContentEditable;
  }

  function fieldLabel(el) {
    const id = el.id;
    if (id) {
      const lbl = document.querySelector(`label[for="${CSS.escape(id)}"]`);
      if (lbl?.textContent?.trim()) return lbl.textContent.trim().replace(/[*:]+$/,'').trim();
    }
    const aria = el.getAttribute('aria-label') || el.getAttribute('placeholder') || el.name || el.id || 'this field';
    return String(aria).replace(/[_-]+/g,' ').replace(/[*:]+$/,'').trim().slice(0, 60);
  }

  function modalTitle(el) {
    const modal = el.closest('.modal');
    const t = modal?.querySelector('.modal-title, h5, h4, h3')?.textContent?.trim();
    return t ? t.slice(0, 80) : '';
  }

  function autoMountField(el) {
    if (!isDraftableField(el)) return;
    if (el.dataset.aiDraftAuto === '1' || el.dataset.aiDraftBoundTarget === '1') return;
    if (el.id && document.querySelector(`[data-ai-draft-bound="1"][data-ai-draft-target="${CSS.escape(el.id)}"]`)) return;
    const next = el.nextElementSibling;
    if (next && next.querySelector && next.querySelector('[id$="AiHost"],[id*="AiHost"],[id*="DraftBtn"],[id*="Draft"]')) return;
    const parent = el.parentElement;
    if (parent && el.id) {
      const covers = Array.from(parent.querySelectorAll('[data-ai-draft-bound="1"]'))
        .some((b) => b.dataset.aiDraftTarget === el.id);
      if (covers) return;
    }
    const modal = el.closest('.modal, .offcanvas, form, .card, .panel, section');
    if (!modal && el.tagName === 'INPUT') return;
    if (el.tagName === 'INPUT' && /search|filter|date|time|number|amount|price|qty|quantity|phone|otp|code|password|pin/i.test((el.name||'')+(el.id||'')+(el.placeholder||'')+(el.type||''))) return;
    const label = fieldLabel(el);
    if (/search|filter/i.test(label) && el.tagName === 'INPUT') return;
    const host = document.createElement('div');
    host.className = 'ai-draft-host-auto';
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn btn-link btn-sm p-0 ai-draft-auto-btn';
    btn.innerHTML = '<i class="bi bi-stars me-1"></i>Draft with AI';
    btn.style.fontSize = '.78rem';
    // Draftable fields authored without an id (common in hand-built modals)
    // still need a stable handle for Insert-into-field targeting.
    if (!el.id) { el.id = 'kwAiField' + (++__aiFieldSeq); }
    btn.dataset.aiDraftTarget = el.id;
    const wrap = document.createElement('div');
    wrap.className = 'ai-draft-auto-wrap mt-1';
    wrap.appendChild(btn);
    wrap.appendChild(host);
    el.insertAdjacentElement('afterend', wrap);
    el.dataset.aiDraftAuto = '1';
    const route = window.REQUESTED_ROUTE || 'page';
    const moduleName = inferModule(route);
    const mt = modalTitle(el);
    mount({
      button: btn, host: host, route, module: moduleName, liveTarget: el, label: `Draft ${label}`,
      prompt: () => {
        const ctx = [mt ? `Modal/form: ${mt}` : '', `Field: ${label}`, `Page: ${route.replace(/_/g,' ')}`].filter(Boolean).join('\n');
        const existing = (el.value || el.textContent || '').trim();
        return `Draft content for the field "${label}" on the Kingsway school system page. ${ctx}.\n${existing ? `Existing text to improve/continue:\n${existing}\n` : ''}Write only the field content, professional and clear. Do not invent private learner data.`;
      },
    });
  }

  function scanAutoDraft(root) {
    const scope = root && root.querySelectorAll ? root : document;
    const fields = scope.querySelectorAll('textarea, input[type="text"], input:not([type]), input[type="search"], input[type="email"], input[type="url"], [contenteditable="true"]');
    fields.forEach(autoMountField);
  }

  // ---------------------------------------------------------------------------
  // Universal L3 form coverage.
  //
  // Every draftable field on EVERY page gets an inline "Draft with AI" affordance:
  // textareas, plain text/email/url inputs, contenteditable surfaces, plus any
  // field that appears later inside a modal, offcanvas, JS-rendered card or a
  // soft-navigated page fragment. Pages that hand-author their own mount()
  // (CMS drafts) keep their labelled buttons; this scanner is the universal
  // floor so no form on any of the 466 routes is left without inline drafting.
  // ---------------------------------------------------------------------------
  const AI_MODULE_HINTS = [
    ['finance', /fee|financ|payment|invoice|payroll|budget|expense|mpesa|kcb|bursar|arrear|payslip|requisition|petty|disburse|reconcil|ledger|voucher|statutory|remittance/i],
    ['academics', /academic|assessment|exam|result|grade|mark|lesson|scheme|syllabus|rubric|competenc|curriculum|report_card|cat|timetable|subject|learning_area|term_report/i],
    ['students', /student|learner|pupil|enrol|promotion|clearance|portfolio|guardian|parent_contact/i],
    ['hr_staff', /staff|employee|teacher|leave|appointment|onboard|payroll|performance_review|role_assign|department/i],
    ['admissions', /admission|applicant|application|interview|placement|enrolment/i],
    ['discipline', /discipline|conduct|incident|sanction|suspension|expulsion|counsel/i],
    ['communications', /communicat|announcement|sms|email|whatsapp|notice|message|inquiry|news|article/i],
    ['inventory', /inventory|stock|store|uniform|catalog|product|supplier|vendor|purchase_order|requisition/i],
    ['attendance', /attendance|absent|present|roll_call|late_arrival|truancy|exeat/i],
    ['transport', /transport|vehicle|driver|route_assign|passenger|fleet/i],
    ['boarding', /boarding|dormitor|hostel|roll_call|weekend/i],
    ['catering', /catering|food|meal|menu|nutrition|kitchen/i],
    ['activities', /activity|club|society|sport|competition|event|gallery/i],
    ['library', /library|book|borrow|overdue|catalog/i],
    ['health_welfare', /health|medical|welfare|vaccin|sick_bay|counsel|special_needs/i],
    ['website', /website|public|web|cms|page_content|vacanc|download|seo/i],
    ['system_admin', /system|setting|user|role|permission|audit|security|backup|migration|feature_flag|module_enable|api|webhook|token|session|log/i],
    ['reports', /report|analytic|dashboard|metric|insight|statistic/i],
  ];

  let __aiFieldSeq = 0;

  function inferModule(route) {
    const override = (window.KW_AI_MODULE || document.body?.dataset?.aiModule || document.body?.dataset?.module || '').toString().trim();
    if (override) return override;
    const key = String(route || '').replace(/^\/+/, '');
    for (const [module, re] of AI_MODULE_HINTS) {
      if (re.test(key)) return module;
    }
    return 'general';
  }

  function scanAutoDraft(root) {
    const scope = root && root.querySelectorAll ? root : document;
    const fields = scope.querySelectorAll(
      'textarea, input[type="text"], input:not([type]), input[type="email"], input[type="url"], ' +
      'input[type="tel"], [contenteditable="true"], [data-ai-draftable="1"]'
    );
    fields.forEach(autoMountField);
  }

  let autoScanTimer = null;
  function scheduleAutoScan(root) {
    clearTimeout(autoScanTimer);
    autoScanTimer = setTimeout(() => scanAutoDraft(root || document), 250);
  }

  function initAutoDraftCoverage() {
    if (window.__kwAiAutoDraftInit) return;
    window.__kwAiAutoDraftInit = true;
    scheduleAutoScan(document);
    document.addEventListener('shown.bs.modal', (e) => scheduleAutoScan(e.target));
    document.addEventListener('shown.bs.offcanvas', (e) => scheduleAutoScan(e.target));
    document.addEventListener('shown.bs.dropdown', (e) => scheduleAutoScan(e.target));
    window.addEventListener('kingsway:page-loaded', () => scheduleAutoScan(document));
    window.addEventListener('kingsway:fragment-loaded', () => scheduleAutoScan(document));
    document.addEventListener('submit', () => scheduleAutoScan(document), true);
    const obs = new MutationObserver((mutations) => {
      for (const m of mutations) {
        for (const n of m.addedNodes || []) {
          if (n.nodeType === 1) { scheduleAutoScan(n); return; }
        }
      }
    });
    obs.observe(document.body, { childList: true, subtree: true });
    // Re-scan on viewport changes so lazily rendered forms are covered too.
    window.addEventListener('resize', () => scheduleAutoScan(document));
    // Periodic safety net for pages that hydrate late without a DOM event.
    setInterval(() => {
      const open = document.querySelectorAll('.modal.show, .offcanvas.show').length;
      if (open) scheduleAutoScan(document);
    }, 1500);
  }

  // The universal chat workspace is owned by ai_chat_panel.js. This file keeps
  // the inline form-draft API and its page-task helpers, but must not mount a
  // second floating panel alongside the session-aware one.
  if (!window.__kwAiAutoDraftMounted) {
    window.__kwAiAutoDraftMounted = true;
    const boot = () => { initAutoDraftCoverage(); };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
  }

  window.AiInlineDraft = { mount, mountPageAssist, __debug: { pageTasksFor, userRoleKeys, normalizeRoute, getPageContext, resolvePageEntry } };
})();
