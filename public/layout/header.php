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
$siteBase = public_site_base();
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
$socialImage = $siteBase . '/uploads/school_assets/official_school_logo.png?v=' . asset_version('uploads/school_assets/official_school_logo.png');
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
  // Use verified profile URLs only. The Instagram link in the footer is a
  // post, not a profile, so it is deliberately not used as a sameAs identity.
  'sameAs' => [
    'https://www.facebook.com/profile.php/?id=100066942647437',
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
// Site-level and breadcrumb structured data. One canonical site identity is
// linked from every page via @id, so Search can connect pages into one site.
$websiteData = [
  '@context' => 'https://schema.org',
  '@type' => 'WebSite',
  '@id' => $siteBase . '/#website',
  'url' => $siteBase . '/',
  'name' => 'Kingsway Preparatory School',
  'inLanguage' => 'en-KE',
  'publisher' => ['@id' => $siteBase . '/#school'],
];
$breadcrumbLabels = [
  'about' => 'About Us', 'admissions' => 'Admissions', 'news' => 'News & Updates',
  'news-article' => 'News', 'events' => 'Events', 'event-detail' => 'Events',
  'careers' => 'Careers', 'job-detail' => 'Careers', 'contact' => 'Contact Us',
  'downloads' => 'Downloads', 'gallery' => 'Gallery',
  'uniform-catalog' => 'Uniform Store', 'product-details' => 'Uniform Store',
  'privacy-policy' => 'Privacy Policy', 'terms-of-use' => 'Terms of Use',
  'cookie-policy' => 'Cookie Policy',
];
$breadcrumbKey = $canonicalRouteKey ?? ($activePage === 'uniforms' ? 'uniform-catalog' : $activePage);
$breadcrumbData = null;
if (!$robotsNoindex && isset($breadcrumbLabels[$breadcrumbKey])) {
  $breadcrumbItems = [
    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => $siteBase . '/'],
  ];
  $breadcrumbParents = [
    'news-article' => 'news', 'event-detail' => 'events',
    'job-detail' => 'careers', 'product-details' => 'uniform-catalog',
  ];
  $parentKey = $breadcrumbParents[$breadcrumbKey] ?? null;
  $position = 2;
  if ($parentKey !== null && isset($breadcrumbLabels[$parentKey])) {
    $breadcrumbItems[] = [
      '@type' => 'ListItem', 'position' => $position++, 'name' => $breadcrumbLabels[$parentKey],
      'item' => $siteBase . '/' . public_clean_url($parentKey),
    ];
  }
  $breadcrumbItems[] = ['@type' => 'ListItem', 'position' => $position, 'name' => $pageTitle, 'item' => $canonicalUrl];
  $breadcrumbData = [
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => $breadcrumbItems,
  ];
}
$jsonLdFlags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <?php /* Load first: silences every console.* call site (incl. inline <script>)
        across public pages and routes warnings/errors to the file logger. */ ?>
  <?= csp_bootstrap_script($appBase) ?>
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
  <?php if (!$robotsNoindex): ?>
  <link rel="alternate" hreflang="en-KE" href="<?= htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8') ?>">
  <link rel="alternate" hreflang="x-default" href="<?= htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8') ?>">
  <?php endif; ?>
  <?php if ($robotsNoindex): ?>
  <meta name="robots" content="noindex, nofollow">
  <?php endif; ?>
  <?php $siteVerification = trim((string) getenv('GOOGLE_SITE_VERIFICATION')); ?>
  <?php if ($siteVerification !== ''): ?>
  <meta name="google-site-verification" content="<?= htmlspecialchars($siteVerification, ENT_QUOTES, 'UTF-8') ?>">
  <?php endif; ?>
  <meta name="theme-color" content="#178a50">
  <link rel="manifest" href="<?= $appBase ?>/manifest.webmanifest">
  <?php if ($canonicalPageKey === 'home'): ?>
  <!-- The first hero image is a CSS custom property; preload it so the
       preload scanner can start the LCP request before CSS is parsed. -->
  <link rel="preload" as="image" type="image/webp" href="<?= $appBase ?>/uploads/school_assets/bg_images/school_hero_classroom_v2.webp?v=<?= asset_version('uploads/school_assets/bg_images/school_hero_classroom_v2.webp') ?>" fetchpriority="high">
  <?php endif; ?>
  <meta property="og:type" content="<?= isset($canonicalRouteKey) && $canonicalRouteKey === 'news-article' ? 'article' : 'website' ?>">
  <meta property="og:locale" content="en_KE">
  <meta property="og:site_name" content="Kingsway Preparatory School">
  <meta property="og:title" content="<?= htmlspecialchars($pageTitle . ' | Kingsway Preparatory School', ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:description" content="<?= htmlspecialchars($pageDescription, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:url" content="<?= htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:image" content="<?= htmlspecialchars($pageImage, ENT_QUOTES, 'UTF-8') ?>">
  <?php if ($pageImage === $socialImage): ?>
  <meta property="og:image:width" content="1254">
  <meta property="og:image:height" content="1254">
  <?php endif; ?>
  <meta property="og:image:alt" content="Kingsway Preparatory School, Londiani, Kenya">
  <meta name="twitter:card" content="<?= $pageImage !== $socialImage ? 'summary_large_image' : 'summary' ?>">
  <meta name="twitter:title" content="<?= htmlspecialchars($pageTitle . ' | Kingsway Preparatory School', ENT_QUOTES, 'UTF-8') ?>">
  <meta name="twitter:description" content="<?= htmlspecialchars($pageDescription, ENT_QUOTES, 'UTF-8') ?>">
  <meta name="twitter:image" content="<?= htmlspecialchars($pageImage, ENT_QUOTES, 'UTF-8') ?>">
  <?php if ($canonicalPageKey === 'home'): ?>
  <script nonce="<?= csp_nonce() ?>" type="application/ld+json"><?= json_encode([$organizationData, $websiteData], $jsonLdFlags) ?></script>
  <?php endif; ?>
  <?php if (!empty($breadcrumbData)): ?>
  <script nonce="<?= csp_nonce() ?>" type="application/ld+json"><?= json_encode($breadcrumbData, $jsonLdFlags) ?></script>
  <?php endif; ?>
  <?php if (!empty($pageStructuredData) && is_array($pageStructuredData)): ?>
  <script nonce="<?= csp_nonce() ?>" type="application/ld+json"><?= json_encode($pageStructuredData, $jsonLdFlags) ?></script>
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
  <link href="<?= $appBase ?>/public/vendor/bootstrap/css/bootstrap.min.css?v=<?= asset_version('public/vendor/bootstrap/css/bootstrap.min.css') ?>" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= $appBase ?>/css/public.css?v=<?= asset_version('css/public.css') ?>">
  <noscript><link rel="stylesheet" href="<?= $appBase ?>/css/no-script.css?v=<?= asset_version('css/no-script.css') ?>"></noscript>
