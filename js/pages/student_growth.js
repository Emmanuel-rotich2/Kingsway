/**
 * Student Growth Controller
 * Tracks academic growth and progress over time.
 */
const studentGrowthController = {
  state: { referenceCache: null, growthData: [], student: null },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("students", "student-growth-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Student Growth Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async load(studentId) {
    try {
      const cache = this.state.referenceCache;
      const [growthData, student] = await Promise.all([
        cache?.growthData !== undefined ? cache.growthData : this.API('GET', `/api/students/${studentId}/growth`),
        cache?.student !== undefined ? cache.student : this.API('GET', `/api/students/${studentId}`),
      ]);
      this.state.growthData = Array.isArray(growthData?.data) ? growthData.data : (Array.isArray(growthData) ? growthData : []);
      this.state.student = student?.data || student || {};
      this.render();
    } catch (e) { console.error(e); }
  },

  getCache(key) { return this.state.referenceCache?.[key]; },

  render() {
    const growth = this.state.growthData;
    const student = this.state.student;
    document.getElementById('sgStudentName')?.textContent = student?.name || '—';
    document.getElementById('sgAdmissionNo')?.textContent = student?.admission_number || '—';
    const body = document.getElementById('sgGrowthBody');
    if (!growth.length) { body.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No growth data available.</td></tr>'; return; }
    body.innerHTML = growth.map(g => `<tr>
      <td class="small">${this.esc(g.term || '—')}</td>
      <td class="small">${this.esc(g.subject || '—')}</td>
      <td class="small text-center">${this.esc(g.score || '—')}</td>
      <td class="small text-center">${this.esc(g.class_average || '—')}</td>
      <td class="small text-center">${this.esc(g.rank || '—')}</td>
      <td class="small">${this.esc(g.trend || '—')}</td>
    </tr>`).join('');
    this.renderTermFilter();
  },

  renderTermFilter() {
    const terms = this.getCache('terms') || [];
    const select = document.getElementById('sgTermFilter');
    if (select) select.innerHTML = '<option value="">All Terms</option>' + terms.map(t => `<option value="${t.id}">${this.esc(t.name)}</option>`).join('');
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    const studentId = new URLSearchParams(window.location.search).get('student_id');
    if (!studentId) return;
    await this.loadReferencesComposed();
    await this.load(studentId);
    window.APIRealtime?.register?.("student_growth", {
      refresh: async () => { this.state.referenceCache = null; await this.loadReferencesComposed(); await this.load(studentId); }
    }, ["students", "academic"]);
  },
};

window.studentGrowthController = studentGrowthController;
document.addEventListener('DOMContentLoaded', () => studentGrowthController.init().catch(() => {}));