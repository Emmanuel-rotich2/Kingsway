/**
 * Student Fund Transfers Controller
 * Handles fund transfers between student accounts.
 */
const studentFundTransfersController = {
  state: { referenceCache: null, transfers: [], sources: [], students: [] },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("students", "student-fund-transfers-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Student Fund Transfers Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async load() {
    try {
      const cache = this.state.referenceCache;
      const [transfers, sources, students] = await Promise.all([
        cache?.transfers !== undefined ? cache.transfers : this.API('GET', '/api/finance/student-fund-transfers'),
        cache?.sources !== undefined ? cache.sources : this.API('GET', '/api/finance/student-fund-sources'),
        cache?.students !== undefined ? cache.students : this.API('GET', '/api/students/get-all', { limit: '1000', status: 'active' }),
      ]);
      this.state.transfers = Array.isArray(transfers?.data) ? transfers.data : (Array.isArray(transfers) ? transfers : []);
      this.state.sources = Array.isArray(sources?.data) ? sources.data : (Array.isArray(sources) ? sources : []);
      this.state.students = Array.isArray(students?.data) ? students.data : (Array.isArray(students) ? students : []);
      this.renderTransfers();
      this.renderFormOptions();
    } catch (e) { console.error(e); }
  },

  getCache(key) { return this.state.referenceCache?.[key]; },

  renderTransfers() {
    const transfers = this.state.transfers;
    const body = document.getElementById('sftTransfersBody');
    if (!transfers.length) { body.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No transfers recorded.</td></tr>'; return; }
    body.innerHTML = transfers.map(t => `<tr>
      <td class="small">${this.fmtDate(t.date)}</td>
      <td class="small">${this.esc(t.from_student || '—')}</td>
      <td class="small">${this.esc(t.to_student || '—')}</td>
      <td class="small">${this.fmtKES(t.amount)}</td>
      <td class="small">${this.esc(t.source || '—')}</td>
      <td class="small">${this.esc(t.reference || '—')}</td>
    </tr>`).join('');
  },

  renderFormOptions() {
    const sources = this.state.sources;
    const students = this.state.students;
    const fromSelect = document.getElementById('sftFromStudent');
    const toSelect = document.getElementById('sftToStudent');
    const sourceSelect = document.getElementById('sftSource');
    if (fromSelect) fromSelect.innerHTML = '<option value="">Select Student</option>' + students.map(s => `<option value="${s.id}">${this.esc(s.name)} (${this.esc(s.admission_number)})</option>`).join('');
    if (toSelect) toSelect.innerHTML = '<option value="">Select Student</option>' + students.map(s => `<option value="${s.id}">${this.esc(s.name)} (${this.esc(s.admission_number)})</option>`).join('');
    if (sourceSelect) sourceSelect.innerHTML = '<option value="">Select Source</option>' + sources.map(s => `<option value="${s.id}">${this.esc(s.name)}</option>`).join('');
  },

  fmtDate(v) { if (!v) return '—'; const dt = new Date(String(v).replace(' ', 'T')); return isNaN(dt) ? this.esc(v) : dt.toLocaleDateString(); },
  fmtKES(n) { return 'KES ' + Number(n || 0).toLocaleString('en-KE', { minimumFractionDigits: 2 }); },

  async submitTransfer() {
    const from = document.getElementById('sftFromStudent')?.value;
    const to = document.getElementById('sftToStudent')?.value;
    const source = document.getElementById('sftSource')?.value;
    const amount = document.getElementById('sftAmount')?.value;
    const ref = document.getElementById('sftReference')?.value;
    if (!from || !to || !source || !amount) { this.notify('All fields required', 'warning'); return; }
    try {
      await this.API('POST', '/api/finance/student-fund-transfer', { from_student_id: from, to_student_id: to, source_id: source, amount, reference: ref });
      this.notify('Transfer completed', 'success');
      document.getElementById('sftForm')?.reset();
      await this.load();
    } catch (e) { this.notify('Transfer failed', 'danger'); }
  },

  notify(msg, type = 'info') { if (window.showNotification) window.showNotification(msg, type); else if (window.showToast) window.showToast(msg, type); else alert(msg); },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.loadReferencesComposed();
    await this.load();
    window.APIRealtime?.register?.("student_fund_transfers", {
      refresh: async () => { this.state.referenceCache = null; await this.loadReferencesComposed(); await this.load(); }
    }, ["students", "finance"]);
  },
};

window.studentFundTransfersController = studentFundTransfersController;
document.addEventListener('DOMContentLoaded', () => studentFundTransfersController.init().catch(() => {}));