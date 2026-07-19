/**
 * Admission Interviews Controller
 * Uses current admissions workflow queues.
 */

const admissionInterviewsController = {
  _data: [],
  _applicants: [],
  _staff: [],
  _schedModal: null,
  _outcomeModal: null,
  _viewModal: null,
  _initialized: false,

  init: async function () {
    if (this._initialized) return;
    this._initialized = true;

    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || '') + '/index.php';
      return;
    }

    this._schedModal = new bootstrap.Modal(document.getElementById('aiScheduleModal'));
    this._outcomeModal = new bootstrap.Modal(document.getElementById('aiOutcomeModal'));
    this._viewModal = new bootstrap.Modal(document.getElementById('aiViewModal'));
    this._bindFilters();
    await Promise.all([this._loadData(), this._loadStaff()]);
  },

  _api: function (path, method = 'GET', data = null) {
    if (window.API?.callAPI) return window.API.callAPI(path, method, data);
    return callAPI(path, method, data);
  },

  _loadData: async function () {
    const container = document.getElementById('aiTableBody');
    if (!container) return;
    container.innerHTML = '<div class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div></div>';

    try {
      const response = await this._api('/admission/queues', 'GET');
      const queues = response?.data?.queues || response?.queues || {};
      this._data = (queues.interview_pending || []).map(app => ({
        ...app,
        workflow_data: this._parseWorkflowData(app),
      }));
      this._applicants = this._data.filter(app => app.current_stage === 'interview_scheduling');
      this._populateApplicants();
      this._computeStats();
      this._render();
    } catch (e) {
      console.error('Failed to load interview queue:', e);
      container.innerHTML = `<div class="alert alert-danger">Failed to load interview queue: ${this._esc(e.message)}</div>`;
    }
  },

  _loadStaff: async function () {
    try {
      const response = await this._api('/staff', 'GET');
      this._staff = Array.isArray(response?.data) ? response.data : (Array.isArray(response) ? response : []);
      const sel = document.getElementById('aiInterviewerId');
      const filter = document.getElementById('aiFilterInterviewer');
      const options = this._staff.map(s => {
        const name = `${s.first_name || ''} ${s.last_name || ''}`.trim() || s.full_name || s.name || 'Staff';
        return `<option value="${this._esc(s.id)}">${this._esc(name)} — ${this._esc(s.role_name || s.designation || '')}</option>`;
      }).join('');
      if (sel) sel.innerHTML = '<option value="">— Select staff member —</option>' + options;
      if (filter) filter.innerHTML = '<option value="">All Interviewers</option>' + options;
    } catch (e) {
      console.warn('Staff failed:', e);
    }
  },

  _populateApplicants: function () {
    const sel = document.getElementById('aiApplicantId');
    if (!sel) return;
    sel.innerHTML = '<option value="">— Select applicant —</option>' + this._applicants.map(app =>
      `<option value="${this._esc(app.id)}">${this._esc(app.applicant_name || 'Unknown')} — ${this._esc(app.grade_applying_for || '')}</option>`
    ).join('');
  },

  _bindFilters: function () {
    ['aiFilterStatus', 'aiFilterDate', 'aiFilterStage', 'aiFilterInterviewer'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.addEventListener('change', () => this._render());
    });
    const search = document.getElementById('aiSearch');
    if (search) search.addEventListener('input', this._debounce(() => this._render(), 250));
  },

  _computeStats: function () {
    const today = new Date().toISOString().split('T')[0];
    const todayArr = this._data.filter(app => this._parseWorkflowData(app).interview_date === today);
    const pendingScheduling = this._data.filter(app => app.current_stage === 'interview_scheduling');
    const pendingResults = this._data.filter(app => app.current_stage === 'interview_results');
    const completedMonth = this._data.filter(app => {
      const data = this._parseWorkflowData(app);
      const rawDate = data.interview_completed_at || data.interview_date;
      if (!rawDate || app.current_stage !== 'interview_results') return false;
      const d = new Date(rawDate);
      const now = new Date();
      return d.getMonth() === now.getMonth() && d.getFullYear() === now.getFullYear();
    });
    const recommended = this._data.filter(app => {
      const data = this._parseWorkflowData(app);
      return ['recommended', 'conditional'].includes(String(data.recommendation || '').toLowerCase());
    });
    const completed = this._data.filter(app => this._parseWorkflowData(app).recommendation).length;

    this._set('aiStatToday', todayArr.length);
    this._set('aiStatPending', pendingScheduling.length + pendingResults.length);
    this._set('aiStatCompletedMonth', completedMonth.length);
    this._set('aiStatAwaitingDecision', recommended.length);
    this._set('aiStatRate', completed ? `${Math.round((recommended.length / completed) * 100)}%` : '—');
  },

  _filteredData: function () {
    const statusFilter = document.getElementById('aiFilterStatus')?.value || '';
    const dateFilter = document.getElementById('aiFilterDate')?.value || '';
    const stageFilter = document.getElementById('aiFilterStage')?.value || '';
    const interviewerFilter = document.getElementById('aiFilterInterviewer')?.value || '';
    const search = (document.getElementById('aiSearch')?.value || '').toLowerCase();

    return this._data.filter(app => {
      const data = this._parseWorkflowData(app);
      if (statusFilter) {
        const dataStatus = app.current_stage === 'interview_scheduling'
          ? 'pending_scheduling'
          : (data.recommendation ? 'completed' : 'scheduled');
        if (dataStatus !== statusFilter) return false;
      }
      if (dateFilter && data.interview_date !== dateFilter) return false;
      if (stageFilter && app.current_stage !== stageFilter) return false;
      if (interviewerFilter && String(data.interviewer_id || '') !== String(interviewerFilter)) return false;
      if (search) {
        const haystack = [app.applicant_name, app.application_no, app.grade_applying_for, app.current_stage].join(' ').toLowerCase();
        if (!haystack.includes(search)) return false;
      }
      return true;
    });
  },

  _render: function () {
    const container = document.getElementById('aiTableBody');
    if (!container) return;
    const rowsData = this._filteredData();

    if (!rowsData.length) {
      container.innerHTML = '<div class="alert alert-info text-center mt-2">No applications in the interview queue.</div>';
      return;
    }

    const today = new Date().toISOString().split('T')[0];
    const rows = rowsData.map(app => {
      const data = this._parseWorkflowData(app);
      const isToday = data.interview_date === today;
      const canSchedule = app.current_stage === 'interview_scheduling';
      const canRecord = app.current_stage === 'interview_results';
      const stageBadge = this._stageBadge(app.current_stage);
      return `<tr class="${isToday ? 'table-info' : ''}">
        <td class="fw-semibold">${this._esc(app.applicant_name || 'Unknown')}${isToday ? ' <span class="badge bg-primary ms-1">Today</span>' : ''}<br><small class="text-muted">${this._esc(app.application_no || '—')}</small></td>
        <td>${this._esc(app.grade_applying_for || '—')}</td>
        <td>${this._esc(data.interview_date || 'Not scheduled')}</td>
        <td>${this._esc(data.interview_time || '—')}</td>
        <td>${this._esc(this._interviewerName(data.interviewer_id) || data.interviewer_name || '—')}</td>
        <td>${this._esc(data.venue || data.location || '—')}</td>
        <td>${stageBadge}</td>
        <td>${this._esc(data.recommendation || '—')}</td>
        <td class="text-end">
          <div class="btn-group btn-group-sm">
            <button class="btn btn-outline-primary" onclick="admissionInterviewsController.viewApplication(${app.id})">View</button>
            ${canSchedule ? `<button class="btn btn-outline-success" onclick="admissionInterviewsController.showScheduleModal(${app.id})">Schedule</button>` : ''}
            ${canRecord ? `<button class="btn btn-success" onclick="admissionInterviewsController.showOutcomeModal(${app.id})">Record</button>` : ''}
          </div>
        </td>
      </tr>`;
    }).join('');

    container.innerHTML = `
      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>Applicant</th><th>Grade</th><th>Interview Date</th><th>Time</th>
              <th>Interviewer</th><th>Location</th><th>Stage</th><th>Recommendation</th><th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>${rows}</tbody>
        </table>
      </div>`;
  },

  showScheduleModal: function (applicationId = null) {
    ['aiInterviewDate', 'aiInterviewTime', 'aiInterviewerId', 'aiLocation', 'aiSpecialRequirements'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.value = '';
    });
    const applicant = document.getElementById('aiApplicantId');
    if (applicant) applicant.value = applicationId || '';
    const err = document.getElementById('aiScheduleError');
    if (err) { err.classList.add('d-none'); err.textContent = ''; }
    this._schedModal.show();
  },

  saveSchedule: async function () {
    const applicationId = document.getElementById('aiApplicantId')?.value;
    const date = document.getElementById('aiInterviewDate')?.value;
    const time = document.getElementById('aiInterviewTime')?.value;
    const interviewer = document.getElementById('aiInterviewerId')?.value;
    const venue = document.getElementById('aiLocation')?.value.trim();
    const specialRequirements = document.getElementById('aiSpecialRequirements')?.value.trim();
    const errEl = document.getElementById('aiScheduleError');

    if (!applicationId || !date || !time || !interviewer) {
      if (errEl) { errEl.textContent = 'Applicant, date, time, and interviewer are required.'; errEl.classList.remove('d-none'); }
      return;
    }
    if (errEl) errEl.classList.add('d-none');

    try {
      await this._api('/admission/schedule-interview', 'POST', {
        application_id: applicationId,
        interview_date: date,
        interview_time: time,
        interviewer_id: interviewer,
        venue,
        special_requirements: specialRequirements,
      });
      showNotification('Interview scheduled.', 'success');
      this._schedModal.hide();
      await this._loadData();
    } catch (e) {
      if (errEl) { errEl.textContent = e.message || 'Failed to schedule interview.'; errEl.classList.remove('d-none'); }
    }
  },

  showOutcomeModal: async function (applicationId) {
    document.getElementById('aiOutcomeApplicationId').value = applicationId;
    document.getElementById('aiOutcomeInterviewId').value = applicationId;
    ['aiAcademicScore', 'aiBehaviorScore', 'aiCommunicationScore', 'aiOutcome', 'aiOutcomeNotes', 'aiNextStep'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.value = '';
    });
    this._set('aiOverallScore', '—');
    const err = document.getElementById('aiOutcomeError');
    if (err) { err.classList.add('d-none'); err.textContent = ''; }
    await this._loadApplicantSummary(applicationId, 'aiApplicantSummary');
    this._outcomeModal.show();
  },

  saveOutcome: async function () {
    const applicationId = document.getElementById('aiOutcomeApplicationId')?.value;
    const outcome = document.getElementById('aiOutcome')?.value;
    const nextStep = document.getElementById('aiNextStep')?.value;
    const scores = [
      Number(document.getElementById('aiAcademicScore')?.value || 0),
      Number(document.getElementById('aiBehaviorScore')?.value || 0),
      Number(document.getElementById('aiCommunicationScore')?.value || 0),
    ];
    const errEl = document.getElementById('aiOutcomeError');

    if (!outcome || !nextStep) {
      if (errEl) { errEl.textContent = 'Recommendation and next workflow step are required.'; errEl.classList.remove('d-none'); }
      return;
    }
    if (errEl) errEl.classList.add('d-none');

    const score = Math.round(scores.reduce((sum, item) => sum + item, 0) / scores.length);
    try {
      await this._api('/admission/record-interview-results', 'POST', {
        application_id: applicationId,
        assessment_data: {
          academic_readiness_score: scores[0],
          behavior_score: scores[1],
          communication_score: scores[2],
          score,
          interview_score: score,
          recommendation: outcome,
          next_step: nextStep,
          remarks: document.getElementById('aiOutcomeNotes')?.value.trim() || '',
        },
      });
      showNotification('Interview assessment recorded.', 'success');
      this._outcomeModal.hide();
      await this._loadData();
    } catch (e) {
      if (errEl) { errEl.textContent = e.message || 'Failed to save assessment.'; errEl.classList.remove('d-none'); }
    }
  },

  viewApplication: async function (applicationId) {
    const content = document.getElementById('aiViewContent');
    if (content) content.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-info"></div></div>';
    this._viewModal.show();
    try {
      const response = await this._api(`/admission/application/${applicationId}`, 'GET');
      const app = response?.data?.application || response?.application || {};
      const documents = response?.data?.documents || response?.documents || [];
      const workflowData = response?.data?.workflow_data || response?.workflow_data || this._parseWorkflowData(app);
      content.innerHTML = `
        <div class="row g-3">
          <div class="col-md-6">
            <h6 class="fw-semibold mb-3">Applicant Information</h6>
            <table class="table table-sm">
              <tr><td><strong>Application No:</strong></td><td>${this._esc(app.application_no || '—')}</td></tr>
              <tr><td><strong>Name:</strong></td><td>${this._esc(app.applicant_name || '—')}</td></tr>
              <tr><td><strong>Grade Applying For:</strong></td><td>${this._esc(app.grade_applying_for || '—')}</td></tr>
              <tr><td><strong>Current Stage:</strong></td><td>${this._stageBadge(app.current_stage)}</td></tr>
            </table>
          </div>
          <div class="col-md-6">
            <h6 class="fw-semibold mb-3">Interview Details</h6>
            <table class="table table-sm">
              <tr><td><strong>Date:</strong></td><td>${this._esc(workflowData.interview_date || '—')}</td></tr>
              <tr><td><strong>Time:</strong></td><td>${this._esc(workflowData.interview_time || '—')}</td></tr>
              <tr><td><strong>Venue:</strong></td><td>${this._esc(workflowData.venue || workflowData.location || '—')}</td></tr>
              <tr><td><strong>Recommendation:</strong></td><td>${this._esc(workflowData.recommendation || '—')}</td></tr>
            </table>
          </div>
        </div>
        <div class="mt-3">
          <h6 class="fw-semibold mb-2">Documents (${documents.length})</h6>
          ${documents.length ? documents.map(doc => `<span class="badge bg-light text-dark border me-1 mb-1">${this._esc(doc.document_type || 'Document')} · ${this._esc(doc.verification_status || 'pending')}</span>`).join('') : '<p class="text-muted mb-0">No documents uploaded</p>'}
        </div>`;
      const btn = document.getElementById('aiConductInterviewBtn');
      if (btn) {
        btn.onclick = () => {
          this._viewModal.hide();
          if (app.current_stage === 'interview_scheduling') this.showScheduleModal(applicationId);
          else this.showOutcomeModal(applicationId);
        };
        btn.classList.toggle('d-none', !['interview_scheduling', 'interview_results'].includes(app.current_stage));
      }
    } catch (e) {
      content.innerHTML = `<div class="alert alert-danger">Failed to load application details: ${this._esc(e.message)}</div>`;
    }
  },

  _loadApplicantSummary: async function (applicationId, targetId) {
    const target = document.getElementById(targetId);
    if (!target) return;
    try {
      const response = await this._api(`/admission/application/${applicationId}`, 'GET');
      const app = response?.data?.application || response?.application || {};
      target.innerHTML = `<strong>Applicant:</strong> ${this._esc(app.applicant_name || '—')}<br><strong>Grade:</strong> ${this._esc(app.grade_applying_for || '—')}<br><strong>Application No:</strong> ${this._esc(app.application_no || '—')}`;
    } catch (e) {
      target.innerHTML = '<span class="text-danger">Failed to load applicant details.</span>';
    }
  },

  _parseWorkflowData: function (app) {
    const raw = app?.data_json || app?.workflow_data_json || '{}';
    if (typeof raw === 'object' && raw !== null) return raw;
    try {
      return JSON.parse(raw || '{}') || {};
    } catch (e) {
      return {};
    }
  },

  _interviewerName: function (id) {
    if (!id) return '';
    const staff = this._staff.find(item => String(item.id) === String(id));
    return staff ? `${staff.first_name || ''} ${staff.last_name || ''}`.trim() : '';
  },

  _stageBadge: function (stage) {
    const badges = {
      interview_scheduling: '<span class="badge bg-warning text-dark">Scheduling Needed</span>',
      interview_results: '<span class="badge bg-info">Assessment Pending</span>',
    };
    return badges[stage] || `<span class="badge bg-secondary">${this._esc(stage || '—')}</span>`;
  },

  _set: (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; },
  _esc: s => { const d = document.createElement('div'); d.textContent = String(s ?? ''); return d.innerHTML; },
  _debounce: function (func, wait) {
    let timeout;
    return (...args) => {
      clearTimeout(timeout);
      timeout = setTimeout(() => func.apply(this, args), wait);
    };
  },
};

['aiAcademicScore', 'aiBehaviorScore', 'aiCommunicationScore'].forEach(id => {
  document.addEventListener('input', event => {
    if (event.target?.id !== id) return;
    const values = ['aiAcademicScore', 'aiBehaviorScore', 'aiCommunicationScore'].map(scoreId => Number(document.getElementById(scoreId)?.value || 0));
    const hasAny = values.some(value => value > 0);
    const overall = hasAny ? Math.round(values.reduce((sum, value) => sum + value, 0) / values.length) : '—';
    const target = document.getElementById('aiOverallScore');
    if (target) target.textContent = overall === '—' ? overall : `${overall}/100`;
  });
});
