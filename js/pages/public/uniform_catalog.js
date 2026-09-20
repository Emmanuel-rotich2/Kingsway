/* =============================================================================
   Uniform Catalogue — js/pages/public/uniform_catalog.js
   Listing page: image, name, short description, size count, price range.
   Every card links to product_details.php?id= for full details + cart.
   ============================================================================= */
(function () {
  'use strict';

  var PS = window.PublicSite;
  if (!PS) return;

  var S = PS.escapeHtml;
  var base = String(window.APP_BASE || '').replace(/\/+$/, '');
  var FALLBACK_IMG = base + '/uploads/school_assets/official_school_logo.png';
  var PUBLIC_CATEGORIES = ['formal-uniform', 'school-uniforms', 'sportswear', 'bags', 'branded-merchandise', 'accessories'];
  var CATEGORY_LABELS = {
    'formal-uniform': 'Formal uniforms',
    'school-uniforms': 'School uniforms',
    sportswear: 'Games & sportswear',
    bags: 'School bags',
    'branded-merchandise': 'Weekend wear',
    accessories: 'Uniform accessories',
  };

  function productImage(p) {
    if (p.image_url && p.image_url !== FALLBACK_IMG) return p.image_url;
    return FALLBACK_IMG;
  }

  function versionedImage(url, version) {
    var value = String(url || '');
    return value + (value.indexOf('?') === -1 ? '?' : '&') + 'v=' + encodeURIComponent(version);
  }

  var page = {
    initialized: false,
    products: [],
    activeCategory: 'all',
    query: '',

    publicProducts: function () {
      var seen = new Set();
      return this.products.filter(function (p) {
        var key = String(p.slug || p.id || '');
        if (!key || seen.has(key) || PUBLIC_CATEGORIES.indexOf(String(p.category_slug || '')) === -1) return false;
        seen.add(key);
        return true;
      });
    },

    visibleProducts: function () {
      var query = this.query;
      return this.publicProducts().filter(function (p) {
        if (page.activeCategory !== 'all' && p.category_slug !== page.activeCategory) return false;
        if (!query) return true;
        var variantNames = (p.variants || []).map(function (v) { return v.name || v.color_name || ''; }).join(' ');
        return (String(p.title || '') + ' ' + String(p.description || '') + ' ' +
          String(p.product_type || '') + ' ' + variantNames).toLowerCase().indexOf(query) !== -1;
      });
    },

    async init() {
      if (this.initialized) return;
      this.initialized = true;
      try {
        var resp = await fetch(base + '/api/public/uniform-catalog', {
          method: 'GET', credentials: 'same-origin',
          headers: { Accept: 'application/json' },
        });
        if (!resp.ok) throw new Error('HTTP ' + resp.status);
        var payload = await resp.json();
        this.products = (payload.data && payload.data.products) || payload.products || [];
        this.render();
        this.renderCategories();
        this.bindSearch();
      } catch (err) {
        if (window.KINGSWAY_DEBUG) console.warn('[uniform_catalog] load failed:', err);
        var el = document.getElementById('ucError');
        if (el) { el.textContent = 'Unable to load the catalogue. Please try again later.'; el.classList.remove('d-none'); }
        var grid = document.getElementById('ucGrid');
        if (grid) grid.innerHTML = '';
      }
    },

    render: function () {
      var el = document.getElementById('ucGrid');
      if (!el) return;
      var visible = this.visibleProducts();
      if (!visible.length) {
        el.innerHTML = '<div class="catalog-empty"><i class="bi bi-bag-x fs-1 d-block mb-3"></i>' +
          '<p class="mb-0">No matching school uniforms are available right now.</p></div>';
        this.updateCount(0);
        return;
      }
      this.updateCount(visible.length);
      el.innerHTML = visible.map(function (p, i) { return page.card(p, i); }).join('');
      if (window.PublicUI && window.PublicUI.observeReveals) window.PublicUI.observeReveals(el);
    },

    renderCategories: function(){
      var el = document.getElementById('ucCategories');
      if (!el) return;
      var categories = Array.from(new Set(this.publicProducts().map(function (p) { return p.category_slug; })));
      el.innerHTML = '<button class="' + (this.activeCategory === 'all' ? 'btn-success' : '') + '" data-category="all">All uniforms</button>' +
        categories.map(function (c) {
          return '<button class="' + (page.activeCategory === c ? 'btn-success' : '') + '" data-category="' + S(c) + '">' + S(CATEGORY_LABELS[c] || c) + '</button>';
        }).join('');
      el.querySelectorAll('[data-category]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          page.activeCategory = btn.dataset.category;
          page.render();
          page.renderCategories();
        });
      });
    },

    card: function (p, index) {
      var img = productImage(p);
      var sizes = p.sizes || [];
      var count = sizes.length;
      var lo = Infinity, hi = 0;
      for (var i = 0; i < count; i++) {
        var price = Number(sizes[i].unit_price) || 0;
        if (price <= 0) continue;
        if (price < lo) lo = price;
        if (price > hi) hi = price;
      }
      if (lo === Infinity) lo = 0;
      var priceStr = lo > 0
        ? (lo === hi
          ? 'KES ' + lo.toLocaleString()
          : 'KES ' + lo.toLocaleString() + ' – ' + hi.toLocaleString())
        : '';
      var href = base + '/index.php?route=r468ffeac30cf&id=' + encodeURIComponent(p.id);
      var available = sizes.reduce(function (total, size) { return total + Number(size.available || 0); }, 0);
      var swatches = (p.variants || []).slice(0, 6).map(function (variant) {
        var color = variant.swatch_hex || variant.swatch || '#0b5d3b';
        return '<span class="catalog-swatch" style="background:' + S(color) + '" title="' + S(variant.name || variant.color_name || 'Variant') + '"></span>';
      }).join('');

      return '<article class="catalog-product-card reveal">' +
        '<a href="' + href + '" class="text-decoration-none d-block">' +
        '<div class="catalog-product-media">' +
        '<img src="' + S(versionedImage(img, '20260831-3')) + '" alt="' + S(p.title) + '" loading="lazy" onerror="this.onerror=null;this.src=\'' + FALLBACK_IMG + '\'">' +
        '<span class="catalog-product-number">' + String(index + 1).padStart(2, '0') + '</span>' +
        '<span class="catalog-product-state">' + (available > 0 ? 'In stock' : 'Coming Soon') + '</span>' +
        '</div></a>' +
        '<div class="catalog-product-info">' +
        '<span class="catalog-product-category">' + S(CATEGORY_LABELS[p.category_slug] || p.category_slug || 'School uniform') + '</span>' +
        '<h3 class="catalog-product-title">' + S(p.title) + '</h3>' +
        '<p class="catalog-product-desc">' + S((p.description || 'Official Kingsway Preparatory School uniform item.').substring(0, 105)) + ((p.description || '').length > 105 ? '…' : '') + '</p>' +
        (swatches ? '<div class="catalog-swatches" aria-label="Available colours">' + swatches + '</div>' : '') +
        '<div class="catalog-product-footer"><div><div class="catalog-product-price">' + (available > 0 ? (priceStr || 'Price on request') : 'Coming Soon') + '</div>' +
        '<small class="text-muted">' + (count ? count + ' size' + (count === 1 ? '' : 's') : 'Details available') + '</small></div>' +
        '<a href="' + href + '" class="catalog-view-link" aria-label="View ' + S(p.title) + '"><i class="bi bi-arrow-up-right"></i></a></div>' +
        '</div></article>';
    },

    updateCount: function (n) {
      var el = document.getElementById('ucCount');
      if (el) el.textContent = n + ' item' + (n === 1 ? '' : 's');
    },

    bindSearch: function () {
      var input = document.getElementById('ucSearch');
      if (!input) return;
      input.addEventListener('input', function () {
        page.query = input.value.toLowerCase().trim();
        page.render();
      });
    },
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { page.init(); });
  } else {
    page.init();
  }
})();
