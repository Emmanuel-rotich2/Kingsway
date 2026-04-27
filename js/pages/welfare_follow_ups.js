/**
 * Welfare Follow-ups Controller
 * Create, complete, and manage welfare follow-up actions for at-risk students.
 * API proxy: /api/counseling/session (type=followup)
 */

const welfareFUController = {

  _followups: [],
  _students:  [],
  _staff:     [],

  init: async function () {
    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || '') + '/index.php';
      return;
    }
    await Promise.all([
      this._loadStudents(),
      this._loadStaff(),
    ]);
    await this.loadFollowUps();
  },

  // ── LOAD DATA ─────────────────────────────────────────────────────

  loadFollowUps: async function () {
    const container = document.getElementById('wfTableContainer');
    if (!container) return;
    container.innerHTML = '<div class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div></div>';

    try {
      const r = await callAPI('/counseling/session?type=followup', 'GET');
      this._followups = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      this._updateStats();
      this._renderTable();
    } catch (e) {
      container.innerHTML = `<div class="alert alert-danger">Failed to load follow-ups: ${this._esc(e.message)}</div>`;
    }
  },

  _updateStats: function () {
    const now   = new Date();
    const total = this._followups.length;
    const pending   = this._followups.filter(f => (f.status || 'pending') === 'pending').length;
    const completed = this._followups.filter(f => f.status === 'completed').length;
    const overdue   = this._followups.filter(f => {
      return f.status !== 'completed' && f.due_date && new Date(f.due_date) < now;
    }).length;

    this._set('wfStatTotal',     total);
    this._set('wfStatPending',   pending);
    this._set('wfStatCompleted', completed);
    this._set('wfStatOverdue',   overdue);
  },

  _renderTable: function () {
    const container = document.getElementById('wfTableContainer');
    if (!container) return;

    if (!this._followups.length) {
      container.innerHTML = '<div class="alert alert-info text-center">No follow-ups recorded yet. Click "Add Follow-up" to get started.</div>';
      return;
    }

    const statusBadge = status => {
      const map = {
        pending:   'bg-warning text-dark',
        completed: 'bg-success',
        overdue:   'bg-danger',
      };
      return `<span class="badge ${map[status] || 'bg-secondary'}">${this._esc(status || 'pending')}</span>`;
    };

    const now = new Date();
    const rows = this._followups.map(f => {
      const isOverdue = f.status !== 'completed' && f.due_date && new Date(f.due_date) < now;
      const displayStatus = isOverdue ? 'overdue' : (f.status || 'pending');
      return `
        <tr>
          <td class="fw-semibold">${this._esc(f.student_name || f.full_name || ('Student #' + (f.student_id || '?')))}</td>
          <td>${this._esc(f.category || f.session_type || '—')}</td>
          <td>${this._esc(f.notes || f.description || f.action || '—')}</td>
          <td>${this._esc(f.due_date || f.session_date || '—')}</td>
          <td>${this._esc(f.assigned_to_name || f.staff_name || '—')}</td>
          <td>${statusBadge(displayStatus)}</td>
          <td>
            ${f.status !== 'completed' ? `
              <button class="btn btn-sm btn-success me-1" onclick="welfareFUController.complete(${f.id})" title="Mark complete">
                <i class="bi bi-check-lg"></i>
              </button>` : ''}
            <button class="btn btn-sm btn-outline-secondary" onclick="welfareFUController.editItem(${f.id})" title="Edit">
              <i class="bi bi-pencil"></i>
            </button>
          </td>
        </tr>
      `;
    }).join('');

    container.innerHTML = `<div class="table-responsive">
      <table class="table table-hover align-middle" id="wfTableBody">
        <thead class="table-light"><tr>
          <th>Student</th>
          <th>Category</th>
          <th>Follow-up Action</th>
          <th>Due Date</th>
          <th>Assigned To</th>
          <th>Status</th>
          <th>Actions</th>
        </tr></thead>
        <tbody>${rows}</tbody>
      </table>
    </div>`;
  },

  // ── MODAL ─────────────────────────────────────────────────────────

  showModal: function (item) {
    this._set('wfModalTitle', item ? 'Edit Follow-up' : 'Add Follow-up');
    document.getElementById('wfEditId').value    = item?.id    || '';
    document.getElementById('wfStudentId').value = item?.student_id || '';
    document.getElementById('wfCategory').value  = item?.category  || item?.session_type || '';
    document.getElementById('wfAction').value    = item?.notes || item?.description || item?.action || '';
    document.getElementById('wfDueDate').value   = item?.due_date   || '';
    document.getElementById('wfAssignedTo').value= item?.assigned_to || '';
    document.getElementById('wfError').classList.add('d-none');
    new bootstrap.Modal(document.getElementById('wfModal')).show();
  },

  editItem: function (id) {
    const item = this._followups.find(f => f.id == id);
    if (item) this.showModal(item);
  },

  // ── SAVE ──────────────────────────────────────────────────────────

  save: async function () {
    const errEl     = document.getElementById('wfError');
    errEl.classList.add('d-none');

    const editId    = document.getElementById('wfEditId').value;
    const studentId = document.getElementById('wfStudentId').value;
    const category  = document.getElementById('wfCategory').value;
    const action    = document.getElementById('wfAction').value.trim();
    const dueDate   = document.getElementById('wfDueDate').value;

    if (!studentId || !category || !action || !dueDate) {
      errEl.textContent = 'Student, category, action description, and due date are required.';
      errEl.classList.remove('d-none');
      return;
    }

    const payload = {
      student_id:  parseInt(studentId),
      session_type: 'followup',
      category,
      notes:       action,
      due_date:    dueDate,
      assigned_to: document.getElementById('wfAssignedTo').value || null,
      status:      'pending',
    };

    try {
      if (editId) {
        await callAPI('/counseling/session/' + editId, 'PUT', payload);
      } else {
        await callAPI('/counseling/session', 'POST', payload);
      }
      bootstrap.Modal.getInstance(document.getElementById('wfModal')).hide();
      showNotification('Follow-up saved', 'success');
      await this.loadFollowUps();
    } catch (e) {
      errEl.textContent = e.message || 'Save failed';
      errEl.classList.remove('d-none');
    }
  },

  complete: async function (id) {
    try {
      await callAPI('/counseling/session/' + id, 'PUT', { status: 'completed' });
      showNotification('Follow-up marked as completed', 'success');
      await this.loadFollowUps();
    } catch (e) {
      showNotification('Failed to complete follow-up: ' + e.message, 'danger');
    }
  },

  // ── DROPDOWNS ─────────────────────────────────────────────────────

  _loadStudents: async function () {
    try {
      const r = await callAPI('/students?status=active&limit=500', 'GET');
      this._students = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      const sel = document.getElementById('wfStudentId');
      if (!sel) return;
      sel.innerHTML = '<option value="">— Select student —</option>';
      this._students.forEach(s => {
        const o = document.createElement('option');
        o.value       = s.id;
        o.textContent = `${s.first_name} ${s.last_name} (${s.admission_no || s.id})`;
        sel.appendChild(o);
      });
    } catch (e) { console.warn('Could not load students:', e); }
  },

  _loadStaff: async function () {
    try {
      const r = await callAPI('/staff?limit=200', 'GET');
      this._staff = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      const sel = document.getElementById('wfAssignedTo');
      if (!sel) return;
      sel.innerHTML = '<option value="">— Select staff —</option>';
      this._staff.forEach(s => {
        const o = document.createElement('option');
        o.value       = s.id;
        o.textContent = `${s.first_name || ''} ${s.last_name || s.name || ''}`.trim();
        sel.appendChild(o);
      });
    } catch (e) { console.warn('Could not load staff:', e); }
  },

  // ── UTILS ─────────────────────────────────────────────────────────

  _set: function (id, val) { const el = document.getElementById(id); if (el) el.textContent = val; },
  _esc: function (s) {
    return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  },
};

document.addEventListener('DOMContentLoaded', () => welfareFUController.init());
