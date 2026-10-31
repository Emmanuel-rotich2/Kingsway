/**
 * Student discipline case controller backed by discipline_incidents.
 * API: GET /students/discipline-get, POST /students/discipline-record,
 * POST /students/discipline-resolve.
 */

const sanctionsController = {

  _data: [],
  _activeFilter: 'all',
  _logModal: null,
  _liftModal: null,

  init: async function () {
    await window.AuthContext?.ready();
    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || '') + '/index.php';
      return;
    }
    this._logModal  = new bootstrap.Modal(document.getElementById('saModal'));
    this._liftModal = new bootstrap.Modal(document.getElementById('saLiftModal'));
    await Promise.all([this._loadData(), this._loadStudents()]);
  },

  _extractPayload: function (response) {
    let value = response;
    for (let depth = 0; depth < 5; depth += 1) {
      if (Array.isArray(value)) return { cases: value };
      if (!value || typeof value !== 'object') break;
      if (Array.isArray(value.cases)) return value;
      value = value.data;
    }
    return {};
  },

  _loadData: async function () {
    const tbody = document.getElementById('saTableBody');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div></td></tr>';
    try {
      const r = await callAPI('/students/discipline-get', 'GET');
      const payload = this._extractPayload(r);
      this._data = Array.isArray(payload.cases) ? payload.cases : [];
      const summary = payload.summary || {};
      this._set('saStatTotal', summary.total ?? this._data.length);
      this._set('saStatPending', summary.pending ?? this._data.filter(item => item.status === 'pending').length);
      this._set('saStatResolved', summary.resolved ?? this._data.filter(item => item.status === 'resolved').length);
      this._set('saStatEscalated', summary.escalated ?? this._data.filter(item => item.status === 'escalated').length);
      this._render(this._data);
    } catch (e) {
      tbody.innerHTML = `<tr><td colspan="9" class="text-danger text-center py-4">Failed to load discipline cases: ${this._esc(e.message || 'Request failed')}</td></tr>`;
    }
  },

  _loadStudents: async function () {
    try {
      const r = await callAPI('/students/get-all', 'GET');
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
      tbody.innerHTML = '<tr><td colspan="9" class="text-center py-5 text-muted">No discipline cases found.</td></tr>';
      return;
    }
    const statusCls = { pending: 'warning', resolved: 'success', escalated: 'danger' };

    tbody.innerHTML = items.map(s => {
      const status = String(s.status || 'pending').toLowerCase();
      const type = s.type || 'general';
      const learner = [s.first_name, s.last_name].filter(Boolean).join(' ') || s.student_name || '—';
      return `<tr>
        <td class="fw-semibold">${this._esc(learner)}${s.admission_no ? `<div class="small text-muted">${this._esc(s.admission_no)}</div>` : ''}</td>
        <td>${this._esc(s.class_name || '—')}</td>
        <td><span class="badge bg-secondary">${this._esc(type)}</span></td>
        <td><span class="badge bg-${s.severity === 'high' ? 'danger' : s.severity === 'medium' ? 'warning' : 'info'}">${this._esc(s.severity || '—')}</span></td>
        <td class="small text-muted" style="max-width:180px;">${this._esc((s.description || '—').substring(0,80))}</td>
        <td>${this._esc(s.incident_date || '—')}</td>
        <td><span class="badge bg-${statusCls[status] || 'secondary'}">${this._esc(status)}</span></td>
        <td>${this._esc(s.action_taken || '—')}</td>
        <td class="text-end">
          ${status === 'pending' ? `<button class="btn btn-sm btn-outline-success" onclick="sanctionsController.showLiftModal(${Number(s.id)})">Resolve</button>` : ''}
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
    ['saStudentId','saType','saSeverity','saDescription','saIncidentDate','saActionTaken'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.value = '';
    });
    const err = document.getElementById('saError');
    if (err) { err.classList.add('d-none'); err.textContent = ''; }
    this._logModal.show();
  },

  logSanction: async function () {
    const student  = document.getElementById('saStudentId')?.value;
    const type     = document.getElementById('saType')?.value;
    const severity = document.getElementById('saSeverity')?.value;
    const description = document.getElementById('saDescription')?.value.trim();
    const incidentDate = document.getElementById('saIncidentDate')?.value;
    const actionTaken = document.getElementById('saActionTaken')?.value.trim();
    const errEl    = document.getElementById('saError');

    if (!student || !type || !severity || !description || !incidentDate) {
      if (errEl) { errEl.textContent = 'Student, incident type, severity, description, and incident date are required.'; errEl.classList.remove('d-none'); }
      return;
    }
    if (errEl) errEl.classList.add('d-none');

    try {
      await callAPI('/students/discipline-record', 'POST', {
        student_id: Number(student), type, severity, description,
        incident_date: incidentDate, action_taken: actionTaken || null, status: 'pending',
      });
      showNotification('Discipline case recorded.', 'success');
      this._logModal.hide();
      await this._loadData();
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
      await callAPI('/students/discipline-resolve', 'POST', { record_id: Number(id), action_taken: reason });
      showNotification('Discipline case resolved.', 'success');
      this._liftModal.hide();
      await this._loadData();
    } catch (e) {
      if (errEl) { errEl.textContent = e.message || 'Failed to lift sanction.'; errEl.classList.remove('d-none'); }
    }
  },

  _set: (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; },
  _esc: s => { const d = document.createElement('div'); d.textContent = String(s ?? ''); return d.innerHTML; },
};

document.addEventListener('DOMContentLoaded', () => sanctionsController.init());

window.sanctionsController = sanctionsController;
