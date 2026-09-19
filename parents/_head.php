<?php
/**
 * parents/_head.php — shared top of every parent-portal page.
 *
 * Renders the document head, the top navigation bar, a per-page optional
 * sidebar slot and opens the main content area. Each page sets the
 * expected variables BEFORE including this file:
 *
 *   $parentPageTitle   (string)  e.g. 'Fees & Payments'
 *   $parentActive      (string)  one of the $parentSections keys
 *   $parentPageScript  (string)  path to the page JS controller e.g. 'parents/fees'
 *   $parentBodyClass   (string)  optional extra body classes
 *   $parentSidebar     (bool)    whether this page renders a per-page sidebar
 *
 * The matching `parents/_foot.php` MUST be included to close the document.
 */
declare(strict_types=1);

$familyStaffMode = $familyStaffMode ?? ($_GET['staff'] ?? '') === '1';
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$appBase = $appBaseOverride ?? rtrim(str_replace('\\', '/', dirname(dirname($scriptName))), '/');
if ($appBase === '.') $appBase = '';
require_once dirname(__DIR__) . '/public/layout/public_data.php';

$parentPageTitle = $parentPageTitle ?? 'Parent Portal';
$parentActive    = $parentActive    ?? 'dashboard';
$parentPageScript= $parentPageScript?? 'parents/dashboard';
$parentBodyClass = trim((string)($parentBodyClass ?? ''));
$parentSidebar   = (bool)($parentSidebar ?? false);

