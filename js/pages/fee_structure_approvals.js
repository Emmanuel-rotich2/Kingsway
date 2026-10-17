/**
 * fee_structure_approvals.js — Director's Fee Structure Bundle Approval Controller.
 * Lists submitted fee bundles and lets the Director approve (bills students) or reject.
 */
const feeApprovalsController = {
  state: { items: [] },

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

  badgeStatus(s) {
    const map = { submitted: ['warning', 'Submitted'], reviewed: ['info', 'Reviewed'], approved: ['success', 'Approved'], rejected: ['danger', 'Rejected'], draft: ['secondary', 'Draft'] };
    const [c, l] = map[s] || ['secondary', s || '\u2014'];
    return `<span class="badge bg-${c}">${l}</span>`;
  },

  async loadData() {
    const body = document.getElementById('bundleTableBody');
    body.innerHTML = '<tr><td colspan="7" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    try {
      const status = document.getElementById('bundleStatusFilter').value;
      const year   = document.getElementById('bundleYearFilter').value.trim();
      const params = { limit: 200 };
      if (status) params.status = status;
      if (year) params.academic_year = year;
      const resp = await this.API('GET', 'finance/fees-bundle-list', null, params);
      const bundles = resp?.data?.bundles || resp?.data || [];
      this.state.items = Object.values(bundles.reduce((groups, bundle) => {
        const key = String(bundle.academic_year || bundle.academic_year_id || '');
        if (!groups[key]) groups[key] = { key, academic_year: bundle.academic_year, ids: [], terms: new Set(), types: new Set(), classes: 0, submitted_by_name: bundle.submitted_by_name, status: bundle.status || 'submitted' };
        groups[key].ids.push(Number(bundle.id));
        groups[key].terms.add(bundle.term_name || `Term ${bundle.term_id}`);
        groups[key].types.add(bundle.student_type_name || bundle.student_type_id);
        groups[key].classes = Math.max(groups[key].classes, Number(bundle.class_count || 0));
        return groups;
      }, {})).map(group => ({ ...group, terms: [...group.terms], types: [...group.types] }));
      this.render();
    } catch (e) {
      body.innerHTML = `<tr><td colspan="10" class="text-center py-3 text-danger">${this.esc(e.message || 'Load failed')}</td></tr>`;
    }
  },

  render() {
    const body = document.getElementById('bundleTableBody');
    if (!this.state.items.length) { body.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No fee structures found.</td></tr>'; return; }
    body.innerHTML = this.state.items.map(b => `
      <tr>
        <td class="small fw-semibold">${this.esc(b.academic_year)}</td>
        <td class="small">${this.esc(b.terms.join(', '))}</td>
        <td class="small">${this.esc(b.types.join(', '))}</td>
        <td class="small">${b.classes ? `${b.classes} classes` : 'All configured classes'}</td>
        <td class="small">${this.esc(b.submitted_by_name || '\u2014')}</td>
        <td class="small text-muted">${this.esc((b.submitted_at || '').substring(0, 10) || '\u2014')}</td>
        <td>${this.badgeStatus(b.status)}</td>
        <td class="text-end" style="white-space:nowrap">
          <button class="btn btn-sm btn-outline-primary rounded-pill px-2 me-1" onclick="feeApprovalsView('${this.esc(b.key)}')"><i class="bi bi-eye"></i> View matrix</button>
          ${b.status === 'submitted' ? `
            <button class="btn btn-sm btn-success rounded-pill px-2 me-1" onclick="feeApprovalsApprove('${this.esc(b.key)}')"><i class="bi bi-check-lg"></i> Approve</button>
            <button class="btn btn-sm btn-outline-danger rounded-pill px-2" onclick="feeApprovalsReject('${this.esc(b.key)}')"><i class="bi bi-x-lg"></i> Reject</button>
          ` : '<span class="text-muted small">\u2014</span>'}
        </td>
      </tr>`).join('');
  },

  async view(id) {
    const bundle = this.state.items.find(item => String(item.key) === String(id));
    const body = document.getElementById('approvalMatrixBody');
    if (!bundle || !body) return;
    document.getElementById('approvalMatrixMeta').textContent = `${bundle.academic_year || ''} · ${bundle.terms.join(', ')} · ${bundle.types.join(', ')}`;
    body.innerHTML = '<div class="text-center text-muted py-5"><div class="spinner-border spinner-border-sm"></div> Loading…</div>';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('approvalMatrixModal')).show();
    try {
      const model = await window.FeeStructureMatrix.load(bundle.academic_year);
      window.FeeStructureMatrix.render(body, model);
    } catch (e) {
      body.innerHTML = `<div class="alert alert-danger">${this.esc(e.message || 'Failed to load matrix')}</div>`;
    }
  },

  async approve(id) {
    const group = this.state.items.find(item => String(item.key) === String(id));
    if (!group) return;
    if (!(await window.confirmAction('Confirm Approval', 'Approve this complete fee structure? This will immediately generate fee obligations for all affected students.', { confirmText: 'Approve', danger: false }))) return;
    const notes = await window.promptAction('Input', 'Approval notes (optional):') || '';
    try {
      let students = 0, obligations = 0;
      for (const itemId of group.ids) {
        const resp = await this.API('POST', `finance/fees-bundle-approve/${itemId}`, { action: 'approve', notes });
        const d = resp?.data || {};
        students += Number(d.students_processed || 0); obligations += Number(d.obligations_created || 0);
      }
      this.notify(`Fee structure approved. Students billed: ${students}, obligations: ${obligations}`);
      this.loadData();
    } catch (e) { this.notify(e.message || 'Approval failed', 'danger'); }
  },

  async reject(id) {
    const group = this.state.items.find(item => String(item.key) === String(id));
    if (!group) return;
    const reason = await window.promptAction('Input', 'Rejection reason (required):');
    if (!reason) return;
    try {
      for (const itemId of group.ids) await this.API('POST', `finance/fees-bundle-approve/${itemId}`, { action: 'reject', notes: reason });
      this.notify('Bundle rejected. Accountant can revise and resubmit.');
      this.loadData();
    } catch (e) { this.notify(e.message || 'Reject failed', 'danger'); }
  },

  setupEventListeners() {
    document.getElementById('bundleStatusFilter')?.addEventListener('change', () => this.loadData());
    document.getElementById('bundleYearFilter')?.addEventListener('keyup', () => this.loadData());
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    this.setupEventListeners();
    this.loadData();
    window.APIRealtime?.register?.("fee_structure_approvals", this, ["finance"]);
  },
};

window.feeApprovalsController = feeApprovalsController;
window.feeApprovalsApprove    = (id) => feeApprovalsController.approve(id);
window.feeApprovalsReject     = (id) => feeApprovalsController.reject(id);
window.feeApprovalsView       = (id) => feeApprovalsController.view(id);

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => feeApprovalsController.init().catch(() => {}));
} else {
  feeApprovalsController.init().catch(() => {});
}
