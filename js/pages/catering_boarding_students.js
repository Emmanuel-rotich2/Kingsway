/** Boarding meal roster, backed by the authorized catering student APIs. */
const cateringBoardingStudentsController = {
  state: { students: [], visibleStudents: [], meta: {}, summary: {}, selectedStudent: null },
  API: (method, endpoint, data = null, params = null) => window.callAPI(endpoint, method, data, params),
  esc(value) { return String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' })[char]); },
  data(response) { return response?.data?.data ?? response?.data ?? response ?? {}; },
  today() { const now = new Date(); return `${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}-${String(now.getDate()).padStart(2,'0')}`; },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    const date = document.getElementById('dateFilter'); if (date && !date.value) date.value = this.today();
    document.getElementById('refreshBtn')?.addEventListener('click', () => this.load());
    document.getElementById('applyFiltersBtn')?.addEventListener('click', () => this.load());
    document.getElementById('resetFiltersBtn')?.addEventListener('click', () => this.resetFilters());
    ['dateFilter','mealFilter','classFilter','streamFilter','genderFilter','dormitoryFilter','boardingStatusFilter','dietTypeFilter'].forEach((id) => document.getElementById(id)?.addEventListener('change', () => this.load()));
    document.getElementById('searchBox')?.addEventListener('input', () => { clearTimeout(this.searchTimer); this.searchTimer = setTimeout(() => this.load(), 300); });
    document.getElementById('studentsTableBody')?.addEventListener('click', (event) => {
      const button = event.target.closest('[data-student-profile]');
      if (button) this.showProfile(Number(button.dataset.studentProfile));
    });
    document.getElementById('planTodayBtn')?.addEventListener('click', () => this.showPlanModal());
    document.getElementById('mealPlanForm')?.addEventListener('submit', (event) => { event.preventDefault(); this.saveMealPlan(); });
    document.getElementById('saveMealPlanBtn')?.addEventListener('click', () => document.getElementById('mealPlanForm')?.requestSubmit());
    document.getElementById('foodStoreRequisitionBtn')?.addEventListener('click', () => this.loadFoodRequisition());
    document.getElementById('exportMealSheetBtn')?.addEventListener('click', () => this.exportCSV());
    document.getElementById('printMealCountBtn')?.addEventListener('click', () => this.print());
    // These legacy controls have no matching authorized write endpoint yet.
    ['markNotEatingBtn','addDietNoteBtn'].forEach((id) => { const button = document.getElementById(id); if (button) { button.disabled = true; button.title = 'This action is not available in the current catering API.'; } });
    await this.loadMeta();
    await this.load();
    window.APIRealtime?.register?.('catering_boarding_students', { refresh: () => this.load() }, ['boarding', 'catering']);
  },

  filters() {
    const value = (id) => document.getElementById(id)?.value || '';
    return { date: value('dateFilter') || this.today(), class_id: value('classFilter'), stream_id: value('streamFilter'), gender: value('genderFilter'), dormitory_id: value('dormitoryFilter'), diet_type: value('dietTypeFilter'), boarding_status: value('boardingStatusFilter'), search: value('searchBox') };
  },
  async loadMeta() {
    try {
      this.state.meta = this.data(await this.API('GET', '/api/students/catering-boarding-meta'));
      this.renderFilterOptions();
    } catch (error) {
      this.showError(error?.message || 'Catering filter options could not be loaded.');
    }
  },
  async load() {
    this.setState('loading');
    const params = this.filters();
    try {
      const qs = new URLSearchParams(Object.entries(params).filter(([, value]) => value !== '')).toString();
      const [studentResponse, summaryResponse] = await Promise.all([
        this.API('GET', `/api/students/catering-boarding-students?${qs}`),
        this.API('GET', `/api/students/catering-boarding-summary?date=${encodeURIComponent(params.date)}`),
      ]);
      const students = this.data(studentResponse);
      this.state.students = Array.isArray(students) ? students : Array.isArray(students.students) ? students.students : [];
      this.state.summary = this.data(summaryResponse);
      this.render();
      this.setState(this.state.students.length ? 'ready' : 'empty');
    } catch (error) {
      this.setState('error', error?.message || 'Boarding meal data could not be loaded.');
      const body = document.getElementById('studentsTableBody');
      if (body) body.innerHTML = '<tr><td colspan="14" class="text-center text-danger py-4">Boarding meal data could not be loaded.</td></tr>';
    }
  },
  setState(state, message = '') {
    const show = (id, visible) => document.getElementById(id)?.classList.toggle('d-none', !visible);
    show('studentsLoading', state === 'loading'); show('studentsError', state === 'error'); show('studentsForbidden', state === 'forbidden'); show('studentsEmpty', state === 'empty');
    const error = document.getElementById('studentsError'); if (error && message) error.textContent = message;
    if (state === 'forbidden') show('studentsError', false);
  },
  showError(message) { this.setState('error', message); },
  renderFilterOptions() {
    const options = (id, rows, labelKey, valueKey) => {
      const select = document.getElementById(id); if (!select) return;
      const first = select.options[0]?.outerHTML || '<option value="">All</option>';
      select.innerHTML = first + rows.map((row) => `<option value="${this.esc(row[valueKey])}">${this.esc(row[labelKey])}</option>`).join('');
    };
    options('classFilter', this.state.meta.classes || [], 'name', 'id');
    options('streamFilter', this.state.meta.streams || [], 'name', 'id');
    options('dormitoryFilter', this.state.meta.dormitories || [], 'dormitory_name', 'id');
  },
  render() {
    const filters = this.filters();
    const students = this.state.students.filter((student) => {
      const term = `${student.full_name || ''} ${student.admission_no || ''}`.toLowerCase();
      if (filters.gender && String(student.gender || '').toLowerCase() !== filters.gender.toLowerCase()) return false;
      if (filters.dormitory_id && Number(student.dormitory_id) !== Number(filters.dormitory_id)) return false;
      if (filters.diet_type && String(student.diet_type || 'normal').toLowerCase() !== filters.diet_type) return false;
      if (filters.class_id && Number(student.class_id) !== Number(filters.class_id)) return false;
      if (filters.stream_id && student.stream_id != null && Number(student.stream_id) !== Number(filters.stream_id)) return false;
      if (filters.search && !term.includes(filters.search.toLowerCase())) return false;
      if (filters.boarding_status && String(student.boarding_status || '') !== filters.boarding_status) return false;
      return true;
    });
    const meal = document.getElementById('mealFilter')?.value;
    this.state.visibleStudents = students;
    const body = document.getElementById('studentsTableBody');
    if (body) body.innerHTML = students.length ? students.map((student) => {
      const mealCell = (eating) => eating ? '<span class="badge text-bg-success">Yes</span>' : '<span class="badge text-bg-secondary">No</span>';
      return `<tr><td>${this.esc(student.admission_no || '—')}</td><td>${this.esc(student.full_name || '—')}</td><td>${this.esc(student.class_name || '—')}</td><td>${this.esc(student.stream_name || '—')}</td><td>${this.esc(student.gender || '—')}</td><td>${this.esc(student.dormitory_name || '—')}</td><td>${this.esc(student.boarding_status || '—')}</td><td>${mealCell(student.breakfast)}</td><td>${mealCell(student.lunch)}</td><td>${mealCell(student.supper)}</td><td>${this.esc(student.diet_type || 'normal')}</td><td>${Number(student.has_food_restriction) ? 'Yes' : 'No'}</td><td>${this.esc(String(student.meal_status_today || '—').replaceAll('_',' '))}</td><td><button class="btn btn-sm btn-outline-primary" data-student-profile="${Number(student.student_id)}">Meal profile</button></td></tr>`;
    }).join('') : '<tr><td colspan="14" class="text-center text-muted py-4">No boarding students match these filters.</td></tr>';
    this.renderSummary();
    if (meal) this.filterMealColumn(meal);
  },
  filterMealColumn(meal) {
    const index = { breakfast: 7, lunch: 8, supper: 9 }[meal];
    document.querySelectorAll('#studentsTableBody tr').forEach((row) => {
      if (index === undefined) return;
      const value = row.cells[index]?.textContent?.trim();
      row.classList.toggle('d-none', value === 'No');
    });
  },
  renderSummary() {
    const summary = this.state.summary;
    const set = (id, value) => { const element = document.getElementById(id); if (element) element.textContent = String(value ?? 0); };
    set('totalBoarders', summary.total_boarders);
    set('breakfastCount', summary.breakfast_count); set('lunchCount', summary.lunch_count); set('supperCount', summary.supper_count);
    set('specialDietCount', summary.special_diet_count); set('absentLeaveCount', summary.absent_or_leave_count);
    set('sickBayCount', summary.sick_meal_count); set('storeItemsCount', summary.food_store_items_required);
    const renderBreakdown = (id, rows, key) => {
      const el = document.getElementById(id); if (!el) return;
      el.innerHTML = (Array.isArray(rows) && rows.length) ? rows.map((row) => `<div class="d-flex justify-content-between border-bottom py-1"><span>${this.esc(row[key] || 'Unassigned')}</span><strong>${Number(row.count || 0)}</strong></div>`).join('') : '<p class="text-muted mb-0">No breakdown data available.</p>';
    };
    renderBreakdown('breakdownByClass', summary.breakdown_by_class, 'class_name');
    renderBreakdown('breakdownByDiet', summary.breakdown_by_diet, 'diet_type');
  },
  async showProfile(studentId) {
    const body = document.getElementById('modalMealContent'); if (!body) return;
    const box = document.getElementById('modalError'); box?.classList.add('d-none');
    const loading = document.getElementById('modalLoading'); loading?.classList.remove('d-none'); body.innerHTML = '';
    try {
      const data = this.data(await this.API('GET', `/api/students/catering-boarding-student/${studentId}`));
      const student = data.student || {}; const diet = data.diet || {};
      document.getElementById('modalSubtitle').textContent = `${student.first_name || ''} ${student.last_name || ''} · ${student.admission_no || ''}`;
      body.innerHTML = `<div class="row g-3"><div class="col-md-6"><strong>Class</strong><div>${this.esc(data.class_name || '—')} ${this.esc(data.stream_name || '')}</div></div><div class="col-md-6"><strong>Dormitory</strong><div>${this.esc(data.dormitory_name || '—')}</div></div><div class="col-md-6"><strong>Diet</strong><div>${this.esc(diet.diet_type || 'normal')}</div></div><div class="col-md-6"><strong>Restrictions</strong><div>${this.esc(diet.food_restrictions || diet.allergy_notes || 'None recorded')}</div></div><div class="col-12"><strong>Recent meal history</strong>${(data.meal_history || []).length ? `<ul class="small mt-2">${data.meal_history.map((item) => `<li>${this.esc(item.meal_date)} · ${this.esc(item.meal_type)} · ${this.esc(item.status)} ${this.esc(item.notes || '')}</li>`).join('')}</ul>` : '<p class="text-muted small mt-2">No meal history recorded.</p>'}</div></div>`;
      this.state.selectedStudent = studentId;
      window.bootstrap?.Modal.getOrCreateInstance(document.getElementById('mealProfileModal'))?.show();
    } catch (error) { if (box) { box.textContent = error.message || 'Meal profile could not be loaded.'; box.classList.remove('d-none'); } }
    finally { loading?.classList.add('d-none'); }
  },
  showPlanModal() {
    const date = document.getElementById('planDate'); if (date && !date.value) date.value = this.filters().date;
    const count = document.getElementById('planExpectedCount'); if (count) count.value = this.state.visibleStudents.length;
    window.bootstrap?.Modal.getOrCreateInstance(document.getElementById('mealPlanModal'))?.show();
  },
  async saveMealPlan() {
    const payload = { date: document.getElementById('planDate')?.value, meal_type: document.getElementById('planMealType')?.value, expected_count: Number(document.getElementById('planExpectedCount')?.value || 0), notes: document.getElementById('planNotes')?.value || '' };
    const button = document.getElementById('saveMealPlanBtn'); if (button) button.disabled = true;
    try {
      await this.API('POST', '/api/students/catering-menu-plan', payload);
      window.bootstrap?.Modal.getInstance(document.getElementById('mealPlanModal'))?.hide();
      window.showNotification?.('Meal plan saved.', 'success');
    } catch (error) { window.showNotification?.(error.message || 'Meal plan could not be saved.', 'danger'); }
    finally { if (button) button.disabled = false; }
  },
  async loadFoodRequisition() {
    const box = document.getElementById('foodStoreContent'); const loading = document.getElementById('foodStoreLoading');
    loading?.classList.remove('d-none');
    try {
      const data = this.data(await this.API('GET', '/api/students/catering-food-requisition'));
      box.innerHTML = data.available ? `<p class="text-muted mb-0">${this.esc(data.message || 'No food requisition items are available.')}</p>` : `<p class="text-muted mb-0">${this.esc(data.message || 'Food requisition data is unavailable.')}</p>`;
    } catch (error) { box.textContent = error.message || 'Food requisition data could not be loaded.'; }
    finally { loading?.classList.add('d-none'); }
  },
  resetFilters() {
    ['mealFilter','classFilter','streamFilter','genderFilter','dormitoryFilter','boardingStatusFilter','dietTypeFilter','searchBox'].forEach((id) => { const el = document.getElementById(id); if (el) el.value = ''; });
    const date = document.getElementById('dateFilter'); if (date) date.value = this.today();
    this.load();
  },
  exportCSV() {
    if (window.AuthContext?.canExport && !window.AuthContext.canExport('catering')) return window.showNotification?.('You do not have permission to export catering data.', 'error');
    const columns = ['admission_no','full_name','class_name','stream_name','gender','dormitory_name','boarding_status','breakfast','lunch','supper','diet_type','has_food_restriction','meal_status_today'];
    const csv = [columns, ...this.state.visibleStudents.map((student) => columns.map((key) => student[key] ?? ''))].map((row) => row.map((value) => `"${String(value).replaceAll('"','""')}"`).join(',')).join('\r\n');
    window.KingswayFileLifecycle?.exportText?.(csv, 'boarding-meal-count.csv', 'text/csv;charset=utf-8;');
  },
  print() { if (window.AuthContext?.canPrint && !window.AuthContext.canPrint('catering')) return window.showNotification?.('You do not have permission to print catering data.', 'error'); window.print(); },
};
window.cateringBoardingStudentsController = cateringBoardingStudentsController;
document.addEventListener('DOMContentLoaded', () => cateringBoardingStudentsController.init().catch((error) => cateringBoardingStudentsController.showError(error?.message || 'Catering page setup failed.')));
