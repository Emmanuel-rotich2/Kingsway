/**
 * Transaction Approvals Controller
 * Pending / History tabs for financial transaction approvals.
 * API: /finance/pending-approvals, /finance
 */

const transactionApprovalsController = {

  _pending: [],
  _history: [],
  _activeTab: 'pending',

  init: async function () {
    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || '') + '/index.php';
      return;
    }
    await Promise.all([this._loadStats(), this._loadPending(), this._loadHistory()]);
  },

  _loadStats: async function () {
    try {
      const r = await callAPI('/finance/pending-approvals', 'GET');
      const items = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      const total = items.reduce((s, t) => s + Number(t.amount || 0), 0);
      this._set('taStatPending',   items.length);
      this._set('taStatTotalAmt',  'KES ' + total.toLocaleString());
      const urgent = items.filter(t => {
        const d = new Date(t.created_at || t.submitted_at || '');
        return !isNaN(d) && (Date.now() - d) > 86400000 * 2;
      }).length;
      this._set('taStatUrgent', urgent);
    } catch (e) { console.warn('Stats failed:', e); }
  },

  _loadPending: async function () {
    try {
      const r = await callAPI('/finance/pending-approvals', 'GET');
      this._pending = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      if (this._activeTab === 'pending') this._renderPending();
    } catch (e) { console.warn('Pending failed:', e); }
  },

  _loadHistory: async function () {
    try {
      const r = await callAPI('/finance?status=approved', 'GET');
      this._history = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      this._set('taStatApproved', this._history.length);
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
      const age = this._ageLabel(t.created_at || t.submitted_at);
      return `<tr>
        <td>${this._esc(t.reference || t.id || '—')}</td>
        <td class="fw-semibold">${this._esc(t.description || t.transaction_type || '—')}</td>
        <td class="fw-bold">KES ${Number(t.amount||0).toLocaleString()}</td>
        <td>${this._esc(t.submitted_by || t.requested_by || '—')}</td>
        <td>${this._esc(t.created_at || t.submitted_at || '—')}</td>
        <td><span class="badge bg-${age.cls}">${age.label}</span></td>
        <td class="text-muted small" style="max-width:150px;">${this._esc((t.notes||'—').substring(0,60))}</td>
        <td class="text-end">
          ${canApprove ? `
            <button class="btn btn-sm btn-success me-1" onclick="transactionApprovalsController.approve(${t.id})">
              <i class="bi bi-check2"></i> Approve
            </button>
            <button class="btn btn-sm btn-outline-danger" onclick="transactionApprovalsController.reject(${t.id})">
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

    const statusCls = { approved: 'success', rejected: 'danger', cancelled: 'secondary' };
    tbody.innerHTML = this._history.map(t => `
      <tr>
        <td>${this._esc(t.reference || t.id || '—')}</td>
        <td class="fw-semibold">${this._esc(t.description || t.transaction_type || '—')}</td>
        <td class="fw-bold">KES ${Number(t.amount||0).toLocaleString()}</td>
        <td>${this._esc(t.submitted_by || t.requested_by || '—')}</td>
        <td>${this._esc(t.approved_by || t.reviewed_by || '—')}</td>
        <td>${this._esc(t.approved_at || t.updated_at || '—')}</td>
        <td><span class="badge bg-${statusCls[(t.status||'').toLowerCase()]||'secondary'}">${this._esc(t.status||'—')}</span></td>
        <td class="text-muted small">${this._esc((t.approval_notes||t.notes||'—').substring(0,60))}</td>
      </tr>`).join('');
  },

  approve: async function (id) {
    const notes = prompt('Approval notes (optional):') || '';
    try {
      await callAPI('/finance/' + id, 'PUT', { status: 'approved', approval_notes: notes });
      showNotification('Transaction approved.', 'success');
      await Promise.all([this._loadStats(), this._loadPending(), this._loadHistory()]);
    } catch (e) { showNotification(e.message || 'Approval failed.', 'danger'); }
  },

  reject: async function (id) {
    const reason = prompt('Reason for rejection:');
    if (!reason) return;
    try {
      await callAPI('/finance/' + id, 'PUT', { status: 'rejected', rejection_reason: reason });
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
