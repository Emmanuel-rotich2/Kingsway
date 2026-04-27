<?php
$appBase    = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '')),'/');
if ($appBase === '.') $appBase = '';
$pageTitle  = 'Downloads';
$activePage = 'downloads';
require_once __DIR__ . '/public/layout/public_data.php';
$categories = kw_downloads();
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

    <?php foreach ($categories as $cat => $docs): ?>
    <div class="mb-5 reveal dl-category">
      <div class="d-flex align-items-center gap-3 mb-3">
        <h4 class="fw-bold mb-0"><?= htmlspecialchars($cat) ?></h4>
        <span class="badge bg-success"><?= count($docs) ?> document<?= count($docs) !== 1 ? 's' : '' ?></span>
      </div>
      <div class="row g-3">
        <?php foreach ($docs as $doc): ?>
        <div class="col-lg-6 dl-item">
          <div class="download-item">
            <div class="download-icon" style="background:<?= htmlspecialchars($doc['color'] ?? '#198754') ?>22;color:<?= htmlspecialchars($doc['color'] ?? '#198754') ?>">
              <i class="bi <?= htmlspecialchars($doc['icon'] ?? 'bi-file-earmark-pdf-fill') ?>"></i>
            </div>
            <div class="flex-grow-1">
              <div class="download-name"><?= htmlspecialchars($doc['title']) ?></div>
              <div class="download-meta">
                <span class="tag me-1" style="background:<?= htmlspecialchars($doc['color'] ?? '#198754') ?>22;color:<?= htmlspecialchars($doc['color'] ?? '#198754') ?>;padding:2px 8px;font-size:.68rem"><?= htmlspecialchars($doc['file_type'] ?? 'PDF') ?></span>
                <?php if (!empty($doc['file_size'])): ?><span><?= htmlspecialchars($doc['file_size']) ?></span><?php endif; ?>
              </div>
            </div>
            <a href="<?= $appBase ?>/<?= htmlspecialchars($doc['file_url']) ?>" download class="download-btn" title="Download <?= htmlspecialchars($doc['title']) ?>">
              <i class="bi bi-download"></i>
            </a>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>

    <!-- Note -->
    <div class="p-4 rounded-4 reveal" style="background:#fff8e1;border-left:4px solid var(--gold)">
      <h6 class="fw-bold mb-1"><i class="bi bi-info-circle-fill text-warning me-2"></i>Note on Downloads</h6>
      <p class="text-muted small mb-0">
        Some documents require Adobe Reader or Microsoft Office to open. If you cannot find what you're looking for,
        please <a href="<?= $appBase ?>/contact.php" class="text-success fw-semibold">contact our office</a> and we'll be happy to assist.
        Documents are updated at the start of each academic year.
      </p>
    </div>

  </div>
</section>

<script>
document.getElementById('dlSearch').addEventListener('input', function() {
  const q = this.value.toLowerCase();
  document.querySelectorAll('.dl-item').forEach(item => {
    const name = item.querySelector('.download-name')?.textContent.toLowerCase() || '';
    item.style.display = name.includes(q) ? '' : 'none';
  });
});
</script>

<?php include __DIR__ . '/public/layout/footer.php'; ?>
