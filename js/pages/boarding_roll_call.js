/**
 * Boarding Roll Call Controller
 * Manages daily boarding roll calls and attendance.
 */
const boardingRollCallController = {
  state: { referenceCache: null, rollCalls: [], dormitories: [], sessions: [] },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("boarding", "boarding-rollcall-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Boarding Roll Call Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async load() {
    try {
      const cache = this.state.referenceCache;
      const [rollCalls, dormitories, sessions] = await Promise.all([
        cache?.rollCalls !== undefined ? cache.rollCalls : this.API('GET', '/api/boarding/roll-calls'),
        cache?.dormitories !== undefined ? cache.dormitories : this.API('GET', '/api/attendance/dormitories'),
        cache?.sessions !== undefined ? cache.sessions : this.API('GET', '/api/attendance/sessions', { type: 'boarding' }),
      ]);
      this.state.rollCalls = Array.isArray(rollCalls?.data) ? rollCalls.data : (Array.isArray(rollCalls) ? rollCalls : []);
      this.state.dormitories = Array.isArray(dormitories?.data) ? dormitories.data : (Array.isArray(dormitories) ? dormitories : []);
      this.state.sessions = Array.isArray(sessions?.data) ? sessions.data : (Array.isArray(sessions) ? sessions : []);
      this.renderRollCalls();
      this.renderFormOptions();
    } catch (e) { console.error(e); }
  },

  getCache(key) { return this.state.referenceCache?.[key]; },

  renderRollCalls() {
    const rollCalls = this.state.rollCalls;
    const body = document.getElementById('brcRollCallsBody');
    if (!rollCalls.length) { body.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No roll calls recorded.</td></tr>'; return; }
    body.innerHTML = rollCalls.map(r => `<tr>
      <td class="small">${this.fmtDate(r.date)}</td>
      <td class="small">${this.esc(r.session || '—')}</td>
      <td class="small">${this.esc(r.dormitory || '—')}</td>
      <td class="small text-center">${r.present}/${r.total}</td>
      <td class="small">${this.esc(r.conducted_by || '—')}</td>
      <td><button class="btn btn-sm btn-outline-primary" onclick="boardingRollCallController.viewDetails(${r.id})"><i class="bi bi-eye"></i></button></td>
    </tr>`).join('');
  },

  renderFormOptions() {
    const dormitories = this.state.dormitories;
    const sessions = this.state.sessions;
    const dormSelect = document.getElementById('brcDormitorySelect');
    const sessionSelect = document.getElementById('brcSessionSelect');
    if (dormSelect) dormSelect.innerHTML = '<option value="">Select Dormitory</option>' + dormitories.map(d => `<option value="${d.id}">${this.esc(d.name)}</option>`).join('');
    if (sessionSelect) sessionSelect.innerHTML = '<option value="">Select Session</option>' + sessions.map(s => `<option value="${s.id}">${this.esc(s.name)}</option>`).join('');
  },

  fmtDate(v) { if (!v) return '—'; const dt = new Date(String(v).replace(' ', 'T')); return isNaN(dt) ? this.esc(v) : dt.toLocaleDateString(); },

  async conductRollCall() {
    const dormitory = document.getElementById('brcDormitorySelect')?.value;
    const session = document.getElementById('brcSessionSelect')?.value;
    const date = document.getElementById('brcDate')?.value;
    if (!dormitory || !session || !date) { this.notify('All fields required', 'warning'); return; }
    try {
      await this.API('POST', '/api/boarding/roll-call', { dormitory_id: dormitory, session_id: session, date });
      this.notify('Roll call conducted', 'success');
      document.getElementById('brcForm')?.reset();
      await this.load();
    } catch (e) { this.notify('Roll call failed', 'danger'); }
  },

  async viewDetails(id) { /* open roll call detail modal */ },

  notify(msg, type = 'info') { if (window.showNotification) window.showNotification(msg, type); else if (window.showToast) window.showToast(msg, type); else alert(msg); },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.loadReferencesComposed();
    await this.load();
    window.APIRealtime?.register?.("boarding_roll_call", {
      refresh: async () => { this.state.referenceCache = null; await this.loadReferencesComposed(); await this.load(); }
    }, ["boarding", "attendance"]);
  },
};

window.boardingRollCallController = boardingRollCallController;
document.addEventListener('DOMContentLoaded', () => boardingRollCallController.init().catch(() => {}));