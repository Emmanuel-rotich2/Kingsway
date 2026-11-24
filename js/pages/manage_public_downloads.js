/**
 * manage_public_downloads.js — Public Downloads Controller (standalone page).
 * CRUD for downloadable school documents on the public website.
 */
const managePublicDownloadsController = {
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

  async loadData() {
    const body = document.getElementById('downloadsTableBody');
    body.innerHTML = '<tr><td colspan="6" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    try {
      const r = await this.API('GET', 'website/downloads');
      this.state.items = r?.data?.items || [];
      this.render();
    } catch (e) {
      body.innerHTML = `<tr><td colspan="6" class="text-center py-3 text-danger">${this.esc(e.message || 'Load failed')}</td></tr>`;
    }
  },

  render() {
    const body = document.getElementById('downloadsTableBody');
    document.getElementById('statDownloads').textContent = this.state.items.filter(d => d.is_active).length;
    if (!this.state.items.length) { body.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No downloads configured.</td></tr>'; return; }
    body.innerHTML = this.state.items.map(d => `
      <tr>
        <td><i class="bi ${this.esc(d.icon || 'bi-file-earmark-pdf')} me-2" style="color:${this.esc(d.color || '#198754')}"></i>${this.esc(d.title)}</td>
        <td class="text-muted small">${this.esc(d.category)}</td>
        <td><span class="badge bg-secondary">${this.esc(d.file_type)}</span></td>
        <td class="text-muted small">${this.esc(d.file_size || '\u2014')}</td>
        <td>${d.is_active ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Hidden</span>'}</td>
        <td class="text-end">
          <button class="btn btn-sm btn-outline-secondary rounded-pill px-2 me-1" data-kw-onclick="downloadsOpenModal(${d.id})"><i class="bi bi-pencil"></i></button>
          <button class="btn btn-sm btn-outline-danger rounded-pill px-2" data-kw-onclick="downloadsHide(${d.id})"><i class="bi bi-eye-slash"></i></button>
        </td>
      </tr>`).join('');
  },

  async openModal(id = null) {
    document.getElementById('dlEditId').value = id || '';
    document.getElementById('wsDownloadModalTitle').textContent = id ? 'Edit Download' : 'Add Download';
    ['dlTitle', 'dlDesc', 'dlSize'].forEach(f => { const el = document.getElementById(f); if (el) el.value = ''; });
    document.getElementById('dlCategory').value = 'General';
    document.getElementById('dlType').value = 'PDF';
    const fileInput = document.getElementById('dlFile'); if (fileInput) fileInput.value = '';
    if (id) {
      const r = await this.API('GET', 'website/downloads');
      const item = (r?.data?.items || []).find(d => d.id == id);
      if (item) {
        document.getElementById('dlTitle').value    = item.title || '';
        document.getElementById('dlDesc').value     = item.description || '';
        document.getElementById('dlSize').value     = item.file_size || '';
        document.getElementById('dlCategory').value = item.category || 'General';
        document.getElementById('dlType').value     = item.file_type || 'PDF';
      }
    }
    bootstrap.Modal.getOrCreateInstance(document.getElementById('wsDownloadModal')).show();
  },

  async save() {
    const id = document.getElementById('dlEditId').value;
    const title = document.getElementById('dlTitle').value.trim();
    if (!title) return this.notify('Title is required.', 'warning');
    const fileInput = document.getElementById('dlFile');
    const hasFile = fileInput && fileInput.files && fileInput.files.length > 0;
    if (!id && !hasFile) return this.notify('Choose a school document to upload.', 'warning');

    const fd = new FormData();
    fd.append('title', title);
    fd.append('description', document.getElementById('dlDesc').value.trim());
    fd.append('category', document.getElementById('dlCategory').value);
    fd.append('file_type', document.getElementById('dlType').value);
    if (document.getElementById('dlSize').value.trim()) fd.append('file_size', document.getElementById('dlSize').value.trim());
    if (hasFile) fd.append('file', fileInput.files[0]);
    try {
      const r = id ? await this.API('PUT', `website/downloads/${id}`, fd, {}, { isFile: true }) : await this.API('POST', 'website/downloads', fd, {}, { isFile: true });
      if (r.status === 'success') {
        this.notify(id ? 'Download updated' : 'Download added');
        bootstrap.Modal.getInstance(document.getElementById('wsDownloadModal')).hide();
        this.loadData();
      } else this.notify(r.message, 'danger');
    } catch (e) { this.notify(e.message, 'danger'); }
  },

  async hide(id) {
    if (!(await window.confirmAction('Confirm', 'Hide this download from the public site?'))) return;
    try { await this.API('DELETE', `website/downloads/${id}`); this.notify('Download hidden'); this.loadData(); }
    catch (e) { this.notify(e.message, 'danger'); }
  },

  mountAiDrafts() {
    if (this._aiDraftsMounted) return;
    this._aiDraftsMounted = true;
    window.AiInlineDraft?.mount?.({
      button:'#downloadDraftDescBtn', host:'#downloadAiHostDesc', route:window.REQUESTED_ROUTE||'manage_public_downloads', module:'website',
      liveTarget:'#dlDesc', label:'Draft download description',
      prompt:()=>`Draft a clear, helpful description for a public school download on the Kingsway Preparatory School website. Title: ${document.getElementById('dlTitle')?.value||''}. Category: ${document.getElementById('dlCategory')?.value||''}. Return only the description.`
    });
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    this.mountAiDrafts();
    this.loadData();
  },
};

window.downloadsOpenModal = (id) => managePublicDownloadsController.openModal(id);
window.downloadsSave      = () => managePublicDownloadsController.save();
window.downloadsHide      = (id) => managePublicDownloadsController.hide(id);

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => managePublicDownloadsController.init().catch(() => {}));
} else {
  managePublicDownloadsController.init().catch(() => {});
}

window.managePublicDownloadsController = managePublicDownloadsController;
