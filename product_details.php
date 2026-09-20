<?php
$appBase    = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '')),'/');
if ($appBase === '.') $appBase = '';
$pageTitle  = 'Product Details';
$activePage = 'uniforms';
$pageScript = 'product_details';
require_once __DIR__ . '/public/layout/public_data.php';
?>
<?php include __DIR__ . '/public/layout/header.php'; ?>

<div class="page-header" style="padding-bottom:1rem">
  <div class="container position-relative" style="z-index:1">
    <nav aria-label="breadcrumb"><ol class="breadcrumb mb-2">
      <li class="breadcrumb-item"><a href="<?= $appBase ?>/index.php">Home</a></li>
      <li class="breadcrumb-item"><a href="<?= $appBase ?>/index.php?route=r39d07ccbcf8a">Uniform Store</a></li>
      <li class="breadcrumb-item active" id="pd-crumb">Product</li>
    </ol></nav>
  </div>
</div>

<section class="section" style="padding-top:1rem">
  <div class="container">

    <div id="pdError" class="alert alert-danger d-none"></div>

    <!-- Loading -->
    <div id="pdLoading" class="text-center py-5">
      <div class="spinner-border text-success"></div>
    </div>

    <!-- Product layout — hidden until loaded -->
    <div id="pdContent" class="d-none">
      <div class="row g-5">

        <!-- LEFT: Images -->
        <div class="col-lg-7">
          <div class="card-modern p-2 mb-3">
            <div id="pdMainImage" style="aspect-ratio:4/3;border-radius:var(--radius-lg);overflow:hidden;background:#e8f1eb"></div>
          </div>
          <div id="pdThumbs" class="d-flex gap-2 flex-wrap"></div>
        </div>

        <!-- RIGHT: Info + Actions -->
        <div class="col-lg-5">

          <h2 class="fw-bold mb-2" id="pdTitle"></h2>
          <p class="text-muted mb-3" id="pdDesc"></p>

          <div id="pdVariantsSection" class="mb-4 d-none"><label class="form-label fw-semibold">Select colour / design</label><div id="pdVariants" class="d-flex flex-wrap gap-2"></div><div class="small text-muted mt-2" id="pdVariantName"></div></div>

          <!-- Size selector -->
          <div id="pdSizesSection" class="mb-4">
            <label class="form-label fw-semibold">Select size</label>
            <div id="pdSizes" class="d-flex flex-wrap gap-2"></div>
            <div id="pdPriceRow" class="mt-3 d-none">
              <span class="fw-bold fs-5" style="color:var(--green-dark)" id="pdPrice"></span>
              <span class="text-muted small ms-2" id="pdStock"></span>
            </div>
          </div>

          <!-- Actions -->
          <div class="d-flex gap-2 mb-4" id="pdActions">
            <button class="btn btn-success btn-lg rounded-pill flex-grow-1" id="pdAddToCart" disabled>
              <i class="bi bi-bag-plus me-2"></i>Add to Cart
            </button>
            <button class="btn btn-outline-success btn-lg rounded-pill" id="pdWishlist" title="Add to wishlist">
              <i class="bi bi-heart"></i>
            </button>
          </div>

          <!-- Checkout shortcut (shown when cart has items) -->
          <div id="pdCheckoutBox" class="d-none p-3 rounded-4 mb-4" style="background:#e8f5e9">
            <div class="d-flex align-items-center justify-content-between">
              <div>
                <div class="fw-semibold small">Your cart</div>
                <div class="text-muted small" id="pdCartInfo"></div>
              </div>
              <a href="<?= $appBase ?>/index.php?route=r7d32d41da298" class="btn btn-success btn-sm rounded-pill px-3">
                Checkout<i class="bi bi-arrow-right ms-1"></i>
              </a>
            </div>
          </div>

          <!-- Notes -->
          <div class="p-3 rounded-4" style="background:#f8f9fa">
            <h6 class="fw-bold small mb-2"><i class="bi bi-info-circle text-success me-1"></i>How to order</h6>
            <ol class="small text-muted mb-0 ps-3">
              <li class="mb-1">Select your size above and click <strong>Add to Cart</strong>.</li>
              <li class="mb-1">Sign in to the Parent Portal to complete payment via M-Pesa.</li>
              <li class="mb-0">Your uniform will be prepared for collection at the school store.</li>
            </ol>
          </div>

          <a href="<?= $appBase ?>/index.php?route=r39d07ccbcf8a" class="btn-kw-outline mt-4" style="font-size:.85rem">
            <i class="bi bi-arrow-left me-1"></i>Back to Uniform Store
          </a>

        </div>
      </div>
    </div>

  </div>
</section>

<section class="section pt-0"><div class="container"><div class="d-flex justify-content-between align-items-end mb-3"><div><span class="section-subtitle">Customer feedback</span><h2 class="h3 mb-0">Ratings &amp; reviews</h2></div><a href="<?= $appBase ?>/index.php?route=r7d32d41da298&tab=orders" class="btn btn-outline-success rounded-pill">Review a purchase</a></div><div id="pdReviews" class="row g-3"></div></div></section>

<style>
.pd-thumb{width:72px;height:72px;object-fit:cover;border-radius:var(--radius-md);cursor:pointer;border:3px solid transparent;transition:var(--transition);opacity:.7}
.pd-thumb.active,.pd-thumb:hover{border-color:var(--green);opacity:1}
.pd-size-btn{border:2px solid var(--gray-200);border-radius:var(--radius-md);padding:10px 18px;background:var(--white);cursor:pointer;transition:var(--transition);text-align:center;min-width:80px}
.pd-size-btn:hover{border-color:var(--green)}
.pd-size-btn.selected{border-color:var(--green);background:var(--green);color:#fff}
.pd-variant-btn{display:inline-flex;align-items:center;gap:.5rem;border:2px solid var(--gray-200);border-radius:999px;padding:.5rem .85rem;background:#fff;cursor:pointer}.pd-variant-btn.selected{border-color:var(--green);box-shadow:0 0 0 2px rgba(25,135,84,.12)}.pd-variant-swatch{width:20px;height:20px;border-radius:50%;border:1px solid rgba(0,0,0,.15)}
.pd-size-btn .pd-size-label{font-size:.82rem;font-weight:600;display:block}
.pd-size-btn .pd-size-price{font-size:.72rem;color:var(--gray-600);display:block;margin-top:2px}
.pd-size-btn.selected .pd-size-price{color:rgba(255,255,255,.8)}
</style>

<?php include __DIR__ . '/public/layout/footer.php'; ?>
