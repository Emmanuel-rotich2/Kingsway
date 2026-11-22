/**
 * Class Records page.
 *
 * "This year": one class stream in the selected academic year
 * ("Grade 1 A · Class of 2026"), every learner on the roll against every
 * learning area for the chosen term, plus class averages per term.
 * "Over the years": the same learners followed through every year they
 * were enrolled, with each year's class and class teacher.
 *
 * Read-only. The year comes from the shared AcademicYearFilter (default:
 * the current year, kept in the URL); the class list shows only classes the
 * user may open (teachers: the classes they teach or have taught).
 */
const classRecordsController = {
  state: { yearId: null, streams: [], record: null, cohort: null, request: 0 },

  api(endpoint, params) { return window.callAPI(endpoint, 'GET', null, params); },
  data(response) { return response?.data?.data ?? response?.data ?? response; },
  esc(value) { return String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]); },
  el(id) { return document.getElementById(id); },
  view() { return document.querySelector('input[name="crView"]:checked')?.value || 'year'; },
  pct(v) { return v == null ? '—' : `${Number(v).toFixed(1)}%`; },

  status(text, isError = false) {
    const box = this.el('crStatus');
    if (!box) return;
    box.classList.toggle('d-none', !text);
    box.classList.toggle('text-danger', isError);
    box.textContent = text || '';
  },

  show(which) {
    this.el('crYearView')?.classList.toggle('d-none', which !== 'year');
    this.el('crCohortView')?.classList.toggle('d-none', which !== 'cohort');
    this.el('crTermWrap')?.classList.toggle('d-none', which === 'cohort');
  },

  urlStream() {
    try { const v = new URLSearchParams(window.location.search).get('academic_year_class_stream_id'); return v && /^\d+$/.test(v) ? Number(v) : null; } catch (_) { return null; }
  },

  rememberStream(id) {
    try {
      const url = new URL(window.location.href);
      if (id) url.searchParams.set('academic_year_class_stream_id', String(id)); else url.searchParams.delete('academic_year_class_stream_id');
      window.history.replaceState(window.history.state, '', url);
    } catch (_) { /* the page still works without the URL */ }
  },

  async loadStreams(yearId) {
    this.state.yearId = yearId;
    const request = ++this.state.request;
    this.status('Loading classes…');
    const response = await this.api('/api/academic/class-record-streams', yearId ? { academic_year_id: yearId } : {});
    if (request !== this.state.request) return;
    if (response?.success === false) { this.status(response.message || 'Classes could not be loaded.', true); return; }
    const payload = this.data(response) || {};
    this.state.streams = Array.isArray(payload.streams) ? payload.streams : [];
    const note = this.el('crScopeNote');
    if (note) note.textContent = payload.scope === 'own_classes'
      ? 'Your classes, this year and past years.'
      : 'All classes.';
    const select = this.el('crClass');
    const wanted = this.urlStream();
    const withLearners = this.state.streams.filter((s) => s.learners > 0);
    const list = withLearners.length ? withLearners : this.state.streams;
    if (select) {
      select.innerHTML = list.length
        ? list.map((s) => `<option value="${Number(s.academic_year_class_stream_id)}">${this.esc(s.label)} (${Number(s.learners)} learners)</option>`).join('')
        : '<option value="">No classes for this year</option>';
      const keep = list.find((s) => Number(s.academic_year_class_stream_id) === wanted);
      if (keep) select.value = String(keep.academic_year_class_stream_id);
    }
    if (!list.length) {
      this.show(null);
      this.status(payload.scope === 'own_classes' ? 'You have no classes recorded for this academic year.' : 'No classes are set up for this academic year.');
      return;
    }
    await this.load();
  },

  async load() {
    const streamId = Number(this.el('crClass')?.value || 0);
    if (!streamId) return;
    this.rememberStream(streamId);
    const request = ++this.state.request;
    const cohort = this.view() === 'cohort';
    this.status(cohort ? 'Loading the class over the years…' : 'Loading the class record…');
    const response = await this.api(cohort ? `/api/academic/class-cohort-history/${streamId}` : `/api/academic/class-record/${streamId}`);
    if (request !== this.state.request) return;
    if (response?.success === false) { this.show(null); this.status(response.message || 'The class record could not be loaded.', true); return; }
    const payload = this.data(response) || {};
    this.status('');
    if (cohort) { this.state.cohort = payload; this.renderCohort(); this.show('cohort'); }
    else { this.state.record = payload; this.fillTerms(); this.renderYear(); this.show('year'); }
  },

  fillTerms() {
    const terms = this.state.record?.terms || [];
    const select = this.el('crTerm');
    if (!select) return;
    const previous = Number(select.value || 0);
    // Default: the latest term that has any result, else the last term.
    const withData = terms.filter((t) => (this.state.record.learning_areas || []).some((a) => a.terms.some((x) => x.academic_year_term_id === t.academic_year_term_id && x.learners_assessed > 0)));
    const fallback = (withData[withData.length - 1] || terms[terms.length - 1] || {}).academic_year_term_id;
    select.innerHTML = terms.map((t) => `<option value="${Number(t.academic_year_term_id)}">${this.esc(t.term)}</option>`).join('');
    const keep = terms.find((t) => t.academic_year_term_id === previous);
    select.value = String(keep ? previous : (fallback || ''));
  },

  termId() { return Number(this.el('crTerm')?.value || 0); },
  termName() { return this.el('crTerm')?.selectedOptions?.[0]?.textContent || ''; },

  renderYear() {
    const r = this.state.record || {};
    const c = r.class || {};
    const tid = this.termId();
    this.el('crClassLabel').textContent = c.label || '';
    this.el('crClassMeta').textContent = [
      c.academic_year ? `Academic year ${c.academic_year}` : '',
      `Class teacher: ${c.class_teacher || 'not recorded'}`,
      `${Number(r.roll || 0)} learners on the roll`,
    ].filter(Boolean).join(' · ');
    this.el('crTermLabel').textContent = this.termName() ? `— ${this.termName()} result per learning area` : '';

    const areas = r.learning_areas || [];
    const head = `<thead class="table-light"><tr><th>#</th><th>Learner</th><th>Adm No</th>${areas.map((a) => `<th class="cr-area text-center">${this.esc(a.name)}</th>`).join('')}<th class="text-center">Average</th></tr></thead>`;
    const learners = r.learners || [];
    const body = learners.length ? learners.map((l, i) => {
      const cells = areas.map((a) => {
        const s = l.results?.[a.learning_area_id]?.[tid];
        return `<td class="text-center cr-score">${s ? `${this.pct(s.overall_percentage)}${s.grade ? ` <small class="text-muted">${this.esc(window.CbcLevels.format(s.grade, ''))}</small>` : ''}` : '<span class="text-muted">—</span>'}</td>`;
      }).join('');
      const status = l.enrollment_status === 'graduated' ? ' <span class="badge bg-info-subtle text-info-emphasis">graduated</span>' : '';
      return `<tr><td>${i + 1}</td><td>${this.esc(l.name)}${status}</td><td>${this.esc(l.admission_no || '—')}</td>${cells}<td class="text-center fw-semibold">${this.pct(l.term_averages?.[tid])}</td></tr>`;
    }).join('') : `<tr><td colspan="${areas.length + 4}" class="text-center text-muted py-4">No learners on this class's roll.</td></tr>`;
    const anyResult = learners.some((l) => areas.some((a) => l.results?.[a.learning_area_id]?.[tid]));
    const foot = learners.length && !anyResult
      ? `<tfoot><tr><td colspan="${areas.length + 4}" class="text-center text-muted">No approved results for ${this.esc(this.termName() || 'this term')} yet.</td></tr></tfoot>` : '';
    this.el('crLearnersTable').innerHTML = head + `<tbody>${body}</tbody>` + foot;

    const terms = r.terms || [];
    const aHead = `<thead class="table-light"><tr><th>Learning area</th>${terms.map((t) => `<th class="text-center">${this.esc(t.term)}</th>`).join('')}</tr></thead>`;
    const aBody = areas.length ? areas.map((a) => `<tr><td>${this.esc(a.name)}</td>${a.terms.map((t) => {
      const bands = Object.entries(t.grade_bands || {}).map(([g, n]) => `${this.esc(g)} ${Number(n)}`).join(' · ');
      const pending = t.assessments_pending ? ` <small class="text-warning">${Number(t.assessments_pending)} awaiting approval</small>` : '';
      return `<td class="text-center">${t.learners_assessed ? `<strong>${this.pct(t.average_percentage)}</strong><br><small class="text-muted">${Number(t.learners_assessed)} assessed${bands ? ` · ${bands}` : ''}</small>` : '<span class="text-muted">not assessed</span>'}${pending}</td>`;
    }).join('')}</tr>`).join('') : `<tr><td colspan="${terms.length + 1}" class="text-center text-muted py-3">No learning areas are set up for this class.</td></tr>`;
    this.el('crAreasTable').innerHTML = aHead + `<tbody>${aBody}</tbody>`;

    const left = r.left_during_year || [];
    this.el('crLeftCard').classList.toggle('d-none', !left.length);
    this.el('crLeftList').innerHTML = left.map((l) => `<span class="badge bg-light text-dark border me-1 mb-1">${this.esc(l.name)} (${this.esc(l.enrollment_status)})</span>`).join('');
  },

  renderCohort() {
    const h = this.state.cohort || {};
    const c = h.class || {};
    this.el('crCohortLabel').textContent = c.label ? `The learners of ${c.label}` : '';
    this.el('crCohortMeta').textContent = `${Number(h.cohort_size || 0)} learners`;
    const years = h.years || [];
    this.el('crCohortYears').innerHTML = years.length ? years.map((y) => {
      const classes = (y.classes || []).map((k) => `<span class="badge bg-light text-dark border me-1">${this.esc(k.label)} — ${Number(k.cohort_learners)} learners · class teacher: ${this.esc(k.class_teacher || 'not recorded')}</span>`).join('');
      const terms = y.terms || [];
      const rows = (y.learning_areas || []).map((a) => `<tr><td>${this.esc(a.name)}</td>${a.terms.map((t) => `<td class="text-center">${t.learners_assessed ? `${this.pct(t.average_percentage)} <small class="text-muted">(${Number(t.learners_assessed)})</small>` : '<span class="text-muted">—</span>'}</td>`).join('')}</tr>`).join('');
      return `<div class="card mb-3"><div class="card-header d-flex flex-wrap justify-content-between gap-2">
          <strong>${this.esc(y.academic_year)}${y.is_current ? ' <span class="badge bg-success">current</span>' : ''}</strong>
          <span class="text-muted small">${Number(y.cohort_learners)} of the cohort enrolled</span></div>
        <div class="card-body"><div class="mb-2">${classes}</div>
          <div class="table-responsive"><table class="table table-sm table-bordered mb-0"><thead class="table-light"><tr><th>Learning area</th>${terms.map((t) => `<th class="text-center">${this.esc(t.term)}</th>`).join('')}</tr></thead>
          <tbody>${rows || `<tr><td colspan="${terms.length + 1}" class="text-center text-muted">No approved results this year yet.</td></tr>`}</tbody></table></div>
        </div></div>`;
    }).join('') : '<div class="text-muted">No years recorded for these learners.</div>';
  },

  exportCSV() {
    if (window.AuthContext?.canExport && !window.AuthContext.canExport('results')) return window.showNotification?.('You do not have permission to export results.', 'error');
    const q = (v) => `"${String(v ?? '').replace(/"/g, '""')}"`;
    const lines = [];
    let name = 'class-record';
    if (this.view() === 'cohort') {
      const h = this.state.cohort || {};
      name = `class-history_${h.class?.label || ''}`;
      lines.push([`The learners of ${h.class?.label || ''}`].map(q).join(','));
      (h.years || []).forEach((y) => {
        lines.push('', [y.academic_year, (y.classes || []).map((k) => `${k.label} (class teacher: ${k.class_teacher || 'not recorded'})`).join('; ')].map(q).join(','));
        lines.push(['Learning area', ...(y.terms || []).map((t) => `${t.term} average %`)].map(q).join(','));
        (y.learning_areas || []).forEach((a) => lines.push([a.name, ...a.terms.map((t) => t.average_percentage ?? '')].map(q).join(',')));
      });
    } else {
      const r = this.state.record || {};
      const tid = this.termId();
      const areas = r.learning_areas || [];
      name = `class-record_${r.class?.label || ''}_${this.termName()}`;
      lines.push([`${r.class?.label || ''} — ${r.class?.academic_year || ''} — ${this.termName()}`].map(q).join(','));
      lines.push(['#', 'Learner', 'Adm No', 'Status', ...areas.map((a) => `${a.name} %`), 'Average %'].map(q).join(','));
      (r.learners || []).forEach((l, i) => lines.push([i + 1, l.name, l.admission_no, l.enrollment_status, ...areas.map((a) => l.results?.[a.learning_area_id]?.[tid]?.overall_percentage ?? ''), l.term_averages?.[tid] ?? ''].map(q).join(',')));
    }
    const file = `${name.replace(/[^0-9A-Za-z-]+/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, '')}.csv`;
    window.KingswayFileLifecycle?.exportText?.('﻿' + lines.join('\r\n'), file, 'text/csv;charset=utf-8');
  },

  print() {
    if (window.AuthContext?.canPrint && !window.AuthContext.canPrint('results')) return window.showNotification?.('You do not have permission to print results.', 'error');
    window.print();
  },

  async init() {
    this.el('crExportBtn')?.addEventListener('click', () => this.exportCSV());
    this.el('crPrintBtn')?.addEventListener('click', () => this.print());
    this.el('crClass')?.addEventListener('change', () => this.load());
    this.el('crTerm')?.addEventListener('change', () => this.renderYear());
    document.querySelectorAll('input[name="crView"]').forEach((input) => input.addEventListener('change', () => this.load()));
    const yearSelect = this.el('crYear');
    const mounted = window.AcademicYearFilter && yearSelect
      ? await window.AcademicYearFilter.mount(yearSelect, { onChange: (yearId) => this.loadStreams(yearId).catch((e) => this.status(e?.message || 'Classes could not be loaded.', true)) })
      : null;
    if (!mounted) await this.loadStreams(null);
  },
};
window.classRecordsController = classRecordsController;
document.addEventListener('DOMContentLoaded', () => classRecordsController.init());
