/**
 * manage_articles.js — News Articles Controller (standalone page).
 * CRUD for public website news articles.
 */
const manageArticlesController = {
  state: { items: [], newsCats: [], editorRange: null },

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
    const colors = { published: 'success', draft: 'secondary', archived: 'dark' };
    const c = colors[s] || 'secondary';
    return `<span class="badge bg-${c}">${s || '\u2014'}</span>`;
  },
  catColor(cat) {
    const m = { Sports: '#198754', Academic: '#1976d2', Infrastructure: '#e91e63', Announcement: '#f9a825', Arts: '#9c27b0', Community: '#00695c' };
    return m[cat] || '#198754';
  },

  async loadStats() {
    try {
      const r = await this.API('GET', 'website/stats');
      if (r.status === 'success' && r.data) {
        document.getElementById('statTotal').textContent = r.data.news ?? '\u2014';
      }
      const items = this.state.items;
      const views = items.reduce((s, n) => s + (n.views || 0), 0);
      document.getElementById('statViews').textContent = views;
    } catch (_) {}
  },

  async loadData() {
    const body = document.getElementById('newsTableBody');
    body.innerHTML = '<tr><td colspan="7" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    try {
      if (this.state.newsCats.length === 0) {
        const cr = await this.API('GET', 'website/categories');
        this.state.newsCats = cr?.data?.items || [];
        const sel = document.getElementById('newsCategory');
        const filterSel = document.getElementById('newsCatFilter');
        this.state.newsCats.forEach(c => {
          if (!sel.querySelector(`option[value="${c.name}"]`)) sel.innerHTML += `<option value="${this.esc(c.name)}">${this.esc(c.name)}</option>`;
          filterSel.innerHTML += `<option value="${this.esc(c.name)}">${this.esc(c.name)}</option>`;
        });
      }
      const cat    = document.getElementById('newsCatFilter').value;
      const status = document.getElementById('newsStatusFilter').value;
      const search = document.getElementById('newsSearch').value;
      const r = await this.API('GET', 'website/news', { category: cat, status, search, limit: 100 });
      this.state.items = r?.data?.items || [];
      this.render();
      this.loadStats();
    } catch (e) {
      body.innerHTML = `<tr><td colspan="7" class="text-center py-3 text-danger">${this.esc(e.message || 'Load failed')}</td></tr>`;
    }
  },

  render() {
    const body = document.getElementById('newsTableBody');
    if (!this.state.items.length) { body.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No articles found.</td></tr>'; return; }
    body.innerHTML = this.state.items.map(n => `
      <tr>
        <td><img src="${this.esc(n.image_url || '')}" class="ws-img-thumb" data-kw-onerror="this.src='https://placehold.co/80x50/198754/fff?text=News'"></td>
        <td><div class="fw-semibold small" style="max-width:260px">${this.esc(n.title)}</div><div class="text-muted" style="font-size:.72rem">${this.esc(n.author || '')}</div></td>
        <td><span class="ws-tag-chip" style="background:${this.catColor(n.category)}22;color:${this.catColor(n.category)};border-color:${this.catColor(n.category)}44">${this.esc(n.category)}</span></td>
        <td>${this.badgeStatus(n.status)}</td>
        <td class="text-muted small">${n.views || 0}</td>
        <td class="text-muted small">${this.fmtDate(n.created_at)}</td>
        <td class="text-end">
          <button class="btn btn-sm btn-outline-secondary rounded-pill px-2 me-1" title="Edit" data-kw-onclick="articlesOpenModal(${n.id})"><i class="bi bi-pencil"></i></button>
          <button class="btn btn-sm btn-outline-danger rounded-pill px-2" title="Delete" data-kw-onclick="articlesDelete(${n.id},'${this.esc(n.title).replace(/'/g, '')}')"><i class="bi bi-trash"></i></button>
        </td>
      </tr>`).join('');
  },

  previewNewsImg(url) {
    const wrap = document.getElementById('newsImgPreviewWrap');
    const img  = document.getElementById('newsImgPreview');
    if (url) { img.src = url; wrap.style.display = ''; }
    else { wrap.style.display = 'none'; }
  },

  rememberCaret() {
    const selection = window.getSelection();
    const editor = document.getElementById('newsContent');
    if (selection?.rangeCount && editor.contains(selection.anchorNode)) {
      this.state.editorRange = selection.getRangeAt(0).cloneRange();
    }
  },

  insertHtml(html) {
    const editor = document.getElementById('newsContent');
    editor.focus();
    const selection = window.getSelection();
    selection.removeAllRanges();
    if (this.state.editorRange) selection.addRange(this.state.editorRange);
    else {
      const range = document.createRange();
      range.selectNodeContents(editor); range.collapse(false); selection.addRange(range);
    }
    document.execCommand('insertHTML', false, html);
    this.rememberCaret();
  },

  async uploadMedia(file) {
    if (!file) throw new Error('Choose a media file first.');
    const form = new FormData();
    form.append('file', file);
    form.append('context', 'public');
    form.append('description', 'Public website story media');
    form.append('tags', 'website,news');
    const uploaded = await window.API.system.uploadMedia(form);
    const mediaId = uploaded?.data?.data ?? uploaded?.data?.id ?? uploaded?.data;
    if (!mediaId) throw new Error('The media upload did not return an identifier.');
    const preview = await window.API.system.getMediaPreview(mediaId);
    const url = preview?.data?.data?.url ?? preview?.data?.url ?? preview?.data?.data ?? preview?.data;
    if (typeof url !== 'string' || !url) throw new Error('The uploaded media URL is unavailable.');
    return url;
  },

  async handleFileInsert(input, kind) {
    const files = [...(input.files || [])];
    if (!files.length) return;
    this.notify('Uploading media…', 'info');
    try {
      const urls = [];
      for (const file of files) urls.push(await this.uploadMedia(file));
      if (kind === 'cover') {
        document.getElementById('newsImageUrl').value = urls[0];
        this.previewNewsImg(urls[0]);
      } else if (kind === 'image') {
        this.insertHtml(`<figure><img src="${this.esc(urls[0])}" alt=""><figcaption>Click here to add an image caption</figcaption></figure><p><br></p>`);
      } else if (kind === 'video') {
        this.insertHtml(`<figure><video controls preload="metadata" src="${this.esc(urls[0])}"></video><figcaption>Click here to add a video caption</figcaption></figure><p><br></p>`);
      } else {
        this.insertHtml(`<div class="story-slider" data-story-slider="true">${urls.map(url => `<img src="${this.esc(url)}" alt="">`).join('')}</div><p><br></p>`);
      }
      this.notify('Media inserted successfully.');
    } catch (error) { this.notify(error.message || 'Media upload failed.', 'danger'); }
    finally { input.value = ''; }
  },

  openLinkModal() {
    if (!document.getElementById('articleLinkModal')) {
      document.body.insertAdjacentHTML('beforeend', `
        <div class="modal fade" id="articleLinkModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content">
          <div class="modal-header"><h5 class="modal-title">Insert link</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
          <div class="modal-body"><label class="form-label" for="articleLinkUrl">Web address</label><input id="articleLinkUrl" class="form-control" placeholder="https://..."></div>
          <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" id="articleLinkInsertBtn">Insert</button></div>
        </div></div></div>`);
      document.getElementById('articleLinkInsertBtn').addEventListener('click', () => {
        const url = document.getElementById('articleLinkUrl').value.trim();
        if (!/^https?:\/\//i.test(url)) return this.notify('Enter a complete http:// or https:// address.', 'warning');
        const editor = document.getElementById('newsContent');
        editor.focus();
        document.execCommand('createLink', false, url);
        bootstrap.Modal.getInstance(document.getElementById('articleLinkModal'))?.hide();
      });
    }
    document.getElementById('articleLinkUrl').value = 'https://';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('articleLinkModal')).show();
  },

  previewStory() {
    const title = this.esc(document.getElementById('newsTitle').value || 'Untitled story');
    const cover = this.esc(document.getElementById('newsImageUrl').value);
    const content = document.getElementById('newsContent').innerHTML;
    let modal = document.getElementById('storyPreviewModal');
    if (!modal) {
      modal = document.createElement('div'); modal.id = 'storyPreviewModal'; modal.className = 'modal fade';
      modal.innerHTML = '<div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Public story preview</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body" id="storyPreviewBody"></div></div></div>';
      document.body.appendChild(modal);
    }
    document.getElementById('storyPreviewBody').innerHTML = `<article class="mx-auto" style="max-width:850px">${cover ? `<img src="${cover}" class="w-100 rounded-4 mb-4" style="max-height:450px;object-fit:cover">` : ''}<h1 class="fw-bold">${title}</h1><div class="article-body mt-4">${content}</div></article>`;
    bootstrap.Modal.getOrCreateInstance(modal).show();
  },

  async openModal(id = null) {
    document.getElementById('newsEditId').value = id || '';
    document.getElementById('wsNewsModalTitle').textContent = id ? 'Edit Article' : 'New Article';
    ['newsTitle', 'newsExcerpt', 'newsAuthor', 'newsImageUrl'].forEach(f => {
      const el = document.getElementById(f); if (el) el.value = '';
    });
    document.getElementById('newsContent').innerHTML = '';
    document.getElementById('newsImgPreviewWrap').style.display = 'none';
    document.getElementById('newsStatus').value   = 'published';
    document.getElementById('newsCategory').value = 'Announcement';
    if (id) {
      const r = await this.API('GET', `website/news/${id}`);
      const a = r?.data;
      if (a) {
        document.getElementById('newsTitle').value     = a.title || '';
        document.getElementById('newsExcerpt').value   = a.excerpt || '';
        document.getElementById('newsContent').innerHTML = a.content || '';
        document.getElementById('newsAuthor').value    = a.author || '';
        document.getElementById('newsImageUrl').value  = a.image_url || '';
        document.getElementById('newsStatus').value    = a.status || 'published';
        document.getElementById('newsCategory').value  = a.category || 'Announcement';
        this.previewNewsImg(a.image_url);
      }
    }
    bootstrap.Modal.getOrCreateInstance(document.getElementById('wsNewsModal')).show();
  },

  async save() {
    const id = document.getElementById('newsEditId').value;
    const payload = {
      title:     document.getElementById('newsTitle').value.trim(),
      excerpt:   document.getElementById('newsExcerpt').value.trim(),
      content:   document.getElementById('newsContent').innerHTML.trim(),
      author:    document.getElementById('newsAuthor').value.trim(),
      image_url: document.getElementById('newsImageUrl').value.trim(),
      category:  document.getElementById('newsCategory').value,
      status:    document.getElementById('newsStatus').value,
    };
    if (!payload.title || !payload.content) return this.notify('Title and content are required.', 'warning');
    const btn = document.getElementById('newsSubmitBtn');
    btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving\u2026';
    try {
      const r = id
        ? await this.API('PUT',  `website/news/${id}`, payload)
        : await this.API('POST', 'website/news',        payload);
      if (r.status === 'success') {
        this.notify(id ? 'Article updated' : 'Article published');
        bootstrap.Modal.getInstance(document.getElementById('wsNewsModal')).hide();
        this.loadData();
      } else { this.notify(r.message || 'Save failed', 'danger'); }
    } catch (e) { this.notify(e.message || 'Error', 'danger'); }
    finally { btn.disabled = false; btn.innerHTML = '<i class="bi bi-send-fill me-1"></i>Publish Article'; }
  },

  async delete(id, title) {
    if (!(await window.confirmAction('Confirm Deletion', `Delete "${title}"? This cannot be undone.`, { confirmText: 'Delete', danger: true }))) return;
    try {
      await this.API('DELETE', `website/news/${id}`);
      this.notify('Article deleted'); this.loadData();
    } catch (e) { this.notify(e.message, 'danger'); }
  },

  setupEventListeners() {
    ['newsSearch', 'newsCatFilter', 'newsStatusFilter'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.addEventListener('change', () => this.loadData());
    });
    const searchEl = document.getElementById('newsSearch');
    if (searchEl) searchEl.addEventListener('keyup', () => this.loadData());
    const editor = document.getElementById('newsContent');
    ['keyup','mouseup','input','focus'].forEach(event => editor?.addEventListener(event, () => this.rememberCaret()));
    document.querySelectorAll('.story-toolbar [data-command]').forEach(button => button.addEventListener('click', () => {
      editor.focus(); document.execCommand(button.dataset.command, false); this.rememberCaret();
    }));
    document.querySelectorAll('.story-toolbar [data-block]').forEach(button => button.addEventListener('click', () => {
      editor.focus(); document.execCommand('formatBlock', false, button.dataset.block); this.rememberCaret();
    }));
    document.querySelectorAll('.story-toolbar [data-insert]').forEach(button => button.addEventListener('mousedown', () => this.rememberCaret()));
    document.querySelectorAll('.story-toolbar [data-insert]').forEach(button => button.addEventListener('click', () => {
      const type = button.dataset.insert;
      if (type === 'link') {
        this.openLinkModal();
        return;
      }
      document.getElementById(type === 'slider' ? 'storySliderFiles' : type === 'video' ? 'storyVideoFile' : 'storyImageFile').click();
    }));
    document.getElementById('newsCoverFile')?.addEventListener('change', event => this.handleFileInsert(event.target, 'cover'));
    document.getElementById('storyImageFile')?.addEventListener('change', event => this.handleFileInsert(event.target, 'image'));
    document.getElementById('storySliderFiles')?.addEventListener('change', event => this.handleFileInsert(event.target, 'slider'));
    document.getElementById('storyVideoFile')?.addEventListener('change', event => this.handleFileInsert(event.target, 'video'));
    document.getElementById('newsPreviewBtn')?.addEventListener('click', () => this.previewStory());
  },

  mountAiDrafts() {
    if (this._aiDraftsMounted) return;
    this._aiDraftsMounted = true;
    window.AiInlineDraft?.mount?.({button:'#newsDraftTitleBtn', host:'#newsAiHostTitle', route:window.REQUESTED_ROUTE||'manage_articles', module:'website', liveTarget:'#newsTitle', label:'Draft title', prompt:()=>`Draft a clear news/article headline for Kingsway Preparatory School. Category: ${document.getElementById('newsCategory')?.value||''}. Excerpt: ${document.getElementById('newsExcerpt')?.value||''}. Return only the title.`});
    window.AiInlineDraft?.mount?.({button:'#newsDraftExcerptBtn', host:'#newsAiHostExcerpt', route:window.REQUESTED_ROUTE||'manage_articles', module:'website', liveTarget:'#newsExcerpt', label:'Draft excerpt', prompt:()=>`Draft a short 1-2 sentence excerpt for this Kingsway Preparatory School news article. Title: ${document.getElementById('newsTitle')?.value||''}. Category: ${document.getElementById('newsCategory')?.value||''}. Return only the excerpt.`});
    window.AiInlineDraft?.mount?.({button:'#newsDraftBodyBtn', host:'#newsAiHostBody', route:window.REQUESTED_ROUTE||'manage_articles', module:'website', liveTarget:'#newsContent', label:'Draft article body', prompt:()=>`Draft a professional news article body for Kingsway Preparatory School. Title: ${document.getElementById('newsTitle')?.value||''}. Excerpt: ${document.getElementById('newsExcerpt')?.value||''}. Category: ${document.getElementById('newsCategory')?.value||''}. Write in warm, clear HTML paragraphs. Return only the body.`});
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    this.setupEventListeners();
    this.mountAiDrafts();
    this.loadData();
  },
};

window.articlesOpenModal = (id) => manageArticlesController.openModal(id);
window.articlesSave      = () => manageArticlesController.save();
window.articlesDelete    = (id, title) => manageArticlesController.delete(id, title);

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => manageArticlesController.init().catch(() => {}));
} else {
  manageArticlesController.init().catch(() => {});
}

window.manageArticlesController = manageArticlesController;
