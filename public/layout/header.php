<?php
/* Shared public header — included by every public page.
 * Expects: $pageTitle (string), $activePage (string), $appBase (string) */
if (class_exists(\App\Config\Config::class)) {
  \App\Config\Config::init();
}
$pageTitle  = $pageTitle  ?? 'Kingsway Preparatory School';
$activePage = $activePage ?? 'home';
$bodyClass  = trim((string)($bodyClass ?? ''));
$pageDescriptions = [
  'home' => 'Kingsway Preparatory School in Londiani, Kenya offers faith-based CBC education from Playgroup through Grade 9.',
  'about' => 'Learn about Kingsway Preparatory School, its Christian foundation, community, learning programmes and history in Londiani, Kenya.',
  'admissions' => 'Find admissions information for Kingsway Preparatory School, a CBC primary and junior secondary school in Londiani, Kenya.',
  'news' => 'Read public news and updates from Kingsway Preparatory School in Londiani, Kericho County, Kenya.',
  'events' => 'See upcoming public events at Kingsway Preparatory School in Londiani, Kenya.',
  'careers' => 'View current staff vacancies and career opportunities at Kingsway Preparatory School in Kenya.',
  'contact' => 'Contact Kingsway Preparatory School in Simotwet, Londiani, Kericho County, Kenya.',
  'gallery' => 'Explore the Kingsway Preparatory School gallery — photos and videos of school life, facilities, events, and student activities.',
  'downloads' => 'Access public school documents and downloads from Kingsway Preparatory School.',
  'uniform-catalog' => 'Browse the Kingsway Preparatory School uniform catalogue.',
  'privacy-policy' => 'How Kingsway Preparatory School collects, uses and protects personal data under the Kenya Data Protection Act, 2019.',
  'terms-of-use' => 'Terms for using the Kingsway Preparatory School website, admissions, parent portal and online payments.',
  'cookie-policy' => 'The cookies and browser storage used by the Kingsway Preparatory School website and portals.',
];
$pageDescription = $pageDescription ?? ($pageDescriptions[$activePage] ?? 'Kingsway Preparatory School — Nurturing Excellence, Character & Leadership in Londiani, Kenya.');
$siteBase = rtrim((string) (defined('BASE_URL') ? BASE_URL : $appBase), '/');
$publicRouteKeys = array_keys((require __DIR__ . '/facade_routes.php')['public'] ?? []);
// Canonical URL: one crawlable URL per page. Listing pages use their clean
// path (/about); detail pages (article, event, vacancy, product) set
// $canonicalRouteKey and keep their ?id so every item has its own canonical.
// The uniform store pages report activePage 'uniforms' for the nav highlight.
$canonicalPageKey = $canonicalRouteKey ?? ($activePage === 'uniforms' ? 'uniform-catalog' : $activePage);
$detailId = (int) ($_GET['id'] ?? 0);
if (!isset($canonicalUrl)) {
  if ($canonicalPageKey === 'home') {
    $canonicalUrl = $siteBase . '/';
  } elseif (isset($canonicalRouteKey)) {
    $canonicalUrl = $siteBase . '/index.php?route=' . rawurlencode($canonicalRouteKey) . ($detailId > 0 ? '&id=' . $detailId : '');
  } elseif (in_array($canonicalPageKey, $publicRouteKeys, true)) {
    $canonicalUrl = $siteBase . '/' . public_clean_url($canonicalPageKey);
  } else {
    $canonicalUrl = $siteBase . '/';
  }
}
// Sign-in, password and account pages, and detail pages without an item,
// stay out of search results (they are still crawlable for link discovery).
$robotsNoindex = !empty($robotsNoindex)
  || $activePage === 'login'
  || (isset($canonicalRouteKey) && $detailId <= 0);
