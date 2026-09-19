/**
 * js/pages/parents/uniform_catalog.js — Uniform Store in the parent cpanel.
 *
 * Authenticated parent commerce: catalogue grid (auto-filtered by category
 * and search with no submit button), product quick-view with size/variant and
 * add-to-cart, cart offcanvas with line updates, checkout (learner + payment
 * method + M-Pesa), and order history with cancel + payment retry. Uses only
 * the parent-portal endpoints; parents never leave the cpanel.
 */
(function () {
  'use strict';
  var P = window.ParentCommon;
  if (!P) return;

  var FALLBACK_IMG =
    String(window.APP_BASE || '').replace(/\/+$/, '') +
    '/uploads/school_assets/official_school_logo.png';

  var CATEGORY_LABELS = {
    'formal-uniform': 'Formal uniforms',
    'school-uniforms': 'School uniforms',
    sportswear: 'Games & sportswear',
    bags: 'School bags',
    'branded-merchandise': 'Weekend wear',
    accessories: 'Uniform accessories',
  };

  var state = {
    products: [],
    activeCategory: 'all',
    query: '',
    activeProductId: 0,
    children: [],
    payments: [],
    checkoutChannel: 'daraja_mpesa',
    checkoutAccount: 0,
    statusTimer: null,
  };

  function fallbackImage(p) {
    return p.image_url && p.image_url !== FALLBACK_IMG ? p.image_url : FALLBACK_IMG;
  }

  function versionedImage(url) {
    var value = String(url || '');
    return value + (value.indexOf('?') === -1 ? '?' : '&') + 'v=' + encodeURIComponent(Date.now());
  }

  function esc(s) { return P ? P.esc(s) : String(s || ''); }

  function fmt(n) {
    var v = Number(n || 0);
    return 'KES ' + v.toLocaleString('en-KE');
  }

  function showError(message) {
    var el = document.getElementById('storeError');
    if (!el) return;
    el.textContent = message || 'Something went wrong. Please try again.';
    el.classList.remove('d-none');
  }

  function hideError() {
    var el = document.getElementById('storeError');
    if (el) el.classList.add('d-none');
  }

  function setCount(n) {
    var el = document.getElementById('storeCount');
    if (el) el.textContent = n + ' item' + (n === 1 ? '' : 's');
  }

  function setCartCount(n) {
    var el = document.getElementById('storeCartCount');
    if (el) el.textContent = n;
  }

  /* ── Catalogue grid ─────────────────────────────────────────────────── */

  function visibleProducts() {
    var query = state.query;
    return state.products.filter(function (p) {
      if (state.activeCategory !== 'all' && p.category_slug !== state.activeCategory) return false;
      if (!query) return true;
      var variantNames = (p.variants || []).map(function (v) { return v.name || v.color_name || ''; }).join(' ');
      return (String(p.title || '') + ' ' + String(p.description || '') + ' ' +
        String(p.product_type || '') + ' ' + variantNames).toLowerCase().indexOf(query) !== -1;
    });
  }

  function productRange(p) {
    var sizes = p.sizes || [];
    var lo = Infinity, hi = 0;
    sizes.forEach(function (s) {
      var price = Number(s.unit_price) || 0;
      if (price <= 0) return;
      if (price < lo) lo = price;
      if (price > hi) hi = price;
    });
    if (lo === Infinity) lo = 0;
    return lo > 0 ? (lo === hi ? fmt(lo) : fmt(lo) + ' – ' + fmt(hi)) : '';
  }

  function productStock(p) {
    var available = (p.sizes || []).reduce(function (t, s) { return t + Number(s.available || 0); }, 0);
    return available;
  }

  function card(p, index) {
    var img = fallbackImage(p);
    var available = productStock(p);
    var swatches = (p.variants || []).slice(0, 6).map(function (v) {
      var color = v.swatch_hex || v.swatch || '#0b5d3b';
      return '<span class="store-swatch" style="background:' + esc(color) + '" title="' + esc(v.name || v.color_name || 'Variant') + '"></span>';
    }).join('');
    return '<article class="store-card">' +
      '<div class="store-media"><img src="' + esc(versionedImage(img)) + '" alt="' + esc(p.title) + '" loading="lazy" onerror="this.onerror=null;this.src=\'' + FALLBACK_IMG + '\'">' +
      '<span class="store-state">' + (available > 0 ? 'In stock' : 'Coming soon') + '</span></div>' +
      '<div class="store-info">' +
      '<span class="store-category">' + esc(CATEGORY_LABELS[p.category_slug] || p.category_slug || 'School uniform') + '</span>' +
      '<h3 class="store-title">' + esc(p.title) + '</h3>' +
      '<p class="store-desc">' + esc((p.description || 'Official Kingsway Preparatory School uniform item.').substring(0, 100)) +
      ((p.description || '').length > 100 ? '…' : '') + '</p>' +
      (swatches ? '<div class="store-swatches" aria-label="Available colours">' + swatches + '</div>' : '') +
      '<div class="store-footer"><div><div class="store-price">' + (available > 0 ? (productRange(p) || 'Price on request') : 'Coming soon') + '</div>' +
      '<small class="text-muted">' + ((p.sizes || []).length ? (p.sizes.length + ' size' + (p.sizes.length === 1 ? '' : 's')) : 'Details available') + '</small></div>' +
      '<button type="button" class="btn btn-sm btn-outline-success" data-open-product="' + p.id + '" aria-label="View ' + esc(p.title) + '"><i class="bi bi-eye me-1"></i>View</button></div>' +
      '</div></article>';
  }

  function renderGrid() {
    var el = document.getElementById('storeGrid');
    var loading = document.getElementById('storeLoading');
    if (loading) loading.style.display = 'none';
    if (!el) return;
    var visible = visibleProducts();
    setCount(visible.length);
    if (!visible.length) {
      el.innerHTML = '<div class="col-12"><div class="pp-card"><div class="pp-card-body text-center py-5">' +
        '<i class="bi bi-bag-x fs-1 d-block mb-3 text-muted"></i>' +
        '<p class="mb-0">No matching school uniforms are available right now.</p></div></div></div>';
      return;
    }
    el.innerHTML = visible.map(function (p, i) { return '<div class="col-12 col-sm-6 col-lg-4 col-xl-3">' + card(p, i) + '</div>'; }).join('');
    el.querySelectorAll('[data-open-product]').forEach(function (btn) {
      btn.addEventListener('click', function () { openProduct(Number(btn.dataset.openProduct)); });
    });
  }

  function renderCategories() {
    var el = document.getElementById('storeCategories');
    if (!el) return;
    var categories = Array.from(new Set(state.products.map(function (p) { return p.category_slug; })));
    el.innerHTML = '<button class="btn btn-sm' + (state.activeCategory === 'all' ? ' btn-success' : ' btn-outline-success') + '" data-category="all">All uniforms</button>' +
      categories.map(function (c) {
        return '<button class="btn btn-sm' + (state.activeCategory === c ? ' btn-success' : ' btn-outline-success') + '" data-category="' + esc(c) + '">' + esc(CATEGORY_LABELS[c] || c) + '</button>';
      }).join('');
    el.querySelectorAll('[data-category]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        state.activeCategory = btn.dataset.category;
        renderCategories();
        renderGrid();
      });
    });
  }

  /* ── Product quick view ─────────────────────────────────────────────── */

  function sizeOptions(p) {
    var options = '';
    (p.sizes || []).forEach(function (s) {
      if (Number(s.available || 0) <= 0) return;
      var price = Number(s.unit_price) || 0;
      options += '<label class="store-size' + (options === '' ? ' selected' : '') + '" data-variant="' + (s.variant_id || '') + '">' +
        '<input type="radio" name="storeSize" value="' + s.size_id + '" data-price="' + price + '" data-variant="' + (s.variant_id || '') + '"' + (options === '' ? ' checked' : '') + '>' +
        '<span>' + esc(s.size_label || s.size || 'Size') + '<small>' + fmt(price) + '</small></span></label>';
    });
    return options;
  }

  function variantOptions(p) {
    if (!(p.variants || []).length) return '';
    return '<div class="mb-3"><label class="form-label fw-semibold">Colour / design</label>' +
      '<div class="d-flex flex-wrap gap-2">' +
      p.variants.map(function (v) {
        var color = v.swatch_hex || v.swatch || '#0b5d3b';
        var isDefault = v.is_default === 1 || v.is_default === '1';
        return '<button type="button" class="store-variant' + (isDefault ? ' active' : '') + '" data-variant="' + v.id + '" title="' + esc(v.name || v.color_name || 'Variant') + '">' +
          '<span style="background:' + esc(color) + '"></span><small>' + esc(v.name || v.color_name || '') + '</small></button>';
      }).join('') + '</div></div>';
  }

  function filterSizesForVariant(variantId) {
    var bodyEl = document.getElementById('storeProductBody');
    if (!bodyEl) return;
    var hasVariants = bodyEl.querySelectorAll('.store-variant').length > 0;
    if (!hasVariants) return;
    var labels = bodyEl.querySelectorAll('.store-size');
    labels.forEach(function (label) {
      var matches = String(label.dataset.variant || '') === String(variantId || '');
      label.style.display = matches ? '' : 'none';
      if (!matches) {
        var radio = label.querySelector('input[name="storeSize"]');
        if (radio) radio.checked = false;
      }
    });
    var firstVisible = Array.prototype.slice.call(labels).find(function (l) { return l.style.display !== 'none'; });
    if (firstVisible) {
      labels.forEach(function (l) { l.classList.remove('selected'); });
      firstVisible.classList.add('selected');
      var firstRadio = firstVisible.querySelector('input[name="storeSize"]');
      if (firstRadio) firstRadio.checked = true;
    }
  }

  function openProduct(id) {
    var modalEl = document.getElementById('storeProductModal');
    if (!modalEl || !window.bootstrap) return;
    var p = state.products.find(function (x) { return String(x.id) === String(id); });
    if (!p) return;
    state.activeProductId = p.id;
    document.getElementById('storeProductTitle').textContent = p.title;
    var body = '';
    if (p.image_url) {
      body += '<div class="col-md-5"><div class="border rounded-3 overflow-hidden"><img src="' + esc(versionedImage(p.image_url)) + '" class="w-100" alt="' + esc(p.title) + '" onerror="this.onerror=null;this.src=\'' + FALLBACK_IMG + '\'"></div>' +
        ((p.variants || []).length ? '<small class="text-muted d-block mt-2">' + esc(p.description || '') + '</small>' : '') + '</div>';
    }
    body += '<div class="' + (p.image_url ? 'col-md-7' : 'col-12') + '">' +
      '<p class="text-muted small mb-3">' + esc(p.description || 'Official Kingsway Preparatory School uniform item.') + '</p>' +
      variantOptions(p) +
      '<label class="form-label fw-semibold">Select size</label>' +
      '<div class="store-size-grid">' + sizeOptions(p) + '</div>' +
      '<div class="d-flex align-items-center gap-2 mt-3 mb-3">' +
      '<label class="form-label mb-0 fw-semibold">Quantity</label>' +
      '<button type="button" class="btn btn-outline-secondary btn-sm" id="storeQtyMinus"><i class="bi bi-dash"></i></button>' +
      '<input type="number" class="form-control form-control-sm text-center" style="width:70px" id="storeQty" value="1" min="1" max="20">' +
      '<button type="button" class="btn btn-outline-secondary btn-sm" id="storeQtyPlus"><i class="bi bi-plus"></i></button>' +
      '</div>' +
      '<div class="d-flex gap-2">' +
      '<button class="btn btn-success flex-fill" type="button" id="storeAddToCart"><i class="bi bi-cart-plus me-1"></i>Add to cart</button>' +
      '</div>' +
      '<div id="storeProductError" class="alert alert-danger small d-none mt-3 mb-0"></div>' +
      '</div>';
    document.getElementById('storeProductBody').innerHTML = body;
    document.getElementById('storeProductBody').querySelectorAll('.store-variant').forEach(function (btn) {
      btn.addEventListener('click', function () {
        document.getElementById('storeProductBody').querySelectorAll('.store-variant').forEach(function (b) {
          b.classList.toggle('active', b === btn);
        });
        filterSizesForVariant(btn.dataset.variant);
      });
    });
    document.getElementById('storeProductBody').querySelectorAll('.store-size').forEach(function (label) {
      label.addEventListener('click', function () {
        document.getElementById('storeProductBody').querySelectorAll('.store-size').forEach(function (l) {
          l.classList.toggle('selected', l === label);
        });
      });
    });
    var qty = document.getElementById('storeQty');
    document.getElementById('storeQtyMinus').addEventListener('click', function () {
      if (Number(qty.value) > 1) qty.value = Number(qty.value) - 1;
    });
    document.getElementById('storeQtyPlus').addEventListener('click', function () {
      if (Number(qty.value) < 20) qty.value = Number(qty.value) + 1;
    });
    var defaultVariant = document.getElementById('storeProductBody').querySelector('.store-variant.active');
    if (defaultVariant) filterSizesForVariant(defaultVariant.dataset.variant);
    var addBtn = document.getElementById('storeAddToCart');
    addBtn.addEventListener('click', function () { addToCart(); });
    bootstrap.Modal.getOrCreateInstance(modalEl).show();
  }

  function productError(message) {
    var el = document.getElementById('storeProductError');
    if (!el) return;
    el.textContent = message;
    el.classList.remove('d-none');
  }

  async function addToCart() {
    hideProductError();
    var modalEl = document.getElementById('storeProductModal');
    var bodyEl = document.getElementById('storeProductBody');
    var selectedSize = bodyEl.querySelector('input[name="storeSize"]:checked');
    if (!selectedSize) { productError('Please select a size first.'); return; }
    var variantBtn = bodyEl.querySelector('.store-variant.active');
    var qty = Number(document.getElementById('storeQty').value) || 1;
    var payload = {
      product_id: state.activeProductId,
      variant_id: variantBtn ? Number(variantBtn.dataset.variant) : null,
      size_id: Number(selectedSize.value),
      quantity: qty,
    };
    try {
      var resp = await P.apiFetch('/uniform-cart', 'POST', payload);
      var d = resp.data !== undefined ? resp.data : resp;
      setCartCount(d.count || 0);
      var inst = bootstrap.Modal.getInstance(modalEl);
      if (inst) inst.hide();
      var cartBtn = document.getElementById('btnStoreCart');
      if (cartBtn) {
        var original = cartBtn.innerHTML;
        cartBtn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Added to cart';
        setTimeout(function () { cartBtn.innerHTML = original; updateCartBadge(); }, 1600);
      }
    } catch (e) {
      productError(e.message || 'Could not add to cart.');
    }
  }

  /* ── Cart offcanvas ─────────────────────────────────────────────────── */

  function cartItemRow(it) {
    return '<div class="d-flex gap-3 align-items-start border-bottom pb-3 mb-3">' +
      '<img src="' + esc(fallbackImage(it)) + '" class="rounded-2" style="width:64px;height:64px;object-fit:cover" alt="' + esc(it.title) + '" onerror="this.onerror=null;this.src=\'' + FALLBACK_IMG + '\'">' +
      '<div class="flex-fill">' +
      '<strong class="d-block">' + esc(it.title) + '</strong>' +
      '<small class="text-muted d-block">' + esc(it.size_label || it.size || '') + (it.variant_name ? ' · ' + esc(it.variant_name) : '') + '</small>' +
      '<div class="d-flex align-items-center gap-2 mt-2">' +
      '<button class="btn btn-outline-secondary btn-sm px-2" type="button" data-cart-minus="' + it.id + '"><i class="bi bi-dash"></i></button>' +
      '<span class="fw-semibold">' + Number(it.quantity || 0) + '</span>' +
      '<button class="btn btn-outline-secondary btn-sm px-2" type="button" data-cart-plus="' + it.id + '"><i class="bi bi-plus"></i></button>' +
      '<span class="ms-auto fw-semibold text-success">' + fmt(it.line_total) + '</span>' +
      '<button class="btn btn-link btn-sm text-danger p-0 ms-1" type="button" data-cart-remove="' + it.id + '" aria-label="Remove"><i class="bi bi-trash"></i></button>' +
      '</div></div></div>';
  }

  async function renderCart() {
    var body = document.getElementById('storeCartBody');
    if (!body) return;
    try {
      var resp = await P.apiFetch('/uniform-cart', 'GET');
      var d = resp.data !== undefined ? resp.data : resp;
      var items = d.items || [];
      setCartCount(d.count || 0);
      if (!items.length) {
        body.innerHTML = '<div class="text-center py-5 text-muted"><i class="bi bi-cart-x fs-1 d-block mb-3"></i>' +
          '<p>Your cart is empty. Browse the catalogue and add sizes to get started.</p>' +
          '<button class="btn btn-success btn-sm" type="button" data-close-cart><i class="bi bi-bag-heart me-1"></i>Browse uniforms</button></div>';
        return;
      }
      body.innerHTML = '<div id="storeCartItems">' + items.map(cartItemRow).join('') + '</div>' +
        '<div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">' +
        '<strong>Total</strong><strong class="fs-5 text-success">' + fmt(d.total) + '</strong></div>' +
        '<button class="btn btn-success w-100 mt-3 py-2 fw-semibold" type="button" id="storeProceedCheckout">' +
        '<i class="bi bi-shield-lock me-1"></i>Proceed to checkout</button>';
      body.querySelectorAll('[data-cart-plus]').forEach(function (btn) {
        btn.addEventListener('click', function () { updateCartLine(Number(btn.dataset.cartPlus), 1); });
      });
      body.querySelectorAll('[data-cart-minus]').forEach(function (btn) {
        btn.addEventListener('click', function () { updateCartLine(Number(btn.dataset.cartMinus), -1); });
      });
      body.querySelectorAll('[data-cart-remove]').forEach(function (btn) {
        btn.addEventListener('click', function () { removeCartLine(Number(btn.dataset.cartRemove)); });
      });
      body.querySelector('[data-close-cart]').addEventListener('click', function () {
        var inst = bootstrap.Offcanvas.getInstance(document.getElementById('storeCartDrawer'));
        if (inst) inst.hide();
      });
      body.querySelector('#storeProceedCheckout').addEventListener('click', function () { openCheckout(d); });
    } catch (e) {
      body.innerHTML = '<div class="alert alert-danger">' + esc(e.message || 'Could not load your cart.') + '</div>';
    }
  }

  function currentCartLineQty(lineId, items) {
    var it = items.find(function (x) { return String(x.id) === String(lineId); });
    return it ? Number(it.quantity || 0) : 0;
  }

  async function updateCartLine(lineId, delta) {
    var body = document.getElementById('storeCartBody');
    try {
      var resp = await P.apiFetch('/uniform-cart', 'GET');
      var d = resp.data !== undefined ? resp.data : resp;
      var current = currentCartLineQty(lineId, d.items || []);
      var next = current + delta;
      if (next <= 0) { await removeCartLine(lineId); return; }
      await P.apiFetch('/uniform-cart/' + lineId, 'PUT', { quantity: next });
      renderCart();
    } catch (e) {
      if (body) body.insertAdjacentHTML('afterbegin', '<div class="alert alert-warning">' + esc(e.message || 'Update failed.') + '</div>');
    }
  }

  async function removeCartLine(lineId) {
    try {
      await P.apiFetch('/uniform-cart/' + lineId, 'DELETE');
      renderCart();
    } catch (e) { /* leave cart as-is */ }
  }

  function updateCartBadge() {
    P.apiFetch('/uniform-cart', 'GET')
      .then(function (resp) {
        var d = resp.data !== undefined ? resp.data : resp;
        setCartCount(d.count || 0);
      })
      .catch(function () {});
  }

  /* ── Checkout ───────────────────────────────────────────────────────── */

  function renderPaymentOptions() {
    var el = document.getElementById('storeCheckoutPayments');
    if (!el) return;
    var options = (state.payments || []).filter(function (o) {
      return o.channel !== 'cash' && o.channel !== 'bank_transfer' && o.channel !== 'cheque';
    });
    if (!options.length) {
      el.innerHTML = '<div class="alert alert-warning">No online payment methods are available right now. Please visit the school uniform store to pay in person.</div>';
    }
    el.innerHTML = options.map(function (o, i) {
      var account = (o.accounts || [])[0] || {};
      var checked = i === 0 ? ' checked' : '';
      return '<div class="form-check mb-2">' +
        '<input class="form-check-input store-pay-channel" type="radio" name="storePayChannel" value="' + esc(o.channel) + '" data-account="' + Number(account.id || 0) + '"' + checked + ' id="storePay' + i + '">' +
        '<label class="form-check-label" for="storePay' + i + '"><i class="bi bi-' + (o.channel === 'daraja_mpesa' ? 'phone' : 'bank') + ' me-1"></i>' + esc(o.label) +
        (account.account_name ? '<small class="d-block text-muted">' + esc(account.account_name) + ' · ' + esc(account.account_identifier || '') + '</small>' : '') +
        '</label></div>';
    }).join('');
    el.querySelectorAll('.store-pay-channel').forEach(function (radio) {
      radio.addEventListener('change', function () {
        state.checkoutChannel = radio.value;
        state.checkoutAccount = Number(radio.dataset.account || 0);
      });
    });
  }

  function openCheckout(cart) {
    var modalEl = document.getElementById('storeCheckoutModal');
    if (!modalEl || !window.bootstrap) return;
    hideCheckoutError();
    document.getElementById('storeCheckoutForm').style.display = 'block';
    document.getElementById('storeCheckoutWaiting').style.display = 'none';
    var summary = (cart.items || []).map(function (it) {
      return '<div class="d-flex justify-content-between"><span>' + esc(it.title) + ' × ' + Number(it.quantity || 0) + '</span><span>' + fmt(it.line_total) + '</span></div>';
    }).join('');
    summary += '<div class="d-flex justify-content-between fw-semibold border-top mt-2 pt-2"><span>Total</span><span>' + fmt(cart.total) + '</span></div>';
    document.getElementById('storeCheckoutSummary').innerHTML = summary;
    document.getElementById('btnStoreCheckout').disabled = false;
    var studentSel = document.getElementById('storeCheckoutStudent');
    var prior = studentSel.value;
    studentSel.innerHTML = state.children.map(function (c) {
      return '<option value="' + c.id + '">' + esc(c.first_name + ' ' + c.last_name) + (c.class_name ? ' — ' + esc(c.class_name) : '') + '</option>';
    }).join('') || '<option value="">No learners linked</option>';
    if (prior && [...studentSel.options].some(function (o) { return o.value === prior; })) studentSel.value = prior;
    renderPaymentOptions();
    var phone = document.getElementById('storeCheckoutPhone');
    if (!phone.value) phone.value = '';
    bootstrap.Modal.getOrCreateInstance(modalEl).show();
  }

  function hideCheckoutError() {
    var el = document.getElementById('storeCheckoutError');
    if (el) el.classList.add('d-none');
  }

  function checkoutError(message) {
    var el = document.getElementById('storeCheckoutError');
    if (!el) return;
    el.textContent = message;
    el.classList.remove('d-none');
  }

  function setCheckoutButton(busy) {
    var btn = document.getElementById('btnStoreCheckout');
    var spinner = document.getElementById('storeCheckoutSpinner');
    if (!btn || !spinner) return;
    btn.disabled = busy;
    spinner.classList.toggle('d-none', !busy);
  }

  async function placeCheckout() {
    hideCheckoutError();
    var studentId = document.getElementById('storeCheckoutStudent').value;
    if (!studentId) { checkoutError('Please select the learner receiving this order.'); return; }
    var channel = state.checkoutChannel || 'daraja_mpesa';
    var manual = ['cash', 'bank_transfer', 'cheque'].indexOf(channel) !== -1;
    if (manual) { checkoutError('In-person payments must be completed at the school uniform store.'); return; }
    if (!state.checkoutAccount) {
      var firstRadio = document.querySelector('.store-pay-channel:checked');
      state.checkoutAccount = firstRadio ? Number(firstRadio.dataset.account || 0) : 0;
    }
    if (!state.checkoutAccount) { checkoutError('No payment account is configured for this method.'); return; }
    var phone = String(document.getElementById('storeCheckoutPhone').value || '').trim();
    if (!phone) { checkoutError('Enter the M-Pesa phone number registered with your provider.'); return; }
    var note = String(document.getElementById('storeCheckoutNote').value || '').trim();
    var payload = {
      student_id: Number(studentId),
      channel: channel,
      financial_account_id: state.checkoutAccount,
      phone: phone,
      note: note || null,
    };
    setCheckoutButton(true);
    try {
      var resp = await P.apiFetch('/uniform-checkout-payment', 'POST', payload);
      var d = resp.data !== undefined ? resp.data : resp;
      setCheckoutButton(false);
      document.getElementById('storeCheckoutForm').style.display = 'none';
      document.getElementById('storeCheckoutWaiting').style.display = 'block';
      var msg = 'Your order has been placed. A payment prompt has been sent to your phone — complete it to confirm.';
      if (d.order && d.order.order_reference) {
        msg = 'Order ' + d.order.order_reference + ' placed. A payment prompt has been sent to your phone — complete it to confirm.';
      }
      if (d.provider && d.provider.message) {
        msg += ' (' + d.provider.message + ')';
      }
      document.getElementById('storeCheckoutWaitMsg').textContent = msg;
      var checkoutId = d.checkout_request_id || (d.provider && d.provider.checkout_request_id) || null;
      if (checkoutId && channel === 'daraja_mpesa') {
        startCheckoutPolling(checkoutId);
      }
    } catch (e) {
      setCheckoutButton(false);
      checkoutError(e.message || 'Could not place the order. Try again.');
    }
  }

  function startCheckoutPolling(checkoutRequestId) {
    stopCheckoutPolling();
    state.statusTimer = setInterval(function () {
      P.apiFetch('/mpesa-status/' + encodeURIComponent(checkoutRequestId), 'GET')
        .then(function (resp) {
          var d = resp.data !== undefined ? resp.data : resp;
          if (d.ResultCode === '0' || d.resultCode === '0' || d.status === 'completed' || d.status === 'paid') {
            stopCheckoutPolling();
            document.getElementById('storeCheckoutWaitMsg').textContent = 'Payment confirmed! Your order is now processing.';
            refreshOrders();
          }
        })
        .catch(function () {});
    }, 6000);
    setTimeout(stopCheckoutPolling, 3 * 60 * 1000);
  }

  function stopCheckoutPolling() {
    if (state.statusTimer) {
      clearInterval(state.statusTimer);
      state.statusTimer = null;
    }
  }

  /* ── Orders ─────────────────────────────────────────────────────────── */

  function orderStatusBadge(o) {
    var map = {
      'pending_payment': ['warning', 'Awaiting payment'],
      'payment_processing': ['info', 'Payment in progress'],
      'paid': ['success', 'Paid'],
      'preparing': ['primary', 'Preparing'],
      'ready': ['dark', 'Ready for collection'],
      'completed': ['success', 'Completed'],
      'fulfilled': ['success', 'Fulfilled'],
      'cancelled': ['secondary', 'Cancelled'],
    };
    var entry = map[o.status] || ['secondary', o.status || 'Order'];
    return '<span class="badge bg-' + entry[0] + '-subtle text-' + entry[0] + '">' + entry[1] + '</span>';
  }

  function paymentStatusPill(o) {
    if (o.payment_status === 'paid') return '<span class="badge bg-success-subtle text-success"><i class="bi bi-check-circle me-1"></i>Paid</span>';
    if (o.payment_status === 'failed') return '<span class="badge bg-danger-subtle text-danger">Payment failed</span>';
    if (o.payment_status === 'refunded') return '<span class="badge bg-secondary-subtle text-secondary">Refunded</span>';
    if (o.payment_status === 'pending') return '<span class="badge bg-warning-subtle text-warning">Pending</span>';
    return '<span class="badge bg-warning-subtle text-warning">Pending</span>';
  }

  function orderRow(o) {
    var lines = (o.items || []).map(function (it) {
      return esc(it.product_title) + (it.variant_name ? ' (' + esc(it.variant_name) + ')' : '') + ' × ' + Number(it.quantity || 0);
    }).join(', ');
    var canCancel = o.status === 'pending_payment' || o.status === 'payment_processing';
    var canPay = (o.status === 'pending_payment' || o.status === 'payment_processing') && o.payment_status !== 'paid';
    var actions = '<div class="d-flex gap-2 mt-2">';
    if (canPay) actions += '<button class="btn btn-sm btn-outline-success" type="button" data-order-pay="' + o.id + '"><i class="bi bi-phone me-1"></i>Pay now</button>';
    if (canCancel) actions += '<button class="btn btn-sm btn-outline-secondary" type="button" data-order-cancel="' + o.id + '"><i class="bi bi-x-circle me-1"></i>Cancel</button>';
    actions += '</div>';
    return '<div class="border rounded-3 p-3 mb-3 store-order">' +
      '<div class="d-flex flex-wrap justify-content-between align-items-center gap-2">' +
      '<div><strong class="me-2">' + esc(o.order_reference || 'Order') + '</strong>' + orderStatusBadge(o) + ' ' + paymentStatusPill(o) + '</div>' +
      '<span class="text-muted small">' + esc((o.created_at || '').replace('T', ' ')) + '</span></div>' +
      '<div class="small text-muted mt-2">' + lines + '</div>' +
      '<div class="d-flex justify-content-between mt-2"><span class="text-muted small">' + esc(o.customer_note || '') + '</span>' +
      '<strong class="text-success">' + fmt(o.total_amount) + '</strong></div>' +
      actions +
      (o.payment_status === 'paid' ? '<div class="alert alert-success small mt-2 mb-0 py-2"><i class="bi bi-info-circle me-1"></i>Payment received. The school store will prepare your order.</div>' : '') +
      '</div>';
  }

  async function renderOrders() {
    var el = document.getElementById('storeOrders');
    if (!el) return;
    try {
      var resp = await P.apiFetch('/uniform-orders', 'GET');
      var d = resp.data !== undefined ? resp.data : resp;
      var orders = d.orders || [];
      if (!orders.length) {
        el.innerHTML = '<div class="text-center py-4 text-muted"><i class="bi bi-bag-check fs-1 d-block mb-2"></i>' +
          '<p class="mb-0">No uniform orders yet. When you place an order it will appear here.</p></div>';
        return;
      }
      el.innerHTML = orders.map(orderRow).join('');
      el.querySelectorAll('[data-order-cancel]').forEach(function (btn) {
        btn.addEventListener('click', function () { cancelOrder(Number(btn.dataset.orderCancel)); });
      });
      el.querySelectorAll('[data-order-pay]').forEach(function (btn) {
        btn.addEventListener('click', function () { retryOrderPayment(Number(btn.dataset.orderPay)); });
      });
    } catch (e) {
      el.innerHTML = '<div class="alert alert-danger">' + esc(e.message || 'Could not load orders.') + '</div>';
    }
  }

  async function refreshOrders() { await renderOrders(); }

  async function cancelOrder(orderId) {
    if (!window.confirm('Cancel this order? Reserved stock will be released.')) return;
    try {
      await P.apiFetch('/uniform-orders/' + orderId, 'DELETE');
      refreshOrders();
    } catch (e) {
      window.alert(e.message || 'Could not cancel the order.');
    }
  }

  async function retryOrderPayment(orderId) {
    var channel = state.checkoutChannel || 'daraja_mpesa';
    var account = state.checkoutAccount;
    if (!account) {
      var payResp = await P.apiFetch('/uniform-payment-options', 'GET');
      var d = payResp.data !== undefined ? payResp.data : payResp;
      state.payments = d.options || [];
      var options = state.payments.filter(function (o) {
        return o.channel !== 'cash' && o.channel !== 'bank_transfer' && o.channel !== 'cheque';
      });
      if (!options.length) { window.alert('No online payment method is available right now.'); return; }
      state.checkoutChannel = options[0].channel;
      channel = state.checkoutChannel;
      var firstAccount = (options[0].accounts || [])[0] || { id: 0 };
      state.checkoutAccount = Number(firstAccount.id || 0);
      account = state.checkoutAccount;
    }
    var phone = String(document.getElementById('storeCheckoutPhone').value || '').trim();
    if (!phone) phone = window.prompt('Enter the M-Pesa phone number for payment:', '2547');
    if (!phone) return;
    if (!account) { window.alert('No payment account is configured for this method.'); return; }
    var payload = { channel: channel, financial_account_id: account, phone: phone };
    try {
      var resp = await P.apiFetch('/uniform-order-payment-retry/' + orderId, 'POST', payload);
      var d = resp.data !== undefined ? resp.data : resp;
      var checkoutId = d.checkout_request_id || (d.provider && d.provider.checkout_request_id) || null;
      if (checkoutId && channel === 'daraja_mpesa') startCheckoutPolling(checkoutId);
      renderOrders();
      if (d.provider && d.provider.message) {
        window.alert('Payment prompt sent: ' + d.provider.message);
      } else {
        window.alert('A payment prompt has been sent to ' + phone + '. Complete it on your phone.');
      }
    } catch (e) {
      window.alert(e.message || 'Could not start the payment.');
    }
  }

  /* ── CSV / print ────────────────────────────────────────────────────── */

  function ordersCsv(rows) {
    var headers = ['Reference', 'Status', 'Payment', 'Total', 'Created', 'Items', 'Note'];
    var data = rows.map(function (o) {
      return [
        o.order_reference || '',
        o.status || '',
        o.payment_status || '',
        o.total_amount || 0,
        o.created_at || '',
        (o.items || []).map(function (it) { return it.product_title + ' x' + it.quantity; }).join('; '),
        o.customer_note || '',
      ];
    });
    return P.csvFromHeaders(headers, data);
  }

  function catalogCsv(rows) {
    var headers = ['Category', 'Product', 'Description', 'Price range', 'Sizes', 'Stock', 'Type'];
    var data = rows.map(function (p) {
      var sizes = (p.sizes || []).filter(function (s) { return Number(s.available || 0) > 0; });
      var stock = sizes.reduce(function (t, s) { return t + Number(s.available || 0); }, 0);
      return [
        p.category_slug || '',
        p.title || '',
        p.description || '',
        productRange(p),
        sizes.map(function (s) { return s.size_label || s.size || ''; }).join('; '),
        stock,
        p.product_type || '',
      ];
    });
    return P.csvFromHeaders(headers, data);
  }

  async function exportOrdersCsv() {
    var resp = await P.apiFetch('/uniform-orders', 'GET');
    var d = resp.data !== undefined ? resp.data : resp;
    P.exportCsv('kingsway-uniform-orders.csv', ordersCsv(d.orders || []));
  }

  function exportCatalogCsv() {
    P.exportCsv('kingsway-uniform-catalog.csv', catalogCsv(state.products));
  }

  function printSection() {
    P.printSection();
  }

  /* ── Events ─────────────────────────────────────────────────────────── */

  function bindEvents() {
    var search = document.getElementById('storeSearch');
    if (search) search.addEventListener('input', function () {
      state.query = search.value.toLowerCase().trim();
      renderGrid();
    });
    var cartBtn = document.getElementById('btnStoreCart');
    if (cartBtn) cartBtn.addEventListener('click', function () {
      var drawer = document.getElementById('storeCartDrawer');
      if (drawer && window.bootstrap) bootstrap.Offcanvas.getOrCreateInstance(drawer).show();
      renderCart();
    });
    var csvBtn = document.getElementById('btnStoreCatalogCsv');
    if (csvBtn) csvBtn.addEventListener('click', exportCatalogCsv);
    var printBtn = document.getElementById('btnStoreCatalogPrint');
    if (printBtn) printBtn.addEventListener('click', printSection);
    var ordersCsvBtn = document.getElementById('btnOrdersCsv');
    if (ordersCsvBtn) ordersCsvBtn.addEventListener('click', exportOrdersCsv);
    var ordersPrintBtn = document.getElementById('btnOrdersPrint');
    if (ordersPrintBtn) ordersPrintBtn.addEventListener('click', printSection);
    var checkoutBtn = document.getElementById('btnStoreCheckout');
    if (checkoutBtn) checkoutBtn.addEventListener('click', placeCheckout);
    var doneBtn = document.getElementById('btnStoreCheckoutDone');
    if (doneBtn) doneBtn.addEventListener('click', function () {
      stopCheckoutPolling();
      var modal = document.getElementById('storeCheckoutModal');
      if (modal && window.bootstrap) {
        var inst = bootstrap.Modal.getInstance(modal);
        if (inst) inst.hide();
      }
      refreshOrders();
    });
    var ordersAnchor = document.getElementById('btnStoreOrders');
    if (ordersAnchor) ordersAnchor.addEventListener('click', function (e) {
      e.preventDefault();
      document.getElementById('ppOrders').scrollIntoView({ behavior: 'smooth' });
    });
  }

  /* ── Init ───────────────────────────────────────────────────────────── */

  async function init() {
    bindEvents();
    if (!(await P.ensureAuth())) return;
    try {
      var dash = await P.loadDashboard();
      state.children = dash.children || [];
      var [catResp, cartResp, ordersResp, payResp] = await Promise.all([
        P.apiFetch('/uniform-catalog', 'GET'),
        P.apiFetch('/uniform-cart', 'GET'),
        P.apiFetch('/uniform-orders', 'GET'),
        P.apiFetch('/uniform-payment-options', 'GET'),
      ]);
      var cat = catResp.data !== undefined ? catResp.data : catResp;
      var cart = cartResp.data !== undefined ? cartResp.data : cartResp;
      state.products = cat.products || [];
      state.payments = (payResp.data !== undefined ? payResp.data : payResp).options || [];
      setCartCount(cart.count || 0);
      renderCategories();
      renderGrid();
      await renderOrders();
    } catch (e) {
      showError(e.message || 'Could not load the uniform store. Please try again.');
      var loading = document.getElementById('storeLoading');
      if (loading) loading.style.display = 'none';
    }
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();