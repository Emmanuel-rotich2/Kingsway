/**
 * pending_exeat_requests.js — Pending Exeat Requests Controller (standalone page).
 * Leave requests awaiting approval; approve or reject.
 */
const pendingExeatRequestsController = {
  state: { requests: [] },

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

  fmtDate(v) {
    if (!v) return '\u2014';
    const dt = new Date(String(v).replace(' ', 'T'));
    if (isNaN(dt)) return this.esc(v);
    return dt.toLocaleDateString();
  },

  norm(r) { return Array.isArray(r) ? r : (r?.data || r?.requests || r?.exeats || r || []); },

  async load() {
    const body = document.getElementById('pexBody');
    body.innerHTML = '<tr><td colspan="8" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    try {
      const r = await this.API('GET', '/boarding/exeats', null, { status: 'pending' });
      this.state.requests = this.norm(r);
      this.render();
    } catch (e) { body.innerHTML = `<tr><td colspan="8" class="text-center py-3 text-danger">${this.esc(e.message || 'Load failed')}</td></tr>`; }
  },

  async approve(id) {
    try {
      await this.API('PUT', `/boarding/exeats/${id}/approve`, {});
      this.notify('Leave request approved.', 'success');
      this.load();
    } catch (e) { this.notify(e.message || 'Failed to approve request.', 'danger'); }
  },

  async reject(id) {
    const reason = prompt('Rejection reason (optional):');
    if (reason === null) return;
    try {
      await this.API('PUT', `/boarding/exeats/${id}/reject`, { reason });
      this.notify('Leave request rejected.', 'success');
      this.load();
    } catch (e) { this.notify(e.message || 'Failed to reject request.', 'danger'); }
  },

  render() {
    const body = document.getElementById('pexBody');
    if (!this.state.requests.length) { body.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">No pending leave requests.</td></tr>'; return; }
    body.innerHTML = this.state.requests.map(r => `<tr>
      <td class="small">${this.esc(r.admission_no || '\u2014')}</td>
      <td class="small fw-semibold">${this.esc(r.student_name || '\u2014')}</td>
      <td class="small">${this.esc(r.permission_type_name || '\u2014')}</td>
      <td class="small">${this.fmtDate(r.start_date)}</td>
      <td class="small">${this.fmtDate(r.end_date)}</td>
      <td class="small text-muted">${this.esc(r.reason || '\u2014')}</td>
      <td class="small">${this.esc(r.dormitory_name || '\u2014')}</td>
      <td class="text-end text-nowrap">
        <button class="btn btn-sm btn-outline-success" data-kw-onclick="pendingExeatRequestsController.approve(${r.id})" data-year-write><i class="bi bi-check-lg"></i></button>
        <button class="btn btn-sm btn-outline-danger" data-kw-onclick="pendingExeatRequestsController.reject(${r.id})" data-year-write><i class="bi bi-x-lg"></i></button>
      </td>
    </tr>`).join('');
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.load();
  },
};

window.pendingExeatRequestsController = pendingExeatRequestsController;

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => pendingExeatRequestsController.init().catch(() => {}));
} else {
  pendingExeatRequestsController.init().catch(() => {});
}
