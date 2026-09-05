<?php
/**
 * My Family — full in-shell staff family workspace.
 *
 * This page runs INSIDE the authenticated app shell (home.php?route=my_family)
 * and authenticates every family API call with the active staff JWT through
 * FamilyController (/api/family/*). It deliberately does NOT redirect to the
 * standalone parent portal, so a staff member who is also a parent/guardian can
 * manage their linked children and relatives without ever leaving the panel.
 */
?>
<div class="my-family-workspace">
    <!-- Page Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h4 class="mb-1"><i class="bi bi-house-heart-fill me-2 text-success"></i>My Family</h4>
            <p class="text-muted mb-0">People linked to you — children, siblings, relatives and dependants you support.</p>
        </div>
        <div class="btn-group">
            <button class="btn btn-outline-secondary btn-sm" type="button" id="mfRefresh" title="Refresh">
                <i class="bi bi-arrow-clockwise me-1"></i>Refresh
            </button>
            <button class="btn btn-outline-secondary btn-sm" type="button" id="mfHelp" title="What counts as family?">
                <i class="bi bi-question-circle me-1"></i>Help
            </button>
        </div>
    </div>

    <!-- KPI strip -->
    <div class="row g-3 mb-4" id="mfKpis"></div>

    <div class="row g-4">
        <!-- Linked people -->
        <div class="col-lg-5">
            <div class="card dash-card h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-people-fill me-2 text-success"></i>Linked learners &amp; relatives</h6>
                    <span class="badge text-bg-secondary" id="mfChildCount">0</span>
                </div>
                <div id="mfLoading" class="text-center py-4">
                    <div class="spinner-border text-success" role="status"></div>
                </div>
                <div id="mfError" class="alert alert-danger d-none mx-3 mt-3 mb-0"></div>
                <div class="card-body" id="mfChildrenCards"></div>
            </div>
        </div>

        <!-- Detail panel -->
        <div class="col-lg-7">
            <div class="card dash-card">
                <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-person-bounding-box me-2 text-success"></i><span id="mfDetailTitle">Select a learner</span></h6>
                    <div class="d-flex align-items-center gap-2">
                        <select id="mfChildSwitcher" class="form-select form-select-sm d-none" style="max-width:220px" aria-label="Switch learner"></select>
                    </div>
                </div>
                <div class="card-body" id="mfDetail">
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-mouse fs-1 d-block mb-2"></i>
                        Choose a linked person on the left to view their fees, payments,
                        attendance, learning, report cards, messages and portfolio.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Help modal: what counts as family -->
<div class="modal fade" id="mfHelpModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-house-heart me-2"></i>What counts as my family?</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>This page shows the learners and relatives the school has linked to your
                staff or guardian profile. Representatives can include:</p>
                <ul class="mb-0">
                    <li>Your own children attending the school</li>
                    <li>Siblings, brothers, sisters</li>
                    <li>Other relatives (nephew, niece, cousin, grandchild)</li>
                    <li>Learners you sponsor or legally support as a guardian</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php asset_script($appBase, 'js/pages/my_family.js'); ?>
