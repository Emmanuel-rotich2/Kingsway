/** Term report release status and generation controller. */
const termReportsCtrl = {
  years: [],
  terms: [],
  classes: [],
  students: [],
  releases: [],
  initialized: false,
  loading: false,

  esc(value) {
    const node = document.createElement('span');
    node.textContent = String(value ?? '');
    return node.innerHTML;
  },

  array(response) {
    const value = response?.data?.data ?? response?.data ?? response;
    return Array.isArray(value) ? value : [];
  },

  async init() {
    if (this.initialized) return;
    this.initialized = true;
    await window.AuthContext?.ready?.();
    if (window.AuthContext && !window.AuthContext.isAuthenticated()) {
      window.location.href = `${window.APP_BASE || ''}/index.php`;
      return;
    }
    this.bindEvents();
    try {
      const [yearsResponse, classesResponse] = await Promise.all([
        window.API.academic.getAllAcademicYears(),
        window.API.academic.listClasses(),
      ]);
      this.years = this.array(yearsResponse);
      this.classes = this.array(classesResponse);
      this.populateYears();
      this.populateClasses();
      const currentYear = this.years.find((year) => year.is_current || year.status === 'active');
      if (currentYear) document.getElementById('academicYear').value = String(currentYear.id);
      await this.loadTerms();
    } catch (error) {
      console.error('Term reports initialization failed:', error);
      this.showError(error.message || 'Could not initialize term reports.');
      this.renderError(error.message || 'Could not load term reports.');
    }
  },

  bindEvents() {
    document.getElementById('academicYear')?.addEventListener('change', () => this.loadTerms());
    document.getElementById('term')?.addEventListener('change', () => this.loadReports());
    document.getElementById('classFilter')?.addEventListener('change', () => this.loadReports());
    document.getElementById('selectAll')?.addEventListener('change', (event) => {
      document.querySelectorAll('#reportsTbody [data-student-select]').forEach((box) => { box.checked = event.target.checked; });
    });
    document.getElementById('generateReports')?.addEventListener('click', () => this.generateReports());
    document.getElementById('bulkPrint')?.addEventListener('click', () => this.bulkPrint());
  },

  populateYears() {
    const select = document.getElementById('academicYear');
    if (!select) return;
    select.innerHTML = '<option value="">Select Year</option>' + this.years.map((year) =>
      `<option value="${Number(year.id)}">${this.esc(year.year_name || year.name || year.year_code)}</option>`,
    ).join('');
  },

  populateClasses() {
    const select = document.getElementById('classFilter');
    if (!select) return;
    select.innerHTML = '<option value="">All Classes</option>' + this.classes.map((item) =>
      `<option value="${Number(item.id)}">${this.esc(item.name || item.class_name)}</option>`,
    ).join('');
  },

  async loadTerms() {
    const yearId = document.getElementById('academicYear')?.value;
    const select = document.getElementById('term');
    if (!select) return;
    select.innerHTML = '<option value="">Loading terms…</option>';
    if (!yearId) {
      select.innerHTML = '<option value="">Select Term</option>';
      this.students = []; this.releases = []; this.render();
      return;
    }
    try {
      const response = await window.API.academic.listTerms({ academic_year_id: yearId });
      this.terms = this.array(response);
      select.innerHTML = '<option value="">Select Term</option>' + this.terms.map((term) =>
        `<option value="${Number(term.id)}">${this.esc(term.name || term.term_name)}${term.status === 'current' ? ' (Current)' : ''}</option>`,
      ).join('');
      const current = this.terms.find((term) => term.status === 'current' || term.is_current);
      if (current) select.value = String(current.id);
      await this.loadReports();
    } catch (error) {
      console.error('Could not load terms for report cards:', error);
      select.innerHTML = '<option value="">Terms unavailable</option>';
      this.showError(error.message || 'Could not load terms.');
    }
  },

  async loadStudents(yearId, classId) {
    const filters = { context: 'academic', academic_year_id: yearId, enrolled_only: true, limit: 100 };
    if (classId) filters.class_id = classId;
    const first = await window.API.students.contextList(filters);
    const payload = first?.data?.data ?? first?.data ?? first ?? {};
    const students = Array.isArray(payload.students) ? payload.students : (Array.isArray(payload) ? payload : []);
    const pages = Math.max(1, Number(payload.pagination?.total_pages || 1));
    if (pages > 1) {
      const responses = await Promise.all(Array.from({ length: pages - 1 }, (_, index) =>
        window.API.students.contextList({ ...filters, page: index + 2 }),
      ));
      responses.forEach((response) => {
        const data = response?.data?.data ?? response?.data ?? response ?? {};
        if (Array.isArray(data.students)) students.push(...data.students);
      });
    }
    return students;
  },

  async loadReports() {
    if (this.loading) return;
    const yearId = document.getElementById('academicYear')?.value;
    const termId = document.getElementById('term')?.value;
    const classId = document.getElementById('classFilter')?.value;
    const body = document.getElementById('reportsTbody');
    if (!yearId || !termId) {
      this.students = []; this.releases = []; this.render();
      if (body) body.innerHTML = '<tr><td colspan="10" class="tr-empty">Select an academic year and term to view report card status.</td></tr>';
      return;
    }
    this.loading = true;
    if (body) body.innerHTML = '<tr><td colspan="10" class="text-center py-4"><span class="spinner-border spinner-border-sm me-2"></span>Loading report card status…</td></tr>';
    try {
      const [students, releasesResponse] = await Promise.all([
        this.loadStudents(yearId, classId),
        window.API.apiCall('/academic/report-card-releases', 'GET', null, { term_id: termId }),
      ]);
      this.students = students;
      this.releases = this.array(releasesResponse);
      this.render();
    } catch (error) {
      console.error('Could not load report card status:', error);
      this.renderError(error.message || 'Could not load report card status.');
    } finally {
      this.loading = false;
    }
  },

  render() {
    const selectedClass = document.getElementById('classFilter')?.value;
    const students = selectedClass ? this.students.filter((student) => Number(student.class_id) === Number(selectedClass)) : this.students;
    const releases = new Map(this.releases.map((release) => [Number(release.student_id), release]));
    const rows = students.map((student) => ({ student, release: releases.get(Number(student.id)) || null }));
    const set = (id, value) => { const element = document.getElementById(id); if (element) element.textContent = String(value); };
    set('totalStudents', students.length);
    set('reportsGenerated', rows.filter(({ release }) => release).length);
    set('reportsPrinted', rows.filter(({ release }) => ['released', 'approved'].includes(String(release?.status || '').toLowerCase())).length);
    set('pendingRemarks', '—');
    set('trMeta', `${rows.length} learners · ${rows.filter(({ release }) => release).length} report cards generated`);
    set('trPgMeta', `Showing ${rows.length} learners`);
    const body = document.getElementById('reportsTbody');
    if (!body) return;
    if (!rows.length) {
      body.innerHTML = '<tr><td colspan="10" class="tr-empty">No enrolled learners found for this academic context.</td></tr>';
      return;
    }
    body.innerHTML = rows.map(({ student, release }) => {
      const percentage = Number(release?.overall_percentage);
      const grade = Number.isFinite(percentage) ? (window.GradingScale?.grade?.(percentage) || '—') : '—';
      const status = release?.status || 'not_generated';
      const label = status === 'not_generated' ? 'Not Generated' : String(status).replaceAll('_', ' ');
      const colour = status === 'released' ? 'success' : status === 'approved' ? 'primary' : status === 'pending_approval' ? 'warning text-dark' : 'secondary';
      const name = student.full_name || [student.first_name, student.middle_name, student.last_name].filter(Boolean).join(' ');
      const download = release?.download_url ? `<a class="btn btn-sm btn-outline-primary" href="${this.esc(release.download_url)}" target="_blank" rel="noopener">Open</a>` : '—';
      return `<tr><td><input type="checkbox" data-student-select value="${Number(student.id)}" ${release ? '' : 'disabled'} aria-label="Select ${this.esc(name)}"></td><td>${this.esc(name || '—')}</td><td>${this.esc(student.admission_no || '—')}</td><td>${this.esc(student.class_name || '—')}</td><td>${this.esc(student.stream_name || '—')}</td><td>${grade}</td><td>${Number.isFinite(percentage) ? `${percentage.toFixed(1)}%` : '—'}</td><td>${release?.class_position ?? '—'}</td><td><span class="badge bg-${colour}">${this.esc(label)}</span></td><td>${download}</td></tr>`;
    }).join('');
  },

  renderError(message) {
    const body = document.getElementById('reportsTbody');
    if (body) body.innerHTML = `<tr><td colspan="10" class="text-center text-danger py-4">${this.esc(message)}</td></tr>`;
  },

  async generateReports() {
    const termId = Number(document.getElementById('term')?.value || 0);
    const classId = Number(document.getElementById('classFilter')?.value || 0);
    if (!termId || !classId) {
      this.showError('Select an academic term and class before preparing report cards.');
      return;
    }
    try {
      const response = await window.API.apiCall('/academic/reports/generate-student-reports', 'POST', { term_id: termId, class_id: classId });
      const data = response?.data || response || {};
      this.showMessage(`Prepared ${Number(data.generated_count || 0)} report cards; ${Number(data.failed_count || 0)} need review.`, 'success');
      await this.loadReports();
    } catch (error) {
      this.showError(error.message || 'Could not prepare report cards.');
    }
  },

  bulkPrint() {
    const selectedIds = new Set(Array.from(document.querySelectorAll('#reportsTbody [data-student-select]:checked')).map((box) => Number(box.value)));
    const links = Array.from(document.querySelectorAll('#reportsTbody a[href]')).filter((link) => {
      const row = link.closest('tr');
      return selectedIds.has(Number(row?.querySelector('[data-student-select]')?.value));
    });
    if (!links.length) { this.showError('Select learners with approved or released report cards first.'); return; }
    if (window.AuthContext?.canPrint && !window.AuthContext.canPrint()) { this.showError('You do not have permission to print reports.'); return; }
    links.forEach((link) => { const download = document.createElement('a'); download.href = link.href; download.target = '_blank'; download.rel = 'noopener'; download.click(); });
  },

  exportCSV() {
    if (window.AuthContext?.canExport && !window.AuthContext.canExport()) { this.showError('You do not have permission to export reports.'); return; }
    const rows = Array.from(document.querySelectorAll('#reportsTbody tr')).filter((row) => row.querySelector('[data-student-select]'));
    if (!rows.length) { this.showError('There are no report rows to export.'); return; }
    const csv = [['Student', 'Admission No', 'Class', 'Stream', 'CBC Grade', 'Overall %', 'Position', 'Status'], ...rows.map((row) => Array.from(row.cells).slice(1, 9).map((cell) => cell.textContent.trim()))]
      .map((row) => row.map((value) => `"${String(value ?? '').replaceAll('"', '""')}"`).join(',')).join('\r\n');
    window.KingswayFileLifecycle?.exportText?.(csv, 'term_report_card_status.csv', 'text/csv;charset=utf-8;');
  },

  showMessage(message, type = 'info') {
    const toast = document.getElementById('trToast');
    const body = document.getElementById('trToastBody');
    if (!toast || !body || !window.bootstrap?.Toast) { window.showNotification?.(message, type); return; }
    body.textContent = message;
    toast.className = `toast align-items-center border-0 text-bg-${type}`;
    window.bootstrap.Toast.getOrCreateInstance(toast).show();
  },

  showError(message) { this.showMessage(message, 'danger'); },
};

window.termReportsCtrl = termReportsCtrl;
window.termReportsController = termReportsCtrl;
document.addEventListener('DOMContentLoaded', () => termReportsCtrl.init().catch((error) => {
  console.error('Term reports initialization failed:', error);
  termReportsCtrl.renderError('Could not initialize term reports.');
}));
