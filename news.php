<?php
$appBase    = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '')),'/');
if ($appBase === '.') $appBase = '';
$pageTitle  = 'News & Blog';
$activePage = 'news';
require_once __DIR__ . '/public/layout/public_data.php';

$activeCategory = $_GET['cat'] ?? '';
$page           = max(1, (int)($_GET['page'] ?? 1));
$perPage        = 9;
$total          = kw_news_count($activeCategory);
$totalPages     = max(1, (int)ceil($total / $perPage));
$news           = kw_latest_news($perPage, $page, $activeCategory);
$catColors      = kw_news_categories();
$categories     = array_keys($catColors);
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
    <?php if (!empty($news) && $page === 1 && !$activeCategory):
      $featured = $news[0];
      $featDate  = date('d M Y', strtotime($featured['created_at']));
      $col      = $catColors[$featured['category']] ?? '#198754';
      $img      = !empty($featured['image_url'])
                    ? htmlspecialchars($featured['image_url'])
                    : "https://placehold.co/800x500/".ltrim($col,'#')."/ffffff?text=".urlencode($featured['category']);
    ?>
    <a href="<?= $appBase ?>/news-article.php?id=<?= $featured['id'] ?>" class="text-decoration-none">
      <div class="card-modern mb-5 reveal" style="cursor:pointer">
        <div class="row g-0">
          <div class="col-lg-6">
            <div class="card-img-wrap" style="aspect-ratio:16/9;height:100%;min-height:280px">
              <img src="<?= $img ?>" alt="<?= htmlspecialchars($featured['title']) ?>"
                   style="height:100%;object-fit:cover"
                   onerror="this.src='https://placehold.co/800x500/198754/ffffff?text=Kingsway+News'">
            </div>
          </div>
          <div class="col-lg-6 p-4 p-lg-5 d-flex flex-column justify-content-center">
            <span class="card-category mb-2" style="background:<?= $col ?>"><?= htmlspecialchars($featured['category']) ?></span>
            <h2 class="fw-bold mb-3" style="font-size:1.5rem"><?= htmlspecialchars($featured['title']) ?></h2>
            <p class="text-muted mb-3"><?= htmlspecialchars(mb_strimwidth(strip_tags($featured['content'] ?? $featured['excerpt'] ?? ''),0,200,'…')) ?></p>
            <div class="d-flex align-items-center gap-3 text-muted small mt-auto">
              <span><i class="bi bi-calendar3 me-1"></i><?= $featDate ?></span>
              <span><i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($featured['author']) ?></span>
              <span><i class="bi bi-eye me-1"></i><?= number_format($featured['views'] ?? 0) ?> views</span>
            </div>
          </div>
        </div>
      </div>
    </a>
    <?php endif; ?>

    <!-- News grid -->
    <?php
    $gridStart = ($page === 1 && !$activeCategory) ? 1 : 0;
    $gridNews  = ($page === 1 && !$activeCategory) ? array_slice($news, 1) : $news;
    ?>
    <?php if (!empty($gridNews)): ?>
    <div class="row g-4">
      <?php foreach ($gridNews as $i => $n):
        $col  = $catColors[$n['category']] ?? '#198754';
        $img  = !empty($n['image_url'])
                  ? htmlspecialchars($n['image_url'])
                  : "https://placehold.co/600x380/".ltrim($col,'#')."/ffffff?text=".urlencode($n['category']);
        $date = date('d M Y', strtotime($n['created_at']));
      ?>
      <div class="col-lg-4 col-md-6">
        <a href="<?= $appBase ?>/news-article.php?id=<?= $n['id'] ?>" class="text-decoration-none">
          <div class="card-modern h-100 reveal delay-<?= ($i%3)+1 ?>" style="cursor:pointer">
            <div class="card-img-wrap">
              <img src="<?= $img ?>" alt="<?= htmlspecialchars($n['title']) ?>"
                   style="object-fit:cover"
                   onerror="this.src='https://placehold.co/600x380/198754/ffffff?text=News'">
            </div>
            <div class="p-4 d-flex flex-column h-100">
              <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="card-category" style="background:<?= $col ?>"><?= htmlspecialchars($n['category']) ?></span>
                <span class="card-date"><i class="bi bi-calendar3"></i><?= $date ?></span>
              </div>
              <div class="card-title fw-bold fs-6 mb-2"><?= htmlspecialchars($n['title']) ?></div>
              <p class="card-excerpt flex-grow-1"><?= htmlspecialchars(mb_strimwidth(strip_tags($n['excerpt'] ?? $n['content'] ?? ''),0,120,'…')) ?></p>
              <div class="d-flex align-items-center justify-content-between mt-3 pt-3 border-top">
                <span class="text-muted small"><i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($n['author']) ?></span>
                <span class="read-more small text-success">Read More <i class="bi bi-arrow-right"></i></span>
              </div>
            </div>
          </div>
        </a>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="text-center py-5">
      <i class="bi bi-newspaper fs-1 text-muted d-block mb-3"></i>
      <p class="text-muted">No articles in this category yet.</p>
      <a href="<?= $appBase ?>/news.php" class="btn-kw-outline mt-2">View All News</a>
    </div>
    <?php endif; ?>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <nav class="mt-5 d-flex justify-content-center reveal">
      <ul class="pagination">
        <li class="page-item <?= $page<=1?'disabled':'' ?>">
          <a class="page-link <?= $page<=1?'':'text-success' ?>" href="?<?= $activeCategory?'cat='.urlencode($activeCategory).'&':'' ?>page=<?= $page-1 ?>">&laquo;</a>
        </li>
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
        <li class="page-item <?= $p===$page?'active':'' ?>">
          <a class="page-link <?= $p===$page?'bg-success border-success':'' ?>"
             style="<?= $p!==$page?'color:var(--green)':'' ?>"
             href="?<?= $activeCategory?'cat='.urlencode($activeCategory).'&':'' ?>page=<?= $p ?>"><?= $p ?></a>
        </li>
        <?php endfor; ?>
        <li class="page-item <?= $page>=$totalPages?'disabled':'' ?>">
          <a class="page-link <?= $page<$totalPages?'text-success':'' ?>" href="?<?= $activeCategory?'cat='.urlencode($activeCategory).'&':'' ?>page=<?= $page+1 ?>">&raquo;</a>
        </li>
      </ul>
    </nav>
    <?php endif; ?>
  </div>
</section>

<?php include __DIR__ . '/public/layout/footer.php'; ?>