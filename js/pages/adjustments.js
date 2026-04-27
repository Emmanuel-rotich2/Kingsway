/**
 * Financial Adjustments Controller
 * Manages fee waivers, discounts, corrections, refunds.
 * Approve/reject workflow for authorized roles.
 * API: /finance/adjustments
 */

const adjustmentsController = {

  _data: [],
  _activeTab: '',
  _modal: null,
  _approveModal: null,

  init: async function () {
    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || '') + '/index.php';
      return;
    }
    this._modal        = new bootstrap.Modal(document.getElementById('ajModal'));
    this._approveModal = new bootstrap.Modal(document.getElementById('ajApproveModal'));
    await Promise.all([this._loadStats(), this._loadData(), this._loadStudents()]);
  },

  _loadStats: async function () {
    try {
      const r = await callAPI('/finance/adjustments', 'GET');
      const items = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      const pending  = items.filter(a => (a.status||'').toLowerCase() === 'pending').length;
      const approved = items.filter(a => (a.status||'').toLowerCase() === 'approved').length;
      const rejected = items.filter(a => (a.status||'').toLowerCase() === 'rejected').length;
      const total    = items.reduce((s, a) => s + Math.abs(Number(a.amount || 0)), 0);
      this._set('ajStatPending',  pending);
      this._set('ajStatApproved', approved);
      this._set('ajStatAmount',   'KES ' + total.toLocaleString());
      this._set('ajStatRejected', rejected);
    } catch (e) { console.warn('Stats failed:', e); }
  },

  _loadData: async function () {
    const tbody = document.getElementById('ajTableBody');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div></td></tr>';
    try {
      const r = await callAPI('/finance/adjustments', 'GET');
      this._data = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      this._renderTab(this._activeTab);
    } catch (e) {
      tbody.innerHTML = `<tr><td colspan="9" class="text-danger text-center py-4">Failed to load adjustments.</td></tr>`;
    }
  },

  _loadStudents: async function () {
    try {
      const r = await callAPI('/students', 'GET');
      const students = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      const sel = document.getElementById('ajStudentId');
      if (sel) {
        sel.innerHTML = '<option value="">— Select student (leave blank for general ledger) —</option>' +
          students.map(s => `<option value="${s.id}">${this._esc((s.first_name||'') + ' ' + (s.last_name||''))} (${this._esc(s.admission_no||'')})</option>`).join('');
      }
    } catch (e) { console.warn('Students failed:', e); }
  },

  switchTab: function (btn, status) {
    this._activeTab = status;
    document.querySelectorAll('#ajTabs .nav-link').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
    this._renderTab(status);
  },

  _renderTab: function (status) {
    const tbody = document.getElementById('ajTableBody');
    if (!tbody) return;
    const filtered = !status ? this._data : this._data.filter(a => (a.status||'pending').toLowerCase() === status);

    if (!filtered.length) {
      tbody.innerHTML = `<tr><td colspan="9" class="text-center py-4 text-muted">No ${status||''}  adjustments found.</td></tr>`;
      return;
    }

    const statusCls = { pending: 'warning', approved: 'success', rejected: 'danger' };
    const typeCls   = { fee_waiver: 'info', discount: 'primary', correction: 'secondary', overpayment_refund: 'success', write_off: 'danger' };
    const canApprove = AuthContext.hasPermission('finance.approve') || AuthContext.hasPermission('finance_approve');

    tbody.innerHTML = filtered.map(a => {
      const st   = (a.status || 'pending').toLowerCase();
      const type = (a.adjustment_type || a.type || 'correction').replace(/_/g, ' ');
      const typeKey = (a.adjustment_type || a.type || '').toLowerCase();
      const sign = Number(a.amount) < 0 ? '-' : '+';
      return `<tr>
        <td class="small text-muted">${this._esc(a.reference || a.ref_no || a.id || '—')}</td>
        <td>${this._esc(a.created_at?.split('T')[0] || a.date || '—')}</td>
        <td><span class="badge bg-${typeCls[typeKey]||'secondary'} text-capitalize">${this._esc(type)}</span></td>
        <td>${this._esc(a.student_name || a.account_name || 'General Ledger')}</td>
        <td class="text-end fw-bold ${Number(a.amount)<0?'text-danger':'text-success'}">
          ${sign} KES ${Math.abs(Number(a.amount||0)).toLocaleString()}
        </td>
        <td class="small text-muted" style="max-width:160px;">${this._esc((a.reason||'—').substring(0,60))}</td>
        <td>${this._esc(a.submitted_by || a.requested_by || '—')}</td>
        <td class="text-center"><span class="badge bg-${statusCls[st]||'secondary'}">${this._esc(a.status||'pending')}</span></td>
        <td class="text-center">
          ${st === 'pending' && canApprove ? `
            <div class="btn-group btn-group-sm">
              <button class="btn btn-success" onclick="adjustmentsController.showApproveModal(${a.id},'${this._esc(a.reference||a.id)}','approve')">
                <i class="bi bi-check2"></i>
              </button>
              <button class="btn btn-danger" onclick="adjustmentsController.showApproveModal(${a.id},'${this._esc(a.reference||a.id)}','reject')">
                <i class="bi bi-x-lg"></i>
              </button>
            </div>` : '—'}
        </td>
      </tr>`;
    }).join('');
  },

  showNewModal: function () {
    ['ajType','ajRefNo','ajStudentId','ajAmount','ajReason'].forEach(id => {
      const el = document.getElementById(id); if (el) el.value = '';
    });
    const sign = document.getElementById('ajSign');
    if (sign) sign.value = '+';
    const err = document.getElementById('ajModalError');
    if (err) { err.classList.add('d-none'); err.textContent = ''; }
    const docEl = document.getElementById('ajDoc');
    if (docEl) docEl.value = '';
    this._modal.show();
  },

  saveAdjustment: async function () {
    const type   = document.getElementById('ajType')?.value;
    const ref    = document.getElementById('ajRefNo')?.value.trim();
    const student= document.getElementById('ajStudentId')?.value;
    const amount = document.getElementById('ajAmount')?.value;
    const sign   = document.getElementById('ajSign')?.value || '+';
    const reason = document.getElementById('ajReason')?.value.trim();
    const errEl  = document.getElementById('ajModalError');

    if (!type || !amount || !reason) {
      if (errEl) { errEl.textContent = 'Type, amount, and reason are required.'; errEl.classList.remove('d-none'); }
      return;
    }
    if (errEl) errEl.classList.add('d-none');

    const signedAmount = sign === '-' ? -Math.abs(Number(amount)) : Math.abs(Number(amount));

    try {
      await callAPI('/finance/adjustments', 'POST', {
        adjustment_type: type,
        ref_no:          ref || null,
        student_id:      student || null,
        amount:          signedAmount,
        reason,
        status:          'pending',
      });
      showNotification('Adjustment submitted for approval.', 'success');
      this._modal.hide();
      await Promise.all([this._loadStats(), this._loadData()]);
    } catch (e) {
      if (errEl) { errEl.textContent = e.message || 'Save failed.'; errEl.classList.remove('d-none'); }
    }
  },

  showApproveModal: function (id, ref, action) {
    document.getElementById('ajApproveId').value     = id;
    document.getElementById('ajApproveAction').value = action;
    document.getElementById('ajApproveRef').textContent = ref;
    document.getElementById('ajApproveNotes').value  = '';
    const err = document.getElementById('ajApproveError');
    if (err) { err.classList.add('d-none'); err.textContent = ''; }
    const btn = document.getElementById('ajApproveConfirmBtn');
    if (btn) {
      btn.className = action === 'approve' ? 'btn btn-success' : 'btn btn-danger';
      btn.textContent = action === 'approve' ? 'Approve' : 'Reject';
    }
    document.getElementById('ajApproveModalTitle').textContent =
      action === 'approve' ? 'Approve Adjustment' : 'Reject Adjustment';
    this._approveModal.show();
  },

  confirmApproveReject: async function () {
    const id     = document.getElementById('ajApproveId')?.value;
    const action = document.getElementById('ajApproveAction')?.value;
    const notes  = document.getElementById('ajApproveNotes')?.value.trim();
    const errEl  = document.getElementById('ajApproveError');
    if (errEl) errEl.classList.add('d-none');

    const status = action === 'approve' ? 'approved' : 'rejected';
    try {
      await callAPI('/finance/adjustments/' + id, 'PUT', { status, approval_notes: notes });
      showNotification(`Adjustment ${status}.`, 'success');
      this._approveModal.hide();
      await Promise.all([this._loadStats(), this._loadData()]);
    } catch (e) {
      if (errEl) { errEl.textContent = e.message || 'Action failed.'; errEl.classList.remove('d-none'); }
    }
  },

  exportCSV: function () {
    const data = this._activeTab ? this._data.filter(a => (a.status||'').toLowerCase() === this._activeTab) : this._data;
    if (!data.length) { showNotification('No data to export.', 'warning'); return; }
    const header = ['Reference','Date','Type','Student/Account','Amount','Reason','Submitted By','Status'];
    const rows = [header.join(','), ...data.map(a => [
      `"${a.reference||a.id||''}"`,`"${a.created_at?.split('T')[0]||''}"`,`"${a.adjustment_type||''}"`,
      `"${a.student_name||'General'}"`,a.amount||0,`"${(a.reason||'').replace(/"/g,"'")}"`,
      `"${a.submitted_by||''}"`,`"${a.status||''}"`,
    ].join(','))];
    const blob = new Blob([rows.join('\n')], { type: 'text/csv' });
    const el = document.createElement('a');
    el.href = URL.createObjectURL(blob);
    el.download = `adjustments_${new Date().toISOString().split('T')[0]}.csv`;
    el.click();
    URL.revokeObjectURL(el.href);
  },

  _set: (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; },
  _esc: s => { const d = document.createElement('div'); d.textContent = String(s ?? ''); return d.innerHTML; },
};

document.addEventListener('DOMContentLoaded', () => adjustmentsController.init());
