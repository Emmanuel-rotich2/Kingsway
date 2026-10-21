/**
 * manage_school_gallery.js — School Gallery Controller (standalone page).
 * Manage images and videos displayed in the public website gallery.
 */
const manageSchoolGalleryController = {
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
    const grid = document.getElementById('galleryGrid');
    grid.innerHTML = '<div class="text-muted small p-3"><div class="spinner-border spinner-border-sm me-2"></div>Loading\u2026</div>';
    try {
      const r = await this.API('GET', 'website/gallery');
      this.state.items = r?.data?.items || [];
      this.render();
    } catch (e) {
      grid.innerHTML = `<div class="text-danger small p-3">${this.esc(e.message || 'Load failed')}</div>`;
    }
  },

  render() {
    const grid = document.getElementById('galleryGrid');
    document.getElementById('statImages').textContent = this.state.items.length;
    if (!this.state.items.length) { grid.innerHTML = '<div class="text-muted small p-3">No media in gallery yet. Add one above.</div>'; return; }
    grid.innerHTML = this.state.items.map(g => {
      const isVideo = g.media_type === 'video';
      const src = isVideo ? (g.video_url || g.image_url) : g.image_url;
      const badge = isVideo ? '<span class="badge bg-info position-absolute top-0 start-0 m-2"><i class="bi bi-play-fill me-1"></i>Video</span>' : '';
      return `
        <div class="ws-gallery-item">
          ${badge}
          ${isVideo
            ? `<video src="${this.esc(src)}" poster="${this.esc(g.image_url || '')}" onerror="this.style.display='none'" style="width:100%;aspect-ratio:16/9;object-fit:cover;display:block;background:#000" muted playsinline onclick="galleryViewMedia('${this.esc(g.video_url || g.image_url)}', '${g.media_type}')"></video>`
            : `<img src="${this.esc(g.image_url)}" alt="${this.esc(g.caption || '')}"
                 onerror="this.src='https://placehold.co/300x200/198754/fff?text=Image'"
                 onclick="galleryViewMedia('${this.esc(g.image_url)}', 'image')" style="cursor:pointer">`}
          <div class="ws-overlay">
            <button class="btn btn-sm btn-danger rounded-circle" style="width:32px;height:32px;padding:0" onclick="galleryDelete(${g.id})" title="Remove"><i class="bi bi-trash-fill"></i></button>
          </div>
          <div class="caption">${this.esc(g.caption || '\u2014')} <span class="text-muted">(${this.esc(g.category || '')})</span></div>
        </div>`;
    }).join('');
  },

  viewMedia(url, type) {
    const modalEl = document.getElementById('wsImgViewModal');
    const imgEl = document.getElementById('wsImgViewSrc');
    const videoEl = document.getElementById('wsVideoViewSrc');
    const imgWrap = document.getElementById('wsImgViewWrap');
    const videoWrap = document.getElementById('wsVideoViewWrap');
    if (type === 'video') {
      imgWrap.style.display = 'none';
      videoWrap.style.display = 'block';
      videoEl.src = url;
      videoEl.load();
    } else {
      videoWrap.style.display = 'none';
      imgWrap.style.display = 'block';
      imgEl.src = url;
    }
    new bootstrap.Modal(modalEl).show();
  },

  openModal() {
    document.getElementById('galleryUrl').value      = '';
    document.getElementById('galleryCaption').value  = '';
    document.getElementById('galleryCategory').value = 'General';
    document.getElementById('galleryMediaType').value = 'image';
    galleryMediaTypeChanged();
    new bootstrap.Modal(document.getElementById('wsGalleryModal')).show();
  },

  previewImg(url) {
    const wrap = document.getElementById('galleryImgPreviewWrap');
    const img  = document.getElementById('galleryImgPreview');
    const videoWrap = document.getElementById('galleryVideoPreviewWrap');
    const videoEl = document.getElementById('galleryVideoPreview');
    const type = document.getElementById('galleryMediaType').value;
    if (type === 'video') {
      wrap.style.display = 'none';
      videoWrap.style.display = url ? '' : 'none';
      if (url) { videoEl.src = url; videoEl.load(); }
    } else {
      videoWrap.style.display = 'none';
      if (url) { img.src = url; wrap.style.display = ''; }
      else { wrap.style.display = 'none'; }
    }
  },

  async save() {
    const mediaType = document.getElementById('galleryMediaType').value;
    const url = document.getElementById('galleryUrl').value.trim();
    if (!url) return this.notify('Media URL is required.', 'warning');
    try {
      const r = await this.API('POST', 'website/gallery', {
        image_url: url,
        media_type: mediaType,
        video_url: mediaType === 'video' ? url : null,
        caption:   document.getElementById('galleryCaption').value.trim(),
        category:  document.getElementById('galleryCategory').value,
      });
      if (r.status === 'success') {
        this.notify('Media added to gallery');
        bootstrap.Modal.getInstance(document.getElementById('wsGalleryModal')).hide();
        this.loadData();
      } else this.notify(r.message, 'danger');
    } catch (e) { this.notify(e.message, 'danger'); }
  },

  async delete(id) {
    if (!(await window.confirmAction('Confirm Deletion', 'Remove this media from the gallery?', { confirmText: 'Delete', danger: true }))) return;
    try { await this.API('DELETE', `website/gallery/${id}`); this.notify('Media removed'); this.loadData(); }
    catch (e) { this.notify(e.message, 'danger'); }
  },

  setupEventListeners() {
    const urlEl = document.getElementById('galleryUrl');
    if (urlEl) urlEl.addEventListener('input', () => this.previewImg(urlEl.value));
    const typeEl = document.getElementById('galleryMediaType');
    if (typeEl) typeEl.addEventListener('change', () => this.previewImg(document.getElementById('galleryUrl').value));
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    this.setupEventListeners();
    this.loadData();
  },
};

window.galleryOpenModal = () => manageSchoolGalleryController.openModal();
window.gallerySave      = () => manageSchoolGalleryController.save();
window.galleryDelete    = (id) => manageSchoolGalleryController.delete(id);
window.galleryViewMedia = (url, type) => manageSchoolGalleryController.viewMedia(url, type);

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => manageSchoolGalleryController.init().catch(() => {}));
} else {
  manageSchoolGalleryController.init().catch(() => {});
}

window.manageSchoolGalleryController = manageSchoolGalleryController;
