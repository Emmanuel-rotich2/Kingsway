/**
 * Academic Years Controller
 * Manages academic year definitions.
 */
const academicYearsController = {
  state: { referenceCache: null, years: [] },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("academic", "manager-overview");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Academic Years Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async load() {
    try {
      const cache = this.state.referenceCache;
      const years = cache?.years !== undefined ? cache.years : await this.API('GET', '/api/academic/years/list');
      this.state.years = Array.isArray(years?.data) ? years.data : (Array.isArray(years) ? years : []);
      this.render();
    } catch (e) { console.error(e); }
  },

  getCache(key) { return this.state.referenceCache?.[key]; },

  render() {
    const years = this.state.years;
    const body = document.getElementById('ayYearsBody');
    if (!years.length) { body.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">No academic years defined.</td></tr>'; return; }
    body.innerHTML = years.map(y => `<tr>
      <td class="small">${this.esc(y.name || '—')}</td>
      <td class="small">${this.fmtDate(y.start_date)}</td>
      <td class="small">${this.fmtDate(y.end_date)}</td>
      <td class="small"><span class="badge bg-${y.is_current ? 'success' : y.is_active ? 'primary' : 'secondary'}">${y.is_current ? 'Current' : y.is_active ? 'Active' : 'Inactive'}</span></td>
      <td><button class="btn btn-sm btn-outline-primary" onclick="academicYearsController.editYear(${y.id})"><i class="bi bi-pencil"></i></button></td>
    </tr>`).join('');
  },

  fmtDate(v) { if (!v) return '—'; const dt = new Date(String(v).replace(' ', 'T')); return isNaN(dt) ? this.esc(v) : dt.toLocaleDateString(); },

  async editYear(id) { /* open edit modal */ },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.loadReferencesComposed();
    await this.load();
    window.APIRealtime?.register?.("academic_years", {
      refresh: async () => { this.state.referenceCache = null; await this.loadReferencesComposed(); await this.load(); }
    }, ["academic"]);
  },
};

window.academicYearsController = academicYearsController;
document.addEventListener('DOMContentLoaded', () => academicYearsController.init().catch(() => {}));