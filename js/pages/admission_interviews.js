/**
 * Admission Interviews Controller
 * Schedule interviews, record outcomes, track admission decisions.
 * API: /students/admissions, /staff
 */

const admissionInterviewsController = {

  _data: [],
  _schedModal: null,
  _outcomeModal: null,

  init: async function () {
    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || '') + '/index.php';
      return;
    }
    this._schedModal   = new bootstrap.Modal(document.getElementById('aiScheduleModal'));
    this._outcomeModal = new bootstrap.Modal(document.getElementById('aiOutcomeModal'));
    await Promise.all([this._loadData(), this._loadApplicants(), this._loadStaff()]);
  },

  _loadData: async function () {
    const container = document.getElementById('aiTableBody');
    if (!container) return;
    container.innerHTML = '<div class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div></div>';
    try {
      const r = await callAPI('/students/admissions?status=pending', 'GET');
      this._data = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      this._computeStats();
      this._render();
    } catch (e) {
      container.innerHTML = `<div class="alert alert-danger">Failed to load interviews: ${this._esc(e.message)}</div>`;
    }
  },

  _computeStats: function () {
    const today     = new Date().toISOString().split('T')[0];
    const todayArr  = this._data.filter(i => i.interview_date === today);
    const pending   = this._data.filter(i => !i.outcome);
    const thisMonth = this._data.filter(i => {
      if (!i.interview_date) return false;
      const d = new Date(i.interview_date);
      const n = new Date();
      return d.getMonth() === n.getMonth() && d.getFullYear() === n.getFullYear() && i.outcome;
    });
    const recommended = this._data.filter(i => i.outcome === 'Recommended');
    const total       = this._data.filter(i => i.outcome).length;
    const rate        = total ? Math.round((recommended.length / total) * 100) + '%' : '—';

    this._set('aiStatToday',         todayArr.length);
    this._set('aiStatPending',        pending.length);
    this._set('aiStatCompletedMonth', thisMonth.length);
    this._set('aiStatRate',           rate);
  },

  _loadApplicants: async function () {
    try {
      const r = await callAPI('/students/admissions?status=pending', 'GET');
      const list = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      const sel = document.getElementById('aiApplicantId');
      if (sel) {
        sel.innerHTML = '<option value="">— Select applicant —</option>' +
          list.map(a => `<option value="${a.id}">${this._esc((a.first_name||'') + ' ' + (a.last_name||''))} — ${this._esc(a.class_applied||'')}</option>`).join('');
      }
    } catch (e) { console.warn('Applicants failed:', e); }
  },

  _loadStaff: async function () {
    try {
      const r = await callAPI('/staff', 'GET');
      const staff = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      const sel = document.getElementById('aiInterviewerId');
      if (sel) {
        sel.innerHTML = '<option value="">— Select staff member —</option>' +
          staff.map(s => `<option value="${s.id}">${this._esc((s.first_name||'') + ' ' + (s.last_name||''))} — ${this._esc(s.role_name||s.designation||'')}</option>`).join('');
      }
    } catch (e) { console.warn('Staff failed:', e); }
  },

  _render: function () {
    const container = document.getElementById('aiTableBody');
    if (!container) return;
    if (!this._data.length) {
      container.innerHTML = '<div class="alert alert-info text-center mt-2">No interviews scheduled.</div>';
      return;
    }

    const outcomeCls = { Recommended: 'success', 'Not Recommended': 'danger', Conditional: 'warning' };
    const nextCls    = { 'Proceed to Admission': 'success', Waitlist: 'warning', Decline: 'danger' };
    const today      = new Date().toISOString().split('T')[0];

    const rows = this._data.map(i => {
      const isToday   = i.interview_date === today;
      const hasOutcome = !!i.outcome;
      return `<tr class="${isToday ? 'table-info' : ''}">
        <td class="fw-semibold">${this._esc((i.first_name||'') + ' ' + (i.last_name||''))}${isToday ? ' <span class="badge bg-primary ms-1">Today</span>' : ''}</td>
        <td>${this._esc(i.class_applied || '—')}</td>
        <td>${this._esc(i.interview_date || 'Not scheduled')}</td>
        <td>${this._esc(i.interview_time || '—')}</td>
        <td>${this._esc(i.interviewer_name || '—')}</td>
        <td>${this._esc(i.location || i.room || '—')}</td>
        <td>${hasOutcome ? `<span class="badge bg-${outcomeCls[i.outcome]||'secondary'}">${this._esc(i.outcome)}</span>` : '<span class="badge bg-secondary">Pending</span>'}</td>
        <td>${i.next_step ? `<span class="badge bg-${nextCls[i.next_step]||'secondary'}">${this._esc(i.next_step)}</span>` : '—'}</td>
        <td class="text-end">
          ${!hasOutcome ? `<button class="btn btn-sm btn-success" onclick="admissionInterviewsController.showOutcomeModal(${i.id})">Record Outcome</button>` : ''}
        </td>
      </tr>`;
    }).join('');

    container.innerHTML = `
      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>Applicant</th><th>Class</th><th>Interview Date</th><th>Time</th>
              <th>Interviewer</th><th>Location</th><th>Outcome</th><th>Next Step</th><th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>${rows}</tbody>
        </table>
      </div>`;
  },

  showScheduleModal: function () {
    ['aiApplicantId','aiInterviewDate','aiInterviewTime','aiInterviewerId','aiLocation'].forEach(id => {
      const el = document.getElementById(id); if (el) el.value = '';
    });
    const err = document.getElementById('aiScheduleError');
    if (err) { err.classList.add('d-none'); err.textContent = ''; }
    this._schedModal.show();
  },

  saveSchedule: async function () {
    const applicant   = document.getElementById('aiApplicantId')?.value;
    const date        = document.getElementById('aiInterviewDate')?.value;
    const time        = document.getElementById('aiInterviewTime')?.value;
    const interviewer = document.getElementById('aiInterviewerId')?.value;
    const location    = document.getElementById('aiLocation')?.value.trim();
    const errEl       = document.getElementById('aiScheduleError');

    if (!applicant || !date || !time || !interviewer) {
      if (errEl) { errEl.textContent = 'Applicant, date, time, and interviewer are required.'; errEl.classList.remove('d-none'); }
      return;
    }
    if (errEl) errEl.classList.add('d-none');

    try {
      await callAPI('/students/admissions', 'POST', { applicant_id: applicant, interview_date: date, interview_time: time, interviewer_id: interviewer, location });
      showNotification('Interview scheduled.', 'success');
      this._schedModal.hide();
      await this._loadData();
    } catch (e) {
      if (errEl) { errEl.textContent = e.message || 'Failed to schedule interview.'; errEl.classList.remove('d-none'); }
    }
  },

  showOutcomeModal: function (id) {
    document.getElementById('aiOutcomeInterviewId').value = id;
    document.getElementById('aiOutcomeNotes').value = '';
    const err = document.getElementById('aiOutcomeError');
    if (err) { err.classList.add('d-none'); err.textContent = ''; }
    this._outcomeModal.show();
  },

  saveOutcome: async function () {
    const id       = document.getElementById('aiOutcomeInterviewId')?.value;
    const outcome  = document.getElementById('aiOutcome')?.value;
    const notes    = document.getElementById('aiOutcomeNotes')?.value.trim();
    const nextStep = document.getElementById('aiNextStep')?.value;
    const errEl    = document.getElementById('aiOutcomeError');

    if (!outcome) {
      if (errEl) { errEl.textContent = 'Please select an outcome.'; errEl.classList.remove('d-none'); }
      return;
    }
    if (errEl) errEl.classList.add('d-none');

    try {
      await callAPI('/students/admissions/' + id, 'PUT', { outcome, notes, next_step: nextStep });
      showNotification('Interview outcome recorded.', 'success');
      this._outcomeModal.hide();
      await this._loadData();
    } catch (e) {
      if (errEl) { errEl.textContent = e.message || 'Failed to save outcome.'; errEl.classList.remove('d-none'); }
    }
  },

  _set: (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; },
  _esc: s => { const d = document.createElement('div'); d.textContent = String(s ?? ''); return d.innerHTML; },
};

document.addEventListener('DOMContentLoaded', () => admissionInterviewsController.init());
