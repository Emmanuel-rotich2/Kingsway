/* =============================================================================
   Public School Gallery — js/pages/public/gallery.js
   Fetches all gallery items (images + videos) from /api/website/gallery and
   renders them in a responsive grid with a lightbox modal.
   ============================================================================= */
(function () {
  'use strict';

  const PS = window.PublicSite;
  if (!PS) return;

  const S = (v) => PS.escapeHtml(v);
  const base = String(window.APP_BASE || '').replace(/\/+$/, '');

  const gallery = {
    initialized: false,

    async init() {
      if (this.initialized) return;
      this.initialized = true;
      try {
        const data = await PS.get('gallery', { active: '1' }, { tier: 'reference' });
        this.render(data);
      } catch (err) {
        const grid = document.getElementById('public-gallery-grid');
        if (grid) {
          grid.innerHTML = '<div class="col-12 text-danger small p-4">Gallery could not be loaded. Please try again later.</div>';
        }
        if (window.KINGSWAY_DEBUG) console.warn('[gallery] render failed:', err);
      }
    },

    render(data) {
      const grid = document.getElementById('public-gallery-grid');
      if (!grid) return;
      const items = PS.items(data) || [];
      if (!items.length) {
        grid.innerHTML = '<div class="col-12 text-muted small p-4">No gallery items yet. Check back soon.</div>';
        return;
      }
      grid.innerHTML = items.map((g) => {
        const isVideo = g.media_type === 'video';
        const src = isVideo ? S(g.video_url || g.image_url) : S(PS.mediaUrl(g.image_url));
        const poster = g.image_url ? S(PS.mediaUrl(g.image_url)) : '';
        const mediaHtml = isVideo
          ? `<video src="${src}" poster="${poster}" class="gallery-media" style="width:100%;height:100%;object-fit:cover;background:#000" muted playsinline onclick="galleryViewMedia('${src}', 'video', '${this.escAttr(g.caption || '')}')"></video>`
          : `<img src="${src}" alt="${S(g.caption || 'Kingsway School')}" class="gallery-media" loading="lazy" onerror="this.src='https://images.unsplash.com/photo-1503676260728-1c00da094a0b?w=800&q=70'" onclick="galleryViewMedia('${src}', 'image', '${this.escAttr(g.caption || '')}')">`;
        return `
          <div class="col-lg-3 col-md-4 col-sm-6">
            <div class="gallery-card">
              ${isVideo ? '<span class="gallery-video-badge"><i class="bi bi-play-fill"></i></span>' : ''}
              ${mediaHtml}
              <div class="gallery-caption p-3">
                <div class="gallery-caption-text">${S(g.caption || '')}</div>
                <div class="gallery-category small text-muted">${S(g.category || 'General')}</div>
              </div>
            </div>
          </div>`;
      }).join('');
      if (window.PublicUI && window.PublicUI.observeReveals) window.PublicUI.observeReveals(grid);
    },

    escAttr(s) {
      return String(s || '').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    },
  };

  window.galleryViewMedia = (src, type, caption) => {
    const modalEl = document.getElementById('galleryLightbox');
    const imgWrap = document.getElementById('lightboxImgWrap');
    const videoWrap = document.getElementById('lightboxVideoWrap');
    const imgEl = document.getElementById('lightboxImg');
    const videoEl = document.getElementById('lightboxVideo');
    const capEl = document.getElementById('lightboxCaption');
    capEl.textContent = caption || '';
    if (type === 'video') {
      imgWrap.style.display = 'none';
      videoWrap.style.display = 'block';
      videoEl.src = src;
      videoEl.load();
      videoEl.play().catch(() => {});
    } else {
      videoWrap.style.display = 'none';
      imgWrap.style.display = 'block';
      imgEl.src = src;
    }
    new bootstrap.Modal(modalEl).show();
  };

  gallery.init();
})();
