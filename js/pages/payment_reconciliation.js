const paymentReconciliationController = {
  cases: [],
  students: [],
  transport: [],
  uniformSales: [],
  state: { referenceCache: null },

  esc(v) {
    const d = document.createElement('div');
    d.textContent = String(v ?? '');
    return d.innerHTML;
  },

  notify(m, t = 'success') {
    if (window.showNotification) showNotification(m, t);
    else alert(m);
  },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.resolve("finance", "payment-reconciliation-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Payment reconciliation Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async loadLookups() {
    const cache = this.state.referenceCache;
    const [s, t, u] = await Promise.all([
      cache?.students !== undefined ? cache.students : window.API.students.getAll({ limit: 1000, status: 'active' }),
      cache?.fundSources !== undefined ? cache.fundSources : window.API.finance.getStudentFundSources(),
      cache?.uniformSales !== undefined ? cache.uniformSales : window.API.inventory.listUniformSales({ limit: 1000 }),
    ]);
    this.students = s?.data || s || [];
    const sources = t?.data?.sources || t?.sources || t || [];
    this.transport = sources.filter(x => x.account_type === 'transport');
    this.uniformSales = u?.data?.sales || u?.data || u || [];

    document.getElementById('routingStudent').innerHTML = '<option value="">Select student</option>' +
      this.students.map(x => `<option value="${x.id}">${this.esc(`${x.admission_no || ''} — ${x.first_name || ''} ${x.last_name || ''}`)}</option>`).join('');

    document.getElementById('routingEntitlement').innerHTML = '<option value="">Select entitlement</option>' +
      this.transport.map(x =>
        `<option value="${x.source_id}">${this.esc(x.student_name)} · ${this.esc(x.reference)}</option>`).join('');

    document.getElementById('routingUniformSale').innerHTML = '<option value="">Select sale</option>' +
      this.uniformSales.map(x =>
        `<option value="${x.id}">${this.esc(`${x.admission_no || x.admission_number || ''} — ${x.student_name || ''} — ${x.item_name || x.uniform_item || 'sale'} — KES ${x.total_amount || ((x.quantity || 0) * (x.unit_price || 0))}`)}</option>`).join('');
  },

  async load() {
    const status = document.getElementById('routingCaseStatus')?.value || 'unmatched';
    const r = await window.API.finance.getPaymentRoutingCases({ status });
    this.cases = r?.data?.cases || [];
    // The case table carries the payer's raw reference, the ledger the router
    // guessed, why it could not be allocated, and the resolution state — there
    // is no student name on the row, because an unmatched payment is exactly
    // one whose learner could not be identified.
    document.getElementById('routingCasesBody').innerHTML = this.cases.map(c => `
      <tr>
        <td>${this.esc(c.reference_value || c.normalized_reference || c.provider_transaction_id || '—')}</td>
        <td>${this.esc(c.provider_code || '—')}</td>
        <td>${this.esc(c.purpose_candidate || 'unknown')}</td>
        <td>${this.esc(c.reason || '—')}</td>
        <td class="text-end">${this.money(c.amount || 0)}</td>
        <td><span class="badge ${c.status === 'resolved' ? 'bg-success' : (c.status === 'rejected' ? 'bg-secondary' : 'bg-warning')}">${this.esc(c.status || 'unmatched')}</span></td>
        <td>${c.status === 'unmatched'
          ? `<button class="btn btn-sm btn-outline-primary" onclick="paymentReconciliationController.openResolve(${c.id})">Resolve</button>`
          : '—'}</td>
      </tr>
    `).join('') || '<tr><td colspan="7" class="text-center text-muted py-4">No routing cases.</td></tr>';
  },

  openResolve(id) {
    const c = this.cases.find(x => x.id === id);
    if (!c) return;
    document.getElementById('routingCaseId').value = id;
    document.getElementById('routingCaseSummary').textContent =
      `${this.money(c.amount || 0)} · provider reference ${c.reference_value || c.normalized_reference || '—'} · ${c.reason || 'no reason recorded'}`;
    // The router's guess is the default ledger; the operator confirms or changes it.
    document.getElementById('routingPurpose').value =
      (c.purpose_candidate && ['fees', 'transport', 'uniforms'].includes(c.purpose_candidate)) ? c.purpose_candidate : 'fees';
    this.toggle();
    new bootstrap.Modal(document.getElementById('routingResolveModal')).show();
  },

  async resolve() {
    const id = document.getElementById('routingCaseId').value;
    const purpose = document.getElementById('routingPurpose').value;
    if (!purpose) return this.notify('Select a ledger to allocate this payment to.', 'warning');

    // resolveCase() rejects an allocation without the target record: a fee
    // needs the learner and the receiving account, transport needs the
    // entitlement, uniforms need the sale. Sending only the ledger purpose is
    // what made every resolution fail with "required field missing".
    const payload = { purpose };
    const studentId = Number(document.getElementById('routingStudent')?.value || 0);
    if (studentId > 0) payload.student_id = studentId;

    if (purpose === 'transport') {
      const entitlementId = Number(document.getElementById('routingEntitlement')?.value || 0);
      if (!entitlementId) return this.notify('Select the transport entitlement this payment settles.', 'warning');
      payload.entitlement_id = entitlementId;
    }

    if (purpose === 'uniforms') {
      const saleId = Number(document.getElementById('routingUniformSale')?.value || 0);
      if (!saleId) return this.notify('Select the uniform sale this payment settles.', 'warning');
      payload.uniform_sale_id = saleId;
    }

    if (purpose === 'fees' && studentId <= 0) {
      return this.notify('Select the learner this payment belongs to.', 'warning');
    }

    // The receiving account is read here rather than injected by a monkey
    // patch. resolveCase() refuses an allocation without it, and a select that
    // failed to load would otherwise surface as an unexplained backend error.
    const accountSelect = document.getElementById('routingFinancialAccount');
    if (!accountSelect) {
      return this.notify('The financial account selector did not load. Refresh the page and try again.', 'danger');
    }
    const financialAccountId = Number(accountSelect.value || 0);
    if (financialAccountId <= 0) {
      return this.notify('Select the financial account that will receive this payment.', 'warning');
    }
    payload.financial_account_id = financialAccountId;

    try {
      await window.API.finance.resolvePaymentRoutingCase(id, payload);
      this.notify('Payment allocated and receipt queued', 'success');
      new bootstrap.Modal.getInstance(document.getElementById('routingResolveModal'))?.hide();
      await this.load();
    } catch (e) {
      this.notify(e.message || 'Unable to resolve', 'danger');
    }
  },

  toggle() {
    const purpose = document.getElementById('routingPurpose').value;
    // The page ships these controls d-none; the purpose values are
    // fees / transport / uniforms, so the show/hide must match them exactly.
    document.getElementById('routingEntitlementLabel')?.classList.toggle('d-none', purpose !== 'transport');
    document.getElementById('routingEntitlement')?.classList.toggle('d-none', purpose !== 'transport');
    document.getElementById('routingUniformSaleLabel')?.classList.toggle('d-none', purpose !== 'uniforms');
    document.getElementById('routingUniformSale')?.classList.toggle('d-none', purpose !== 'uniforms');
    document.getElementById('routingStudentWrap')?.classList.toggle('d-none', purpose === 'uniforms');
  },

  money(n) { return 'KES ' + Number(n || 0).toLocaleString('en-KE', { minimumFractionDigits: 2 }); },

  async init() {
    await window.AuthContext?.ready();
    document.getElementById('routingCasesRefresh')?.addEventListener('click', () => this.load());
    document.getElementById('routingCaseStatus')?.addEventListener('change', () => this.load());
    document.getElementById('routingResolveForm')?.addEventListener('submit', e => { e.preventDefault(); this.resolve(); });
    document.getElementById('routingPurpose')?.addEventListener('change', () => this.toggle());
    await this.loadReferencesComposed();
    await this.loadLookups();
    await this.load();
    window.APIRealtime?.register?.('payment_reconciliation', {
      refresh: async () => {
        this.state.referenceCache = null;
        await this.loadReferencesComposed();
        await this.loadLookups();
        await this.load();
      }
    }, ['finance', 'payments']);
  }
};

