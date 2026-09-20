<?php
$appBase    = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '')),'/');
if ($appBase === '.') $appBase = '';
$pageTitle  = 'Downloads';
$activePage = 'downloads';
$pageScript = 'downloads';
// Document categories are rendered by js/pages/public/downloads.js via
// GET /api/website/downloads (grouped by category in the browser).
require_once __DIR__ . '/public/layout/public_data.php';
?>
<?php include __DIR__ . '/public/layout/header.php'; ?>

<div class="page-header">
  <div class="container position-relative" style="z-index:1">
    <nav aria-label="breadcrumb"><ol class="breadcrumb mb-2">
      <li class="breadcrumb-item"><a href="<?= $appBase ?>/index.php">Home</a></li>
      <li class="breadcrumb-item active">Downloads</li>
    </ol></nav>
    <h1 class="page-title">Downloads &amp; Resources</h1>
    <p class="mt-2" style="color:rgba(255,255,255,.7)">Forms, guides, policies and school documents available for download</p>
  </div>
</div>

<section class="section">
  <div class="container">

    <!-- Search -->
    <div class="row justify-content-center mb-5 reveal">
      <div class="col-lg-6">
        <div class="input-group shadow-sm">
          <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
          <input type="text" id="dlSearch" class="form-control border-start-0 py-3" placeholder="Search documents…">
        </div>
      </div>
    </div>

    <div id="dl-year-selector" class="row justify-content-center mb-4" style="display:none">
      <div class="col-md-5 col-lg-4">
        <label for="dlAcademicYear" class="form-label fw-semibold">Academic year</label>
        <select id="dlAcademicYear" class="form-select"></select>
      </div>
    </div>

    <div id="dl-categories"></div>

    <!-- Note -->
    <div class="p-4 rounded-4 reveal" style="background:#fff8e1;border-left:4px solid var(--gold)">
      <h6 class="fw-bold mb-1"><i class="bi bi-info-circle-fill text-warning me-2"></i>Note on Downloads</h6>
      <p class="text-muted small mb-0">
        Some documents require Adobe Reader or Microsoft Office to open. If you cannot find what you're looking for,
        please <a href="<?= $appBase ?>/index.php?route=r3443047604ab" class="text-success fw-semibold">contact our office</a> and we'll be happy to assist.
        Documents are updated at the start of each academic year.
      </p>
    </div>

  </div>
</section>

<?php include __DIR__ . '/public/layout/footer.php'; ?>
