/**
 * At-Risk Students Controller
 * Identifies and tracks students at academic or behavioral risk.
 */
const atRiskStudentsController = {
  state: { referenceCache: null, students: [], riskFactors: [] },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },

  async loadReferencesComposed() {
    // At-risk students typically use direct reads with filters
    this.state.referenceCache = {};
  },

  async load() {
    try {
      const [students, riskFactors] = await Promise.all([
        this.API('GET', '/api/students/at-risk'),
        this.API('GET', '/api/students/risk-factors'),
      ]);
      this.state.students = Array.isArray(students?.data) ? students.data : (Array.isArray(students) ? students : []);
      this.state.riskFactors = Array.isArray(riskFactors?.data) ? riskFactors.data : (Array.isArray(riskFactors) ? riskFactors : []);
      this.render();
    } catch (e) { console.error(e); }
  },

  render() {
    const students = this.state.students;
    const body = document.getElementById('arsStudentsBody');
    if (!students.length) { body.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No at-risk students identified.</td></tr>'; return; }
    body.innerHTML = students.map(s => `<tr>
      <td class="small">${this.esc(s.admission_number || '—')}</td>
      <td class="small fw-semibold">${this.esc(s.name || '—')}</td>
      <td class="small">${this.esc(s.class_name || '—')}</td>
      <td class="small"><span class="badge bg-${s.risk_level === 'high' ? 'danger' : s.risk_level === 'medium' ? 'warning' : 'info'}">${this.esc(s.risk_level || '—')}</span></td>
      <td class="small">${this.esc(s.primary_factor || '—')}</td>
      <td class="small">${this.fmtDate(s.identified_date)}</td>
      <td><button class="btn btn-sm btn-outline-primary" onclick="atRiskStudentsController.viewPlan(${s.id})"><i class="bi bi-eye"></i></button></td>
    </tr>`).join('');
  },

  fmtDate(v) { if (!v) return '—'; const dt = new Date(String(v).replace(' ', 'T')); return isNaN(dt) ? this.esc(v) : dt.toLocaleDateString(); },

  async viewPlan(id) { /* open intervention plan modal */ },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.load();
    window.APIRealtime?.register?.("at_risk_students", {
      refresh: async () => await this.load()
    }, ["students", "counseling"]);
  },
};

window.atRiskStudentsController = atRiskStudentsController;
document.addEventListener('DOMContentLoaded', () => atRiskStudentsController.init().catch(() => {}));