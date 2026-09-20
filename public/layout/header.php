<?php
/* Shared public header — included by every public page.
 * Expects: $pageTitle (string), $activePage (string), $appBase (string) */
$pageTitle  = $pageTitle  ?? 'Kingsway Preparatory School';
$activePage = $activePage ?? 'home';
$bodyClass  = trim((string)($bodyClass ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <?php /* Load first: silences every console.* call site (incl. inline <script>)
        across public pages and routes warnings/errors to the file logger. */ ?>
  <?php asset_script($appBase, 'js/core/console_logger.js'); ?>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> | Kingsway Preparatory School</title>
  <meta name="description" content="Kingsway Preparatory School — Nurturing Excellence, Character &amp; Leadership. Located in Londiani, Kenya.">

  <!-- Favicons -->
  <link rel="icon" type="image/png" href="<?= $appBase ?>/images/favicon/favicon-96x96.png" sizes="96x96">
  <link rel="icon" type="image/svg+xml" href="<?= $appBase ?>/images/favicon/favicon.svg">
  <link rel="shortcut icon" href="<?= $appBase ?>/images/favicon/favicon.ico">
  <link rel="apple-touch-icon" sizes="180x180" href="<?= $appBase ?>/images/favicon/apple-touch-icon.png">

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

  <!-- CSS -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= $appBase ?>/css/public.css?v=<?= asset_version('css/public.css') ?>">
  <noscript><link rel="stylesheet" href="<?= $appBase ?>/css/no-script.css?v=<?= asset_version('css/no-script.css') ?>"></noscript>
</head>
<body<?= $bodyClass !== '' ? ' class="' . htmlspecialchars($bodyClass, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
<script>
window.PUBLIC_ROUTE_KEY = <?= json_encode(isset($activePage) ? (string)$activePage : (string)($activePage = 'home')) ?>;
window.PUBLIC_ROUTE_MAP = <?= json_encode(public_route_map()) ?>;
</script>

<noscript>
  <div class="noscript-overlay">
    <div class="noscript-card">
      <span class="icon">⚠️</span>
      <h2>JavaScript Required</h2>
      <p>Kingsway Preparatory School requires JavaScript for full functionality. Please enable JavaScript in your browser settings and reload the page.</p>
      <span class="badge">Kingsway Preparatory School</span>
    </div>
  </div>
</noscript>

<!-- ═══ NAVBAR ═══════════════════════════════════════════════════════════════ -->
<nav class="site-nav navbar navbar-expand-lg">
  <div class="container-fluid site-nav-inner">

    <a class="navbar-brand" href="<?= $appBase ?>/index.php">
      <img src="<?= $appBase ?>/uploads/school_assets/official_school_logo.png" alt="Kingsway Logo" class="school-logo" onerror="this.onerror=null;this.src='<?= $appBase ?>/images/official_school_logo.png';">
      <span class="school-name">Kingsway Preparatory School</span>
    </a>

    <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#siteNav" aria-controls="siteNav" aria-expanded="false">
      <i class="bi bi-list text-white fs-3"></i>
    </button>

    <div class="collapse navbar-collapse" id="siteNav">
      <ul class="navbar-nav ms-auto align-items-lg-center gap-1">

        <li class="nav-item">
          <a class="nav-link <?= $activePage==='home'?'active':'' ?>" href="<?= $appBase ?>/index.php">Home</a>
        </li>

        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle <?= $activePage==='about'?'active':'' ?>" href="#" data-bs-toggle="dropdown">About Us</a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="<?= $appBase ?>/index.php?route=r417bdc0697f0#mission"><i class="bi bi-bullseye me-2 text-success"></i>Mission &amp; Vision</a></li>
            <li><a class="dropdown-item" href="<?= $appBase ?>/index.php?route=r417bdc0697f0#history"><i class="bi bi-book me-2 text-success"></i>Our History</a></li>
            <li><a class="dropdown-item" href="<?= $appBase ?>/index.php?route=r417bdc0697f0#leadership"><i class="bi bi-person-badge me-2 text-success"></i>Leadership Team</a></li>
            <li><a class="dropdown-item" href="<?= $appBase ?>/index.php?route=r417bdc0697f0#facilities"><i class="bi bi-buildings me-2 text-success"></i>Facilities</a></li>
          </ul>
        </li>

        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle <?= $activePage==='admissions'?'active':'' ?>" href="#" data-bs-toggle="dropdown">Admissions</a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="<?= $appBase ?>/index.php?route=rdb9314b5fdb2#process"><i class="bi bi-list-check me-2 text-success"></i>Admission Process</a></li>
            <li><a class="dropdown-item" href="<?= $appBase ?>/index.php?route=rdb9314b5fdb2#requirements"><i class="bi bi-file-earmark-text me-2 text-success"></i>Requirements</a></li>
            <li><a class="dropdown-item" href="<?= $appBase ?>/index.php?route=rdb9314b5fdb2#fees"><i class="bi bi-cash me-2 text-success"></i>Fee Structure</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item fw-semibold text-success" href="<?= $appBase ?>/index.php?route=rdb9314b5fdb2#apply"><i class="bi bi-send me-2"></i>Apply Now</a></li>
          </ul>
        </li>

        <li class="nav-item">
          <a class="nav-link <?= $activePage==='news'?'active':'' ?>" href="<?= $appBase ?>/index.php?route=rcbd4a4c91922">
            <i class="bi bi-newspaper me-1"></i>News
          </a>
        </li>

        <li class="nav-item">
          <a class="nav-link <?= $activePage==='events'?'active':'' ?>" href="<?= $appBase ?>/index.php?route=r78213fd42e2d">
            <i class="bi bi-calendar-event me-1"></i>Events
          </a>
        </li>

        <li class="nav-item">
          <a class="nav-link <?= $activePage==='careers'?'active':'' ?>" href="<?= $appBase ?>/index.php?route=ra5d49bd64f1c">Careers</a>
        </li>

        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">More</a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="<?= $appBase ?>/index.php?route=r01a0050e3f7e"><i class="bi bi-download me-2 text-success"></i>Downloads</a></li>
            
            <li><a class="dropdown-item" href="<?= $appBase ?>/index.php?route=r39d07ccbcf8a"><i class="bi bi-bag-heart me-2 text-success"></i>Uniform Store</a></li>
            <li><a class="dropdown-item" href="<?= $appBase ?>/index.php?route=r3443047604ab"><i class="bi bi-envelope me-2 text-success"></i>Contact Us</a></li>
            <li><a class="dropdown-item" href="<?= $appBase ?>/parent_portal.php"><i class="bi bi-people me-2 text-success"></i>Parent Portal</a></li>
          </ul>
        </li>

        <li class="nav-item">
          <a class="nav-link btn-login" href="<?= $appBase ?>/index.php?route=r6d394ab20b0b">
            <i class="bi bi-box-arrow-in-right me-1"></i>Login
          </a>
        </li>

      </ul>
    </div>
  </div>
</nav>

<!-- ═══ ANNOUNCEMENT TICKER ══════════════════════════════════════════════════ -->
<div class="ticker-bar d-flex align-items-center gap-3 px-3">
  <span class="ticker-label"><i class="bi bi-megaphone-fill me-1"></i>News</span>
  <div class="overflow-hidden flex-grow-1">
    <div class="ticker-track" id="site-ticker"></div>
  </div>
</div>

<!-- Public, source-grounded assistant. It never exposes staff or learner data. -->
<aside id="public-ai-assistant" class="public-ai-assistant" aria-label="Kingsway public assistant">
  <button type="button" id="public-ai-toggle" class="public-ai-toggle" aria-expanded="false" aria-controls="public-ai-panel">
    <i class="bi bi-stars me-2" aria-hidden="true"></i>Ask Kingsway
  </button>
  <section id="public-ai-panel" class="public-ai-panel" hidden aria-labelledby="public-ai-title">
    <div class="public-ai-panel-header">
      <div>
        <h2 id="public-ai-title" class="h6 mb-1">Kingsway assistant</h2>
        <p class="small mb-0">Ask about the school, admissions, programmes, or policies.</p>
      </div>
      <button type="button" id="public-ai-close" class="btn-close btn-close-white" aria-label="Close assistant"></button>
    </div>
    <div id="public-ai-history" class="public-ai-history" aria-label="Conversation history"></div>
    <div id="public-ai-answer" class="public-ai-answer" aria-live="polite">
      <p class="small mb-0">Answers use published school information. For anything uncertain, we will refer you to a human assistant.</p>
    </div>
    <div id="public-ai-suggestions" class="public-ai-suggestions" aria-label="Suggested follow-up questions" hidden></div>
    <form id="public-ai-form" class="public-ai-form" novalidate>
      <label class="visually-hidden" for="public-ai-question">Your question</label>
      <textarea id="public-ai-question" name="question" rows="2" maxlength="500" required placeholder="How do I apply for admission?"></textarea>
      <div class="d-flex justify-content-between align-items-center gap-2 mt-2">
        <span id="public-ai-status" class="small text-muted" role="status"></span>
        <button type="submit" id="public-ai-submit" class="btn btn-success btn-sm"><i class="bi bi-send me-1" aria-hidden="true"></i>Ask</button>
      </div>
    </form>
  </section>
</aside>
