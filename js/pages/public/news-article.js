/* =============================================================================
   News Article — js/pages/public/news-article.js
   Renders a single news article, category sidebar, and related stories from
   /api/website/news/{id} (+ list for the sidebar) through window.PublicSite.
   news-article.php is a thin shell; article/SEO metadata is applied
   client-side after the fetch. View counting happens server-side via ?view=1.
   ============================================================================= */
(function () {
  'use strict';

  const PS = window.PublicSite;
  if (!PS) return;

  const S = (v) => PS.escapeHtml(v);
  const base = String(window.APP_BASE || '').replace(/\/+$/, '');
  const CATEGORY_COLORS = {
    Sports: '#198754', Academic: '#1976d2', Infrastructure: '#e91e63',
    Announcement: '#f9a825', Arts: '#9c27b0', Community: '#00695c',
  };
  const CATEGORIES = Object.keys(CATEGORY_COLORS);

  const qs = new URLSearchParams(location.search);
  const articleId = parseInt(qs.get('id') || '0', 10) || 0;

  function dateOnly(value) {
    if (!value) return '';
    const m = String(value).match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (m) {
      const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
      return parseInt(m[3], 10) + ' ' + (months[(parseInt(m[2], 10) || 1) - 1] || '') + ' ' + m[1];
    }
    return PS.formatDate(value, 'full');
  }

  function excerpt(text, len) {
    const div = document.createElement('div');
    div.innerHTML = String(text || '');
    const plain = div.textContent || '';
    return plain.length > len ? plain.slice(0, len) + '…' : plain;
  }

  function imgFor(item, w, h) {
    const col = CATEGORY_COLORS[item.category] || '#198754';
    if (item.image_url) return S(item.image_url);
    return `https://placehold.co/${w}x${h}/${col.replace('#', '')}/ffffff?text=${encodeURIComponent(item.category || 'News')}`;
  }

  const articlePage = {
    async init() {
      if (!articleId) {
        location.replace(`${base}/index.php?route=rcbd4a4c91922`);
        return;
      }
      let resp;
      try {
        // PublicSite folds {id} into the resource path; bumpView fires the
        // server-side view counter separately (never inflated by prefetch).
        resp = await PS.get('news', { id: articleId }, { tier: 'dynamic' });
        PS.bumpView('news', articleId);
      } catch (e) {
        resp = null;
      }
      const article = resp?.data || resp;
      if (!article || !article.id) {
        location.replace(`${base}/index.php?route=rcbd4a4c91922`);
        return;
      }

      document.title = `${article.title || 'Article'} · Kingsway Preparatory School`;
      this.renderArticle(article);
      await this.renderRelated(article);
      this.renderCategories();
      if (typeof window.revealOnScroll === 'function') window.revealOnScroll();
    },

    articleUrl(id) {
      return `${base}/index.php?route=rfcb132e4845b&id=${encodeURIComponent(id)}`;
    },

    renderArticle(a) {
      const col = CATEGORY_COLORS[a.category] || '#198754';
      const host = location.host;
      const url = encodeURIComponent(`https://${host}${this.articleUrl(a.id)}`);
      const titleEnc = encodeURIComponent(a.title || '');

      const crumb = document.getElementById('article-crumb');
      if (crumb) crumb.textContent = String(a.title || '').slice(0, 40) + (String(a.title || '').length > 40 ? '…' : '');
      const hTitle = document.getElementById('article-title-header');
      if (hTitle) hTitle.textContent = a.title || '';

      const main = document.getElementById('article-main');
      if (main) {
        main.innerHTML = `
          <img src="${imgFor(a, 1200, 600)}" alt="${S(a.title)}"
               class="w-100 rounded-4 mb-4"
               style="aspect-ratio:16/9;object-fit:cover;max-height:480px"
               onerror="this.src='https://placehold.co/800x450/198754/ffffff?text=Kingsway+News'">
          <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
            <span class="tag text-white" style="background:${col}">${S(a.category)}</span>
            <span class="text-muted small"><i class="bi bi-calendar3 me-1"></i>${dateOnly(a.created_at)}</span>
            <span class="text-muted small"><i class="bi bi-person-circle me-1"></i>${S(a.author || '')}</span>
            <span class="text-muted small"><i class="bi bi-eye me-1"></i>${Number(a.views || 0).toLocaleString()} views</span>
          </div>
          <h1 class="fw-bold mb-3" style="font-size:clamp(1.4rem,2.5vw,1.9rem)">${S(a.title)}</h1>
          ${a.excerpt ? `<p class="lead text-muted mb-4 border-start border-4 ps-3" style="border-color:${col} !important">${S(a.excerpt)}</p>` : ''}
          <div class="article-body">${a.content || ''}</div>
          <div class="mt-5 pt-4 border-top">
            <div class="d-flex align-items-center gap-3 flex-wrap">
              <span class="fw-semibold small">Share this story:</span>
              <a href="https://www.facebook.com/sharer/sharer.php?u=${url}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary rounded-pill px-3"><i class="bi bi-facebook me-1"></i>Facebook</a>
              <a href="https://twitter.com/intent/tweet?text=${titleEnc}&url=${url}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary rounded-pill px-3"><i class="bi bi-twitter-x me-1"></i>X (Twitter)</a>
              <a href="https://wa.me/?text=${titleEnc}%20-%20${url}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary rounded-pill px-3"><i class="bi bi-whatsapp me-1"></i>WhatsApp</a>
            </div>
          </div>
          <div class="mt-4">
            <a href="${base}/index.php?route=rcbd4a4c91922" class="btn-kw-outline"><i class="bi bi-arrow-left"></i>Back to All News</a>
          </div>`;
      }
    },

    async renderRelated(article) {
      const wrap = document.getElementById('article-related');
      if (!wrap) return;
      let resp;
      try {
        resp = await PS.get('news', { status: 'published', limit: 10 }, { tier: 'dynamic' });
      } catch (e) {
        return;
      }
      const items = Array.isArray(resp?.items) ? resp.items : (Array.isArray(resp?.data?.items) ? resp.data.items : []);
      let related = items.filter((n) => Number(n.id) !== articleId && n.category === article.category);
      if (related.length < 3) {
        for (const c of items) {
          if (Number(c.id) !== articleId && !related.some((r) => Number(r.id) === Number(c.id))) related.push(c);
          if (related.length >= 3) break;
        }
      }
      related = related.slice(0, 3);
      if (!related.length) {
        wrap.innerHTML = '';
        return;
      }
      wrap.innerHTML = `
        <div class="card-modern p-4">
          <h6 class="fw-bold mb-3"><i class="bi bi-newspaper text-success me-2"></i>Related Stories</h6>
          ${related.map((r) => `
            <a href="${this.articleUrl(r.id)}" class="d-flex gap-3 mb-3 text-decoration-none text-dark">
              <img src="${imgFor(r, 120, 80)}" alt="" style="width:80px;height:60px;object-fit:cover;border-radius:8px;flex-shrink:0"
                   onerror="this.src='https://placehold.co/80x60/198754/ffffff?text=News'">
              <div>
                <div class="small fw-semibold lh-sm mb-1">${S(excerpt(r.title, 65))}</div>
                <div class="text-muted" style="font-size:.75rem">${dateOnly(r.created_at)}</div>
              </div>
            </a>`).join('')}
        </div>`;
    },

    renderCategories() {
      const wrap = document.getElementById('article-categories');
      if (!wrap) return;
      wrap.innerHTML = CATEGORIES.map((c) => {
        const col = CATEGORY_COLORS[c];
        return `
          <a href="${base}/index.php?route=rcbd4a4c91922&cat=${encodeURIComponent(c)}"
             class="d-flex align-items-center gap-2 py-2 border-bottom text-decoration-none text-dark">
            <span class="rounded-2 px-2 py-1" style="background:${col}22;color:${col};font-size:.72rem;font-weight:700">${S(c)}</span>
          </a>`;
      }).join('');
    },
  };

  window.KingswayPublicNewsArticlePage = articlePage;
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => articlePage.init());
  } else {
    articlePage.init();
  }
})();
