<?php
/**
 * Academic Planning Oversight
 * Read-only cross-module view for Directors and School Administrators.
 * All data loading and filtering is handled by js/pages/academic_planning_oversight.js.
 */
?>
<style>
    .apo-hero { background: linear-gradient(135deg, #146c43, #198754); color: #fff; border-radius: 12px; padding: 1.35rem 1.5rem; }
    .apo-kpi { border: 0; border-top: 4px solid #198754; box-shadow: 0 2px 10px rgba(0,0,0,.07); height: 100%; }
    .apo-kpi .value { font-size: 1.7rem; font-weight: 700; color: #146c43; }
    .apo-tabs .nav-link { color: #146c43; font-weight: 600; }
    .apo-tabs .nav-link.active { color: #fff; background: #198754; }
    .apo-table { font-size: .86rem; }
    .apo-table thead th { white-space: nowrap; background: #198754; color: #fff; }
    .apo-progress { height: 8px; min-width: 90px; }
    .apo-empty { min-height: 140px; display: grid; place-items: center; }
</style>

<div class="apo-hero d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-1"><i class="bi bi-journal-check me-2"></i>Academic Planning Oversight</h4>
        <small class="opacity-75">Monitor schemes of work, lesson delivery, coverage, approvals and reconciliation.</small>
        <div class="small mt-2" id="apoContext">Loading current academic context…</div>
    </div>
    <div class="d-flex flex-wrap gap-2 align-items-end">
        <div><label class="form-label small mb-1" for="apoYear">Academic year</label><select class="form-select form-select-sm" id="apoYear"></select></div>
        <div><label class="form-label small mb-1" for="apoTerm">Term</label><select class="form-select form-select-sm" id="apoTerm"></select></div>
        <button class="btn btn-light btn-sm" id="apoRefresh"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
    </div>
</div>

<div class="row g-3 mb-3" id="apoKpis">
    <div class="col-6 col-xl-3"><div class="card apo-kpi"><div class="card-body"><div class="value" id="apoSchemeCount">—</div><small>Schemes of Work</small></div></div></div>
    <div class="col-6 col-xl-3"><div class="card apo-kpi"><div class="card-body"><div class="value" id="apoLessonCount">—</div><small>Lesson Plans</small></div></div></div>
    <div class="col-6 col-xl-3"><div class="card apo-kpi"><div class="card-body"><div class="value" id="apoPendingCount">—</div><small>Awaiting Review</small></div></div></div>
    <div class="col-6 col-xl-3"><div class="card apo-kpi"><div class="card-body"><div class="value" id="apoReconciliationCount">—</div><small>Reconciliation Items</small></div></div></div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white border-0 pb-0">
        <ul class="nav nav-pills apo-tabs gap-1" role="tablist">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#apoSchemes" type="button">Schemes of Work</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#apoLessons" type="button">Lesson Plans</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#apoClasses" type="button">Plans by Class</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#apoTeachers" type="button">Plans by Teacher</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#apoCoverage" type="button">Coverage & Delivery</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#apoApproval" type="button">Approval & Reconciliation</button></li>
        </ul>
    </div>
    <div class="card-body">
        <div class="tab-content">
            <div class="tab-pane fade show active" id="apoSchemes">
                <div class="d-flex justify-content-between align-items-center mb-2"><h6 class="mb-0">Schemes in the current term</h6><input class="form-control form-control-sm w-auto" id="apoSchemeSearch" placeholder="Search scheme…"></div>
                <div class="table-responsive"><table class="table table-hover apo-table" id="apoSchemesTable"><thead><tr><th>#</th><th>Learning Area</th><th>Grade</th><th>Stream</th><th>Teacher</th><th>Weeks</th><th>Progress</th><th>Status</th><th>Actions</th></tr></thead><tbody></tbody></table></div>
            </div>
            <div class="tab-pane fade" id="apoLessons">
                <div class="d-flex justify-content-between align-items-center mb-2"><h6 class="mb-0">Lesson plans in the current term</h6><select class="form-select form-select-sm w-auto" id="apoLessonStatus"><option value="">All statuses</option><option value="draft">Draft (with the teacher)</option><option value="submitted">Submitted for review</option><option value="approved">Approved, not yet published</option><option value="published">Published to parents</option></select></div>
                <div class="table-responsive"><table class="table table-hover apo-table" id="apoLessonsTable"><thead><tr><th>#</th><th>Learning Area</th><th>Grade</th><th>Stream</th><th>Teacher</th><th>Plans</th><th>Delivery Dates</th><th>Status</th><th>Actions</th></tr></thead><tbody></tbody></table></div>
            </div>
            <div class="tab-pane fade" id="apoClasses">
                <h6>Lesson-plan coverage by class</h6><div class="table-responsive"><table class="table table-hover apo-table" id="apoClassesTable"><thead><tr><th>Class</th><th>Learning Areas</th><th>With Plans</th><th>Without Plans</th><th>Coverage</th></tr></thead><tbody></tbody></table></div>
            </div>
            <div class="tab-pane fade" id="apoTeachers">
                <h6>Teacher delivery and submission overview</h6><div class="table-responsive"><table class="table table-hover apo-table" id="apoTeachersTable"><thead><tr><th>Teacher</th><th>Plans</th><th>Approved</th><th>Pending</th><th>Coverage</th></tr></thead><tbody></tbody></table></div>
            </div>
            <div class="tab-pane fade" id="apoCoverage">
                <div class="row g-3 mb-3"><div class="col-md-4"><div class="alert alert-success mb-0"><strong id="apoFullCoverage">0</strong><br><small>Classes with full coverage</small></div></div><div class="col-md-4"><div class="alert alert-warning mb-0"><strong id="apoPartialCoverage">0</strong><br><small>Classes with partial coverage</small></div></div><div class="col-md-4"><div class="alert alert-danger mb-0"><strong id="apoNoCoverage">0</strong><br><small>Classes without plans</small></div></div></div>
                <p class="text-muted small mb-0">Coverage is calculated from configured academic-year class learning areas and recorded lesson plans. It is a monitoring indicator, not an approval decision.</p>
            </div>
            <div class="tab-pane fade" id="apoApproval">
                <div class="row g-3 mb-3" id="apoApprovalCards"></div>
                <h6>Legacy academic-content reconciliation</h6><div class="table-responsive"><table class="table table-hover apo-table" id="apoReconciliationTable"><thead><tr><th>Content Type</th><th>Status</th><th>Reason</th><th>Records</th><th>Actions</th></tr></thead><tbody></tbody></table></div>
                <p class="text-muted small mb-0">This tab is read-only. Reconciliation decisions remain in the controlled reconciliation workflow.</p>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="apoLessonModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white"><div><h5 class="modal-title mb-1" id="apoLessonModalTitle">Lesson Plans</h5><small id="apoLessonModalMeta"></small></div><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body" id="apoLessonModalBody"></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button><button type="button" class="btn btn-outline-success d-none" id="apoApproveAllLessons" data-year-write><i class="bi bi-check2-all me-1"></i>Approve all submitted</button><button type="button" class="btn btn-success d-none" id="apoPublishAllLessons" data-year-write><i class="bi bi-broadcast me-1"></i>Publish all approved</button></div>
        </div>
    </div>
</div>

<div class="modal fade" id="apoSchemeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen-lg-down modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <div><h5 class="modal-title mb-1" id="apoSchemeModalTitle">Scheme of Work</h5><small id="apoSchemeModalMeta"></small></div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="apoSchemeModalBody"></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button><button type="button" class="btn btn-success d-none" id="apoApproveScheme" data-year-write><i class="bi bi-check2-circle me-1"></i>Approve complete workbook</button><button type="button" class="btn btn-success d-none" id="apoPublishScheme" data-year-write><i class="bi bi-broadcast me-1"></i>Publish to parents</button></div>
        </div>
    </div>
</div>

<?php asset_script($appBase ?? '', 'js/pages/academic_planning_oversight.js'); ?>
