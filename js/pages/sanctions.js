/**
 * Student Sanctions Controller
 * Manage active detentions, suspensions, expulsions.
 * Approve/lift workflow with reason.
 * API: /students/discipline-get, /students/discipline-get (POST/PUT)
 */

const sanctionsController = {

  _data: [],
  _activeFilter: 'all',
  _logModal: null,
  _liftModal: null,

  init: async function () {
    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || '') + '/index.php';
      return;
    }
    this._logModal  = new bootstrap.Modal(document.getElementById('saModal'));
    this._liftModal = new bootstrap.Modal(document.getElementById('saLiftModal'));
    await Promise.all([this._loadStats(), this._loadData(), this._loadStudents()]);
  },

  _loadStats: async function () {
    try {
      const r = await callAPI('/students/discipline-get?status=active', 'GET');
      const items = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      const count = type => items.filter(i => (i.sanction_type || i.type || '').toLowerCase().includes(type.toLowerCase())).length;
      this._set('saStatDetention',  count('Detention'));
      this._set('saStatSuspension', count('Suspension'));
      this._set('saStatExpulsion',  count('Expulsion'));
      const pending = items.filter(i => (i.status||'').toLowerCase() === 'pending_review').length;
      this._set('saStatPending', pending);
    } catch (e) { console.warn('Stats failed:', e); }
  },

  _loadData: async function () {
    const tbody = document.getElementById('saTableBody');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div></td></tr>';
    try {
      const r = await callAPI('/students/discipline-get?status=active', 'GET');
      this._data = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      this._render(this._data);
    } catch (e) {
      tbody.innerHTML = `<tr><td colspan="9" class="text-danger text-center py-4">Failed to load sanctions.</td></tr>`;
    }
  },

  _loadStudents: async function () {
    try {
      const r = await callAPI('/students', 'GET');
      const students = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      const sel = document.getElementById('saStudentId');
      if (sel) {
        sel.innerHTML = '<option value="">— Select student —</option>' +
          students.map(s => `<option value="${s.id}">${this._esc((s.first_name||'') + ' ' + (s.last_name||''))} (${this._esc(s.class_name||s.grade||'')})</option>`).join('');
      }
    } catch (e) { console.warn('Students failed:', e); }
  },

  _render: function (items) {
    const tbody = document.getElementById('saTableBody');
    if (!tbody) return;
    if (!items.length) {
      tbody.innerHTML = '<tr><td colspan="9" class="text-center py-5 text-muted">No sanctions found for the selected filter.</td></tr>';
      return;
    }
    const statusCls = { active: 'danger', pending_review: 'warning', lifted: 'success', expired: 'secondary' };
    const typeCls   = { Detention: 'warning', Suspension: 'orange', Expulsion: 'danger', 'Community Service': 'info' };

    tbody.innerHTML = items.map(s => {
      const status   = (s.status || 'active').toLowerCase();
      const type     = s.sanction_type || s.type || s.violation_type || '—';
      const isActive = status === 'active' || status === 'pending_review';
      return `<tr>
        <td class="fw-semibold">${this._esc(s.student_name || s.first_name || '—')}</td>
        <td>${this._esc(s.class_name || '—')}</td>
        <td><span class="badge bg-${typeCls[type] || 'secondary'}" style="${type==='Suspension'?'background:#e67e22!important':''}">${this._esc(type)}</span></td>
        <td class="small text-muted" style="max-width:180px;">${this._esc((s.reason||s.description||'—').substring(0,80))}</td>
        <td>${this._esc(s.start_date || s.incident_date || '—')}</td>
        <td>${this._esc(s.end_date || '—')}</td>
        <td><span class="badge bg-${statusCls[status] || 'secondary'}">${this._esc(s.status || '—')}</span></td>
        <td>${this._esc(s.issued_by || s.teacher_name || '—')}</td>
        <td class="text-end">
          ${isActive ? `<button class="btn btn-sm btn-outline-success" onclick="sanctionsController.showLiftModal(${s.id})">Lift</button>` : ''}
        </td>
      </tr>`;
    }).join('');
  },

  filterByType: function (type, btn) {
    this._activeFilter = type;
    document.querySelectorAll('#saTabs .nav-link').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
    const filtered = type === 'all' ? this._data : this._data.filter(s => {
      const t = (s.sanction_type || s.type || s.violation_type || '').toLowerCase();
      return t.includes(type.toLowerCase());
    });
    this._render(filtered);
  },

  showLogModal: function () {
    ['saStudentId','saType','saStatus','saReason','saStartDate','saEndDate'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.value = '';
    });
    const cb = document.getElementById('saParentNotified');
    if (cb) cb.checked = false;
    const err = document.getElementById('saError');
    if (err) { err.classList.add('d-none'); err.textContent = ''; }
    this._logModal.show();
  },

  logSanction: async function () {
    const student  = document.getElementById('saStudentId')?.value;
    const type     = document.getElementById('saType')?.value;
    const status   = document.getElementById('saStatus')?.value || 'active';
    const reason   = document.getElementById('saReason')?.value.trim();
    const start    = document.getElementById('saStartDate')?.value;
    const end      = document.getElementById('saEndDate')?.value;
    const notified = document.getElementById('saParentNotified')?.checked;
    const errEl    = document.getElementById('saError');

    if (!student || !type || !reason || !start) {
      if (errEl) { errEl.textContent = 'Student, type, reason, and start date are required.'; errEl.classList.remove('d-none'); }
      return;
    }
    if (errEl) errEl.classList.add('d-none');

    try {
      await callAPI('/students/discipline-get', 'POST', {
        student_id: student, sanction_type: type, status, reason, start_date: start,
        end_date: end || null, parent_notified: notified,
      });
      showNotification('Sanction logged successfully.', 'success');
      this._logModal.hide();
      await Promise.all([this._loadStats(), this._loadData()]);
    } catch (e) {
      if (errEl) { errEl.textContent = e.message || 'Failed to log sanction.'; errEl.classList.remove('d-none'); }
    }
  },

  showLiftModal: function (id) {
    document.getElementById('saLiftId').value = id;
    const err = document.getElementById('saLiftError');
    if (err) { err.classList.add('d-none'); err.textContent = ''; }
    const reason = document.getElementById('saLiftReason');
    if (reason) reason.value = '';
    this._liftModal.show();
  },

  confirmLift: async function () {
    const id     = document.getElementById('saLiftId')?.value;
    const reason = document.getElementById('saLiftReason')?.value.trim();
    const errEl  = document.getElementById('saLiftError');

    if (!reason) {
      if (errEl) { errEl.textContent = 'Please provide a reason for lifting the sanction.'; errEl.classList.remove('d-none'); }
      return;
    }
    if (errEl) errEl.classList.add('d-none');

    try {
      await callAPI('/students/discipline-get/' + id, 'PUT', { status: 'lifted', lift_reason: reason });
      showNotification('Sanction lifted successfully.', 'success');
      this._liftModal.hide();
      await Promise.all([this._loadStats(), this._loadData()]);
    } catch (e) {
      if (errEl) { errEl.textContent = e.message || 'Failed to lift sanction.'; errEl.classList.remove('d-none'); }
    }
  },

  _set: (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; },
  _esc: s => { const d = document.createElement('div'); d.textContent = String(s ?? ''); return d.innerHTML; },
};

document.addEventListener('DOMContentLoaded', () => sanctionsController.init());
