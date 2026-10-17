const supplierPaymentsController = {
  rows: [],
  selected: new Set(),
  state: { referenceCache: null },

  esc(v) {
    const d = document.createElement('div');
    d.textContent = String(v ?? '');
    return d.innerHTML;
  },

  notify(msg, type) {
    if (window.showNotification) window.showNotification(msg, type);
    else alert(msg);
  },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.resolve("finance", "supplier-payments-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Supplier payments Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async load() {
    const body = document.getElementById('spBody');
    body.innerHTML = '<tr><td colspan="7" class="text-center py-5"><div class="spinner-border spinner-border-sm text-primary"></div></td></tr>';
    try {
      const cache = this.state.referenceCache;
      const r = cache?.payables !== undefined ? cache.payables : await callAPI('/finance/supplier-payables', 'GET');
      const data = r?.data || r;
      this.rows = Array.isArray(data?.payables) ? data.payables : [];
      this.selected.clear();
      this.render();
    } catch (e) {
      body.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-5">${this.esc(e.message || 'Unable to load supplier liabilities')}</td></tr>`;
    }
  },

  render() {
    const fmt = n => 'KES ' + Number(n || 0).toLocaleString('en-KE', { minimumFractionDigits: 2 });
    const body = document.getElementById('spBody');
    if (!this.rows.length) {
      body.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-5">No approved supplier liabilities awaiting payment.</td></tr>';
      this.updateSummary();
      return;
    }
    body.innerHTML = this.rows.map((r, i) => {
      const bank = (r.bank_accounts || []).map(a => `<option value="bank:${a.id}">${this.esc(a.bank_name)} · ${this.esc(a.account_number)} · ${this.esc(a.account_name)}</option>`).join('');
      const mobile = (r.mobile_accounts || []).map(a => `<option value="mobile:${a.id}">M-Pesa · ${this.esc(a.phone_number)} · ${this.esc(a.account_name)}</option>`).join('');
      const ready = !!(bank || mobile);
      return `<tr><td><input class="sp-check" type="checkbox" data-index="${i}" ${ready ? '' : 'disabled'}></td><td><strong>${this.esc(r.supplier_name)}</strong><div class="small text-muted">${this.esc(r.description || 'Supplier expense')} · #${Number(r.expense_id)}</div></td><td class="text-end fw-semibold">${fmt(r.outstanding_amount)}</td><td><input class="form-control form-control-sm sp-amount" type="number" min="0.01" max="${Number(r.outstanding_amount).toFixed(2)}" step="0.01" value="${Number(r.outstanding_amount).toFixed(2)}" data-index="${i}" ${ready ? '' : 'disabled'}></td><td><select class="form-select form-select-sm sp-channel" data-index="${i}" ${ready ? '' : 'disabled'}><option value="bank">Bank transfer</option>${mobile ? '<option value="mobile">M-Pesa B2C</option>' : ''}</select></td><td><select class="form-select form-select-sm sp-account" data-index="${i}" ${ready ? '' : 'disabled'}>${bank || '<option value="">No verified account</option>'}${mobile}</select>${ready ? '' : '<div class="small text-danger mt-1">Configure and verify account</div>'}</td><td><span class="badge ${ready ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning'}">${ready ? 'Ready' : 'Blocked'}</span></td></tr>`;
    }).join('');
    document.querySelectorAll('.sp-channel').forEach(sel => sel.addEventListener('change', e => this.filterAccounts(Number(e.target.dataset.index), e.target.value)));
    document.querySelectorAll('.sp-check').forEach(chk => chk.addEventListener('change', e => this.toggleSingle(Number(e.target.dataset.index), e.target.checked)));
  },

  filterAccounts(i, channel) {
    const r = this.rows[i];
    const select = document.querySelector(`.sp-account[data-index="${i}"]`);
    const accounts = channel === 'mobile' ? (r.mobile_accounts || []) : (r.bank_accounts || []);
    if (!select) return;
    select.innerHTML = accounts.map(a => channel === 'mobile'
      ? `<option value="mobile:${a.id}">M-Pesa · ${this.esc(a.phone_number)} · ${this.esc(a.account_name)}</option>`
      : `<option value="bank:${a.id}">${this.esc(a.bank_name)} · ${this.esc(a.account_number)} · ${this.esc(a.account_name)}</option>`).join('');
  },

  toggleSingle(i, checked) {
    const r = this.rows[i];
    if ((r.bank_accounts || []).length || (r.mobile_accounts || []).length) {
      if (checked) this.selected.add(i);
      else this.selected.delete(i);
      this.updateSummary();
    }
  },

  toggleAll(checked) {
    this.selected.clear();
    this.rows.forEach((r, i) => {
      if ((r.bank_accounts || []).length || (r.mobile_accounts || []).length) {
        const box = document.querySelector(`.sp-check[data-index="${i}"]`);
        if (box) {
          box.checked = checked;
          if (checked) this.selected.add(i);
        }
      }
    });
    this.updateSummary();
  },

  updateSummary() {
    const fmt = n => 'KES ' + Number(n || 0).toLocaleString('en-KE', { minimumFractionDigits: 2 });
    let total = 0;
    this.selected.forEach(i => {
      total += Number(document.querySelector(`.sp-amount[data-index="${i}"]`)?.value || 0);
    });
    document.getElementById('spCount').textContent = this.rows.length;
    document.getElementById('spOutstanding').textContent = fmt(this.rows.reduce((s, r) => s + Number(r.outstanding_amount || 0), 0));
    document.getElementById('spSelected').textContent = `${this.selected.size} · ${fmt(total)}`;
    document.getElementById('spSubmit').disabled = !this.selected.size;
  },

  async submit() {
    const items = Array.from(this.selected).map(i => {
      const channel = document.querySelector(`.sp-channel[data-index="${i}"]`)?.value || 'bank';
      const account = document.querySelector(`.sp-account[data-index="${i}"]`)?.value || '';
      const [kind, id] = account.split(':');
      return {
        expense_id: Number(this.rows[i].expense_id),
        amount: Number(document.querySelector(`.sp-amount[data-index="${i}"]`)?.value || 0),
        channel: channel === 'mobile' ? 'mpesa_b2c' : 'kcb_bank',
        supplier_bank_account_id: kind === 'bank' ? Number(id) : null,
        supplier_mobile_account_id: kind === 'mobile' ? Number(id) : null,
      };
    });
    if (items.some(x => !x.amount || x.amount <= 0)) return this.notify('Every selected payment needs a positive amount.', 'warning');
    const btn = document.getElementById('spSubmit');
    btn.disabled = true;
    try {
      const r = await callAPI('/finance/supplier-payments', 'POST', { items });
      const results = r?.data?.results || [];
      const failed = results.filter(x => x.status === 'failed');
      this.notify(failed.length ? `${results.length - failed.length} submitted; ${failed.length} failed.` : `${results.length} supplier payment(s) submitted.`, failed.length ? 'warning' : 'success');
      await this.load();
    } catch (e) {
      this.notify(e.message || 'Submission failed', 'danger');
      btn.disabled = false;
    }
  },

  async init() {
    await window.AuthContext?.ready();
    if (!window.AuthContext?.isAuthenticated()) return;
    document.getElementById('spRefresh')?.addEventListener('click', () => this.load());
    document.getElementById('spAll')?.addEventListener('change', e => this.toggleAll(e.target.checked));
    document.getElementById('spSubmit')?.addEventListener('click', () => this.submit());
    await this.loadReferencesComposed();
    await this.load();
    window.APIRealtime?.register?.("supplier_payments", {
      refresh: async () => {
        this.state.referenceCache = null;
        await this.loadReferencesComposed();
        await this.load();
      }
    }, ["finance"]);
  }
};

