/**
 * approved_lesson_plans.js — Approved Lesson Plans Controller (standalone page).
 * Lists approved lesson plans with subject/class filters and read-only view.
 */
const approvedLessonPlansController = {
  state: { items: [], subjects: [], classes: [] },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  notify(msg, type = 'success') {
    if (window.showNotification) { showNotification(msg, type); return; }
    const el = document.createElement('div');
    el.className = `alert alert-${type} position-fixed top-0 end-0 m-3 shadow`;
    el.style.zIndex = 99999;
    el.textContent = msg;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 3000);
  },

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); },

  async loadReference() {
    try {
      const [s, c] = await Promise.all([
        this.API('GET', 'academic/subjects-list'),
        this.API('GET', 'academic/classes-list'),
      ]);
      this.state.subjects = s?.data?.items || s?.data || [];
      this.state.classes = c?.data?.items || c?.data || [];
      const subjSel = document.getElementById('alpSubject');
      subjSel.innerHTML = '<option value="">All learning areas</option>' + this.state.subjects.map(x => `<option value="${x.id}">${this.esc(x.name || x.subject_name || x.id)}</option>`).join('');
      const clsSel = document.getElementById('alpClass');
      clsSel.innerHTML = '<option value="">All classes</option>' + this.state.classes.map(x => `<option value="${x.id}">${this.esc(x.name || x.class_name || x.id)}</option>`).join('');
    } catch (_) {}
  },

  async loadData() {
    const body = document.getElementById('alpBody');
    body.innerHTML = '<tr><td colspan="7" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    try {
      const params = { status: 'approved', limit: 100 };
      const search = document.getElementById('alpSearch').value.trim();
      if (search) params.search = search;
      const subjectId = document.getElementById('alpSubject').value;
      if (subjectId) params.learning_area_id = subjectId;
      const classId = document.getElementById('alpClass').value;
      if (classId) params.class_id = classId;
      const r = await this.API('GET', 'academic/lesson-plans-list', null, params);
      const data = r?.data || r || [];
      this.state.items = Array.isArray(data) ? data : data.lesson_plans || data.data || [];
      this.render();
    } catch (e) {
      body.innerHTML = `<tr><td colspan="7" class="text-center py-3 text-danger">${this.esc(e.message || 'Load failed')}</td></tr>`;
    }
  },

  render() {
    const body = document.getElementById('alpBody');
    if (!this.state.items.length) { body.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No approved lesson plans found.</td></tr>'; return; }
    body.innerHTML = this.state.items.map(p => `
      <tr>
        <td class="fw-semibold small">${this.esc(p.topic || '\u2014')}</td>
        <td class="small">${this.esc(p.learning_area_name || p.subject_name || '\u2014')}</td>
        <td class="small text-muted">${this.esc(p.class_name || '\u2014')}</td>
        <td class="small text-muted">${this.esc(p.teacher_name || p.teacher_first_name ? `${p.teacher_first_name || ''} ${p.teacher_last_name || ''}`.trim() : '') || '\u2014'}</td>
        <td class="small text-muted">${this.esc(p.lesson_date || '\u2014')}</td>
        <td><span class="badge bg-success">Approved</span></td>
        <td class="text-end">
          <button class="btn btn-sm btn-outline-secondary rounded-pill px-2" data-kw-onclick="approvedLessonPlansController.view(${p.id})" title="View"><i class="bi bi-eye"></i></button>
        </td>
      </tr>`).join('');
  },

  async view(id) {
    try {
      const r = await this.API('GET', `academic/lesson-plans-get/${id}`);
      const p = r?.data || {};
      document.getElementById('alpViewTitle').textContent = p.topic || 'Lesson Plan';
      document.getElementById('alpViewBody').innerHTML = `
        <div class="row g-3">
          <div class="col-md-4"><strong>Learning Area:</strong><br><span class="small">${this.esc(p.learning_area_name || '\u2014')}</span></div>
          <div class="col-md-4"><strong>Class:</strong><br><span class="small">${this.esc(p.class_name || '\u2014')}</span></div>
          <div class="col-md-4"><strong>Date:</strong><br><span class="small">${this.esc(p.lesson_date || '\u2014')}</span></div>
          ${p.subtopic ? `<div class="col-12"><strong>Subtopic:</strong><br><span class="small">${this.esc(p.subtopic)}</span></div>` : ''}
          <div class="col-12"><strong>Objectives:</strong><br><span class="small">${this.esc(p.objectives || '\u2014')}</span></div>
          <div class="col-12"><strong>Content / Activities:</strong><br><span class="small">${this.esc(p.content || p.activities || p.lesson_content || '\u2014')}</span></div>
          ${p.remedial ? `<div class="col-12"><strong>Remedial Activities:</strong><br><span class="small">${this.esc(p.remedial)}</span></div>` : ''}
          ${p.extension ? `<div class="col-12"><strong>Extension Activities:</strong><br><span class="small">${this.esc(p.extension)}</span></div>` : ''}
          ${p.remarks ? `<div class="col-12"><strong>Approver Remarks:</strong><br><span class="small">${this.esc(p.remarks)}</span></div>` : ''}
        </div>`;
      bootstrap.Modal.getOrCreateInstance(document.getElementById('alpViewModal')).show();
    } catch (e) { this.notify(e.message, 'danger'); }
  },

  setupEventListeners() {
    ['alpSubject', 'alpClass'].forEach(id => {
      document.getElementById(id)?.addEventListener('change', () => this.loadData());
    });
    const searchEl = document.getElementById('alpSearch');
    if (searchEl) searchEl.addEventListener('keyup', () => this.loadData());
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    this.setupEventListeners();
    await this.loadReference();
    this.loadData();
  },
};

window.approvedLessonPlansController = approvedLessonPlansController;

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => approvedLessonPlansController.init().catch(() => {}));
} else {
  approvedLessonPlansController.init().catch(() => {});
}

// Shared year bar: reload this page's data in place for the chosen year.
window.KingswayYearScope?.onYearChange?.(() => approvedLessonPlansController.loadData());
