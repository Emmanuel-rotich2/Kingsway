/**
 * Petty Cash Controller — petty_cash.php
 * API: GET/POST /finance/petty-cash
 *      GET /finance/expense-categories
 */
const pettyCashController = {
  _data: [],
  _filtered: [],
  _categories: [],
  _fund: null,
  _page: 1,
  _perPage: 15,
  _modal: null,

  init: async function () {
    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || '') + '/index.php';
      return;
    }
    this._modal = new bootstrap.Modal(document.getElementById('pettyCashModal'));
    this._canCreate  = AuthContext.hasPermission('finance.create')  || AuthContext.hasPermission('finance_create')
                    || AuthContext.hasPermission('expenses.create') || AuthContext.hasPermission('expenses_create');
    this._canApprove = AuthContext.hasPermission('finance.approve') || AuthContext.hasPermission('finance_approve');

    const addBtn = document.getElementById('addPcBtn');
    if (addBtn) addBtn.style.display = this._canCreate ? '' : 'none';

    this._bindEvents();
    await Promise.all([this._loadCategories(), this._load()]);
  },

  _bindEvents: function () {
    ['pcSearch', 'pcTypeFilter', 'pcCategoryFilter', 'pcDateFrom', 'pcDateTo'].forEach(id => {
      document.getElementById(id)?.addEventListener('input',  () => this._applyFilters());
      document.getElementById(id)?.addEventListener('change', () => this._applyFilters());
    });
  },

  _load: async function () {
    const tbody = document.getElementById('pettyCashTableBody');
    if (tbody) tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4"><div class="spinner-border spinner-border-sm text-success"></div><span class="ms-2 text-muted">Loading…</span></td></tr>';
    try {
      const r    = await callAPI('/finance/petty-cash', 'GET');
      const resp = r?.data || r;
      this._fund   = resp?.fund   || {};
      this._data   = Array.isArray(resp?.transactions) ? resp.transactions : [];
      const stats  = resp?.stats  || {};
      this._setStats(stats);
      this._filtered = [...this._data];
      this._page = 1;
      this._renderTable();
    } catch (e) {
      if (tbody) tbody.innerHTML = '<tr><td colspan="9" class="text-danger text-center py-4">Failed to load petty cash. ' + (e.message || '') + '</td></tr>';
    }
  },

  _loadCategories: async function () {
    try {
      const r = await callAPI('/finance/expense-categories', 'GET');
      this._categories = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      const opts = this._categories.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
      document.getElementById('pcCategoryFilter') && (document.getElementById('pcCategoryFilter').innerHTML = '<option value="">All Categories</option>' + opts);
      document.getElementById('pc_category_id')   && (document.getElementById('pc_category_id').innerHTML   = '<option value="">— Select category —</option>' + opts);
    } catch (e) { /* non-fatal */ }
  },

  _setStats: function (stats) {
    const fmt = v => 'KES ' + Number(v || 0).toLocaleString('en-KE', {minimumFractionDigits: 2});
    this._set('kpiCurrentBalance',    fmt(this._fund?.current_balance   || 0));
    this._set('kpiExpensesMonth',     fmt(stats.expenses_this_month     || 0));
    this._set('kpiTopupsMonth',       fmt(stats.topups_this_month       || 0));
    const lastRecon = this._fund?.last_reconciled_at;
    this._set('kpiLastReconciliation', lastRecon ? new Date(lastRecon).toLocaleDateString('en-KE') : 'Never');
  },

  _applyFilters: function () {
    const q   = (document.getElementById('pcSearch')?.value           || '').toLowerCase();
    const tp  = document.getElementById('pcTypeFilter')?.value         || '';
    const cat = document.getElementById('pcCategoryFilter')?.value     || '';
    const df  = document.getElementById('pcDateFrom')?.value           || '';
    const dt  = document.getElementById('pcDateTo')?.value             || '';
    this._filtered = this._data.filter(t => {
      if (tp  && t.type !== tp) return false;
      if (cat && String(t.category_id) !== cat) return false;
      if (df  && (t.transaction_date || '') < df) return false;
      if (dt  && (t.transaction_date || '') > dt) return false;
      if (q   && !(t.description || '').toLowerCase().includes(q)
              && !(t.vendor_name  || '').toLowerCase().includes(q)) return false;
      return true;
    });
    this._page = 1;
    this._renderTable();
  },

  clearFilters: function () {
    ['pcSearch', 'pcTypeFilter', 'pcCategoryFilter', 'pcDateFrom', 'pcDateTo'].forEach(id => {
      const el = document.getElementById(id); if (el) el.value = '';
    });
    this._applyFilters();
  },

  _renderTable: function () {
    const tbody = document.getElementById('pettyCashTableBody');
    if (!tbody) return;
    const start = (this._page - 1) * this._perPage;
    const page  = this._filtered.slice(start, start + this._perPage);

    if (!page.length) {
      tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted">No petty cash records found.</td></tr>';
      this._setPagination(); return;
    }
    tbody.innerHTML = page.map((t, i) => {
      const isExpense = t.type === 'expense';
      return `<tr>
        <td>${start + i + 1}</td>
        <td>${this._esc(t.transaction_date || '—')}</td>
        <td>${this._esc(t.description || '—')}</td>
        <td><span class="badge bg-light text-dark border">${this._esc(t.category_name || '—')}</span></td>
        <td class="text-center">
          <span class="badge bg-${isExpense ? 'danger' : 'success'}">${isExpense ? 'Expense' : 'Top-up'}</span>
        </td>
        <td class="text-end fw-bold ${isExpense ? 'text-danger' : 'text-success'}">
          ${isExpense ? '−' : '+'}KES ${Number(t.amount || 0).toLocaleString()}
        </td>
        <td class="text-end text-muted small">KES ${Number(t.balance_after || 0).toLocaleString()}</td>
        <td>${this._esc(t.recorded_by_name || '—')}</td>
        <td class="text-end">
          <button class="btn btn-sm btn-outline-danger py-0" onclick="pettyCashController.delete(${t.id})"
                  title="Delete" ${!this._canApprove ? 'style="display:none"' : ''}>
            <i class="bi bi-trash3"></i>
          </button>
        </td>
      </tr>`;
    }).join('');
    this._setPagination();
  },

  _setPagination: function () {
    const pg = document.getElementById('pcPagination');
    if (!pg) return;
    const pages = Math.ceil(this._filtered.length / this._perPage);
    if (pages <= 1) { pg.innerHTML = ''; return; }
    pg.innerHTML = Array.from({length: pages}, (_, i) =>
      `<li class="page-item ${i+1===this._page?'active':''}">
         <button class="page-link" onclick="pettyCashController._goPage(${i+1})">${i+1}</button>
       </li>`
    ).join('');
  },

  _goPage: function (p) { this._page = p; this._renderTable(); },

  // ── Modal ─────────────────────────────────────────────────────────────────
  showModal: function () {
    document.getElementById('pc_id').value                = '';
    document.getElementById('pc_type').value              = 'expense';
    document.getElementById('pc_category_id').value       = '';
    document.getElementById('pc_amount').value            = '';
    document.getElementById('pc_date').value              = new Date().toISOString().split('T')[0];
    document.getElementById('pc_description').value       = '';
    document.getElementById('pc_vendor_name').value       = '';
    document.getElementById('pc_receipt_number').value    = '';
    document.getElementById('pc_notes').value             = '';
    this._modal.show();
  },

  save: async function () {
    const type  = document.getElementById('pc_type')?.value;
    const catId = document.getElementById('pc_category_id')?.value;
    const amt   = parseFloat(document.getElementById('pc_amount')?.value || 0);
    const date  = document.getElementById('pc_date')?.value;
    const desc  = document.getElementById('pc_description')?.value.trim();

    if (!type || !amt || !date || !desc) {
      showNotification('Type, Amount, Date, and Description are required.', 'warning');
      return;
    }
    if (type === 'expense' && this._fund && amt > (this._fund.current_balance || 0)) {
      showNotification(`Insufficient petty cash balance. Available: KES ${Number(this._fund.current_balance).toLocaleString()}`, 'warning');
      return;
    }
    try {
      await callAPI('/finance/petty-cash', 'POST', {
        type,
        category_id:    catId || null,
        amount:         amt,
        transaction_date: date,
        description:    desc,
        vendor_name:    document.getElementById('pc_vendor_name')?.value  || null,
        receipt_number: document.getElementById('pc_receipt_number')?.value || null,
        notes:          document.getElementById('pc_notes')?.value         || null,
      });
      showNotification('Petty cash record saved.', 'success');
      this._modal.hide();
      await this._load();
    } catch (e) {
      showNotification(e.message || 'Save failed.', 'danger');
    }
  },

  delete: async function (id) {
    if (!confirm('Delete this petty cash record?')) return;
    try {
      await callAPI('/finance/petty-cash/' + id, 'DELETE');
      showNotification('Record deleted.', 'success');
      await this._load();
    } catch (e) {
      showNotification(e.message || 'Delete failed.', 'danger');
    }
  },

  exportCSV: function () {
    if (!this._filtered.length) { showNotification('No data to export.', 'warning'); return; }
    const h = ['#','Date','Description','Category','Type','Amount','Balance After','Recorded By'];
    const rows = this._filtered.map((t, i) => [
      i+1, `"${t.transaction_date||''}"`, `"${(t.description||'').replace(/"/g,"'")}"`,
      `"${t.category_name||''}"`, t.type, t.amount||0, t.balance_after||0,
      `"${t.recorded_by_name||''}"`
    ].join(','));
    const blob = new Blob([[h.join(','), ...rows].join('\n')], {type: 'text/csv'});
    const a = document.createElement('a'); a.href = URL.createObjectURL(blob);
    a.download = 'petty_cash_' + new Date().toISOString().slice(0,10) + '.csv'; a.click();
  },

  _set: (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; },
  _esc: s => { const d = document.createElement('div'); d.textContent = String(s ?? ''); return d.innerHTML; },
};
document.addEventListener('DOMContentLoaded', () => pettyCashController.init());
