/**
 * CBC Curriculum Controller
 * Manages Competency-Based Curriculum structure.
 */
const cbcCurriculumController = {
  state: { referenceCache: null, strands: [], competencies: [], learningAreas: [] },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("academic", "curriculum-reference");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("CBC Curriculum Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async load() {
    try {
      const cache = this.state.referenceCache;
      const [strands, competencies, learningAreas] = await Promise.all([
        cache?.strands !== undefined ? cache.strands : this.API('GET', '/api/academic/strands'),
        cache?.competencies !== undefined ? cache.competencies : this.API('GET', '/api/academic/core-competencies'),
        cache?.learningAreas !== undefined ? cache.learningAreas : this.API('GET', '/api/academic/learning-areas'),
      ]);
      this.state.strands = Array.isArray(strands?.data) ? strands.data : (Array.isArray(strands) ? strands : []);
      this.state.competencies = Array.isArray(competencies?.data) ? competencies.data : (Array.isArray(competencies) ? competencies : []);
      this.state.learningAreas = Array.isArray(learningAreas?.data) ? learningAreas.data : (Array.isArray(learningAreas) ? learningAreas : []);
      this.renderStrands();
      this.renderCompetencies();
      this.renderLearningAreas();
    } catch (e) { console.error(e); }
  },

  getCache(key) { return this.state.referenceCache?.[key]; },

  renderStrands() {
    const strands = this.state.strands;
    const body = document.getElementById('cbcStrandsBody');
    if (!strands.length) { body.innerHTML = '<tr><td colspan="3" class="text-center py-4 text-muted">No strands defined.</td></tr>'; return; }
    body.innerHTML = strands.map(s => `<tr>
      <td class="small">${this.esc(s.name || '—')}</td>
      <td class="small">${this.esc(s.description || '—')}</td>
      <td><button class="btn btn-sm btn-outline-primary" onclick="cbcCurriculumController.editStrand(${s.id})"><i class="bi bi-pencil"></i></button></td>
    </tr>`).join('');
  },

  renderCompetencies() {
    const competencies = this.state.competencies;
    const body = document.getElementById('cbcCompetenciesBody');
    if (!competencies.length) { body.innerHTML = '<tr><td colspan="3" class="text-center py-4 text-muted">No competencies defined.</td></tr>'; return; }
    body.innerHTML = competencies.map(c => `<tr>
      <td class="small">${this.esc(c.name || '—')}</td>
      <td class="small">${this.esc(c.description || '—')}</td>
      <td><button class="btn btn-sm btn-outline-primary" onclick="cbcCurriculumController.editCompetency(${c.id})"><i class="bi bi-pencil"></i></button></td>
    </tr>`).join('');
  },

  renderLearningAreas() {
    const learningAreas = this.state.learningAreas;
    const body = document.getElementById('cbcLearningAreasBody');
    if (!learningAreas.length) { body.innerHTML = '<tr><td colspan="3" class="text-center py-4 text-muted">No learning areas defined.</td></tr>'; return; }
    body.innerHTML = learningAreas.map(a => `<tr>
      <td class="small">${this.esc(a.name || '—')}</td>
      <td class="small">${this.esc(a.description || '—')}</td>
      <td><button class="btn btn-sm btn-outline-primary" onclick="cbcCurriculumController.editLearningArea(${a.id})"><i class="bi bi-pencil"></i></button></td>
    </tr>`).join('');
  },

  async editStrand(id) { /* open edit modal */ },
  async editCompetency(id) { /* open edit modal */ },
  async editLearningArea(id) { /* open edit modal */ },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.loadReferencesComposed();
    await this.load();
    window.APIRealtime?.register?.("cbc_curriculum", {
      refresh: async () => { this.state.referenceCache = null; await this.loadReferencesComposed(); await this.load(); }
    }, ["academic"]);
  },
};

window.cbcCurriculumController = cbcCurriculumController;
document.addEventListener('DOMContentLoaded', () => cbcCurriculumController.init().catch(() => {}));