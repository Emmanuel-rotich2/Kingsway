/** School-wide lesson plan register, limited by the server's teacher scope. */
const allLessonPlansController = {
  state: { plans: [], filtered: [], teachers: [], classes: [], subjects: [], page: 1, pageSize: 25, request: 0 },
  api(method, endpoint, data, params) { return window.callAPI(endpoint, method, data, params); },
  esc(value) { return String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]); },
  rows(response) {
    let data = response;
    for (let i = 0; i < 4 && data && !Array.isArray(data); i += 1) {
      if (Array.isArray(data.lesson_plans)) return data.lesson_plans;
      if (Array.isArray(data.items)) return data.items;
      if (data.data === undefined) break;
      data = data.data;
    }
    return Array.isArray(data) ? data : [];
  },
  async loadReferences() {
    const [teachers, classes, subjects] = await Promise.all([
      this.api('GET', '/api/academic/teachers-list'),
      this.api('GET', '/api/academic/classes-list'),
      this.api('GET', '/api/academic/learning-areas/list'),
    ]);
    this.state.teachers = this.rows(teachers); this.state.classes = this.rows(classes); this.state.subjects = this.rows(subjects);
    const fill = (id, items, label) => {
      const select = document.getElementById(id); if (!select) return;
      const first = select.options[0]?.outerHTML || '<option value="">All</option>';
      select.innerHTML = first + items.map((item) => `<option value="${Number(item.id)}">${this.esc(label(item))}</option>`).join('');
    };
    fill('teacherFilterLP', this.state.teachers, (x) => x.name || x.full_name || [x.first_name, x.last_name].filter(Boolean).join(' '));
    fill('subjectFilterLP', this.state.subjects, (x) => x.name || x.subject_name);
    fill('classFilterLP', this.state.classes, (x) => x.name || x.class_name);
  },
  async load() {
    const request = ++this.state.request; const body = document.getElementById('lessonPlansTableBody');
    if (body) body.innerHTML = '<tr><td colspan="8" class="text-center py-4"><span class="spinner-border spinner-border-sm me-2"></span>Loading lesson plans…</td></tr>';
    try {
      const params = { limit: 1000 };
      const teacher = document.getElementById('teacherFilterLP')?.value;
      const subject = document.getElementById('subjectFilterLP')?.value;
      const classId = document.getElementById('classFilterLP')?.value;
      const status = document.getElementById('statusFilterLP')?.value;
      if (teacher) params.teacher_id = teacher;
      if (subject) params.learning_area_id = subject;
      if (classId) params.class_id = classId;
      if (status) params.status = status;
      const response = await this.api('GET', '/api/academic/lesson-plans/list', null, params);
      if (request !== this.state.request) return;
      if (response?.success === false || response?.error) throw new Error(response.message || response.error || 'Could not load lesson plans.');
      this.state.plans = this.rows(response); this.state.page = 1; this.applyFilters();
    } catch (error) {
      if (request !== this.state.request) return;
      if (body) body.innerHTML = `<tr><td colspan="8" class="text-center text-danger py-4">${this.esc(error?.message || 'Lesson plans could not be loaded.')}</td></tr>`;
      ['totalPlans', 'approvedPlans', 'pendingPlans', 'rejectedPlans', 'totalRecords', 'showingFrom', 'showingTo'].forEach((id) => this.text(id, '—'));
    }
  },
  applyFilters() {
    const query = (document.getElementById('searchLessonPlans')?.value || '').trim().toLowerCase();
    this.state.filtered = this.state.plans.filter((plan) => !query || [plan.title, plan.topic, plan.teacher_name, plan.subject_name, plan.class_name, plan.status].join(' ').toLowerCase().includes(query));
    this.state.page = Math.min(this.state.page, Math.max(1, Math.ceil(this.state.filtered.length / this.state.pageSize)));
    this.render();
  },
  render() {
    const plans = this.state.filtered; const start = (this.state.page - 1) * this.state.pageSize; const visible = plans.slice(start, start + this.state.pageSize);
    const body = document.getElementById('lessonPlansTableBody');
    if (body) body.innerHTML = visible.length ? visible.map((p, index) => {
      const status = String(p.status || 'draft').toLowerCase(); const color = status === 'approved' ? 'success' : ['pending', 'submitted', 'pending_review'].includes(status) ? 'warning' : status === 'rejected' ? 'danger' : 'secondary';
      return `<tr><td>${start + index + 1}</td><td>${this.esc(p.title || p.topic || '—')}</td><td>${this.esc(p.teacher_name || '—')}</td><td>${this.esc(p.subject_name || p.learning_area_name || '—')}</td><td>${this.esc(p.class_name || '—')}</td><td>${this.date(p.lesson_date || p.date)}</td><td><span class="badge text-bg-${color}">${this.esc(status.replaceAll('_', ' '))}</span></td><td><button type="button" class="btn btn-sm btn-outline-primary" data-plan-id="${Number(p.id)}" title="View details"><i class="bi bi-eye"></i></button></td></tr>`;
    }).join('') : '<tr><td colspan="8" class="text-center py-4 text-muted">No lesson plans match these filters.</td></tr>';
    this.text('totalPlans', this.state.plans.length);
    this.text('approvedPlans', this.state.plans.filter((x) => String(x.status).toLowerCase() === 'approved').length);
    this.text('pendingPlans', this.state.plans.filter((x) => ['pending', 'submitted', 'pending_review'].includes(String(x.status).toLowerCase())).length);
    this.text('rejectedPlans', this.state.plans.filter((x) => String(x.status).toLowerCase() === 'rejected').length);
    this.text('totalRecords', plans.length); this.text('showingFrom', visible.length ? start + 1 : 0); this.text('showingTo', start + visible.length);
    this.renderPagination();
  },
  renderPagination() {
    const target = document.getElementById('pagination'); if (!target) return;
    const pages = Math.ceil(this.state.filtered.length / this.state.pageSize); if (pages <= 1) { target.innerHTML = ''; return; }
    target.innerHTML = `<li class="page-item ${this.state.page <= 1 ? 'disabled' : ''}"><button type="button" class="page-link" data-page="${this.state.page - 1}">Previous</button></li><li class="page-item disabled"><span class="page-link">${this.state.page} / ${pages}</span></li><li class="page-item ${this.state.page >= pages ? 'disabled' : ''}"><button type="button" class="page-link" data-page="${this.state.page + 1}">Next</button></li>`;
  },
  async viewPlan(id) {
    try {
      const response = await this.api('GET', `/api/academic/lesson-plans/get/${Number(id)}`); const plan = response?.data?.data ?? response?.data ?? response ?? {};
      if (response?.success === false || response?.error) throw new Error(response.message || 'Could not open lesson plan.');
      const content = document.getElementById('viewLessonPlanContent'); if (!content) return;
      content.innerHTML = `<dl class="row"><dt class="col-sm-3">Title</dt><dd class="col-sm-9">${this.esc(plan.title || plan.topic || '—')}</dd><dt class="col-sm-3">Teacher</dt><dd class="col-sm-9">${this.esc(plan.teacher_name || '—')}</dd><dt class="col-sm-3">Subject / class</dt><dd class="col-sm-9">${this.esc(plan.subject_name || '—')} · ${this.esc(plan.class_name || '—')}</dd><dt class="col-sm-3">Date</dt><dd class="col-sm-9">${this.date(plan.date || plan.lesson_date)}</dd><dt class="col-sm-3">Objectives</dt><dd class="col-sm-9">${this.esc(plan.objectives || plan.learning_objectives || '—')}</dd><dt class="col-sm-3">Content / activities</dt><dd class="col-sm-9">${this.esc(plan.content || plan.activities || '—')}</dd><dt class="col-sm-3">Resources</dt><dd class="col-sm-9">${this.esc(plan.resources || '—')}</dd><dt class="col-sm-3">Assessment</dt><dd class="col-sm-9">${this.esc(plan.assessment || '—')}</dd></dl>`;
      window.bootstrap?.Modal.getOrCreateInstance(document.getElementById('viewLessonPlanModal'))?.show();
    } catch (error) { window.showNotification?.(error?.message || 'Could not open lesson plan.', 'error'); }
  },
  date(value) { if (!value) return '—'; const d = new Date(String(value).replace(' ', 'T')); return Number.isNaN(d.getTime()) ? this.esc(value) : d.toLocaleDateString(); },
  text(id, value) { const el = document.getElementById(id); if (el) el.textContent = String(value); },
  exportCSV() {
    if (window.AuthContext?.canExport && !window.AuthContext.canExport('academic')) return window.showNotification?.('You do not have permission to export academic data.', 'error');
    const quote = (v) => `"${String(v ?? '').replace(/"/g, '""')}"`;
    const rows = [['Title', 'Teacher', 'Subject', 'Class', 'Date', 'Status'], ...this.state.filtered.map((p) => [p.title || p.topic, p.teacher_name, p.subject_name, p.class_name, p.lesson_date || p.date, p.status])];
    window.KingswayFileLifecycle?.exportText?.(rows.map((r) => r.map(quote).join(',')).join('\r\n'), 'lesson-plans.csv', 'text/csv;charset=utf-8');
  },
  print() { if (window.AuthContext?.canPrint && !window.AuthContext.canPrint('academic')) return window.showNotification?.('You do not have permission to print academic data.', 'error'); window.print(); },
  bind() {
    ['teacherFilterLP', 'subjectFilterLP', 'classFilterLP', 'statusFilterLP'].forEach((id) => document.getElementById(id)?.addEventListener('change', () => this.load()));
    let timer; document.getElementById('searchLessonPlans')?.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(() => { this.state.page = 1; this.applyFilters(); }, 200); });
    document.getElementById('lessonPlansTableBody')?.addEventListener('click', (event) => { const button = event.target.closest('[data-plan-id]'); if (button) this.viewPlan(button.dataset.planId); });
    document.getElementById('pagination')?.addEventListener('click', (event) => { const button = event.target.closest('[data-page]'); if (!button || button.closest('.disabled')) return; this.state.page = Number(button.dataset.page); this.render(); });
    document.getElementById('exportLessonPlansBtn')?.addEventListener('click', () => this.exportCSV());
    document.getElementById('printLessonPlansBtn')?.addEventListener('click', () => this.print());
  },
  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready(); this.bind();
    try { await this.loadReferences(); } catch (error) { console.error('Lesson plan filters could not load:', error); }
    await this.load();
    window.APIRealtime?.register?.('all_lesson_plans', { refresh: () => this.load() }, ['academic']);
  },
};
window.allLessonPlansController = allLessonPlansController;
document.addEventListener('DOMContentLoaded', () => allLessonPlansController.init().catch((error) => console.error('All lesson plans failed to initialize:', error)));
