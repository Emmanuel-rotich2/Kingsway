/**
 * Transaction Approvals Controller
 * Pending / History tabs for financial expense approvals.
 * API: /finance/expenses, /finance/expenses/{id}
 */

const transactionApprovalsController = {

  _pending: [],
  _history: [],
  _activeTab: 'pending',

  init: async function () {
    await window.AuthContext?.ready();
    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || '') + '/index.php';
      return;
    }
    await Promise.all([this._loadStats(), this._loadPending(), this._loadHistory()]);
  },

  _list: function (r) {
    return Array.isArray(r?.data?.expenses) ? r.data.expenses
         : Array.isArray(r?.expenses) ? r.expenses
         : (Array.isArray(r) ? r : []);
  },

  _loadStats: async function () {
    try {
      const pending = this._list(await callAPI('/finance/expenses?status=pending_approval', 'GET'));
      const total = pending.reduce((s, t) => s + Number(t.amount || 0), 0);
      this._set('taStatPending',  pending.length);
      this._set('taStatTotalAmt', 'KES ' + total.toLocaleString());
      const urgent = pending.filter(t => {
        const d = new Date(t.created_at || '');
        return !isNaN(d) && (Date.now() - d) > 86400000 * 2;
      }).length;
      this._set('taStatUrgent', urgent);
    } catch (e) { console.warn('Stats failed:', e); }
  },

  _loadPending: async function () {
    try {
      const r = await callAPI('/finance/expenses?status=pending_approval', 'GET');
      this._pending = this._list(r);
      if (this._activeTab === 'pending') this._renderPending();
    } catch (e) { console.warn('Pending failed:', e); }
  },

  _loadHistory: async function () {
    try {
      const [approved, rejected] = await Promise.all([
        callAPI('/finance/expenses?status=approved', 'GET'),
        callAPI('/finance/expenses?status=rejected', 'GET'),
      ]);
      this._history = this._list(approved)
        .concat(this._list(rejected))
        .sort((a, b) => new Date(b.created_at || 0) - new Date(a.created_at || 0));
      this._set('taStatApproved', this._list(approved).length);
      if (this._activeTab === 'history') this._renderHistory();
    } catch (e) { console.warn('History failed:', e); }
  },

  switchTab: function (btn, tab) {
    this._activeTab = tab;
    document.querySelectorAll('#taTabs .nav-link').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
    if (tab === 'pending')  this._renderPending();
    if (tab === 'history')  this._renderHistory();
  },

  _renderPending: function () {
    const tbody = document.getElementById('taTableBody');
    if (!tbody) return;

    if (!this._pending.length) {
      tbody.innerHTML = '<tr><td colspan="8" class="text-center py-5 text-muted"><i class="bi bi-check-circle fs-3 d-block mb-2 text-success"></i>No pending approvals.</td></tr>';
      return;
    }

    const canApprove = AuthContext.hasPermission('finance.approve') || AuthContext.hasPermission('finance_approve');

    tbody.innerHTML = this._pending.map(t => {
      const age = this._ageLabel(t.created_at);
      return `<tr>
        <td>${this._esc(t.expense_number || t.id || '—')}</td>
        <td class="fw-semibold">${this._esc(t.description || '—')}</td>
        <td class="fw-bold">KES ${Number(t.amount||0).toLocaleString()}</td>
        <td>${this._esc(t.recorded_by_name || '—')}</td>
        <td>${this._esc(t.created_at || '—')}</td>
        <td><span class="badge bg-${age.cls}">${age.label}</span></td>
        <td class="text-muted small" style="max-width:150px;">${this._esc((t.notes||'—').substring(0,60))}</td>
        <td class="text-end">
          ${canApprove ? `
            <button class="btn btn-sm btn-success me-1" data-kw-onclick="transactionApprovalsController.approve(${t.id})" data-year-write>
              <i class="bi bi-check2"></i> Approve
            </button>
            <button class="btn btn-sm btn-outline-danger" data-kw-onclick="transactionApprovalsController.reject(${t.id})" data-year-write>
              <i class="bi bi-x"></i> Reject
            </button>` : '<span class="text-muted small">Awaiting approval</span>'}
        </td>
      </tr>`;
    }).join('');
  },

  _renderHistory: function () {
    const tbody = document.getElementById('taTableBody');
    if (!tbody) return;

    if (!this._history.length) {
      tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">No approval history.</td></tr>';
      return;
    }

    const statusCls = { approved: 'success', rejected: 'danger', cancelled: 'secondary', paid: 'info' };
    tbody.innerHTML = this._history.map(t => `
      <tr>
        <td>${this._esc(t.expense_number || t.id || '—')}</td>
        <td class="fw-semibold">${this._esc(t.description || '—')}</td>
        <td class="fw-bold">KES ${Number(t.amount||0).toLocaleString()}</td>
        <td>${this._esc(t.recorded_by_name || '—')}</td>
        <td>${this._esc(t.approved_by_name || '—')}</td>
        <td>${this._esc(t.approved_at || t.updated_at || '—')}</td>
        <td><span class="badge bg-${statusCls[(t.status||'').toLowerCase()]||'secondary'}">${this._esc(t.status||'—')}</span></td>
        <td class="text-muted small">${this._esc((t.rejection_reason||t.notes||'—').substring(0,60))}</td>
      </tr>`).join('');
  },

  approve: async function (id) {
    const notes = await window.promptAction('Input', 'Approval notes (optional):') || '';
    try {
      await callAPI('/finance/expenses/' + id, 'PUT', { status: 'approved', notes: notes });
      showNotification('Transaction approved.', 'success');
      await Promise.all([this._loadStats(), this._loadPending(), this._loadHistory()]);
    } catch (e) { showNotification(e.message || 'Approval failed.', 'danger'); }
  },

  reject: async function (id) {
    const reason = await window.promptAction('Input', 'Reason for rejection:');
    if (!reason) return;
    try {
      await callAPI('/finance/expenses/' + id, 'PUT', { status: 'rejected', reason: reason });
      showNotification('Transaction rejected.', 'warning');
      await Promise.all([this._loadStats(), this._loadPending(), this._loadHistory()]);
    } catch (e) { showNotification(e.message || 'Rejection failed.', 'danger'); }
  },

  _ageLabel: function (dateStr) {
    if (!dateStr) return { label: 'Unknown', cls: 'secondary' };
    const ms  = Date.now() - new Date(dateStr).getTime();
    const days = Math.floor(ms / 86400000);
    if (days === 0) return { label: 'Today',      cls: 'success' };
    if (days === 1) return { label: '1 day ago',   cls: 'info' };
    if (days <= 3)  return { label: days + ' days', cls: 'warning' };
    return { label: days + ' days', cls: 'danger' };
  },

  _set: (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; },
  _esc: s => { const d = document.createElement('div'); d.textContent = String(s ?? ''); return d.innerHTML; },
};

document.addEventListener('DOMContentLoaded', () => transactionApprovalsController.init());

window.transactionApprovalsController = transactionApprovalsController;
