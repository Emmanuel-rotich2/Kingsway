<?php
/**
 * Grading Status Page – Production UI
 * All logic handled in: js/pages/grading_status.js
 * UI Theme: Green / White (Academic Professional)
 *
 * Role-based access:
 * - Headteacher: View all grading progress across subjects and teachers
 * - Subject Teacher: View own grading progress
 * - Admin: Full access
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
    text-align: center;
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
</style>

<!-- =======================================================
 HEADER
======================================================= -->
<div class="academic-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
        <h2 class="mb-1">
            <i class="bi bi-clipboard-check me-2"></i>Grading Status
        </h2>
        <small class="opacity-75">
            Monitor grading completion progress across all subjects and classes
        </small>
    </div>
    <div class="btn-group">
        <button class="btn btn-light btn-sm" id="refreshBtn">
            <i class="bi bi-arrow-clockwise me-1"></i>Refresh
        </button>
        <button class="btn btn-light btn-sm" id="exportGradingBtn"
                data-role="headteacher,admin">
            <i class="bi bi-download me-1"></i>Export
        </button>
        <button class="btn btn-light btn-sm" id="printGradingBtn">
            <i class="bi bi-printer me-1"></i>Print
        </button>
    </div>
</div>

<!-- =======================================================
 KPI STATISTICS
======================================================= -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-number" id="totalSubjects">0</div>
            <small>Total Subjects</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-number" id="fullyGraded">0</div>
            <small>Fully Graded</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-number" id="partiallyGraded">0</div>
            <small>Partially Graded</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-number" id="notStarted">0</div>
            <small>Not Started</small>
        </div>
    </div>
</div>

<!-- =======================================================
 OVERALL PROGRESS BAR
======================================================= -->
<div class="academic-card p-3 mb-4">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="mb-0"><i class="bi bi-bar-chart-steps me-2"></i>Overall Grading Progress</h6>
        <span class="badge" style="background: var(--acad-primary);" id="overallPercentage">0%</span>
    </div>
    <div class="progress" style="height: 24px; border-radius: 8px;">
        <div class="progress-bar" role="progressbar" id="overallProgressBar"
             style="width: 0%; background: var(--acad-primary);"
             aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
    </div>
</div>

<!-- =======================================================
 FILTER BAR
======================================================= -->
<div class="academic-card p-3 mb-4">
    <div class="row g-3 align-items-end">
        <div class="col-md-4">
            <label class="form-label fw-semibold">Term</label>
            <select class="form-select" id="termFilter">
                <option value="">All Terms</option>
                <option value="1">Term 1</option>
                <option value="2">Term 2</option>
                <option value="3">Term 3</option>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">Class</label>
            <select class="form-select" id="classFilter">
                <option value="">All Classes</option>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">Status</label>
            <select class="form-select" id="statusFilter">
                <option value="">All Status</option>
                <option value="complete">Fully Graded</option>
                <option value="partial">Partially Graded</option>
                <option value="not_started">Not Started</option>
            </select>
        </div>
    </div>
</div>

<!-- =======================================================
 DATA TABLE
======================================================= -->
<div class="academic-card p-3">
    <div class="table-responsive">
        <table class="table table-hover table-academic" id="gradingStatusTable">
            <thead>
                <tr>
                    <th>Subject</th>
                    <th>Teacher</th>
                    <th>Class</th>
                    <th>Total Students</th>
                    <th>Graded</th>
                    <th>Pending</th>
                    <th>Completion</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">
                        <div class="spinner-border spinner-border-sm text-success me-2"></div>
                        Loading grading status...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <nav>
        <ul class="pagination justify-content-center" id="pagination"></ul>
    </nav>
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
<script src="/Kingsway/js/pages/grading_status.js"></script>
