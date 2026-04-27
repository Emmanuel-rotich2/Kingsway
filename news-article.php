<?php
$appBase    = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '')),'/');
if ($appBase === '.') $appBase = '';
require_once __DIR__ . '/public/layout/public_data.php';

$id      = (int)($_GET['id'] ?? 0);
$article = $id ? kw_news_by_id($id) : null;
if (!$article) { header("Location: {$appBase}/news.php"); exit; }
kw_increment_news_views($id);

$pageTitle  = htmlspecialchars($article['title']);
$activePage = 'news';
$related    = array_filter(kw_latest_news(7), fn($n) => $n['id'] != $id);
$related    = array_slice(array_values($related), 0, 3);

$catColors = ['Sports'=>'#198754','Academic'=>'#1976d2','Infrastructure'=>'#e91e63',
              'Announcement'=>'#f9a825','Arts'=>'#9c27b0','Community'=>'#00695c'];
$col  = $catColors[$article['category']] ?? '#198754';
$date = date('d M Y', strtotime($article['created_at']));
$img  = $article['image_url'] ?: "https://placehold.co/1200x600/".ltrim($col,'#')."/ffffff?text=".urlencode($article['category']);
?>
<?php include __DIR__ . '/public/layout/header.php'; ?>

<div class="page-header">
  <div class="container position-relative" style="z-index:1">
    <nav aria-label="breadcrumb"><ol class="breadcrumb mb-2">
      <li class="breadcrumb-item"><a href="<?= $appBase ?>/index.php">Home</a></li>
      <li class="breadcrumb-item"><a href="<?= $appBase ?>/news.php">News</a></li>
      <li class="breadcrumb-item active"><?= htmlspecialchars(mb_strimwidth($article['title'],0,40,'…')) ?></li>
    </ol></nav>
    <h1 class="page-title" style="font-size:clamp(1.4rem,3vw,2rem)"><?= htmlspecialchars($article['title']) ?></h1>
  </div>
</div>

<section class="section">
  <div class="container">
    <div class="row g-5">

      <!-- Article -->
      <div class="col-lg-8">
        <img src="<?= htmlspecialchars($img) ?>"
             alt="<?= htmlspecialchars($article['title']) ?>"
             class="w-100 rounded-4 mb-4"
             style="aspect-ratio:16/9;object-fit:cover;max-height:480px"
             onerror="this.src='https://placehold.co/800x450/198754/ffffff?text=Kingsway+News'">

        <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
          <span class="tag text-white" style="background:<?= $col ?>"><?= htmlspecialchars($article['category']) ?></span>
          <span class="text-muted small"><i class="bi bi-calendar3 me-1"></i><?= $date ?></span>
          <span class="text-muted small"><i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($article['author']) ?></span>
          <span class="text-muted small"><i class="bi bi-eye me-1"></i><?= number_format($article['views']) ?> views</span>
        </div>

        <h1 class="fw-bold mb-3" style="font-size:clamp(1.4rem,2.5vw,1.9rem)"><?= htmlspecialchars($article['title']) ?></h1>

        <?php if (!empty($article['excerpt'])): ?>
        <p class="lead text-muted mb-4 border-start border-4 ps-3" style="border-color:<?= $col ?> !important">
          <?= htmlspecialchars($article['excerpt']) ?>
        </p>
        <?php endif; ?>

        <div class="article-body">
          <?= $article['content'] ?>
        </div>

        <!-- Share -->
        <div class="mt-5 pt-4 border-top">
          <div class="d-flex align-items-center gap-3 flex-wrap">
            <span class="fw-semibold small">Share this story:</span>
            <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode('https://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']) ?>"
               target="_blank" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
              <i class="bi bi-facebook me-1"></i>Facebook
            </a>
            <a href="https://twitter.com/intent/tweet?text=<?= urlencode($article['title']) ?>&url=<?= urlencode('https://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']) ?>"
               target="_blank" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
              <i class="bi bi-twitter-x me-1"></i>X (Twitter)
            </a>
            <a href="https://wa.me/?text=<?= urlencode($article['title'].' - https://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']) ?>"
               target="_blank" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
              <i class="bi bi-whatsapp me-1"></i>WhatsApp
            </a>
          </div>
        </div>

        <div class="mt-4">
          <a href="<?= $appBase ?>/news.php" class="btn-kw-outline">
            <i class="bi bi-arrow-left"></i>Back to All News
          </a>
        </div>
      </div>

      <!-- Sidebar -->
      <div class="col-lg-4">

        <!-- Categories -->
        <div class="card-modern p-4 mb-4">
          <h6 class="fw-bold mb-3"><i class="bi bi-tags text-success me-2"></i>Browse by Category</h6>
          <?php foreach (['Sports','Academic','Infrastructure','Announcement','Arts','Community'] as $cat):
            $c = $catColors[$cat] ?? '#198754'; ?>
          <a href="<?= $appBase ?>/news.php?cat=<?= urlencode($cat) ?>"
             class="d-flex align-items-center gap-2 py-2 border-bottom text-decoration-none text-dark">
            <span class="rounded-2 px-2 py-1" style="background:<?= $c ?>22;color:<?= $c ?>;font-size:.72rem;font-weight:700"><?= $cat ?></span>
          </a>
          <?php endforeach; ?>
        </div>

        <!-- Related Articles -->
        <?php if (!empty($related)): ?>
        <div class="card-modern p-4">
          <h6 class="fw-bold mb-3"><i class="bi bi-newspaper text-success me-2"></i>Related Stories</h6>
          <?php foreach ($related as $r):
            $rImg = $r['image_url'] ?: "https://placehold.co/120x80/".ltrim($catColors[$r['category']] ?? '198754','#')."/ffffff?text=News";
          ?>
          <a href="<?= $appBase ?>/news-article.php?id=<?= $r['id'] ?>" class="d-flex gap-3 mb-3 text-decoration-none text-dark">
            <img src="<?= htmlspecialchars($rImg) ?>" alt=""
                 style="width:80px;height:60px;object-fit:cover;border-radius:8px;flex-shrink:0"
                 onerror="this.src='https://placehold.co/80x60/198754/ffffff?text=News'">
            <div>
              <div class="small fw-semibold lh-sm mb-1"><?= htmlspecialchars(mb_strimwidth($r['title'],0,65,'…')) ?></div>
              <div class="text-muted" style="font-size:.75rem"><?= date('d M Y',strtotime($r['created_at'])) ?></div>
            </div>
          </a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

      </div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/public/layout/footer.php'; ?>
