<?php
declare(strict_types=1);
/* Uniform Store — in-cpanel parent commerce: catalogue, cart, orders, checkout. */
$parentPageTitle  = 'Uniform Store';
$parentActive     = 'store';
$parentPageScript = 'parents/uniform_catalog';
$parentSidebar    = false;
require __DIR__ . '/_head.php';
?>
<div class="container pp-container">
  <div class="pp-card mb-3">
    <div class="pp-card-header pp-card-header-wrap">
      <div>
        <h1 class="mb-1"><i class="bi bi-bag-heart-fill me-2 text-success"></i>Uniform Store</h1>
        <p class="text-muted mb-0 small">Order official Kingsway school uniform from your family centre. Size first, then secure payment.</p>
      </div>
      <div class="d-flex gap-2 align-items-center">
        <a class="btn btn-outline-success btn-sm" type="button" id="btnStoreOrders" href="#ppOrders"><i class="bi bi-bag-check me-1"></i>My orders</a>
        <button class="btn btn-success btn-sm" type="button" id="btnStoreCart"><i class="bi bi-cart3 me-1"></i>Cart <span class="badge bg-white text-success ms-1" id="storeCartCount">0</span></button>
      </div>
    </div>
  </div>

  <!-- Category + search filter bar (auto-loads; no submit button) -->
  <div class="pp-card mb-3">
    <div class="pp-card-body py-3">
      <div class="row g-2 align-items-center">
        <div class="col-lg-7">
          <div class="d-flex flex-wrap gap-2" id="storeCategories"></div>
        </div>
        <div class="col-lg-5">
          <div class="input-group">
            <span class="input-group-text bg-white"><i class="bi bi-search text-success"></i></span>
            <input type="search" class="form-control" id="storeSearch" placeholder="Search uniforms, sizes, colours…" aria-label="Search the uniform store">
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="mb-3 d-flex justify-content-between align-items-center">
    <span class="text-muted small" id="storeCount">0 items</span>
    <div class="d-flex gap-2">
      <button class="btn btn-outline-success btn-sm" type="button" id="btnStoreCatalogCsv"><i class="bi bi-filetype-csv me-1"></i>CSV</button>
      <button class="btn btn-outline-success btn-sm" type="button" id="btnStoreCatalogPrint"><i class="bi bi-printer me-1"></i>Print</button>
    </div>
  </div>

  <div id="storeError" class="alert alert-danger d-none"></div>
  <div id="storeLoading" class="text-center py-5">
    <div class="spinner-border text-success"></div>
  </div>
  <div class="row g-3" id="storeGrid"></div>

  <!-- Orders -->
  <div class="pp-card mt-4" id="ppOrders">
    <div class="pp-card-header pp-card-header-wrap">
      <div>
        <h2 class="mb-1 fs-4"><i class="bi bi-bag-check me-2 text-success"></i>My orders</h2>
        <p class="text-muted mb-0 small">Pending and paid orders for your family's uniforms.</p>
      </div>
      <div class="d-flex gap-2">
        <button class="btn btn-outline-success btn-sm" type="button" id="btnOrdersCsv"><i class="bi bi-filetype-csv me-1"></i>CSV</button>
        <button class="btn btn-outline-success btn-sm" type="button" id="btnOrdersPrint"><i class="bi bi-printer me-1"></i>Print</button>
      </div>
    </div>
    <div class="pp-card-body" id="storeOrders"></div>
  </div>
</div>

<!-- Product quick-view modal -->
<div class="modal fade" id="storeProductModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content border-0 shadow rounded-4">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title" id="storeProductTitle"><i class="bi bi-bag-heart me-2"></i>Item details</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
        <div id="storeProductBody" class="row g-4"></div>
      </div>
    </div>
  </div>
</div>

<!-- Cart offcanvas -->
<div class="offcanvas offcanvas-end pp-drawer" tabindex="-1" id="storeCartDrawer" aria-labelledby="storeCartTitle">
  <div class="offcanvas-header pp-drawer-header">
    <span class="offcanvas-title" id="storeCartTitle"><i class="bi bi-cart3 me-2"></i>Your cart</span>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body pp-drawer-body" id="storeCartBody"></div>
</div>

<!-- Checkout modal -->
<div class="modal fade" id="storeCheckoutModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content border-0 shadow rounded-4">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title"><i class="bi bi-credit-card me-2"></i>Checkout</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
        <div id="storeCheckoutForm">
          <div class="mb-3">
            <label class="form-label fw-semibold">Item summary</label>
            <div id="storeCheckoutSummary" class="border rounded-3 p-2 bg-light small"></div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Learner receiving this order <span class="text-danger">*</span></label>
            <select id="storeCheckoutStudent" class="form-select"></select>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Payment method <span class="text-danger">*</span></label>
            <div id="storeCheckoutPayments"></div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">M-Pesa phone number</label>
            <input type="tel" id="storeCheckoutPhone" class="form-control" placeholder="2547XXXXXXXX">
            <div class="form-text">Only needed for M-Pesa. Enter the number registered with M-Pesa.</div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Note (optional)</label>
            <input type="text" id="storeCheckoutNote" class="form-control" placeholder="e.g. preferred pick-up time at the store">
          </div>
          <div id="storeCheckoutError" class="alert alert-danger d-none"></div>
          <button class="btn btn-success w-100 py-2 fw-semibold" type="button" id="btnStoreCheckout">
            <span class="spinner-border spinner-border-sm me-2 d-none" id="storeCheckoutSpinner"></span>
            <i class="bi bi-shield-lock me-2"></i>Place secure order
          </button>
        </div>
        <div id="storeCheckoutWaiting" class="text-center py-4" style="display:none">
          <div class="spinner-border text-success mb-3" style="width:3rem;height:3rem"></div>
          <h6>Order placed!</h6>
          <p class="text-muted small" id="storeCheckoutWaitMsg">Your payment prompt has been sent. Complete it on your phone to confirm the order.</p>
          <button class="btn btn-outline-secondary btn-sm mt-3" type="button" id="btnStoreCheckoutDone"><i class="bi bi-check-lg me-1"></i>View my orders</button>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/_foot.php'; ?>