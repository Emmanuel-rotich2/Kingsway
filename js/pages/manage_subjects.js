/** Read-only canonical learning-area register. Changes use curriculum proposals. */
const manageSubjectsController = {
  rows: [],
  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),
  esc(value) {
    return String(value ?? '').replace(/[&<>"']/g, (char) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[char]);
  },
  unwrap(response) {
    let value = response;
    for (let i = 0; i < 3 && value && !Array.isArray(value); i += 1) {
      value = value.data ?? value.result ?? value;
    }
    return Array.isArray(value) ? value : [];
  },
  async load() {
    const body = document.getElementById('msSubjectsBody');
    const error = document.getElementById('msError');
    if (error) error.classList.add('d-none');
    if (body) body.innerHTML = '<tr><td colspan="4" class="text-center py-4">Loading learning areas…</td></tr>';
    try {
      const response = await this.API('GET', '/api/academic/learning-areas/list');
      this.rows = this.unwrap(response);
      this.render();
    } catch (exception) {
      if (body) body.innerHTML = '<tr><td colspan="4" class="text-center text-danger py-4">Learning areas could not be loaded.</td></tr>';
      if (error) { error.textContent = exception?.message || 'Learning areas could not be loaded.'; error.classList.remove('d-none'); }
    }
  },
  render() {
    const body = document.getElementById('msSubjectsBody');
    const search = (document.getElementById('searchSubjects')?.value || '').trim().toLowerCase();
    const status = document.getElementById('subjectStatusFilter')?.value || '';
    const rows = this.rows.filter((row) => {
      const active = String(row.status || '').toLowerCase() === 'active';
      return (!search || `${row.name || ''} ${row.code || ''}`.toLowerCase().includes(search))
        && (!status || (status === 'active') === active);
    });
    if (body) body.innerHTML = rows.length ? rows.map((row, index) => {
      const active = String(row.status || '').toLowerCase() === 'active';
      return `<tr><td>${index + 1}</td><td>${this.esc(row.name || '—')}</td><td>${this.esc(row.code || '—')}</td><td><span class="badge bg-${active ? 'success' : 'secondary'}">${this.esc(row.status || 'Unknown')}</span></td></tr>`;
    }).join('') : '<tr><td colspan="4" class="text-center py-4 text-muted">No matching learning areas.</td></tr>';
    const activeCount = this.rows.filter((row) => String(row.status || '').toLowerCase() === 'active').length;
    const total = document.getElementById('totalSubjectsCount'); if (total) total.textContent = this.rows.length;
    const core = document.getElementById('coreSubjectsCount'); if (core) core.textContent = activeCount;
    const optional = document.getElementById('optionalSubjectsCount'); if (optional) optional.textContent = this.rows.length - activeCount;
    const teachers = document.getElementById('teachersAssignedCount'); if (teachers) teachers.textContent = '—';
    const from = document.getElementById('subjShowingFrom'); if (from) from.textContent = rows.length ? '1' : '0';
    const to = document.getElementById('subjShowingTo'); if (to) to.textContent = rows.length;
    const count = document.getElementById('subjTotalRecords'); if (count) count.textContent = rows.length;
    const pagination = document.getElementById('subjectsPagination'); if (pagination) pagination.replaceChildren();
  },
  exportSubjects() {
    if (window.AuthContext?.canExport && !window.AuthContext.canExport('academic')) return window.showNotification?.('You do not have permission to export.', 'warning');
    const rows = this.rows.map((row) => [row.name || '', row.code || '', row.status || '']);
    const quote = (value) => `"${String(value ?? '').replace(/"/g, '""')}"`;
    const csv = [['Name', 'Code', 'Status'], ...rows].map((row) => row.map(quote).join(',')).join('\r\n');
    window.KingswayFileLifecycle?.exportText?.(csv, 'learning-areas.csv', 'text/csv;charset=utf-8;');
  },
  init() {
    document.getElementById('searchSubjects')?.addEventListener('input', () => this.render());
    document.getElementById('subjectStatusFilter')?.addEventListener('change', () => this.render());
    document.getElementById('categoryFilter')?.closest('.col-md-3')?.classList.add('d-none');
    document.getElementById('levelFilter')?.closest('.col-md-3')?.classList.add('d-none');
    document.getElementById('printSubjects')?.addEventListener('click', () => {
      if (!window.AuthContext?.canPrint || window.AuthContext.canPrint('academic')) window.print();
    });
    window.APIRealtime?.register?.('manage_subjects', { refresh: () => this.load() }, ['academic']);
    return this.load();
  }
};
window.manageSubjectsController = manageSubjectsController;
window.ManageSubjectsController = manageSubjectsController;
document.addEventListener('DOMContentLoaded', () => manageSubjectsController.init());
