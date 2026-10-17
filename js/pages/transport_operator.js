/**
 * Transport Operator Controller
 * Driver-facing interface for daily transport operations.
 */
const transportOperatorController = {
  state: { referenceCache: null, manifest: null, date: '', session: '' },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("transport", "transport-operator-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Transport Operator Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async load() {
    try {
      const date = document.getElementById('toDate')?.value || new Date().toISOString().split('T')[0];
      const session = document.getElementById('toSession')?.value || 'morning';
      const cache = this.state.referenceCache;
      const manifest = cache?.manifest !== undefined ? cache.manifest : await this.API('GET', '/api/transport/driver-manifest', { date, trip_session: session });
      this.state.manifest = manifest?.data || manifest || {};
      this.state.date = date;
      this.state.session = session;
      this.renderManifest();
    } catch (e) { console.error(e); }
  },

  getCache(key) { return this.state.referenceCache?.[key]; },

  renderManifest() {
    const m = this.state.manifest;
    document.getElementById('toRouteName')?.textContent = m?.route_name || '—';
    document.getElementById('toVehicle')?.textContent = m?.vehicle_reg || '—';
    document.getElementById('toDriver')?.textContent = m?.driver_name || '—';
    document.getElementById('toDate')?.textContent = this.fmtDate(this.state.date);
    document.getElementById('toSession')?.textContent = this.state.session;
    const students = m?.students || [];
    const body = document.getElementById('toStudentsBody');
    if (!students.length) { body.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">No students on this run.</td></tr>'; return; }
    body.innerHTML = students.map(s => `<tr>
      <td class="small">${this.esc(s.name || '—')}</td>
      <td class="small">${this.esc(s.admission_number || '—')}</td>
      <td class="small">${this.esc(s.stop_name || '—')}</td>
      <td class="small"><input type="checkbox" class="form-check-input" ${s.present ? 'checked' : ''} onchange="transportOperatorController.markAttendance(${s.id}, this.checked)"></td>
      <td class="small">${this.esc(s.notes || '—')}</td>
    </tr>`).join('');
  },

  fmtDate(v) { if (!v) return '—'; const dt = new Date(String(v).replace(' ', 'T')); return isNaN(dt) ? this.esc(v) : dt.toLocaleDateString(); },

  async markAttendance(studentId, present) {
    try {
      await this.API('POST', '/api/transport/attendance', { student_id: studentId, date: this.state.date, session: this.state.session, present });
    } catch (e) { console.error(e); }
  },

  async submitManifest() {
    try {
      await this.API('POST', '/api/transport/manifest/submit', { date: this.state.date, session: this.state.session });
      this.notify('Manifest submitted', 'success');
    } catch (e) { this.notify('Submission failed', 'danger'); }
  },

  notify(msg, type = 'info') { if (window.showNotification) window.showNotification(msg, type); else if (window.showToast) window.showToast(msg, type); else alert(msg); },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.loadReferencesComposed();
    await this.load();
    window.APIRealtime?.register?.("transport_operator", {
      refresh: async () => { this.state.referenceCache = null; await this.loadReferencesComposed(); await this.load(); }
    }, ["transport"]);
  },
};

window.transportOperatorController = transportOperatorController;
document.addEventListener('DOMContentLoaded', () => transportOperatorController.init().catch(() => {}));