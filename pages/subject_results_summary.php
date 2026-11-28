<?php
/**
 * Subject Results Summary — summative exam results for the classes and
 * learning areas the teacher teaches, per term of each academic year
 * (history included). The server limits academic/results to the teacher's
 * own assignments. Filters load on change; table is CSV/PDF-ready.
 */
?>
<style>
@media print {
    @page { size: A4 landscape; margin: 10mm; }
    #subjectResultsPage .d-print-none { display: none !important; }
    #subjectResultsPage .table-responsive { overflow: visible; }
}
</style>
<div class="container-fluid py-4" id="subjectResultsPage">
    <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h5 class="card-title mb-0"><i class="bi bi-graph-up me-2"></i>Subject Results Summary</h5>
                <small class="text-muted">Summative exam results for the classes and learning areas you teach</small>
            </div>
            <div class="d-flex gap-2 d-print-none">
                <button id="exportBtn" class="btn btn-outline-success btn-sm" type="button"><i class="bi bi-download me-1"></i>CSV</button>
                <button id="printBtn" class="btn btn-outline-secondary btn-sm" type="button"><i class="bi bi-printer me-1"></i>Print / PDF</button>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-2 mb-3 d-print-none">
                <div class="col-md-2">
                    <label class="form-label small text-muted" for="yearFilter">Academic year</label>
                    <select id="yearFilter" class="form-select"></select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted" for="termFilter">Term</label>
                    <select id="termFilter" class="form-select"></select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted" for="scopeFilter">Class &amp; learning area</label>
                    <select id="scopeFilter" class="form-select"><option value="">All my classes and learning areas</option></select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted" for="viewFilter">Show</label>
                    <select id="viewFilter" class="form-select">
                        <option value="summary">Learner averages</option>
                        <option value="detail">Every exam result</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted" for="searchFilter">Search</label>
                    <input id="searchFilter" type="search" class="form-control" placeholder="Learner name or admission number">
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2 mb-3" id="statsContainer">
                <span class="badge bg-primary">Learners: <span id="totalStudents">0</span></span>
                <span class="badge bg-secondary">Exams: <span id="totalExams">0</span></span>
                <span class="badge bg-success">Average: <span id="averageScore">—</span></span>
                <span class="badge bg-info text-dark">At or above average: <span id="aboveAverage">0</span></span>
                <span class="badge bg-warning text-dark">Below expectation: <span id="belowExpectation">0</span></span>
            </div>
            <div id="levelDistribution" class="mb-3"></div>
            <div class="table-responsive">
                <table class="table table-hover align-middle" id="resultsTable">
                    <thead class="table-light"><tr id="resultsHead"></tr></thead>
                    <tbody id="resultsTableBody"><tr><td class="text-center text-muted py-4">Loading results…</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php asset_script($appBase, 'js/pages/subject_results_summary.js'); ?>
