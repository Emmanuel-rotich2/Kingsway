/**
 * Intern Assigned Subjects Controller
 * Manages subject assignments for intern teachers.
 */
const internAssignedSubjectsController = {
  state: { referenceCache: null, assignments: [], interns: [], subjects: [], classes: [] },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("academic", "manage-timetable-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Intern Assigned Subjects Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async load() {
    try {
      const cache = this.state.referenceCache;
      const [assignments, interns, subjects, classes] = await Promise.all([
        cache?.assignments !== undefined ? cache.assignments : this.API('GET', '/api/academic/intern-subject-assignments'),
        cache?.interns !== undefined ? cache.interns : this.API('GET', '/api/staff/interns'),
        cache?.subjects !== undefined ? cache.subjects : this.API('GET', '/api/academic/learning-areas/list'),
        cache?.classes !== undefined ? cache.classes : this.API('GET', '/api/academic/classes-list'),
      ]);
      this.state.assignments = Array.isArray(assignments?.data) ? assignments.data : (Array.isArray(assignments) ? assignments : []);
      this.state.interns = Array.isArray(interns?.data) ? interns.data : (Array.isArray(interns) ? interns : []);
      this.state.subjects = Array.isArray(subjects?.data) ? subjects.data : (Array.isArray(subjects) ? subjects : []);
      this.state.classes = Array.isArray(classes?.data) ? classes.data : (Array.isArray(classes) ? classes : []);
      this.renderAssignments();
      this.renderFormOptions();
    } catch (e) { console.error(e); }
  },

  getCache(key) { return this.state.referenceCache?.[key]; },

  renderAssignments() {
    const assignments = this.state.assignments;
    const body = document.getElementById('iasAssignmentsBody');
    if (!assignments.length) { body.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No assignments.</td></tr>'; return; }
    body.innerHTML = assignments.map(a => `<tr>
      <td class="small">${this.esc(a.intern_name || '—')}</td>
      <td class="small">${this.esc(a.subject || '—')}</td>
      <td class="small">${this.esc(a.class_name || '—')}</td>
      <td class="small">${this.esc(a.stream_name || '—')}</td>
      <td class="small"><span class="badge bg-${a.status === 'active' ? 'success' : 'secondary'}">${this.esc(a.status || '—')}</span></td>
      <td><button class="btn btn-sm btn-outline-primary" onclick="internAssignedSubjectsController.editAssignment(${a.id})"><i class="bi bi-pencil"></i></button></td>
    </tr>`).join('');
  },

  renderFormOptions() {
    const interns = this.state.interns;
    const subjects = this.state.subjects;
    const classes = this.state.classes;
    const internSelect = document.getElementById('iasInternSelect');
    const subjectSelect = document.getElementById('iasSubjectSelect');
    const classSelect = document.getElementById('iasClassSelect');
    if (internSelect) internSelect.innerHTML = '<option value="">Select Intern</option>' + interns.map(i => `<option value="${i.id}">${this.esc(i.name)}</option>`).join('');
    if (subjectSelect) subjectSelect.innerHTML = '<option value="">Select Subject</option>' + subjects.map(s => `<option value="${s.id}">${this.esc(s.name)}</option>`).join('');
    if (classSelect) classSelect.innerHTML = '<option value="">Select Class</option>' + classes.map(c => `<option value="${c.id}">${this.esc(c.name)}</option>`).join('');
  },

  async createAssignment() {
    const intern = document.getElementById('iasInternSelect')?.value;
    const subject = document.getElementById('iasSubjectSelect')?.value;
    const classId = document.getElementById('iasClassSelect')?.value;
    const stream = document.getElementById('iasStreamSelect')?.value;
    if (!intern || !subject || !classId) { this.notify('All fields required', 'warning'); return; }
    try {
      await this.API('POST', '/api/academic/intern-subject-assignments', { intern_id: intern, subject_id: subject, class_id: classId, stream_id: stream });
      this.notify('Assignment created', 'success');
      document.getElementById('iasForm')?.reset();
      await this.load();
    } catch (e) { this.notify('Creation failed', 'danger'); }
  },

  async editAssignment(id) { /* open edit modal */ },

  notify(msg, type = 'info') { if (window.showNotification) window.showNotification(msg, type); else if (window.showToast) window.showToast(msg, type); else alert(msg); },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.loadReferencesComposed();
    await this.load();
    window.APIRealtime?.register?.("intern_assigned_subjects", {
      refresh: async () => { this.state.referenceCache = null; await this.loadReferencesComposed(); await this.load(); }
    }, ["academic"]);
  },
};

window.internAssignedSubjectsController = internAssignedSubjectsController;
document.addEventListener('DOMContentLoaded', () => internAssignedSubjectsController.init().catch(() => {}));