$socialImage = $siteBase . '/uploads/school_assets/official_school_logo.png?v=' . asset_version('school_assets/official_school_logo.png');
$pageImage = (string) ($socialImageOverride ?? $socialImage);
$organizationData = [
  '@context' => 'https://schema.org',
  '@type' => ['School', 'EducationalOrganization'],
  '@id' => $siteBase . '/#school',
  'name' => 'Kingsway Preparatory School',
  'url' => $siteBase . '/',
  'logo' => $socialImage,
  'image' => $socialImage,
  'description' => 'A Seventh-day Adventist CBC primary and junior secondary school (Playgroup to Grade 9) with day and boarding places in Simotwet, Londiani, Kericho County, Kenya.',
  'foundingDate' => '2008',
  'email' => 'info@kingswaypreparatoryschool.sc.ke',
  'telephone' => '+254720113030',
  'areaServed' => ['Londiani', 'Kericho County', 'Kenya'],
  'sameAs' => [
    'https://www.facebook.com/profile.php/?id=100066942647437',
    'https://www.instagram.com/p/DE7jLBkIEjS/',
  ],
  'address' => [
    '@type' => 'PostalAddress',
    'streetAddress' => 'Simotwet',
    'addressLocality' => 'Londiani',
    'addressRegion' => 'Kericho County',
    'postalCode' => '20203',
    'addressCountry' => 'KE',
  ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <?php /* Load first: silences every console.* call site (incl. inline <script>)
        across public pages and routes warnings/errors to the file logger. */ ?>
  <?php asset_script($appBase, 'js/core/console_logger.js'); ?>
  <?php if (!empty($showCookieConsent)): ?>
  <?php asset_script($appBase, 'js/core/cookie_consent.js'); ?>
  <?php endif; ?>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="kingsway-cookie-policy" content="<?= htmlspecialchars($appBase . '/' . public_route_url('cookie-policy'), ENT_QUOTES, 'UTF-8') ?>">
  <title><?= htmlspecialchars($pageTitle) ?> | Kingsway Preparatory School</title>
  <meta name="description" content="<?= htmlspecialchars($pageDescription, ENT_QUOTES, 'UTF-8') ?>">
  <link rel="canonical" href="<?= htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8') ?>">
  <?php if ($robotsNoindex): ?>
  <meta name="robots" content="noindex, follow">
  <?php endif; ?>
  <meta property="og:type" content="<?= isset($canonicalRouteKey) && $canonicalRouteKey === 'news-article' ? 'article' : 'website' ?>">
  <meta property="og:locale" content="en_KE">
  <meta property="og:site_name" content="Kingsway Preparatory School">
  <meta property="og:title" content="<?= htmlspecialchars($pageTitle . ' | Kingsway Preparatory School', ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:description" content="<?= htmlspecialchars($pageDescription, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:url" content="<?= htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:image" content="<?= htmlspecialchars($pageImage, ENT_QUOTES, 'UTF-8') ?>">
  <meta name="twitter:card" content="<?= $pageImage !== $socialImage ? 'summary_large_image' : 'summary' ?>">
  <meta name="twitter:title" content="<?= htmlspecialchars($pageTitle . ' | Kingsway Preparatory School', ENT_QUOTES, 'UTF-8') ?>">
  <meta name="twitter:description" content="<?= htmlspecialchars($pageDescription, ENT_QUOTES, 'UTF-8') ?>">
  <meta name="twitter:image" content="<?= htmlspecialchars($pageImage, ENT_QUOTES, 'UTF-8') ?>">
  <script type="application/ld+json"><?= json_encode($organizationData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
  <?php if (!empty($pageStructuredData) && is_array($pageStructuredData)): ?>
  <script type="application/ld+json"><?= json_encode($pageStructuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
  <?php endif; ?>

  <!-- Favicons -->
  <link rel="icon" type="image/png" href="<?= $appBase ?>/images/favicon/favicon-96x96.png?v=<?= asset_version('images/favicon/favicon-96x96.png') ?>" sizes="96x96">
  <link rel="icon" type="image/svg+xml" href="<?= $appBase ?>/images/favicon/favicon.svg?v=<?= asset_version('images/favicon/favicon.svg') ?>">
  <link rel="shortcut icon" href="<?= $appBase ?>/images/favicon/favicon.ico?v=<?= asset_version('images/favicon/favicon.ico') ?>">
  <link rel="apple-touch-icon" sizes="180x180" href="<?= $appBase ?>/images/favicon/apple-touch-icon.png?v=<?= asset_version('images/favicon/apple-touch-icon.png') ?>">

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
      <img src="<?= $appBase ?>/uploads/school_assets/official_school_logo.png?v=<?= asset_version('school_assets/official_school_logo.png') ?>" alt="Kingsway Logo" class="school-logo" onerror="this.onerror=null;this.src='<?= $appBase ?>/images/official_school_logo.png';">
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
            <li><a class="dropdown-item" href="<?= $appBase ?>/about#mission"><i class="bi bi-bullseye me-2 text-success"></i>Mission &amp; Vision</a></li>
            <li><a class="dropdown-item" href="<?= $appBase ?>/about#history"><i class="bi bi-book me-2 text-success"></i>Our History</a></li>
            <li><a class="dropdown-item" href="<?= $appBase ?>/about#leadership"><i class="bi bi-person-badge me-2 text-success"></i>Leadership Team</a></li>
            <li><a class="dropdown-item" href="<?= $appBase ?>/about#facilities"><i class="bi bi-buildings me-2 text-success"></i>Facilities</a></li>
          </ul>
        </li>

        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle <?= $activePage==='admissions'?'active':'' ?>" href="#" data-bs-toggle="dropdown">Admissions</a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="<?= $appBase ?>/admissions#process"><i class="bi bi-list-check me-2 text-success"></i>Admission Process</a></li>
            <li><a class="dropdown-item" href="<?= $appBase ?>/admissions#requirements"><i class="bi bi-file-earmark-text me-2 text-success"></i>Requirements</a></li>
            <li><a class="dropdown-item" href="<?= $appBase ?>/admissions#fees"><i class="bi bi-cash me-2 text-success"></i>Fee Structure</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item fw-semibold text-success" href="<?= $appBase ?>/admissions#apply"><i class="bi bi-send me-2"></i>Apply Now</a></li>
          </ul>
        </li>

        <li class="nav-item">
          <a class="nav-link <?= $activePage==='news'?'active':'' ?>" href="<?= $appBase ?>/news">
            <i class="bi bi-newspaper me-1"></i>News
          </a>
        </li>

        <li class="nav-item">
          <a class="nav-link <?= $activePage==='events'?'active':'' ?>" href="<?= $appBase ?>/events">
            <i class="bi bi-calendar-event me-1"></i>Events
          </a>
        </li>

        <li class="nav-item">
          <a class="nav-link <?= $activePage==='gallery'?'active':'' ?>" href="<?= $appBase ?>/gallery">
            <i class="bi bi-collection me-1"></i>Gallery
          </a>
        </li>

        <li class="nav-item">
          <a class="nav-link <?= $activePage==='careers'?'active':'' ?>" href="<?= $appBase ?>/careers">Careers</a>
        </li>

        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">More</a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="<?= $appBase ?>/downloads"><i class="bi bi-download me-2 text-success"></i>Downloads</a></li>
            
            <li><a class="dropdown-item" href="<?= $appBase ?>/uniform-catalog"><i class="bi bi-bag-heart me-2 text-success"></i>Uniform Store</a></li>
            <li><a class="dropdown-item" href="<?= $appBase ?>/contact"><i class="bi bi-envelope me-2 text-success"></i>Contact Us</a></li>
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
    <div id="public-ai-handoff" class="public-ai-handoff mt-2" hidden>
      <button type="button" id="public-ai-handoff-open" class="btn btn-outline-success btn-sm">
        <i class="bi bi-person-lines-fill me-1" aria-hidden="true"></i>Talk to admissions
      </button>
      <form id="public-ai-handoff-form" class="mt-2" hidden novalidate>
        <div class="row g-2">
          <div class="col-sm-6">
            <input id="public-ai-lead-name" class="form-control form-control-sm" maxlength="120" placeholder="Your name" autocomplete="name" required>
          </div>
          <div class="col-sm-6">
            <input id="public-ai-lead-phone" class="form-control form-control-sm" maxlength="40" placeholder="Phone" autocomplete="tel" required>
          </div>
          <div class="col-12">
            <input id="public-ai-lead-email" type="email" class="form-control form-control-sm" maxlength="190" placeholder="Email (optional)" autocomplete="email">
          </div>
          <div class="col-12">
            <textarea id="public-ai-lead-details" class="form-control form-control-sm" rows="2" maxlength="1000" placeholder="What should we help with?" required></textarea>
          </div>
        </div>
        <div class="d-flex justify-content-between align-items-center gap-2 mt-2">
          <span id="public-ai-lead-status" class="small text-muted" role="status"></span>
          <button type="submit" id="public-ai-lead-submit" class="btn btn-success btn-sm">Send request</button>
        </div>
      </form>
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
