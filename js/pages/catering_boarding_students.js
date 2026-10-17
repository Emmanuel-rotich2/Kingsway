/**
 * Catering Boarding Students Controller
 * Manages meal planning and catering for boarding students.
 */
const cateringBoardingStudentsController = {
  state: { referenceCache: null, students: [], mealPlans: [] },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("boarding", "boarding-students-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Catering Boarding Students Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async load() {
    try {
      const cache = this.state.referenceCache;
      const [students, mealPlans] = await Promise.all([
        cache?.students !== undefined ? cache.students : this.API('GET', '/api/boarding/students'),
        this.API('GET', '/api/catering/boarding-meal-plans'),
      ]);
      this.state.students = Array.isArray(students?.data) ? students.data : (Array.isArray(students) ? students : []);
      this.state.mealPlans = Array.isArray(mealPlans?.data) ? mealPlans.data : (Array.isArray(mealPlans) ? mealPlans : []);
      this.renderStudents();
      this.renderMealPlans();
    } catch (e) { console.error(e); }
  },

  getCache(key) { return this.state.referenceCache?.[key]; },

  renderStudents() {
    const students = this.state.students;
    const body = document.getElementById('cbsStudentsBody');
    if (!students.length) { body.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">No boarding students.</td></tr>'; return; }
    body.innerHTML = students.map(s => `<tr>
      <td class="small">${this.esc(s.admission_number || '—')}</td>
      <td class="small">${this.esc(s.name || '—')}</td>
      <td class="small">${this.esc(s.dormitory || '—')}</td>
      <td class="small">${this.esc(s.dietary_requirements || 'None')}</td>
      <td class="small text-center">${this.esc(s.meal_plan || 'Standard')}</td>
    </tr>`).join('');
  },

  renderMealPlans() {
    const mealPlans = this.state.mealPlans;
    const body = document.getElementById('cbsMealPlansBody');
    if (!mealPlans.length) { body.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">No meal plans.</td></tr>'; return; }
    body.innerHTML = mealPlans.map(m => `<tr>
      <td class="small">${this.esc(m.name || '—')}</td>
      <td class="small">${this.esc(m.description || '—')}</td>
      <td class="small">${this.fmtKES(m.cost_per_day)}</td>
      <td><button class="btn btn-sm btn-outline-primary" onclick="cateringBoardingStudentsController.editPlan(${m.id})"><i class="bi bi-pencil"></i></button></td>
    </tr>`).join('');
  },

  fmtKES(n) { return 'KES ' + Number(n || 0).toLocaleString('en-KE', { minimumFractionDigits: 2 }); },

  async editPlan(id) { /* open edit modal */ },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.loadReferencesComposed();
    await this.load();
    window.APIRealtime?.register?.("catering_boarding_students", {
      refresh: async () => { this.state.referenceCache = null; await this.loadReferencesComposed(); await this.load(); }
    }, ["boarding", "catering"]);
  },
};

window.cateringBoardingStudentsController = cateringBoardingStudentsController;
document.addEventListener('DOMContentLoaded', () => cateringBoardingStudentsController.init().catch(() => {}));