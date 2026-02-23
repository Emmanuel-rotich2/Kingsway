<?php
/**
 * Results Analysis Page – Production UI
 * All logic handled in: js/pages/results_analysis.js
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
<div class="academic-header d-flex justify-content-between align-items-center">
    <div>
        <h2 class="mb-1">
            <i class="bi bi-bar-chart-line me-2"></i>Results Analysis
        </h2>
        <small class="opacity-75">
            Analyze exam results, subject means, and grade distributions
        </small>
    </div>
    <div class="btn-group">
        <button class="btn btn-light btn-sm" id="exportResultsBtn">
            <i class="bi bi-download me-1"></i>Export Report
        </button>
        <button class="btn btn-light btn-sm" id="printResultsBtn">
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
            <div class="stat-number" id="overallMean">0%</div>
            <small>Overall Mean</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-number" id="highestSubject">-</div>
            <small>Highest Subject</small>
            <br><small class="text-muted" id="highestSubjectScore"></small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-number" id="lowestSubject">-</div>
            <small>Lowest Subject</small>
            <br><small class="text-muted" id="lowestSubjectScore"></small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-number" id="studentsAssessed">0</div>
            <small>Students Assessed</small>
        </div>
    </div>
</div>

<!-- =======================================================
 FILTER BAR
======================================================= -->
<div class="academic-card p-3 mb-4">
    <div class="row g-3 align-items-end">
        <div class="col-md-3">
            <label class="form-label fw-semibold">Term</label>
            <select class="form-select" id="termFilterResults">
                <option value="">All Terms</option>
                <option value="1">Term 1</option>
                <option value="2">Term 2</option>
                <option value="3">Term 3</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold">Class</label>
            <select class="form-select" id="classFilterResults">
                <option value="">All Classes</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold">Subject</label>
            <select class="form-select" id="subjectFilterResults">
                <option value="">All Subjects</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold">Academic Year</label>
            <select class="form-select" id="yearFilterResults">
                <option value="">Current Year</option>
            </select>
        </div>
    </div>
</div>

<!-- =======================================================
 CHART ROW
======================================================= -->
<div class="row mb-4">
    <div class="col-md-8">
        <div class="academic-card p-3">
            <h6 class="mb-3"><i class="bi bi-bar-chart me-2"></i>Subject Mean Scores</h6>
            <canvas id="subjectMeansChart" height="200"></canvas>
        </div>
    </div>
    <div class="col-md-4">
        <div class="academic-card p-3">
            <h6 class="mb-3"><i class="bi bi-pie-chart me-2"></i>Grade Distribution</h6>
            <canvas id="gradeDistributionChart" height="200"></canvas>
        </div>
    </div>
</div>

<!-- =======================================================
 DATA TABLE
======================================================= -->
<div class="academic-card p-3">
    <div class="table-responsive">
        <table class="table table-hover table-bordered table-academic" id="resultsTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Subject</th>
                    <th>Teacher</th>
                    <th>Mean Score</th>
                    <th>Highest</th>
                    <th>Lowest</th>
                    <th>Pass Rate</th>
                    <th>Grade Distribution</th>
                </tr>
            </thead>
            <tbody id="resultsTableBody">
                <!-- Dynamic content -->
            </tbody>
        </table>
    </div>
    <div class="d-flex justify-content-between align-items-center mt-3">
        <small class="text-muted">
            Showing <span id="showingFrom">0</span> to <span id="showingTo">0</span> of <span id="totalRecords">0</span> subjects
        </small>
        <nav>
            <ul class="pagination pagination-sm mb-0" id="pagination"></ul>
        </nav>
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
<script src="/Kingsway/js/pages/results_analysis.js?v=<?php echo time(); ?>"></script>
