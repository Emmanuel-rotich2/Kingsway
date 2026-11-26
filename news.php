<?php
// Thin public template — no SQL, no service calls, no server-side data.
// All news content is fetched client-side by js/pages/public/news.js through
// /api/website/news (the login.php pattern: shell + JS controller + window.API).
$appBase    = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '')),'/');

$pageTitle  = 'News & Blog';
$activePage = 'news';
$pageScript = 'news';
require_once __DIR__ . '/public/layout/public_data.php';
require_once __DIR__ . '/public/layout/listing_seo.php';
?>
<?php include __DIR__ . '/public/layout/header.php'; ?>

<div class="page-header">
  <div class="container position-relative" style="z-index:1">
    <nav aria-label="breadcrumb"><ol class="breadcrumb mb-2">
      <li class="breadcrumb-item"><a href="<?= $appBase ?>/">Home</a></li>
      <li class="breadcrumb-item active">News &amp; Blog</li>
    </ol></nav>
    <h1 class="page-title">News &amp; Updates</h1>
    <p class="mt-2" style="color:rgba(255,255,255,.7)">Stay informed about life at Kingsway Preparatory School</p>
  </div>
</div>

<section class="section">
  <div class="container">
    <div class="d-flex flex-wrap gap-2 mb-5 reveal" id="news-categories"></div>
    <div id="news-featured"></div>
    <div id="news-grid">
      <?php kw_public_seo_cards('news'); ?>
    </div>
    <nav class="mt-5 d-flex justify-content-center reveal" id="news-pagination"></nav>
  </div>
</section>

<?php include __DIR__ . '/public/layout/footer.php'; ?>
