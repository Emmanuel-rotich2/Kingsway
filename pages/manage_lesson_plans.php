<?php
/**
 * Lesson Plans Management – Production UI
 * All logic handled in: js/pages/manage_lesson_plans.js
 * UI Theme: Green / White (Academic Professional)
 */
?>

<style>
/* =========================================================
   DESIGN TOKENS
========================================================= */
:root {
    --acad-primary: #198754;
    --acad-primary-dark: #146c43;
    --acad-primary-soft: #d1e7dd;
    --acad-bg-light: #f8f9fa;
    --acad-white: #ffffff;
    --acad-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}

.academic-header {
    background: linear-gradient(135deg, var(--acad-primary), var(--acad-primary-dark));
    color: #fff;
    border-radius: 12px;
    padding: 1.75rem 2rem;
    margin-bottom: 2rem;
    box-shadow: var(--acad-shadow);
}

.academic-card {
    background: var(--acad-white);
    border-radius: 12px;
    border-left: 4px solid var(--acad-primary);
    box-shadow: var(--acad-shadow);
    margin-bottom: 1.75rem;
}

.stat-card {
    background: var(--acad-primary-soft);
    border-radius: 10px;
    padding: 1.2rem;
    height: 100%;
}

.stat-number {
    font-size: 1.9rem;
    font-weight: 700;
    color: var(--acad-primary-dark);
}

.btn-academic {
    background: var(--acad-primary);
    color: #fff;
    border: none;
}

.btn-academic:hover {
    background: var(--acad-primary-dark);
    color: #fff;
}

.table-academic thead {
    background: var(--acad-primary);
    color: #fff;
}

.nav-tabs {
    border-bottom: none;
}

.nav-tabs .nav-link {
    border: none;
    font-weight: 500;
    color: var(--acad-primary-dark);
    padding: 0.75rem 1.1rem;
}

.nav-tabs .nav-link.active {
    border-bottom: 3px solid var(--acad-primary);
    color: var(--acad-primary);
    background: transparent;
}
</style>

<!-- =======================================================
 HEADER
======================================================= -->
<div class="academic-header d-flex justify-content-between align-items-center">
    <div>
        <h2 class="mb-1">
            <i class="bi bi-journal-text me-2"></i>Lesson Plans Management
        </h2>
        <small class="opacity-75">
            Create, review, and approve lesson plans
        </small>
    </div>
    <button class="btn btn-light btn-sm" id="btnCreateLessonPlan" data-bs-toggle="modal" data-bs-target="#addLessonPlanModal">
        <i class="bi bi-plus-circle me-1"></i>Create Lesson Plan
    </button>
</div>

<!-- =======================================================
 STATISTICS
======================================================= -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-number" id="lpTotalCount">0</div>
            <small>Total Plans</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-number" id="lpApprovedCount">0</div>
            <small>Approved</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-number" id="lpPendingCount">0</div>
            <small>Pending Review</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-number" id="lpDraftCount">0</div>
            <small>Drafts</small>
        </div>
    </div>
</div>

<!-- =======================================================
 FILTER BAR
======================================================= -->
<div class="academic-card p-3 mb-4">
    <div class="row g-3">
        <div class="col-md-3">
            <label class="form-label fw-semibold">Class</label>
            <select id="lpFilterClass" class="form-select">
                <option value="">All Classes</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold">Status</label>
            <select id="lpFilterStatus" class="form-select">
                <option value="">All Statuses</option>
                <option value="draft">Draft</option>
                <option value="submitted">Submitted</option>
                <option value="approved">Approved</option>
                <option value="completed">Completed</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold">From Date</label>
            <input type="date" id="lpFilterFrom" class="form-control" placeholder="From date">
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold">To Date</label>
            <input type="date" id="lpFilterTo" class="form-control" placeholder="To date">
        </div>
    </div>
</div>

<!-- =======================================================
 TABS
======================================================= -->
<ul class="nav nav-tabs mb-4" role="tablist">
    <li class="nav-item">
        <a class="nav-link active" data-bs-toggle="tab" href="#allLessons" data-filter="">
            <i class="bi bi-list-ul me-1"></i>All Plans
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="tab" href="#myLessons" data-filter="mine">
            <i class="bi bi-person me-1"></i>My Lessons
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="tab" href="#pending" data-filter="submitted">
            <i class="bi bi-hourglass-split me-1"></i>Pending Review
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="tab" href="#approved" data-filter="approved">
            <i class="bi bi-check-circle me-1"></i>Approved
        </a>
    </li>
</ul>

<!-- =======================================================
 TAB CONTENT
======================================================= -->
<div class="tab-content">
    <div id="allLessons" class="tab-pane fade show active">
        <div class="table-responsive">
            <table class="table table-striped table-hover table-academic" id="lessonPlansTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Lesson Title</th>
                        <th>Subject</th>
                        <th>Class</th>
                        <th>Teacher</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            <div class="spinner-border spinner-border-sm text-success me-2"></div>
                            Loading lesson plans...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <nav>
            <ul class="pagination justify-content-center" id="lpPagination"></ul>
        </nav>
    </div>
    <div id="myLessons" class="tab-pane fade">
        <div class="table-responsive">
            <table class="table table-striped table-hover table-academic" id="myLessonPlansTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Lesson Title</th>
                        <th>Subject</th>
                        <th>Class</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">Select tab to load your lessons</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <div id="pending" class="tab-pane fade">
        <div class="table-responsive">
            <table class="table table-striped table-hover table-academic" id="pendingLessonPlansTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Lesson Title</th>
                        <th>Subject</th>
                        <th>Class</th>
                        <th>Teacher</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">Select tab to load pending plans</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <div id="approved" class="tab-pane fade">
        <div class="table-responsive">
            <table class="table table-striped table-hover table-academic" id="approvedLessonPlansTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Lesson Title</th>
                        <th>Subject</th>
                        <th>Class</th>
                        <th>Teacher</th>
                        <th>Date</th>
                        <th>Approved By</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">Select tab to load approved plans</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- =======================================================
 TOAST NOTIFICATIONS
======================================================= -->
<div class="position-fixed top-0 end-0 p-3" style="z-index: 11000;">
    <div id="academicToast" class="toast">
        <div class="toast-header">
            <strong id="toastTitle" class="me-auto">Notice</strong>
            <button class="btn-close" data-bs-dismiss="toast"></button>
        </div>
        <div class="toast-body" id="toastBody"></div>
    </div>
</div>

<!-- =======================================================
 SCRIPTS
======================================================= -->
<script src="<?= $appBase ?>/js/pages/manage_lesson_plans.js"></script>
