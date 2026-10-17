/**
 * transport_fees.js — Transport Fees Controller (standalone page).
 * Overview of transport billing/collections: per-route monthly ledger and overall arrears.
 */
const transportFeesController = {
  state: { routes: [], stops: [], items: [], referenceCache: null },

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

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); },
  money(n) { return `KES ${Number(n || 0).toLocaleString()}`; },

  initFilters() {
    const monthSel = document.getElementById('monthFilter');
    const yearSel = document.getElementById('yearFilter');
    const now = new Date();
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    monthSel.innerHTML = months.map((m, i) => `<option value="${i + 1}">${m}</option>`).join('');
    monthSel.value = String(now.getMonth() + 1);
    for (let y = now.getFullYear(); y >= now.getFullYear() - 2; y--) {
      yearSel.innerHTML += `<option value="${y}">${y}</option>`;
    }
    yearSel.value = String(now.getFullYear());
  },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.resolve("finance", "transport-fees-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Transport fees Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async loadRoutes() {
    try {
      const cache = this.state.referenceCache;
      const [r, stopResponse] = await Promise.all([
        cache?.routes !== undefined ? cache.routes : this.API('GET', 'transport/all-routes'),
        cache?.stops !== undefined ? cache.stops : this.API('GET', 'transport/all-stops'),
      ]);
      const routes = r?.data || r?.items || [];
      this.state.stops = stopResponse?.data || stopResponse?.items || [];
      this.state.routes = routes;
      const sel = document.getElementById('routeFilter');
      sel.innerHTML = '<option value="">All routes (summary)</option>' + routes.map(x => `<option value="${x.id}">${this.esc(x.name || x.route_name || x.id)}</option>`).join('');
      const entitlementRoute = document.getElementById('transportEntRoute');
      if (entitlementRoute) entitlementRoute.innerHTML = '<option value="">Select route</option>' + routes.map(x => `<option value="${x.id}">${this.esc(x.name || x.route_name || x.id)}</option>`).join('');
      this.updateStopOptions();
    } catch (_) {}
  },

  updateStopOptions() {
    const routeId = Number(document.getElementById('transportEntRoute')?.value || 0);
    const stops = this.state.stops.filter(stop => Number(stop.route_id) === routeId && stop.status !== 'inactive');
    const options = stops.length
      ? '<option value="">Select point</option>' + stops.sort((a, b) => Number(a.sequence) - Number(b.sequence)).map(stop => `<option value="${stop.id}">${this.esc(stop.name)}</option>`).join('')
      : `<option value="">${routeId ? 'No pickup/drop-off points configured' : 'Select a route first'}</option>`;
    ['transportEntPickupStop', 'transportEntDropoffStop'].forEach(id => {
      const select = document.getElementById(id);
      if (select) select.innerHTML = options;
    });
  },

  async loadData() {
    const body = document.getElementById('feesTableBody');
    body.innerHTML = '<tr><td colspan="7" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    const routeId = document.getElementById('routeFilter').value;
    try {
      if (routeId) {
        const month = document.getElementById('monthFilter').value;
        const year  = document.getElementById('yearFilter').value;
        const r = await this.API('GET', 'transport/route-payment-summary', null, { route_id: routeId, month, year });
        const rows = r?.data || [];
        this.state.items = rows.map(row => ({
          admission_no: row.admission_no,
          student_name: `${row.first_name || ''} ${row.last_name || ''}`.trim(),
          route_id: routeId,
          total_expected: row.expected_amount ?? row.amount_due ?? 0,
          total_paid: row.total_paid ?? 0,
          balance: row.balance ?? (Number(row.expected_amount || 0) - Number(row.total_paid || 0)),
        }));
      } else {
        const r = await this.API('GET', 'transport/all-arrears-credits');
        this.state.items = (r?.data || []).map(row => ({
          admission_no: row.admission_no,
          student_name: `${row.first_name || ''} ${row.last_name || ''}`.trim(),
          route_id: row.route_id,
          total_expected: row.total_expected ?? 0,
          total_paid: row.total_paid ?? 0,
          balance: row.balance ?? (Number(row.total_expected || 0) - Number(row.total_paid || 0)),
        }));
      }
      this.render();
    } catch (e) {
      body.innerHTML = `<tr><td colspan="7" class="text-center py-3 text-danger">${this.esc(e.message || 'Load failed')}</td></tr>`;
    }
  },

  render() {
    const body = document.getElementById('feesTableBody');
    const items = this.state.items;
    if (!items.length) { body.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No transport fee records found.</td></tr>'; return; }
    const billed = items.reduce((s, r) => s + Number(r.total_expected || 0), 0);
    const paid   = items.reduce((s, r) => s + Number(r.total_paid || 0), 0);
    const bal    = billed - paid;
    document.getElementById('statStudents').textContent = items.length;
    document.getElementById('statBilled').textContent   = this.money(billed).replace('KES ', '');
    document.getElementById('statCollected').textContent = this.money(paid).replace('KES ', '');
    document.getElementById('statBalance').textContent   = this.money(bal).replace('KES ', '');

    const routeNames = {};
    this.state.routes.forEach(r => { routeNames[r.id] = r.name || r.route_name || r.id; });

    body.innerHTML = items.map(r => {
      const bal = Number(r.balance || 0);
      const badge = bal > 0 ? '<span class="badge bg-danger">Arrears</span>' : (bal < 0 ? '<span class="badge bg-success">Credit</span>' : '<span class="badge bg-success">Paid</span>');
      return `
      <tr>
        <td class="small"><span class="badge bg-light text-dark border">${this.esc(r.admission_no || '\u2014')}</span></td>
        <td class="fw-semibold small">${this.esc(r.student_name || '\u2014')}</td>
        <td class="small text-muted">${this.esc(routeNames[r.route_id] || '\u2014')}</td>
        <td class="text-end small">${this.money(r.total_expected)}</td>
        <td class="text-end small">${this.money(r.total_paid)}</td>
        <td class="text-end small fw-bold ${bal > 0 ? 'text-danger' : 'text-success'}">${this.money(bal)}</td>
        <td>${badge}</td>
      </tr>`;
    }).join('');
  },

  setupEventListeners() {
    if (!document.getElementById('transportEntDays')) {
      const amountGroup = document.getElementById('transportEntAmount')?.closest('.col-md-4');
      if (amountGroup) {
        const group = document.createElement('div');
        group.className = 'col-md-4';
        group.innerHTML = '<label class="form-label">Paid school days</label><input type="number" min="1" step="1" class="form-control" id="transportEntDays" required><div class="form-text">Weekends, holidays and duplicate scans never reduce this balance.</div>';
        amountGroup.before(group);
      }
    }
    ['routeFilter', 'monthFilter', 'yearFilter'].forEach(id => {
      document.getElementById(id)?.addEventListener('change', () => this.loadData());
    });
    document.getElementById('transportEntType')?.addEventListener('change', () => this.updateEntitlementDates());
    document.getElementById('transportEntStart')?.addEventListener('change', () => this.updateEntitlementDates());
    document.getElementById('transportEntRoute')?.addEventListener('change', () => this.updateStopOptions());
    document.getElementById('transportEntitlementForm')?.addEventListener('submit', (event) => { event.preventDefault(); this.saveEntitlement(); });
  },

  updateEntitlementDates() {
    const start = document.getElementById('transportEntStart')?.value;
    const type = document.getElementById('transportEntType')?.value;
    if (!start || !type) return;
    const d = new Date(`${start}T00:00:00`);
    if (type === 'day') d.setDate(d.getDate());
    if (type === 'week') d.setDate(d.getDate() + 6);
    if (type === 'month') d.setMonth(d.getMonth() + 1, 0);
    if (type === 'term') d.setMonth(d.getMonth() + 3, 0);
    if (type === 'year') d.setFullYear(d.getFullYear() + 1); d.setDate(d.getDate() - (type === 'year' ? 1 : 0));
    document.getElementById('transportEntEnd').value = d.toISOString().slice(0, 10);
  },

  async loadEntitlementStudents() {
    const select = document.getElementById('transportEntStudent');
    if (!select || select.dataset.loaded) return;
    try {
      const cache = this.state.referenceCache;
      const response = cache?.students !== undefined ? cache.students : await window.API.students.getAll({ limit: 500, status: 'active' });
      const students = response?.data || [];
      select.innerHTML = '<option value="">Select learner</option>' + students.map(s => `<option value="${s.id}">${this.esc(`${s.admission_no || ''} — ${s.first_name || ''} ${s.last_name || ''}`)}</option>`).join('');
      select.dataset.loaded = '1';
    } catch (error) { select.innerHTML = '<option value="">Unable to load learners</option>'; this.notify(error.message || 'Learners could not be loaded', 'danger'); }
  },

  async saveEntitlement() {
    const button = document.getElementById('transportEntSubmit');
    const data = { student_id: Number(document.getElementById('transportEntStudent').value), route_id: Number(document.getElementById('transportEntRoute').value), pickup_stop_id: Number(document.getElementById('transportEntPickupStop').value), dropoff_stop_id: Number(document.getElementById('transportEntDropoffStop').value), period_type: document.getElementById('transportEntType').value, period_start: document.getElementById('transportEntStart').value, period_end: document.getElementById('transportEntEnd').value, allocated_school_days: Number(document.getElementById('transportEntDays').value), amount_due: Number(document.getElementById('transportEntAmount').value), source_type: document.getElementById('transportEntMethod').value === 'bursary' ? 'bursary' : 'prepaid', notes: document.getElementById('transportEntNotes').value.trim() };
    const financialAccountId = Number(document.getElementById('transportEntFinancialAccount')?.value || 0);
    if (data.amount_due > 0 && data.source_type !== 'bursary' && !financialAccountId) { this.notify('Select the authorized transport receiving account', 'warning'); return; }
    if (!data.student_id || !data.route_id || !data.pickup_stop_id || !data.dropoff_stop_id || !data.period_start || !data.period_end || data.allocated_school_days < 1 || data.amount_due < 0) { this.notify('Complete the learner, route, pickup/drop-off points, eligible dates, paid school days and agreed charge', 'warning'); return; }
    button.disabled = true;
    try {
      const entitlement = await this.API('POST', 'transport/enrollments', data);
      const entitlementId = entitlement?.data?.id || entitlement?.data?.entitlement_id || entitlement?.id;
      const method = document.getElementById('transportEntMethod').value;
      if (entitlementId && data.amount_due > 0 && data.source_type !== 'bursary') {
        if (method === 'cash') {
          await this.API('POST', `transport/entitlements-payment/${entitlementId}`, { amount: data.amount_due, payment_method: method, provider_name: 'manual', provider_reference: document.getElementById('transportEntReference').value, financial_account_id: financialAccountId });
          this.notify('Transport coverage saved and cash payment allocated', 'success');
        } else {
          const intent = await this.API('POST', 'transport/payment-intents', { entitlement_id: Number(entitlementId), amount: data.amount_due, channel: method, phone_number: document.getElementById('transportEntPhone').value, financial_account_id: financialAccountId, idempotency_reference: document.getElementById('transportEntReference').value || undefined });
          const status = intent?.data?.status || intent?.status || 'pending';
          this.notify(status === 'manual_review' ? 'Coverage saved; payment is awaiting finance reconciliation' : 'Coverage saved; payment request is pending provider confirmation', 'info');
        }
      } else this.notify('Transport coverage saved', 'success');
      bootstrap.Modal.getInstance(document.getElementById('transportEntitlementModal'))?.hide();
      this.loadData();
    } catch (error) { this.notify(error.message || 'Transport coverage could not be saved', 'danger'); } finally { button.disabled = false; }
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.loadReferencesComposed();
    this.initFilters();
    this.loadEntitlementStudents();
    this.loadTransportAccounts();
    await this.loadRoutes();
    this.setupEventListeners();
    this.loadData();
    window.APIRealtime?.register?.("transport_fees", {
      refresh: async () => {
        this.state.referenceCache = null;
        await this.loadReferencesComposed();
        await this.loadRoutes();
        this.loadEntitlementStudents();
        this.loadTransportAccounts();
        this.loadData();
      }
    }, ["finance", "students", "transport"]);
  },

  async loadTransportAccounts() {
    const select = document.getElementById('transportEntFinancialAccount');
    if (!select) return;
    try {
      const cache = this.state.referenceCache;
      const response = cache?.accounts !== undefined ? cache.accounts : await window.API.finance.getFinancialAccounts();
      const accounts = ((response?.data || response)?.accounts || []).filter(a => a.status === 'active' && String(a.purposes || '').split(',').includes('transport'));
      select.innerHTML = accounts.length ? '<option value="">Select receiving account</option>' + accounts.map(a => `<option value="${Number(a.id)}">${this.esc(a.account_name)} · ${this.esc(a.account_identifier)}</option>`).join('') : '<option value="">No authorized transport account configured</option>';
    } catch (_) { select.innerHTML = '<option value="">Unable to load receiving accounts</option>'; }
  },
};

window.transportFeesController = transportFeesController;

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => transportFeesController.init().catch(() => {}));
} else {
  transportFeesController.init().catch(() => {});
}
