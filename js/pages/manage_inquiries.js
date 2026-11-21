/**
 * manage_inquiries.js — Contact Inquiries Controller (standalone page).
 * Review and triage messages from the public contact page.
 */
const inquiriesController = {
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

  esc(s) { return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); },
  fmtDate(d) { return d ? new Date(d).toLocaleDateString('en-KE', { day: '2-digit', month: 'short', year: 'numeric' }) : '\u2014'; },
  badgeStatus(s) {
    const colors = { new: 'primary', read: 'secondary', replied: 'success' };
    const c = colors[s] || 'secondary';
    return `<span class="badge bg-${c}">${s || '\u2014'}</span>`;
  },

  async loadData() {
    const body = document.getElementById('inquiriesTableBody');
    body.innerHTML = '<tr><td colspan="8" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    try {
      const r = await this.API('GET', 'website/inquiries');
      this.state.items = r?.data?.items || [];
      this.render();
    } catch (e) {
      body.innerHTML = `<tr><td colspan="8" class="text-center py-3 text-danger">${this.esc(e.message || 'Load failed')}</td></tr>`;
    }
  },

  render() {
    const body = document.getElementById('inquiriesTableBody');
    document.getElementById('statInquiries').textContent = this.state.items.length;
    document.getElementById('statNew').textContent = this.state.items.filter(q => q.status === 'new').length;
    const filter = document.getElementById('inquiryStatusFilter').value;
    const items = filter ? this.state.items.filter(q => q.status === filter) : this.state.items;
    if (!items.length) { body.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">No inquiries found.</td></tr>'; return; }
    body.innerHTML = items.map(q => `
      <tr>
        <td class="fw-semibold small">${this.esc(q.full_name)}</td>
        <td class="small text-muted">${this.esc(q.email)}</td>
        <td class="small text-muted">${this.esc(q.phone || '\u2014')}</td>
        <td class="small">${this.esc(q.subject || '\u2014')}</td>
        <td class="small text-muted" style="max-width:200px">${this.esc((q.message || '').substring(0, 80))}${q.message?.length > 80 ? '\u2026' : ''}</td>
        <td>${this.badgeStatus(q.status)}</td>
        <td class="small text-muted">${this.fmtDate(q.created_at)}</td>
        <td class="text-end">
          <button class="btn btn-sm btn-outline-primary me-1" data-kw-onclick="inquiriesReply(${q.id})" ${q.email ? '' : 'disabled'} title="Reply by email"><i class="bi bi-reply"></i></button>
          <select class="form-select form-select-sm" style="width:100px;display:inline-block" data-kw-onchange="inquiriesSetStatus(${q.id},this.value)">
            ${['new', 'read', 'replied'].map(s => `<option value="${s}" ${q.status === s ? 'selected' : ''}>${s}</option>`).join('')}
          </select>
        </td>
      </tr>`).join('');
  },

  async setStatus(id, status) {
    try {
      const r = await this.API('PUT', `website/inquiries/${id}`, { status });
      if (r.status === 'success') { this.notify('Status updated'); this.loadData(); }
      else this.notify(r.message, 'warning');
    } catch (e) { this.notify(e.message, 'danger'); }
  },

  async reply(id) {
    const reply = window.prompt('Reply to this inquiry:');
    if (!reply || !reply.trim()) return;
    try {
      const r = await this.API('PUT', `website/inquiries/${id}`, { reply: reply.trim() });
      if (r.status === 'success') { this.notify('Reply queued for email delivery'); this.loadData(); }
      else this.notify(r.message || 'Reply failed', 'warning');
    } catch (e) { this.notify(e.message || 'Reply failed', 'danger'); }
  },

  setupEventListeners() {
    document.getElementById('inquiryStatusFilter')?.addEventListener('change', () => this.render());
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    this.setupEventListeners();
    this.loadData();
  },
};

window.inquiriesController = inquiriesController;
window.inquiriesSetStatus  = (id, status) => inquiriesController.setStatus(id, status);
window.inquiriesReply = (id) => inquiriesController.reply(id);

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => inquiriesController.init().catch(() => {}));
} else {
  inquiriesController.init().catch(() => {});
}
