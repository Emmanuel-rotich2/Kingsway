/**
 * manage_job_applications.js — Job Applications Controller (standalone page).
 * Recruitment review: shortlist, schedule/record interviews, and progress applicants.
 */
const jobAppsController = {
  state: { items: [] },
  assignmentOptions: { departments: [], staff_types: [], staff_categories: [] },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  notify(msg, type = 'success') {
    if (window.showNotification) { showNotification(msg, type); return; }
    const el = document.createElement('div');
    el.className = `alert alert-${type} position-fixed top-0 end-0 m-3 shadow`;
    el.style.zIndex = 99999;
    el.textContent = msg;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 3000);
  },

  esc(s) { return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); },
  fmtDate(d) { return d ? new Date(d).toLocaleDateString('en-KE', { day: '2-digit', month: 'short', year: 'numeric' }) : '\u2014'; },
  badgeStatus(s) {
    const colors = { received: 'primary', shortlisted: 'info', interview_scheduled: 'warning', interviewed: 'secondary', hired: 'success', rejected: 'danger' };
    const c = colors[s] || 'secondary';
    const label = (s || '').replace(/_/g, ' ');
    return `<span class="badge bg-${c}">${label || '\u2014'}</span>`;
  },

  async updateStatus(id, status) {
    if (status === 'hired') return this.notify('Create and approve the school employment proposal first. The application becomes hired when the staff account is created.', 'warning');
    try { await this.API('PUT', `website/job-applications/${id}`, { status }); this.notify('Application status updated'); await this.loadData(); }
    catch (e) { this.notify(e.message || 'Could not update application', 'danger'); }
  },

  async openEmploymentProposal(application) {
    try {
      if (!this.assignmentOptions.departments.length) {
        const response = await this.API('GET', 'staff-appointments/job-application-options');
        this.assignmentOptions = response?.data?.data || response?.data || this.assignmentOptions;
      }
      const fill = (id, rows, label) => {
        const select = document.getElementById(id);
        select.innerHTML = `<option value="">Select ${label}</option>` + rows.map(x => `<option value="${Number(x.id)}">${this.esc(x.name)}</option>`).join('');
      };
      fill('jobEmploymentDepartment', this.assignmentOptions.departments, 'department');
      fill('jobEmploymentStaffType', this.assignmentOptions.staff_types, 'staff type');
      this.renderEmploymentCategories();
      document.getElementById('jobEmploymentApplicationId').value = application.id;
      document.getElementById('jobEmploymentPosition').value = application.job_title || '';
      document.getElementById('jobEmploymentDate').value = '';
      bootstrap.Modal.getOrCreateInstance(document.getElementById('jobEmploymentProposalModal')).show();
    } catch (e) { this.notify(e.message || 'Could not load staff assignment options', 'danger'); }
  },

  renderEmploymentCategories() {
    const typeId = Number(document.getElementById('jobEmploymentStaffType').value || 0);
    const rows = this.assignmentOptions.staff_categories.filter(x => Number(x.staff_type_id) === typeId);
    const select = document.getElementById('jobEmploymentStaffCategory');
    select.innerHTML = '<option value="">Select staff category</option>' + rows.map(x => `<option value="${Number(x.id)}">${this.esc(x.name)}</option>`).join('');
  },

  async submitEmploymentProposal(event) {
    event.preventDefault();
    const id = Number(document.getElementById('jobEmploymentApplicationId').value);
    const payload = {
      department_id: document.getElementById('jobEmploymentDepartment').value,
      position: document.getElementById('jobEmploymentPosition').value.trim(),
      employment_date: document.getElementById('jobEmploymentDate').value,
      contract_type: document.getElementById('jobEmploymentContract').value,
      staff_type_id: document.getElementById('jobEmploymentStaffType').value,
      staff_category_id: document.getElementById('jobEmploymentStaffCategory').value,
    };
    try {
      await this.API('POST', `staff-appointments/job-application/${id}`, payload);
      bootstrap.Modal.getInstance(document.getElementById('jobEmploymentProposalModal'))?.hide();
      this.notify('Employment proposal submitted for Director approval.');
      await this.loadData();
    } catch (e) { this.notify(e.message || 'Could not submit employment proposal', 'danger'); }
  },

  openInterview(application, complete = false) {
    document.getElementById('jobInterviewApplicationId').value = application.id;
    document.getElementById('jobInterviewId').value = application.interview_id || '';
    document.getElementById('jobInterviewModalTitle').textContent = complete ? 'Record interview outcome' : 'Schedule interview';
    document.getElementById('jobInterviewScheduleFields').classList.toggle('d-none', complete);
    document.getElementById('jobInterviewCompletionFields').classList.toggle('d-none', !complete);
    document.getElementById('jobInterviewScheduledAt').value = application.interview_scheduled_at ? application.interview_scheduled_at.replace(' ', 'T').slice(0, 16) : '';
    document.getElementById('jobInterviewMode').value = application.interview_mode || 'in_person';
    document.getElementById('jobInterviewLocation').value = application.interview_location || '';
    document.getElementById('jobInterviewScore').value = application.interview_score || '';
    document.getElementById('jobInterviewNotes').value = application.interview_notes || '';
    document.getElementById('jobInterviewForm').dataset.complete = complete ? '1' : '0';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('jobInterviewModal')).show();
  },

  async saveInterview(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const complete = form.dataset.complete === '1';
    try {
      if (complete) {
        const id = document.getElementById('jobInterviewId').value;
        if (!id) throw new Error('No scheduled interview found');
        await this.API('PUT', `website/job-applications/interviews/${id}`, { score: document.getElementById('jobInterviewScore').value || null, notes: document.getElementById('jobInterviewNotes').value || null });
      } else {
        const id = document.getElementById('jobInterviewApplicationId').value;
        const scheduledAt = document.getElementById('jobInterviewScheduledAt').value;
        if (!scheduledAt) throw new Error('Interview date and time are required');
        await this.API('POST', `website/job-applications/${id}/interview`, { scheduled_at: scheduledAt.replace('T', ' '), mode: document.getElementById('jobInterviewMode').value, location: document.getElementById('jobInterviewLocation').value || null });
      }
      bootstrap.Modal.getInstance(document.getElementById('jobInterviewModal'))?.hide();
      this.notify(complete ? 'Interview outcome recorded' : 'Interview scheduled');
      await this.loadData();
    } catch (e) { this.notify(e.message || 'Could not save interview', 'danger'); }
  },

  async loadData() {
    const body = document.getElementById('jobAppsTableBody');
    body.innerHTML = '<tr><td colspan="8" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    try {
      const r = await this.API('GET', 'website/job-applications');
      this.state.items = r?.data?.items || [];
      this.render();
    } catch (e) {
      body.innerHTML = `<tr><td colspan="8" class="text-center py-3 text-danger">${this.esc(e.message || 'Load failed')}</td></tr>`;
    }
  },

  render() {
    const body = document.getElementById('jobAppsTableBody');
    document.getElementById('statApps').textContent = this.state.items.length;
    const status = document.getElementById('appStatusFilter').value;
    const q = (document.getElementById('appSearch').value || '').toLowerCase();
    const filtered = this.state.items.filter(a => {
      const okStatus = !status || a.status === status;
      const hay = `${a.first_name} ${a.last_name} ${a.job_title} ${a.email} ${a.tsc_number || ''}`.toLowerCase();
      return okStatus && (!q || hay.includes(q));
    });
    if (!filtered.length) { body.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">No job applications found.</td></tr>'; return; }
    body.innerHTML = filtered.map(a => `
      <tr>
        <td class="fw-semibold small">${this.esc(a.first_name)} ${this.esc(a.last_name)}</td>
        <td class="small">${this.esc(a.job_title)}</td>
        <td class="small text-muted">${this.esc(a.email)}</td>
        <td class="small text-muted">${this.esc(a.phone)}</td>
        <td class="small text-muted">${this.esc(a.tsc_number || '\u2014')}</td>
        <td>${this.badgeStatus(a.status)}</td>
        <td class="small text-muted">${this.fmtDate(a.created_at)}</td>
        <td class="text-end">
          <button class="btn btn-sm btn-outline-secondary rounded-pill px-2" title="Copy email" onclick="jobAppsCopy('${this.esc(a.email)}')"><i class="bi bi-envelope"></i></button>
          ${a.status === 'received' ? `<button class="btn btn-sm btn-outline-primary rounded-pill px-2 ms-1" onclick="jobAppsAction('shortlist', ${a.id})" title="Shortlist"><i class="bi bi-check2"></i></button>` : ''}
          ${['shortlisted','received'].includes(a.status) ? `<button class="btn btn-sm btn-outline-warning rounded-pill px-2 ms-1" onclick="jobAppsAction('schedule', ${a.id})" title="Schedule interview"><i class="bi bi-calendar-plus"></i></button>` : ''}
          ${a.status === 'interview_scheduled' && a.interview_id ? `<button class="btn btn-sm btn-outline-success rounded-pill px-2 ms-1" onclick="jobAppsAction('complete', ${a.id})" title="Record interview"><i class="bi bi-clipboard-check"></i></button>` : ''}
          ${a.status === 'interviewed' && !a.staff_id && !a.staff_appointment_id ? `<button class="btn btn-sm btn-success rounded-pill px-2 ms-1" onclick="jobAppsAction('employment', ${a.id})" title="Prepare school employment proposal"><i class="bi bi-person-check"></i><span class="ms-1">Prepare hire</span></button>` : ''}
          ${a.staff_appointment_id && !a.staff_id ? `<span class="badge text-bg-warning ms-1">Proposal: ${this.esc(a.staff_appointment_status || 'submitted')}</span>` : ''}
          ${a.staff_id ? `<span class="badge text-bg-success ms-1">Staff account created</span>` : ''}
        </td>
      </tr>`).join('');
  },

  copy(email) {
    navigator.clipboard?.writeText(email).then(() => this.notify('Email copied')).catch(() => {});
  },

  setupEventListeners() {
    document.getElementById('appStatusFilter')?.addEventListener('change', () => this.render());
    const searchEl = document.getElementById('appSearch');
    if (searchEl) searchEl.addEventListener('keyup', () => this.render());
    document.getElementById('jobInterviewForm')?.addEventListener('submit', e => this.saveInterview(e));
    document.getElementById('jobEmploymentProposalForm')?.addEventListener('submit', e => this.submitEmploymentProposal(e));
    document.getElementById('jobEmploymentStaffType')?.addEventListener('change', () => this.renderEmploymentCategories());
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    this.setupEventListeners();
    this.loadData();
  },
};

window.jobAppsController = jobAppsController;
window.jobAppsCopy       = (email) => jobAppsController.copy(email);
window.jobAppsAction = (action, id) => {
  const app = jobAppsController.state.items.find(x => Number(x.id) === Number(id));
  if (!app) return;
  if (action === 'schedule') return jobAppsController.openInterview(app, false);
  if (action === 'complete') return jobAppsController.openInterview(app, true);
  if (action === 'shortlist') return jobAppsController.updateStatus(id, 'shortlisted');
  if (action === 'employment') return jobAppsController.openEmploymentProposal(app);
};

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => jobAppsController.init().catch(() => {}));
} else {
  jobAppsController.init().catch(() => {});
}
