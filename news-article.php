<?php
// Thin public template — no SQL, no service calls, no server-side data.
// The article is fetched client-side by js/pages/public/news-article.js via
// /api/website/news/{id} (view counting stays server-side in the manager).
$appBase    = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '')),'/');

$pageTitle  = 'News';
$activePage = 'news';
$canonicalRouteKey = 'news-article';
$pageScript = 'news-article';
require_once __DIR__ . '/public/layout/public_data.php';
?>
<?php require __DIR__ . '/public/layout/detail_seo.php'; ?>
<?php include __DIR__ . '/public/layout/header.php'; ?>

<div class="page-header">
  <div class="container position-relative" style="z-index:1">
    <nav aria-label="breadcrumb"><ol class="breadcrumb mb-2">
      <li class="breadcrumb-item"><a href="<?= $appBase ?>/">Home</a></li>
      <li class="breadcrumb-item"><a href="<?= $appBase ?>/news">News</a></li>
      <li class="breadcrumb-item active" id="article-crumb">Article</li>
    </ol></nav>
    <h1 class="page-title" id="article-title-header" style="font-size:clamp(1.4rem,3vw,2rem)"><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
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
