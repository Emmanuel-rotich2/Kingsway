/** Student growth page backed by authorized student and academic history APIs. */
const studentGrowthController = {
  state: { students: [], classes: [], terms: [], areas: [], selected: null, assessments: [], trend: [] },
  api(method, endpoint, data, params) { return window.callAPI(endpoint, method, data, params); },
  esc(value) { return String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]); },
  rows(response) { return Array.isArray(response?.data) ? response.data : (Array.isArray(response) ? response : []); },
  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    const [classes, terms, areas] = await Promise.all([
      this.api('GET', '/api/academic/classes-list'), this.api('GET', '/api/academic/terms-list'),
      this.api('GET', '/api/academic/learning-areas/list'),
    ]);
    this.state.classes = this.rows(classes); this.state.terms = this.rows(terms); this.state.areas = this.rows(areas);
    const classSelect = document.getElementById('sgClassFilter');
    if (classSelect) classSelect.innerHTML = '<option value="">All Classes</option>' + this.state.classes.map((c) => `<option value="${Number(c.id)}">${this.esc(c.name || c.class_name)}</option>`).join('');
    const termSelect = document.getElementById('sgPerfTermFilter');
    if (termSelect) termSelect.innerHTML = '<option value="">Current Term</option>' + this.state.terms.map((t) => `<option value="${Number(t.id)}" ${t.status === 'current' ? 'selected' : ''}>${this.esc(t.name || t.term_name)}</option>`).join('');
    const areaSelect = document.getElementById('sgGrowthLASelect');
    if (areaSelect) areaSelect.innerHTML = '<option value="">— Select learning area —</option>' + this.state.areas.map((a) => `<option value="${Number(a.id)}">${this.esc(a.name || a.subject_name)}</option>`).join('');
    const search = document.getElementById('sgSearchInput'); let timer;
    search?.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(() => this.search(search.value), 250); });
    classSelect?.addEventListener('change', () => this.filterByClass());
    termSelect?.addEventListener('change', () => this.loadPerformance());
    areaSelect?.addEventListener('change', () => this.plotGrowth());
    const id = new URLSearchParams(window.location.search).get('student_id');
    if (id) await this.openStudent({ id: Number(id), first_name: 'Learner' });
    window.APIRealtime?.register?.('student_growth', { refresh: () => this.state.selected ? this.openStudent(this.state.selected) : this.search(search?.value || '') }, ['students', 'academic']);
  },
  async search(query) {
    const host = document.getElementById('sgSearchResults'); const text = String(query || '').trim();
    if (!host) return;
    if (text.length < 2) { host.innerHTML = '<div class="text-center text-muted py-5"><i class="bi bi-search fs-1 d-block mb-2 opacity-25"></i>Start typing to find a student.</div>'; this.state.students = []; return; }
    host.innerHTML = '<div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm me-2"></span>Searching students…</div>';
    try {
      const response = await this.api('GET', '/api/students/get-all', null, { search: text, limit: 100, status: 'active' });
      const data = response?.data?.data ?? response?.data ?? response;
      this.state.students = Array.isArray(data) ? data : [];
      this.renderSearch();
    } catch (error) { host.innerHTML = `<div class="alert alert-danger">${this.esc(error?.message || 'Student search is unavailable.')}</div>`; }
  },
  filterByClass() { this.renderSearch(); },
  renderSearch() {
    const host = document.getElementById('sgSearchResults'); if (!host) return;
    const classId = document.getElementById('sgClassFilter')?.value || '';
    const list = this.state.students.filter((student) => !classId || String(student.class_id || '') === classId);
    host.innerHTML = list.length ? `<div class="list-group shadow-sm">${list.map((student) => {
      const name = [student.first_name, student.middle_name, student.last_name].filter(Boolean).join(' ') || student.full_name || student.name || 'Learner';
      return `<button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" data-student-id="${Number(student.id)}"><span><strong>${this.esc(name)}</strong><small class="d-block text-muted">${this.esc(student.admission_no || '—')} · ${this.esc(student.class_name || 'Class not assigned')}</small></span><i class="bi bi-chevron-right"></i></button>`;
    }).join('')}</div>` : '<div class="alert alert-warning">No matching students were found.</div>';
    host.querySelectorAll('[data-student-id]').forEach((button) => button.addEventListener('click', () => {
      const student = this.state.students.find((item) => Number(item.id) === Number(button.dataset.studentId)); if (student) this.openStudent(student);
    }));
  },
  async openStudent(student) {
    this.state.selected = student;
    const name = [student.first_name, student.middle_name, student.last_name].filter(Boolean).join(' ') || student.full_name || student.name || 'Learner';
    this.text('sgStudentName', name); this.text('sgClass', student.class_name || '—'); this.text('sgAdmNo', student.admission_no || '—');
    document.getElementById('sgSearchView').style.display = 'none'; document.getElementById('sgProfileView').style.display = '';
    this.setHtml('sgPerfBody', this.loadingRow(7)); this.setHtml('sgAssessmentsBody', this.loadingRow(7));
    try {
      const [history, trend] = await Promise.all([
        this.api('GET', '/api/academic/student-assessment-history', null, { student_id: Number(student.id) }),
        this.api('GET', '/api/academic/student-growth-trend', null, { student_id: Number(student.id) }),
      ]);
      this.state.assessments = this.rows(history); this.state.trend = this.rows(trend);
      this.loadPerformance(); this.renderAssessments(); this.renderInsights(); this.renderYearSummary(); this.plotGrowth();
    } catch (error) {
      const message = this.esc(error?.message || 'Academic history could not be loaded.');
      this.setHtml('sgPerfBody', `<tr><td colspan="7" class="text-center text-danger py-4">${message}</td></tr>`);
      this.setHtml('sgAssessmentsBody', `<tr><td colspan="7" class="text-center text-danger py-4">${message}</td></tr>`);
    }
  },
  loadPerformance() {
    const termId = document.getElementById('sgPerfTermFilter')?.value || '';
    const rows = this.state.assessments.filter((x) => !termId || String(x.term_id) === termId);
    this.text('sgPerfTerm', rows[0]?.term_name || 'Selected');
    const grouped = new Map();
    rows.forEach((row) => { const key = row.subject_name || 'Unassigned learning area'; if (!grouped.has(key)) grouped.set(key, []); grouped.get(key).push(row); });
    const metrics = [...grouped.entries()].map(([subject, items]) => {
      const formative = items.filter((x) => Boolean(Number(x.is_formative))).map((x) => Number(x.percentage)).filter(Number.isFinite);
      const summative = items.filter((x) => Boolean(Number(x.is_summative))).map((x) => Number(x.percentage)).filter(Number.isFinite);
      const ca = formative.length ? formative.reduce((a, b) => a + b, 0) / formative.length : null;
      const exam = summative.length ? summative.reduce((a, b) => a + b, 0) / summative.length : null;
      const score = ca != null && exam != null ? ca * 0.4 + exam * 0.6 : (ca ?? exam);
      return { subject, count: formative.length, ca, exam, score, grade: score == null ? null : (window.GradingScale?.grade?.(score) || '—') };
    });
    this.setHtml('sgPerfBody', metrics.length ? metrics.map((m) => `<tr><td>${this.esc(m.subject)}</td><td class="text-center">${m.count || '—'}</td><td class="text-center">${m.ca == null ? '—' : `${m.ca.toFixed(1)}%`}</td><td class="text-center">${m.exam == null ? '—' : `${m.exam.toFixed(1)}%`}</td><td class="text-center">${m.score == null ? '—' : `${m.score.toFixed(1)}%`}</td><td class="text-center">${this.esc(m.grade || '—')}</td><td class="text-center">—</td></tr>`).join('') : '<tr><td colspan="7" class="text-center text-muted py-4">No assessment scores are available for this term.</td></tr>');
    this.text('sgTerm', rows[0]?.term_name || '—');
    const scored = metrics.map((m) => m.score).filter((x) => x != null);
    this.text('sgYearAvg', scored.length ? `${(scored.reduce((a, b) => a + b, 0) / scored.length).toFixed(1)}%` : '—');
    const badge = document.getElementById('sgOverallGradeBadge'); if (badge) badge.textContent = scored.length ? (window.GradingScale?.grade?.(scored.reduce((a, b) => a + b, 0) / scored.length) || '—') : '—';
    this.setHtml('sgCompetenciesGrid', '<div class="col-12 text-muted">Competency ratings are not available from the current assessment history endpoint.</div>');
  },
  renderAssessments() {
    const rows = this.state.assessments;
    this.setHtml('sgAssessmentsBody', rows.length ? rows.map((r) => `<tr><td>${this.esc(r.title || '—')}</td><td>${this.esc(r.type_name || (r.is_formative ? 'Formative' : 'Summative'))}</td><td>${this.esc(r.subject_name || '—')}</td><td>${this.date(r.assessment_date)}</td><td class="text-center">${r.score == null ? '—' : `${this.esc(r.score)} / ${this.esc(r.max_marks)}`}</td><td class="text-center">${r.percentage == null ? '—' : `${Number(r.percentage).toFixed(1)}%`}</td><td class="text-center">${this.esc(r.cbc_grade || '—')}</td></tr>`).join('') : '<tr><td colspan="7" class="text-center text-muted py-4">No assessment records are available.</td></tr>');
  },
  renderInsights() {
    const rows = this.state.assessments; const bySubject = new Map();
    rows.forEach((r) => { const key = r.subject_name || 'Learning area'; const val = Number(r.percentage); if (Number.isFinite(val)) { if (!bySubject.has(key)) bySubject.set(key, []); bySubject.get(key).push(val); } });
    const averages = [...bySubject.entries()].map(([name, vals]) => ({ name, score: vals.reduce((a, b) => a + b, 0) / vals.length })).sort((a, b) => b.score - a.score);
    this.setHtml('sgStrengthsList', averages.length ? averages.filter((x) => x.score >= 50).slice(0, 5).map((x) => `<div class="d-flex justify-content-between border-bottom py-2"><span>${this.esc(x.name)}</span><strong>${x.score.toFixed(1)}%</strong></div>`).join('') || '<div class="text-muted">No learning area averages meet the strength threshold yet.</div>' : '<div class="text-muted">No scored results available.</div>');
    this.setHtml('sgWeaknessesList', averages.length ? averages.filter((x) => x.score < 50).slice(0, 5).map((x) => `<div class="d-flex justify-content-between border-bottom py-2"><span>${this.esc(x.name)}</span><strong>${x.score.toFixed(1)}%</strong></div>`).join('') || '<div class="text-muted">No learning area averages below 50% in this data.</div>' : '<div class="text-muted">No scored results available.</div>');
    this.setHtml('sgCommentsBlock', '<div class="text-muted small">Teacher comments are not included in the available assessment history.</div>');
  },
  renderYearSummary() {
    const rows = this.state.trend; const host = document.getElementById('sgYearSummary');
    if (host) host.innerHTML = rows.length ? `<div class="small text-muted">${rows.length} term and learning-area results</div><div class="fw-semibold mt-2">${this.esc(rows[rows.length - 1].year || 'Current year')}</div>` : '<div class="text-muted">No term trend data is available.</div>';
  },
  plotGrowth() {
    const areaId = document.getElementById('sgGrowthLASelect')?.value || ''; const host = document.getElementById('sgGrowthChart');
    const rows = this.state.trend.filter((x) => !areaId || String(x.subject_id) === areaId);
    if (host) host.innerHTML = rows.length ? `<div class="table-responsive"><table class="table table-sm"><thead><tr><th>Year</th><th>Term</th><th>Learning Area</th><th>Overall</th><th>Grade</th></tr></thead><tbody>${rows.map((x) => `<tr><td>${this.esc(x.year || '—')}</td><td>${this.esc(x.term_name || '—')}</td><td>${this.esc(x.subject_name || '—')}</td><td>${x.overall_percentage == null ? '—' : `${Number(x.overall_percentage).toFixed(1)}%`}</td><td>${this.esc(x.overall_grade || '—')}</td></tr>`).join('')}</tbody></table></div>` : '<span class="text-muted">No term-over-term trend is available.</span>';
  },
  backToSearch() { document.getElementById('sgProfileView').style.display = 'none'; document.getElementById('sgSearchView').style.display = ''; },
  text(id, value) { const el = document.getElementById(id); if (el) el.textContent = String(value ?? '—'); },
  setHtml(id, value) { const el = document.getElementById(id); if (el) el.innerHTML = value; },
  loadingRow(cols) { return `<tr><td colspan="${cols}" class="text-center py-4"><span class="spinner-border spinner-border-sm"></span></td></tr>`; },
  date(value) { if (!value) return '—'; const d = new Date(String(value).replace(' ', 'T')); return Number.isNaN(d.getTime()) ? this.esc(value) : d.toLocaleDateString(); },
};
window.studentGrowthController = studentGrowthController;
window.studentGrowthCtrl = studentGrowthController;
document.addEventListener('DOMContentLoaded', () => studentGrowthController.init().catch((error) => {
  console.error('Student growth page failed to initialize:', error);
  const host = document.getElementById('sgSearchResults'); if (host) host.innerHTML = '<div class="alert alert-danger">Student growth data could not be initialized. Refresh and try again.</div>';
}));
