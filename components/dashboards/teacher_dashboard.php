<?php
/**
 * Unified Teacher Dashboard
 * Decides whether to show Class Teacher or Subject Teacher dashboard
 * and loads the appropriate component client-side.
 */
require_once __DIR__ . '/../../components/global/dashboard_base.php';
?>

<div class="container-fluid py-4" id="teacher-dashboard-container">
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="mb-1"><i class="bi bi-person-badge me-2"></i>Teacher Dashboard</h4>
            <p class="text-muted">Loading appropriate teacher dashboard...</p>
        </div>
    </div>

    <div id="teacher-dashboard-loading" class="text-center py-5">
        <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>
        <p class="mt-2 text-muted">Determining your role and loading dashboard...</p>
    </div>

    <div id="teacher-dashboard-fragment"></div>
</div>

<script src="/Kingsway/js/dashboards/teacher_dashboard.js?v=<?php echo time(); ?>"></script>