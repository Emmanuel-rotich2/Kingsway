<?php
$appBase    = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '')),'/');
if ($appBase === '.') $appBase = '';
$pageTitle  = 'School Gallery';
$activePage = 'gallery';
$pageScript = 'gallery';
// Dynamic gallery is rendered by js/pages/public/gallery.js via /api/website/gallery.
require_once __DIR__ . '/public/layout/public_data.php';
?>
<?php include __DIR__ . '/public/layout/header.php'; ?>

<div class="page-header">
  <div class="container position-relative" style="z-index:1">
    <nav aria-label="breadcrumb"><ol class="breadcrumb mb-2">
      <li class="breadcrumb-item"><a href="<?= $appBase ?>/index.php">Home</a></li>
      <li class="breadcrumb-item active">School Gallery</li>
    </ol></nav>
    <h1 class="page-title">School <span>Gallery</span></h1>
    <p class="mt-2" style="color:rgba(255,255,255,.7);font-size:1rem">Moments from life at Kingsway Preparatory School.</p>
  </div>
</div>

<section class="section">
  <div class="container">
    <div class="text-center mb-5 reveal">
      <div class="section-label justify-content-center"><span>Browse the Gallery</span></div>
      <h2 class="section-title">Life at <span>Kingsway</span></h2>
      <p class="section-subtitle mx-auto">
        From morning assemblies to afternoon sports, from science labs to music festivals —
        every day at Kingsway is filled with purpose, joy, and growth.
      </p>
    </div>
    <div class="row g-3" id="public-gallery-grid">
      <div class="col-12 text-muted small p-4"><div class="spinner-border spinner-border-sm me-2"></div>Loading gallery…</div>
    </div>
    <div class="text-center mt-4 reveal">
      <a href="<?= $appBase ?>/index.php" class="btn btn-outline-success rounded-pill px-4">
        <i class="bi bi-house-door me-1"></i>Back to Home
      </a>
    </div>
  </div>
</section>

<!-- Gallery Lightbox Modal -->
<div class="modal fade gallery-lightbox" id="galleryLightbox" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold" id="lightboxCaption"></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0 text-center">
        <div id="lightboxImgWrap" class="d-block">
          <img id="lightboxImg" class="img-fluid" style="max-height:70vh">
        </div>
        <div id="lightboxVideoWrap" class="d-none">
          <video id="lightboxVideo" class="img-fluid" style="max-height:70vh;background:#000" controls autoplay playsinline></video>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/public/layout/footer.php'; ?>
<?php asset_script($appBase, 'js/pages/public/gallery.js'); ?>
