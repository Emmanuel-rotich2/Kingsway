/* =============================================================================
   News & Blog — js/pages/public/news.js
   Renders the category filter, featured post, article grid, and pagination
   from /api/website/news through window.PublicSite. news.php is a thin HTML
   shell with no server-side data access (the login.php pattern: template +
   JS controller fetching via api.js).
   ============================================================================= */
(function () {
  'use strict';

  const PS = window.PublicSite;
  if (!PS) return;

  const S = (v) => PS.escapeHtml(v);
  const base = String(window.APP_BASE || '').replace(/\/+$/, '');
  const PER_PAGE = 9;

  const CATEGORY_COLORS = {
    Sports: '#198754', Academic: '#1976d2', Infrastructure: '#e91e63',
    Announcement: '#f9a825', Arts: '#9c27b0', Community: '#00695c',
  };
  const CATEGORIES = Object.keys(CATEGORY_COLORS);

  const qs = new URLSearchParams(location.search);
  const state = { page: Math.max(1, parseInt(qs.get('page') || '1', 10) || 1), category: qs.get('cat') || '' };

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

  const newsPage = {
    initialized: false,

    async init() {
      if (this.initialized) return;
      this.initialized = true;
      await this.load();
      window.addEventListener('popstate', () => {
        const q = new URLSearchParams(location.search);
        state.page = Math.max(1, parseInt(q.get('page') || '1', 10) || 1);
        state.category = q.get('cat') || '';
        this.load();
      });
    },

    async load() {
      const params = {
        status: 'published',
        limit: PER_PAGE,
        offset: (state.page - 1) * PER_PAGE,
      };
      if (state.category) params.category = state.category;
      let data;
      try {
        data = await PS.get('news', params, { tier: 'dynamic' });
      } catch (e) {
        console.error('[news] load failed', e);
        data = { items: [], total: 0 };
      }
      const items = Array.isArray(data?.items) ? data.items : (Array.isArray(data?.data?.items) ? data.data.items : []);
      const total = Number(data?.total ?? data?.data?.total ?? items.length);
      this.renderCategories();
      this.renderFeatured(items);
      this.renderGrid(items);
      this.renderPagination(total);
      if (typeof window.revealOnScroll === 'function') window.revealOnScroll();
    },

    pushUrl() {
      const q = new URLSearchParams();
      if (state.category) q.set('cat', state.category);
      if (state.page > 1) q.set('page', String(state.page));
      const url = location.pathname + (q.toString() ? '?' + q.toString() : '');
      window.history.pushState({}, '', url);
    },

    setPage(p) {
      state.page = p;
      this.pushUrl();
      this.load();
      window.scrollTo({ top: 0, behavior: 'smooth' });
    },

    setCategory(cat) {
      state.category = cat;
      state.page = 1;
      this.pushUrl();
      this.load();
    },

    renderCategories() {
      const wrap = document.getElementById('news-categories');
      if (!wrap) return;
      const activeColor = 'bg-success text-white';
      const idleColor = 'bg-white border text-muted';
      wrap.innerHTML = '';
      const addBtn = (label, cat) => {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'tag border-0 ' + (state.category === cat ? activeColor : idleColor);
        b.textContent = label;
        b.addEventListener('click', () => this.setCategory(cat));
        wrap.appendChild(b);
      };
      addBtn('All', '');
      CATEGORIES.forEach((c) => addBtn(c, c));
    },

    imgFor(item, w, h, color) {
      if (item.image_url) return S(item.image_url);
      const c = (color || '#198754').replace('#', '');
      return `https://placehold.co/${w}x${h}/${c}/ffffff?text=${encodeURIComponent(item.category || 'News')}`;
    },

    articleUrl(id) {
      return `${base}/index.php?route=rfcb132e4845b&id=${encodeURIComponent(id)}`;
    },

    renderFeatured(items) {
      const wrap = document.getElementById('news-featured');
      if (!wrap) return;
      if (state.page !== 1 || state.category || !items.length) {
        wrap.innerHTML = '';
        return;
      }
      const f = items[0];
      const col = CATEGORY_COLORS[f.category] || '#198754';
      wrap.innerHTML = `
        <a href="${this.articleUrl(f.id)}" class="text-decoration-none">
          <div class="card-modern mb-5 reveal" style="cursor:pointer">
            <div class="row g-0">
              <div class="col-lg-6">
                <div class="card-img-wrap" style="aspect-ratio:16/9;height:100%;min-height:280px">
                  <img src="${this.imgFor(f, 800, 500, col)}" alt="${S(f.title)}"
                       style="height:100%;object-fit:cover"
                       onerror="this.src='https://placehold.co/800x500/198754/ffffff?text=Kingsway+News'">
                </div>
              </div>
              <div class="col-lg-6 p-4 p-lg-5 d-flex flex-column justify-content-center">
                <span class="card-category mb-2" style="background:${col}">${S(f.category)}</span>
                <h2 class="fw-bold mb-3" style="font-size:1.5rem">${S(f.title)}</h2>
                <p class="text-muted mb-3">${S(excerpt(f.content || f.excerpt, 200))}</p>
                <div class="d-flex align-items-center gap-3 text-muted small mt-auto">
                  <span><i class="bi bi-calendar3 me-1"></i>${dateOnly(f.created_at)}</span>
                  <span><i class="bi bi-person-circle me-1"></i>${S(f.author || '')}</span>
                  <span><i class="bi bi-eye me-1"></i>${Number(f.views || 0).toLocaleString()} views</span>
                </div>
              </div>
            </div>
          </div>
        </a>`;
    },

    renderGrid(items) {
      const wrap = document.getElementById('news-grid');
      if (!wrap) return;
      const grid = (state.page === 1 && !state.category) ? items.slice(1) : items;
      if (!grid.length) {
        wrap.innerHTML = `
          <div class="text-center py-5">
            <i class="bi bi-newspaper fs-1 text-muted d-block mb-3"></i>
            <p class="text-muted">No articles in this category yet.</p>
            <a href="${base}/index.php?route=rcbd4a4c91922" class="btn-kw-outline mt-2">View All News</a>
          </div>`;
        return;
      }
      const cards = grid.map((n, i) => {
        const col = CATEGORY_COLORS[n.category] || '#198754';
        return `
        <div class="col-lg-4 col-md-6">
          <a href="${this.articleUrl(n.id)}" class="text-decoration-none">
            <div class="card-modern h-100 reveal delay-${(i % 3) + 1}" style="cursor:pointer">
              <div class="card-img-wrap">
                <img src="${this.imgFor(n, 600, 380, col)}" alt="${S(n.title)}"
                     style="object-fit:cover"
                     onerror="this.src='https://placehold.co/600x380/198754/ffffff?text=News'">
              </div>
              <div class="p-4 d-flex flex-column h-100">
                <div class="d-flex align-items-center justify-content-between mb-2">
                  <span class="card-category" style="background:${col}">${S(n.category)}</span>
                  <span class="card-date"><i class="bi bi-calendar3"></i>${dateOnly(n.created_at)}</span>
                </div>
                <div class="card-title fw-bold fs-6 mb-2">${S(n.title)}</div>
                <p class="card-excerpt flex-grow-1">${S(excerpt(n.excerpt || n.content, 120))}</p>
                <div class="d-flex align-items-center justify-content-between mt-3 pt-3 border-top">
                  <span class="text-muted small"><i class="bi bi-person-circle me-1"></i>${S(n.author || '')}</span>
                  <span class="read-more small text-success">Read More <i class="bi bi-arrow-right"></i></span>
                </div>
              </div>
            </div>
          </a>
        </div>`;
      }).join('');
      wrap.innerHTML = `<div class="row g-4">${cards}</div>`;
    },

    renderPagination(total) {
      const wrap = document.getElementById('news-pagination');
      if (!wrap) return;
      const totalPages = Math.max(1, Math.ceil(total / PER_PAGE));
      if (totalPages <= 1) {
        wrap.innerHTML = '';
        return;
      }
      const btn = (label, page, disabled, active) => `
        <li class="page-item ${disabled ? 'disabled' : ''} ${active ? 'active' : ''}">
          <a class="page-link ${active ? 'bg-success border-success' : 'text-success'}"
             data-page="${page}" href="javascript:void(0)" ${disabled ? 'tabindex="-1" aria-disabled="true"' : ''}>${label}</a>
        </li>`;
      let html = btn('&laquo;', state.page - 1, state.page <= 1, false);
      for (let p = 1; p <= totalPages; p++) {
        html += btn(String(p), p, false, p === state.page);
      }
      html += btn('&raquo;', state.page + 1, state.page >= totalPages, false);
      wrap.innerHTML = `<ul class="pagination">${html}</ul>`;
      wrap.querySelectorAll('a[data-page]').forEach((a) => {
        a.addEventListener('click', (e) => {
          e.preventDefault();
          const p = parseInt(a.getAttribute('data-page'), 10);
          if (!a.closest('.disabled') && !a.closest('.active') && p >= 1) this.setPage(p);
        });
      });
    },
  };

  window.KingswayPublicNewsPage = newsPage;
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => newsPage.init());
  } else {
    newsPage.init();
  }
})();
