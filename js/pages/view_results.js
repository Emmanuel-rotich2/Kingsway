/* Results Management workspace: formative, summative, and pooled-average results.
   Filter-driven, so every filter change re-loads automatically — there is no Load button.
   Server-side RBAC and row scope remain the authority; this controller only renders. */
(() => {
  const $ = (id) => document.getElementById(id);
  const esc = (value) => { const n = document.createElement('span'); n.textContent = String(value ?? ''); return n.innerHTML; };
  const unwrap = (response) => response?.data?.data ?? response?.data ?? response;
  const notify = (message, type = 'info') => window.API?.showNotification?.(message, type) || window.showNotification?.(message, type);
  const payload = (path, method = 'GET', body = null, query = null) => unwrap(window.API.apiCall(path, method, body, query));
  /** A 409 from a register write is contention, not a rejection: retry it once. */
  const isContention = (error) => Number(error?.response?.status ?? error?.status ?? 0) === 409;
  async function payloadWithRetry(path, method = 'GET', body = null, query = null) {
    try {
      return await payload(path, method, body, query);
    } catch (error) {
      if (!isContention(error)) throw error;
      await new Promise((resolve) => setTimeout(resolve, 400));
      return payload(path, method, body, query);
    }
  }

  const state = {
    terms: [],
    classes: [],
    tab: 'formative',
    rows: { formative: [], summative: [], average: [] },
    loaded: { formative: false, summative: false, average: false },
    marks: { assessmentId: 0, maxMarks: 100, rows: [] },
    busy: false,
    searchTimer: null,
  };

  const canManage = () => window.AuthContext?.hasAnyPermission?.(['academic_manage', 'academic_edit', 'assessments_edit', 'results_manage'])
    || ['System Administrator', 'School Administrator', 'Headteacher'].some((r) => window.AuthContext?.hasRole?.(r));
  const canRecord = () => canManage()
    || window.AuthContext?.hasAnyPermission?.(['assessments_create', 'assessments_edit', 'results_manage']);
  const canPublish = () => ['System Administrator', 'School Administrator'].some((r) => window.AuthContext?.hasRole?.(r))
    || window.AuthContext?.hasAnyPermission?.(['results_publish']);
  const canDistribute = () => canPublish();
  const canExport = () => typeof window.AuthContext?.canExport !== 'function' || window.AuthContext.canExport('academic');
  const canPrint = () => typeof window.AuthContext?.canPrint !== 'function' || window.AuthContext.canPrint('academic');

  const band = (pct) => (pct >= 80 ? 'EE' : pct >= 50 ? 'ME' : pct >= 25 ? 'AE' : 'BE');
  const bandClass = (b) => ({ EE: 'grade-EE', ME: 'grade-ME', AE: 'grade-AE', BE: 'grade-BE' }[b] || '');
  const stamp = (value) => {
    if (!value) return '';
    const d = new Date(String(value).replace(' ', 'T'));
    return Number.isNaN(d.getTime()) ? esc(value) : esc(d.toLocaleString('en-KE', { dateStyle: 'medium', timeStyle: 'short' }));
  };

  function filters() {
    return {
      year_id: $('vrYearSelect')?.value || '',
      term_id: $('vrTermSelect')?.value || '',
      class_id: $('vrClassSelect')?.value || '',
      search: ($('vrSearchInput')?.value || '').trim(),
    };
  }
  function query() {
    const f = filters();
    const parts = [];
    Object.entries(f).forEach(([key, value]) => { if (value !== '') parts.push(`${encodeURIComponent(key)}=${encodeURIComponent(value)}`); });
    return parts.length ? `?${parts.join('&')}` : '';
  }
  function scopeLabel() {
    const f = filters();
    const year = state.terms.find((t) => String(t.id) === String(f.term_id));
    const term = year ? `${year.year_name} · ${year.name}` : 'All terms';
    const klass = f.class_id ? (state.classes.find((c) => String(c.id) === String(f.class_id))?.name || 'Selected class') : 'all authorised classes';
    $('vrScopeLabel').textContent = `Scope: ${term} · ${klass}${f.search ? ` · search "${f.search}"` : ''}`;
  }

  async function loadReferences() {
    try { state.terms = (await payload('/academic/terms-list')) || []; } catch (_) { state.terms = []; }
    if (!Array.isArray(state.terms)) state.terms = [];
    try { state.classes = (await payload('/academic/classes-list')) || []; } catch (_) { state.classes = []; }
    if (!Array.isArray(state.classes)) state.classes = [];

    const years = [];
    state.terms.forEach((t) => { if (t.year && !years.some((y) => y.id === t.year)) years.push({ id: t.year, name: t.year_name || `Academic year ${t.year}` }); });

    const yearSelect = $('vrYearSelect');
    const termSelect = $('vrTermSelect');
    const classSelect = $('vrClassSelect');
    const currentYear = Number(yearSelect?.value) || Number(window.AcademicContext?.getAcademicYearId?.()) || years[0]?.id || '';
    const currentTerm = Number(termSelect?.value) || Number(window.AcademicContext?.getTermId?.()) || 0;

    yearSelect.innerHTML = years.length
      ? years.map((y) => `<option value="${y.id}">${esc(y.name)}</option>`).join('')
      : '<option value="">No academic years</option>';
    if (currentYear) yearSelect.value = String(currentYear);

    const termOptions = state.terms.filter((t) => !yearSelect.value || String(t.year) === yearSelect.value);
    termSelect.innerHTML = termOptions.length
      ? termOptions.map((t) => `<option value="${t.id}">${esc(t.name)}</option>`).join('')
      : '<option value="">No terms</option>';
    if (termOptions.some((t) => Number(t.id) === currentTerm)) termSelect.value = String(currentTerm);

    classSelect.innerHTML = '<option value="">All classes</option>'
      + state.classes.map((c) => `<option value="${c.id}">${esc(c.name)}${c.student_count ? ` (${Number(c.student_count)})` : ''}</option>`).join('');
    await applyDefaultClassScope(classSelect);
  }

  /**
   * The server decides the default class scope: a class teacher assigned to
   * exactly one class starts there; everyone else starts on All classes.
   */
  async function applyDefaultClassScope(classSelect) {
    if (!classSelect || !classSelect.options.length) return;
    try {
      const resolved = (await payload('/academic/my-default-class')) || {};
      const defaultId = Number(resolved?.class_id || 0);
      if (defaultId && [...classSelect.options].some((option) => Number(option.value) === defaultId)) {
        classSelect.value = String(defaultId);
      }
    } catch (_) { /* All classes stays the default */ }
  }

  function renderKpis(target, cards) {
    $(target).innerHTML = cards.map((card) => `
      <div class="col-6 col-md-3"><div class="card vr-card vr-kpi h-100"><div class="card-body py-2 px-3">
        <div class="text-muted small">${esc(card.label)}</div>
        <div class="fs-5 fw-bold">${card.value}</div>
        ${card.note ? `<div class="small text-muted">${esc(card.note)}</div>` : ''}
      </div></div></div>`).join('');
  }

  const loading = (label) => `<div class="vr-loading"><div class="spinner-border" role="status"></div><div class="text-muted mt-2">${esc(label)}</div></div>`;
  const empty = (message, icon = 'bi-inbox') => `<div class="vr-empty"><i class="bi ${icon}"></i>${esc(message)}</div>`;
  const failed = (message) => `<div class="alert alert-danger mb-0">${esc(message)}</div>`;

  async function loadTab(tab) {
    if (tab === 'portfolio') return loadPortfolioTab();
    const container = { formative: 'vrFormativeContainer', summative: 'vrSummativeContainer', average: 'vrAverageContainer' }[tab];
    const label = { formative: 'Loading formative assessments…', summative: 'Loading summative results…', average: 'Loading averages…' }[tab];
    $(container).innerHTML = loading(label);
    $('vrFreshness').textContent = 'Refreshing…';
    try {
      const result = await payload(`/academic/results-management-${tab}${query()}`);
      const rows = Array.isArray(result) ? result : (result?.items || []);
      state.rows[tab] = rows;
      state.loaded[tab] = true;
      if (tab === 'formative') renderFormative(rows);
      if (tab === 'summative') renderSummative(rows, await loadExamPeriods());
      if (tab === 'average') renderAverage(rows);
      $('vrFreshness').textContent = `${rows.length} row${rows.length === 1 ? '' : 's'} · updated ${new Date().toLocaleTimeString('en-KE', { hour: '2-digit', minute: '2-digit' })}`;
    } catch (error) {
      $(container).innerHTML = failed(error.message || 'Unable to load these results.');
      $('vrFreshness').textContent = 'Last refresh failed';
    }
  }
  function refresh() {
    scopeLabel();
    state.loaded[tab()] = false;
    loadTab(state.tab);
  }
  const tab = () => state.tab;
  function setTab(next) {
    state.tab = next;
    scopeLabel();
    if (!state.loaded[next]) loadTab(next);
  }

  // ── E-Portfolio (master hub tab 4) ────────────────────────────────────
  let portfolioRows = [];
  async function loadPortfolioTab() {
    const container = $('vrPortfolioContainer');
    if (!container) return;
    container.innerHTML = '<div class="vr-loading"><div class="spinner-border" role="status"><span class="visually-hidden">Loading portfolios…</span></div></div>';
    const classId = Number($('vrClassSelect')?.value || 0);
    const termId = Number($('vrTermSelect')?.value || 0);
    try {
      const data = (await payload(`/academic/portfolio-hub?class_id=${classId}&term_id=${termId}`)) || {};
      renderPortfolio(data);
    } catch (error) {
      container.innerHTML = failed(error.message || 'Unable to load portfolios.');
    }
  }
  /** All-classes overview: one aggregated evidence row per class. */
  function renderPortfolioRollup(data) {
    const classes = Array.isArray(data?.classes) ? data.classes : [];
    const totals = classes.reduce((sum, row) => ({
      learners: sum.learners + Number(row.learners || 0),
      portfolios: sum.portfolios + Number(row.portfolios || 0),
      artifacts: sum.artifacts + Number(row.artifacts || 0),
      withEvidence: sum.withEvidence + Number(row.learners_with_evidence || 0),
      projects: sum.projects + Number(row.project_evidence || 0),
      writtenTests: sum.writtenTests + Number(row.written_test_evidence || 0),
    }), { learners: 0, portfolios: 0, artifacts: 0, withEvidence: 0, projects: 0, writtenTests: 0 });
    renderKpis('vrPortfolioKpis', [
      { label: 'Classes', value: classes.length },
      { label: 'Learners', value: totals.learners },
      { label: 'Portfolios', value: totals.portfolios, note: `${totals.withEvidence} learners with evidence` },
      { label: 'Artifacts', value: totals.artifacts },
      { label: 'Projects (T2)', value: totals.projects, note: 'SBA project evidence' },
      { label: 'Written tests (T3)', value: totals.writtenTests },
    ]);
    const container = $('vrPortfolioContainer');
    if (!classes.length) { container.innerHTML = empty('No classes have active learners yet.', 'bi-journal-richtext'); return; }
    const chips = (row) => ['project', 'performance_task', 'written_test', 'reflection']
      .map((source) => `<span class="badge bg-academic-subtle text-academic me-1">${source.replace('_', ' ')}: ${Number(row[`${source}_evidence`] || 0)}</span>`).join('');
    container.innerHTML = `<div class="table-responsive"><table class="table table-sm table-bordered align-middle"><thead class="table-light"><tr><th>Class</th><th>Learners</th><th>Portfolios</th><th>With evidence</th><th>Artifacts</th><th>Evidence by source</th></tr></thead><tbody>
      ${classes.map((row) => `<tr>
        <td class="fw-semibold">${esc(row.class_name || '')}</td>
        <td>${Number(row.learners || 0)}</td>
        <td>${Number(row.portfolios || 0)}</td>
        <td>${Number(row.learners_with_evidence || 0)}</td>
        <td>${Number(row.artifacts || 0)}</td>
        <td>${chips(row)}</td>
      </tr>`).join('')}
    </tbody></table></div>
    <div class="small text-muted mt-1">All-classes overview. Pick one class above to open its per-learner portfolio evidence, then export the KNEC CBA manifest.</div>`;
  }

  function renderPortfolio(data) {
    portfolioRows = Array.isArray(data?.manifest) ? data.manifest : [];
    if ((data?.scope ?? '') === 'all_classes') return renderPortfolioRollup(data);

    const learners = Array.isArray(data?.learners) ? data.learners : [];
    const withPortfolio = learners.filter((l) => Number(l.artifact_count || 0) > 0).length;
    const sources = ['project', 'performance_task', 'written_test', 'homework', 'co_curricular', 'reflection'];
    const totals = Object.fromEntries(sources.map((source) => [source, learners.reduce((sum, l) => sum + Number((l.evidence_counts || {})[source] || 0), 0)]));
    renderKpis('vrPortfolioKpis', [
      { label: 'Learners', value: learners.length },
      { label: 'With evidence', value: withPortfolio, note: `${learners.length - withPortfolio} without artifacts` },
      { label: 'Projects (T2)', value: totals.project, note: 'SBA project evidence' },
      { label: 'Performance tasks', value: totals.performance_task },
      { label: 'Written tests (T3)', value: totals.written_test },
      { label: 'Reflections', value: totals.reflection },
    ]);
    const container = $('vrPortfolioContainer');
    if (!learners.length) { container.innerHTML = empty('No active learners found for this class.', 'bi-journal-richtext'); return; }
    const chips = (counts) => sources
      .filter((source) => Number((counts || {})[source] || 0) > 0)
      .map((source) => `<span class="badge bg-academic-subtle text-academic me-1">${source.replace('_', ' ')}: ${(counts || {})[source]}</span>`).join('');
    container.innerHTML = `<div class="table-responsive"><table class="table table-sm table-bordered align-middle"><thead class="table-light"><tr><th>Learner</th><th>Adm. No</th><th>Stream</th><th>Portfolio</th><th>Evidence</th><th>Learning areas</th></tr></thead><tbody>
      ${learners.map((l) => `<tr>
        <td>${esc(((l.first_name || '') + ' ' + (l.last_name || '')).trim())}</td>
        <td>${esc(l.admission_no || '—')}</td>
        <td>${esc(l.stream_name || '—')}</td>
        <td>${Number(l.portfolio_id || 0) ? `<span class="badge bg-success-subtle text-success">${esc(l.portfolio_status || 'active')} · ${esc(String(l.academic_year || ''))}</span>` : '<span class="text-muted small">None yet</span>'}</td>
        <td>${Number(l.artifact_count || 0) ? `${Number(l.artifact_count)} artifact(s)<br>${chips(l.evidence_counts)}` : '<span class="text-muted small">No evidence filed</span>'}</td>
        <td>${(l.learning_areas || []).length ? (l.learning_areas || []).map((area) => esc(area)).join(', ') : '—'}</td>
      </tr>`).join('')}
    </tbody></table></div>
    <div class="small text-muted mt-1">KNEC circular 7.0: retain marked scripts, score breakdown sheets and portfolio evidence for official verification. File Term 2 projects before the Term 3 written tests close the cycle.</div>`;
  }

  // ── Formative ────────────────────────────────────────────────────────
  function renderFormative(rows) {
    const recorded = rows.filter((r) => Number(r.student_count || 0) > 0).length;
    renderKpis('vrFormativeKpis', [
      { label: 'Assessments', value: rows.length },
      { label: 'With marks recorded', value: recorded, note: 'Learner scores saved' },
      { label: 'Awaiting marks', value: rows.length - recorded },
      { label: 'Learning areas', value: new Set(rows.map((r) => r.subject_name).filter(Boolean)).size },
    ]);
    if (!rows.length) { $('vrFormativeContainer').innerHTML = empty('No formative assessments match this scope.', 'bi-clipboard2'); return; }
    const canRecord = window.viewResultsCtrl.canRecord();
    $('vrFormativeContainer').innerHTML = `
      <table class="table table-sm table-hover align-middle" id="vrFormativeTable">
        <thead class="table-light"><tr>
          <th>Assessment</th><th>Learning area</th><th>Class / stream</th><th>Term</th>
          <th>Date</th><th class="text-center">Out of</th><th class="text-center">Marks in</th>
          <th>Status</th><th class="vr-no-print">Actions</th>
        </tr></thead>
        <tbody>${rows.map((r) => `
          <tr>
            <td class="fw-semibold">${esc(r.title || r.name || '—')}</td>
            <td>${esc(r.subject_name || '—')}</td>
            <td>${esc(r.class_name || '—')}${r.stream_name ? ` · ${esc(r.stream_name)}` : ''}</td>
            <td>${esc(r.term_name || '—')}</td>
            <td>${esc(r.assessment_date || r.cat_date || '—')}</td>
            <td class="text-center">${esc(r.max_marks ?? '—')}</td>
            <td class="text-center">${Number(r.student_count || 0) > 0 ? `<span class="badge bg-success-subtle text-success">${Number(r.student_count)}</span>` : '<span class="badge bg-secondary-subtle text-secondary">None</span>'}</td>
            <td><span class="badge bg-light text-dark border">${esc(r.status || '—')}</span></td>
            <td class="vr-no-print">${canRecord ? `<button class="btn btn-sm btn-outline-primary" data-marks-open="${Number(r.id)}" data-max="${esc(r.max_marks ?? 100)}"><i class="bi bi-pencil-square me-1"></i>Record marks</button>` : '<span class="text-muted small">View only</span>'}</td>
          </tr>`).join('')}
        </tbody>
      </table>`;
    $('vrFormativeContainer').querySelectorAll('[data-marks-open]').forEach((button) => {
      button.onclick = () => openMarks(Number(button.dataset.marksOpen), Number(button.dataset.max) || 100);
    });
  }

  // ── Summative ────────────────────────────────────────────────────────
  /** Exam periods for the selected term, so the tab is meaningful even with zero result rows. */
  async function loadExamPeriods() {
    try {
      const list = (await payload('/academic/exam-periods')) || [];
      const termId = String($('vrTermSelect')?.value || '');
      return (Array.isArray(list) ? list : []).filter((p) => !termId || String(p.academic_year_term_id) === termId);
    } catch (_) { return []; }
  }

  function renderSummative(rows, periods = []) {
    const recorded = rows.filter((r) => r.result_id && !r.result_deleted_at).length;
    const missing = rows.filter((r) => !r.result_id).length;
    const published = new Set(rows.filter((r) => r.results_published_at).map((r) => Number(r.exam_period_id)));
    const rowPeriods = [...new Map(rows.map((r) => [Number(r.exam_period_id), r])).values()];
    const merged = new Map(rowPeriods.map((p) => [Number(p.exam_period_id), p]));
    periods.forEach((p) => {
      const id = Number(p.id);
      if (!merged.has(id)) merged.set(id, { exam_period_id: id, exam_period_title: p.title, period_status: p.status, results_published_at: p.results_published_at, entry_mode: p.entry_mode, deleted_at: p.deleted_at });
    });
    const allPeriods = [...merged.values()];
    const mean = rows.length
      ? rows.filter((r) => r.percentage !== null && r.percentage !== undefined).reduce((sum, r) => sum + Number(r.percentage), 0)
        / Math.max(1, rows.filter((r) => r.percentage !== null && r.percentage !== undefined).length)
      : 0;
    renderKpis('vrSummativeKpis', [
      { label: 'Result rows', value: rows.length },
      { label: 'Recorded', value: recorded, note: `${missing} awaiting a mark` },
      { label: 'Mean score', value: `${mean.toFixed(1)}%` },
      { label: 'Exam periods', value: allPeriods.length, note: `${published.size} published` },
    ]);

    const canRecord = window.viewResultsCtrl.canRecord();
    const pendingRegisters = new Map();
    rows.forEach((r) => {
      if (r.assessment_id && r.assessment_status === 'pending_submission') pendingRegisters.set(Number(r.assessment_id), r);
    });
    const actions = allPeriods.map((r) => {
      const isPublished = Boolean(r.results_published_at);
      // Publication is only meaningful once results are open for entry or in moderation.
      const publishable = ['results_open', 'moderation'].includes(String(r.period_status || ''));
      const canPublish = window.viewResultsCtrl.canPublish() && !isPublished && publishable;
      const periodId = Number(r.exam_period_id);
      const pendings = [...pendingRegisters.values()].filter((reg) => Number(reg.exam_period_id) === periodId);
      return `<div class="d-flex flex-wrap align-items-center gap-2 border rounded px-2 py-1">
        <div>
          <div class="fw-semibold small">${esc(r.exam_period_title || `Period #${periodId}`)}</div>
          <div class="text-muted small">${isPublished ? `Published ${stamp(r.results_published_at)}` : `Status: ${esc(r.period_status || '—')}${r.entry_mode === 'results_only' ? ' · record-only (no timetable)' : ''}`}</div>
        </div>
        ${canRecord && !isPublished && pendings.length ? `<button class="btn btn-sm btn-outline-primary" data-submit-register="${periodId}"
          title="Submit every complete learning-area register in this period">Submit registers (${pendings.length})</button>` : ''}
        ${canPublish ? `<button class="btn btn-sm btn-outline-success" data-publish-period="${periodId}"><i class="bi bi-megaphone me-1"></i>Publish</button>` : ''}
        <button class="btn btn-sm btn-outline-secondary" data-generate-cards="${periodId}" title="Generate report cards for this period"><i class="bi bi-file-earmark-text me-1"></i>Report cards</button>
      </div>`;
    }).join('');
    $('vrSummativePeriodActions').innerHTML = actions || '<small class="text-muted">No exam period in this scope.</small>';
    $('vrSummativeHint').textContent = rows.length ? 'Record each learner mark, submit the registers, publish the period, then release report cards to parents.' : '';

    if (!rows.length) {
      const noTimetable = allPeriods.some((p) => !p.deleted_at && ['draft', 'published'].includes(String(p.period_status)));
      $('vrSummativeContainer').innerHTML = empty(
        allPeriods.length
          ? (noTimetable
            ? 'This exam period has no saved timetable yet, so no learner result rows exist. Open Exam Schedule to schedule its sittings, then the registers appear here for mark entry.'
            : 'No learner result rows match this scope. Record a mark on any register below to begin.')
          : 'No exam period exists in this scope yet. Create one on Exam Schedule, then record summative results here.',
        'bi-journal-x');
      renderPeriodActions();
      return;
    }
    $('vrSummativeContainer').innerHTML = `
      <table class="table table-sm table-hover align-middle" id="vrSummativeTable">
        <thead class="table-light"><tr>
          <th>Learner</th><th>Admission no.</th><th>Class / stream</th><th>Learning area</th>
          <th>Exam period</th><th class="text-center">Mark</th><th class="text-center">Out of</th>
          <th class="text-center">%</th><th>Grade</th><th>Entry</th><th>Register</th>
          ${canRecord ? '<th class="vr-no-print">Actions</th>' : ''}
        </tr></thead>
        <tbody>${rows.map((r) => {
          const b = r.grade ? String(r.grade).toUpperCase() : (r.percentage !== null && r.percentage !== undefined ? band(Number(r.percentage)) : '');
          return `<tr class="${r.result_deleted_at ? 'table-secondary' : ''}">
            <td class="fw-semibold">${esc(r.learner_name || '—')}</td>
            <td>${esc(r.admission_no || '—')}</td>
            <td>${esc(r.class_name || '—')}${r.stream_name ? ` · ${esc(r.stream_name)}` : ''}</td>
            <td>${esc(r.learning_area || '—')}</td>
            <td>${esc(r.exam_period_title || '—')}</td>
            <td class="text-center">${r.marks_obtained === null || r.marks_obtained === undefined ? '<span class="text-muted">Not entered</span>' : esc(r.marks_obtained)}</td>
            <td class="text-center">${esc(r.max_marks ?? '—')}</td>
            <td class="text-center">${r.percentage === null || r.percentage === undefined ? '—' : `${esc(r.percentage)}%`}</td>
            <td>${b ? `<span class="vr-grade ${bandClass(b)}">${esc(b)}</span>` : '—'}</td>
            <td>${esc(r.entry_status || 'Not entered')}</td>
            <td><span class="badge bg-light text-dark border">${esc(r.assessment_status || '—')}</span></td>
            ${canRecord ? `<td class="vr-no-print"><button class="btn btn-sm btn-outline-primary" data-result-edit="${Number(r.result_id || 0)}"
                data-assessment="${Number(r.assessment_id)}" data-enrollment="${Number(r.enrollment_id)}"
                data-marks="${esc(r.marks_obtained ?? '')}" data-status="${esc(r.entry_status || 'present')}"
                data-remarks="${esc(r.remarks || '')}" data-max="${esc(r.max_marks ?? 100)}"
                data-label="${esc(`${r.learner_name} · ${r.learning_area}`)}">${r.result_id && !r.result_deleted_at ? 'Edit' : 'Record'}</button></td>` : ''}
          </tr>`;
        }).join('')}
        </tbody>
      </table>`;
    renderPeriodActions();
    $('vrSummativeContainer').querySelectorAll('[data-result-edit]').forEach((button) => {
      button.onclick = () => openResult(button.dataset);
    });
  }
  function renderPeriodActions() {
    $('vrSummativeContainer').parentElement.parentElement.querySelectorAll('[data-submit-register]').forEach((b) => {
      b.onclick = async () => {
        if (!(await window.confirmAction?.('Submit result registers', 'Submit every complete learning-area register in this period? Registers with incomplete marks are refused and stay open.') ?? confirm('Submit every complete learning-area register in this period?'))) return;
        b.disabled = true;
        try {
          const periodId = b.dataset.submitRegister;
          const registers = [...new Set(state.rows.summative
            .filter((r) => Number(r.exam_period_id) === Number(periodId))
            .map((r) => Number(r.assessment_id)))]
            .filter(Boolean);
          const outcomes = [];
          for (const assessmentId of registers) {
            try {
              await payloadWithRetry(`/academic/exam-period-assessment/${assessmentId}/submit`, 'POST', { reason: 'Results workspace register submission' });
              outcomes.push({ ok: true });
            } catch (error) { outcomes.push({ ok: false, message: error.message }); }
          }
          const submitted = outcomes.filter((o) => o.ok).length;
          const refused = outcomes.length - submitted;
          if (submitted) notify(`${submitted} register(s) submitted${refused ? `; ${refused} still incomplete` : ''}.`, refused ? 'warning' : 'success');
          else notify(outcomes[0]?.message || 'No register could be submitted yet.', 'error');
          refresh();
        } catch (error) { notify(error.message || 'Unable to submit these registers.', 'error'); }
        finally { b.disabled = false; }
      };
    });
    $('vrSummativeContainer').parentElement.parentElement.querySelectorAll('[data-publish-period]').forEach((b) => {
      b.onclick = async () => {
        if (!(await window.confirmAction?.('Publish exam results', 'Publish these reviewed marks as official term results? Parents will be able to see them after report-card release.') ?? confirm('Publish these reviewed marks as official term results?'))) return;
        try {
          await payload(`/academic/exam-periods/${b.dataset.publishPeriod}/publish-results`, 'POST', {});
          notify('Official summative results published.', 'success');
          refresh();
        } catch (error) { notify(error.message || 'Unable to publish these results.', 'error'); }
      };
    });
    $('vrSummativeContainer').parentElement.parentElement.querySelectorAll('[data-generate-cards]').forEach((b) => {
      b.onclick = () => generateReportCards(b.dataset.generateCards);
    });
  }

  // ── Average ──────────────────────────────────────────────────────────
  function renderAverage(rows) {
    const scored = rows.filter((r) => r.overall_average !== null && r.overall_average !== undefined);
    const mean = (key) => {
      const values = scored.filter((r) => r[key] !== null && r[key] !== undefined).map((r) => Number(r[key]));
      return values.length ? (values.reduce((sum, value) => sum + value, 0) / values.length).toFixed(1) : '0.0';
    };
    renderKpis('vrAverageKpis', [
      { label: 'Learners', value: rows.length },
      { label: 'Pooled mean', value: `${mean('overall_average')}%` },
      { label: 'Formative mean', value: `${mean('formative_average')}%` },
      { label: 'Summative mean', value: `${mean('summative_average')}%` },
    ]);
    if (!rows.length) { $('vrAverageContainer').innerHTML = empty('No averaged results match this scope.', 'bi-bar-chart'); return; }
    $('vrAverageContainer').innerHTML = `
      <table class="table table-sm table-hover align-middle" id="vrAverageTable">
        <thead class="table-light"><tr>
          <th>Learner</th><th>Admission no.</th><th>Class / stream</th><th>Term</th>
          <th class="text-center">Learning areas</th><th class="text-center">Formative</th>
          <th class="text-center">Summative</th><th class="text-center">Overall</th><th>Grade</th>
        </tr></thead>
        <tbody>${rows.map((r) => {
          const b = r.grade_band || (r.overall_average != null ? band(Number(r.overall_average)) : '');
          return `<tr>
            <td class="fw-semibold">${esc(r.learner_name || '—')}</td>
            <td>${esc(r.admission_no || '—')}</td>
            <td>${esc(r.class_name || '—')}${r.stream_name ? ` · ${esc(r.stream_name)}` : ''}</td>
            <td>${esc(r.term_name || '—')}</td>
            <td class="text-center">${esc(r.subjects_count ?? '—')}</td>
            <td class="text-center">${r.formative_average == null ? '—' : `${esc(r.formative_average)}%`}</td>
            <td class="text-center">${r.summative_average == null ? '—' : `${esc(r.summative_average)}%`}</td>
            <td class="text-center fw-semibold">${r.overall_average == null ? '—' : `${esc(r.overall_average)}%`}</td>
            <td>${b ? `<span class="vr-grade ${bandClass(b)}">${esc(b)}</span>` : '—'}</td>
          </tr>`;
        }).join('')}
        </tbody>
      </table>`;
  }

  // ── Summative result modal ───────────────────────────────────────────
  let resultTarget = null;
  function openResult(data) {
    resultTarget = {
      resultId: Number(data.resultEdit || 0),
      assessmentId: Number(data.assessment),
      enrollmentId: Number(data.enrollment),
      maxMarks: Number(data.max) || 100,
    };
    $('vrResultModalTitle').textContent = resultTarget.resultId ? 'Edit result' : 'Record result';
    $('vrResultContext').textContent = `${data.label || 'Learner result'} — out of ${resultTarget.maxMarks}`;
    $('vrResultMarks').value = data.marks === '' ? '' : data.marks;
    $('vrResultMarks').max = String(resultTarget.maxMarks);
    $('vrResultStatus').value = data.status || 'present';
    $('vrResultRemarks').value = data.remarks || '';
    $('vrResultOutOf').textContent = `Out of ${resultTarget.maxMarks}`;
    bootstrap.Modal.getOrCreateInstance($('vrResultModal')).show();
  }
  async function saveResult(event) {
    event.preventDefault();
    if (!resultTarget) return;
    const marks = $('vrResultMarks').value.trim();
    const entry_status = $('vrResultStatus').value;
    const remarks = $('vrResultRemarks').value.trim();
    if (marks !== '' && (Number(marks) < 0 || Number(marks) > resultTarget.maxMarks)) {
      notify(`The mark must be between 0 and ${resultTarget.maxMarks}.`, 'error');
      return;
    }
    const button = $('vrResultSave'); button.disabled = true;
    try {
      if (resultTarget.resultId) {
        await payloadWithRetry(`/academic/exam-period-result/${resultTarget.resultId}`, 'PUT', { marks_obtained: marks, entry_status, remarks, reason: 'Results workspace correction' });
      } else {
        await payloadWithRetry('/academic/exam-period-result', 'POST', {
          assessment_id: resultTarget.assessmentId,
          student_academic_enrollment_id: resultTarget.enrollmentId,
          marks_obtained: marks,
          entry_status,
          remarks,
        });
      }
      bootstrap.Modal.getInstance($('vrResultModal'))?.hide();
      notify('Result saved.', 'success');
      refresh();
    } catch (error) { notify(error.message || 'Unable to save this result.', 'error'); }
    finally { button.disabled = false; }
  }

  // ── Formative mark grid ──────────────────────────────────────────────
  async function openMarks(assessmentId, maxMarks) {
    state.marks = { assessmentId, maxMarks, rows: [] };
    $('vrMarksModalTitle').textContent = 'Record formative marks';
    $('vrMarksBody').innerHTML = loading('Loading learners…');
    bootstrap.Modal.getOrCreateInstance($('vrMarksModal')).show();
    try {
      const rows = (await payload(`/academic/formative-assessment-marks?assessment_id=${assessmentId}`)) || [];
      state.marks.rows = Array.isArray(rows) ? rows : (rows.items || []);
      renderMarksGrid();
    } catch (error) {
      $('vrMarksBody').innerHTML = failed(error.message || 'Unable to load learners for this assessment.');
    }
  }
  function renderMarksGrid() {
    const { rows, maxMarks } = state.marks;
    if (!rows.length) { $('vrMarksBody').innerHTML = empty('No learners are enrolled for this assessment.', 'bi-people'); return; }
    $('vrMarksBody').innerHTML = `
      <p class="small text-muted mb-2">${rows.length} learner${rows.length === 1 ? '' : 's'} · score out of ${maxMarks}. Leave a score blank to skip that learner.</p>
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle" id="vrMarksTable">
          <thead class="table-light"><tr>
            <th>Learner</th><th>Admission no.</th><th class="text-center">Score</th>
            <th class="text-center">%</th><th class="text-center">Grade</th><th>Remarks</th>
          </tr></thead>
          <tbody>${rows.map((m) => `<tr data-student="${Number(m.student_id)}">
            <td class="fw-semibold">${esc(`${m.first_name || ''} ${m.last_name || ''}`.trim() || '—')}</td>
            <td>${esc(m.admission_no || '—')}</td>
            <td class="text-center" style="width:8rem">
              <input type="number" class="form-control form-control-sm text-center vr-mark-input"
                     data-student="${Number(m.student_id)}" value="${esc(m.score ?? '')}"
                     min="0" max="${maxMarks}" step="0.5" placeholder="/${maxMarks}">
            </td>
            <td class="text-center vr-pct">${m.percentage ?? '—'}</td>
            <td class="text-center vr-band">${esc(m.cbc_grade || '—')}</td>
            <td><input type="text" class="form-control form-control-sm vr-remark-input"
                   data-student="${Number(m.student_id)}" value="${esc(m.remarks || '')}" maxlength="255" placeholder="Optional"></td>
          </tr>`).join('')}
          </tbody>
        </table>
      </div>`;
    $('vrMarksBody').querySelectorAll('.vr-mark-input').forEach((input) => {
      input.oninput = () => {
        const row = input.closest('tr');
        const value = parseFloat(input.value);
        if (input.value === '' || Number.isNaN(value) || value < 0 || value > maxMarks) {
          input.classList.add('is-invalid');
          row.querySelector('.vr-pct').textContent = '—';
          row.querySelector('.vr-band').textContent = '—';
          return;
        }
        input.classList.remove('is-invalid');
        const pct = Math.round((value / maxMarks) * 10000) / 100;
        const b = band(pct);
        row.querySelector('.vr-pct').textContent = `${pct}%`;
        row.querySelector('.vr-band').innerHTML = `<span class="vr-grade ${bandClass(b)}">${b}</span>`;
      };
    });
  }
  async function saveMarks() {
    const { assessmentId, rows } = state.marks;
    const marks = [];
    let invalid = false;
    $('vrMarksBody').querySelectorAll('.vr-mark-input').forEach((input) => {
      if (input.classList.contains('is-invalid')) invalid = true;
      const score = input.value.trim();
      if (score === '') return;
      const row = rows.find((m) => Number(m.student_id) === Number(input.dataset.student));
      marks.push({
        student_id: Number(input.dataset.student),
        score: parseFloat(score),
        remarks: row ? (row.remarks || '') : '',
      });
    });
    if (invalid) { notify('Fix the highlighted scores before saving.', 'error'); return; }
    if (!marks.length) { notify('Enter at least one score before saving.', 'error'); return; }
    const button = $('vrMarksSave'); button.disabled = true;
    try {
      const remarksByStudent = {};
      $('vrMarksBody').querySelectorAll('.vr-remark-input').forEach((input) => { remarksByStudent[input.dataset.student] = input.value.trim(); });
      marks.forEach((m) => { if (remarksByStudent[m.student_id]) m.remarks = remarksByStudent[m.student_id]; });
      await payload('/academic/formative-assessment-marks', 'POST', { assessment_id: assessmentId, marks });
      notify(`${marks.length} formative mark${marks.length === 1 ? '' : 's'} saved.`, 'success');
      bootstrap.Modal.getInstance($('vrMarksModal'))?.hide();
      refresh();
    } catch (error) { notify(error.message || 'Unable to save these marks.', 'error'); }
    finally { button.disabled = false; }
  }

  // ── Report cards ─────────────────────────────────────────────────────
  async function generateReportCards(periodId) {
    const f = filters();
    const params = new URLSearchParams();
    if (f.term_id) params.set('term_id', f.term_id);
    if (f.class_id) params.set('class_id', f.class_id);
    if (window.viewResultsCtrl.canPublish()) params.set('exam_period_id', periodId);
    const confirmed = await window.confirmAction?.('Generate report cards', 'Generate report cards for this scope? They must be approved before parents receive them.')
      ?? confirm('Generate report cards for this scope?');
    if (!confirmed) return;
    notify('Generating report cards…', 'info');
    try {
      const result = await payload(`/academic/reports-generate-student-reports?${params}`, 'POST', {});
      const generated = Number(result?.generated_count || 0);
      const failedCount = Array.isArray(result?.failed) ? result.failed.length : 0;
      notify(failedCount ? `Generated ${generated}; ${failedCount} need attention.` : `Generated ${generated} report card(s) for approval.`, failedCount ? 'warning' : 'success');
      window.open(`${window.APP_BASE || ''}/pages/report_cards.php${f.term_id ? `?term_id=${f.term_id}` : ''}`, '_blank');
    } catch (error) { notify(error.message || 'Unable to generate report cards.', 'error'); }
  }

  // ── Export / print ───────────────────────────────────────────────────
  function activeTable() { return { formative: 'vrFormativeTable', summative: 'vrSummativeTable', average: 'vrAverageTable' }[state.tab]; }
  function exportCsv() {
    if (!window.viewResultsCtrl.canExport()) { notify('You are not permitted to export this view.', 'error'); return; }
    const table = $(activeTable());
    if (!table) { notify('There is no table to export yet.', 'error'); return; }
    const rows = [...table.querySelectorAll('tr')]
      .filter((row) => !row.querySelector('.vr-no-print'))
      .map((row) => [...row.cells].map((cell) => `"${cell.innerText.replaceAll('"', '""').trim()}"`).join(','));
    if (window.KingswayFileLifecycle?.exportText) {
      window.KingswayFileLifecycle.exportText(rows.join('\r\n'), `results-${state.tab}.csv`, 'text/csv');
    } else {
      notify('CSV export is unavailable on this page.', 'error');
    }
  }
  function printResults() {
    if (!window.viewResultsCtrl.canPrint()) { notify('You are not permitted to print this view.', 'error'); return; }
    document.body.classList.add('vr-printing');
    const cleanup = () => document.body.classList.remove('vr-printing');
    window.addEventListener('afterprint', cleanup, { once: true });
    window.print();
    setTimeout(cleanup, 1500);
  }

  // ── Wiring ───────────────────────────────────────────────────────────
  function attach() {
    if (!window.viewResultsCtrl.canExport()) $('vrCsv')?.classList.add('d-none');
    if (!window.viewResultsCtrl.canPrint()) $('vrPrint')?.classList.add('d-none');

    $('vrYearSelect')?.addEventListener('change', () => {
      const year = $('vrYearSelect').value;
      const termOptions = state.terms.filter((t) => String(t.year) === String(year));
      $('vrTermSelect').innerHTML = termOptions.map((t) => `<option value="${t.id}">${esc(t.name)}</option>`).join('');
      refresh();
    });
    $('vrTermSelect')?.addEventListener('change', refresh);
    $('vrClassSelect')?.addEventListener('change', refresh);
    $('vrSearchInput')?.addEventListener('input', () => {
      clearTimeout(state.searchTimer);
      state.searchTimer = setTimeout(refresh, 320);
    });
    $('vrTabs')?.addEventListener('shown.bs.tab', (event) => {
      const map = { '#vrPaneFormative': 'formative', '#vrPaneSummative': 'summative', '#vrPaneAverage': 'average', '#vrPanePortfolio': 'portfolio' };
      const target = String(event.target.getAttribute('data-bs-target') || '').trim();
      setTab(map[target] || 'formative');
    });
    $('vrResultForm')?.addEventListener('submit', saveResult);
    $('vrMarksSave')?.addEventListener('click', saveMarks);
    $('vrCsv')?.addEventListener('click', exportCsv);
    $('vrPrint')?.addEventListener('click', printResults);
    $('vrPortfolioManifestCsv')?.addEventListener('click', () => {
      if (!portfolioRows.length) return;
      const headers = Object.keys(portfolioRows[0]);
      const csv = [headers, ...portfolioRows.map((row) => headers.map((header) => `"${String(row[header] ?? '').replaceAll('"', '""')}"`).join(','))].join('\r\n');
      window.KingswayFileLifecycle?.exportText?.(csv, 'kne-cba-evidence-manifest.csv', 'text/csv');
    });
    $('vrPortfolioPrint')?.addEventListener('click', () => window.print());
  }

  window.viewResultsCtrl = {
    init: async () => {
      if (!$('vrTabContent')) return;
      if (!window.AuthContext) await window.AuthContext?.ready?.();
      else await window.AuthContext.ready?.();
      attach();
      await loadReferences();
      scopeLabel();
      await loadTab('formative');
    },
    canRecord, canPublish, canDistribute, canExport, canPrint,
    refresh,
    setTab,
    exportCSV: exportCsv,
    printResults,
    saveMarks,
    openMarks,
  };

  document.addEventListener('DOMContentLoaded', () => { window.viewResultsCtrl.init(); });
})();