</head>
<body<?= $bodyClass !== '' ? ' class="' . htmlspecialchars($bodyClass, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
<script nonce="<?= csp_nonce() ?>">
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

    <a class="navbar-brand" href="<?= $appBase ?>/">
      <img src="<?= $appBase ?>/uploads/school_assets/official_school_logo.png?v=<?= asset_version('uploads/school_assets/official_school_logo.png') ?>" alt="Kingsway Preparatory School" class="school-logo" data-kw-onerror="this.onerror=null;this.src='<?= $appBase ?>/images/official_school_logo.png';">
      <span class="school-name">Kingsway Preparatory School</span>
    </a>

    <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#siteNav" aria-controls="siteNav" aria-expanded="false">
      <i class="bi bi-list text-white fs-3"></i>
    </button>

    <div class="collapse navbar-collapse" id="siteNav">
      <ul class="navbar-nav ms-auto align-items-lg-center gap-1">

        <li class="nav-item">
          <a class="nav-link <?= $activePage==='home'?'active':'' ?>" href="<?= $appBase ?>/">Home</a>
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
            <li><a class="dropdown-item" href="<?= $appBase ?>/parent_portal.php" rel="nofollow"><i class="bi bi-people me-2 text-success"></i>Parent Portal</a></li>
          </ul>
        </li>

        <li class="nav-item">
          <a class="nav-link btn-login" href="<?= $appBase ?>/login" rel="nofollow">
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
