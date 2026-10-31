/** Student health records dashboard. */
const studentHealthController = {
  state: { records: [], loading: false, request: 0 },

  api(method, endpoint, data, params) { return window.callAPI(endpoint, method, data, params); },
  esc(value) {
    return String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char]);
  },
  value(id) { return document.getElementById(id)?.value || ''; },
  rows(response) { return Array.isArray(response?.data) ? response.data : (Array.isArray(response) ? response : []); },

  async loadMeta() {
    const [metaResponse, classesResponse] = await Promise.all([
      this.api('GET', '/api/students/health-meta'),
      this.api('GET', '/api/academic/classes-list'),
    ]);
    const meta = metaResponse?.data || metaResponse || {};
    const years = Array.isArray(meta.academic_years) ? meta.academic_years : [];
    const classes = this.rows(classesResponse);
    const yearFilter = document.getElementById('academicYearFilter');
    const classFilter = document.getElementById('classFilter');
    const streamFilter = document.getElementById('streamFilter');
    if (yearFilter) yearFilter.innerHTML = '<option value="">All Years</option>' + years.map((year) => `<option value="${Number(year.id)}" ${year.is_current ? 'selected' : ''}>${this.esc(year.year_name || year.year_code || year.name)}</option>`).join('');
    if (classFilter) classFilter.innerHTML = '<option value="">All Classes</option>' + classes.map((item) => `<option value="${Number(item.id)}">${this.esc(item.name || item.class_name)}</option>`).join('');
    if (streamFilter) streamFilter.innerHTML = '<option value="">All Streams</option>' + (Array.isArray(meta.streams) ? meta.streams : []).map((item) => `<option value="${Number(item.id)}">${this.esc(item.stream_name || item.name)}</option>`).join('');
  },

  async load() {
    const request = ++this.state.request;
    this.state.loading = true;
    this.setState('recordsLoading', true);
    this.setState('recordsError', false);
    this.setState('recordsEmpty', false);
    const params = {};
    const mapping = { academic_year_id: 'academicYearFilter', class_id: 'classFilter', stream_id: 'streamFilter', health_category: 'healthCategoryFilter', alert_status: 'alertStatusFilter', severity: 'severityFilter', search: 'searchBox' };
    Object.entries(mapping).forEach(([key, id]) => { const value = this.value(id).trim(); if (value) params[key] = value; });
    try {
      const response = await this.api('GET', '/api/students/health-records', null, params);
      if (request !== this.state.request) return;
      if (response?.success === false || response?.error) throw new Error(response.message || response.error || 'Could not load health records.');
      this.state.records = this.rows(response);
      this.render();
    } catch (error) {
      if (request !== this.state.request) return;
      const box = document.getElementById('recordsError');
      if (box) { box.textContent = error?.message || 'Could not load health records. Please refresh and try again.'; box.classList.remove('d-none'); }
      const body = document.getElementById('recordsTableBody');
      if (body) body.innerHTML = '<tr><td colspan="12" class="text-center text-danger py-4">Health records could not be loaded.</td></tr>';
    } finally {
      if (request === this.state.request) { this.state.loading = false; this.setState('recordsLoading', false); }
    }
  },

  setState(id, visible) { const el = document.getElementById(id); if (el) el.classList.toggle('d-none', !visible); },
  fmtDate(value) { if (!value) return '—'; const date = new Date(String(value).replace(' ', 'T')); return Number.isNaN(date.getTime()) ? this.esc(value) : date.toLocaleDateString(); },
  render() {
    const records = this.state.records;
    const body = document.getElementById('recordsTableBody');
    const empty = document.getElementById('recordsEmpty');
    if (!body) return;
    if (!records.length) {
      body.innerHTML = '';
      if (empty) empty.classList.remove('d-none');
    } else {
      if (empty) empty.classList.add('d-none');
      body.innerHTML = records.map((record) => {
        const severity = String(record.severity || '').toLowerCase();
        const badge = severity === 'critical' || severity === 'high' ? 'danger' : severity === 'medium' ? 'warning' : 'info';
        return `<tr>
          <td>${Number(record.id) || '—'}</td>
          <td class="fw-semibold">${this.esc(record.student_name || '—')}</td>
          <td>${this.esc(record.admission_no || '—')}</td>
          <td>${this.esc(record.class_name || '—')}</td>
          <td>${this.esc(record.stream_name || '—')}</td>
          <td>${this.esc(record.health_category || '—')}</td>
          <td>${this.esc(record.alert_type || record.condition_name || record.allergy_name || record.medication_name || '—')}</td>
          <td><span class="badge text-bg-${badge}">${this.esc(record.severity || '—')}</span></td>
          <td>${this.esc(record.status || '—')}</td>
          <td>${this.fmtDate(record.last_visit)}</td>
          <td>${this.fmtDate(record.next_review_date)}</td>
          <td><button class="btn btn-sm btn-outline-danger" type="button" data-health-record="${Number(record.id)}">View</button></td>
        </tr>`;
      }).join('');
    }
    const count = (predicate) => records.filter(predicate).length;
    this.text('totalRecords', records.length);
    this.text('activeAlerts', count((r) => ['active', 'monitoring'].includes(String(r.status || '').toLowerCase())));
    this.text('clinicVisits', records.reduce((sum, r) => sum + Number(r.clinic_visits_count || 0), 0));
    this.text('allergiesCount', count((r) => String(r.health_category).toLowerCase() === 'allergy'));
    this.text('medicationCount', count((r) => String(r.health_category).toLowerCase() === 'medication'));
    this.text('emergencyCount', count((r) => Boolean(Number(r.emergency_flag))));
  },
  text(id, value) { const el = document.getElementById(id); if (el) el.textContent = String(value); },

  exportCSV() {
    if (window.AuthContext?.canExport && !window.AuthContext.canExport('health')) return window.showNotification?.('You do not have permission to export health data.', 'error');
    const columns = ['record_code', 'student_name', 'admission_no', 'class_name', 'stream_name', 'health_category', 'alert_type', 'severity', 'status', 'last_visit', 'next_review_date'];
    const header = ['Record ID', 'Student Name', 'Admission No', 'Class', 'Stream', 'Category', 'Alert Type', 'Severity', 'Status', 'Last Visit', 'Next Review'];
    const quote = (value) => `"${String(value ?? '').replace(/"/g, '""')}"`;
    const csv = [header, ...this.state.records.map((record) => columns.map((key) => record[key] ?? ''))].map((row) => row.map(quote).join(',')).join('\r\n');
    if (window.KingswayFileLifecycle?.exportText) return window.KingswayFileLifecycle.exportText(csv, 'student-health-records.csv', 'text/csv;charset=utf-8');
    const link = document.createElement('a'); link.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' })); link.download = 'student-health-records.csv'; link.click(); URL.revokeObjectURL(link.href);
  },
  print() {
    if (window.AuthContext?.canPrint && !window.AuthContext.canPrint('health')) return window.showNotification?.('You do not have permission to print health data.', 'error');
    window.print();
  },
  async viewRecord(id) {
    const modal = document.getElementById('recordModal');
    if (!modal || !window.bootstrap?.Modal) return;
    this.text('modalRecordId', id);
    this.setState('modalLoading', true); this.setState('modalError', false);
    window.bootstrap.Modal.getOrCreateInstance(modal).show();
    try {
      const response = await this.api('GET', `/api/students/health-record/${Number(id)}`);
      const data = response?.data || response || {};
      if (response?.success === false || response?.error) throw new Error(response.message || 'Could not load record details.');
      const record = data.record || {};
      const host = document.getElementById('modalRecordContent');
      if (host) host.innerHTML = `<dl class="row"><dt class="col-sm-3">Student</dt><dd class="col-sm-9">${this.esc(record.student_name || '—')} (${this.esc(record.admission_no || '—')})</dd><dt class="col-sm-3">Category</dt><dd class="col-sm-9">${this.esc(record.health_category || '—')}</dd><dt class="col-sm-3">Alert</dt><dd class="col-sm-9">${this.esc(record.alert_type || record.condition_name || record.allergy_name || record.medication_name || '—')}</dd><dt class="col-sm-3">Description</dt><dd class="col-sm-9">${this.esc(record.description || '—')}</dd><dt class="col-sm-3">Action instructions</dt><dd class="col-sm-9">${this.esc(record.action_instructions || '—')}</dd><dt class="col-sm-3">Next review</dt><dd class="col-sm-9">${this.fmtDate(record.next_review_date)}</dd></dl>`;
    } catch (error) {
      const box = document.getElementById('modalError'); if (box) { box.textContent = error?.message || 'Could not load record details.'; box.classList.remove('d-none'); }
    } finally { this.setState('modalLoading', false); }
  },

  bind() {
    document.getElementById('refreshBtn')?.addEventListener('click', () => this.load());
    document.getElementById('exportBtn')?.addEventListener('click', () => this.exportCSV());
    document.getElementById('printBtn')?.addEventListener('click', () => this.print());
    document.getElementById('applyFiltersBtn')?.remove();
    document.getElementById('addRecordBtn')?.classList.add('d-none');
    ['addClinicVisitBtn', 'addMedicationBtn', 'markReviewedBtn'].forEach((id) => document.getElementById(id)?.classList.add('d-none'));
    document.getElementById('recordsTableBody')?.addEventListener('click', (event) => {
      const button = event.target.closest('[data-health-record]'); if (button) this.viewRecord(button.dataset.healthRecord);
    });
    ['academicYearFilter', 'classFilter', 'streamFilter', 'healthCategoryFilter', 'alertStatusFilter', 'severityFilter'].forEach((id) => document.getElementById(id)?.addEventListener('change', () => this.load()));
    let timer; document.getElementById('searchBox')?.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(() => this.load(), 250); });
    document.getElementById('resetFiltersBtn')?.addEventListener('click', () => {
      ['academicYearFilter', 'classFilter', 'streamFilter', 'healthCategoryFilter', 'alertStatusFilter', 'severityFilter', 'searchBox'].forEach((id) => { const el = document.getElementById(id); if (el) el.value = ''; });
      this.load();
    });
  },
  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    this.bind();
    try { await this.loadMeta(); } catch (error) { const box = document.getElementById('recordsError'); if (box) { box.textContent = error?.message || 'Could not load health filters.'; box.classList.remove('d-none'); } }
    await this.load();
    window.APIRealtime?.register?.('student_health', { refresh: () => this.load() }, ['students', 'health']);
  },
};

window.studentHealthController = studentHealthController;
document.addEventListener('DOMContentLoaded', () => studentHealthController.init().catch((error) => console.error('Student health page failed to initialize:', error)));
