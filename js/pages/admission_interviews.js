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
  _sessionsModal: null,
  _sessions: [],
  _windows: [],
  _initialized: false,

  init: async function () {
    if (this._initialized) return;
    this._initialized = true;

    await window.AuthContext?.ready();
    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || '') + '/index.php';
      return;
    }

    this._schedModal = new bootstrap.Modal(document.getElementById('aiScheduleModal'));
    this._outcomeModal = new bootstrap.Modal(document.getElementById('aiOutcomeModal'));
    this._viewModal = new bootstrap.Modal(document.getElementById('aiViewModal'));
    this._sessionsModal = new bootstrap.Modal(document.getElementById('aiSessionsModal'));
    this._bindFilters();
    await Promise.all([this._loadData(), this._loadStaff(), this._loadSessions(), this._loadWindows()]);
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
      const response = await this._api('/staff/teachers', 'GET');
      this._staff = Array.isArray(response?.data) ? response.data : (Array.isArray(response) ? response : []);
      const filter = document.getElementById('aiFilterInterviewer');
      const options = this._staff.map(s => {
        const name = `${s.first_name || ''} ${s.last_name || ''}`.trim() || s.full_name || s.name || 'Staff';
        return `<option value="${this._esc(s.id)}">${this._esc(name)} — ${this._esc(s.role_name || s.designation || '')}</option>`;
      }).join('');
      if (filter) filter.innerHTML = '<option value="">All Interviewers</option>' + options;
      ['aiSessionInterviewer'].forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        el.multiple = true;
        el.setAttribute('aria-multiselectable', 'true');
        el.innerHTML = options;
        // Native multi-selects normally require Ctrl/Command. Admissions uses
        // click-to-toggle so each teacher can be selected independently.
        if (el.dataset.clickToggleBound !== 'true') {
          el.addEventListener('mousedown', event => {
            const option = event.target.closest('option');
            if (!option) return;
            event.preventDefault();
            option.selected = !option.selected;
            el.dispatchEvent(new Event('change', { bubbles: true }));
          });
          el.dataset.clickToggleBound = 'true';
        }
      });
    } catch (e) {
      console.warn('Staff failed:', e);
    }
  },

  _loadSessions: async function () {
    try {
      const response = await this._api('/admission/interview-sessions', 'GET');
      this._sessions = response?.data?.sessions || response?.sessions || [];
      this._populateSessionSelect();
    } catch (e) { console.warn('Interview sessions failed:', e); }
  },

  _loadWindows: async function () {
    try {
      const response = await this._api('/admission/windows', 'GET');
      this._windows = response?.data?.windows || response?.windows || [];
      const el = document.getElementById('aiSessionWindow');
      if (el) el.innerHTML = this._windows.length ? this._windows.map(w => `<option value="${this._esc(w.id)}">${this._esc(w.label || ('Window #' + w.id))}</option>`).join('') : '<option value="">No admission windows</option>';
    } catch (e) { console.warn('Admission windows failed:', e); }
  },

  _populateSessionSelect: function () {
    const el = document.getElementById('aiSessionId'); if (!el) return;
    const available = this._sessions.filter(s => ['scheduled','full'].includes(s.status) && Number(s.assigned_count) < Number(s.capacity));
    el.innerHTML = '<option value="">— Select a configured session —</option>' + available.map(s => `<option value="${this._esc(s.id)}">${this._esc(s.window_label)} · ${this._esc(s.session_date)} ${this._esc(String(s.start_time).slice(0,5))} · ${this._esc(s.venue)} · ${this._esc(s.assigned_count)}/${this._esc(s.capacity)}</option>`).join('');
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
      const fallbackAreas = Array.isArray(data.assessment_items) ? data.assessment_items.map(item => item.learning_area_name).filter(Boolean).join(', ') : '';
      return `<tr class="${isToday ? 'table-info' : ''}">
        <td class="fw-semibold">${this._esc(app.applicant_name || 'Unknown')}${isToday ? ' <span class="badge bg-primary ms-1">Today</span>' : ''}<br><small class="text-muted">${this._esc(app.application_no || '—')}</small></td>
        <td>${this._esc(app.grade_applying_for || '—')}</td>
        <td>${this._esc(app.interview_date || data.interview_date || 'Not scheduled')}</td>
        <td>${this._esc(app.interview_time || data.interview_time || '—')}</td>
        <td>${this._esc(app.interview_interviewer_name || this._interviewerName(app.interview_interviewer_id) || '—')}<br><small class="text-muted">${this._esc(app.interview_interviewer_phone || '')}</small></td>
        <td>${this._esc(app.interview_venue || data.venue || data.location || '—')}</td>
        <td>${this._esc(app.tested_learning_areas || data.tested_learning_areas || fallbackAreas || 'Not specified')}</td>
        <td>${stageBadge}</td>
        <td>${this._esc(data.recommendation || '—')}</td>
        <td class="text-end">
          <div class="btn-group btn-group-sm">
            <button class="btn btn-outline-primary" onclick="admissionInterviewsController.viewApplication(${app.id})">View</button>
            ${canSchedule && !app.interview_id ? `<button class="btn btn-outline-success" onclick="admissionInterviewsController.showScheduleModal(${app.id})">Assign session</button>` : ''}
            ${app.interview_id ? `<button class="btn btn-outline-warning" onclick="admissionInterviewsController.showScheduleModal(${app.id})">Switch / reschedule</button>` : ''}
            ${app.interview_id ? `<button class="btn btn-outline-info" onclick="admissionInterviewsController.notify(${app.id})">Notify parent</button>` : ''}
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
              <th>Interviewer</th><th>Location</th><th>Tested learning areas</th><th>Stage</th><th>Recommendation</th><th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>${rows}</tbody>
        </table>
      </div>`;
  },

  showScheduleModal: async function (applicationId = null) {
    ['aiSessionId', 'aiSpecialRequirements'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.value = '';
    });
    const applicant = document.getElementById('aiApplicantId');
    const row = this._data.find(a => String(a.id) === String(applicationId));
    if (applicant) {
      if (row && ![...applicant.options].some(o => String(o.value) === String(applicationId))) {
        applicant.insertAdjacentHTML('beforeend', `<option value="${this._esc(applicationId)}">${this._esc(row.applicant_name || 'Applicant')} — ${this._esc(row.grade_applying_for || '')} (already scheduled)</option>`);
      }
      applicant.value = applicationId || '';
    }
    if (row?.interview_session_id) document.getElementById('aiSessionId').value = row.interview_session_id;
    const areas = document.getElementById('aiLearningAreas');
    if (areas) {
      areas.innerHTML = '<option disabled>Loading current-class learning areas…</option>';
      try {
        const response = await this._api(`/admission/application/${applicationId}`, 'GET');
        const available = response?.data?.interview_learning_areas || response?.interview_learning_areas || [];
        areas.innerHTML = available.map(area => `<option value="${this._esc(area.id)}">${this._esc(area.name)}</option>`).join('') || '<option disabled>No curriculum areas configured for the current class</option>';
        areas.multiple = true;
        if (areas.dataset.clickToggleBound !== 'true') {
          areas.addEventListener('mousedown', event => {
            const option = event.target.closest('option');
            if (!option || option.disabled) return;
            event.preventDefault();
            if (!option.selected && [...areas.selectedOptions].length >= 3) {
              showNotification('Select at most three learning areas.', 'error');
              return;
            }
            option.selected = !option.selected;
          });
          areas.dataset.clickToggleBound = 'true';
        }
      } catch (e) {
        areas.innerHTML = '<option disabled>Unable to load current-class learning areas</option>';
      }
    }
    const err = document.getElementById('aiScheduleError');
    if (err) { err.classList.add('d-none'); err.textContent = ''; }
    this._schedModal.show();
  },

  saveSchedule: async function () {
    const applicationId = document.getElementById('aiApplicantId')?.value;
    const sessionId = document.getElementById('aiSessionId')?.value;
    const errEl = document.getElementById('aiScheduleError');

    const learningAreaIds = [...(document.getElementById('aiLearningAreas')?.selectedOptions || [])].map(option => Number(option.value)).filter(Boolean);
    if (!applicationId || !sessionId) {
      if (errEl) { errEl.textContent = 'Applicant and a configured interview session are required.'; errEl.classList.remove('d-none'); }
      return;
    }
    if (learningAreaIds.length < 1 || learningAreaIds.length > 3) {
      if (errEl) { errEl.textContent = 'Select between one and three learning areas from the applicant\'s current class.'; errEl.classList.remove('d-none'); }
      return;
    }
    if (errEl) errEl.classList.add('d-none');

    try {
      const existing = this._data.find(a => String(a.id) === String(applicationId))?.interview_id;
      await this._api(existing ? '/admission/interview-assignment' : '/admission/schedule-interview', 'POST', {
        application_id: applicationId,
        session_id: sessionId,
        reason: 'Interview session selected by admissions staff',
        learning_area_ids: learningAreaIds
      });
      showNotification('Interview scheduled.', 'success');
      this._schedModal.hide();
      await Promise.all([this._loadData(), this._loadSessions()]);
    } catch (e) {
      if (errEl) { errEl.textContent = e.message || 'Failed to schedule interview.'; errEl.classList.remove('d-none'); }
    }
  },

  notify: async function (applicationId) {
    try { const result = await this._api('/admission/interview-notifications', 'POST', {application_id: applicationId}); showNotification(result?.message || 'Parent SMS and email queued.', 'success'); }
    catch (e) { showNotification(e.message || 'Notification failed.', 'error'); }
  },

  manageSessions: async function () {
    await Promise.all([this._loadSessions(), this._loadWindows()]);
    const body = document.getElementById('aiSessionsBody');
    if (body) body.innerHTML = this._sessions.length ? `<div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Intake</th><th>Date/time</th><th>Venue</th><th>Supervisors / invigilators</th><th>Assigned</th><th>Applicants</th><th>Actions</th></tr></thead><tbody>${this._sessions.map(s => `<tr><td>${this._esc(s.window_label)}</td><td>${this._esc(s.session_date)} ${this._esc(String(s.start_time).slice(0,5))}–${this._esc(String(s.end_time).slice(0,5))}</td><td>${this._esc(s.venue)}</td><td>${this._esc(s.supervisor_names || s.interviewer_name || '—')}<br><small class="text-muted">${this._esc(s.interviewer_phone || 'No phone')}</small></td><td>${this._esc(s.assigned_count)}/${this._esc(s.capacity)}</td><td>${this._esc((s.assigned_applicants || '').split('||').filter(Boolean).join(', ') || 'None')}</td><td><button class="btn btn-sm btn-outline-primary" onclick="admissionInterviewsController.editSession(${s.id})">Edit</button></td></tr>`).join('')}</tbody></table></div>` : '<div class="alert alert-info">No interview sessions have been configured.</div>';
    this._sessionsModal.show();
  },

  editSession: function (id) {
    const s = this._sessions.find(x => Number(x.id) === Number(id)); if (!s) return;
    ['aiSessionEditId','aiSessionWindow','aiSessionDate','aiSessionStart','aiSessionEnd','aiSessionCapacity','aiSessionVenue'].forEach(id => { const e=document.getElementById(id); if(e) e.value=''; });
    const supervisorSelect = document.getElementById('aiSessionInterviewer'); if (supervisorSelect) [...supervisorSelect.options].forEach(o => { o.selected = false; });
    document.getElementById('aiSessionEditId').value=s.id; document.getElementById('aiSessionWindow').value=s.admission_window_id; document.getElementById('aiSessionDate').value=s.session_date; document.getElementById('aiSessionStart').value=s.start_time; document.getElementById('aiSessionEnd').value=s.end_time; document.getElementById('aiSessionCapacity').value=s.capacity; const selectedIds = (Array.isArray(s.supervisor_ids) && s.supervisor_ids.length) ? s.supervisor_ids.map(String) : [String(s.interviewer_id || '')]; if (supervisorSelect) [...supervisorSelect.options].forEach(o => { o.selected = selectedIds.includes(String(o.value)); }); document.getElementById('aiSessionVenue').value=s.venue;
  },

  saveSession: async function () {
    const supervisors = [...(document.getElementById('aiSessionInterviewer')?.selectedOptions || [])].map(o => Number(o.value)); const data={id:document.getElementById('aiSessionEditId').value,admission_window_id:document.getElementById('aiSessionWindow').value,session_date:document.getElementById('aiSessionDate').value,start_time:document.getElementById('aiSessionStart').value,end_time:document.getElementById('aiSessionEnd').value,capacity:document.getElementById('aiSessionCapacity').value,supervisor_ids:supervisors,interviewer_id:supervisors[0] || '',venue:document.getElementById('aiSessionVenue').value};
    try { await this._api(data.id ? `/admission/interview-sessions/${data.id}` : '/admission/interview-sessions',data.id ? 'PUT' : 'POST',data); showNotification('Interview session saved.','success'); await this._loadSessions(); await this.manageSessions(); }
    catch(e){const el=document.getElementById('aiSessionError');el.textContent=e.message||'Unable to save session';el.classList.remove('d-none');}
  },

  showOutcomeModal: async function (applicationId) {
    document.getElementById('aiOutcomeApplicationId').value = applicationId;
    document.getElementById('aiOutcomeInterviewId').value = applicationId;
    ['aiAcademicScore', 'aiBehaviorScore', 'aiCommunicationScore', 'aiOutcome', 'aiOutcomeNotes'].forEach(id => {
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
    const errEl = document.getElementById('aiOutcomeError');

    const inputs = [...document.querySelectorAll('#aiAssessmentItems [data-assessment-score]')];
    const items = inputs.map(input => ({ learning_area_id: Number(input.dataset.learningAreaId || 0) || null, learning_area_name: input.dataset.learningAreaName || 'Assessment', max_score: Number(input.max || 100), score: Number(input.value) }));
    if (!outcome || !items.length || items.some(item => !Number.isFinite(item.score) || item.score < 0 || item.score > item.max_score)) {
      if (errEl) { errEl.textContent = 'Select a recommendation and enter a valid score for every tested learning area.'; errEl.classList.remove('d-none'); }
      return;
    }
    if (errEl) errEl.classList.add('d-none');

    const score = Math.round(items.reduce((sum, item) => sum + item.score, 0) / items.length);
    try {
      await this._api('/admission/record-interview-results', 'POST', {
        application_id: applicationId,
        assessment_data: {
          assessment_items: items,
          score,
          interview_score: score,
          recommendation: outcome,
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
      const items = response?.data?.assessment_items || response?.assessment_items || [];
      target.innerHTML = `<strong>Applicant:</strong> ${this._esc(app.applicant_name || '—')}<br><strong>Grade:</strong> ${this._esc(app.grade_applying_for || '—')}<br><strong>Application No:</strong> ${this._esc(app.application_no || '—')}`;
      const container = document.getElementById('aiAssessmentItems');
      if (container) {
        const rows = items.length ? items : [{ learning_area_id: '', learning_area_name: 'Interview assessment', max_score: 100 }];
        container.innerHTML = rows.map(item => `<div class="col-md-6"><label class="form-label">${this._esc(item.learning_area_name)}${item.competency ? ' — ' + this._esc(item.competency) : ''}</label><input type="number" class="form-control" min="0" max="${Number(item.max_score || 100)}" data-assessment-score data-learning-area-id="${this._esc(item.learning_area_id || '')}" data-learning-area-name="${this._esc(item.learning_area_name || 'Assessment')}" placeholder="0-${Number(item.max_score || 100)}"></div>`).join('');
      }
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
document.addEventListener('input', event => {
  if (!event.target?.matches('#aiAssessmentItems [data-assessment-score]')) return;
  const values = [...document.querySelectorAll('#aiAssessmentItems [data-assessment-score]')].map(input => Number(input.value)).filter(Number.isFinite);
  const target = document.getElementById('aiOverallScore');
  if (target) target.textContent = values.length ? `${Math.round(values.reduce((sum, value) => sum + value, 0) / values.length)}/100` : '—';
});

window.admissionInterviewsController = admissionInterviewsController;
