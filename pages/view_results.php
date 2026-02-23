<?php
/**
 * View Results Page – Production UI
 * All logic handled in: js/pages/view_results.js (viewResultsController)
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
</style>

<!-- =======================================================
 HEADER
======================================================= -->
<div class="academic-header d-flex justify-content-between align-items-center">
    <div>
        <h2 class="mb-1">
            <i class="bi bi-bar-chart-line me-2"></i>View Student Results
        </h2>
        <small class="opacity-75">
            Browse and export individual student academic records
        </small>
    </div>
    <div class="btn-group">
        <button class="btn btn-light btn-sm" onclick="viewResultsController.printResults()">
            <i class="bi bi-printer me-1"></i>Print
        </button>
        <button class="btn btn-light btn-sm" onclick="viewResultsController.exportCSV()">
            <i class="bi bi-download me-1"></i>Export CSV
        </button>
    </div>
</div>

<!-- =======================================================
 FILTER SELECTORS
======================================================= -->
<div class="academic-card p-3 mb-4">
    <div class="row g-3 align-items-end">
        <div class="col-md-5">
            <label class="form-label fw-semibold">Select Class</label>
            <select id="classSelect" class="form-select" required onchange="viewResultsController.loadStudents()">
                <option value="">-- Select Class --</option>
            </select>
        </div>
        <div class="col-md-5">
            <label class="form-label fw-semibold">Select Student</label>
            <select id="studentSelect" class="form-select" required onchange="viewResultsController.loadResults()">
                <option value="">-- Select Student --</option>
            </select>
        </div>
        <div class="col-md-2">
            <button class="btn btn-academic w-100" onclick="viewResultsController.loadResults()">
                <i class="bi bi-search me-1"></i>Search
            </button>
        </div>
    </div>
</div>

<!-- =======================================================
 RESULTS DISPLAY (JS renders everything here)
======================================================= -->
<div id="resultsContainer">
    <div class="academic-card p-4 text-center text-muted">
        <i class="bi bi-person-badge" style="font-size: 2.5rem; color: var(--acad-primary);"></i>
        <p class="mt-3 mb-0">Select a class and student to view their academic results</p>
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
<script src="/Kingsway/js/pages/view_results.js"></script>