$parentSections = [
    'dashboard'  => ['label' => 'Dashboard',    'href' => 'dashboard.php',  'icon' => 'bi bi-grid-1x2-fill'],
    'children'   => ['label' => 'My Children',  'href' => 'children.php',   'icon' => 'bi bi-people-fill'],
    'updates'    => ['label' => 'School Updates', 'href' => 'updates.php', 'icon' => 'bi bi-megaphone-fill'],
    'results'    => ['label' => 'Learning & Results', 'href' => 'results.php', 'icon' => 'bi bi-mortarboard-fill'],
    'learning'   => ['label' => 'Homework & Objectives', 'href' => 'learning.php', 'icon' => 'bi bi-journal-text'],
    'health'     => ['label' => 'Health & Welfare', 'href' => 'health.php', 'icon' => 'bi bi-heart-pulse-fill'],
    'activities' => ['label' => 'Clubs & Activities', 'href' => 'activities.php', 'icon' => 'bi bi-trophy-fill'],
    'attendance' => ['label' => 'Attendance',   'href' => 'attendance.php', 'icon' => 'bi bi-calendar-check-fill'],
    'messages'   => ['label' => 'Messages',     'href' => 'messages.php',   'icon' => 'bi bi-chat-dots-fill'],
    'documents'  => ['label' => 'Documents & Reports', 'href' => 'documents.php', 'icon' => 'bi bi-folder2-open'],
    'downloads'  => ['label' => 'Downloads',            'href' => 'downloads.php',  'icon' => 'bi bi-download'],
    'fees'       => ['label' => 'Fees & Payments',     'href' => 'fees.php', 'icon' => 'bi bi-receipt-cutoff'],
    'transport'  => ['label' => 'Transport',    'href' => 'transport.php',  'icon' => 'bi bi-bus-front-fill'],
    'community'  => ['label' => 'PTA & Community',     'href' => 'community.php', 'icon' => 'bi bi-person-hearts'],
    'account'    => ['label' => 'Account Settings',    'href' => 'account.php',   'icon' => 'bi bi-gear-fill'],
];
$parentNavGroups = [
    'overview'    => ['label' => 'Overview',       'icon' => 'bi bi-grid-1x2-fill',   'items' => ['dashboard']],
    'learning'    => ['label' => 'Learning',       'icon' => 'bi bi-mortarboard-fill', 'items' => ['children','results','learning','attendance']],
    'school-life' => ['label' => 'School Life',    'icon' => 'bi bi-stars',            'items' => ['updates','health','activities','community']],
    'contact'     => ['label' => 'Contact & Files','icon' => 'bi bi-chat-dots-fill',   'items' => ['messages','documents','downloads']],
    'finance'     => ['label' => 'Finance',        'icon' => 'bi bi-receipt-cutoff',   'items' => ['fees','transport']],
    'account'     => ['label' => 'My Account',     'icon' => 'bi bi-gear-fill',        'items' => ['account']],
];
$parentActiveGroup = 'overview';
foreach ($parentNavGroups as $navGroupKey => $navGroup) {
    if (in_array($parentActive, $navGroup['items'], true)) { $parentActiveGroup = $navGroupKey; break; }
}
$ppTerms  = function_exists('kw_academic_terms')  ? kw_academic_terms()  : [];
$ppGrades = function_exists('kw_active_grades')   ? kw_active_grades()   : [];
$ppAdminGradeOptions = $ppGrades ?: ['PP1','PP2','Grade 1','Grade 2','Grade 3','Grade 4','Grade 5','Grade 6','Grade 7','Grade 8','Grade 9'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <?php asset_script($appBase, 'js/core/console_logger.js'); ?>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($parentPageTitle, ENT_QUOTES, 'UTF-8') ?> — Kingsway Parent Portal</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= $appBase ?>/css/school-theme.css?v=<?= asset_version('css/school-theme.css') ?>">
  <link rel="stylesheet" href="<?= $appBase ?>/css/app-common.css?v=<?= asset_version('css/app-common.css') ?>">
  <link rel="stylesheet" href="<?= $appBase ?>/css/parent-cpanel.css?v=<?= asset_version('css/parent-cpanel.css') ?>">
  <script>
    window.APP_BASE = <?= json_encode($appBase) ?>;
    // The parent portal owns an independent family session. Prevent api.js
    // from restoring or redirecting based on an unrelated internal-staff cookie.
    window.KINGSWAY_PUBLIC_PAGE = true;
    window.FAMILY_STAFF_MODE = <?= $familyStaffMode ? 'true' : 'false' ?>;
    window.PARENT_ACTIVE = <?= json_encode($parentActive) ?>;
    window.PARENT_APPS = <?= json_encode(array_map(static function (array $s): array {
        return ['label' => $s['label'], 'href' => $s['href'], 'icon' => $s['icon']];
    }, $parentSections)) ?>;
    window.PARENT_GRADES = <?= json_encode(array_values($ppAdminGradeOptions)) ?>;
  </script>
</head>
<body class="pp-shell<?= $parentSidebar ? ' has-sidebar' : '' ?><?= $parentBodyClass !== '' ? ' ' . $parentBodyClass : '' ?>">

<!-- ═══════ TOP NAVIGATION (shared) ══════════════════════════════════════ -->
<header class="pp-topbar no-print">
  <a class="pp-brand" href="<?= $appBase ?>/parents/dashboard.php">
    <img src="<?= $appBase ?>/uploads/school_assets/official_school_logo.png" alt="Kingsway" onerror="this.onerror=null;this.src='<?= $appBase ?>/images/official_school_logo.png';">
    <span><strong>KINGSWAY PREPARATORY SCHOOL</strong><small>Parent &amp; Family Centre</small></span>
  </a>
  <nav class="pp-nav" aria-label="Parent portal sections">
    <?php $parentDesktopGroups = array_filter($parentNavGroups, static function ($groupKey): bool {
        return $groupKey !== 'account';
    }, ARRAY_FILTER_USE_KEY); ?>
    <?php foreach ($parentDesktopGroups as $groupKey => $group): $isActiveGroup = $parentActiveGroup === $groupKey; ?>
    <div class="pp-nav-group dropdown<?= $isActiveGroup ? ' active' : '' ?>">
      <button class="pp-nav-toggle dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="<?= htmlspecialchars($group['icon'], ENT_QUOTES, 'UTF-8') ?>"></i><?= htmlspecialchars($group['label'], ENT_QUOTES, 'UTF-8') ?>
      </button>
      <ul class="dropdown-menu">
        <?php foreach ($group['items'] as $itemKey): $item = $parentSections[$itemKey] ?? null; if (!$item) continue; ?>
        <li>
          <a href="<?= $appBase ?>/parents/<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') ?><?= $familyStaffMode ? '?staff=1' : '' ?>"
             class="dropdown-item<?= $parentActive === $itemKey ? ' active' : '' ?>">
            <i class="<?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?>"></i><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endforeach; ?>
    <a href="<?= $appBase ?>/parents/uniform_catalog.php<?= $familyStaffMode ? '?staff=1' : '' ?>" class="pp-nav-toggle<?= $parentActive === 'store' ? ' active' : '' ?>"><i class="bi bi-bag-heart-fill"></i>Store</a>
  </nav>
  <div class="pp-topbar-actions">
    <button class="btn btn-outline-light btn-sm pp-nav-burger" type="button" data-bs-toggle="offcanvas" data-bs-target="#ppDrawer" aria-controls="ppDrawer" aria-label="Open menu"><i class="bi bi-list"></i></button>
    <span class="pp-user-chip dropdown" title="Signed-in parent">
      <button class="pp-user-toggle dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Account menu">
        <i class="bi bi-person-circle"></i><span class="pp-user-name" id="parentName">Parent</span>
      </button>
      <ul class="dropdown-menu dropdown-menu-end pp-user-menu">
        <li><a class="dropdown-item<?= $parentActive === 'account' ? ' active' : '' ?>" href="<?= $appBase ?>/parents/account.php<?= $familyStaffMode ? '?staff=1' : '' ?>"><i class="bi bi-gear-fill"></i>Account settings</a></li>
      </ul>
    </span>
    <?php if ($familyStaffMode): ?>
    <a class="btn btn-outline-light btn-sm rounded-pill" href="<?= $appBase ?>/home.php" title="Back to staff area"><i class="bi bi-arrow-left"></i></a>
    <?php else: ?>
    <button class="btn btn-warning btn-sm rounded-pill fw-semibold" type="button" id="btnApplyAdmission"><i class="bi bi-person-plus me-1"></i><span class="pp-btn-label">New admission</span></button>
    <?php endif; ?>
    <button class="btn btn-outline-light btn-sm rounded-pill" type="button" id="btnLogout" title="Sign out"><i class="bi bi-box-arrow-right"></i></button>
  </div>
</header>

<!-- Mobile navigation drawer (visible below 1200px) -->
<div class="offcanvas offcanvas-start pp-drawer" tabindex="-1" id="ppDrawer" aria-labelledby="ppDrawerTitle">
  <div class="offcanvas-header pp-drawer-header">
    <span class="offcanvas-title" id="ppDrawerTitle"><i class="bi bi-person-heart me-2"></i>Menu</span>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body pp-drawer-body">
    <?php foreach ($parentNavGroups as $groupKey => $group): $isActiveGroup = $parentActiveGroup === $groupKey; ?>
    <div class="pp-drawer-group<?= $isActiveGroup ? ' active' : '' ?>">
      <div class="pp-drawer-group-title">
        <i class="<?= htmlspecialchars($group['icon'], ENT_QUOTES, 'UTF-8') ?>"></i><?= htmlspecialchars($group['label'], ENT_QUOTES, 'UTF-8') ?>
      </div>
      <?php foreach ($group['items'] as $itemKey): $item = $parentSections[$itemKey] ?? null; if (!$item) continue; ?>
      <a href="<?= $appBase ?>/parents/<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') ?><?= $familyStaffMode ? '?staff=1' : '' ?>"
         class="pp-drawer-item<?= $parentActive === $itemKey ? ' active' : '' ?>">
        <i class="<?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?>"></i><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
    <div class="pp-drawer-group">
      <div class="pp-drawer-group-title"><i class="bi bi-bag-heart-fill"></i>Shop</div>
      <a href="<?= $appBase ?>/parents/uniform_catalog.php<?= $familyStaffMode ? '?staff=1' : '' ?>" class="pp-drawer-item<?= $parentActive === 'store' ? ' active' : '' ?>"><i class="bi bi-bag-heart-fill"></i>Uniform Store</a>
    </div>
  </div>
</div>

<main class="pp-main">
<?php
if ($parentSidebar) {
    // Per-page sidebar slot. Pages with a per-page sidebar render their own
    // <aside class="pp-sidebar"> with a child list; the controller populates it.
    echo '<!-- per-page sidebar + content rendered by the page template -->' . PHP_EOL;
}