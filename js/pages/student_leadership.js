/** Student leadership, houses and awards page controller. */
const StudentLeadershipController = {
  root: null,
  initialized: false,
  state: {
    referenceCache: null,
    leadership: [],
    houses: [],
    positions: [],
    awards: [],
    categories: [],
    awardTypes: [],
    students: [],
    years: [],
    terms: [],
    departments: [],
    editingPositionId: null,
    editingAwardTypeId: null,
  },

  isMounted() { return Boolean(this.root?.isConnected); },
  el(id) {
    const element = document.getElementById(id);
    return element && this.root?.contains(element) ? element : null;
  },
  esc(value) {
    return String(value ?? '').replace(/[&<>"']/g, (char) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    })[char]);
  },
  listFrom(response, keys = []) {
    let value = response;
    for (let depth = 0; depth < 6; depth += 1) {
      if (Array.isArray(value)) return value;
      if (!value || typeof value !== 'object') return [];
      const candidate = keys.map((key) => value[key]).find((item) => item !== undefined);
      value = candidate ?? value.data ?? value.items ?? value.rows ?? value.records ?? value.sources;
    }
    return Array.isArray(value) ? value : [];
  },
  responseData(response) {
    let value = response;
    for (let depth = 0; depth < 5 && value && typeof value === 'object'; depth += 1) {
      if (value.data !== undefined) value = value.data;
      else break;
    }
    return value;
  },
  async apiCall(method, endpoint, data, params) {
    return window.callAPI(endpoint, method, data, params);
  },
  async loadReferencesComposed() {
    if (!this.isMounted()) return;
    try {
      const response = await window.API.nodeViews.read('students', 'student-leadership-references');
      if (!this.isMounted()) return;
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn('Student Leadership composed references unavailable; loading through PHP APIs.', error);
      this.state.referenceCache = null;
    }
  },
  async readList(cacheKey, request, responseKeys = []) {
    const cached = this.state.referenceCache?.[cacheKey];
    const response = cached !== undefined ? cached : await request();
    return this.listFrom(response, responseKeys);
  },
  async loadAllStudents() {
    const pageSize = 100;
    const requestPage = async (page) => window.API.students.contextList({ context: 'academic', page, limit: pageSize });
    const first = await requestPage(1);
    const all = this.listFrom(first, ['students', 'records']);
    const payload = this.responseData(first);
    const pagination = payload?.pagination || first?.pagination || {};
    const totalPages = Math.max(1, Number(pagination.total_pages ?? pagination.pages ?? 1));
    for (let start = 2; start <= totalPages; start += 5) {
      const pages = Array.from({ length: Math.min(5, totalPages - start + 1) }, (_, index) => start + index);
      const results = await Promise.all(pages.map(requestPage));
      results.forEach((response) => all.push(...this.listFrom(response, ['students', 'records'])));
      if (!this.isMounted()) return [];
    }
    return all;
  },
  async load() {
    if (!this.isMounted()) return;
    this.setTableMessage('leadershipTable', 9, 'Loading leadership records…');
    this.setTableMessage('leadPositionsTable', 5, 'Loading positions…');
    this.setTableMessage('awardsTable', 9, 'Loading awards…');
    const results = await Promise.allSettled([
      this.apiCall('GET', '/api/students/leadership'),
      this.readList('houses', () => window.API.students.houses.list()),
      this.readList('positions', () => window.API.students.leadership.positions()),
      this.apiCall('GET', '/api/students/awards'),
      this.readList('categories', () => window.API.students.awards.categories()),
      this.readList('types', () => window.API.students.awards.types.list()),
      this.loadAllStudents(),
      window.API.academic.getAllAcademicYears(),
      window.API.academic.listTerms(),
      window.API.staff.getDepartments(),
    ]);
    if (!this.isMounted()) return;

    const value = (index, fallback = []) => results[index].status === 'fulfilled'
      ? results[index].value
      : fallback;
    this.state.leadership = this.listFrom(value(0), ['leadership', 'records']);
    this.state.houses = value(1);
    this.state.positions = value(2);
    this.state.awards = this.listFrom(value(3), ['awards', 'records']);
    this.state.categories = value(4);
    this.state.awardTypes = value(5);
    this.state.students = this.listFrom(value(6), ['students', 'records']);
    this.state.years = this.listFrom(value(7), ['years', 'academic_years']);
    this.state.terms = this.listFrom(value(8), ['terms']);
    this.state.departments = this.listFrom(value(9), ['departments']);

    this.renderFilters();
    this.renderFormOptions();
    this.renderLeadership();
    this.renderPositions();
    this.renderHouses();
    this.renderAwards();
    this.renderAwardTypes();
    this.renderKpis();
    this.showLoadErrors(results);
  },
  setTableMessage(id, colspan, message, type = 'muted') {
    const body = this.el(id);
    if (body) body.innerHTML = `<tr><td colspan="${colspan}" class="text-center py-3 text-${type}">${this.esc(message)}</td></tr>`;
  },
  showLoadErrors(results) {
    const mappings = [
      [0, 'leadershipTable', 9, 'Leadership records could not be loaded.'],
      [2, 'leadPositionsTable', 5, 'Leadership positions could not be loaded.'],
      [3, 'awardsTable', 9, 'Awards could not be loaded.'],
    ];
    mappings.forEach(([index, id, span, message]) => {
      if (results[index].status === 'rejected') this.setTableMessage(id, span, message, 'danger');
    });
    results.forEach((result, index) => {
      if (result.status === 'rejected') console.error(`Student Leadership data source ${index + 1} failed`, result.reason);
    });
  },
  renderFilters() {
    const setOptions = (id, firstLabel, records, getValue, getLabel) => {
      const select = this.el(id);
      if (!select) return;
      const previous = select.value;
      select.innerHTML = `<option value="">${this.esc(firstLabel)}</option>` + records.map((record) =>
        `<option value="${this.esc(getValue(record))}">${this.esc(getLabel(record))}</option>`
      ).join('');
      if ([...select.options].some((option) => option.value === previous)) select.value = previous;
    };
    setOptions('leadYearFilter', 'All academic years', this.state.years,
      (year) => year.id, (year) => year.year_name || year.name || year.year_code || `Year ${year.id}`);
    setOptions('leadCatFilter', 'All categories', this.uniqueCategories(),
      (category) => category.id, (category) => category.name || category.code || 'Leadership');
    setOptions('leadStudentFilter', 'All students', this.state.students,
      (student) => student.id || student.student_id,
      (student) => student.full_name || student.name || [student.first_name, student.last_name].filter(Boolean).join(' ') || student.admission_no || 'Student');
    setOptions('awardCatFilter', 'All award categories', this.state.categories,
      (category) => category.id, (category) => category.name || category.code);
  },
  canManage() {
    const context = window.AuthContext;
    if (!context) return false;
    if (context.hasPermission?.('*') || context.hasAnyPermission?.(['student_leadership_manage'])) return true;
    return ['School Administrator', 'Headteacher', 'Deputy Head Academic', 'Deputy Head Discipline']
      .some((role) => context.hasRole?.(role));
  },
  updateAccessUI() {
    if (!this.isMounted()) return;
    const canManage = this.canManage();
    const banner = this.el('leadReadonlyBanner');
    if (banner) banner.classList.toggle('d-none', canManage);
    this.root.querySelectorAll('.lead-manage-only').forEach((element) => {
      element.classList.toggle('d-none', !canManage);
    });
  },
  uniqueCategories() {
    const found = new Map();
    this.state.positions.forEach((position) => {
      const id = Number(position.leadership_category_id || 0);
      if (id && !found.has(id)) found.set(id, {
        id,
        name: position.leadership_category_name,
        code: position.leadership_category_code,
      });
    });
    return [...found.values()];
  },
  renderFormOptions() {
    const optionList = (records, getValue, getLabel) => records.map((record) =>
      `<option value="${this.esc(getValue(record))}">${this.esc(getLabel(record))}</option>`
    ).join('');
    const studentOptions = optionList(this.state.students,
      (student) => student.id || student.student_id,
      (student) => `${student.full_name || student.name || [student.first_name, student.last_name].filter(Boolean).join(' ') || 'Student'}${student.admission_no ? ` (${student.admission_no})` : ''}`);
    ['leadStudent', 'awardStudent'].forEach((id) => {
      const select = this.el(id);
      if (select) select.innerHTML = `<option value="">Select student…</option>${studentOptions}`;
    });
    const positionOptions = optionList(this.state.positions.filter((item) => Number(item.is_active) !== 0),
      (position) => position.id, (position) => position.name);
    const positionSelect = this.el('leadPosition');
    if (positionSelect) positionSelect.innerHTML = `<option value="">Select position…</option>${positionOptions}`;
    const categoryOptions = optionList(this.uniqueCategories(), (item) => item.id, (item) => item.name || item.code);
    const categorySelect = this.el('leadCategory');
    if (categorySelect) categorySelect.innerHTML = `<option value="">Select category…</option>${categoryOptions}`;
    const houseOptions = optionList(this.state.houses, (house) => house.id, (house) => house.name);
    const houseSelect = this.el('leadHouse');
    if (houseSelect) houseSelect.innerHTML = `<option value="">— None —</option>${houseOptions}`;
    const yearOptions = optionList(this.state.years, (year) => year.id,
      (year) => year.year_name || year.name || year.year_code || `Year ${year.id}`);
    const yearSelect = this.el('leadYear');
    if (yearSelect) yearSelect.innerHTML = `<option value="">Select year…</option>${yearOptions}`;
    this.renderTermsForYear();
    const awardYearSelect = this.el('awardYear');
    if (awardYearSelect) awardYearSelect.innerHTML = `<option value="">Select year…</option>${yearOptions}`;
    this.renderAwardTerms();
    const awardCategoryOptions = optionList(this.state.categories, (item) => item.id, (item) => item.name || item.code);
    ['awardCategory', 'atCategory'].forEach((id) => {
      const select = this.el(id);
      if (select) select.innerHTML = `<option value="">Select category…</option>${awardCategoryOptions}`;
    });
    const templates = [
      ['achievement', 'Achievement'], ['participation', 'Participation'], ['leadership', 'Leadership'],
      ['service', 'Service'], ['custom', 'Custom'],
    ];
    const templateSelect = this.el('atTemplate');
    if (templateSelect) templateSelect.innerHTML = '<option value="">Default</option>' + templates.map(([value, label]) => `<option value="${value}">${label}</option>`).join('');
    const departments = this.state.departments || [];
    const departmentSelect = this.el('atDepartment');
    if (departmentSelect && departments.length) departmentSelect.innerHTML = '<option value="">School-wide</option>' + optionList(departments, (item) => item.id, (item) => item.name);
  },
  renderTermsForYear() {
    const yearId = Number(this.el('leadYear')?.value || 0);
    const select = this.el('leadTerm');
    if (!select) return;
    const terms = this.state.terms.filter((term) => !yearId || Number(term.academic_year_id ?? term.year) === yearId);
    select.innerHTML = '<option value="">All year</option>' + terms.map((term) =>
      `<option value="${Number(term.id)}">${this.esc(term.name || term.term_name || term.term_number || 'Term')}</option>`
    ).join('');
  },
  renderAwardTerms() {
    const yearId = Number(this.el('awardYear')?.value || 0);
    const select = this.el('awardTerm');
    if (!select) return;
    const terms = this.state.terms.filter((term) => !yearId || Number(term.academic_year_id ?? term.year) === yearId);
    select.innerHTML = '<option value="">Whole year / not specified</option>' + terms.map((term) =>
      `<option value="${Number(term.id)}">${this.esc(term.name || term.term_name || term.term_number || 'Term')}</option>`
    ).join('');
  },
  renderKpis() {
    const host = this.el('leadKpis');
    if (!host) return;
    const values = [
      ['Leadership assignments', this.state.leadership.length],
      ['Active positions', this.state.positions.filter((item) => Number(item.is_active) === 1).length],
      ['School houses', this.state.houses.length],
      ['Awards recorded', this.state.awards.length],
    ];
    host.innerHTML = values.map(([label, value]) => `<div class="col-6 col-xl-3"><div class="mini-stat"><div class="v">${Number(value)}</div><div class="l">${this.esc(label)}</div></div></div>`).join('');
  },
  renderLeadership() {
    const body = this.el('leadershipTable');
    if (!body) return;
    if (!this.state.leadership.length) return this.setTableMessage('leadershipTable', 9, 'No leadership assignments match these filters.');
    const canManage = this.canManage();
    body.innerHTML = this.state.leadership.map((item) => {
      const term = item.term_number ? ` · Term ${this.esc(item.term_number)}` : '';
      const yearTerm = `${this.esc(item.academic_year_name || '—')}${term}`;
      return `<tr>
        <td class="small">${this.esc(item.student_name || '—')}</td><td class="small">${this.esc(item.admission_no || '—')}</td>
        <td class="small">${this.esc(item.position_name || '—')}</td><td class="small">${this.esc(item.leadership_category_name || '—')}</td>
        <td class="small">${yearTerm}</td><td class="small">${this.esc(item.house_name || '—')}</td>
        <td class="small">${this.esc(item.class_stream || '—')}</td><td>${this.statusBadge(Number(item.is_active) === 1 ? 'active' : 'inactive')}</td>
        <td class="text-end">${canManage ? `<button type="button" class="btn btn-sm btn-outline-danger" onclick="StudentLeadershipController.removeLeadership(${Number(item.id)})" aria-label="Remove assignment"><i class="fas fa-trash"></i></button>` : ''}</td>
      </tr>`;
    }).join('');
  },
  renderPositions() {
    const body = this.el('leadPositionsTable');
    if (!body) return;
    if (!this.state.positions.length) return this.setTableMessage('leadPositionsTable', 5, 'No student leadership positions are configured.');
    body.innerHTML = this.state.positions.map((position) => `<tr>
      <td>${this.esc(position.name)}</td><td>${this.esc(position.description || '—')}</td>
      <td>${position.max_holders ? Number(position.max_holders) : 'No limit'}</td>
      <td>${this.statusBadge(Number(position.is_active) === 1 ? 'active' : 'inactive')}</td>
      <td class="text-end"><button type="button" class="btn btn-sm btn-outline-primary" onclick="StudentLeadershipController.editPosition(${Number(position.id)})" aria-label="Edit position"><i class="fas fa-pen"></i></button></td>
    </tr>`).join('');
  },
  renderHouses() {
    const grid = this.el('housesGrid');
    if (!grid) return;
    if (!this.state.houses.length) {
      grid.innerHTML = '<div class="col-12"><div class="empty-state">No school houses have been configured.</div></div>';
      return;
    }
    const canManage = this.canManage();
    grid.innerHTML = this.state.houses.map((house) => `<div class="col-sm-6 col-xl-4"><div class="card lead-directory-card h-100 border-0 shadow-sm"><div class="card-body">
      <div class="d-flex justify-content-between align-items-start"><h5 class="mb-1">${this.esc(house.name)}</h5><span class="house-chip" style="background:${this.safeColor(house.color)}">${this.esc(house.code || 'HOUSE')}</span></div>
      <div class="text-muted small">${this.esc(house.motto || house.mascot || 'School house')}</div>
      <div class="mt-3 small">Patron: ${this.esc(house.patron_name || 'Not assigned')} · ${Number(house.active_members || 0)} learners</div>
      <div class="mt-2">${this.statusBadge(Number(house.is_active) === 1 ? 'active' : 'inactive')}</div>
      ${canManage ? `<button type="button" class="btn btn-sm btn-outline-primary mt-3" onclick="StudentLeadershipController.editHouse(${Number(house.id)})">Edit house</button>` : ''}
    </div></div></div>`).join('');
  },
  renderAwards() {
    const body = this.el('awardsTable');
    if (!body) return;
    if (!this.state.awards.length) return this.setTableMessage('awardsTable', 9, 'No awards match these filters.');
    body.innerHTML = this.state.awards.map((award) => {
      const yearTerm = `${this.esc(award.academic_year_name || '—')}${award.term_number ? ` · Term ${this.esc(award.term_number)}` : ''}`;
      return `<tr><td>${this.esc(award.student_name || '—')}</td><td>${this.esc(award.admission_no || '—')}</td>
        <td>${this.esc(award.title || '—')}</td><td>${this.esc(award.award_type_name || award.award_type_code || '—')}</td>
        <td>${yearTerm}</td><td>${this.esc(award.generated_certificate_number || award.certificate_no || '—')}</td>
        <td>${this.fmtDate(award.issue_date)}</td><td>${this.statusBadge(award.status)}</td>
        <td class="text-end">${award.certificate_pdf_path ? '<i class="fas fa-file-pdf text-success" title="Certificate generated"></i>' : '—'}</td></tr>`;
    }).join('');
  },
  renderAwardTypes() {
    const body = this.el('awardTypesTable');
    if (!body) return;
    if (!this.state.awardTypes.length) return this.setTableMessage('awardTypesTable', 8, 'No award types are configured.');
    body.innerHTML = this.state.awardTypes.map((item) => `<tr><td>${this.esc(item.code)}</td><td>${this.esc(item.name)}</td>
      <td>${this.esc(item.category_name || '—')}</td><td>${this.esc(item.department_name || 'School-wide')}</td>
      <td>${this.esc(item.template_key || 'Default')}</td><td>${this.esc(item.number_prefix || '—')}</td>
      <td>${this.esc([item.signatory_label, item.secondary_signatory_label].filter(Boolean).join(' / ') || '—')}</td>
      <td class="text-end"><button type="button" class="btn btn-sm btn-outline-primary" onclick="StudentLeadershipController.editAwardType(${Number(item.id)})" aria-label="Edit award type"><i class="fas fa-pen"></i></button> <button type="button" class="btn btn-sm btn-outline-danger" onclick="StudentLeadershipController.deleteAwardType(${Number(item.id)})" aria-label="Delete award type"><i class="fas fa-trash"></i></button></td></tr>`).join('');
  },
  statusBadge(status) {
    const normalized = String(status || 'unknown').toLowerCase();
    const color = ['active', 'awarded', 'current'].includes(normalized) ? 'success'
      : ['revoked', 'inactive', 'expired'].includes(normalized) ? 'secondary' : 'warning text-dark';
    return `<span class="badge bg-${color}">${this.esc(normalized.replaceAll('_', ' '))}</span>`;
  },
  safeColor(color) {
    const value = String(color || '').trim();
    return /^(#[0-9a-f]{3,8}|[a-z]{3,20})$/i.test(value) ? value : '#1a2980';
  },
  fmtDate(value) {
    if (!value) return '—';
    const parsed = new Date(String(value).replace(' ', 'T'));
    return Number.isNaN(parsed.getTime()) ? this.esc(value) : this.esc(parsed.toLocaleDateString());
  },
  async loadLeadership() {
    if (!this.isMounted()) return;
    const params = {};
    const year = this.el('leadYearFilter')?.value;
    const category = this.el('leadCatFilter')?.value;
    const student = this.el('leadStudentFilter')?.value;
    if (year) params.academic_year_id = Number(year);
    if (category) params.leadership_category_id = Number(category);
    if (student) params.student_id = Number(student);
    try {
      const response = await window.API.students.leadership.list(params);
      if (!this.isMounted()) return;
      this.state.leadership = this.listFrom(response, ['leadership', 'records']);
      this.renderLeadership();
      this.renderKpis();
    } catch (error) {
      console.error('Unable to load leadership assignments.', error);
      this.setTableMessage('leadershipTable', 9, 'Leadership assignments could not be loaded.', 'danger');
    }
  },
  resetLeadershipFilters() {
    ['leadYearFilter', 'leadCatFilter', 'leadStudentFilter'].forEach((id) => { const el = this.el(id); if (el) el.value = ''; });
    this.loadLeadership();
  },
  async loadAwards() {
    if (!this.isMounted()) return;
    const params = {};
    const categoryId = this.el('awardCatFilter')?.value;
    if (categoryId) params.award_category_id = Number(categoryId);
    try {
      const response = await window.API.students.awards.list(params);
      if (!this.isMounted()) return;
      this.state.awards = this.listFrom(response, ['awards', 'records']);
      this.renderAwards();
      this.renderKpis();
    } catch (error) {
      console.error('Unable to load awards.', error);
      this.setTableMessage('awardsTable', 9, 'Awards could not be loaded.', 'danger');
    }
  },
  openModal(id) {
    const modal = this.el(id);
    if (modal && window.bootstrap?.Modal) window.bootstrap.Modal.getOrCreateInstance(modal).show();
  },
  hideModal(id) {
    const modal = this.el(id);
    if (modal && window.bootstrap?.Modal) window.bootstrap.Modal.getOrCreateInstance(modal).hide();
  },
  openPosition() {
    if (!this.canManage()) return this.notify('You do not have permission to manage leadership positions.', 'warning');
    this.state.editingPositionId = null;
    const title = this.el('leadPositionModalTitle');
    if (title) title.textContent = 'Add student leadership position';
    if (this.el('leadPositionName')) this.el('leadPositionName').value = '';
    if (this.el('leadPositionDescription')) this.el('leadPositionDescription').value = '';
    if (this.el('leadPositionMaxHolders')) this.el('leadPositionMaxHolders').value = '';
    if (this.el('leadPositionOrder')) this.el('leadPositionOrder').value = '0';
    if (this.el('leadPositionActive')) this.el('leadPositionActive').checked = true;
    this.openModal('leadPositionModal');
  },
  editPosition(id) {
    const item = this.state.positions.find((position) => Number(position.id) === Number(id));
    if (!item) return;
    this.state.editingPositionId = Number(id);
    if (this.el('leadPositionModalTitle')) this.el('leadPositionModalTitle').textContent = 'Edit student leadership position';
    if (this.el('leadPositionName')) this.el('leadPositionName').value = item.name || '';
    if (this.el('leadPositionDescription')) this.el('leadPositionDescription').value = item.description || '';
    if (this.el('leadPositionMaxHolders')) this.el('leadPositionMaxHolders').value = item.max_holders || '';
    if (this.el('leadPositionOrder')) this.el('leadPositionOrder').value = item.display_order || 0;
    if (this.el('leadPositionActive')) this.el('leadPositionActive').checked = Number(item.is_active) === 1;
    this.openModal('leadPositionModal');
  },
  async savePosition() {
    if (!this.canManage()) return this.notify('You do not have permission to manage leadership positions.', 'warning');
    const name = this.el('leadPositionName')?.value.trim();
    if (!name) return this.notify('Enter a position name.', 'warning');
    const payload = {
      name,
      description: this.el('leadPositionDescription')?.value || '',
      max_holders: this.el('leadPositionMaxHolders')?.value || null,
      display_order: Number(this.el('leadPositionOrder')?.value || 0),
      is_active: this.el('leadPositionActive')?.checked ? 1 : 0,
    };
    try {
      const id = this.state.editingPositionId;
      const response = id
        ? await window.API.students.leadership.updatePosition(id, payload)
        : await window.API.students.leadership.createPosition(payload);
      this.assertSuccess(response);
      this.hideModal('leadPositionModal');
      await this.refresh();
      this.notify('Leadership position saved.', 'success');
    } catch (error) { this.notify(error.message || 'Could not save the position.', 'danger'); }
  },
  openAssign() {
    if (!this.canManage()) return this.notify('You do not have permission to assign leadership.', 'warning');
    this.renderFormOptions(); this.openModal('assignLeadershipModal');
  },
  async saveLeadership() {
    if (!this.canManage()) return this.notify('You do not have permission to assign leadership.', 'warning');
    const studentId = Number(this.el('leadStudent')?.value || 0);
    const positionId = Number(this.el('leadPosition')?.value || 0);
    if (!studentId || !positionId) return this.notify('Select a student and leadership position.', 'warning');
    const payload = {
      student_id: studentId,
      position_id: positionId,
      house_id: this.el('leadHouse')?.value || null,
      academic_year_id: Number(this.el('leadYear')?.value || 0) || null,
      academic_year_term_id: this.el('leadTerm')?.value || null,
      start_date: this.el('leadStart')?.value || null,
      public_bio: this.el('leadBio')?.value || null,
    };
    try {
      this.assertSuccess(await window.API.students.leadership.create(payload));
      this.hideModal('assignLeadershipModal');
      await this.refresh();
      this.notify('Leadership assignment saved.', 'success');
    } catch (error) { this.notify(error.message || 'Could not save the leadership assignment.', 'danger'); }
  },
  async removeLeadership(id) {
    if (!this.canManage()) return this.notify('You do not have permission to remove leadership.', 'warning');
    if (!window.confirm('Remove this leadership assignment?')) return;
    try {
      this.assertSuccess(await window.API.students.leadership.delete(Number(id)));
      await this.refresh();
      this.notify('Leadership assignment removed.', 'success');
    } catch (error) { this.notify(error.message || 'Could not remove the assignment.', 'danger'); }
  },
  openHouse() {
    if (!this.canManage()) return this.notify('You do not have permission to manage houses.', 'warning');
    this.state.editingHouseId = null;
    if (this.el('houseModalTitle')) this.el('houseModalTitle').textContent = 'Add House';
    ['houseName', 'houseCode', 'houseColor', 'houseMascot', 'houseMotto'].forEach((id) => { if (this.el(id)) this.el(id).value = ''; });
    if (this.el('houseOrder')) this.el('houseOrder').value = '0';
    if (this.el('houseActive')) this.el('houseActive').checked = true;
    this.openModal('houseModal');
  },
  editHouse(id) {
    const item = this.state.houses.find((house) => Number(house.id) === Number(id));
    if (!item) return;
    this.state.editingHouseId = Number(id);
    if (this.el('houseModalTitle')) this.el('houseModalTitle').textContent = 'Edit House';
    const map = { houseName: 'name', houseCode: 'code', houseColor: 'color', houseMascot: 'mascot', houseMotto: 'motto', houseOrder: 'display_order' };
    Object.entries(map).forEach(([idKey, field]) => { if (this.el(idKey)) this.el(idKey).value = item[field] ?? ''; });
    if (this.el('houseActive')) this.el('houseActive').checked = Number(item.is_active) === 1;
    this.openModal('houseModal');
  },
  async saveHouse() {
    if (!this.canManage()) return this.notify('You do not have permission to manage houses.', 'warning');
    const name = this.el('houseName')?.value.trim();
    if (!name) return this.notify('Enter a house name.', 'warning');
    const payload = {
      name, code: this.el('houseCode')?.value || '', color: this.el('houseColor')?.value || '',
      mascot: this.el('houseMascot')?.value || '', motto: this.el('houseMotto')?.value || '',
      display_order: Number(this.el('houseOrder')?.value || 0), is_active: this.el('houseActive')?.checked ? 1 : 0,
    };
    try {
      const response = this.state.editingHouseId
        ? await window.API.students.houses.update(this.state.editingHouseId, payload)
        : await window.API.students.houses.create(payload);
      this.assertSuccess(response);
      this.hideModal('houseModal');
      await this.refresh();
      this.notify('House saved.', 'success');
    } catch (error) { this.notify(error.message || 'Could not save the house.', 'danger'); }
  },
  openAward() {
    if (!this.canManage()) return this.notify('You do not have permission to issue awards.', 'warning');
    this.renderFormOptions();
    ['awardStudent', 'awardCategory', 'awardType'].forEach((id) => { if (this.el(id)) this.el(id).value = ''; });
    ['awardTitle', 'awardCertNo', 'awardDescription'].forEach((id) => { if (this.el(id)) this.el(id).value = ''; });
    if (this.el('awardIssueDate')) {
      const today = new Date();
      this.el('awardIssueDate').value = new Date(today.getTime() - today.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
    }
    if (this.el('awardStatus')) this.el('awardStatus').value = 'awarded';
    const currentYear = this.state.years.find((year) => Number(year.is_current) === 1 || year.is_current === true
      || ['current', 'active'].includes(String(year.status || '').toLowerCase()));
    if (this.el('awardYear')) this.el('awardYear').value = currentYear ? String(currentYear.id) : '';
    this.renderAwardTerms();
    const currentTerm = this.state.terms.find((term) => Number(term.is_current) === 1 || term.is_current === true
      || String(term.status || '').toLowerCase() === 'current');
    if (this.el('awardTerm')) this.el('awardTerm').value = currentTerm && Number(currentTerm.academic_year_id ?? currentTerm.year) === Number(this.el('awardYear')?.value)
      ? String(currentTerm.id) : '';
    this.openModal('awardModal');
  },
  fillAwardTypes() {
    const categoryId = Number(this.el('awardCategory')?.value || 0);
    const items = this.state.awardTypes.filter((item) => !categoryId || Number(item.category_id) === categoryId);
    const select = this.el('awardType');
    if (select) select.innerHTML = '<option value="">Select type…</option>' + items.map((item) => `<option value="${Number(item.id)}">${this.esc(item.name)}</option>`).join('');
  },
  async saveAward() {
    if (!this.canManage()) return this.notify('You do not have permission to issue awards.', 'warning');
    const studentId = Number(this.el('awardStudent')?.value || 0);
    const title = this.el('awardTitle')?.value.trim();
    if (!studentId || !title) return this.notify('Select a student and enter the award title.', 'warning');
    const payload = {
      student_id: studentId, award_type_id: Number(this.el('awardType')?.value || 0) || null,
      academic_year_id: Number(this.el('awardYear')?.value || 0) || null,
      academic_year_term_id: Number(this.el('awardTerm')?.value || 0) || null,
      title, award_description: this.el('awardDescription')?.value || '',
      issue_date: this.el('awardIssueDate')?.value || null, status: this.el('awardStatus')?.value || 'awarded',
    };
    try {
      this.assertSuccess(await window.API.students.awards.create(payload));
      this.hideModal('awardModal');
      await this.refresh();
      this.notify('Award recorded.', 'success');
    } catch (error) { this.notify(error.message || 'Could not save the award.', 'danger'); }
  },
  openAwardTypes() {
    if (!this.canManage()) return this.notify('You do not have permission to manage award types.', 'warning');
    this.renderFormOptions(); this.openModal('awardTypesModal');
  },
  resetAwardTypeForm() {
    ['atCategory', 'atDepartment', 'atTemplate'].forEach((id) => { if (this.el(id)) this.el(id).selectedIndex = 0; });
    ['atCode', 'atName', 'atPrefix', 'atDescription', 'atSignatory', 'atSignatory2'].forEach((id) => { if (this.el(id)) this.el(id).value = ''; });
    this.state.editingAwardTypeId = null;
  },
  async saveAwardType() {
    if (!this.canManage()) return this.notify('You do not have permission to manage award types.', 'warning');
    const payload = {
      category_id: Number(this.el('atCategory')?.value || 0),
      department_id: this.el('atDepartment')?.value || null,
      template_key: this.el('atTemplate')?.value || null,
      code: this.el('atCode')?.value.trim() || '', name: this.el('atName')?.value.trim() || '',
      number_prefix: this.el('atPrefix')?.value.trim() || '',
      description: this.el('atDescription')?.value.trim() || '',
      signatory_label: this.el('atSignatory')?.value.trim() || '',
      secondary_signatory_label: this.el('atSignatory2')?.value.trim() || '',
    };
    try {
      const response = this.state.editingAwardTypeId
        ? await window.API.students.awards.types.update(this.state.editingAwardTypeId, payload)
        : await window.API.students.awards.types.create(payload);
      this.assertSuccess(response);
      this.resetAwardTypeForm();
      await this.refresh();
      this.notify('Award type saved.', 'success');
    } catch (error) { this.notify(error.message || 'Could not save the award type.', 'danger'); }
  },
  editAwardType(id) {
    const item = this.state.awardTypes.find((row) => Number(row.id) === Number(id));
    if (!item) return;
    if (!this.canManage()) return this.notify('You do not have permission to manage award types.', 'warning');
    this.state.editingAwardTypeId = Number(id);
    const values = {
      atCategory: item.category_id, atDepartment: item.department_id || '', atTemplate: item.template_key || '',
      atCode: item.code, atName: item.name, atPrefix: item.number_prefix || '', atDescription: item.description || '',
      atSignatory: item.signatory_label || '', atSignatory2: item.secondary_signatory_label || '',
    };
    Object.entries(values).forEach(([key, value]) => { if (this.el(key)) this.el(key).value = value; });
    this.openModal('awardTypesModal');
  },
  async deleteAwardType(id) {
    if (!this.canManage()) return this.notify('You do not have permission to manage award types.', 'warning');
    if (!window.confirm('Delete this award type? Types already used by awards cannot be removed.')) return;
    try {
      this.assertSuccess(await window.API.students.awards.types.delete(Number(id)));
      await this.refresh();
      this.notify('Award type deleted.', 'success');
    } catch (error) { this.notify(error.message || 'Could not delete this award type.', 'danger'); }
  },
  assertSuccess(response) {
    const payload = this.responseData(response);
    if (response?.status === 'error' || response?.success === false
      || payload?.status === 'error' || payload?.success === false) {
      throw new Error(payload?.message || payload?.error || response?.message || response?.error || 'The request was rejected.');
    }
    return response;
  },
  exportPositionsCsv() {
    if (!this.isMounted() || (window.AuthContext?.canExport && !window.AuthContext.canExport('students'))) return;
    const rows = [['Position', 'Category', 'Description', 'Maximum holders', 'Status'], ...this.state.positions.map((item) => [item.name, item.leadership_category_name, item.description, item.max_holders, item.is_active ? 'Active' : 'Inactive'])];
    const csv = rows.map((row) => row.map((cell) => `"${String(cell ?? '').replace(/"/g, '""')}"`).join(',')).join('\r\n');
    window.KingswayFileLifecycle?.exportText?.(csv, 'student-leadership-positions.csv', 'text/csv;charset=utf-8');
  },
  printPositions() {
    if (window.AuthContext?.canPrint && !window.AuthContext.canPrint('students')) return;
    window.print();
  },
  notify(message, type = 'info') {
    if (typeof window.showNotification === 'function') window.showNotification(message, type);
    else if (typeof window.showToast === 'function') window.showToast(message, type);
  },
  async refresh() {
    if (!this.isMounted()) return;
    this.state.referenceCache = null;
    await this.loadReferencesComposed();
    await this.load();
  },
  bindEvents() {
    if (!this.isMounted() || this.root.dataset.studentLeadershipBound === 'true') return;
    this.root.dataset.studentLeadershipBound = 'true';
    this.el('leadYear')?.addEventListener('change', () => this.renderTermsForYear());
    this.el('awardYear')?.addEventListener('change', () => this.renderAwardTerms());
    this.el('awardCategory')?.addEventListener('change', () => this.fillAwardTypes());
    ['leadYearFilter', 'leadCatFilter', 'leadStudentFilter'].forEach((id) => {
      this.el(id)?.addEventListener('change', () => this.loadLeadership());
    });
    this.el('awardCatFilter')?.addEventListener('change', () => this.loadAwards());
  },
  async init() {
    this.root = document.querySelector('.lead-wrap');
    if (!this.root || !this.isMounted() || this.initialized) return;
    this.initialized = true;
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    if (!this.isMounted()) return;
    this.updateAccessUI();
    this.bindEvents();
    await this.loadReferencesComposed();
    if (!this.isMounted()) return;
    await this.load();
    if (!this.isMounted()) return;
    window.APIRealtime?.register?.('student_leadership', {
      refresh: () => this.refresh(),
    }, ['students']);
  },
};

window.StudentLeadershipController = StudentLeadershipController;
document.addEventListener('DOMContentLoaded', () => {
  StudentLeadershipController.init().catch((error) => {
    console.error('Student Leadership page initialization failed.', error);
  });
});
