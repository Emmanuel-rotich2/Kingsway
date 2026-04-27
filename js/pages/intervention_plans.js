/**
 * Intervention Plans Controller
 * CRUD for formal student support/intervention plans.
 * API: /counseling/*, /students, /staff
 */

const interventionPlansController = {

  _data: [],
  _students: [],
  _staff: [],
  _modal: null,
  _viewModal: null,

  init: async function () {
    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || '') + '/index.php';
      return;
    }
    this._modal     = new bootstrap.Modal(document.getElementById('ipModal'));
    this._viewModal = new bootstrap.Modal(document.getElementById('ipViewModal'));
    await Promise.all([this._loadStats(), this._loadData(), this._loadStudents(), this._loadStaff()]);
  },

  _loadStats: async function () {
    try {
      const r = await callAPI('/counseling/summary', 'GET');
      const d = r?.data ?? r ?? {};
      this._set('ipStatTotal',     d.total_plans     ?? d.total     ?? '—');
      this._set('ipStatActive',    d.active_plans    ?? d.active    ?? '—');
      this._set('ipStatCompleted', d.completed_plans ?? d.completed ?? '—');
      this._set('ipStatStudents',  d.students_supported ?? d.students ?? '—');
    } catch (e) { console.warn('Stats failed:', e); }
  },

  _loadData: async function () {
    const container = document.getElementById('ipTableContainer');
    if (!container) return;
    container.innerHTML = '<div class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div></div>';
    try {
      const r = await callAPI('/counseling/session', 'GET');
      this._data = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      this._render();
    } catch (e) {
      container.innerHTML = `<div class="alert alert-danger">Failed to load intervention plans: ${this._esc(e.message)}</div>`;
    }
  },

  _loadStudents: async function () {
    try {
      const r = await callAPI('/students', 'GET');
      this._students = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      const sel = document.getElementById('ipStudentId');
      if (sel) {
        sel.innerHTML = '<option value="">— Select student —</option>' +
          this._students.map(s => `<option value="${s.id}">${this._esc((s.first_name||'') + ' ' + (s.last_name||''))} (${this._esc(s.admission_no||'')})</option>`).join('');
      }
    } catch (e) { console.warn('Students failed:', e); }
  },

  _loadStaff: async function () {
    try {
      const r = await callAPI('/staff', 'GET');
      this._staff = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      const sel = document.getElementById('ipResponsibleStaff');
      if (sel) {
        sel.innerHTML = '<option value="">— Select staff —</option>' +
          this._staff.map(s => `<option value="${s.id}">${this._esc((s.first_name||'') + ' ' + (s.last_name||''))}</option>`).join('');
      }
    } catch (e) { console.warn('Staff failed:', e); }
  },

  _render: function () {
    const container = document.getElementById('ipTableContainer');
    if (!container) return;
    if (!this._data.length) {
      container.innerHTML = '<div class="alert alert-info text-center">No intervention plans found. Create the first one.</div>';
      return;
    }
    const statusCls = { active: 'success', completed: 'secondary', paused: 'warning', cancelled: 'danger' };
    const rows = this._data.map(p => `
      <tr>
        <td class="fw-semibold">${this._esc(p.student_name || p.first_name || '—')}</td>
        <td>${this._esc(p.class_name || '—')}</td>
        <td><span class="badge bg-primary">${this._esc(p.plan_type || p.type || '—')}</span></td>
        <td class="text-muted small" style="max-width:200px;">${this._esc((p.goals || p.description || '—').substring(0,80))}${(p.goals||'').length>80?'…':''}</td>
        <td>${this._esc(p.start_date || '—')}</td>
        <td>${this._esc(p.review_date || p.end_date || '—')}</td>
        <td><span class="badge bg-${statusCls[(p.status||'active').toLowerCase()] || 'secondary'}">${this._esc(p.status || 'active')}</span></td>
        <td class="text-end">
          <button class="btn btn-sm btn-outline-primary me-1" onclick="interventionPlansController.viewPlan(${p.id})">View</button>
          <button class="btn btn-sm btn-outline-secondary me-1" onclick="interventionPlansController.editPlan(${p.id})">Edit</button>
          <button class="btn btn-sm btn-outline-danger" onclick="interventionPlansController.deletePlan(${p.id})">Delete</button>
        </td>
      </tr>`).join('');

    container.innerHTML = `
      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>Student</th><th>Class</th><th>Type</th><th>Goals</th>
              <th>Start Date</th><th>Review Date</th><th>Status</th><th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>${rows}</tbody>
        </table>
      </div>`;
  },

  showModal: function (plan = null) {
    document.getElementById('ipEditId').value     = plan?.id     || '';
    document.getElementById('ipStudentId').value  = plan?.student_id || '';
    document.getElementById('ipPlanType').value   = plan?.plan_type || plan?.type || '';
    document.getElementById('ipGoals').value      = plan?.goals || plan?.description || '';
    document.getElementById('ipStartDate').value  = plan?.start_date || '';
    document.getElementById('ipReviewDate').value = plan?.review_date || plan?.end_date || '';
    document.getElementById('ipResponsibleStaff').value = plan?.responsible_staff_id || '';
    document.getElementById('ipStatus').value     = plan?.status || 'active';
    document.getElementById('ipModalTitle').textContent = plan ? 'Edit Intervention Plan' : 'Create Intervention Plan';
    const err = document.getElementById('ipError');
    if (err) { err.classList.add('d-none'); err.textContent = ''; }
    this._modal.show();
  },

  editPlan: function (id) {
    const plan = this._data.find(p => p.id == id);
    if (plan) this.showModal(plan);
  },

  viewPlan: function (id) {
    const plan = this._data.find(p => p.id == id);
    if (!plan) return;
    const body = document.getElementById('ipViewBody');
    if (body) {
      body.innerHTML = `
        <div class="row g-3">
          <div class="col-md-6"><strong>Student:</strong> ${this._esc(plan.student_name || '—')}</div>
          <div class="col-md-6"><strong>Class:</strong> ${this._esc(plan.class_name || '—')}</div>
          <div class="col-md-6"><strong>Plan Type:</strong> ${this._esc(plan.plan_type || '—')}</div>
          <div class="col-md-6"><strong>Status:</strong> ${this._esc(plan.status || '—')}</div>
          <div class="col-md-6"><strong>Start Date:</strong> ${this._esc(plan.start_date || '—')}</div>
          <div class="col-md-6"><strong>Review Date:</strong> ${this._esc(plan.review_date || '—')}</div>
          <div class="col-12"><strong>Goals:</strong><p class="mt-1">${this._esc(plan.goals || '—')}</p></div>
          <div class="col-md-6"><strong>Responsible Staff:</strong> ${this._esc(plan.staff_name || '—')}</div>
        </div>`;
    }
    this._viewModal.show();
  },

  save: async function () {
    const id       = document.getElementById('ipEditId').value;
    const student  = document.getElementById('ipStudentId').value;
    const type     = document.getElementById('ipPlanType').value;
    const goals    = document.getElementById('ipGoals').value.trim();
    const start    = document.getElementById('ipStartDate').value;
    const review   = document.getElementById('ipReviewDate').value;
    const staff    = document.getElementById('ipResponsibleStaff').value;
    const status   = document.getElementById('ipStatus').value;
    const errEl    = document.getElementById('ipError');

    if (!student || !type || !goals || !start || !review) {
      if (errEl) { errEl.textContent = 'Please fill in all required fields.'; errEl.classList.remove('d-none'); }
      return;
    }
    if (errEl) errEl.classList.add('d-none');

    const payload = { student_id: student, plan_type: type, goals, start_date: start, review_date: review, responsible_staff_id: staff || null, status };
    try {
      if (id) {
        await callAPI('/counseling/session/' + id, 'PUT', payload);
        showNotification('Intervention plan updated.', 'success');
      } else {
        await callAPI('/counseling/session', 'POST', payload);
        showNotification('Intervention plan created.', 'success');
      }
      this._modal.hide();
      await Promise.all([this._loadStats(), this._loadData()]);
    } catch (e) {
      if (errEl) { errEl.textContent = e.message || 'Save failed.'; errEl.classList.remove('d-none'); }
    }
  },

  deletePlan: async function (id) {
    if (!confirm('Delete this intervention plan?')) return;
    try {
      await callAPI('/counseling/session/' + id, 'DELETE');
      showNotification('Plan deleted.', 'success');
      await Promise.all([this._loadStats(), this._loadData()]);
    } catch (e) {
      showNotification(e.message || 'Delete failed.', 'danger');
    }
  },

  _set: (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; },
  _esc: s => { const d = document.createElement('div'); d.textContent = String(s ?? ''); return d.innerHTML; },
};

document.addEventListener('DOMContentLoaded', () => interventionPlansController.init());
