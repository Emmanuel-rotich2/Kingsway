const kcbDisbursementReconciliationController = {
  initialized: false,
  state: { rows: [], busy: new Set() },

  async init() {
    if (this.initialized) return;
    this.initialized = true;
    await window.AuthContext?.ready();
    if (!window.AuthContext?.isAuthenticated()) return;
    this.setupEventListeners();
    await this.loadData();
  },

  setupEventListeners() {
    document.getElementById('kcbRefresh')?.addEventListener('click', () => this.loadData());
    document.getElementById('kcbPollDue')?.addEventListener('click', () => this.pollDue());
    document.getElementById('kcbStatusFilter')?.addEventListener('change', () => this.loadData());
    document.getElementById('kcbExceptionsOnly')?.addEventListener('change', () => this.loadData());
    document.getElementById('kcbQueueBody')?.addEventListener('click', (event) => {
      const button = event.target.closest('button[data-action]');
      if (!button) return;
      const id = Number(button.dataset.id || 0);
      if (button.dataset.action === 'inquire') this.checkStatus(id);
      if (button.dataset.action === 'retry') this.retry(id);
      if (button.dataset.action === 'confirm-failed') this.confirmFailed(id);
    });
  },

  async loadData() {
    const body = document.getElementById('kcbQueueBody');
    try {
      const params = {
        status: document.getElementById('kcbStatusFilter')?.value || '',
        exceptions_only: document.getElementById('kcbExceptionsOnly')?.checked ? 1 : 0,
        limit: 200,
      };
      const response = await window.API.finance.getKcbDisbursements(params);
      this.state.rows = (response?.data || response)?.disbursements || [];
      this.render();
    } catch (error) {
      body.innerHTML = `<tr><td colspan="8" class="text-center text-danger py-5">${this.escape(error.message || 'Unable to load KCB reconciliation queue.')}</td></tr>`;
    }
  },

  render() {
    const rows = this.state.rows;
    const body = document.getElementById('kcbQueueBody');
    const exceptions = rows.filter((row) => row.exception_status === 'open').length;
    const pending = rows.filter((row) => row.status === 'pending').length;
    document.getElementById('kcbQueueSummary').textContent = `${rows.length} transfer(s) · ${pending} pending · ${exceptions} exception(s)`;
    if (!rows.length) {
      body.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-5">No KCB disbursements match these filters.</td></tr>';
      return;
    }
    body.innerHTML = rows.map((row) => {
      const id = Number(row.disbursement_id);
      const busy = this.state.busy.has(id);
      const canCheck = !['completed'].includes(row.status) && row.reconciliation_status !== 'confirmed_failure';
      const canRetry = Number(row.retry_allowed) === 1;
      const exception = row.exception_status === 'open' || ['exception', 'manual_review'].includes(row.reconciliation_status);
      const actions = [
        canCheck ? `<button class="btn btn-sm btn-outline-primary" data-action="inquire" data-id="${id}" ${busy ? 'disabled' : ''}>Check KCB status</button>` : '',
        canRetry ? `<button class="btn btn-sm btn-outline-danger" data-action="retry" data-id="${id}" ${busy ? 'disabled' : ''}>Safe retry</button>` : '',
        exception ? `<button class="btn btn-sm btn-outline-secondary" data-action="confirm-failed" data-id="${id}" ${busy ? 'disabled' : ''}>Confirm failed from statement</button>` : '',
      ].filter(Boolean).join(' ');
      return `<tr>
        <td><strong>#${id}</strong><div class="small text-muted">${this.escape(row.payment_purpose || row.disbursement_type || 'Payment')}${row.retry_of_disbursement_id ? ` · retry of #${Number(row.retry_of_disbursement_id)}` : ''}</div></td>
        <td>${this.escape(row.recipient_name || '—')}<div class="small text-muted">${this.maskAccount(row.account_number)}</div></td>
        <td class="text-end fw-semibold">${this.money(row.amount, row.currency)}</td>
        <td><code>${this.escape(row.transaction_ref || row.request_id || 'Not assigned')}</code></td>
        <td>${this.badge(row.status)}</td>
        <td>${this.reconciliationBadge(row)}${exception ? `<div class="small text-danger mt-1">${this.escape(row.exception_reason || '')}</div>` : ''}</td>
        <td class="small">${this.date(row.last_status_inquiry_at)}<div class="text-muted">${Number(row.status_inquiry_count || 0)} inquiry attempt(s)</div></td>
        <td class="text-end text-nowrap">${actions || '<span class="text-muted small">No action required</span>'}</td>
      </tr>`;
    }).join('');
  },

  async checkStatus(id) {
    await this.run(id, async () => {
      const response = await window.API.finance.checkKcbDisbursementStatus(id);
      this.notify(response?.message || 'KCB status checked.', 'success');
    });
  },

  async pollDue() {
    const button = document.getElementById('kcbPollDue');
    button.disabled = true;
    try {
      const response = await window.API.finance.pollKcbDisbursements(25);
      const result = response?.data || response;
      this.notify(`Checked ${Number(result.selected || 0)} due transfer(s); ${Number(result.completed || 0)} completed, ${Number(result.exceptions || 0)} exceptions.`, 'success');
      await this.loadData();
    } catch (error) {
      this.notify(error.message || 'KCB status polling failed.', 'danger');
    } finally { button.disabled = false; }
  },

  async retry(id) {
    if (!(await window.confirmAction('Create retry', 'KCB has confirmed this transfer failed. Create one linked retry with a new idempotency key?', { confirmText: 'Create retry' }))) return;
    await this.run(id, async () => {
      await window.API.finance.retryKcbDisbursement(id);
      this.notify('The linked KCB retry was submitted and is awaiting confirmation.', 'success');
    });
  },

  async confirmFailed(id) {
    const evidence = window.promptAction?.('Enter the KCB statement reference and evidence confirming that no payment was completed:');
    if (!evidence) return;
    await this.run(id, async () => {
      await window.API.finance.resolveKcbDisbursement(id, { outcome: 'confirmed_failure', evidence });
      this.notify('The failure was reconciled and recorded in the audit trail.', 'success');
    });
  },

  async run(id, action) {
    if (this.state.busy.has(id)) return;
    this.state.busy.add(id); this.render();
    try { await action(); }
    catch (error) { this.notify(error.message || 'The KCB action failed.', 'danger'); }
    finally { this.state.busy.delete(id); await this.loadData(); }
  },

  badge(status) {
    const colors = { pending: 'warning', completed: 'success', failed: 'danger', timeout: 'secondary', cancelled: 'secondary' };
    return `<span class="badge text-bg-${colors[status] || 'secondary'}">${this.escape(status || 'unknown')}</span>`;
  },
  reconciliationBadge(row) {
    const value = row.reconciliation_status || 'awaiting_callback';
    const colors = { confirmed_success: 'success', confirmed_failure: 'danger', exception: 'warning', manual_review: 'secondary', awaiting_callback: 'info' };
    return `<span class="badge text-bg-${colors[value] || 'secondary'}">${this.escape(value.replaceAll('_', ' '))}</span>`;
  },
  money(value, currency) { return `${this.escape(currency || 'KES')} ${Number(value || 0).toLocaleString('en-KE', { minimumFractionDigits: 2 })}`; },
  date(value) { return value ? new Date(String(value).replace(' ', 'T')).toLocaleString('en-KE') : 'Never'; },
  maskAccount(value) { const text = String(value || ''); return text ? `Account ••••${this.escape(text.slice(-4))}` : 'No account'; },
  escape(value) { const node = document.createElement('div'); node.textContent = String(value ?? ''); return node.innerHTML; },
  notify(message, type) { if (window.showNotification) window.showNotification(message, type); else if (window.infoDialog) window.infoDialog('Notice', message); },
};

window.kcbDisbursementReconciliationController = kcbDisbursementReconciliationController;
document.addEventListener('DOMContentLoaded', () => kcbDisbursementReconciliationController.init().catch(() => {}));
