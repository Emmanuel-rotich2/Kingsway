/** Attendance follow-up list from the scoped academic attendance summary. */
const atRiskController = {
  state: { rows: [], range: {} },
  esc(value) { return String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' })[c]); },
  async init() {
    document.getElementById('arExportBtn')?.addEventListener('click', () => this.exportCSV());
    document.getElementById('arPrintBtn')?.addEventListener('click', () => { if (!window.AuthContext?.canPrint || window.AuthContext.canPrint('attendance')) window.print(); });
    await this.load();
    window.APIRealtime?.register?.('at_risk_students', { refresh: () => this.load() }, ['attendance']);
  },
  async load() {
    const host = document.getElementById('arTableContainer'); if (host) host.innerHTML = '<div class="text-center py-4" role="status"><span class="spinner-border spinner-border-sm me-2"></span>Loading attendance follow-up data…</div>';
    try {
      const response = await window.API.attendance.getAcademicSummary();
      const payload = response?.data?.data ?? response?.data ?? response ?? {};
      this.state.rows = Array.isArray(payload.low_attendance) ? payload.low_attendance : [];
      this.state.range = { from: payload.date_from, to: payload.date_to };
      this.render();
    } catch (error) {
      if (host) host.innerHTML = `<div class="alert alert-danger" role="alert">${this.esc(error?.message || 'Attendance follow-up data could not be loaded.')}</div>`;
      const stat = document.getElementById('arStatAbsent'); if (stat) stat.textContent = '—';
    }
  },
  render() {
    const host = document.getElementById('arTableContainer'); if (!host) return;
    const stat = document.getElementById('arStatAbsent'); if (stat) stat.textContent = String(this.state.rows.length);
    const range = document.getElementById('arDateRange'); if (range) range.textContent = `${this.state.range.from || ''} – ${this.state.range.to || ''}`;
    host.innerHTML = `<div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Student</th><th>Admission No.</th><th>Attendance</th><th>Absent / Permission Days</th><th>Last Absence</th></tr></thead><tbody>${this.state.rows.length ? this.state.rows.map((row) => `<tr><td>${this.esc(row.student_name || '—')}</td><td>${this.esc(row.admission_no || '—')}</td><td><span class="badge text-bg-warning">${Number(row.attendance_percentage || 0)}%</span></td><td>${Number(row.absent_days || 0)}</td><td>${this.esc(row.last_absent_date || '—')}</td></tr>`).join('') : '<tr><td colspan="5" class="text-center text-muted py-4">No learners are below the attendance follow-up threshold for this period.</td></tr>'}</tbody></table></div>`;
  },
  exportCSV() {
    if (window.AuthContext?.canExport && !window.AuthContext.canExport('attendance')) return window.showNotification?.('You do not have permission to export attendance data.', 'error');
    const quote = (value) => `"${String(value ?? '').replace(/"/g, '""')}"`;
    const rows = [['Student','Admission No.','Attendance %','Absent / Permission Days','Last Absence'], ...this.state.rows.map((row) => [row.student_name,row.admission_no,row.attendance_percentage,row.absent_days,row.last_absent_date])];
    window.KingswayFileLifecycle?.exportText?.(rows.map((row) => row.map(quote).join(',')).join('\r\n'), 'attendance-follow-up.csv', 'text/csv;charset=utf-8;');
  },
};
window.atRiskController = atRiskController;
document.addEventListener('DOMContentLoaded', () => atRiskController.init());
