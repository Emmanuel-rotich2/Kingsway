<?php
/**
 * Dashboard — PARTIAL (injected into app shell by app_layout.php)
 *
 * This page is the role-aware dashboard router entry point.
 * JavaScript (dashboard_router.js) detects user role and loads the correct
 * role-specific dashboard controller + component.
 */
/* PARTIAL — no DOCTYPE/html/head/body. Injected into app shell via fetch. */
?>

<!-- Dashboard container — data-dashboard-page triggers DashboardRouter -->
<div id="dashboardContainer" data-dashboard-page class="py-2">
    <!-- Loading state — replaced by role-specific dashboard content -->
    <div id="dashboardLoading" class="d-flex align-items-center justify-content-center" style="min-height:300px">
        <div class="text-center">
            <div class="spinner-border text-primary mb-3" role="status">
                <span class="visually-hidden">Loading dashboard...</span>
            </div>
            <p class="text-muted">Loading your dashboard...</p>
        </div>
    </div>

    <!-- Role-specific content injected here by dashboard controller -->
    <div id="mainContent"></div>
</div>

<!-- Last refresh indicator (used by dashboard controllers) -->
<div class="text-end mt-2 text-muted small px-3">
    Last updated: <span id="lastRefreshTime">—</span>
</div>

<script src="<?= $appBase ?>js/dashboards/dashboard_router.js?v=<?php echo time(); ?>"></script>
