/* Accountant-only admission payment desk. Amounts come from the admission queue. */
const manageAdmissionsPaymentsController = {
  state: { rows: [], filtered: [], selected: null, paid: [], referenceCache: null },

  esc(value) { return String(value == null ? '' : value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;'); },
  money(value) { return Number(value || 0).toLocaleString('en-KE', { maximumFractionDigits: 2 }); },
  unwrap(value) { return value?.data ?? value ?? {}; },
  notify(message, type = 'success') { if (window.showNotification) return window.showNotification(message, type); alert(message); },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.resolve("finance", "manage-admissions-payments-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Manage admissions payments Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async load() {
    const body = document.getElementById('mapBody');
    body.innerHTML = '<tr><td colspan="6" class="text-center py-5 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Loading applicants…</td></tr>';
    try {
      const response = await window.API.admission.getQueues();
      const payload = this.unwrap(response);
      const queues = payload.queues || payload.data?.queues || {};
      // This desk deliberately excludes class-placement applicants. Only the
      // fees_payment stage is actionable by the accountant here.
      this.state.rows = (Array.isArray(queues.payment_pending) ? queues.payment_pending : [])
        .filter(row => String(row.current_stage || '') === 'fees_payment');
      this.applySearch();
    } catch (error) {
      body.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-danger">${this.esc(error.message || 'Unable to load the admission payment queue.')}</td></tr>`;
    }
  },

  async loadYears() {
    const select = document.getElementById('mapYear');
    try {
      const cache = this.state.referenceCache;
      const response = cache?.years !== undefined ? cache.years : await window.API.academic.listYears();
      const payload = this.unwrap(response);
      const years = Array.isArray(payload.years) ? payload.years : (Array.isArray(payload) ? payload : []);
      const current = years.find(year => year.is_current == 1 || year.is_current === true || year.status === 'current');
      select.innerHTML = years.map(year => {
        const value = year.year_code ?? year.year ?? year.id;
        const label = year.year_name || year.name || year.year_code || year.year || value;
        return `<option value="${this.esc(value)}">${this.esc(label)}</option>`;
      }).join('');
      if (current) select.value = current.year_code ?? current.year ?? current.id;
      if (!select.value) select.innerHTML = `<option value="${new Date().getFullYear()}">${new Date().getFullYear()}</option>`;
      await this.loadPaidPayments();
    } catch (error) {
      select.innerHTML = `<option value="${new Date().getFullYear()}">${new Date().getFullYear()}</option>`;
      await this.loadPaidPayments();
    }
  },

  async loadPaidPayments() {
    const body = document.getElementById('mapPaidBody');
    const year = document.getElementById('mapYear')?.value || new Date().getFullYear();
    body.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Loading paid admission fees…</td></tr>';
    try {
      const response = await window.API.admission.getPaidPayments({ academic_year: year });
      const payload = this.unwrap(response);
      this.state.paid = Array.isArray(payload.payments) ? payload.payments : (Array.isArray(payload) ? payload : []);
      this.renderPaidPayments();
    } catch (error) {
      body.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-danger">${this.esc(error.message || 'Unable to load paid admission fees.')}</td></tr>`;
    }
  },

  renderPaidPayments() {
    const body = document.getElementById('mapPaidBody');
    if (!this.state.paid.length) { body.innerHTML = '<tr><td colspan="7" class="text-center py-5 text-muted">No admission fee payments found for this academic year.</td></tr>'; return; }
    body.innerHTML = this.state.paid.map((payment, index) => `<tr>
      <td class="fw-semibold">${this.esc(payment.admission_no || '—')}</td>
      <td>${this.esc(payment.applicant_name || '—')}</td>
      <td>${this.esc(payment.application_window || '—')}</td>
      <td>${this.esc(payment.class_assigned || '—')}</td>
      <td class="fw-semibold">KES ${this.money(payment.amount_paid)}</td>
      <td>${this.esc(payment.paid_at || '—')}</td>
      <td><button class="btn btn-sm btn-outline-secondary" data-paid-audit="${index}"><i class="bi bi-journal-text"></i></button></td>
    </tr>`).join('');
    body.querySelectorAll('[data-paid-audit]').forEach(button => button.addEventListener('click', () => this.openAudit({ id: this.state.paid[Number(button.dataset.paidAudit)].application_id, applicant_name: this.state.paid[Number(button.dataset.paidAudit)].applicant_name, application_no: this.state.paid[Number(button.dataset.paidAudit)].application_no, registration_fee_due: this.state.paid[Number(button.dataset.paidAudit)].amount_paid })));
  },

  applySearch() {
    const query = String(document.getElementById('mapSearch')?.value || '').trim().toLowerCase();
    this.state.filtered = this.state.rows.filter(row => !query || [row.application_no, row.applicant_name, row.parent_first_name, row.parent_last_name, row.grade_applying_for].join(' ').toLowerCase().includes(query));
    this.render();
  },

  render() {
    const rows = this.state.filtered;
    const pending = rows.filter(row => row.pending_payment_id).length;
    const confirmed = rows.filter(row => !row.pending_payment_id && Number(row.recorded_payment_amount || 0) >= Number(row.registration_fee_due ?? row.total_fees ?? 0) && Number(row.registration_fee_due ?? row.total_fees ?? 0) > 0).length;
    document.getElementById('mapQueueCount').textContent = this.state.rows.length;
    document.getElementById('mapPendingCount').textContent = pending;
    document.getElementById('mapConfirmedCount').textContent = confirmed;
    const body = document.getElementById('mapBody');
    if (!rows.length) { body.innerHTML = '<tr><td colspan="6" class="text-center py-5 text-muted">No applicants are currently at the Fees/Admission Payment stage.</td></tr>'; return; }
    body.innerHTML = rows.map((row, index) => {
      const amount = Number(row.registration_fee_due ?? row.total_fees ?? 0);
      const recorded = Number(row.recorded_payment_amount || 0);
      const parent = `${row.parent_first_name || ''} ${row.parent_last_name || ''}`.trim() || 'Parent not linked';
      const pending = !!row.pending_payment_id;
      const confirmed = !pending && recorded >= amount && amount > 0;
      return `<tr>
        <td><div class="fw-semibold">${this.esc(row.application_no || '—')}</div><div class="small text-muted">${this.esc(row.admission_number || 'Applicant')}</div></td>
        <td><div class="fw-semibold">${this.esc(row.applicant_name || '—')}</div><div class="small text-muted">Parent: ${this.esc(parent)}${row.phone_1 ? ` · ${this.esc(row.phone_1)}` : ''}</div></td>
        <td>${this.esc(row.grade_applying_for || '—')}</td>
        <td class="fw-semibold">KES ${this.money(amount)}</td>
        <td>${pending ? '<span class="badge bg-warning text-dark">Prompt/payment pending</span>' : (confirmed ? '<span class="badge bg-success">Confirmed</span>' : '<span class="badge bg-secondary">Not paid</span>')}</td>
        <td class="text-end text-nowrap"><button class="btn btn-sm btn-success me-1" data-prompt="${index}" ${pending || confirmed || amount <= 0 || !row.phone_1 ? 'disabled' : ''}><i class="bi bi-phone"></i> Prompt</button><button class="btn btn-sm btn-outline-secondary" data-audit="${index}"><i class="bi bi-journal-text"></i> Audit</button></td>
      </tr>`;
    }).join('');
    body.querySelectorAll('[data-prompt]').forEach(button => button.addEventListener('click', () => this.openPrompt(rows[Number(button.dataset.prompt)])));
    body.querySelectorAll('[data-audit]').forEach(button => button.addEventListener('click', () => this.openAudit(rows[Number(button.dataset.audit)])));
  },

  openPrompt(row) {
    this.state.selected = row;
    document.getElementById('mapApplicationId').value = row.id;
    document.getElementById('mapAmount').value = this.money(row.registration_fee_due ?? row.total_fees);
    document.getElementById('mapPhone').value = row.phone_1 || '';
    document.getElementById('mapPromptSummary').innerHTML = `<strong>${this.esc(row.applicant_name || 'Applicant')}</strong><br>Application ${this.esc(row.application_no || '—')} · ${this.esc(row.grade_applying_for || '—')}`;
    document.getElementById('mapPromptError').classList.add('d-none');
    bootstrap.Modal.getOrCreateInstance(document.getElementById('mapPromptModal')).show();
  },

  async sendPrompt(event) {
    event.preventDefault();
    const row = this.state.selected;
    if (!row) return;
    const button = document.getElementById('mapSendPrompt');
    const phone = document.getElementById('mapPhone').value.trim();
    const errorBox = document.getElementById('mapPromptError');
    if (!phone) { errorBox.textContent = 'A parent M-Pesa phone number is required.'; errorBox.classList.remove('d-none'); return; }
    button.disabled = true;
    try {
      await window.API.admission.promptPayment({ application_id: Number(row.id), phone });
      bootstrap.Modal.getInstance(document.getElementById('mapPromptModal'))?.hide();
      this.notify('M-Pesa prompt sent. The payment will be confirmed automatically after the provider callback.');
      await this.load();
    } catch (error) {
      errorBox.textContent = error.message || 'The payment prompt could not be sent.';
      errorBox.classList.remove('d-none');
    } finally { button.disabled = false; }
  },

  async openAudit(row) {
    const body = document.getElementById('mapAuditBody');
    body.innerHTML = '<div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm"></span> Loading audit records…</div>';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('mapAuditModal')).show();
    try {
      const response = await window.API.admission.getPayments(row.id);
      const payload = this.unwrap(response);
      const payments = Array.isArray(payload.payments) ? payload.payments : (Array.isArray(payload) ? payload : []);
      body.innerHTML = `<div class="small mb-3"><strong>${this.esc(row.applicant_name || 'Applicant')}</strong> · ${this.esc(row.application_no || '—')} · System amount: <strong>KES ${this.money(row.registration_fee_due ?? row.total_fees)}</strong></div>` + (payments.length ? `<div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Date</th><th>Amount</th><th>Method</th><th>Reference</th><th>Status</th><th>Recorded/verified</th></tr></thead><tbody>${payments.map(payment => `<tr><td>${this.esc(payment.payment_date || payment.created_at || '—')}</td><td>KES ${this.money(payment.amount)}</td><td>${this.esc(payment.payment_method || '—')}</td><td><code>${this.esc(payment.reference_no || '—')}</code></td><td>${this.esc(payment.status || '—')}</td><td class="small">${this.esc(payment.recorded_by_name || payment.recorded_by || 'Provider')} / ${this.esc(payment.verified_by_name || payment.verified_by || 'Automatic callback')}</td></tr>`).join('')}</tbody></table></div>` : '<div class="text-muted py-3">No admission payment record exists yet.</div>');
    } catch (error) { body.innerHTML = `<div class="text-danger">${this.esc(error.message || 'Unable to load the audit trail.')}</div>`; }
  },

  init() {
    document.getElementById('mapRefresh')?.addEventListener('click', () => this.load());
    document.getElementById('mapSearch')?.addEventListener('input', () => this.applySearch());
    document.getElementById('mapYear')?.addEventListener('change', () => this.loadPaidPayments());
    document.getElementById('mapPromptForm')?.addEventListener('submit', event => this.sendPrompt(event));
    window.APIRealtime?.register?.("manage_admissions_payments", { refresh: async () => { this.state.referenceCache = null; await this.loadReferencesComposed(); await Promise.all([this.load(), this.loadYears()]); } }, ["academic", "admission", "finance"]);
    return Promise.all([this.loadReferencesComposed(), this.load(), this.loadYears()]);
  }
};

window.manageAdmissionsPaymentsController = manageAdmissionsPaymentsController;
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => manageAdmissionsPaymentsController.init().catch(console.error));
else manageAdmissionsPaymentsController.init().catch(console.error);
