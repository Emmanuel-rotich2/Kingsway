/** Finance workflow for governed transfers between available student credits. */
const studentFundTransfersController = {
  state: { transfers: [], sources: [], students: [], entitlements: [] },
  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),
  esc(value) { return String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' })[c]); },
  payload(response) { return response?.data?.data ?? response?.data ?? response ?? {}; },
  async init() {
    document.getElementById('fundTransferRefresh')?.addEventListener('click', () => this.load());
    document.getElementById('fundTransferForm')?.addEventListener('submit', (event) => { event.preventDefault(); this.submitTransfer(); });
    document.getElementById('fundSource')?.addEventListener('change', () => this.updateDestinationStudents());
    document.getElementById('fundDestinationStudent')?.addEventListener('change', () => this.renderDestinationTransport());
    document.getElementById('fundDestinationType')?.addEventListener('change', () => this.renderDestinationTransport());
    document.getElementById('fundDestinationTransport')?.addEventListener('change', () => {});
    document.getElementById('fundTransferBody')?.addEventListener('click', (event) => {
      const button = event.target.closest('[data-transfer-action]'); if (!button) return;
      this.decide(Number(button.dataset.transferId), button.dataset.transferAction);
    });
    document.getElementById('fundTransferExport')?.addEventListener('click', () => this.exportCSV());
    document.getElementById('fundTransferPrint')?.addEventListener('click', () => this.print());
    window.APIRealtime?.register?.('student_fund_transfers', { refresh: () => this.load() }, ['finance']);
    return this.load();
  },
  async load() {
    const body = document.getElementById('fundTransferBody');
    if (body) body.innerHTML = '<tr><td colspan="6" class="text-center py-4">Loading transfer requests…</td></tr>';
    const error = document.getElementById('fundTransferError'); error?.classList.add('d-none');
    try {
      const [transferResponse, sourceResponse, destinationResponse] = await Promise.all([
        this.API('GET', '/api/finance/student-fund-transfers'),
        this.API('GET', '/api/finance/student-fund-sources'),
        this.API('GET', '/api/finance/student-fund-destinations')
      ]);
      const transfers = this.payload(transferResponse);
      const sources = this.payload(sourceResponse);
      const destinations = this.payload(destinationResponse);
      this.state.transfers = Array.isArray(transfers.transfers) ? transfers.transfers : (Array.isArray(transfers) ? transfers : []);
      this.state.sources = Array.isArray(sources.sources) ? sources.sources : (Array.isArray(sources) ? sources : []);
      this.state.students = Array.isArray(destinations.students) ? destinations.students : [];
      this.state.entitlements = Array.isArray(destinations.entitlements) ? destinations.entitlements : [];
      this.render(); this.renderSourceOptions();
    } catch (exception) {
      if (body) body.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-4">Transfer data could not be loaded.</td></tr>';
      if (error) { error.textContent = exception?.message || 'Transfer data could not be loaded.'; error.classList.remove('d-none'); }
    }
  },
  fmtKES(value) { return `KES ${Number(value || 0).toLocaleString('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`; },
  render() {
    const body = document.getElementById('fundTransferBody'); if (!body) return;
    body.innerHTML = this.state.transfers.length ? this.state.transfers.map((row) => {
      const pending = row.status === 'pending_approval'; const approved = row.status === 'approved';
      const actions = pending && this.canApprove() ? `<button class="btn btn-sm btn-success me-1" data-transfer-id="${Number(row.id)}" data-transfer-action="approve">Approve</button><button class="btn btn-sm btn-outline-danger" data-transfer-id="${Number(row.id)}" data-transfer-action="reject">Reject</button>`
        : approved && this.canApprove() ? `<button class="btn btn-sm btn-primary" data-transfer-id="${Number(row.id)}" data-transfer-action="post">Post</button>` : '—';
      return `<tr><td>${this.esc(row.source_student_name || '—')} <small class="text-muted">(${this.esc(row.source_account_type || '')})</small></td>
        <td>${this.esc(row.destination_student_name || '—')} <small class="text-muted">(${this.esc(row.destination_account_type || '')})</small></td>
        <td>${this.fmtKES(row.amount)}</td><td>${this.esc(row.parent_request_reference || '—')}</td>
        <td><span class="badge bg-${pending ? 'warning text-dark' : approved ? 'primary' : row.status === 'posted' ? 'success' : 'secondary'}">${this.esc(String(row.status || '—').replace(/_/g, ' '))}</span></td><td>${actions}</td></tr>`;
    }).join('') : '<tr><td colspan="6" class="text-center py-4 text-muted">No transfer requests yet.</td></tr>';
  },
  canApprove() {
    const auth = window.AuthContext;
    if (typeof auth?.hasPermission !== 'function') return false;
    return auth.hasPermission('finance.approve') || auth.hasPermission('finance_approve') || auth.hasPermission('finance.manage') || auth.hasPermission('finance_manage');
  },
  renderSourceOptions() {
    const select = document.getElementById('fundSource'); if (!select) return;
    select.innerHTML = '<option value="">Select available credit</option>' + this.state.sources.map((source, index) => `<option value="${index}">${this.esc(source.student_name)} · ${this.esc(source.account_type)} · ${this.esc(source.reference)} · ${this.fmtKES(source.available_amount)}</option>`).join('');
    this.updateDestinationStudents();
  },
  source() { const value = document.getElementById('fundSource')?.value; if (value === '' || value == null) return null; const index = Number(value); return Number.isInteger(index) ? this.state.sources[index] || null : null; },
  updateDestinationStudents() {
    const select = document.getElementById('fundDestinationStudent'); if (!select) return;
    const students = this.state.students;
    select.innerHTML = '<option value="">Select student</option>' + students.map((student) => `<option value="${Number(student.id)}">${this.esc(student.full_name || student.name || [student.first_name,student.last_name].filter(Boolean).join(' '))} (${this.esc(student.admission_no || student.admission_number || '')})</option>`).join('');
    this.renderDestinationTransport();
  },
  renderDestinationTransport() {
    const type = document.getElementById('fundDestinationType')?.value || 'fees';
    const studentId = Number(document.getElementById('fundDestinationStudent')?.value || 0);
    const wrap = document.getElementById('fundDestinationTransportWrap'); wrap?.classList.toggle('d-none', type !== 'transport');
    const select = document.getElementById('fundDestinationTransport'); if (!select) return;
    const options = this.state.entitlements.filter((entitlement) => Number(entitlement.student_id) === studentId);
    select.innerHTML = '<option value="">Select active transport coverage</option>' + options.map((entitlement) => `<option value="${Number(entitlement.entitlement_id)}">${this.esc(entitlement.reference)}</option>`).join('');
    if (type === 'transport' && !options.length && studentId) {
      this.showFormError('No active transport entitlement with an available balance was found for this learner.');
    }
  },
  showFormError(message) {
    const box = document.getElementById('fundTransferError'); if (box) { box.textContent = message; box.classList.remove('d-none'); }
    else window.showNotification?.(message, 'error');
  },
  async submitTransfer() {
    const source = this.source();
    const destinationId = Number(document.getElementById('fundDestinationStudent')?.value || 0);
    const destinationType = document.getElementById('fundDestinationType')?.value || 'fees';
    const transport = document.getElementById('fundDestinationTransport')?.value || '';
    const amount = Number(document.getElementById('fundAmount')?.value || 0);
    const reason = document.getElementById('fundReason')?.value.trim() || '';
    const reference = document.getElementById('fundParentReference')?.value.trim() || '';
    document.getElementById('fundTransferError')?.classList.add('d-none');
    if (!source || !destinationId || !amount || !reason) return this.showFormError('Choose available credit, a destination learner, amount, and reason.');
    if (amount > Number(source.available_amount)) return this.showFormError('The amount exceeds the available source credit.');
    if (destinationType === 'transport' && !transport) return this.showFormError('Choose an active destination transport entitlement.');
    const payload = {
      source_student_id: Number(source.student_id), source_account_type: source.account_type,
      source_credit_note_id: source.account_type === 'fees' ? Number(source.source_id) : null,
      source_entitlement_id: source.account_type === 'transport' ? Number(source.source_id) : null,
      destination_student_id: destinationId, destination_account_type: destinationType,
      destination_entitlement_id: destinationType === 'transport' ? Number(transport) : null,
      amount, parent_id: source.parent_id ? Number(source.parent_id) : null,
      parent_request_reference: reference, reason
    };
    const submit = document.querySelector('#fundTransferForm button[type="submit"]'); if (submit) submit.disabled = true;
    try {
      await this.API('POST', '/api/finance/student-fund-transfers', payload);
      window.bootstrap?.Modal.getInstance(document.getElementById('fundTransferModal'))?.hide();
      document.getElementById('fundTransferForm')?.reset();
      window.showNotification?.('Transfer request submitted for approval.', 'success');
      await this.load();
    } catch (exception) { this.showFormError(exception?.message || 'Transfer request could not be submitted.'); }
    finally { if (submit) submit.disabled = false; }
  },
  async decide(id, action) {
    try {
      if (action === 'approve' || action === 'reject') await this.API('PUT', `/api/finance/student-fund-transfers/${id}`, { decision: action });
      else if (action === 'post') await this.API('POST', `/api/finance/student-fund-transfer-post/${id}`, {});
      await this.load();
    } catch (exception) { window.showNotification?.(exception?.message || 'Transfer action failed.', 'error'); }
  },
  exportCSV() {
    if (window.AuthContext?.canExport && !window.AuthContext.canExport('finance')) return window.showNotification?.('You do not have permission to export finance data.', 'error');
    const quote = (value) => `"${String(value ?? '').replace(/"/g, '""')}"`;
    const rows = [['Source learner','Source account','Destination learner','Destination account','Amount','Parent request','Status','Reason'], ...this.state.transfers.map((row) => [row.source_student_name,row.source_account_type,row.destination_student_name,row.destination_account_type,row.amount,row.parent_request_reference,row.status,row.reason])];
    window.KingswayFileLifecycle?.exportText?.(rows.map((row) => row.map(quote).join(',')).join('\r\n'), 'student-fund-transfers.csv', 'text/csv;charset=utf-8;');
  },
  print() { if (window.AuthContext?.canPrint && !window.AuthContext.canPrint('finance')) return window.showNotification?.('You do not have permission to print finance data.', 'error'); window.print(); }
};
window.studentFundTransfersController = studentFundTransfersController;
document.addEventListener('DOMContentLoaded', () => studentFundTransfersController.init());
