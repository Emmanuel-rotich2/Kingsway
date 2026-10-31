/** Read-only view of the current intern's academic assignments. */
const internAssignedSubjectsController = {
  assignments: [],
  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),
  esc(value) { return String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' })[c]); },
  rows(response) { const value = response?.data?.data ?? response?.data ?? response ?? []; return Array.isArray(value) ? value : []; },
  async load() {
    const body = document.getElementById('iasAssignmentsBody');
    const error = document.getElementById('iasError'); error?.classList.add('d-none');
    if (body) body.innerHTML = '<tr><td colspan="7" class="text-center py-4">Loading assigned learning areas…</td></tr>';
    try {
      const response = await this.API('GET', '/api/academic/intern-subjects');
      this.assignments = this.rows(response);
      this.render();
    } catch (exception) {
      if (body) body.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-4">Assignments could not be loaded.</td></tr>';
      if (error) { error.textContent = exception?.message || 'Assignments could not be loaded.'; error.classList.remove('d-none'); }
    }
  },
  render() {
    const body = document.getElementById('iasAssignmentsBody');
    if (body) body.innerHTML = this.assignments.length ? this.assignments.map((row, index) => `<tr>
      <td>${index + 1}</td><td class="fw-semibold">${this.esc(row.subject_name || '—')}</td>
      <td>${this.esc(row.learning_area || '—')}</td><td>${Number(row.classes_count || 0)}</td>
      <td>${this.esc(row.teacher_name || '—')}</td><td>—</td>
      <td><span class="badge bg-${String(row.status || '').toLowerCase() === 'intern' ? 'info' : 'success'}">${this.esc(row.status || 'Assigned')}</span></td>
    </tr>`).join('') : '<tr><td colspan="7" class="text-center py-4 text-muted">No learning areas are assigned for the current academic year.</td></tr>';
    const distinct = new Set(this.assignments.map((row) => row.subject_id)).size;
    const classes = this.assignments.reduce((total, row) => total + Number(row.classes_count || 0), 0);
    const set = (id, value) => { const el = document.getElementById(id); if (el) el.textContent = value; };
    set('totalSubjectsCount', distinct); set('classesCount', classes); set('hoursPerWeekCount', '—'); set('syllabusProgress', '—');
  },
  refresh() { return this.load(); },
  exportSubjects() {
    if (window.AuthContext?.canExport && !window.AuthContext.canExport('academic')) return window.showNotification?.('You do not have permission to export.', 'error');
    const quote = (value) => `"${String(value ?? '').replace(/"/g, '""')}"`;
    const rows = [['Subject','Learning Area','Classes','Teacher','Status'], ...this.assignments.map((row) => [row.subject_name,row.learning_area,row.classes_count,row.teacher_name,row.status])];
    window.KingswayFileLifecycle?.exportText?.(rows.map((row) => row.map(quote).join(',')).join('\r\n'), 'intern-assigned-learning-areas.csv', 'text/csv;charset=utf-8;');
  },
  init() {
    document.getElementById('iasPrint')?.addEventListener('click', () => { if (!window.AuthContext?.canPrint || window.AuthContext.canPrint('academic')) window.print(); });
    document.getElementById('academicYearSelect')?.closest('.row')?.classList.add('d-none');
    window.APIRealtime?.register?.('intern_assigned_subjects', { refresh: () => this.load() }, ['academic']);
    return this.load();
  }
};
window.internAssignedSubjectsController = internAssignedSubjectsController;
window.InternAssignedSubjectsController = internAssignedSubjectsController;
document.addEventListener('DOMContentLoaded', () => internAssignedSubjectsController.init());
