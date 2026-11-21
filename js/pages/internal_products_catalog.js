/** Internal staff shopping catalogue. No inventory-management behavior belongs here. */
(function () {
  'use strict';
  const state = { products: [], query: '', category: 'all' };
  const base = String(window.APP_BASE || '').replace(/\/+$/, '');
  const fallback = `${base}/uploads/school_assets/official_school_logo.png`;
  const labels = {
    'formal-uniform': 'Formal uniforms', sportswear: 'Games & sportswear', bags: 'School bags',
    'branded-merchandise': 'Kingsway merchandise', drinkware: 'Drinkware', stationery: 'Stationery',
    accessories: 'Accessories', 'school-uniforms': 'School uniforms',
  };
  const esc = (value) => String(value == null ? '' : value)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  const versionedImage = (url, version) => {
    const value = String(url || '');
    return `${value}${value.includes('?') ? '&' : '?'}v=${encodeURIComponent(version)}`;
  };

  function visible() {
    const seen = new Set();
    return state.products.filter((product) => {
      const key = String(product.slug || product.id || '');
      if (!key || seen.has(key)) return false;
      seen.add(key);
      if (state.category !== 'all' && product.category_slug !== state.category) return false;
      if (!state.query) return true;
      const variants = (product.variants || []).map((variant) => variant.name || variant.color_name || '').join(' ');
      return `${product.title || ''} ${product.description || ''} ${variants}`.toLowerCase().includes(state.query);
    });
  }

  function renderCategories() {
    const categories = [...new Set(state.products.map((product) => product.category_slug).filter(Boolean))];
    const element = document.getElementById('sicCategories');
    element.innerHTML = `<button class="${state.category === 'all' ? 'btn-success' : ''}" data-category="all">All products</button>` +
      categories.map((category) => `<button class="${state.category === category ? 'btn-success' : ''}" data-category="${esc(category)}">${esc(labels[category] || category)}</button>`).join('');
    element.querySelectorAll('[data-category]').forEach((button) => button.addEventListener('click', () => {
      state.category = button.dataset.category;
      renderCategories(); renderProducts();
    }));
  }

  function renderProducts() {
    const products = visible();
    document.getElementById('sicCount').textContent = `${products.length} item${products.length === 1 ? '' : 's'}`;
    document.getElementById('sicProducts').innerHTML = products.map((product, index) => {
      const sizes = product.sizes || [];
      const available = sizes.reduce((sum, size) => sum + Number(size.available || 0), 0);
      const swatches = (product.variants || []).slice(0, 6).map((variant) => `<span class="catalog-swatch" style="background:${esc(variant.swatch_hex || '#0b5d3b')}" title="${esc(variant.name || 'Variant')}"></span>`).join('');
      return `<article class="staff-catalog-item">
        <button type="button" class="staff-catalog-photo border-0 p-0" data-product="${Number(product.id)}"><img src="${esc(versionedImage(product.image_url || fallback, '20260831-4'))}" alt="${esc(product.title)}" loading="lazy" data-kw-onerror="this.onerror=null;this.src='${fallback}'"><span>${String(index + 1).padStart(2, '0')}</span></button>
        <div class="staff-catalog-item-copy"><small>${esc(labels[product.category_slug] || product.category_slug || 'Collection')}</small><h3>${esc(product.title)}</h3><p>${esc((product.description || 'Official Kingsway school product.').substring(0, 125))}</p>${swatches ? `<div class="catalog-swatches">${swatches}</div>` : ''}<footer><strong>${available > 0 ? 'Available' : 'Coming Soon'}</strong><button type="button" data-product="${Number(product.id)}">View product <i class="bi bi-arrow-up-right"></i></button></footer></div>
      </article>`;
    }).join('') || '<div class="catalog-empty"><i class="bi bi-search fs-2 d-block mb-2"></i>No matching products.</div>';
    document.querySelectorAll('#sicProducts [data-product]').forEach((button) => button.addEventListener('click', () => {
      window.location.href = `${base}/home.php?route=internal_product_details&id=${Number(button.dataset.product)}`;
    }));
  }

  async function init() {
    try {
      const response = await window.API.inventory.getUniformCatalog();
      state.products = response?.products || [];
      renderCategories(); renderProducts();
      document.getElementById('sicSearch').addEventListener('input', (event) => {
        state.query = event.target.value.trim().toLowerCase(); renderProducts();
      });
    } catch (error) {
      const element = document.getElementById('sicError');
      element.textContent = error?.message || 'Unable to open the products catalogue.';
      element.classList.remove('d-none');
    }
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
