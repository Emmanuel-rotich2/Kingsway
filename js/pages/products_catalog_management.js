/**
 * products_catalog_management.js — catalogue management controller.
 * Injecting page: pages/products_catalog_management.php.
 *
 * View-only for all authenticated staff; sell + management actions require an
 * inventory write permission (mirrors InventoryController::guardInventoryWrite:
 * inventory.manage / inventory.admin / system administrator).
 */
const internalProductsCatalogController = {
  initialized: false,
  state: {
    products: [],
    items: [],
    students: [],
    allStudents: [],
    filters: { q: '', status: 'all', category: 'all' },
    canManage: false,
    sellProduct: null,
  },

  esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  },

  versionedImage(url, version) {
    const value = String(url || '');
    return `${value}${value.includes('?') ? '&' : '?'}v=${encodeURIComponent(version)}`;
  },

  notify(message, type = 'success') {
    if (typeof showNotification === 'function') {
      showNotification(message, type);
    } else {
      window.alert(message);
    }
  },

  canManageInventory() {
    return Boolean(
      window.API?.hasRole?.('school administrator') ||
      window.API?.hasRole?.('uniform store manager'),
    );
  },

  applyExperience() {
    document.querySelectorAll('.ipc-manager-only, .ipc-manage-only').forEach((el) => {
      el.classList.toggle('d-none', !this.state.canManage);
    });
    document.querySelectorAll('.ipc-buyer-only').forEach((el) => {
      el.classList.toggle('d-none', this.state.canManage);
    });
    const grid = document.getElementById('ipcProductsBody');
    grid.classList.toggle('internal-products-grid', this.state.canManage);
    grid.classList.toggle('catalog-product-grid', !this.state.canManage);
    document.getElementById('ipcPageTitle').textContent = this.state.canManage
      ? 'Products Catalogue Management'
      : 'Kingsway Products Catalogue';
  },

  normUnits(r) {
    const d = r?.data && r.data.data ? r.data : (r ?? {});
    const arr = d?.items ?? d?.students ?? d?.data ?? (Array.isArray(d) ? d : []);
    return Array.isArray(arr) ? arr : [];
  },

  async init() {
    if (this.initialized) return;
    this.initialized = true;
    if (window.AuthContext?.ready) await window.AuthContext.ready();

    this.state.canManage = this.canManageInventory();
    this.applyExperience();

    this.setupEventListeners();

    if (this.state.canManage) {
      await this.loadItems();
    }
    await this.loadData();
  },

  setupEventListeners() {
    const $ = (id) => document.getElementById(id);
    $('ipcRefreshBtn').addEventListener('click', () => this.loadData());
    $('ipcSearch').addEventListener('input', (e) => {
      this.state.filters.q = e.target.value.trim();
      this.render();
    });
    $('ipcStatusFilter').addEventListener('change', (e) => {
      this.state.filters.status = e.target.value;
      this.render();
    });
    $('ipcCategoryFilter')?.addEventListener('change', (e) => {
      this.state.filters.category = e.target.value;
      this.render();
    });
    $('ipcAddProductBtn').addEventListener('click', () => this.openEdit(null));
    $('ipcProductForm').addEventListener('submit', (e) => {
      e.preventDefault();
      this.submitProduct();
    });
    $('ipcImageForm').addEventListener('submit', (e) => {
      e.preventDefault();
      this.submitImage();
    });
    $('ipcVariantForm').addEventListener('submit', (e) => { e.preventDefault(); this.submitVariant(); });
    $('ipcSizeForm').addEventListener('submit', (e) => { e.preventDefault(); this.submitSize(); });
  },

  async loadData() {
    const body = document.getElementById('ipcProductsBody');
    body.innerHTML = '<div class="catalog-loading"><div class="spinner-border spinner-border-sm me-2"></div>Loading collection…</div>';
    try {
      const d = await window.API.inventory.getUniformCatalog({ management: 1 });
      this.state.products = d?.products || [];
      if (typeof d?.can_manage === 'boolean') this.state.canManage = d.can_manage;
      this.applyExperience();
      this.populateBuyerCategories();
    } catch (err) {
      this.state.products = [];
      body.innerHTML = `<div class="catalog-empty text-danger">${this.esc(err?.message || 'Unable to load the catalogue.')}</div>`;
      this.notify('Unable to load the catalogue.', 'danger');
      return;
    }
    this.render();
  },

  populateBuyerCategories() {
    const select = document.getElementById('ipcCategoryFilter');
    if (!select || this.state.canManage) return;
    const labels = {
      'formal-uniform': 'Formal uniforms', sportswear: 'Games & sportswear',
      bags: 'School bags', 'branded-merchandise': 'Weekend wear', accessories: 'Accessories',
    };
    const categories = [...new Set(this.state.products.map((p) => p.category_slug).filter(Boolean))];
    select.innerHTML = '<option value="all">All collections</option>' + categories.map((category) =>
      `<option value="${this.esc(category)}">${this.esc(labels[category] || category)}</option>`).join('');
    select.value = categories.includes(this.state.filters.category) ? this.state.filters.category : 'all';
  },

  async loadItems() {
    try {
      const r = await window.API.inventory.list({ limit: 500 });
      this.state.items = this.normUnits(r);
    } catch (err) {
      this.state.items = [];
    }
  },

  async loadStudents() {
    try {
      const r = await window.API.students.get();
      const arr = Array.isArray(r) ? r : (r?.students ?? r?.data ?? []);
      this.state.allStudents = Array.isArray(arr)
        ? arr.filter((s) => typeof s === 'object' && s !== null)
        : [];
      this.filterStudents('');
    } catch (err) {
      this.state.allStudents = [];
      const sel = document.getElementById('ipcSellStudentLarge');
      sel.innerHTML = '<option value="">Unable to load learners</option>';
    }
  },

  filterStudents(q) {
    const filtered = this.state.allStudents.filter((s) => {
      if (!q) return true;
      const name = `${s.first_name || ''} ${s.last_name || ''} ${s.admission_number || ''} ${s.admission_no || ''}`;
      return name.toLowerCase().includes(q);
    });
    this.state.students = filtered;
    const sel = document.getElementById('ipcSellStudentLarge');
    sel.innerHTML = filtered.length
      ? filtered.map((s) => `<option value="${Number(s.id)}">${this.esc(s.first_name || '')} ${this.esc(s.last_name || '')} (${this.esc(s.admission_number || 'N/A')})</option>`).join('')
      : '<option value="">No matching learners</option>';
  },

  render() {
    if (!this.state.canManage) {
      this.renderBuyerCatalogue();
      return;
    }
    const { q, status } = this.state.filters;
    const rows = this.state.products.filter((p) => {
      if (status !== 'all' && p.status !== status) return false;
      if (q) {
        const hay = `${p.title || ''} ${p.code || ''} ${p.description || ''}`.toLowerCase();
        if (!hay.includes(q)) return false;
      }
      return true;
    });

    const live = this.state.products.filter((p) => p.status === 'active' && Number(p.published) === 1).length;
    const draft = this.state.products.filter((p) => p.status !== 'archived' && Number(p.published) !== 1).length;
    const archived = this.state.products.filter((p) => p.status === 'archived').length;
    document.getElementById('ipcStatTotal').textContent = this.state.products.length;
    document.getElementById('ipcStatTotalSub').textContent = `${this.state.products.length - archived} catalogued`;
    document.getElementById('ipcStatLive').textContent = live;
    document.getElementById('ipcStatDraft').textContent = draft;
    document.getElementById('ipcStatArchived').textContent = archived;
    document.getElementById('ipcCount').textContent = `${rows.length} item${rows.length === 1 ? '' : 's'} shown`;

    const manage = this.state.canManage ? '' : 'd-none';
    const fallback = `${window.APP_BASE || ''}/uploads/school_assets/official_school_logo.png`;
    document.getElementById('ipcProductsBody').innerHTML = rows.map((p) => {
      const image = p.image_url || fallback;
      const statusBadge = {
        active: 'bg-success', draft: 'bg-warning text-dark', archived: 'bg-secondary',
      }[p.status] || 'bg-secondary';
      const sizeCells = (p.sizes || []).length
        ? p.sizes.map((s) =>
            `<span title="${this.esc(s.size)}">` +
            `${this.esc(s.size_label || s.size)} · ${Number(s.available || 0)} · KES ${Number(s.unit_price || 0).toLocaleString()}` +
            `</span>`).join('')
        : '<span class="text-muted small">—</span>';
      const swatches = (p.variants || []).slice(0, 7).map((v) =>
        `<span class="catalog-swatch" style="background:${this.esc(v.swatch_hex || v.swatch || '#0b5d3b')}" title="${this.esc(v.name || v.color_name || 'Variant')}"></span>`).join('');
      return `<article class="internal-product-card">
        <div class="internal-product-image">
          <img src="${this.esc(image)}" alt="${this.esc(p.title)}" loading="lazy" onerror="this.onerror=null;this.src='${fallback}'">
          <div class="internal-product-badges"><span class="${statusBadge}">${this.esc(p.status || '—')}</span>${Number(p.published) === 1 ? '<span class="bg-success text-white">Published</span>' : '<span class="bg-light text-dark">Internal only</span>'}</div>
        </div>
        <div class="internal-product-content">
          <div class="d-flex justify-content-between gap-2"><span class="internal-product-code">${this.esc(p.code || 'NO SKU')}</span><span class="catalog-product-category">${this.esc(p.category_slug || 'product')}</span></div>
          <h3>${this.esc(p.title)}</h3>
          <p>${this.esc((p.description || 'No catalogue description supplied.').substring(0, 120))}</p>
          ${swatches ? `<div class="catalog-swatches mb-2">${swatches}</div>` : ''}
          <div class="internal-product-stock">${sizeCells}</div>
        </div>
        <div class="internal-product-actions ${manage}">
            <button class="btn btn-sm btn-outline-primary" data-edit="${p.id}"><i class="bi bi-pencil me-1"></i>Edit</button>
            <button class="btn btn-sm btn-outline-success" data-options="${p.id}" title="Manage variants, sizes, prices and stock"><i class="bi bi-sliders me-1"></i>Options</button>
            <button class="btn btn-sm btn-outline-secondary" data-image="${p.id}" title="Upload product image"><i class="bi bi-image"></i></button>
            <button class="btn btn-sm ${Number(p.published) === 1 ? 'btn-outline-warning' : 'btn-outline-success'}" data-pub="${p.id}">
              ${Number(p.published) === 1 ? '<i class="bi bi-eye-slash me-1"></i>Unpublish' : '<i class="bi bi-eye me-1"></i>Publish'}
            </button>
        </div>
      </article>`;
    }).join('') || '<div class="catalog-empty"><i class="bi bi-search fs-2 d-block mb-2"></i>No matching products.</div>';

    if (!this.state.canManage) return;
    document.querySelectorAll('[data-edit]').forEach((btn) => {
      const p = this.state.products.find((x) => Number(x.id) === Number(btn.dataset.edit));
      btn.addEventListener('click', () => p && this.openEdit(p));
    });
    document.querySelectorAll('[data-image]').forEach((btn) => {
      const p = this.state.products.find((x) => Number(x.id) === Number(btn.dataset.image));
      btn.addEventListener('click', () => p && this.openImage(p));
    });
    document.querySelectorAll('[data-options]').forEach((btn) => {
      const p = this.state.products.find((x) => Number(x.id) === Number(btn.dataset.options));
      btn.addEventListener('click', () => p && this.openOptions(p));
    });
    document.querySelectorAll('[data-pub]').forEach((btn) => {
      const p = this.state.products.find((x) => Number(x.id) === Number(btn.dataset.pub));
      btn.addEventListener('click', () => p && this.togglePublish(p));
    });
  },

  renderBuyerCatalogue() {
    const { q, category } = this.state.filters;
    const seen = new Set();
    const products = this.state.products.filter((p) => {
      const key = String(p.slug || p.id || '');
      if (!key || seen.has(key)) return false;
      seen.add(key);
      if (category !== 'all' && p.category_slug !== category) return false;
      if (!q) return true;
      const variants = (p.variants || []).map((v) => v.name || v.color_name || '').join(' ');
      return `${p.title || ''} ${p.description || ''} ${variants}`.toLowerCase().includes(q.toLowerCase());
    });
    document.getElementById('ipcCount').textContent = `${products.length} item${products.length === 1 ? '' : 's'}`;
    const fallback = `${window.APP_BASE || ''}/uploads/school_assets/official_school_logo.png`;
    const base = String(window.APP_BASE || '').replace(/\/+$/, '');
    document.getElementById('ipcProductsBody').innerHTML = products.map((p, index) => {
      const image = p.image_url || fallback;
      const sizes = p.sizes || [];
      const available = sizes.reduce((total, size) => total + Number(size.available || 0), 0);
      const swatches = (p.variants || []).slice(0, 7).map((v) =>
        `<span class="catalog-swatch" style="background:${this.esc(v.swatch_hex || '#0b5d3b')}" title="${this.esc(v.name || v.color_name || 'Variant')}"></span>`).join('');
      const href = `${base}/index.php?route=r468ffeac30cf&id=${encodeURIComponent(p.id)}`;
      return `<article class="catalog-product-card">
        <a href="${href}" class="text-decoration-none d-block"><div class="catalog-product-media">
          <img src="${this.esc(this.versionedImage(image, '20260831-3'))}" alt="${this.esc(p.title)}" loading="lazy" onerror="this.onerror=null;this.src='${fallback}'">
          <span class="catalog-product-number">${String(index + 1).padStart(2, '0')}</span>
          <span class="catalog-product-state">${available > 0 ? 'In stock' : 'Coming Soon'}</span>
        </div></a>
        <div class="catalog-product-info">
          <span class="catalog-product-category">${this.esc(p.category_slug || 'School collection')}</span>
          <h3 class="catalog-product-title">${this.esc(p.title)}</h3>
          <p class="catalog-product-desc">${this.esc((p.description || 'Official Kingsway school product.').substring(0, 110))}</p>
          ${swatches ? `<div class="catalog-swatches">${swatches}</div>` : ''}
          <div class="catalog-product-footer"><div><div class="catalog-product-price">${available > 0 ? 'Available' : 'Coming Soon'}</div><small class="text-muted">${sizes.length ? `${sizes.length} size${sizes.length === 1 ? '' : 's'}` : 'Details available'}</small></div><a href="${href}" class="catalog-view-link" aria-label="View ${this.esc(p.title)}"><i class="bi bi-arrow-up-right"></i></a></div>
        </div>
      </article>`;
    }).join('') || '<div class="catalog-empty"><i class="bi bi-search fs-2 d-block mb-2"></i>No matching products.</div>';
  },

  openSell(product) {
    if (!this.state.canManage) return;
    this.state.sellProduct = product;
    document.getElementById('ipcSellProductInfo').innerHTML =
      `<strong>${this.esc(product.title)}</strong> <span class="text-muted">· SKU ${this.esc(product.code || '')}</span>`;
    document.getElementById('ipcSellProductId').value = product.id;
    document.getElementById('ipcSellQty').value = 1;
    const sizeSel = document.getElementById('ipcSellSize');
    const sizes = product.sizes || [];
    sizeSel.innerHTML = sizes.length
      ? '<option value="">Select size…</option>' + sizes.map((s) => {
          const available = Number(s.available || 0);
          const variant = (product.variants || []).find((v) => Number(v.id) === Number(s.variant_id));
          return `<option value="${Number(s.size_id)}" data-variant-id="${Number(s.variant_id || 0)}" ${available < 1 ? 'disabled' : ''}>` +
            `${variant ? this.esc(variant.name) + ' · ' : ''}${this.esc(s.size_label || s.size)} · KES ${Number(s.unit_price || 0).toLocaleString()} (${available} available)` +
            `</option>`;
        }).join('')
      : '<option value="">No sizes in stock</option>';
    if (!this.state.students.length && this.state.allStudents.length) this.filterStudents('');
    bootstrap.Modal.getOrCreateInstance(document.getElementById('ipcSellModal')).show();
  },

  async submitSell() {
    const productId = Number(document.getElementById('ipcSellProductId').value);
    const studentId = Number(document.getElementById('ipcSellStudentLarge').value);
    const sizeId = Number(document.getElementById('ipcSellSize').value);
    const variantId = Number(document.getElementById('ipcSellSize').selectedOptions[0]?.dataset.variantId || 0);
    const quantity = Number(document.getElementById('ipcSellQty').value);
    if (!studentId || !sizeId || quantity < 1) {
      this.notify('Select a learner, size and a positive quantity.', 'danger');
      return;
    }
    const btn = document.getElementById('ipcSellSaveBtn');
    btn.disabled = true;
    try {
      await window.API.inventory.createUniformCatalogPurchase({
        student_id: studentId,
        product_id: productId,
        size_id: sizeId,
        variant_id: variantId || null,
        quantity,
      });
      bootstrap.Modal.getInstance(document.getElementById('ipcSellModal')).hide();
      this.notify('Purchase created. Collect payment in the Uniform Sales workflow.');
      this.loadData();
    } catch (err) {
      this.notify(err?.message || 'Unable to create the purchase.', 'danger');
    } finally {
      btn.disabled = false;
    }
  },

  openEdit(product) {
    if (!this.state.canManage) return;
    const usedItems = new Set(
      this.state.products.map((p) => Number(p.item_id)).filter(Boolean),
    );
    const currentItemId = product ? Number(product.item_id) : 0;
    if (currentItemId) usedItems.delete(currentItemId);
    const itemSel = document.getElementById('ipcProductItem');
    const available = this.state.items.filter((i) =>
      currentItemId === Number(i.id) || !usedItems.has(Number(i.id)));
    itemSel.innerHTML = available.length
      ? available.map((i) =>
          `<option value="${Number(i.id)}" ${currentItemId === Number(i.id) ? 'selected' : ''}>` +
          `${this.esc(i.item_name || i.name)} (${this.esc(i.code || '')})</option>`).join('')
      : '<option value="">No remaining inventory items</option>';

    document.getElementById('ipcProductExistingId').value = product ? product.id : 0;
    document.getElementById('ipcProductTitle').value = product ? (product.title || '') : '';
    document.getElementById('ipcProductDesc').value = product ? (product.description || '') : '';
    document.getElementById('ipcProductCategory').value = product ? (product.category_slug || 'formal-uniform') : 'formal-uniform';
    document.getElementById('ipcProductType').value = product ? (product.product_type || '') : '';
    document.getElementById('ipcCustomName').checked = product ? Number(product.customizable_name) === 1 : false;
    document.getElementById('ipcCustomNumber').checked = product ? Number(product.customizable_number) === 1 : false;
    document.getElementById('ipcProductStatus').value = product ? (product.status || 'draft') : 'draft';
    document.getElementById('ipcProductPublished').checked = product ? Number(product.published) === 1 : false;
    document.getElementById('ipcProductModalTitle').textContent = product ? 'Edit Product' : 'Add Product';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('ipcProductModal')).show();
  },

  async submitProduct() {
    const itemId = Number(document.getElementById('ipcProductItem').value);
    const title = document.getElementById('ipcProductTitle').value.trim();
    if (!itemId || !title) {
      this.notify('Select an inventory item and provide a title.', 'danger');
      return;
    }
    const btn = document.getElementById('ipcProductSaveBtn');
    btn.disabled = true;
    try {
      await window.API.inventory.saveUniformCatalogProduct({
        item_id: itemId,
        title,
        description: document.getElementById('ipcProductDesc').value.trim(),
        category_slug: document.getElementById('ipcProductCategory').value,
        product_type: document.getElementById('ipcProductType').value.trim(),
        customizable_name: document.getElementById('ipcCustomName').checked ? 1 : 0,
        customizable_number: document.getElementById('ipcCustomNumber').checked ? 1 : 0,
        status: document.getElementById('ipcProductStatus').value,
        published: document.getElementById('ipcProductPublished').checked ? 1 : 0,
      });
      bootstrap.Modal.getInstance(document.getElementById('ipcProductModal')).hide();
      this.notify('Catalogue product saved.');
      this.loadData();
    } catch (err) {
      this.notify(err?.message || 'Unable to save the product.', 'danger');
    } finally {
      btn.disabled = false;
    }
  },

  async togglePublish(product) {
    const next = Number(product.published) === 1 ? 0 : 1;
    try {
      await window.API.inventory.saveUniformCatalogProduct({
        item_id: Number(product.item_id),
        slug: product.slug,
        title: product.title,
        description: product.description,
        category_slug: product.category_slug,
        product_type: product.product_type,
        customizable_name: Number(product.customizable_name) || 0,
        customizable_number: Number(product.customizable_number) || 0,
        status: product.status || 'active',
        published: next,
      });
      this.notify(next ? 'Product published to buyers.' : 'Product unpublished.');
      this.loadData();
    } catch (err) {
      this.notify(err?.message || 'Unable to update publication state.', 'danger');
    }
  },

  openImage(product) {
    if (!this.state.canManage) return;
    document.getElementById('ipcImageProductId').value = product.id;
    document.getElementById('ipcImageProductInfo').textContent = product.title;
    document.getElementById('ipcImageFile').value = '';
    document.getElementById('ipcImageAlt').value = '';
    document.getElementById('ipcImageVariant').innerHTML = '<option value="">General product image</option>' + (product.variants || []).map((v)=>`<option value="${Number(v.id)}">${this.esc(v.name)}</option>`).join('');
    document.getElementById('ipcImageView').value = 'catalog';
    document.getElementById('ipcImagePrimary').checked = true;
    const images = product.images || [];
    document.getElementById('ipcCurrentImages').innerHTML = images.length ? images.map((img) => `<div class="position-relative border rounded overflow-hidden"><img src="${this.esc(img.url)}" alt="${this.esc(img.alt_text || product.title)}" style="width:86px;height:72px;object-fit:cover"><button type="button" class="btn btn-danger btn-sm position-absolute top-0 end-0 py-0 px-1" data-delete-image="${Number(img.id)}" aria-label="Remove image"><i class="bi bi-x"></i></button><span class="position-absolute bottom-0 start-0 badge bg-dark bg-opacity-75">${this.esc(img.view_type || 'catalog')}</span></div>`).join('') : '<span class="text-muted small">No images uploaded.</span>';
    document.querySelectorAll('[data-delete-image]').forEach((btn) => btn.addEventListener('click', async () => {
      if (!window.confirm('Remove this catalogue image?')) return;
      try { await window.API.inventory.deleteUniformCatalogImage(Number(btn.dataset.deleteImage)); this.notify('Catalogue image removed.'); bootstrap.Modal.getInstance(document.getElementById('ipcImageModal')).hide(); await this.loadData(); }
      catch (err) { this.notify(err?.message || 'Unable to remove image.', 'danger'); }
    }));
    bootstrap.Modal.getOrCreateInstance(document.getElementById('ipcImageModal')).show();
  },

  openOptions(product) {
    document.getElementById('ipcOptionsProductId').value = product.id;
    document.getElementById('ipcOptionsBaseItemId').value = product.item_id;
    document.getElementById('ipcOptionsProductInfo').textContent = product.title;
    document.getElementById('ipcVariantId').value = '';
    document.getElementById('ipcVariantName').value = '';
    document.getElementById('ipcVariantCode').value = '';
    document.getElementById('ipcVariantColor').value = '';
    document.getElementById('ipcVariantSwatch').value = '#0b5d3b';
    document.getElementById('ipcVariantStatus').value = 'draft';
    const variants = product.variants || [];
    document.getElementById('ipcVariantsList').innerHTML = variants.length ? variants.map((v) => `<button type="button" class="btn btn-sm btn-light border me-1 mb-1" data-edit-variant="${Number(v.id)}"><span class="d-inline-block rounded-circle me-1" style="width:12px;height:12px;background:${this.esc(v.swatch_hex || '#ddd')}"></span>${this.esc(v.name)} · ${this.esc(v.status)}</button>`).join('') : '<p class="small text-muted">No variants yet.</p>';
    const stockItems = this.state.items.filter((i) => Number(i.category_id) === 10 || /uniform|shirt|skirt|dress|blazer|sweater|track|games|bag|mug|bottle|umbrella/i.test(`${i.item_name || i.name || ''} ${i.code || ''}`));
    document.getElementById('ipcVariantItem').innerHTML = '<option value="">Share the product\'s base stock</option>' + stockItems.map((i)=>`<option value="${Number(i.id)}">${this.esc(i.item_name||i.name)} (${this.esc(i.code||'')})</option>`).join('');
    document.querySelectorAll('[data-edit-variant]').forEach((btn) => btn.addEventListener('click', () => { const v=variants.find((x)=>Number(x.id)===Number(btn.dataset.editVariant));if(!v)return;document.getElementById('ipcVariantId').value=v.id;document.getElementById('ipcVariantName').value=v.name||'';document.getElementById('ipcVariantCode').value=v.code||'';document.getElementById('ipcVariantColor').value=v.color_name||'';document.getElementById('ipcVariantSwatch').value=v.swatch_hex||'#0b5d3b';document.getElementById('ipcVariantStatus').value=v.status||'draft';document.getElementById('ipcVariantItem').value=v.item_id||''; }));
    const itemIds = new Set([Number(product.item_id), ...variants.map((v)=>Number(v.item_id)).filter(Boolean)]);
    document.getElementById('ipcSizeItem').innerHTML = this.state.items.filter((i)=>itemIds.has(Number(i.id))).map((i)=>`<option value="${Number(i.id)}">${this.esc(i.item_name||i.name)} (${this.esc(i.code||'')})</option>`).join('');
    document.getElementById('ipcSizesList').innerHTML=(product.sizes||[]).map((s)=>`<span class="badge text-bg-light border me-1 mb-1">${this.esc(s.size_label||s.size)} · ${Number(s.available)} · KES ${Number(s.unit_price).toLocaleString()}</span>`).join('')||'<p class="small text-muted">No sizes configured.</p>';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('ipcOptionsModal')).show();
  },

  async submitVariant() {
    try { await window.API.inventory.saveUniformCatalogVariant({id:Number(document.getElementById('ipcVariantId').value)||undefined,product_id:Number(document.getElementById('ipcOptionsProductId').value),item_id:Number(document.getElementById('ipcVariantItem').value)||null,code:document.getElementById('ipcVariantCode').value.trim(),name:document.getElementById('ipcVariantName').value.trim(),color_name:document.getElementById('ipcVariantColor').value.trim(),swatch_hex:document.getElementById('ipcVariantSwatch').value,status:document.getElementById('ipcVariantStatus').value});this.notify('Variant saved.');bootstrap.Modal.getInstance(document.getElementById('ipcOptionsModal')).hide();await this.loadData(); } catch(err){this.notify(err?.message||'Unable to save variant.','danger');}
  },

  async submitSize() {
    try { await window.API.inventory.saveUniformCatalogSize({item_id:Number(document.getElementById('ipcSizeItem').value),size:document.getElementById('ipcSizeCode').value.trim(),size_label:document.getElementById('ipcSizeCode').value.trim(),size_type:document.getElementById('ipcSizeType').value,unit_price:Number(document.getElementById('ipcSizePrice').value),quantity_available:Number(document.getElementById('ipcSizeStock').value),reorder_level:Number(document.getElementById('ipcSizeReorder').value)});this.notify('Size, price and stock saved.');bootstrap.Modal.getInstance(document.getElementById('ipcOptionsModal')).hide();await this.loadData(); } catch(err){this.notify(err?.message||'Unable to save size and stock.','danger');}
  },

  async submitImage() {
    const productId = Number(document.getElementById('ipcImageProductId').value);
    const file = document.getElementById('ipcImageFile').files[0];
    if (!productId || !file) {
      this.notify('Choose an image file to upload.', 'danger');
      return;
    }
    const btn = document.getElementById('ipcImageSaveBtn');
    btn.disabled = true;
    try {
      const fd = new FormData();
      fd.append('file', file);
      fd.append('product_id', String(productId));
      fd.append('alt_text', document.getElementById('ipcImageAlt').value.trim());
      fd.append('is_primary', document.getElementById('ipcImagePrimary').checked ? '1' : '0');
      fd.append('variant_id', document.getElementById('ipcImageVariant').value);
      fd.append('view_type', document.getElementById('ipcImageView').value);
      await window.API.inventory.uploadUniformCatalogImage(fd);
      bootstrap.Modal.getInstance(document.getElementById('ipcImageModal')).hide();
      this.notify('Product image uploaded.');
      this.loadData();
    } catch (err) {
      this.notify(err?.message || 'Unable to upload the image.', 'danger');
    } finally {
      btn.disabled = false;
    }
  },
};

window.internalProductsCatalogController = internalProductsCatalogController;

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => internalProductsCatalogController.init().catch(() => {}));
} else {
  internalProductsCatalogController.init().catch(() => {});
}
