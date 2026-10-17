/**
 * Student Progress Reports Controller
 * Generates and manages student progress reports.
 */
const studentProgressReportsController = {
  state: { referenceCache: null, reports: [], student: null },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("students", "student-progress-reports-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Student Progress Reports Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async load(studentId) {
    try {
      const cache = this.state.referenceCache;
      const [reports, student] = await Promise.all([
        cache?.reports !== undefined ? cache.reports : this.API('GET', `/api/students/${studentId}/progress-reports`),
        cache?.student !== undefined ? cache.student : this.API('GET', `/api/students/${studentId}`),
      ]);
      this.state.reports = Array.isArray(reports?.data) ? reports.data : (Array.isArray(reports) ? reports : []);
      this.state.student = student?.data || student || {};
      this.render();
    } catch (e) { console.error(e); }
  },

  getCache(key) { return this.state.referenceCache?.[key]; },

  render() {
    const reports = this.state.reports;
    const student = this.state.student;
    document.getElementById('sprStudentName')?.textContent = student?.name || '—';
    document.getElementById('sprAdmissionNo')?.textContent = student?.admission_number || '—';
    const body = document.getElementById('sprReportsBody');
    if (!reports.length) { body.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">No progress reports.</td></tr>'; return; }
    body.innerHTML = reports.map(r => `<tr>
      <td class="small">${this.esc(r.term || '—')}</td>
      <td class="small">${this.fmtDate(r.generated_date)}</td>
      <td class="small">${this.esc(r.status || '—')}</td>
      <td class="small"><a href="${r.pdf_url}" class="btn btn-sm btn-outline-primary" target="_blank"><i class="bi bi-file-pdf"></i> View</a></td>
      <td><button class="btn btn-sm btn-outline-secondary" onclick="studentProgressReportsController.regenerate(${r.id})"><i class="bi bi-arrow-clockwise"></i></button></td>
    </tr>`).join('');
    this.renderFilters();
  },

  renderFilters() {
    const years = this.getCache('years') || [];
    const terms = this.getCache('terms') || [];
    const classes = this.getCache('classes') || [];
    const yearSelect = document.getElementById('sprYearFilter');
    const termSelect = document.getElementById('sprTermFilter');
    const classSelect = document.getElementById('sprClassFilter');
    if (yearSelect) yearSelect.innerHTML = '<option value="">All Years</option>' + years.map(y => `<option value="${y.id}">${this.esc(y.name)}</option>`).join('');
    if (termSelect) termSelect.innerHTML = '<option value="">All Terms</option>' + terms.map(t => `<option value="${t.id}">${this.esc(t.name)}</option>`).join('');
    if (classSelect) classSelect.innerHTML = '<option value="">All Classes</option>' + classes.map(c => `<option value="${c.id}">${this.esc(c.name)}</option>`).join('');
  },

  fmtDate(v) { if (!v) return '—'; const dt = new Date(String(v).replace(' ', 'T')); return isNaN(dt) ? this.esc(v) : dt.toLocaleDateString(); },

  async regenerate(id) {
    try {
      await this.API('POST', `/api/students/progress-reports/${id}/regenerate`);
      this.notify('Report regeneration started', 'info');
      await this.load(new URLSearchParams(window.location.search).get('student_id'));
    } catch (e) { this.notify('Regeneration failed', 'danger'); }
  },

  notify(msg, type = 'info') { if (window.showNotification) window.showNotification(msg, type); else if (window.showToast) window.showToast(msg, type); else alert(msg); },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    const studentId = new URLSearchParams(window.location.search).get('student_id');
    if (!studentId) return;
    await this.loadReferencesComposed();
    await this.load(studentId);
    window.APIRealtime?.register?.("student_progress_reports", {
      refresh: async () => { this.state.referenceCache = null; await this.loadReferencesComposed(); await this.load(studentId); }
    }, ["students", "reports"]);
  },
};

window.studentProgressReportsController = studentProgressReportsController;
document.addEventListener('DOMContentLoaded', () => studentProgressReportsController.init().catch(() => {}));