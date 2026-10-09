<?php
// Thin public template — no SQL, no service calls, no server-side data.
// The article is fetched client-side by js/pages/public/news-article.js via
// /api/website/news/{id} (view counting stays server-side in the manager).
$appBase    = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '')),'/');
if ($appBase === '.') $appBase = '';
$pageTitle  = 'News';
$activePage = 'news';
$pageScript = 'news-article';
require_once __DIR__ . '/public/layout/public_data.php';
?>
<?php include __DIR__ . '/public/layout/header.php'; ?>

<div class="page-header">
  <div class="container position-relative" style="z-index:1">
    <nav aria-label="breadcrumb"><ol class="breadcrumb mb-2">
      <li class="breadcrumb-item"><a href="<?= $appBase ?>/index.php">Home</a></li>
      <li class="breadcrumb-item"><a href="<?= $appBase ?>/index.php?route=rcbd4a4c91922">News</a></li>
      <li class="breadcrumb-item active" id="article-crumb">Article</li>
    </ol></nav>
    <h1 class="page-title" id="article-title-header" style="font-size:clamp(1.4rem,3vw,2rem)">Loading…</h1>
  </div>
</div>

<section class="section">
  <div class="container">
    <div class="row g-5">
      <div class="col-lg-8">
        <div id="article-main">
          <div class="text-center py-5 text-muted"><i class="bi bi-arrow-repeat"></i> Loading article…</div>
        </div>
      </div>
      <div class="col-lg-4">
        <div class="card-modern p-4 mb-4">
          <h6 class="fw-bold mb-3"><i class="bi bi-tags text-success me-2"></i>Browse by Category</h6>
          <div id="article-categories"></div>
        </div>
        <div id="article-related"></div>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/public/layout/footer.php'; ?>
