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

    document.getElementById('routingEntitlement').innerHTML = this.transport.map(x =>
      `<option value="${x.source_id}">${this.esc(x.student_name)} · ${this.esc(x.reference)}</option>`).join('');

    document.getElementById('routingUniformSale').innerHTML = this.uniformSales.map(x =>
      `<option value="${x.id}">${this.esc(`${x.admission_no || x.admission_number || ''} — ${x.student_name || ''} — ${x.item_name || x.uniform_item || 'sale'} — KES ${x.total_amount || ((x.quantity || 0) * (x.unit_price || 0))}`)}</option>`).join('');
  },

  async load() {
    const r = await window.API.finance.getPaymentRoutingCases();
    this.cases = r?.data?.cases || [];
    document.getElementById('routingCasesBody').innerHTML = this.cases.map(c => `
      <tr>
        <td>${this.esc(c.admission_no || '—')}</td>
        <td>${this.esc(`${c.first_name || ''} ${c.last_name || ''}`)}</td>
        <td>${this.esc(c.purpose || '—')}</td>
        <td class="text-end">${this.money(c.amount || 0)}</td>
        <td><span class="badge ${c.resolved ? 'bg-success' : 'bg-warning'}">${c.resolved ? 'Resolved' : 'Pending'}</span></td>
        <td><button class="btn btn-sm btn-outline-primary" onclick="paymentReconciliationController.openResolve(${c.id})">Resolve</button></td>
      </tr>
    `).join('') || '<tr><td colspan="6" class="text-center text-muted py-4">No routing cases.</td></tr>';
  },

  openResolve(id) {
    const c = this.cases.find(x => x.id === id);
    if (!c) return;
    document.getElementById('routingResolveId').value = id;
    document.getElementById('routingResolveStudent').textContent = this.esc(`${c.first_name || ''} ${c.last_name || ''}`);
    document.getElementById('routingResolveAmount').textContent = this.money(c.amount || 0);
    document.getElementById('routingResolvePurpose').textContent = this.esc(c.purpose || '—');
    new bootstrap.Modal(document.getElementById('routingResolveModal')).show();
  },

  async resolve() {
    const id = document.getElementById('routingResolveId').value;
    const purpose = document.getElementById('routingPurpose').value;
    if (!purpose) return this.notify('Select a resolution purpose.', 'warning');
    try {
      await window.API.finance.resolvePaymentRouting(id, { purpose });
      this.notify('Routing case resolved', 'success');
      new bootstrap.Modal.getInstance(document.getElementById('routingResolveModal'))?.hide();
      await this.load();
    } catch (e) {
      this.notify(e.message || 'Unable to resolve', 'danger');
    }
  },

  toggle() {
    const purpose = document.getElementById('routingPurpose').value;
    document.getElementById('routingEntitlementWrap').style.display = purpose === 'transport' ? '' : 'none';
    document.getElementById('routingUniformSaleWrap').style.display = purpose === 'uniform' ? '' : 'none';
    document.getElementById('routingAmountWrap').style.display = purpose === 'manual' ? '' : 'none';
  },

  money(n) { return 'KES ' + Number(n || 0).toLocaleString('en-KE', { minimumFractionDigits: 2 }); },

  async init() {
    await window.AuthContext?.ready();
    document.getElementById('routingCasesRefresh')?.addEventListener('click', () => this.load());
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
        `${x.provider_code}: ${x.account_identifier} → ${x.purpose} (${x.reference_prefix})`
      ).join(' | ') || 'No collection routes configured.';
    } catch (e) {
      document.getElementById('collectionRoutesList').textContent = 'Unable to load collection routes.';
    }
  };
  f.addEventListener('submit', async e => {
    e.preventDefault();
    try {
      await window.API.finance.savePaymentCollectionRoute({
        provider_code: document.getElementById('routeProvider').value.trim(),
        account_identifier: document.getElementById('routeAccount').value.trim(),
        purpose: document.getElementById('routePurpose').value,
        reference_prefix: document.getElementById('routePrefix').value.trim()
      });
      window.showNotification?.('Collection route saved', 'success');
      f.reset();
      document.getElementById('routePrefix').value = 'FEE';
      await list();
    } catch (err) {
      window.showNotification?.(err.message || 'Unable to save route', 'danger');
    }
  });
  list();
});

window.paymentReconciliationController = paymentReconciliationController;