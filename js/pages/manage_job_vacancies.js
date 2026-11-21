/**
 * manage_job_vacancies.js — Job Vacancies Controller (standalone page).
 * CRUD for public careers vacancies.
 */
const manageJobVacanciesController = {
  state: { items: [] },

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
    const colors = { open: 'success', closed: 'danger', filled: 'primary' };
    const c = colors[s] || 'secondary';
    return `<span class="badge bg-${c}">${s || '\u2014'}</span>`;
  },

  async loadData() {
    const body = document.getElementById('jobsTableBody');
    body.innerHTML = '<tr><td colspan="6" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    try {
      const r = await this.API('GET', 'website/jobs');
      this.state.items = r?.data?.items || [];
      this.render();
      this.loadStats();
    } catch (e) {
      body.innerHTML = `<tr><td colspan="6" class="text-center py-3 text-danger">${this.esc(e.message || 'Load failed')}</td></tr>`;
    }
  },

  loadStats() {
    const open = this.state.items.filter(j => j.status === 'open').length;
    document.getElementById('statJobs').textContent = open;
  },

  render() {
    const body = document.getElementById('jobsTableBody');
    if (!this.state.items.length) { body.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No job vacancies posted.</td></tr>'; return; }
    body.innerHTML = this.state.items.map(j => `
      <tr>
        <td class="fw-semibold small">${this.esc(j.title)}</td>
        <td class="text-muted small">${this.esc(j.department)}</td>
        <td><span class="ws-tag-chip" style="background:#eef2ff;color:#4338ca;border-color:#4338ca44">${this.esc(j.job_type)}</span></td>
        <td class="text-muted small">${this.fmtDate(j.deadline)}</td>
        <td>${this.badgeStatus(j.status)}</td>
        <td class="text-end">
          <button class="btn btn-sm btn-outline-secondary rounded-pill px-2 me-1" data-kw-onclick="vacanciesOpenModal(${j.id})"><i class="bi bi-pencil"></i></button>
          <button class="btn btn-sm btn-outline-danger rounded-pill px-2" data-kw-onclick="vacanciesClose(${j.id},'${this.esc(j.title).replace(/'/g, '')}')"><i class="bi bi-x-circle"></i></button>
        </td>
      </tr>`).join('');
  },

  async openModal(id = null) {
    document.getElementById('jobEditId').value = id || '';
    document.getElementById('wsJobModalTitle').textContent = id ? 'Edit Vacancy' : 'Post Vacancy';
    ['jobTitle', 'jobDepartment', 'jobLocation', 'jobDescription', 'jobRequirements', 'jobResponsibilities', 'jobDeadline'].forEach(f => {
      const el = document.getElementById(f); if (el) el.value = '';
    });
    document.getElementById('jobType').value   = 'Full-Time';
    document.getElementById('jobStatus').value = 'open';
    document.getElementById('jobLocation').value = 'Londiani, Kenya';
    if (id) {
      const r = await this.API('GET', `website/jobs/${id}`);
      const j = r?.data;
      if (j) {
        document.getElementById('jobTitle').value          = j.title || '';
        document.getElementById('jobDepartment').value     = j.department || '';
        document.getElementById('jobLocation').value       = j.location || 'Londiani, Kenya';
        document.getElementById('jobDescription').value    = j.description || '';
        document.getElementById('jobDeadline').value       = j.deadline?.split('T')[0] || '';
        document.getElementById('jobType').value           = j.job_type || 'Full-Time';
        document.getElementById('jobStatus').value         = j.status || 'open';
        try { document.getElementById('jobRequirements').value     = JSON.parse(j.requirements || '[]').join('\n'); } catch (_) {}
        try { document.getElementById('jobResponsibilities').value = JSON.parse(j.responsibilities || '[]').join('\n'); } catch (_) {}
      }
    }
    bootstrap.Modal.getOrCreateInstance(document.getElementById('wsJobModal')).show();
  },

  async save() {
    const id = document.getElementById('jobEditId').value;
    const reqLines  = document.getElementById('jobRequirements').value.split('\n').map(l => l.trim()).filter(Boolean);
    const respLines = document.getElementById('jobResponsibilities').value.split('\n').map(l => l.trim()).filter(Boolean);
    const payload = {
      title:            document.getElementById('jobTitle').value.trim(),
      department:       document.getElementById('jobDepartment').value.trim(),
      job_type:         document.getElementById('jobType').value,
      location:         document.getElementById('jobLocation').value.trim(),
      description:      document.getElementById('jobDescription').value.trim(),
      requirements:     JSON.stringify(reqLines),
      responsibilities: JSON.stringify(respLines),
      deadline:         document.getElementById('jobDeadline').value,
      status:           document.getElementById('jobStatus').value,
    };
    if (!payload.title || !payload.deadline) return this.notify('Title and deadline are required.', 'warning');
    try {
      const r = id ? await this.API('PUT', `website/jobs/${id}`, payload) : await this.API('POST', 'website/jobs', payload);
      if (r.status === 'success') {
        this.notify(id ? 'Vacancy updated' : 'Vacancy posted');
        bootstrap.Modal.getInstance(document.getElementById('wsJobModal')).hide();
        this.loadData();
      } else this.notify(r.message, 'danger');
    } catch (e) { this.notify(e.message, 'danger'); }
  },

  async close(id, title) {
    if (!(await window.confirmAction('Confirm', `Close vacancy "${title}"?`))) return;
    try { await this.API('DELETE', `website/jobs/${id}`); this.notify('Vacancy closed'); this.loadData(); }
    catch (e) { this.notify(e.message, 'danger'); }
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    this.loadData();
  },
};

window.vacanciesOpenModal = (id) => manageJobVacanciesController.openModal(id);
window.vacanciesSave      = () => manageJobVacanciesController.save();
window.vacanciesClose     = (id, title) => manageJobVacanciesController.close(id, title);

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => manageJobVacanciesController.init().catch(() => {}));
} else {
  manageJobVacanciesController.init().catch(() => {});
}

window.manageJobVacanciesController = manageJobVacanciesController;
