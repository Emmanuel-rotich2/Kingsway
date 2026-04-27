/**
 * Financial Audit Logs Controller
 * Paginated, filterable audit trail of all financial actions.
 * API: /reports/financial-transactions-summary
 */

const auditLogsController = {

  _data: [],
  _filtered: [],
  _page: 1,
  _perPage: 50,

  init: async function () {
    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || '') + '/index.php';
      return;
    }
    await this._loadData();
  },

  _loadData: async function () {
    const tbody = document.getElementById('alTableBody');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div></td></tr>';
    try {
      const r = await callAPI('/reports/financial-transactions-summary', 'GET');
      this._data = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      this._filtered = [...this._data];
      this._computeStats();
      this._page = 1;
      this._renderPage();
    } catch (e) {
      tbody.innerHTML = `<tr><td colspan="8" class="text-danger text-center py-4">Failed to load audit logs: ${this._esc(e.message)}</td></tr>`;
    }
  },

  _computeStats: function () {
    const today = new Date().toISOString().split('T')[0];
    this._set('alStatTotal',   this._data.length);
    this._set('alStatToday',   this._data.filter(l => (l.created_at||l.timestamp||'').startsWith(today)).length);
    this._set('alStatModified',this._data.filter(l => (l.action||'').toUpperCase() === 'UPDATE').length);
    this._set('alStatDeleted', this._data.filter(l => (l.action||'').toUpperCase() === 'DELETE').length);
  },

  filter: function () {
    const search   = (document.getElementById('alSearch')?.value   || '').toLowerCase();
    const action   = (document.getElementById('alAction')?.value   || '').toUpperCase();
    const dateFrom = document.getElementById('alDateFrom')?.value  || '';
    const dateTo   = document.getElementById('alDateTo')?.value    || '';

    this._filtered = this._data.filter(l => {
      const matchSearch = !search || [l.user_name,l.action,l.entity,l.reference,l.details,l.ip_address]
        .some(f => (f||'').toLowerCase().includes(search));
      const matchAction = !action || (l.action||'').toUpperCase() === action;
      const logDate = (l.created_at||l.timestamp||'').split('T')[0];
      const matchFrom = !dateFrom || logDate >= dateFrom;
      const matchTo   = !dateTo   || logDate <= dateTo;
      return matchSearch && matchAction && matchFrom && matchTo;
    });
    this._page = 1;
    this._renderPage();
  },

  _renderPage: function () {
    const tbody = document.getElementById('alTableBody');
    if (!tbody) return;
    const start = (this._page - 1) * this._perPage;
    const end   = start + this._perPage;
    const page  = this._filtered.slice(start, end);
    const total = this._filtered.length;
    const pages = Math.ceil(total / this._perPage);

    const infoEl = document.getElementById('alPaginationInfo');
    if (infoEl) infoEl.textContent = `Showing ${start+1}–${Math.min(end,total)} of ${total} entries`;

    const prevBtn = document.getElementById('alPrevBtn');
    const nextBtn = document.getElementById('alNextBtn');
    if (prevBtn) prevBtn.disabled = this._page <= 1;
    if (nextBtn) nextBtn.disabled = this._page >= pages;

    if (!page.length) {
      tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">No audit entries found.</td></tr>';
      return;
    }

    const actionCls = { CREATE: 'success', UPDATE: 'warning', DELETE: 'danger', APPROVE: 'primary', REJECT: 'secondary' };

    tbody.innerHTML = page.map(l => {
      const action = (l.action || 'action').toUpperCase();
      return `<tr>
        <td class="text-muted small text-nowrap">${this._esc(l.created_at || l.timestamp || '—')}</td>
        <td class="fw-semibold">${this._esc(l.user_name || l.performed_by || '—')}</td>
        <td><span class="badge bg-${actionCls[action]||'secondary'}">${this._esc(action)}</span></td>
        <td>${this._esc(l.entity || l.table_name || '—')}</td>
        <td class="small">${this._esc(l.reference || l.entity_id || '—')}</td>
        <td>${l.amount ? 'KES ' + Number(l.amount).toLocaleString() : '—'}</td>
        <td class="small text-muted" style="max-width:200px;">${this._esc((l.details||l.changes||'—').substring(0,80))}</td>
        <td class="small text-muted">${this._esc(l.ip_address || '—')}</td>
      </tr>`;
    }).join('');
  },

  prevPage: function () { if (this._page > 1) { this._page--; this._renderPage(); } },
  nextPage: function () {
    const pages = Math.ceil(this._filtered.length / this._perPage);
    if (this._page < pages) { this._page++; this._renderPage(); }
  },

  exportCSV: function () {
    if (!this._filtered.length) { showNotification('No data to export.', 'warning'); return; }
    const header = ['Timestamp','User','Action','Entity','Reference','Amount','Details','IP Address'];
    const rows = [header.join(','), ...this._filtered.map(l => [
      `"${l.created_at||l.timestamp||''}"`,`"${l.user_name||''}"`,`"${l.action||''}"`,
      `"${l.entity||''}"`,`"${l.reference||''}"`,l.amount||0,
      `"${(l.details||'').replace(/"/g,"'")}"`,`"${l.ip_address||''}"`,
    ].join(','))];
    const blob = new Blob([rows.join('\n')], { type: 'text/csv' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = `audit_log_${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
    URL.revokeObjectURL(a.href);
  },

  _set: (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; },
  _esc: s => { const d = document.createElement('div'); d.textContent = String(s ?? ''); return d.innerHTML; },
};

document.addEventListener('DOMContentLoaded', () => auditLogsController.init());
