/**
 * Health Reports Controller
 * Health incidents, clinic visits, and wellness reports.
 */
const healthReportsController = {
  state: { classes: [], streams: [], categories: [], report: null, referenceCache: null },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },

  norm(r) { return Array.isArray(r) ? r : (r?.data || r || []); },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("reports", "health-reports-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Health Reports Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async load() {
    try {
      const cache = this.state.referenceCache;
      const [cls, strms, cats] = await Promise.all([
        cache?.classes !== undefined ? cache.classes : this.API('GET', '/api/academic/classes-list'),
        cache?.streams !== undefined ? cache.streams : this.API('GET', '/api/academic/streams-list'),
        cache?.categories !== undefined ? cache.categories : this.API('GET', '/api/students/health-meta'),
      ]);
      this.state.classes = this.norm(cls);
      this.state.streams = this.norm(strms);
      const categoriesRaw = this.norm(cats);
      const categoryList = Array.isArray(categoriesRaw) ? categoriesRaw : (categoriesRaw?.health_categories || []);
      this.state.categories = categoryList.map(x => typeof x === 'string' ? { id: x, name: x } : x);
      this.populateFilters();
      await this.loadReport();
    } catch (e) { console.error(e); }
  },

  populateFilters() {
    const c = document.getElementById('hrClass');
    const s = document.getElementById('hrStream');
    const cat = document.getElementById('hrCategory');
    c.innerHTML = '<option value="">All Classes</option>' + this.state.classes.map(x => `<option value="${x.id}">${this.esc(x.name || x.class_name)}</option>`).join('');
    s.innerHTML = '<option value="">All Streams</option>' + this.state.streams.map(x => `<option value="${x.id}">${this.esc(x.name || x.stream_name)}</option>`).join('');
    cat.innerHTML = '<option value="">All Categories</option>' + this.state.categories.map(x => `<option value="${x.id}">${this.esc(x.name || x.category)}</option>`).join('');
  },

  async loadReport() {
    const params = new URLSearchParams();
    if (document.getElementById('hrClass')?.value) params.set('class_id', document.getElementById('hrClass').value);
    if (document.getElementById('hrStream')?.value) params.set('stream_id', document.getElementById('hrStream').value);
    if (document.getElementById('hrCategory')?.value) params.set('category_id', document.getElementById('hrCategory').value);

    try {
      const response = await this.API('GET', '/api/reports/health', null, params);
      this.state.report = response?.data || response || {};
      this.render();
    } catch (e) { console.error(e); }
  },

  render() {
    const r = this.state.report;
    const body = document.getElementById('hrBody');
    if (!r || !r.length) { body.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No health data.</td></tr>'; return; }
    body.innerHTML = r.map(x => `<tr>
      <td class="small">${this.esc(x.admission_no || '—')}</td>
      <td class="small fw-semibold">${this.esc(x.full_name || '—')}</td>
      <td class="small">${this.esc(x.class_name || '—')}</td>
      <td class="small">${this.esc(x.category_name || '—')}</td>
      <td class="small">${this.esc(x.visit_date || '—')}</td>
      <td class="small"><span class="badge bg-${x.severity === 'high' ? 'danger' : x.severity === 'medium' ? 'warning' : 'info'}">${this.esc(x.severity || '—')}</span></td>
      <td class="small">${this.esc(x.treatment || '—')}</td>
    </tr>`).join('');
  },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("reports", "health-reports-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Health Reports Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.loadReferencesComposed();
    await this.load();
    window.APIRealtime?.register?.("health_reports", {
      refresh: async () => {
        this.state.referenceCache = null;
        await this.loadReferencesComposed();
        await this.load();
      }
    }, ["reports", "health"]);
  },
};

window.healthReportsController = healthReportsController;
document.addEventListener('DOMContentLoaded', () => healthReportsController.init().catch(() => {}));