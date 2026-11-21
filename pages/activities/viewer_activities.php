<?php
/**
 * Activities - Viewer Layout
 * Read-only layout for Students, Parents, Guardians, Interns
 * 
 * Features:
 * - No sidebar (full-width content)
 * - Single summary card
 * - Simple list view (not table)
 * - Read-only (no actions)
 * - Clean, minimal interface
 */
?>

<link rel="stylesheet" href="/css/school-theme.css?v=<?= asset_version('css/school-theme.css') ?>">
<link rel="stylesheet" href="/css/roles/viewer-theme.css?v=<?= asset_version('css/roles/viewer-theme.css') ?>">

<div class="viewer-layout">
    <!-- Header -->
    <header class="viewer-header">
        <div class="logo-title">
            <img src="<?= $appBase ?>/uploads/school_assets/official_school_logo.png" alt="Kingsway Logo" data-kw-onerror="this.onerror=null;this.src='<?= $appBase ?>/images/official_school_logo.png';">
            <span>Kingsway Preparatory School</span>
        </div>
        <h1 class="page-title">Activities</h1>
        <div class="viewer-user-info">
            <span class="user-name" id="userName">Student</span>
            <div class="user-avatar" id="userAvatar">S</div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="viewer-main">
        <!-- Notice Banner -->
        <div class="viewer-notice">
            <span class="notice-icon">ℹ️</span>
            <div class="notice-content">
                <div class="notice-title">Welcome to School Activities</div>
                <div class="notice-text">Browse available activities and see upcoming events.</div>
            </div>
        </div>

        <!-- Summary Card -->
        <div class="viewer-summary-card">
            <div class="summary-icon">🏆</div>
            <div class="summary-value" id="activeCount">0</div>
            <div class="summary-label">Active Activities</div>
        </div>

        <!-- Info Cards -->
        <div class="viewer-info-grid">
            <div class="viewer-info-card">
                <div class="info-label">Total Activities</div>
                <div class="info-value" id="totalActivities">0</div>
            </div>
            <div class="viewer-info-card">
                <div class="info-label">Upcoming</div>
                <div class="info-value" id="upcomingActivities">0</div>
            </div>
        </div>

        <!-- Activity List -->
        <div class="viewer-list-card">
            <div class="viewer-list-header">
                <span class="list-title">School Activities</span>
                <span class="list-count" id="listCount">0</span>
            </div>
            <ul class="viewer-list" id="activitiesList">
                <!-- Activities loaded dynamically -->
            </ul>
        </div>
    </main>

    <!-- Footer -->
    <footer class="viewer-footer">
        Kingsway Preparatory School &copy; <?php echo date('Y'); ?>
    </footer>
</div>

<?php asset_script($appBase, 'js/components/RoleBasedUI.js'); ?>
<?php asset_script($appBase, 'js/pages/activities.js'); ?>
