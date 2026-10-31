/** Role-gated counseling case dashboard. */
const studentCounselingController = {
  state: { cases: [], request: 0 },
  api(method, endpoint, data, params) { return window.callAPI(endpoint, method, data, params); },
  esc(value) { return String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]); },
  rows(response) { return Array.isArray(response?.data) ? response.data : (Array.isArray(response) ? response : []); },
  val(id) { return document.getElementById(id)?.value || ''; },
  show(id, visible) { document.getElementById(id)?.classList.toggle('d-none', !visible); },
  text(id, value) { const el = document.getElementById(id); if (el) el.textContent = String(value); },
  async loadMeta() {
    const response = await this.api('GET', '/api/students/counseling-meta');
    const meta = response?.data || response || {};
    const fill = (id, rows, label) => {
      const select = document.getElementById(id); if (!select) return;
      const initial = select.options[0]?.outerHTML || '<option value="">All</option>';
      select.innerHTML = initial + (rows || []).map((row) => `<option value="${Number(row.id)}">${this.esc(label(row))}</option>`).join('');
    };
    fill('academicYearFilter', meta.academic_years, (x) => x.year_name || x.year_code || '');
    fill('classFilter', meta.classes, (x) => x.name || '');
    fill('streamFilter', meta.streams, (x) => x.stream_name || x.name || '');
    const termFilter = document.getElementById('termFilter');
    if (termFilter && Array.isArray(meta.terms)) termFilter.innerHTML = '<option value="">All Terms</option>' + meta.terms.map((x) => `<option value="${Number(x.id)}">${this.esc(x.name)}</option>`).join('');
    // The case list service does not currently accept term_id; avoid presenting a filter that appears to work but is ignored.
    if (termFilter) termFilter.closest('.col-xl-2')?.classList.add('d-none');
  },
  async load() {
    const request = ++this.state.request;
    this.show('casesLoading', true); this.show('casesError', false); this.show('casesEmpty', false);
    const params = {};
    const mapping = { academic_year_id: 'academicYearFilter', class_id: 'classFilter', stream_id: 'streamFilter', case_type: 'caseTypeFilter', priority: 'priorityFilter', status: 'statusFilter', gender: 'genderFilter', search: 'searchBox' };
    Object.entries(mapping).forEach(([key, id]) => { const value = this.val(id).trim(); if (value) params[key] = value; });
    try {
      const response = await this.api('GET', '/api/students/counseling-cases', null, params);
      if (request !== this.state.request) return;
      if (response?.success === false || response?.error) throw new Error(response.message || response.error || 'Could not load counseling cases.');
      this.state.cases = this.rows(response);
      this.render();
    } catch (error) {
      if (request !== this.state.request) return;
      const box = document.getElementById('casesError'); if (box) { box.textContent = error?.message || 'Could not load counseling cases. Please refresh and try again.'; box.classList.remove('d-none'); }
      const body = document.getElementById('casesTableBody'); if (body) body.innerHTML = '<tr><td colspan="12" class="text-center text-danger py-4">Counseling cases could not be loaded.</td></tr>';
    } finally { if (request === this.state.request) this.show('casesLoading', false); }
  },
  render() {
    const rows = this.state.cases; const body = document.getElementById('casesTableBody');
    if (!body) return;
    if (!rows.length) { body.innerHTML = ''; this.show('casesEmpty', true); }
    else {
      this.show('casesEmpty', false);
      body.innerHTML = rows.map((item) => `<tr>
        <td>${this.esc(item.case_code || item.id)}</td><td class="fw-semibold">${this.esc(item.student_name || '—')}</td><td>${this.esc(item.admission_no || '—')}</td>
        <td>${this.esc(item.class_name || '—')}</td><td>${this.esc(item.stream_name || '—')}</td><td>${this.esc(item.case_type || '—')}</td>
        <td>${this.esc(item.priority || '—')}</td><td>${this.esc(item.status || '—')}</td><td>${this.esc(item.counselor_name || '—')}</td>
        <td>${this.date(item.last_session)}</td><td>${this.date(item.next_follow_up_at)}</td><td><button type="button" class="btn btn-sm btn-outline-primary" data-case-id="${Number(item.id)}">View</button></td>
      </tr>`).join('');
    }
    const n = (fn) => rows.filter(fn).length;
    this.text('totalCases', rows.length); this.text('openCases', n((x) => ['open', 'in_progress'].includes(String(x.status).toLowerCase())));
    this.text('followUpsDue', n((x) => x.next_follow_up_at && new Date(x.next_follow_up_at) <= new Date()));
    this.text('resolvedCases', n((x) => ['resolved', 'closed'].includes(String(x.status).toLowerCase())));
    this.text('highPriorityCases', n((x) => ['high', 'urgent'].includes(String(x.priority).toLowerCase())));
    this.text('thisTermCases', '—');
  },
  date(value) { if (!value) return '—'; const date = new Date(String(value).replace(' ', 'T')); return Number.isNaN(date.getTime()) ? this.esc(value) : date.toLocaleDateString(); },
  exportCSV() {
    if (window.AuthContext?.canExport && !window.AuthContext.canExport('counseling')) return window.showNotification?.('You do not have permission to export counseling data.', 'error');
    const columns = ['case_code', 'student_name', 'admission_no', 'class_name', 'stream_name', 'case_type', 'priority', 'status', 'counselor_name', 'last_session', 'next_follow_up_at'];
    const headers = ['Case ID', 'Student', 'Admission No', 'Class', 'Stream', 'Case Type', 'Priority', 'Status', 'Counselor', 'Last Session', 'Next Follow-up'];
    const quote = (v) => `"${String(v ?? '').replace(/"/g, '""')}"`;
    const csv = [headers, ...this.state.cases.map((row) => columns.map((key) => row[key] ?? ''))].map((row) => row.map(quote).join(',')).join('\r\n');
    window.KingswayFileLifecycle?.exportText?.(csv, 'counseling-cases.csv', 'text/csv;charset=utf-8');
  },
  print() { if (window.AuthContext?.canPrint && !window.AuthContext.canPrint('counseling')) return window.showNotification?.('You do not have permission to print counseling data.', 'error'); window.print(); },
  async viewCase(id) {
    const modal = document.getElementById('caseModal'); if (!modal || !window.bootstrap?.Modal) return;
    this.text('modalCaseId', id); this.show('modalLoading', true); this.show('modalError', false); window.bootstrap.Modal.getOrCreateInstance(modal).show();
    try {
      const response = await this.api('GET', `/api/students/counseling-case/${Number(id)}`); const data = response?.data || response || {};
      if (response?.success === false || response?.error) throw new Error(response.message || 'Could not load case details.');
      const item = data.case || data; const host = document.getElementById('modalCaseContent');
      if (host) host.innerHTML = `<dl class="row"><dt class="col-sm-3">Student</dt><dd class="col-sm-9">${this.esc(item.student_name || '—')} (${this.esc(item.admission_no || '—')})</dd><dt class="col-sm-3">Case title</dt><dd class="col-sm-9">${this.esc(item.title || '—')}</dd><dt class="col-sm-3">Type / priority</dt><dd class="col-sm-9">${this.esc(item.case_type || '—')} / ${this.esc(item.priority || '—')}</dd><dt class="col-sm-3">Status</dt><dd class="col-sm-9">${this.esc(item.status || '—')}</dd><dt class="col-sm-3">Referral</dt><dd class="col-sm-9">${this.esc(item.referral_source || '—')}</dd></dl>`;
    } catch (error) { const box = document.getElementById('modalError'); if (box) { box.textContent = error?.message || 'Could not load case details.'; box.classList.remove('d-none'); } }
    finally { this.show('modalLoading', false); }
  },
  bind() {
    document.getElementById('applyFiltersBtn')?.remove();
    document.getElementById('addCaseBtn')?.classList.add('d-none');
    ['addSessionBtn', 'scheduleFollowUpBtn', 'closeCaseBtn'].forEach((id) => document.getElementById(id)?.classList.add('d-none'));
    document.getElementById('refreshBtn')?.addEventListener('click', () => this.load());
    document.getElementById('exportBtn')?.addEventListener('click', () => this.exportCSV());
    document.getElementById('printBtn')?.addEventListener('click', () => this.print());
    document.getElementById('casesTableBody')?.addEventListener('click', (event) => { const btn = event.target.closest('[data-case-id]'); if (btn) this.viewCase(btn.dataset.caseId); });
    ['academicYearFilter', 'classFilter', 'streamFilter', 'caseTypeFilter', 'priorityFilter', 'statusFilter', 'genderFilter'].forEach((id) => document.getElementById(id)?.addEventListener('change', () => this.load()));
    let timer; document.getElementById('searchBox')?.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(() => this.load(), 250); });
    document.getElementById('resetFiltersBtn')?.addEventListener('click', () => { ['academicYearFilter', 'classFilter', 'streamFilter', 'caseTypeFilter', 'priorityFilter', 'statusFilter', 'genderFilter', 'searchBox'].forEach((id) => { const el = document.getElementById(id); if (el) el.value = ''; }); this.load(); });
  },
  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready(); this.bind();
    try { await this.loadMeta(); } catch (error) { const box = document.getElementById('casesError'); if (box) { box.textContent = error?.message || 'Counseling data access is unavailable for this account.'; box.classList.remove('d-none'); } }
    await this.load(); window.APIRealtime?.register?.('student_counseling', { refresh: () => this.load() }, ['students', 'counseling']);
  },
};
window.studentCounselingController = studentCounselingController;
document.addEventListener('DOMContentLoaded', () => studentCounselingController.init().catch((error) => console.error('Counseling page failed to initialize:', error)));
