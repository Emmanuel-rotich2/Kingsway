/**
 * manage_page_content.js — Page Content Controller (standalone page).
 * Edit website text content blocks and manage news categories.
 */
const pageContentController = {
  state: { blocks: [], cats: [] },

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
    const container = document.getElementById('contentBlocksList');
    const catContainer = document.getElementById('categoriesList');
    container.innerHTML = '<div class="spinner-border spinner-border-sm"></div>';
    try {
      const r = await this.API('GET', 'website/content');
      this.state.blocks = r?.data?.blocks || [];
      this.state.cats   = r?.data?.sections?.categories || [];
      this.render();
    } catch (e) {
      container.innerHTML = `<div class="text-danger small">${this.esc(e.message || 'Load failed')}</div>`;
    }
  },

  render() {
    const container = document.getElementById('contentBlocksList');
    const catContainer = document.getElementById('categoriesList');

    container.innerHTML = this.state.blocks.map(b => `
      <div class="ws-settings-row">
        <div><div class="ws-settings-key">${this.esc(b.content_key)}</div></div>
        <div>
          <textarea class="ws-content-input" data-key="${this.esc(b.content_key)}"
            style="width:100%;padding:6px 10px;border:1px solid #e2e8f0;border-radius:6px;font-size:.82rem;resize:vertical;min-height:60px"
            data-kw-onblur="pageContentSave('${this.esc(b.content_key)}',this.value)">${this.esc(b.content_value || '')}</textarea>
        </div>
      </div>`).join('') || '<div class="text-muted small">No content blocks found.</div>';

    catContainer.innerHTML = this.state.cats.map(c => `
      <div class="d-flex align-items-center gap-2 mb-2">
        <span class="ws-tag-chip" style="background:${this.esc(c.color)}22;color:${this.esc(c.color)};border-color:${this.esc(c.color)}44">${this.esc(c.name)}</span>
        <span class="flex-grow-1"></span>
        <button class="btn btn-sm btn-outline-danger rounded-pill" style="padding:1px 8px;font-size:.72rem" data-kw-onclick="pageContentDeleteCategory(${c.id},'${this.esc(c.name).replace(/'/g, '')}')">Remove</button>
      </div>`).join('') || '<div class="text-muted small">No categories found.</div>';
  },

  async save(key, value) {
    try {
      const r = await this.API('PUT', 'website/content', { key, value });
      if (r.status === 'success') this.notify(`"${key}" saved`);
      else this.notify(r.message, 'warning');
    } catch (e) { this.notify(e.message, 'danger'); }
  },

  async addCategory() {
    const name  = await window.promptAction('Input', 'New category name (e.g. "Events", "Science"):');
    if (!name?.trim()) return;
    const color = await window.promptAction('Input', 'Hex color (e.g. #1976d2):', '#198754');
    try {
      const r = await this.API('POST', 'website/categories', { name: name.trim(), color: color || '#198754' });
      if (r.status === 'success') { this.notify('Category added'); this.loadData(); }
      else this.notify(r.message, 'danger');
    } catch (e) { this.notify(e.message, 'danger'); }
  },

  async deleteCategory(id, name) {
    if (!(await window.confirmAction('Confirm', `Deactivate category "${name}"?`))) return;
    try {
      const r = await this.API('DELETE', `website/categories/${id}`);
      if (r.status === 'success') { this.notify('Category removed'); this.loadData(); }
      else this.notify(r.message, 'warning');
    } catch (e) { this.notify(e.message, 'danger'); }
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    this.loadData();
  },
};

window.pageContentController    = pageContentController;
window.pageContentSave          = (key, value) => pageContentController.save(key, value);
window.pageContentAddCategory   = () => pageContentController.addCategory();
window.pageContentDeleteCategory = (id, name) => pageContentController.deleteCategory(id, name);

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => pageContentController.init().catch(() => {}));
} else {
  pageContentController.init().catch(() => {});
}
