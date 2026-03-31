/**
 * Activities Management Controller
 * Full CRUD for activities, categories, participants, schedules, resources.
 * Uses window.API.activities.* from api.js — no legacy fetch endpoints.
 */

const activitiesController = {
  state: {
    activities: [],
    categories: [],
    participants: [],
    schedules: [],
    resources: [],
  },

  // ─── INIT ──────────────────────────────────────────────────────────────────

  init: async function () {
    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || '') + '/index.php';
      return;
    }
    await this.loadAll();
    this.bindTabEvents();
  },

  loadAll: async function () {
    await Promise.all([
      this.loadStats(),
      this.loadActivities(),
      this.loadCategories(),
    ]);
  },

  bindTabEvents: function () {
    document.querySelectorAll('#activitiesTabs button[data-bs-toggle="tab"]').forEach(btn => {
      btn.addEventListener('shown.bs.tab', (e) => {
        const target = e.target.getAttribute('data-bs-target');
        if (target === '#tabParticipants') this.loadParticipants();
        if (target === '#tabSchedules')    this.loadSchedules();
        if (target === '#tabResources')    this.loadResources();
      });
    });
  },

  // ─── STATS ─────────────────────────────────────────────────────────────────

  loadStats: async function () {
    try {
      const resp = await window.API.activities.getSummary();
      const d = resp?.data ?? resp ?? {};
      this.setEl('totalActivities',   d.total_activities    ?? this.state.activities.length ?? '—');
      this.setEl('activeActivities',  d.active_activities   ?? '—');
      this.setEl('upcomingActivities',d.upcoming_activities ?? '—');
      this.setEl('totalParticipants', d.total_participants  ?? '—');
    } catch (e) {
      console.warn('Stats load failed:', e);
    }
  },

  // ─── ACTIVITIES ────────────────────────────────────────────────────────────

  loadActivities: async function () {
    const container = document.getElementById('activitiesTableContainer');
    if (!container) return;
    container.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
    try {
      const params = {};
      const catVal    = document.getElementById('filterCategory')?.value;
      const statusVal = document.getElementById('filterStatus')?.value;
      const searchVal = document.getElementById('searchActivity')?.value;
      if (catVal)    params.category_id = catVal;
      if (statusVal) params.status      = statusVal;
      if (searchVal) params.search      = searchVal;

      const resp = await window.API.activities.list(params);
      const list = this.extract(resp, 'activities') || this.extract(resp, 'data') || (Array.isArray(resp) ? resp : []);
      this.state.activities = list;

      if (!list.length) {
        container.innerHTML = '<p class="text-muted text-center py-4">No activities found.</p>';
        return;
      }
      container.innerHTML = `
        <div class="table-responsive">
          <table class="table table-hover align-middle">
            <thead class="table-dark">
              <tr><th>Name</th><th>Category</th><th>Dates</th><th>Venue</th><th>Status</th><th>Participants</th><th>Actions</th></tr>
            </thead>
            <tbody>
              ${list.map(a => `
                <tr>
                  <td><strong>${this.esc(a.name || a.activity_name)}</strong></td>
                  <td>${this.esc(a.category_name || '—')}</td>
                  <td>
                    ${a.start_date ? `<small>${this.fmtDate(a.start_date)}</small>` : ''}
                    ${a.end_date   ? ` – <small>${this.fmtDate(a.end_date)}</small>` : ''}
                  </td>
                  <td>${this.esc(a.venue || '—')}</td>
                  <td>${this.statusBadge(a.status)}</td>
                  <td>${a.participant_count ?? a.participants_count ?? '—'}</td>
                  <td>
                    <button class="btn btn-sm btn-outline-primary me-1" onclick="activitiesController.editActivity(${a.id || a.activity_id})">
                      <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-success me-1" title="Register participant"
                      onclick="activitiesController.showParticipantModal(${a.id || a.activity_id})">
                      <i class="bi bi-person-plus"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger" onclick="activitiesController.deleteActivity(${a.id || a.activity_id})">
                      <i class="bi bi-trash"></i>
                    </button>
                  </td>
                </tr>`).join('')}
            </tbody>
          </table>
        </div>`;

      this.populateActivitySelects(list);
    } catch (e) {
      container.innerHTML = `<div class="alert alert-danger">Failed to load activities: ${e.message}</div>`;
    }
  },

  showAddModal: function () {
    document.getElementById('activityModalTitle').textContent = 'Add Activity';
    ['activityId','activityName','activityStartDate','activityEndDate','activityVenue',
     'activityMaxParticipants','activityTeacher','activityDescription'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.value = '';
    });
    document.getElementById('activityStatus').value = 'planning';
    new bootstrap.Modal(document.getElementById('activityModal')).show();
  },

  editActivity: async function (id) {
    try {
      const resp = await window.API.activities.get(id);
      const a = resp?.data ?? resp;
      document.getElementById('activityModalTitle').textContent = 'Edit Activity';
      document.getElementById('activityId').value               = a.id ?? a.activity_id;
      document.getElementById('activityName').value             = a.name ?? a.activity_name ?? '';
      document.getElementById('activityCategory').value         = a.category_id ?? '';
      document.getElementById('activityStartDate').value        = (a.start_date ?? '').split(' ')[0];
      document.getElementById('activityEndDate').value          = (a.end_date ?? '').split(' ')[0];
      document.getElementById('activityVenue').value            = a.venue ?? '';
      document.getElementById('activityStatus').value           = a.status ?? 'planning';
      document.getElementById('activityMaxParticipants').value  = a.max_participants ?? '';
      document.getElementById('activityTeacher').value          = a.teacher_in_charge ?? a.coordinator ?? '';
      document.getElementById('activityDescription').value      = a.description ?? '';
      new bootstrap.Modal(document.getElementById('activityModal')).show();
    } catch (e) {
      API.showNotification('Failed to load activity: ' + e.message, 'error');
    }
  },

  saveActivity: async function () {
    const id   = document.getElementById('activityId').value;
    const name = document.getElementById('activityName').value.trim();
    if (!name) { API.showNotification('Activity name is required', 'warning'); return; }

    const payload = {
      name:              name,
      activity_name:     name,
      category_id:       document.getElementById('activityCategory').value || null,
      start_date:        document.getElementById('activityStartDate').value || null,
      end_date:          document.getElementById('activityEndDate').value   || null,
      venue:             document.getElementById('activityVenue').value     || null,
      status:            document.getElementById('activityStatus').value,
      max_participants:  document.getElementById('activityMaxParticipants').value || null,
      teacher_in_charge: document.getElementById('activityTeacher').value   || null,
      description:       document.getElementById('activityDescription').value || null,
    };

    try {
      if (id) {
        await window.API.activities.update(id, payload);
        API.showNotification('Activity updated', 'success');
      } else {
        await window.API.activities.create(payload);
        API.showNotification('Activity created', 'success');
      }
      bootstrap.Modal.getInstance(document.getElementById('activityModal'))?.hide();
      await this.loadActivities();
      await this.loadStats();
    } catch (e) {
      API.showNotification('Save failed: ' + e.message, 'error');
    }
  },

  deleteActivity: async function (id) {
    if (!confirm('Delete this activity? This cannot be undone.')) return;
    try {
      await window.API.activities.delete(id);
      API.showNotification('Activity deleted', 'success');
      await this.loadActivities();
      await this.loadStats();
    } catch (e) {
      API.showNotification('Delete failed: ' + e.message, 'error');
    }
  },

  clearFilters: function () {
    ['searchActivity','filterCategory','filterStatus'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.value = '';
    });
    this.loadActivities();
  },

  // ─── CATEGORIES ────────────────────────────────────────────────────────────

  loadCategories: async function () {
    const container = document.getElementById('categoriesContainer');
    try {
      const resp = await window.API.activities.listCategories();
      const list = this.extract(resp, 'categories') || this.extract(resp, 'data') || (Array.isArray(resp) ? resp : []);
      this.state.categories = list;

      // Populate category dropdowns
      ['activityCategory', 'filterCategory'].forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        const isFilter = id === 'filterCategory';
        el.innerHTML = `<option value="">${isFilter ? 'All Categories' : 'Select Category'}</option>` +
          list.map(c => `<option value="${c.id ?? c.category_id}">${this.esc(c.name ?? c.category_name)}</option>`).join('');
      });

      if (!container) return;
      if (!list.length) {
        container.innerHTML = '<p class="text-muted text-center py-4">No categories found.</p>';
        return;
      }
      container.innerHTML = `
        <div class="table-responsive">
          <table class="table table-hover align-middle">
            <thead class="table-dark"><tr><th>Name</th><th>Description</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              ${list.map(c => `
                <tr>
                  <td>${this.esc(c.name ?? c.category_name)}</td>
                  <td>${this.esc(c.description ?? '—')}</td>
                  <td>${this.statusBadge(c.is_active != null ? (c.is_active ? 'active' : 'inactive') : (c.status ?? ''))}</td>
                  <td>
                    <button class="btn btn-sm btn-outline-primary me-1" onclick="activitiesController.editCategory(${c.id ?? c.category_id})">
                      <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger" onclick="activitiesController.deleteCategory(${c.id ?? c.category_id})">
                      <i class="bi bi-trash"></i>
                    </button>
                  </td>
                </tr>`).join('')}
            </tbody>
          </table>
        </div>`;
    } catch (e) {
      if (container) container.innerHTML = `<div class="alert alert-danger">Failed to load categories: ${e.message}</div>`;
    }
  },

  showCategoryModal: function () {
    document.getElementById('categoryModalTitle').textContent = 'Add Category';
    document.getElementById('categoryId').value          = '';
    document.getElementById('categoryName').value        = '';
    document.getElementById('categoryDescription').value = '';
    new bootstrap.Modal(document.getElementById('categoryModal')).show();
  },

  editCategory: async function (id) {
    try {
      const resp = await window.API.activities.getCategory(id);
      const c = resp?.data ?? resp;
      document.getElementById('categoryModalTitle').textContent = 'Edit Category';
      document.getElementById('categoryId').value          = c.id ?? c.category_id;
      document.getElementById('categoryName').value        = c.name ?? c.category_name ?? '';
      document.getElementById('categoryDescription').value = c.description ?? '';
      new bootstrap.Modal(document.getElementById('categoryModal')).show();
    } catch (e) {
      API.showNotification('Failed to load category: ' + e.message, 'error');
    }
  },

  saveCategory: async function () {
    const id   = document.getElementById('categoryId').value;
    const name = document.getElementById('categoryName').value.trim();
    if (!name) { API.showNotification('Category name is required', 'warning'); return; }
    const payload = {
      name:        name,
      description: document.getElementById('categoryDescription').value || null,
    };
    try {
      if (id) {
        await window.API.activities.updateCategory(id, payload);
        API.showNotification('Category updated', 'success');
      } else {
        await window.API.activities.createCategory(payload);
        API.showNotification('Category created', 'success');
      }
      bootstrap.Modal.getInstance(document.getElementById('categoryModal'))?.hide();
      await this.loadCategories();
    } catch (e) {
      API.showNotification('Save failed: ' + e.message, 'error');
    }
  },

  deleteCategory: async function (id) {
    if (!confirm('Delete this category?')) return;
    try {
      await window.API.activities.deleteCategory(id);
      API.showNotification('Category deleted', 'success');
      await this.loadCategories();
    } catch (e) {
      API.showNotification('Delete failed: ' + e.message, 'error');
    }
  },

  // ─── PARTICIPANTS ──────────────────────────────────────────────────────────

  loadParticipants: async function () {
    const container = document.getElementById('participantsContainer');
    if (!container) return;
    container.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
    try {
      const activityId = document.getElementById('filterParticipantActivity')?.value || '';
      const params = activityId ? { activity_id: activityId } : {};
      const resp = await window.API.activities.listParticipants(params);
      const list = this.extract(resp, 'participants') || this.extract(resp, 'data') || (Array.isArray(resp) ? resp : []);
      this.state.participants = list;

      if (!list.length) {
        container.innerHTML = `
          <p class="text-muted text-center py-4">No participants found.</p>
          <div class="text-center">
            <button class="btn btn-primary btn-sm" onclick="activitiesController.showParticipantModal()">
              <i class="bi bi-person-plus me-1"></i>Register Participant
            </button>
          </div>`;
        return;
      }
      container.innerHTML = `
        <div class="d-flex justify-content-end mb-2">
          <button class="btn btn-sm btn-primary" onclick="activitiesController.showParticipantModal()">
            <i class="bi bi-person-plus me-1"></i>Register Participant
          </button>
        </div>
        <div class="table-responsive">
          <table class="table table-hover align-middle">
            <thead class="table-dark">
              <tr><th>Student</th><th>Activity</th><th>Role</th><th>Status</th><th>Registered</th><th>Actions</th></tr>
            </thead>
            <tbody>
              ${list.map(p => `
                <tr>
                  <td>${this.esc(p.student_name ?? 'Student #' + p.student_id)}</td>
                  <td>${this.esc(p.activity_name ?? '—')}</td>
                  <td>${this.esc(p.role ?? '—')}</td>
                  <td>${this.statusBadge(p.status)}</td>
                  <td>${p.registration_date ? this.fmtDate(p.registration_date) : '—'}</td>
                  <td>
                    <button class="btn btn-sm btn-outline-danger" title="Withdraw"
                      onclick="activitiesController.withdrawParticipant(${p.id ?? p.participant_id})">
                      <i class="bi bi-person-dash"></i>
                    </button>
                  </td>
                </tr>`).join('')}
            </tbody>
          </table>
        </div>`;
    } catch (e) {
      container.innerHTML = `<div class="alert alert-danger">Failed to load participants: ${e.message}</div>`;
    }
  },

  showParticipantModal: function (activityId = null) {
    const sel = document.getElementById('participantActivity');
    sel.innerHTML = '<option value="">Select Activity</option>' +
      this.state.activities.map(a =>
        `<option value="${a.id ?? a.activity_id}"${activityId == (a.id ?? a.activity_id) ? ' selected' : ''}>${this.esc(a.name ?? a.activity_name)}</option>`
      ).join('');
    document.getElementById('participantStudentId').value = '';
    document.getElementById('participantRole').value      = '';
    new bootstrap.Modal(document.getElementById('participantModal')).show();
  },

  saveParticipant: async function () {
    const activityId = document.getElementById('participantActivity').value;
    const studentId  = document.getElementById('participantStudentId').value;
    if (!activityId || !studentId) {
      API.showNotification('Activity and student are required', 'warning');
      return;
    }
    try {
      await window.API.activities.registerParticipant({
        activity_id: activityId,
        student_id:  studentId,
        role:        document.getElementById('participantRole').value || null,
      });
      API.showNotification('Participant registered', 'success');
      bootstrap.Modal.getInstance(document.getElementById('participantModal'))?.hide();
      await this.loadParticipants();
      await this.loadStats();
    } catch (e) {
      API.showNotification('Registration failed: ' + e.message, 'error');
    }
  },

  withdrawParticipant: async function (id) {
    if (!confirm('Withdraw this participant?')) return;
    try {
      await window.API.activities.withdrawParticipant(id, 'Withdrawn by admin');
      API.showNotification('Participant withdrawn', 'success');
      await this.loadParticipants();
      await this.loadStats();
    } catch (e) {
      API.showNotification('Failed: ' + e.message, 'error');
    }
  },

  // ─── SCHEDULES ─────────────────────────────────────────────────────────────

  loadSchedules: async function () {
    const container = document.getElementById('schedulesContainer');
    if (!container) return;
    container.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
    try {
      const resp = await window.API.activities.listSchedules();
      const list = this.extract(resp, 'schedules') || this.extract(resp, 'data') || (Array.isArray(resp) ? resp : []);
      this.state.schedules = list;

      if (!list.length) {
        container.innerHTML = '<p class="text-muted text-center py-4">No schedules found.</p>';
        return;
      }
      container.innerHTML = `
        <div class="table-responsive">
          <table class="table table-hover align-middle">
            <thead class="table-dark">
              <tr><th>Activity</th><th>Date</th><th>Time</th><th>Venue</th><th>Actions</th></tr>
            </thead>
            <tbody>
              ${list.map(s => `
                <tr>
                  <td>${this.esc(s.activity_name ?? '—')}</td>
                  <td>${s.schedule_date ? this.fmtDate(s.schedule_date) : '—'}</td>
                  <td>${s.start_time ? s.start_time.slice(0,5) : '—'}${s.end_time ? ' – ' + s.end_time.slice(0,5) : ''}</td>
                  <td>${this.esc(s.venue ?? '—')}</td>
                  <td>
                    <button class="btn btn-sm btn-outline-primary me-1" onclick="activitiesController.editSchedule(${s.id ?? s.schedule_id})">
                      <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger" onclick="activitiesController.deleteSchedule(${s.id ?? s.schedule_id})">
                      <i class="bi bi-trash"></i>
                    </button>
                  </td>
                </tr>`).join('')}
            </tbody>
          </table>
        </div>`;
    } catch (e) {
      container.innerHTML = `<div class="alert alert-danger">Failed to load schedules: ${e.message}</div>`;
    }
  },

  showScheduleModal: function () {
    document.getElementById('scheduleModalTitle').textContent = 'Add Schedule';
    ['scheduleId','scheduleDate','scheduleStartTime','scheduleEndTime','scheduleVenue'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.value = '';
    });
    this._fillActivitySelect('scheduleActivity');
    new bootstrap.Modal(document.getElementById('scheduleModal')).show();
  },

  editSchedule: async function (id) {
    try {
      const resp = await window.API.activities.listSchedules({ schedule_id: id });
      const list = this.extract(resp, 'schedules') || this.extract(resp, 'data') || [];
      const s    = list.find(x => (x.id ?? x.schedule_id) == id) ?? resp?.data ?? resp;
      document.getElementById('scheduleModalTitle').textContent = 'Edit Schedule';
      document.getElementById('scheduleId').value       = s.id ?? s.schedule_id;
      document.getElementById('scheduleDate').value     = (s.schedule_date ?? '').split(' ')[0];
      document.getElementById('scheduleStartTime').value = s.start_time ?? '';
      document.getElementById('scheduleEndTime').value   = s.end_time   ?? '';
      document.getElementById('scheduleVenue').value     = s.venue      ?? '';
      this._fillActivitySelect('scheduleActivity', s.activity_id);
      new bootstrap.Modal(document.getElementById('scheduleModal')).show();
    } catch (e) {
      API.showNotification('Failed to load schedule: ' + e.message, 'error');
    }
  },

  saveSchedule: async function () {
    const id         = document.getElementById('scheduleId').value;
    const activityId = document.getElementById('scheduleActivity').value;
    if (!activityId) { API.showNotification('Select an activity', 'warning'); return; }
    const payload = {
      activity_id:   activityId,
      schedule_date: document.getElementById('scheduleDate').value      || null,
      start_time:    document.getElementById('scheduleStartTime').value || null,
      end_time:      document.getElementById('scheduleEndTime').value   || null,
      venue:         document.getElementById('scheduleVenue').value     || null,
    };
    try {
      if (id) {
        await window.API.activities.updateSchedule(id, payload);
        API.showNotification('Schedule updated', 'success');
      } else {
        await window.API.activities.createSchedule(payload);
        API.showNotification('Schedule created', 'success');
      }
      bootstrap.Modal.getInstance(document.getElementById('scheduleModal'))?.hide();
      await this.loadSchedules();
    } catch (e) {
      API.showNotification('Save failed: ' + e.message, 'error');
    }
  },

  deleteSchedule: async function (id) {
    if (!confirm('Delete this schedule?')) return;
    try {
      await window.API.activities.deleteSchedule(id);
      API.showNotification('Schedule deleted', 'success');
      await this.loadSchedules();
    } catch (e) {
      API.showNotification('Delete failed: ' + e.message, 'error');
    }
  },

  // ─── RESOURCES ─────────────────────────────────────────────────────────────

  loadResources: async function () {
    const container = document.getElementById('resourcesContainer');
    if (!container) return;
    container.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
    try {
      const resp = await window.API.activities.listResources();
      const list = this.extract(resp, 'resources') || this.extract(resp, 'data') || (Array.isArray(resp) ? resp : []);
      this.state.resources = list;

      if (!list.length) {
        container.innerHTML = '<p class="text-muted text-center py-4">No resources found.</p>';
        return;
      }
      container.innerHTML = `
        <div class="table-responsive">
          <table class="table table-hover align-middle">
            <thead class="table-dark">
              <tr><th>Resource</th><th>Activity</th><th>Type</th><th>Qty</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
              ${list.map(r => `
                <tr>
                  <td>${this.esc(r.name ?? r.resource_name)}</td>
                  <td>${this.esc(r.activity_name ?? '—')}</td>
                  <td><span class="badge bg-secondary">${this.esc(r.type ?? r.resource_type ?? '—')}</span></td>
                  <td>${r.quantity ?? '—'}</td>
                  <td>${this.statusBadge(r.status ?? r.availability_status)}</td>
                  <td>
                    <button class="btn btn-sm btn-outline-primary me-1" onclick="activitiesController.editResource(${r.id ?? r.resource_id})">
                      <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger" onclick="activitiesController.deleteResource(${r.id ?? r.resource_id})">
                      <i class="bi bi-trash"></i>
                    </button>
                  </td>
                </tr>`).join('')}
            </tbody>
          </table>
        </div>`;
    } catch (e) {
      container.innerHTML = `<div class="alert alert-danger">Failed to load resources: ${e.message}</div>`;
    }
  },

  showResourceModal: function () {
    document.getElementById('resourceModalTitle').textContent = 'Add Resource';
    ['resourceId','resourceName','resourceNotes'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.value = '';
    });
    document.getElementById('resourceType').value     = 'equipment';
    document.getElementById('resourceQuantity').value = '1';
    this._fillActivitySelect('resourceActivity');
    new bootstrap.Modal(document.getElementById('resourceModal')).show();
  },

  editResource: async function (id) {
    try {
      const resp = await window.API.activities.listResources({ resource_id: id });
      const list = this.extract(resp, 'resources') || this.extract(resp, 'data') || [];
      const r    = list.find(x => (x.id ?? x.resource_id) == id) ?? resp?.data ?? resp;
      document.getElementById('resourceModalTitle').textContent = 'Edit Resource';
      document.getElementById('resourceId').value       = r.id ?? r.resource_id;
      document.getElementById('resourceName').value     = r.name ?? r.resource_name ?? '';
      document.getElementById('resourceType').value     = r.type ?? r.resource_type ?? 'equipment';
      document.getElementById('resourceQuantity').value = r.quantity ?? 1;
      document.getElementById('resourceNotes').value    = r.notes ?? '';
      this._fillActivitySelect('resourceActivity', r.activity_id);
      new bootstrap.Modal(document.getElementById('resourceModal')).show();
    } catch (e) {
      API.showNotification('Failed to load resource: ' + e.message, 'error');
    }
  },

  saveResource: async function () {
    const id         = document.getElementById('resourceId').value;
    const name       = document.getElementById('resourceName').value.trim();
    const activityId = document.getElementById('resourceActivity').value;
    if (!name) { API.showNotification('Resource name is required', 'warning'); return; }
    const payload = {
      activity_id: activityId || null,
      name,
      type:     document.getElementById('resourceType').value,
      quantity: parseInt(document.getElementById('resourceQuantity').value) || 1,
      notes:    document.getElementById('resourceNotes').value || null,
    };
    try {
      if (id) {
        await window.API.activities.updateResource(id, payload);
        API.showNotification('Resource updated', 'success');
      } else {
        await window.API.activities.addResource(payload);
        API.showNotification('Resource added', 'success');
      }
      bootstrap.Modal.getInstance(document.getElementById('resourceModal'))?.hide();
      await this.loadResources();
    } catch (e) {
      API.showNotification('Save failed: ' + e.message, 'error');
    }
  },

  deleteResource: async function (id) {
    if (!confirm('Delete this resource?')) return;
    try {
      await window.API.activities.deleteResource(id);
      API.showNotification('Resource deleted', 'success');
      await this.loadResources();
    } catch (e) {
      API.showNotification('Delete failed: ' + e.message, 'error');
    }
  },

  // ─── PRIVATE HELPERS ───────────────────────────────────────────────────────

  _fillActivitySelect: function (selectId, selectedId = null) {
    const el = document.getElementById(selectId);
    if (!el) return;
    el.innerHTML = '<option value="">Select Activity</option>' +
      this.state.activities.map(a =>
        `<option value="${a.id ?? a.activity_id}"${selectedId == (a.id ?? a.activity_id) ? ' selected' : ''}>${this.esc(a.name ?? a.activity_name)}</option>`
      ).join('');
  },

  populateActivitySelects: function (list) {
    ['participantActivity','scheduleActivity','resourceActivity'].forEach(id => {
      const el = document.getElementById(id);
      if (!el) return;
      el.innerHTML = '<option value="">Select Activity</option>' +
        list.map(a => `<option value="${a.id ?? a.activity_id}">${this.esc(a.name ?? a.activity_name)}</option>`).join('');
    });
    const filterEl = document.getElementById('filterParticipantActivity');
    if (filterEl) {
      filterEl.innerHTML = '<option value="">All Activities</option>' +
        list.map(a => `<option value="${a.id ?? a.activity_id}">${this.esc(a.name ?? a.activity_name)}</option>`).join('');
      filterEl.onchange = () => this.loadParticipants();
    }
  },

  // Also bind search/filter change events
  _bindFilterEvents: function () {
    const searchEl   = document.getElementById('searchActivity');
    const filterCat  = document.getElementById('filterCategory');
    const filterStat = document.getElementById('filterStatus');
    let debounce;
    if (searchEl)   searchEl.addEventListener('input',  () => { clearTimeout(debounce); debounce = setTimeout(() => this.loadActivities(), 300); });
    if (filterCat)  filterCat.addEventListener('change', () => this.loadActivities());
    if (filterStat) filterStat.addEventListener('change', () => this.loadActivities());
  },

  extract: (resp, key) => {
    if (!resp) return null;
    if (Array.isArray(resp[key])) return resp[key];
    if (resp.data && Array.isArray(resp.data[key])) return resp.data[key];
    return null;
  },

  setEl: (id, val) => {
    const el = document.getElementById(id);
    if (el) el.textContent = val;
  },

  esc: (str) => {
    if (str == null) return '';
    return String(str)
      .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  },

  fmtDate: (d) => {
    if (!d) return '';
    try { return new Date(d).toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'numeric' }); }
    catch { return String(d); }
  },

  statusBadge: (status) => {
    const map = { active:'success', completed:'secondary', planning:'info', scheduled:'primary', cancelled:'danger', inactive:'secondary' };
    const s = (status ?? '').toLowerCase();
    return `<span class="badge bg-${map[s] || 'secondary'}">${status ?? '—'}</span>`;
  },
};

document.addEventListener('DOMContentLoaded', () => {
  activitiesController.init().then(() => activitiesController._bindFilterEvents());
});
