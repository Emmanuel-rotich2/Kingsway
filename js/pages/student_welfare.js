/** Welfare case list and creation flow backed by the authorized student APIs. */
const studentWelfareController = {
  state: { cases: [], students: [], staff: [], meta: null },
  api(method, endpoint, data = null, params = null) { return window.callAPI(endpoint, method, data, params); },
  esc(value) { return String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' })[c]); },
  payload(response) { return response?.data?.data ?? response?.data ?? response ?? {}; },
  async load() {
    const body = document.getElementById('casesTableBody');
    if (body) body.innerHTML = '<tr><td colspan="13" class="text-center py-4">Loading welfare cases…</td></tr>';
    document.getElementById('casesError')?.classList.add('d-none');
    try {
      const filters = {
        class_id: document.getElementById('classFilter')?.value || '',
        stream_id: document.getElementById('streamFilter')?.value || '',
        gender: document.getElementById('genderFilter')?.value || '',
        welfare_category: document.getElementById('categoryFilter')?.value || '',
        referral_source: document.getElementById('referralSourceFilter')?.value || '',
        priority: document.getElementById('priorityFilter')?.value || '',
        status: document.getElementById('statusFilter')?.value || '',
        assigned_to: document.getElementById('assignedToFilter')?.value || '',
        search: document.getElementById('searchBox')?.value?.trim() || '',
      };
      Object.keys(filters).forEach((key) => { if (!filters[key]) delete filters[key]; });
      const [caseResponse, metaResponse] = await Promise.all([
        this.api('GET', '/api/students/welfare-cases', null, filters),
        this.state.meta ? Promise.resolve({ data: this.state.meta }) : this.api('GET', '/api/students/welfare-meta'),
      ]);
      const cases = this.payload(caseResponse); const meta = this.payload(metaResponse);
      this.state.cases = Array.isArray(cases) ? cases : [];
      this.state.meta = meta;
      this.state.students = Array.isArray(meta.students) ? meta.students : [];
      this.state.staff = Array.isArray(meta.staff) ? meta.staff : [];
      this.fillReferences(meta); this.renderCases(); this.renderStats();
    } catch (error) {
      const message = error?.message || 'Welfare cases could not be loaded.';
      if (body) body.innerHTML = `<tr><td colspan="13" class="text-center text-danger py-4">${this.esc(message)}</td></tr>`;
      const alert = document.getElementById('casesError'); if (alert) { alert.textContent = message; alert.classList.remove('d-none'); }
    }
  },
  fillReferences(meta) {
    const fill = (id, rows, label) => { const select = document.getElementById(id); if (!select || select.dataset.loaded) return; select.innerHTML += rows.map((row) => `<option value="${Number(row.id)}">${this.esc(label(row))}</option>`).join(''); select.dataset.loaded = '1'; };
    fill('classFilter', meta.classes || [], (row) => row.name);
    fill('streamFilter', meta.streams || [], (row) => row.name || row.stream_name);
    fill('assignedToFilter', meta.staff || [], (row) => row.name || row.full_name);
    const student = document.getElementById('newCaseStudent');
    if (student && !student.dataset.loaded) { student.innerHTML += this.state.students.map((row) => `<option value="${Number(row.id)}">${this.esc(row.name || row.full_name)} · ${this.esc(row.admission_number || row.admission_no)}</option>`).join(''); student.dataset.loaded = '1'; }
    const assigned = document.getElementById('newCaseAssignedTo');
    if (assigned && !assigned.dataset.loaded) { assigned.innerHTML += this.state.staff.map((row) => `<option value="${Number(row.id)}">${this.esc(row.name || row.full_name)}</option>`).join(''); assigned.dataset.loaded = '1'; }
  },
  renderCases() {
    const body = document.getElementById('casesTableBody'); if (!body) return;
    const rows = this.state.cases;
    if (!rows.length) { body.innerHTML = '<tr><td colspan="13" class="text-center text-muted py-4">No welfare cases match these filters.</td></tr>'; return; }
    body.innerHTML = rows.map((row) => `<tr><td>${this.esc(row.case_code || row.id)}</td><td>${this.esc(row.full_name || '—')}</td><td>${this.esc(row.admission_no || '—')}</td><td>${this.esc(row.class_name || '—')}</td><td>${this.esc(row.stream_name || '—')}</td><td>${this.esc(row.welfare_category || '—')}</td><td>${this.esc(row.referral_source || '—')}</td><td><span class="badge text-bg-${row.priority === 'urgent' || row.priority === 'high' ? 'danger' : 'secondary'}">${this.esc(row.priority || '—')}</span></td><td>${this.esc(row.status || '—')}</td><td>${this.esc(row.assigned_to_name || '—')}</td><td>${this.date(row.last_interaction)}</td><td>${this.date(row.next_follow_up_at)}</td><td><button class="btn btn-sm btn-outline-primary" type="button" onclick="studentWelfareController.viewCase(${Number(row.id)})" aria-label="View welfare case"><i class="bi bi-eye"></i></button></td></tr>`).join('');
  },
  renderStats() {
    const rows = this.state.cases;
    const totals = { totalCases: rows.length, activeCases: rows.filter((row) => ['open','in_progress'].includes(row.status)).length, highPriorityCases: rows.filter((row) => ['high','urgent'].includes(row.priority)).length, followUpsDue: rows.filter((row) => row.next_follow_up_at && new Date(row.next_follow_up_at) <= new Date()).length, referralsCount: rows.filter((row) => row.referral_source).length, resolvedCases: rows.filter((row) => ['resolved','closed'].includes(row.status)).length };
    Object.entries(totals).forEach(([id, value]) => { const node = document.getElementById(id); if (node) node.textContent = String(value); });
  },
  date(value) { if (!value) return '—'; const date = new Date(String(value).replace(' ', 'T')); return Number.isNaN(date.getTime()) ? this.esc(value) : date.toLocaleDateString(); },
  async createCase() {
    const form = document.getElementById('newCaseForm');
    if (!form?.reportValidity()) return;
    const data = {
      student_id: Number(document.getElementById('newCaseStudent').value),
      title: document.getElementById('newCaseTitle').value.trim(),
      welfare_category: document.getElementById('newCaseCategory').value,
      referral_source: document.getElementById('newCaseReferralSource').value || null,
      priority: document.getElementById('newCasePriority').value,
      description: document.getElementById('newCaseDescription').value.trim(),
      assigned_to: document.getElementById('newCaseAssignedTo').value ? Number(document.getElementById('newCaseAssignedTo').value) : null,
      next_follow_up_at: document.getElementById('newCaseFollowUpDate').value || null,
    };
    try {
      await this.api('POST', '/api/students/welfare-case', data);
      window.showNotification?.('Welfare case created.', 'success');
      form.reset();
      const modal = document.getElementById('newCaseModal'); if (modal && window.bootstrap?.Modal) window.bootstrap.Modal.getOrCreateInstance(modal).hide();
      await this.load();
    } catch (error) { window.showNotification?.(error?.message || 'Welfare case could not be created.', 'error'); }
  },
  async viewCase(id) {
    const loading = document.getElementById('modalLoading'); const content = document.getElementById('modalCaseContent');
    if (loading) loading.classList.remove('d-none'); if (content) content.innerHTML = '';
    const modal = document.getElementById('welfareCaseModal'); if (modal && window.bootstrap?.Modal) window.bootstrap.Modal.getOrCreateInstance(modal).show();
    try {
      const response = await this.api('GET', `/api/students/welfare-case/${Number(id)}`); const payload = this.payload(response); const row = payload.case || {};
      const code = document.getElementById('modalCaseId'); if (code) code.textContent = row.case_code || String(id);
      const subtitle = document.getElementById('modalSubtitle'); if (subtitle) subtitle.firstChild.textContent = `Case #${row.case_code || id} · `;
      if (content) content.innerHTML = `<h6>${this.esc(row.title || 'Welfare case')}</h6><p><strong>Learner:</strong> ${this.esc(payload.student?.name || row.student_name || payload.student?.full_name || '—')}</p><p><strong>Category:</strong> ${this.esc(row.welfare_category || '—')} · <strong>Priority:</strong> ${this.esc(row.priority || '—')}</p><p><strong>Description:</strong> ${this.esc(row.description || '—')}</p><h6>Recent notes</h6>${(payload.notes || []).map((note) => `<p class="border-start ps-2"><strong>${this.esc(note.note_type || 'Note')}</strong> · ${this.esc(note.created_at || '')}<br>${this.esc(note.note || '')}</p>`).join('') || '<p class="text-muted">No notes recorded.</p>'}`;
    } catch (error) { const alert = document.getElementById('modalError'); if (alert) { alert.textContent = error?.message || 'Case details could not be loaded.'; alert.classList.remove('d-none'); } }
    finally { loading?.classList.add('d-none'); }
  },
  init() {
    if (window.AuthContext?.ready) window.AuthContext.ready();
    ['classFilter','streamFilter','genderFilter','categoryFilter','referralSourceFilter','priorityFilter','statusFilter','assignedToFilter'].forEach((id) => document.getElementById(id)?.addEventListener('change', () => this.load()));
    let timer; document.getElementById('searchBox')?.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(() => this.load(), 250); });
    document.getElementById('resetFiltersBtn')?.addEventListener('click', () => { ['classFilter','streamFilter','genderFilter','categoryFilter','referralSourceFilter','priorityFilter','statusFilter','assignedToFilter','searchBox'].forEach((id) => { const node = document.getElementById(id); if (node) node.value = ''; }); this.load(); });
    document.getElementById('applyFiltersBtn')?.remove();
    document.getElementById('newCaseBtn')?.addEventListener('click', () => { const modal = document.getElementById('newCaseModal'); if (modal && window.bootstrap?.Modal) window.bootstrap.Modal.getOrCreateInstance(modal).show(); });
    document.getElementById('saveNewCaseBtn')?.addEventListener('click', () => this.createCase());
    this.load();
    window.APIRealtime?.register?.('student_welfare', { refresh: () => this.load() }, ['students','counseling']);
  },
};
window.studentWelfareController = studentWelfareController;
document.addEventListener('DOMContentLoaded', () => studentWelfareController.init());
