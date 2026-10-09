/* Results Studio — the school's governed results workspace.
 *
 * One shared scope (year / term / class / stream), four surfaces:
 *   Formative : learner x learning-area aggregate, plus an editable
 *               strand/sub-strand (assessment-grained) grid.
 *   Summative : wide learner x exam-period x learning-area matrix with
 *               totals, average and rank; cells save through the batched
 *               results endpoint with optimistic-concurrency tokens.
 *   Analytics : pooled formative + summative comparisons and charts.
 *   Portfolio : learner e-portfolio cards.
 *
 * All data arrives through window.API; no SQL and no raw fetch() here.
 * Excel-style wide matrices expose CSV + landscape print, per the UI rules.
 */
(() => {
  'use strict';

  const $ = (id) => document.getElementById(id);
  const esc = (v) => { const n = document.createElement('span'); n.textContent = String(v ?? ''); return n.innerHTML; };
  const escAttr = (v) => String(v ?? '')
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  const P = window.KingswayResultsPivot || {};
  const notify = (msg, type = 'info') => {
    try { window.API?.showNotification?.(msg, type) || window.showNotification?.(msg, type); } catch (_) { /* noop */ }
  };

  const payload = (path, method = 'GET', body = null) => {
    const r = window.API?.apiCall?.(path, method, body);
    return Promise.resolve(r).then((res) => (res && typeof res === 'object' && 'data' in res ? res.data : res));
  };
  const arr = (res) => {
    if (Array.isArray(res)) return res;
    if (res && Array.isArray(res.data)) return res.data;
    if (res && Array.isArray(res.items)) return res.items;
    if (res && Array.isArray(res.rows)) return res.rows;
    return [];
  };

  const canRecord = () => window.AuthContext?.hasAnyPermission?.(
    ['academic_manage', 'academic_edit', 'academics_manage', 'academics_edit', 'assessments_edit', 'results_manage']
  ) || ['System Administrator', 'School Administrator', 'Headteacher', 'Deputy Head - Academic']
    .some((r) => window.AuthContext?.hasRole?.(r));

  const state = {
    tab: 'formative',
    refs: {
      terms: [], classes: [], streams: [], areas: [], gradingSystems: [],
      gradingSystemId: '', gradingBands: [], examPeriods: [], classifications: [],
      classAreas: [], strands: [], subStrands: [],
    },
    filters: {
      year_id: '', term_id: '', class_id: '', stream_id: '', learning_area_id: '',
      strand_id: '', sub_strand_id: '', exam_period_id: '', assessment_type_classification_id: '', search: '',
    },
    selectedAreaId: '',
    data: { formative: null, areaDetail: null, summative: null, analytics: null, portfolio: null },
    charts: {},
    dirty: new Map(),          // summative: studentKey|colKey -> true
    saveTimers: new Map(),
    exportSpec: null,
    initialised: false,
  };

  // ── Formatting helpers ───────────────────────────────────────────────────
  const bandOf = (pct) => {
    const p = Number(pct);
    if (!Number.isFinite(p)) return '';
    return p >= 80 ? 'EE' : p >= 50 ? 'ME' : p >= 25 ? 'AE' : 'BE';
  };
  const gradeBadge = (pct) => {
    const b = bandOf(pct);
    if (!b) return '<span class="vr-cell-ro">—</span>';
    return `<span class="vr-grade vr-grade-${b}">${Number(pct).toFixed(1)}</span>`;
  };
  const bandPill = (b) => `<span class="vr-grade vr-grade-${b}">${esc(b)}</span>`;
  const num = (v, d = 1) => (v === null || v === undefined || v === '' ? '—' : Number(v).toFixed(d));
  const pctStr = (v) => (v === null || v === undefined || v === '' ? '—' : `${Number(v).toFixed(1)}%`);
  const initials = (name) => String(name || '?').trim().split(/\s+/).slice(0, 2).map((w) => w[0] || '').join('').toUpperCase();

  // ── Reference data ───────────────────────────────────────────────────────
  async function loadReferences() {
    const [terms, classes, periods, areas, systems, classifications] = await Promise.allSettled([
      payload('/academic/terms-list'),
      payload('/academic/classes-list?limit=200'),
      payload('/academic/exam-periods'),
      payload('/academic/learning-areas/list'),
      payload('/academic/grading-systems'),
      payload('/academic/assessment-classifications'),
    ]);
    state.refs.terms = terms.status === 'fulfilled' ? arr(terms.value) : [];
    state.refs.classes = classes.status === 'fulfilled' ? arr(classes.value) : [];
    state.refs.examPeriods = periods.status === 'fulfilled' ? arr(periods.value) : [];
    state.refs.classifications = classifications.status === 'fulfilled' ? arr(classifications.value) : [];
    state.refs.areas = areas.status === 'fulfilled' ? arr(areas.value).map((a) => ({
      id: Number(a.id), name: a.name, code: a.code || '',
    })) : [];
    const sysRes = systems.status === 'fulfilled' ? systems.value : null;
    state.refs.gradingSystems = arr(sysRes?.systems || sysRes);
  }

  function yearOptions() {
    const seen = new Map();
    state.refs.terms.forEach((t) => {
      const id = String(t.year);
      if (id && !seen.has(id)) seen.set(id, t.year_name || `Year ${id}`);
    });
    return [...seen.entries()].map(([id, name]) => ({ id, name }));
  }

  function termsForYear(yearId) {
    return state.refs.terms.filter((t) => !yearId || String(t.year) === String(yearId));
  }

  function classesForYear(yearId) {
    const rows = state.refs.classes.filter((c) => !yearId || String(c.academic_year_id) === String(yearId));
    return rows.length ? rows : state.refs.classes;
  }

  function populateBaseSelectors() {
    const ySel = $('vrYearSelect');
    const years = yearOptions();
    if (ySel) ySel.innerHTML = years.map((y) => `<option value="${escAttr(y.id)}">${esc(y.name)}</option>`).join('');
  }

  function populateTermSelect() {
    const ySel = $('vrYearSelect');
    const tSel = $('vrTermSelect');
    if (!tSel) return;
    const list = termsForYear(ySel?.value || '');
    tSel.innerHTML = '<option value="">All terms</option>' + list
      .map((t) => `<option value="${escAttr(t.id)}">${esc(t.name || t.term_name || `Term ${t.term_id}`)}</option>`).join('');
  }

  function populateClassSelect() {
    const cSel = $('vrClassSelect');
    if (!cSel) return;
    const list = classesForYear($('vrYearSelect')?.value || '');
    cSel.innerHTML = list
      .map((c) => `<option value="${escAttr(c.id)}">${esc(c.name)}</option>`).join('');
  }

  function populateCategorySelect() {
    const sel = $('vrCategorySelect');
    if (!sel) return;
    sel.innerHTML = '<option value="">All categories</option>' + state.refs.classifications
      .map((c) => `<option value="${escAttr(c.id)}">${esc(c.name || c.code)}</option>`).join('');
  }

  async function populateStreams() {
    const sel = $('vrStreamSelect');
    if (!sel) return;
    const classId = $('vrClassSelect')?.value || '';
    sel.innerHTML = '<option value="">All streams</option>';
    state.refs.streams = [];
    if (!classId) return;
    try {
      const r = await payload(`/academic/streams-list?class_id=${encodeURIComponent(classId)}`);
      state.refs.streams = r?.streams || arr(r);
      sel.innerHTML = '<option value="">All streams</option>' + state.refs.streams
        .map((s) => `<option value="${escAttr(s.stream_id || s.id)}">${esc(s.stream_name || s.name)}</option>`).join('');
    } catch (_) { /* leave single option */ }
  }

  async function loadClassAreas() {
    const classId = $('vrClassSelect')?.value || '';
    const termId = $('vrTermSelect')?.value || '';
    const yearId = $('vrYearSelect')?.value || '';
    state.refs.classAreas = [];
    if (!classId) return;
    try {
      const r = await payload(`/academic/results-management-class-areas?class_id=${encodeURIComponent(classId)}&term_id=${encodeURIComponent(termId)}&year_id=${encodeURIComponent(yearId)}`);
      state.refs.classAreas = arr(r?.areas);
    } catch (_) { state.refs.classAreas = []; }
  }

  function populateAreaSelect() {
    const sel = $('vrAreaSelect');
    if (!sel) return;
    const areas = state.refs.classAreas.length ? state.refs.classAreas : state.refs.areas;
    sel.innerHTML = '<option value="">All areas</option>' + areas
      .map((a) => `<option value="${escAttr(a.learning_area_id || a.id)}">${esc(a.name)}</option>`).join('');
    if (state.selectedAreaId) sel.value = String(state.selectedAreaId);
  }

  async function loadStrandsForArea(areaId) {
    state.refs.strands = [];
    state.refs.subStrands = [];
    if (!areaId) {
      $('vrStrandSelect').innerHTML = '<option value="">All strands</option>';
      $('vrSubStrandSelect').innerHTML = '<option value="">All sub-strands</option>';
      return;
    }
    try {
      const r = await payload(`/academic/results-management-class-areas?class_id=${encodeURIComponent($('vrClassSelect')?.value || '')}&term_id=${encodeURIComponent($('vrTermSelect')?.value || '')}&year_id=${encodeURIComponent($('vrYearSelect')?.value || '')}&learning_area_id=${encodeURIComponent(areaId)}`);
      state.refs.strands = arr(r?.strands);
    } catch (_) { state.refs.strands = []; }
    const sSel = $('vrStrandSelect');
    const seen = new Map();
    state.refs.strands.forEach((s) => { if (!seen.has(String(s.strand_id))) seen.set(String(s.strand_id), s.strand); });
    sSel.innerHTML = '<option value="">All strands</option>' + [...seen.entries()]
      .map(([id, name]) => `<option value="${escAttr(id)}">${esc(name)}</option>`).join('');
    onStrandChange();
  }

  function onStrandChange() {
    const strandId = $('vrStrandSelect')?.value || '';
    const ssSel = $('vrSubStrandSelect');
    const subs = state.refs.strands.filter((s) => !strandId || String(s.strand_id) === strandId);
    ssSel.innerHTML = '<option value="">All sub-strands</option>' + subs
      .map((s) => `<option value="${escAttr(s.sub_strand_id)}">${esc(s.sub_strand)}</option>`).join('');
  }

  function populateExamPeriodSelect() {
    const sel = $('vrExamPeriodSelect');
    if (!sel) return;
    const termId = $('vrTermSelect')?.value || '';
    const catId = $('vrCategorySelect')?.value || '';
    const list = state.refs.examPeriods.filter((p) => (
      (!termId || String(p.academic_year_term_id) === String(termId))
      && (!catId || String(p.assessment_type_classification_id) === String(catId))
    ));
    const previous = sel.value;
    sel.innerHTML = '<option value="">All periods</option>' + list
      .map((p) => `<option value="${escAttr(p.id)}">${esc(p.title || p.name || `Period ${p.id}`)}</option>`).join('');
    if (previous && [...sel.options].some((o) => o.value === previous)) sel.value = previous;
  }

  function renderGradingToggle() {
    const mount = $('vrScaleToggle');
    if (!mount) return;
    const systems = state.refs.gradingSystems;
    if (!systems.length) { mount.innerHTML = ''; return; }
    if (!state.refs.gradingSystemId) {
      const saved = localStorage.getItem('vr:gradingSystemId');
      const def = systems.find((s) => String(s.id) === saved) || systems[0];
      state.refs.gradingSystemId = String(def.id);
      state.refs.gradingBands = def.bands || [];
    }
    mount.innerHTML = systems.map((s) => `<button type="button" class="${String(s.id) === state.refs.gradingSystemId ? 'is-active' : ''}" data-scale="${escAttr(s.id)}" title="${escAttr(s.description || s.name)}">${esc(s.code || s.name)}</button>`).join('');
    mount.querySelectorAll('[data-scale]').forEach((btn) => {
      btn.onclick = () => {
        state.refs.gradingSystemId = btn.dataset.scale;
        const sys = systems.find((s) => String(s.id) === state.refs.gradingSystemId);
        state.refs.gradingBands = sys?.bands || [];
        localStorage.setItem('vr:gradingSystemId', state.refs.gradingSystemId);
        renderGradingToggle();
      };
    });
  }

  // ── Defaults ─────────────────────────────────────────────────────────────
  function applyDefaults() {
    const ctxYear = String(window.AcademicContext?.getAcademicYearId?.() || '');
    const ctxTerm = String(window.AcademicContext?.getTermId?.() || '');
    const ySel = $('vrYearSelect');

    if (ySel && ctxYear && [...ySel.options].some((o) => o.value === ctxYear)) ySel.value = ctxYear;
    populateTermSelect();
    const tSel = $('vrTermSelect');
    if (tSel) {
      const terms = termsForYear(ySel?.value || '');
      const active = terms.find((t) => String(t.status) === 'active');
      if (ctxTerm && [...tSel.options].some((o) => o.value === ctxTerm)) tSel.value = ctxTerm;
      else if (active) tSel.value = String(active.id);
      else if (terms[0]) tSel.value = String(terms[0].id);
    }
    populateClassSelect();
    const cSel = $('vrClassSelect');
    if (cSel) {
      const grade9 = [...cSel.options].find((o) => o.textContent.trim().startsWith('Grade 9'));
      if (grade9) cSel.value = grade9.value;
    }
    populateCategorySelect();
  }

  // ── Filter collection ────────────────────────────────────────────────────
  function collectFilters() {
    state.filters = {
      year_id: $('vrYearSelect')?.value || '',
      term_id: $('vrTermSelect')?.value || '',
      class_id: $('vrClassSelect')?.value || '',
      stream_id: $('vrStreamSelect')?.value || '',
      learning_area_id: $('vrAreaSelect')?.value || '',
      strand_id: $('vrStrandSelect')?.value || '',
      sub_strand_id: $('vrSubStrandSelect')?.value || '',
      exam_period_id: $('vrExamPeriodSelect')?.value || '',
      assessment_type_classification_id: $('vrCategorySelect')?.value || '',
      search: ($('vrSearchInput')?.value || '').trim(),
    };
    return state.filters;
  }

  function qs(extra = {}) {
    const f = { ...state.filters, ...extra };
    const p = new URLSearchParams();
    Object.entries(f).forEach(([k, v]) => { if (v !== '' && v !== null && v !== undefined) p.set(k, v); });
    const s = p.toString();
    return s ? `?${s}` : '';
  }

  function renderContext() {
    const mount = $('vrHeroContext');
    if (!mount) return;
    const yName = state.refs.terms.find((t) => String(t.year) === state.filters.year_id)?.year_name || '—';
    const tName = state.refs.terms.find((t) => String(t.id) === state.filters.term_id)?.name || 'All terms';
    const cName = state.refs.classes.find((c) => String(c.id) === state.filters.class_id)?.name || 'All classes';
    mount.innerHTML = [
      { l: 'Year', v: yName }, { l: 'Term', v: tName }, { l: 'Class', v: cName },
    ].map((c) => `<div class="vr-ctx-pill"><b>${esc(c.v)}</b><span>${esc(c.l)}</span></div>`).join('');
  }

  function renderFilterChips() {
    const mount = $('vrFilterChips');
    if (!mount) return;
    const chips = [];
    const push = (key, label) => chips.push({ key, label });
    if (state.filters.stream_id) {
      const s = state.refs.streams.find((x) => String(x.stream_id || x.id) === state.filters.stream_id);
      if (s) push('stream_id', s.stream_name || s.name);
    }
    if (state.filters.learning_area_id) {
      const all = state.refs.classAreas.length ? state.refs.classAreas : state.refs.areas;
      const a = all.find((x) => String(x.learning_area_id || x.id) === state.filters.learning_area_id);
      if (a) push('learning_area_id', a.name);
    }
    if (state.filters.exam_period_id) {
      const p = state.refs.examPeriods.find((x) => String(x.id) === state.filters.exam_period_id);
      if (p) push('exam_period_id', p.title || p.name);
    }
    if (state.filters.assessment_type_classification_id) {
      const c = state.refs.classifications.find((x) => String(x.id) === state.filters.assessment_type_classification_id);
      push('assessment_type_classification_id', c?.name || 'Category');
    }
    if (state.filters.search) push('search', `"${state.filters.search}"`);
    mount.innerHTML = chips.length
      ? chips.map((c) => `<span class="vr-chip">${esc(c.label)}<button type="button" data-clear="${escAttr(c.key)}" aria-label="Clear">&times;</button></span>`).join('')
      : '<span class="vr-freshness">All learners in scope</span>';
    mount.querySelectorAll('[data-clear]').forEach((b) => {
      b.onclick = () => {
        const key = b.dataset.clear;
        const map = { stream_id: 'vrStreamSelect', learning_area_id: 'vrAreaSelect', exam_period_id: 'vrExamPeriodSelect', assessment_type_classification_id: 'vrCategorySelect', search: 'vrSearchInput' };
        const el = $(map[key]);
        if (el) el.value = '';
        if (key === 'learning_area_id') { state.selectedAreaId = ''; onAreaChange(); }
        refresh();
      };
    });
  }

  function renderFreshness() {
    const el = $('vrFreshness');
    if (el) el.textContent = `Updated ${new Date().toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })}`;
  }

  // ── KPI + chrome ─────────────────────────────────────────────────────────
  function renderKpis(cards) {
    const mount = $('vrKpis');
    if (!mount) return;
    mount.innerHTML = cards.map((c) => `
      <div class="vr-kpi"${c.accent ? ` style="--vr-kpi-accent:${escAttr(c.accent)}"` : ''}>
        <div class="vr-kpi-val">${esc(c.value)}${c.unit ? `<small>${esc(c.unit)}</small>` : ''}</div>
        <div class="vr-kpi-lab">${esc(c.label)}</div>
        ${c.note ? `<div class="vr-kpi-note">${esc(c.note)}</div>` : ''}
      </div>`).join('');
  }

  const loading = (label = 'Loading') => `<div class="vr-loading"><div class="vr-spinner"></div><span>${esc(label)}…</span></div>`;
  const emptyState = (icon, title, hint) => `<div class="vr-empty"><i class="bi bi-${esc(icon)}"></i><b>${esc(title)}</b><span>${esc(hint || '')}</span></div>`;
  const alertBox = (msg) => `<div class="alert alert-danger vr-alert">${esc(msg)}</div>`;

  function panel(title, sub, tools, body) {
    return `<section class="vr-panel">
      <div class="vr-panel-head">
        <h3>${esc(title)}</h3>
        ${sub ? `<span class="vr-panel-sub">${esc(sub)}</span>` : ''}
        <div class="vr-panel-tools">${tools || ''}</div>
      </div>
      <div class="vr-panel-body">${body}</div>
    </section>`;
  }

  const toolBtn = (label, icon, cls, attrs = '') => `<button type="button" class="vr-btn ${cls}" ${attrs}><i class="bi bi-${icon}"></i><span>${esc(label)}</span></button>`;

  // ── CSV / print ──────────────────────────────────────────────────────────
  function csvCell(v) {
    let s = v === null || v === undefined ? '' : String(v);
    if (/^[=+\-@]/.test(s)) s = `'${s}`;
    return /[",\n]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s;
  }
  function downloadCsv(filename, headers, rows) {
    const csv = [headers.map(csvCell).join(','), ...rows.map((r) => r.map(csvCell).join(','))].join('\n');
    const text = `\ufeff${csv}`;
    try {
      if (window.KingswayFileLifecycle?.exportText) { window.KingswayFileLifecycle.exportText(text, filename, 'text/csv'); return; }
    } catch (_) { /* fall through */ }
    const blob = new Blob([text], { type: 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = filename; document.body.appendChild(a); a.click();
    a.remove(); URL.revokeObjectURL(url);
  }
  function setExport(filename, headers, rows) { state.exportSpec = { filename, headers, rows: () => rows }; }

  // ── Tab routing ──────────────────────────────────────────────────────────
  function applyTabFieldVisibility() {
    const tab = state.tab;
    document.querySelectorAll('.vr-deck-grid .vr-field[data-tab]').forEach((f) => {
      const show = f.dataset.tab === tab || tab === 'analytics';
      f.style.display = show ? '' : 'none';
    });
  }

  function setTab(tab) {
    state.tab = tab;
    document.querySelectorAll('.vr-tab').forEach((b) => b.classList.toggle('is-active', b.dataset.tab === tab));
    applyTabFieldVisibility();
    refresh();
  }

  async function refresh() {
    collectFilters();
    renderContext();
    renderFilterChips();
    state.dirty.clear();
    state.saveTimers.forEach((t) => clearTimeout(t));
    state.saveTimers.clear();
    try {
      if (state.tab === 'formative') await renderFormative();
      else if (state.tab === 'summative') await renderSummative();
      else if (state.tab === 'analytics') await renderAnalytics();
      else await renderPortfolio();
      renderFreshness();
    } catch (e) {
      $('vrView').innerHTML = alertBox(e?.message || 'Unable to load this view.');
    }
  }

  // ── FORMATIVE ────────────────────────────────────────────────────────────
  async function renderFormative() {
    $('vrView').innerHTML = loading('Loading formative results');
    await loadClassAreas();
    populateAreaSelect();

    const [matrixRes] = await Promise.allSettled([
      payload(`/academic/results-management-formative-matrix${qs()}`),
    ]);
    const mx = matrixRes.status === 'fulfilled' ? matrixRes.value : null;
    const learners = arr(mx?.learners);
    const areaMap = mx?.areas || {};

    // Column set = the class learning areas, so an un-assessed area still shows.
    const columns = state.refs.classAreas.length
      ? state.refs.classAreas.map((a) => ({ id: String(a.learning_area_id), name: a.name }))
      : Object.entries(areaMap).map(([id, name]) => ({ id, name }));

    const assessed = learners.filter((l) => Object.keys(l.areas || {}).length).length;
    const selectedAreaName = (() => {
      const all = state.refs.classAreas.length ? state.refs.classAreas : state.refs.areas;
      const a = all.find((x) => String(x.learning_area_id || x.id) === String(state.selectedAreaId));
      return a ? a.name : 'All areas';
    })();
    renderKpis([
      { value: learners.length, label: 'Learners', accent: 'var(--vr-moss)' },
      { value: columns.length, label: 'Learning areas', accent: 'var(--vr-forest)' },
      { value: assessed, label: 'With evidence', note: `${learners.length - assessed} without`, accent: 'var(--vr-gold)' },
      { value: selectedAreaName, label: 'Detail area', accent: 'var(--vr-blue)' },
    ]);

    if (!learners.length) {
      $('vrView').innerHTML = emptyState('clipboard2-x', 'No formative results in this scope',
        'Record marks on the Formative Assessments page, then return here.');
      await renderFormativeTable2();
      return;
    }

    const headers = ['Admission no.', 'Learner', ...columns.map((c) => c.name)];
    const rows = learners.map((l) => [
      l.admission_no,
      l.learner_name,
      ...columns.map((c) => {
        const cell = l.areas?.[c.id];
        return cell ? `${num(cell.avg_pct)} (${cell.level})` : '';
      }),
    ]);
    setExport(`formative-matrix-${Date.now()}.csv`, headers, rows);

    const head = `<tr>
      <th class="vr-sticky vr-sticky-2">Admission no.</th>
      <th class="vr-sticky vr-sticky-1">Learner</th>
      ${columns.map((c) => `<th class="vr-num" title="${escAttr(c.name)}"><a href="#" class="vr-area-drill" data-area="${escAttr(c.id)}">${esc(c.name)}</a></th>`).join('')}
      <th class="vr-num">Overall</th>
      <th class="vr-num vr-no-print">Actions</th>
    </tr>`;
    const body = learners.map((l) => {
      const cells = Object.values(l.areas || {});
      const overall = cells.length ? cells.reduce((s, c) => s + Number(c.avg_pct || 0), 0) / cells.length : null;
      return `<tr>
        <td class="vr-sticky vr-sticky-2">${esc(l.admission_no)}</td>
        <th class="vr-sticky vr-sticky-1"><a href="#" class="vr-learner-link" data-learner="${escAttr(l.student_id)}" data-name="${escAttr(l.learner_name)}" data-adm="${escAttr(l.admission_no)}">${esc(l.learner_name)}</a></th>
        ${columns.map((c) => {
          const cell = l.areas?.[c.id];
          return `<td class="vr-num">${cell ? gradeBadge(cell.avg_pct) : '<span class="vr-cell-ro">—</span>'}</td>`;
        }).join('')}
        <td class="vr-num">${overall === null ? '<span class="vr-cell-ro">—</span>' : gradeBadge(overall)}</td>
        <td class="vr-num vr-no-print">${rowKebab(l)}</td>
      </tr>`;
    }).join('');

    const table = `<div class="vr-scroll"><table class="vr-matrix"><thead>${head}</thead><tbody>${body}</tbody></table></div>`;
    $('vrView').innerHTML = panel('Formative mastery',
      `${learners.length} learners · ${columns.length} areas`, '', table);

    bindKebabs();
    $('vrView').querySelectorAll('.vr-area-drill').forEach((a) => {
      a.onclick = (e) => {
        e.preventDefault();
        state.selectedAreaId = a.dataset.area;
        $('vrAreaSelect').value = a.dataset.area;
        onAreaChange(true);
      };
    });
    await renderFormativeTable2();
  }

  function rowKebab(l) {
    return `<span class="vr-kebab">
      <button type="button" class="vr-kebab-btn" aria-haspopup="true" aria-expanded="false"><i class="bi bi-three-dots"></i></button>
      <span class="vr-kebab-menu">
        <button type="button" data-act="breakdown" data-learner="${escAttr(l.student_id)}"><i class="bi bi-diagram-3"></i>Sub-strand breakdown</button>
        <button type="button" data-act="profile" data-learner="${escAttr(l.student_id)}" data-name="${escAttr(l.learner_name)}" data-adm="${escAttr(l.admission_no)}"><i class="bi bi-person-lines-fill"></i>Learner profile</button>
      </span>
    </span>`;
  }

  function bindKebabs() {
    document.querySelectorAll('.vr-kebab').forEach((k) => {
      const btn = k.querySelector('.vr-kebab-btn');
      const menu = k.querySelector('.vr-kebab-menu');
      btn.onclick = (e) => {
        e.stopPropagation();
        document.querySelectorAll('.vr-kebab-menu.is-open').forEach((m) => { if (m !== menu) m.classList.remove('is-open'); });
        menu.classList.toggle('is-open');
        btn.setAttribute('aria-expanded', menu.classList.contains('is-open') ? 'true' : 'false');
      };
      menu.querySelectorAll('button').forEach((b) => {
        b.onclick = () => {
          menu.classList.remove('is-open');
          if (b.dataset.act === 'profile') openDrawer(b.dataset.learner, b.dataset.name, b.dataset.adm);
          else if (b.dataset.act === 'breakdown') {
            if (state.selectedAreaId) renderFormativeTable2();
            document.querySelector('#vrView .vr-panel:last-child')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
          }
        };
      });
    });
    if (!state.kebabDocBound) {
      state.kebabDocBound = true;
      document.addEventListener('click', () => document.querySelectorAll('.vr-kebab-menu.is-open').forEach((m) => m.classList.remove('is-open')));
    }
  }

  async function onAreaChange(reload = true) {
    state.selectedAreaId = $('vrAreaSelect')?.value || '';
    await loadStrandsForArea(state.selectedAreaId);
    if (reload) await renderFormativeTable2();
  }

  async function renderFormativeTable2() {
    const areaId = state.selectedAreaId || $('vrAreaSelect')?.value || '';
    // Remove any previous detail panel.
    document.querySelectorAll('#vrView .vr-formative-detail').forEach((n) => n.remove());
    if (!areaId) {
      $('vrView').insertAdjacentHTML('beforeend', panel('Sub-strand & assessment detail',
        'Select a learning area to open the editable grid', '',
        emptyState('grid-3x3', 'No learning area selected', 'Pick an area above, or click an area column header.')));
      return;
    }

    const mount = document.createElement('div');
    mount.className = 'vr-formative-detail';
    mount.innerHTML = loading('Loading area detail');
    $('vrView').appendChild(mount);

    let detail;
    try {
      detail = await payload(`/academic/results-management-area-detail${qs({ learning_area_id: areaId })}`);
    } catch (e) {
      mount.innerHTML = alertBox(e?.message || 'Unable to load this learning area.');
      return;
    }
    const assessments = arr(detail?.assessments);
    const learners = arr(detail?.learners);
    const marks = arr(detail?.marks);
    const markIndex = new Map();
    marks.forEach((m) => markIndex.set(`${m.assessment_id}|${m.student_id}`, m));

    if (!assessments.length) {
      mount.innerHTML = panel('Sub-strand & assessment detail', 'No assessments recorded',
        '', emptyState('journal-x', 'No formative assessments for this area',
        'Create a formative assessment for this class and area to record marks here.'));
      return;
    }

    const editable = canRecord();
    // Group assessment columns by strand for a grouped header.
    const groups = [];
    const seen = new Map();
    assessments.forEach((a) => {
      const gkey = String(a.strand_id || '0') + '|' + String(a.sub_strand_id || '0');
      if (!seen.has(gkey)) {
        seen.set(gkey, { strand: a.strand || 'Unstranded', sub: a.sub_strand || '', cols: [] });
        groups.push(seen.get(gkey));
      }
      seen.get(gkey).cols.push(a);
    });

    const multiStream = new Set(assessments.map((a) => a.stream_name || '')).size > 1;

    const headerTop = `<tr>
      <th class="vr-sticky vr-sticky-2" rowspan="2">Admission no.</th>
      <th class="vr-sticky vr-sticky-1" rowspan="2">Learner</th>
      ${groups.map((g) => `<th class="vr-group vr-num" colspan="${g.cols.length}" title="${escAttr(g.sub || g.strand)}">${esc(g.strand)}${g.sub ? ` · ${esc(g.sub)}` : ''}</th>`).join('')}
    </tr>`;
    const headerSub = `<tr class="vr-sub">
      ${groups.map((g) => g.cols.map((a) => `
        <th class="vr-num" title="${escAttr(`${a.title} · out of ${a.max_marks}${multiStream && a.stream_name ? ` · ${a.stream_name}` : ''}`)}">
          ${esc(a.title)}<br><small class="vr-cell-ro">out of ${esc(a.max_marks)}</small>
        </th>`).join('')).join('')}
    </tr>`;

    const body = learners.map((l) => {
      const cells = assessments.map((a) => {
        const m = markIndex.get(`${a.assessment_id}|${l.student_id}`);
        const value = m && m.marks_obtained !== null && m.marks_obtained !== undefined ? Number(m.marks_obtained) : '';
        const locked = !editable || a.status === 'approved';
        if (locked) return `<td class="vr-num vr-cell-ro">${value === '' ? '—' : esc(value)}</td>`;
        return `<td class="vr-num"><input class="vr-cell-input" type="number" step="0.5" min="0" max="${escAttr(a.max_marks)}"
          value="${escAttr(value)}" data-assessment="${escAttr(a.assessment_id)}" data-student="${escAttr(l.student_id)}"
          data-max="${escAttr(a.max_marks)}" aria-label="${escAttr(l.learner_name + ' ' + a.title)}"></td>`;
      }).join('');
      return `<tr>
        <td class="vr-sticky vr-sticky-2">${esc(l.admission_no)}</td>
        <th class="vr-sticky vr-sticky-1">${esc(l.learner_name)}</th>
        ${cells}
      </tr>`;
    }).join('');

    const headers = ['Admission no.', 'Learner', ...assessments.map((a) => `${a.title} (/${a.max_marks})`)];
    const csvRows = learners.map((l) => [l.admission_no, l.learner_name, ...assessments.map((a) => {
      const m = markIndex.get(`${a.assessment_id}|${l.student_id}`);
      return m && m.marks_obtained !== null && m.marks_obtained !== undefined ? m.marks_obtained : '';
    })]);
    setExport(`formative-detail-${areaId}-${Date.now()}.csv`, headers, csvRows);

    mount.innerHTML = panel(
      'Sub-strand & assessment detail',
      `${assessments.length} assessments · ${learners.length} learners · ${editable ? 'editable' : 'read-only'}`,
      toolBtn('CSV', 'filetype-csv', 'vr-no-print', 'data-export="detail"') + toolBtn('Print', 'printer', 'vr-no-print', 'data-print="1"'),
      `<div class="vr-scroll"><table class="vr-matrix"><thead>${headerTop}${headerSub}</thead><tbody>${body}</tbody></table></div>`
    );
    bindDetailEditing(mount);
    mount.querySelector('[data-export="detail"]').onclick = () => state.exportSpec && downloadCsv(state.exportSpec.filename, state.exportSpec.headers, state.exportSpec.rows());
    mount.querySelector('[data-print="1"]').onclick = () => window.print();
  }

  function bindDetailEditing(scope) {
    const dirtyByAssessment = new Map();
    scope.querySelectorAll('.vr-cell-input').forEach((input) => {
      input.addEventListener('change', () => {
        const max = Number(input.dataset.max || 100);
        const raw = input.value.trim();
        if (raw === '') { input.classList.remove('is-error'); scheduleDetailSave(input.dataset.assessment, dirtyByAssessment, scope); return; }
        const v = Number(raw);
        if (!Number.isFinite(v) || v < 0 || v > max) {
          input.classList.add('is-error');
          notify(`Mark must be between 0 and ${max}.`, 'error');
          return;
        }
        input.classList.remove('is-error');
        input.classList.add('is-saving');
        if (!dirtyByAssessment.has(input.dataset.assessment)) dirtyByAssessment.set(input.dataset.assessment, new Set());
        dirtyByAssessment.get(input.dataset.assessment).add(input);
        scheduleDetailSave(input.dataset.assessment, dirtyByAssessment, scope);
      });
    });
  }

  function scheduleDetailSave(assessmentId, dirtyByAssessment, scope) {
    const key = `detail:${assessmentId}`;
    if (state.saveTimers.has(key)) clearTimeout(state.saveTimers.get(key));
    state.saveTimers.set(key, setTimeout(() => flushDetailSave(assessmentId, dirtyByAssessment, scope), 700));
  }

  async function flushDetailSave(assessmentId, dirtyByAssessment, scope) {
    const inputs = [...(dirtyByAssessment.get(assessmentId) || [])];
    if (!inputs.length) return;
    const marks = inputs.map((i) => ({
      student_id: Number(i.dataset.student),
      marks_obtained: i.value.trim() === '' ? null : Number(i.value),
    }));
    try {
      await payload('/academic/formative-assessment-marks', 'POST', { assessment_id: Number(assessmentId), marks });
      inputs.forEach((i) => { i.classList.remove('is-saving'); i.classList.add('is-saved'); setTimeout(() => i.classList.remove('is-saved'), 900); });
      dirtyByAssessment.delete(assessmentId);
    } catch (e) {
      inputs.forEach((i) => { i.classList.remove('is-saving'); i.classList.add('is-error'); });
      notify(e?.message || 'Could not save marks.', 'error');
    }
  }

  // ── SUMMATIVE ────────────────────────────────────────────────────────────
  async function renderSummative() {
    $('vrView').innerHTML = loading('Loading summative results');
    populateExamPeriodSelect();
    const res = await payload(`/academic/results-management-summative${qs()}`);
    const rows = arr(res?.items || res);
    state.data.summative = rows;

    const pivot = P.pivotStudentRows ? P.pivotStudentRows(rows) : { columns: [], students: [] };
    const recorded = rows.filter((r) => r.result_id && !r.result_deleted_at).length;
    const periods = new Set(rows.map((r) => r.exam_period_id));

    renderKpis([
      { value: pivot.students.length, label: 'Learners', accent: 'var(--vr-moss)' },
      { value: pivot.columns.length, label: 'Exam × area', accent: 'var(--vr-forest)' },
      { value: recorded, label: 'Marks recorded', note: `${rows.length - recorded} awaiting`, accent: 'var(--vr-gold)' },
      { value: periods.size, label: 'Exam periods', accent: 'var(--vr-blue)' },
    ]);

    if (!pivot.students.length) {
      $('vrView').innerHTML = emptyState('journal-x', 'No summative registers in this scope',
        'Exam registers appear once an exam period has a timetable and marks are recorded.');
      return;
    }

    const editable = canRecord();
    const columns = pivot.columns;
    // Group columns by exam period for the grouped header row.
    const periodGroups = [];
    const pSeen = new Map();
    columns.forEach((c) => {
      if (!pSeen.has(c.exam_period_id)) { pSeen.set(c.exam_period_id, { title: c.period_title || `Period ${c.exam_period_id}`, cols: [] }); periodGroups.push(pSeen.get(c.exam_period_id)); }
      pSeen.get(c.exam_period_id).cols.push(c);
    });

    const analytics = computeSummaries(pivot.students, columns);

    const headerTop = `<tr>
      <th class="vr-sticky vr-sticky-2" rowspan="2">Admission no.</th>
      <th class="vr-sticky vr-sticky-1" rowspan="2">Learner</th>
      ${periodGroups.map((g) => `<th class="vr-group vr-num" colspan="${g.cols.length}">${esc(g.title)}</th>`).join('')}
      <th class="vr-group vr-num" colspan="3">Aggregate</th>
    </tr>`;
    const headerSub = `<tr class="vr-sub">
      ${columns.map((c) => `<th class="vr-num" title="${escAttr(`${c.subject} · ${c.period_title} · out of ${c.max_marks}`)}">${esc(shortArea(c.subject))}</th>`).join('')}
      <th class="vr-num">Total</th><th class="vr-num">Avg %</th><th class="vr-num">Rank</th>
    </tr>`;

    const body = pivot.students.map((s) => {
      const ag = analytics.get(s.key) || {};
      const cells = columns.map((c) => {
        const cell = s.cells[c.key];
        if (!cell) return '<td class="vr-num vr-cell-ro">—</td>';
        const has = cell.marks_obtained !== null && cell.marks_obtained !== undefined;
        const locked = !editable || cell.published || cell.deleted || !cell.assessment_id;
        if (locked) {
          return `<td class="vr-num ${cell.deleted ? 'vr-cell-ro' : ''}">${has ? esc(cell.marks_obtained) : '<span class="vr-cell-ro">—</span>'}</td>`;
        }
        return `<td class="vr-num"><input class="vr-cell-input" type="number" step="0.5" min="0" max="${escAttr(cell.max_marks)}"
          value="${escAttr(has ? cell.marks_obtained : '')}" data-student-key="${escAttr(s.key)}" data-col="${escAttr(c.key)}"
          data-assessment="${escAttr(cell.assessment_id)}" data-enrollment="${escAttr(s.enrollment_id)}"
          data-result="${escAttr(cell.result_id)}" data-max="${escAttr(cell.max_marks)}"
          data-updated="${escAttr(cell.updated_at)}" aria-label="${escAttr(s.learner_name + ' ' + c.subject)}"></td>`;
      }).join('');
      return `<tr data-student-key="${escAttr(s.key)}">
        <td class="vr-sticky vr-sticky-2">${esc(s.admission_no)}</td>
        <th class="vr-sticky vr-sticky-1">${esc(s.learner_name)}<div class="vr-cell-ro" style="font-size:.66rem">${esc(s.class_name)}${s.stream_name ? ` · ${esc(s.stream_name)}` : ''}</div></th>
        ${cells}
        <td class="vr-num vr-total">${ag.total === null || ag.total === undefined ? '—' : esc(ag.total)}</td>
        <td class="vr-num vr-total">${pctStr(ag.average)}</td>
        <td class="vr-num"><span class="vr-rank ${ag.rank && ag.rank <= 3 ? 'is-top' : ''}">${ag.rank || '—'}</span></td>
      </tr>`;
    }).join('');

    const headers = ['Admission no.', 'Learner', 'Class', ...columns.map((c) => `${c.subject} (${c.period_title})`), 'Total', 'Average %', 'Rank'];
    const csvRows = pivot.students.map((s) => {
      const ag = analytics.get(s.key) || {};
      return [s.admission_no, s.learner_name, `${s.class_name}${s.stream_name ? ' ' + s.stream_name : ''}`,
        ...columns.map((c) => {
          const cell = s.cells[c.key];
          return cell && cell.marks_obtained !== null && cell.marks_obtained !== undefined ? cell.marks_obtained : '';
        }), ag.total ?? '', ag.average ?? '', ag.rank ?? ''];
    });
    setExport(`summative-matrix-${Date.now()}.csv`, headers, csvRows);

    $('vrView').innerHTML = panel('Summative matrix',
      `${pivot.students.length} learners · ${columns.length} exam-area columns${editable ? ' · editable' : ' · read-only'}`,
      toolBtn('CSV', 'filetype-csv', 'vr-no-print', 'data-export="summative"') + toolBtn('Print', 'printer', 'vr-no-print', 'data-print="1"'),
      `<div class="vr-scroll"><table class="vr-matrix" id="vrSummativeMatrix"><thead>${headerTop}${headerSub}</thead><tbody>${body}</tbody></table></div>`);
    $('vrView').querySelector('[data-export="summative"]').onclick = () => state.exportSpec && downloadCsv(state.exportSpec.filename, state.exportSpec.headers, state.exportSpec.rows());
    $('vrView').querySelector('[data-print="1"]').onclick = () => window.print();
    bindSummativeEditing(pivot.students, columns);
  }

  function shortArea(name) {
    const map = {
      'Mathematics': 'Maths', 'English': 'English', 'Kiswahili': 'Kiswahili',
      'Environmental Activities': 'Env. Act.', 'Religious Education': 'R.E.',
      'Creative Activities': 'Creative', 'Language Activities': 'Lang. Act.',
      'Science and Technology': 'Sci & Tech', 'Social Studies': 'Social St.',
      'Agriculture and Nutrition': 'Agri & Nut.', 'Agriculture': 'Agriculture',
      'Integrated Science': 'Int. Science', 'Pre-Technical Studies': 'Pre-Tech',
      'Christian Religious Education': 'C.R.E.', 'Islamic Religious Education': 'I.R.E.',
    };
    return map[name] || (String(name).length > 14 ? `${String(name).slice(0, 12)}…` : name);
  }

  function computeSummaries(students, columns) {
    const out = new Map();
    students.forEach((s) => {
      let total = 0; let hasTotal = false;
      const pcts = [];
      columns.forEach((c) => {
        const cell = s.cells[c.key];
        if (!cell || cell.deleted) return;
        const has = cell.marks_obtained !== null && cell.marks_obtained !== undefined;
        if (!has) return;
        total += Number(cell.marks_obtained);
        hasTotal = true;
        const pct = cell.percentage !== null && cell.percentage !== undefined
          ? Number(cell.percentage)
          : (cell.max_marks > 0 ? (Number(cell.marks_obtained) / Number(cell.max_marks)) * 100 : null);
        if (pct !== null && Number.isFinite(pct)) pcts.push(pct);
      });
      out.set(s.key, {
        total: hasTotal ? Math.round(total * 100) / 100 : null,
        average: pcts.length ? pcts.reduce((a, b) => a + b, 0) / pcts.length : null,
        rank: null,
      });
    });
    const ranked = [...out.entries()].filter(([, v]) => v.average !== null).sort((a, b) => b[1].average - a[1].average);
    ranked.forEach(([key], i) => { out.get(key).rank = i + 1; });
    return out;
  }

  function bindSummativeEditing(students, columns) {
    const scope = $('vrSummativeMatrix');
    if (!scope) return;
    scope.querySelectorAll('.vr-cell-input').forEach((input) => {
      input.addEventListener('change', () => {
        const max = Number(input.dataset.max || 100);
        const raw = input.value.trim();
        if (raw !== '') {
          const v = Number(raw);
          if (!Number.isFinite(v) || v < 0 || v > max) {
            input.classList.add('is-error');
            notify(`Mark must be between 0 and ${max}.`, 'error');
            return;
          }
        }
        input.classList.remove('is-error');
        input.classList.add('is-saving');
        const sk = input.dataset.studentKey;
        if (!state.dirty.has(sk)) state.dirty.set(sk, new Map());
        state.dirty.get(sk).set(input.dataset.col, input);
        scheduleSummativeSave(sk);
      });
    });
  }

  function scheduleSummativeSave(studentKey) {
    if (state.saveTimers.has(studentKey)) clearTimeout(state.saveTimers.get(studentKey));
    state.saveTimers.set(studentKey, setTimeout(() => saveSummativeRow(studentKey), 700));
  }

  async function saveSummativeRow(studentKey) {
    const pending = state.dirty.get(studentKey);
    if (!pending || !pending.size) return;
    const items = [...pending.values()].map((i) => ({
      assessment_id: Number(i.dataset.assessment),
      enrollment_id: Number(i.dataset.enrollment),
      result_id: Number(i.dataset.result || 0),
      marks_obtained: i.value.trim() === '' ? null : Number(i.value),
      entry_status: 'present',
      expected_updated_at: i.dataset.updated || '',
    }));
    const inputs = [...pending.values()];
    try {
      const res = await payload('/academic/results-management-summative-batch', 'POST', { results: items, reason: 'Results Studio inline edit' });
      const outcomes = arr(res?.results);
      outcomes.forEach((o) => {
        if (o.result_id) {
          // Stamp the returned identity back onto the input for the next save.
          inputs.forEach((i) => {
            if (Number(i.dataset.assessment) === Number(o.assessment_id)) {
              i.dataset.result = String(o.result_id);
              i.dataset.updated = String(o.updated_at || '');
            }
          });
        }
      });
      inputs.forEach((i) => { i.classList.remove('is-saving'); i.classList.add('is-saved'); setTimeout(() => i.classList.remove('is-saved'), 900); });
      state.dirty.delete(studentKey);
      notify('Saved.', 'success');
    } catch (e) {
      inputs.forEach((i) => { i.classList.remove('is-saving'); i.classList.add('is-error'); });
      notify(e?.message || 'Could not save this row.', 'error');
    }
  }

  // ── ANALYTICS ────────────────────────────────────────────────────────────
  async function renderAnalytics() {
    $('vrView').innerHTML = loading('Computing analytics');
    destroyCharts();
    const res = await payload(`/academic/results-management-analytics${qs()}`);
    const overview = res?.overview || {};
    state.data.analytics = res;

    renderKpis([
      { value: overview.learners ?? 0, label: 'Learners', accent: 'var(--vr-moss)' },
      { value: num(overview.mean), unit: '%', label: 'Mean score', accent: 'var(--vr-forest)' },
      { value: num(overview.median), unit: '%', label: 'Median', accent: 'var(--vr-blue)' },
      { value: num(overview.pass_rate), unit: '%', label: 'Meeting+', accent: 'var(--vr-gold)' },
      { value: num(overview.distinction_rate), unit: '%', label: 'Exceeding', accent: 'var(--vr-amber)' },
      { value: overview.records ?? 0, label: 'Evidence records', accent: 'var(--vr-muted)' },
    ]);

    if (!overview.records) {
      $('vrView').innerHTML = emptyState('graph-up', 'Not enough evidence yet',
        'Analytics need recorded formative or summative marks in this scope.');
      return;
    }

    const dist = overview.distribution || {};
    const byArea = arr(res.by_learning_area);
    const byClass = arr(res.by_class);
    const byGender = arr(res.by_gender);
    const byTeacher = arr(res.by_teacher);
    const top = arr(res.top_students);

    const bestOf = `
      <ul class="vr-list">
        <li><span class="vr-list-rank"><i class="bi bi-person-badge"></i></span><div class="vr-list-main"><b>${esc(top[0]?.learner_name || '—')}</b><span>Top learner</span></div><span class="vr-list-val">${pctStr(top[0]?.overall)}</span></li>
        <li><span class="vr-list-rank"><i class="bi bi-book"></i></span><div class="vr-list-main"><b>${esc(byArea[0]?.label || '—')}</b><span>Strongest learning area</span></div><span class="vr-list-val">${num(byArea[0]?.mean)}%</span></li>
        <li><span class="vr-list-rank"><i class="bi bi-mortarboard"></i></span><div class="vr-list-main"><b>${esc(byClass[0]?.label || '—')}</b><span>Best performing class</span></div><span class="vr-list-val">${num(byClass[0]?.mean)}%</span></li>
        <li><span class="vr-list-rank"><i class="bi bi-gender-ambiguous"></i></span><div class="vr-list-main"><b>${esc(byGender[0]?.label || '—')}</b><span>Higher-performing group</span></div><span class="vr-list-val">${num(byGender[0]?.mean)}%</span></li>
        ${byTeacher.length ? `<li><span class="vr-list-rank"><i class="bi bi-easel"></i></span><div class="vr-list-main"><b>${esc(byTeacher[0]?.label || '—')}</b><span>Leading teacher (by mean)</span></div><span class="vr-list-val">${num(byTeacher[0]?.mean)}%</span></li>` : ''}
      </ul>`;

    const genderTable = `<ul class="vr-list">${byGender.map((g) => `
      <li><span class="vr-list-rank">${esc((g.label || '?')[0])}</span>
        <div class="vr-list-main"><b>${esc(g.label)}</b><span>${g.entries} records · ${num(g.meeting_rate)}% meeting+</span><div class="vr-meter"><i style="width:${Math.max(2, Math.min(100, Number(g.mean) || 0))}%"></i></div></div>
        <span class="vr-list-val">${num(g.mean)}%</span></li>`).join('')}</ul>`;

    const areaTable = `<ul class="vr-list">${byArea.map((a) => `
      <li><span class="vr-list-rank">${bandPill(a.band || bandOf(a.mean))}</span>
        <div class="vr-list-main"><b>${esc(a.label)}</b><span>${a.entries} records · ${num(a.meeting_rate)}% meeting+</span><div class="vr-meter"><i style="width:${Math.max(2, Math.min(100, Number(a.mean) || 0))}%"></i></div></div>
        <span class="vr-list-val">${num(a.mean)}%</span></li>`).join('')}</ul>`;

    const topTable = `<div class="vr-scroll" style="max-height:340px"><table class="vr-matrix"><thead><tr>
        <th>#</th><th>Learner</th><th class="vr-sticky vr-sticky-1" style="left:auto;position:static">Admission no.</th><th>Class</th><th class="vr-num">Overall</th><th class="vr-num">Band</th>
      </tr></thead><tbody>${top.map((s, i) => `<tr>
        <td><span class="vr-rank ${i < 3 ? 'is-top' : ''}">${i + 1}</span></td>
        <td>${esc(s.learner_name)}</td>
        <td>${esc(s.admission_no)}</td>
        <td>${esc(s.class_name || '—')}</td>
        <td class="vr-num vr-total">${pctStr(s.overall)}</td>
        <td class="vr-num">${bandPill(s.band || bandOf(s.overall))}</td>
      </tr>`).join('')}</tbody></table></div>`;

    setExport(`results-analytics-${Date.now()}.csv`,
      ['Metric', 'Label', 'Mean %', 'Records', 'Meeting+ %'],
      [...byArea.map((a) => ['Learning area', a.label, a.mean, a.entries, a.meeting_rate]),
        ...byClass.map((a) => ['Class', a.label, a.mean, a.entries, a.meeting_rate]),
        ...byGender.map((a) => ['Gender', a.label, a.mean, a.entries, a.meeting_rate])]);

    const grid = `<div class="vr-grid-2">
      ${panel('Grade distribution', 'share of records by CBC band', '', '<div class="vr-chart"><canvas id="vrChartDist"></canvas></div>')}
      ${panel('Best of the term', 'leaders across dimensions', '', bestOf)}
      ${panel('Mean by learning area', 'all areas in scope', '', '<div class="vr-chart"><canvas id="vrChartArea"></canvas></div>')}
      ${panel('Mean by class', 'comparison', '', '<div class="vr-chart"><canvas id="vrChartClass"></canvas></div>')}
      ${panel('Gender comparison', 'mean and meeting rate', '', genderTable)}
      ${byTeacher.length ? panel('Teaching staff', 'mean by assigned teacher', '', '<div class="vr-chart"><canvas id="vrChartTeacher"></canvas></div>') : panel('Learning-area detail', 'mean and meeting rate', '', areaTable)}
    </div>
    ${panel('Top learners', `highest overall mean`, toolBtn('CSV', 'filetype-csv', 'vr-no-print', 'data-export="analytics"'), topTable)}`;

    $('vrView').innerHTML = grid;
    $('vrView').querySelector('[data-export="analytics"]').onclick = () => state.exportSpec && downloadCsv(state.exportSpec.filename, state.exportSpec.headers, state.exportSpec.rows());
    drawCharts({ dist, byArea, byClass, byTeacher });
  }

  const PALETTE = { forest: '#0b3d2e', moss: '#178a50', gold: '#c9a227', goldSoft: '#f3bd18', blue: '#1f5f8b', red: '#b4232b', amber: '#9a6a00', grey: '#c9cfc9' };
  const BAND_COLORS = { EE: '#178a50', ME: '#1f5f8b', AE: '#c9a227', BE: '#b4232b' };

  function chartBase() {
    return { responsive: true, maintainAspectRatio: false, plugins: { legend: { labels: { font: { size: 11 }, boxWidth: 12 } } } };
  }

  function drawCharts({ dist, byArea, byClass, byTeacher }) {
    if (!window.Chart) return;
    const distLabels = ['EE', 'ME', 'AE', 'BE'];
    const distData = distLabels.map((k) => Number(dist[k] || 0));
    makeChart('vrChartDist', {
      type: 'doughnut',
      data: { labels: distLabels, datasets: [{ data: distData, backgroundColor: distLabels.map((k) => BAND_COLORS[k]), borderWidth: 0 }] },
      options: { ...chartBase(), cutout: '62%' },
    });
    makeChart('vrChartArea', barChart(byArea, PALETTE.moss));
    makeChart('vrChartClass', barChart(byClass, PALETTE.forest));
    if ($('vrChartTeacher')) makeChart('vrChartTeacher', barChart(byTeacher, PALETTE.gold));
  }

  function barChart(rows, color) {
    return {
      type: 'bar',
      data: {
        labels: rows.map((r) => r.label),
        datasets: [{ data: rows.map((r) => r.mean), backgroundColor: color, borderRadius: 5, maxBarThickness: 34 }],
      },
      options: {
        ...chartBase(),
        plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => `${c.parsed.y}% · ${rows[c.dataIndex]?.entries || 0} records` } } },
        scales: { y: { beginAtZero: true, max: 100, ticks: { callback: (v) => `${v}%`, font: { size: 10 } } }, x: { ticks: { font: { size: 10 } } } },
      },
    };
  }

  function makeChart(id, config) {
    const el = $(id);
    if (!el) return;
    if (state.charts[id]) state.charts[id].destroy();
    state.charts[id] = new window.Chart(el, config);
  }
  function destroyCharts() {
    Object.values(state.charts).forEach((c) => { try { c.destroy(); } catch (_) { /* noop */ } });
    state.charts = {};
  }

  // ── PORTFOLIO ────────────────────────────────────────────────────────────
  async function renderPortfolio() {
    $('vrView').innerHTML = loading('Loading portfolios');
    const res = await payload(`/academic/portfolio-hub${qs()}`);
    const learners = arr(res?.learners);
    state.data.portfolio = learners;

    const withPortfolio = learners.filter((l) => l.portfolio_id).length;
    const withArtifacts = learners.filter((l) => Number(l.artifact_count || 0) > 0).length;
    renderKpis([
      { value: learners.length, label: 'Learners', accent: 'var(--vr-moss)' },
      { value: withPortfolio, label: 'Portfolios', accent: 'var(--vr-forest)' },
      { value: withArtifacts, label: 'With evidence', accent: 'var(--vr-gold)' },
      { value: learners.reduce((s, l) => s + Number(l.artifact_count || 0), 0), label: 'Artifacts', accent: 'var(--vr-blue)' },
    ]);

    if (!learners.length) {
      $('vrView').innerHTML = emptyState('collection', 'No learners in this scope', 'Choose a class with active learners.');
      return;
    }

    setExport(`portfolios-${Date.now()}.csv`,
      ['Admission no.', 'Learner', 'Class', 'Status', 'Artifacts', 'Learning areas with evidence'],
      learners.map((l) => [l.admission_no, `${l.first_name || ''} ${l.last_name || ''}`.trim(), `${l.class_name || ''}${l.stream_name ? ' ' + l.stream_name : ''}`, l.portfolio_status || 'none', l.artifact_count || 0, (l.learning_areas || []).join('; ')]));

    const statusTag = (s) => {
      const v = String(s || 'none').toLowerCase();
      if (v === 'complete' || v === 'final') return '<span class="vr-tag is-ok">Complete</span>';
      if (v === 'active' || v === 'in_progress') return '<span class="vr-tag is-warn">In progress</span>';
      return '<span class="vr-tag is-none">Not started</span>';
    };
    const cards = learners.map((l) => {
      const name = `${l.first_name || ''} ${l.middle_name ? l.middle_name + ' ' : ''}${l.last_name || ''}`.trim() || '—';
      const evidence = l.evidence_counts || {};
      return `<article class="vr-card">
        <div class="vr-card-head">
          <div class="vr-avatar">${esc(initials(name))}</div>
          <div><b>${esc(name)}</b><span>${esc(l.admission_no)} · ${esc(l.class_name || '')}${l.stream_name ? ' · ' + esc(l.stream_name) : ''}</span></div>
        </div>
        <div class="vr-card-body">
          <div class="vr-badge-row">${statusTag(l.portfolio_status)}${(l.learning_areas || []).slice(0, 3).map((a) => `<span class="vr-tag">${esc(a)}</span>`).join('') || '<span class="vr-tag is-none">No areas evidenced</span>'}</div>
          <div class="vr-card-stats">
            <div class="vr-card-stat"><b>${Number(l.artifact_count || 0)}</b><span>Artifacts</span></div>
            <div class="vr-card-stat"><b>${Number(evidence.project || 0)}</b><span>Projects</span></div>
            <div class="vr-card-stat"><b>${Number((l.learning_areas || []).length)}</b><span>Areas</span></div>
          </div>
        </div>
        <div class="vr-card-foot">
          <button type="button" class="vr-btn" data-profile="${escAttr(l.student_id)}" data-name="${escAttr(name)}" data-adm="${escAttr(l.admission_no)}"><i class="bi bi-eye"></i>Profile</button>
          <a class="vr-btn vr-no-print" href="home.php?route=student_portfolio&student_id=${escAttr(l.student_id)}"><i class="bi bi-box-arrow-up-right"></i>Open portfolio</a>
        </div>
      </article>`;
    }).join('');

    $('vrView').innerHTML = panel('Learner portfolios',
      `${learners.length} learners · ${withArtifacts} with evidence`,
      toolBtn('CSV', 'filetype-csv', 'vr-no-print', 'data-export="portfolio"') + toolBtn('Print', 'printer', 'vr-no-print', 'data-print="1"'),
      `<div class="vr-cards" style="padding:14px">${cards}</div>`);
    $('vrView').querySelector('[data-export="portfolio"]').onclick = () => state.exportSpec && downloadCsv(state.exportSpec.filename, state.exportSpec.headers, state.exportSpec.rows());
    $('vrView').querySelector('[data-print="1"]').onclick = () => window.print();
    $('vrView').querySelectorAll('[data-profile]').forEach((b) => {
      b.onclick = () => openDrawer(b.dataset.profile, b.dataset.name, b.dataset.adm);
    });
  }

  // ── Learner drawer ───────────────────────────────────────────────────────
  let lastFocused = null;
  async function openDrawer(studentId, name, adm) {
    const drawer = $('vrDrawer');
    const scrim = $('vrDrawerBackdrop');
    if (!drawer || !studentId) return;
    lastFocused = document.activeElement;
    $('vrDrawerName').textContent = name || 'Learner';
    $('vrDrawerSub').textContent = `${adm || ''}${state.filters.class_id ? ' · ' + (state.refs.classes.find((c) => String(c.id) === state.filters.class_id)?.name || '') : ''}`;
    drawer.classList.add('is-open'); scrim.classList.add('is-open'); drawer.setAttribute('aria-hidden', 'false');
    $('vrDrawerBody').innerHTML = loading('Loading learner summary');
    try {
      const r = await payload(`/academic/results-management-average${qs({ student_id: studentId })}`);
      const items = arr(r?.items || r);
      const row = items[0] || {};
      const portfolio = (state.data.portfolio || []).find((l) => String(l.student_id) === String(studentId));
      $('vrDrawerBody').innerHTML = `
        <div class="vr-cards" style="grid-template-columns:1fr">
          <div class="vr-card" style="display:block">
            <div class="vr-card-body">
              <div class="vr-card-stats">
                <div class="vr-card-stat"><b>${row.formative_average !== null && row.formative_average !== undefined ? num(row.formative_average) + '%' : '—'}</b><span>Formative</span></div>
                <div class="vr-card-stat"><b>${row.summative_average !== null && row.summative_average !== undefined ? num(row.summative_average) + '%' : '—'}</b><span>Summative</span></div>
                <div class="vr-card-stat"><b>${row.overall_average !== null && row.overall_average !== undefined ? num(row.overall_average) + '%' : '—'}</b><span>Overall</span></div>
              </div>
              ${row.grade_band ? `<div class="vr-badge-row" style="margin-top:10px">${bandPill(row.grade_band)}</div>` : ''}
              ${portfolio ? `<div class="vr-badge-row" style="margin-top:10px"><span class="vr-tag">${Number(portfolio.artifact_count || 0)} portfolio artifacts</span></div>` : ''}
            </div>
          </div>
        </div>
        <div style="margin-top:14px" class="vr-no-print">
          <a class="vr-btn" href="home.php?route=student_portfolio&student_id=${escAttr(studentId)}"><i class="bi bi-box-arrow-up-right"></i>Open full portfolio</a>
        </div>`;
    } catch (e) {
      $('vrDrawerBody').innerHTML = alertBox(e?.message || 'Unable to load learner summary.');
    }
  }
  function closeDrawer() {
    const drawer = $('vrDrawer'); const scrim = $('vrDrawerBackdrop');
    if (!drawer) return;
    drawer.classList.remove('is-open'); scrim.classList.remove('is-open'); drawer.setAttribute('aria-hidden', 'true');
    if (lastFocused?.focus) lastFocused.focus();
  }

  // ── Bindings + init ──────────────────────────────────────────────────────
  let searchTimer = null;
  function bindControls() {
    document.querySelectorAll('.vr-tab').forEach((b) => { b.onclick = () => setTab(b.dataset.tab); });

    $('vrYearSelect')?.addEventListener('change', () => {
      populateTermSelect(); populateClassSelect(); populateStreams(); refresh();
    });
    $('vrTermSelect')?.addEventListener('change', () => { populateExamPeriodSelect(); populateStreams(); loadClassAreas().then(populateAreaSelect); refresh(); });
    $('vrClassSelect')?.addEventListener('change', () => { populateStreams(); loadClassAreas().then(() => { populateAreaSelect(); loadStrandsForArea(state.selectedAreaId); }); refresh(); });
    $('vrStreamSelect')?.addEventListener('change', refresh);
    $('vrCategorySelect')?.addEventListener('change', () => { populateExamPeriodSelect(); refresh(); });
    $('vrExamPeriodSelect')?.addEventListener('change', refresh);
    $('vrAreaSelect')?.addEventListener('change', () => onAreaChange(false).then(() => { if (state.tab === 'formative') renderFormative(); else refresh(); }));
    $('vrStrandSelect')?.addEventListener('change', () => { onStrandChange(); refresh(); });
    $('vrSubStrandSelect')?.addEventListener('change', refresh);
    $('vrSearchInput')?.addEventListener('input', () => {
      if (searchTimer) clearTimeout(searchTimer);
      searchTimer = setTimeout(refresh, 340);
    });

    $('vrRefreshBtn')?.addEventListener('click', refresh);
    $('vrExportBtn')?.addEventListener('click', () => {
      if (state.exportSpec) downloadCsv(state.exportSpec.filename, state.exportSpec.headers, state.exportSpec.rows());
      else notify('Nothing to export in this view.', 'info');
    });
    $('vrPrintBtn')?.addEventListener('click', () => window.print());
    $('vrDrawerClose')?.addEventListener('click', closeDrawer);
    $('vrDrawerBackdrop')?.addEventListener('click', closeDrawer);
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeDrawer(); });
  }

  async function init() {
    if (state.initialised) return;
    state.initialised = true;
    bindControls();
    applyTabFieldVisibility();
    renderKpis([]);
    $('vrView').innerHTML = loading('Preparing Results Studio');
    await loadReferences();
    populateBaseSelectors();
    applyDefaults();
    renderGradingToggle();
    populateExamPeriodSelect();
    await populateStreams();
    await loadClassAreas();
    populateAreaSelect();
    await refresh();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
  else init();

  window.ViewResultsStudio = { refresh, setTab };
})();
