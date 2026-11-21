<?php
/**
 * Shared layout for the public legal pages (privacy, terms, cookies).
 *
 * The including page sets:
 *   $pageTitle, $activePage (its facade route key), $legalTitle, $legalIntro,
 *   $legalUpdated (human date) and $legalSections: list of
 *   ['id' => anchor, 'title' => heading, 'html' => trusted static HTML].
 * School contact details come from the canonical school profile (SCHOOL_*),
 * so the pages never go stale when the office number or email changes.
 */
$appBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if (class_exists(\App\Config\Config::class)) {
    \App\Config\Config::init();
}
require_once __DIR__ . '/public_data.php';

$schoolName = defined('SCHOOL_NAME') && SCHOOL_NAME !== 'School' ? SCHOOL_NAME : 'Kingsway Preparatory School';
$schoolEmail = defined('SCHOOL_EMAIL') ? (string) SCHOOL_EMAIL : '';
$schoolPhone = defined('SCHOOL_PHONE') ? (string) SCHOOL_PHONE : '';
$schoolAddress = defined('SCHOOL_ADDRESS') ? (string) SCHOOL_ADDRESS : '';
$legalLinks = [
    'privacy-policy' => 'Privacy Policy',
    'terms-of-use' => 'Terms of Use',
    'cookie-policy' => 'Cookie Policy',
];
include __DIR__ . '/header.php';
?>
<div class="page-header">
  <div class="container position-relative" style="z-index:1">
    <nav aria-label="breadcrumb"><ol class="breadcrumb mb-2">
      <li class="breadcrumb-item"><a href="<?= htmlspecialchars($appBase) ?>/index.php">Home</a></li>
      <li class="breadcrumb-item active"><?= htmlspecialchars($legalTitle) ?></li>
    </ol></nav>
    <h1 class="page-title"><?= htmlspecialchars($legalTitle) ?></h1>
    <p class="mt-2" style="color:rgba(255,255,255,.75)">Last updated: <?= htmlspecialchars($legalUpdated) ?></p>
  </div>
</div>

<section class="section legal-page">
  <div class="container">
    <div class="row g-5">
      <aside class="col-lg-3 d-none d-lg-block">
        <nav class="legal-toc position-sticky" style="top:100px" aria-label="On this page">
          <p class="small fw-bold text-uppercase text-muted mb-2">On this page</p>
          <ol class="small ps-3 mb-4">
            <?php foreach ($legalSections as $section): ?>
              <li class="mb-1"><a href="#<?= htmlspecialchars($section['id']) ?>"><?= htmlspecialchars($section['title']) ?></a></li>
            <?php endforeach; ?>
          </ol>
          <p class="small fw-bold text-uppercase text-muted mb-2">Legal</p>
          <ul class="list-unstyled small">
            <?php foreach ($legalLinks as $key => $label): ?>
              <li class="mb-1">
                <?php if ($key === $activePage): ?>
                  <strong><?= htmlspecialchars($label) ?></strong>
                <?php else: ?>
                  <a href="<?= htmlspecialchars($appBase . '/' . public_clean_url($key)) ?>"><?= htmlspecialchars($label) ?></a>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        </nav>
      </aside>
      <article class="col-lg-9" style="max-width:860px">
        <p class="lead"><?= $legalIntro /* trusted static HTML from the page */ ?></p>
        <?php foreach ($legalSections as $i => $section): ?>
          <section id="<?= htmlspecialchars($section['id']) ?>" class="mb-5" style="scroll-margin-top:100px">
            <h2 class="h4 fw-bold mb-3"><?= ($i + 1) . '. ' . htmlspecialchars($section['title']) ?></h2>
            <?= $section['html'] /* trusted static HTML from the page */ ?>
          </section>
        <?php endforeach; ?>

        <div class="p-4 rounded-4 mt-4" style="background:#f1f8f4;border-left:4px solid var(--green)">
          <h2 class="h5 fw-bold mb-2">Contact us about this policy</h2>
          <p class="mb-1"><?= htmlspecialchars($schoolName) ?><?= $schoolAddress !== '' ? ', ' . htmlspecialchars($schoolAddress) : '' ?></p>
          <?php if ($schoolEmail !== ''): ?>
            <p class="mb-1">Email: <a href="mailto:<?= htmlspecialchars($schoolEmail) ?>"><?= htmlspecialchars($schoolEmail) ?></a></p>
          <?php endif; ?>
          <?php if ($schoolPhone !== ''): ?>
            <p class="mb-1">Phone: <?= htmlspecialchars($schoolPhone) ?></p>
          <?php endif; ?>
          <p class="mb-0">Or use the <a href="<?= htmlspecialchars($appBase . '/' . public_clean_url('contact')) ?>">contact page</a>.</p>
        </div>

        <p class="small text-muted mt-4 d-lg-none">
          <?php foreach ($legalLinks as $key => $label): ?>
            <?php if ($key !== $activePage): ?>
              <a href="<?= htmlspecialchars($appBase . '/' . public_clean_url($key)) ?>"><?= htmlspecialchars($label) ?></a> &nbsp;
            <?php endif; ?>
          <?php endforeach; ?>
        </p>
      </article>
    </div>
  </div>
</section>
<?php include __DIR__ . '/footer.php'; ?>