const _supplierPaymentsSubmit = supplierPaymentsController.submit;
supplierPaymentsController.submit = async function () {
  const sourceId = Number(document.getElementById('spSourceAccount')?.value || 0);
  if (!sourceId) return this.notify('Select an authorized supplier source account first.', 'warning');
  const originalCallAPI = window.callAPI;
  window.callAPI = async (path, method, payload, ...rest) => {
    if (path === '/finance/supplier-payments' && payload && Array.isArray(payload.items)) {
      payload.items = payload.items.map(item => Object.assign({}, item, { source_financial_account_id: sourceId }));
    }
    return originalCallAPI(path, method, payload, ...rest);
  };
  try { return await _supplierPaymentsSubmit.call(this); } finally { window.callAPI = originalCallAPI; }
};

const _supplierPaymentsLoad = supplierPaymentsController.load;
supplierPaymentsController.load = async function () {
  const result = await _supplierPaymentsLoad.call(this);
  const select = document.getElementById('spSourceAccount');
  if (select && !select.dataset.loaded) {
    try {
      const cache = this.state.referenceCache;
      const response = cache?.accounts !== undefined ? cache.accounts : await callAPI('/finance/financial-accounts', 'GET');
      const accounts = (response?.data || response)?.accounts || [];
      const options = accounts.filter(a => String(a.purposes || '').split(',').includes('suppliers'));
      select.innerHTML = options.length ? options.map(a => '<option value="' + Number(a.id) + '">' + this.esc(a.account_name) + ' · ' + this.esc(a.account_identifier) + '</option>').join('') : '<option value="">No authorized supplier account configured</option>';
      select.dataset.loaded = '1';
    } catch (e) { select.innerHTML = '<option value="">Unable to load source accounts</option>'; }
  }
  return result;
};

window.supplierPaymentsController = supplierPaymentsController;
document.addEventListener('DOMContentLoaded', () => supplierPaymentsController.init().catch(() => {}));