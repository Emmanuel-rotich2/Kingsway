/**
 * Academic Calendar Controller
 * Manages academic calendar events and term dates.
 */
const academicCalendarController = {
  state: { referenceCache: null, events: [], terms: [] },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("academic", "academic-reports-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Academic Calendar Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async load() {
    try {
      const cache = this.state.referenceCache;
      const [events, terms] = await Promise.all([
        cache?.events !== undefined ? cache.events : this.API('GET', '/api/academic/calendar'),
        cache?.terms !== undefined ? cache.terms : this.API('GET', '/api/academic/terms-list'),
      ]);
      this.state.events = Array.isArray(events?.data) ? events.data : (Array.isArray(events) ? events : []);
      this.state.terms = Array.isArray(terms?.data) ? terms.data : (Array.isArray(terms) ? terms : []);
      this.renderEvents();
      this.renderTerms();
    } catch (e) { console.error(e); }
  },

  getCache(key) { return this.state.referenceCache?.[key]; },

  renderEvents() {
    const events = this.state.events;
    const body = document.getElementById('acEventsBody');
    if (!events.length) { body.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">No calendar events.</td></tr>'; return; }
    body.innerHTML = events.map(e => `<tr>
      <td class="small">${this.fmtDate(e.date)}</td>
      <td class="small">${this.esc(e.title || '—')}</td>
      <td class="small"><span class="badge bg-${e.type === 'holiday' ? 'info' : e.type === 'exam' ? 'warning' : e.type === 'event' ? 'primary' : 'secondary'}">${this.esc(e.type || '—')}</span></td>
      <td class="small">${this.esc(e.description || '—')}</td>
      <td><button class="btn btn-sm btn-outline-primary" onclick="academicCalendarController.editEvent(${e.id})"><i class="bi bi-pencil"></i></button></td>
    </tr>`).join('');
  },

  renderTerms() {
    const terms = this.state.terms;
    const body = document.getElementById('acTermsBody');
    if (!terms.length) { body.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">No terms defined.</td></tr>'; return; }
    body.innerHTML = terms.map(t => `<tr>
      <td class="small">${this.esc(t.name || '—')}</td>
      <td class="small">${this.fmtDate(t.start_date)}</td>
      <td class="small">${this.fmtDate(t.end_date)}</td>
      <td><button class="btn btn-sm btn-outline-primary" onclick="academicCalendarController.editTerm(${t.id})"><i class="bi bi-pencil"></i></button></td>
    </tr>`).join('');
  },

  fmtDate(v) { if (!v) return '—'; const dt = new Date(String(v).replace(' ', 'T')); return isNaN(dt) ? this.esc(v) : dt.toLocaleDateString(); },

  async editEvent(id) { /* open edit modal */ },
  async editTerm(id) { /* open edit modal */ },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.loadReferencesComposed();
    await this.load();
    window.APIRealtime?.register?.("academic_calendar", {
      refresh: async () => { this.state.referenceCache = null; await this.loadReferencesComposed(); await this.load(); }
    }, ["academic"]);
  },
};

window.academicCalendarController = academicCalendarController;
document.addEventListener('DOMContentLoaded', () => academicCalendarController.init().catch(() => {}));