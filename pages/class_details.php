<?php
/**
 * Class Details Page - Drill-Down Navigation
 * Flow: Classes List → Class Details → Student Profile → Learning Area Progress
 * JS controller: js/pages/class_details.js (PageNavigator-based SPA)
 */
?>

<div>
    <!-- Breadcrumb Navigation -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb" id="breadcrumbContainer">
            <li class="breadcrumb-item active">Classes</li>
        </ol>
    </nav>

    <!-- Dynamic Content Area (PageNavigator renders into this) -->
    <div id="mainContent">
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="mt-2 text-muted">Loading classes...</p>
        </div>
    </div>
</div>

<script src="<?= $appBase ?>js/pages/class_details.js"></script>