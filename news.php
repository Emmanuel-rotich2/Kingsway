<?php
$appBase    = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '')),'/');
if ($appBase === '.') $appBase = '';
$pageTitle  = 'News & Blog';
$activePage = 'news';
require_once __DIR__ . '/public/layout/public_data.php';
$news     = kw_latest_news(12);
$activeCategory = $_GET['cat'] ?? '';
$categories = ['Sports','Academic','Infrastructure','Announcement','Arts','Community'];
?>
<?php include __DIR__ . '/public/layout/header.php'; ?>

<div class="page-header">
  <div class="container position-relative" style="z-index:1">
    <nav aria-label="breadcrumb"><ol class="breadcrumb mb-2">
      <li class="breadcrumb-item"><a href="<?= $appBase ?>/index.php">Home</a></li>
      <li class="breadcrumb-item active">News &amp; Blog</li>
    </ol></nav>
    <h1 class="page-title">News &amp; Updates</h1>
    <p class="mt-2" style="color:rgba(255,255,255,.7)">Stay informed about life at Kingsway Preparatory School</p>
  </div>
</div>

<section class="section">
  <div class="container">
    <!-- Category filter -->
    <div class="d-flex flex-wrap gap-2 mb-5 reveal">
      <a href="<?= $appBase ?>/news.php" class="tag <?= !$activeCategory?'bg-success text-white':'bg-white border text-muted' ?>">All</a>
      <?php foreach ($categories as $cat): ?>
      <a href="?cat=<?= urlencode($cat) ?>" class="tag <?= $activeCategory===$cat?'bg-success text-white':'bg-white border text-muted' ?>">
        <?= $cat ?>
      </a>
      <?php endforeach; ?>
    </div>

    <!-- Featured post -->
    <?php if (!empty($news)):
      $featured = $news[0];
      $featDate  = date('d M Y', strtotime($featured['created_at']));
    ?>
    <div class="card-modern mb-5 reveal">
      <div class="row g-0">
        <div class="col-lg-6">
          <div class="card-img-wrap" style="aspect-ratio:16/9;height:100%;min-height:280px">
            <img src="https://placehold.co/800x500/198754/ffffff?text=<?= urlencode($featured['category'] ?? 'News') ?>"
                 alt="<?= htmlspecialchars($featured['title']) ?>" style="height:100%;object-fit:cover">
          </div>
        </div>
        <div class="col-lg-6 p-4 p-lg-5 d-flex flex-column justify-content-center">
          <span class="card-category mb-2"><?= htmlspecialchars($featured['category'] ?? 'News') ?></span>
          <h2 class="fw-bold mb-3" style="font-size:1.5rem"><?= htmlspecialchars($featured['title']) ?></h2>
          <p class="text-muted mb-3"><?= htmlspecialchars(mb_strimwidth(strip_tags($featured['content']),0,200,'…')) ?></p>
          <div class="d-flex align-items-center gap-3 text-muted small mt-auto">
            <span><i class="bi bi-calendar3 me-1"></i><?= $featDate ?></span>
            <span><i class="bi bi-person-circle me-1"></i>Kingsway Admin</span>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- News grid -->
    <div class="row g-4">
      <?php foreach (array_slice($news, 1) as $i => $n):
        if ($activeCategory && ($n['category'] ?? '') !== $activeCategory) continue;
        $catColors = ['Sports'=>'#198754','Academic'=>'#1976d2','Infrastructure'=>'#e91e63','Announcement'=>'#f9a825','Arts'=>'#9c27b0','Community'=>'#00695c'];
        $col = $catColors[$n['category'] ?? ''] ?? '#198754';
        $date = date('d M Y', strtotime($n['created_at']));
      ?>
      <div class="col-lg-4 col-md-6">
        <div class="card-modern h-100 reveal delay-<?= ($i%3)+1 ?>">
          <div class="card-img-wrap">
            <img src="https://placehold.co/600x380/<?= ltrim($col,'#') ?>/ffffff?text=<?= urlencode($n['category'] ?? 'News') ?>"
                 alt="<?= htmlspecialchars($n['title']) ?>">
          </div>
          <div class="p-4 d-flex flex-column h-100">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <span class="card-category" style="background:<?= $col ?>"><?= htmlspecialchars($n['category'] ?? 'News') ?></span>
              <span class="card-date"><i class="bi bi-calendar3"></i><?= $date ?></span>
            </div>
            <div class="card-title fw-bold fs-6 mb-2"><?= htmlspecialchars($n['title']) ?></div>
            <p class="card-excerpt flex-grow-1"><?= htmlspecialchars(mb_strimwidth(strip_tags($n['content']),0,120,'…')) ?></p>
            <div class="d-flex align-items-center justify-content-between mt-3 pt-3 border-top">
              <span class="text-muted small"><i class="bi bi-person-circle me-1"></i>Admin</span>
              <a href="#" class="read-more small">Read More <i class="bi bi-arrow-right"></i></a>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <nav class="mt-5 d-flex justify-content-center reveal">
      <ul class="pagination">
        <li class="page-item disabled"><a class="page-link" href="#">&laquo;</a></li>
        <li class="page-item active"><a class="page-link" href="#" style="background:var(--green);border-color:var(--green)">1</a></li>
        <li class="page-item"><a class="page-link text-success" href="#">2</a></li>
        <li class="page-item"><a class="page-link text-success" href="#">3</a></li>
        <li class="page-item"><a class="page-link text-success" href="#">&raquo;</a></li>
      </ul>
    </nav>
  </div>
</section>

<?php include __DIR__ . '/public/layout/footer.php'; ?>