const money = n => 'KES ' + Number(n || 0).toLocaleString('en-KE', { minimumFractionDigits: 2 });

document.addEventListener('DOMContentLoaded', () => {
  const f = document.getElementById('collectionRouteForm');
  if (!f) return;
  const list = async () => {
    try {
      const r = await window.API.finance.getPaymentCollectionRoutes();
      const rows = r?.data?.routes || [];
      document.getElementById('collectionRoutesList').textContent = rows.map(x =>
        `${window.PaymentMethodLabels?.display(x.provider_code) ?? x.provider_code}: ${x.account_identifier} → ${x.purpose} (${x.reference_prefix})`
      ).join(' | ') || 'No collection routes configured.';
    } catch (e) {
      document.getElementById('collectionRoutesList').textContent = 'Unable to load collection routes.';
    }
  };
  f.addEventListener('submit', async e => {
    e.preventDefault();
    // The settlement account is read from the select this page's companion
    // script injects, and sent here — not merged into the API call by a runtime
    // patch. A selector that failed to load is reported by name rather than as
    // an unexplained backend rejection.
    const accountSelect = document.getElementById('routeFinancialAccount');
    if (!accountSelect) {
      window.showNotification?.('The financial account selector did not load. Refresh the page and try again.', 'danger');
      return;
    }
    const financialAccountId = Number(accountSelect.value || 0);
    if (financialAccountId <= 0) {
      window.showNotification?.('Choose the settlement account for this collection route.', 'warning');
      return;
    }
    try {
      await window.API.finance.savePaymentCollectionRoute({
        provider_code: document.getElementById('routeProvider').value.trim(),
        account_identifier: document.getElementById('routeAccount').value.trim(),
        purpose: document.getElementById('routePurpose').value,
        reference_prefix: document.getElementById('routePrefix').value.trim(),
        financial_account_id: financialAccountId
      });
      window.showNotification?.('Collection route saved', 'success');
      f.reset();
      document.getElementById('routePrefix').value = 'FEE';
      accountSelect.value = '';
      await list();
    } catch (err) {
      window.showNotification?.(err.message || 'Unable to save route', 'danger');
    }
  });
  list();
});

window.paymentReconciliationController = paymentReconciliationController;