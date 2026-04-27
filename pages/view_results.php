<?php
/**
 * View Student Results — Production UI
 * JS: js/pages/view_results.js
 *
 * API:
 *   GET /academic/terms-list   → terms
 *   GET /academic/classes-list → classes
 *   GET /students/student      → students (data.data.students[], double-wrapped)
 */
?>
<style>
    :root {
        --vr-primary: #1b5e20;
        --vr-mid: #2e7d32;
        --vr-soft: #c8e6c9;
        --vr-shadow: 0 2px 10px rgba(0, 0, 0, 0.07);
        --vr-radius: 12px;
    }

    .vr-hero {
        background: linear-gradient(135deg, var(--vr-primary) 0%, #388e3c 100%);
        color: #fff;
        border-radius: var(--vr-radius);
        padding: 1.5rem 2rem;
        margin-bottom: 1.4rem;
        box-shadow: 0 4px 16px rgba(27, 94, 32, .22);
    }

    .vr-hero h4 {
        font-size: 1.25rem;
        font-weight: 700;
        margin: 0 0 .2rem;
    }

    .vr-filter {
        background: #fff;
        border-radius: var(--vr-radius);
        border: 1px solid #e8f5e9;
        padding: 1rem 1.4rem;
        box-shadow: var(--vr-shadow);
        margin-bottom: 1.2rem;
    }

    .vr-card {
        background: #fff;
        border-radius: var(--vr-radius);
        border-left: 4px solid var(--vr-mid);
        box-shadow: var(--vr-shadow);
    }

    .vr-card .card-header {
        background: #f1f8e9;
        border-bottom: 1px solid var(--vr-soft);
        padding: .8rem 1.2rem;
        font-weight: 600;
        font-size: .9rem;
        color: var(--vr-primary);
    }

    /* Subject result rows */
    .subject-row {
        border-left: 3px solid var(--vr-soft);
    }

    .subject-row.ee-row {
        border-left-color: #1b5e20;
    }

    .subject-row.me-row {
        border-left-color: #388e3c;
    }

    .subject-row.ae-row {
        border-left-color: #f57c00;
    }

    .subject-row.be-row {
        border-left-color: #b71c1c;
    }

    .grade-EE {
        background: #1b5e20;
        color: #fff;
        padding: 2px 9px;
        border-radius: 20px;
        font-size: .75rem;
        font-weight: 700;
    }

    .grade-ME {
        background: #388e3c;
        color: #fff;
        padding: 2px 9px;
        border-radius: 20px;
        font-size: .75rem;
        font-weight: 700;
    }

    .grade-AE {
        background: #f57c00;
        color: #fff;
        padding: 2px 9px;
        border-radius: 20px;
        font-size: .75rem;
        font-weight: 700;
    }

    .grade-BE {
        background: #b71c1c;
        color: #fff;
        padding: 2px 9px;
        border-radius: 20px;
        font-size: .75rem;
        font-weight: 700;
    }

    .student-profile-card {
        background: linear-gradient(135deg, #e8f5e9, #fff);
        border-radius: var(--vr-radius);
        border: 1px solid var(--vr-soft);
        padding: 1.25rem 1.5rem;
        margin-bottom: 1.2rem;
    }

    .profile-avatar {
        width: 56px;
        height: 56px;
        border-radius: 50%;
        background: var(--vr-primary);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
        font-weight: 700;
        flex-shrink: 0;
    }

    .vr-empty {
        text-align: center;
        padding: 3.5rem;
        color: #9e9e9e;
    }

    .vr-empty i {
        font-size: 3rem;
        display: block;
        margin-bottom: .75rem;
        opacity: .4;
    }

    .vr-loading {
        text-align: center;
        padding: 3rem;
    }

    .vr-loading .spinner-border {
        width: 2.5rem;
        height: 2.5rem;
        border-color: var(--vr-primary);
        border-right-color: transparent;
    }

    .btn-vr {
        background: var(--vr-primary);
        color: #fff;
        border: none;
        border-radius: 8px;
    }

    .btn-vr:hover {
        background: var(--vr-mid);
        color: #fff;
    }

    .btn-vr-outline {
        background: #fff;
        color: var(--vr-primary);
        border: 2px solid var(--vr-primary);
        border-radius: 8px;
    }

    .btn-vr-outline:hover {
        background: var(--vr-primary);
        color: #fff;
    }

    .perf-bar {
        height: 8px;
        border-radius: 4px;
        background: #e0e0e0;
        overflow: hidden;
    }

    .perf-bar-fill {
        height: 100%;
        border-radius: 4px;
        transition: width .4s ease;
    }
</style>

<!-- HERO -->
<div class="vr-hero d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
        <h4><i class="bi bi-bar-chart-line me-2"></i>View Student Results</h4>
        <small>Browse individual CBC results across subjects and terms</small>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-light btn-sm" onclick="viewResultsCtrl.printResults()">
            <i class="bi bi-printer me-1"></i>Print
        </button>
        <button class="btn btn-light btn-sm" onclick="viewResultsCtrl.exportCSV()">
            <i class="bi bi-filetype-csv me-1"></i>Export CSV
        </button>
    </div>
</div>

<!-- FILTER BAR -->
<div class="vr-filter">
    <div class="row g-3 align-items-end">
        <div class="col-md-3">
            <label class="form-label small fw-semibold mb-1">Term</label>
            <select class="form-select form-select-sm" id="vrTermSelect">
                <option value="">All Terms</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold mb-1">Class <span class="text-danger">*</span></label>
            <select class="form-select form-select-sm" id="vrClassSelect" onchange="viewResultsCtrl.loadStudents()">
                <option value="">— Select Class —</option>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label small fw-semibold mb-1">Student <span class="text-danger">*</span></label>
            <select class="form-select form-select-sm" id="vrStudentSelect" onchange="viewResultsCtrl.loadResults()">
                <option value="">— Select Student —</option>
            </select>
        </div>
        <div class="col-md-2">
            <button class="btn btn-vr btn-sm w-100" onclick="viewResultsCtrl.loadResults()">
                <i class="bi bi-search me-1"></i>Load
            </button>
        </div>
    </div>
</div>

<!-- RESULTS CONTAINER -->
<div id="resultsContainer">
    <div class="vr-empty">
        <i class="bi bi-person-badge"></i>
        Select a class and student above to view their CBC academic results
    </div>
</div>

<!-- Toast -->
<div class="position-fixed top-0 end-0 p-3" style="z-index:11000">
    <div id="vrToast" class="toast align-items-center border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body" id="vrToastBody">Message</div>
            <button class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<script src="<?= $appBase ?>/js/pages/view_results.js"></script>
