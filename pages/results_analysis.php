<?php
/**
 * Results Analysis Page
 * Purpose: Analyze exam results by subject, class, and term with charts
 * Features: Subject mean analysis, pass rate tracking, grade distribution, bar charts
 */
?>

<div>
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1"><i class="bi bi-bar-chart-line"></i> Results Analysis</h4>
            <small class="text-muted">Analyze exam results, subject means, and grade distributions</small>
        </div>
        <div class="btn-group">
            <button class="btn btn-outline-primary btn-sm" id="exportResultsBtn">
                <i class="bi bi-download"></i> Export Report
            </button>
            <button class="btn btn-outline-info btn-sm" id="printResultsBtn">
                <i class="bi bi-printer"></i> Print
            </button>
        </div>
    </div>

    <!-- KPI Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-primary">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Overall Mean</h6>
                    <h3 class="text-primary mb-0" id="overallMean">0%</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-success">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Highest Subject</h6>
                    <h3 class="text-success mb-0" id="highestSubject">-</h3>
                    <small class="text-muted" id="highestSubjectScore"></small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-danger">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Lowest Subject</h6>
                    <h3 class="text-danger mb-0" id="lowestSubject">-</h3>
                    <small class="text-muted" id="lowestSubjectScore"></small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-info">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Students Assessed</h6>
                    <h3 class="text-info mb-0" id="studentsAssessed">0</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter / Search -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Term</label>
                    <select class="form-select" id="termFilterResults">
                        <option value="">All Terms</option>
                        <option value="1">Term 1</option>
                        <option value="2">Term 2</option>
                        <option value="3">Term 3</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Class</label>
                    <select class="form-select" id="classFilterResults">
                        <option value="">All Classes</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Subject</label>
                    <select class="form-select" id="subjectFilterResults">
                        <option value="">All Subjects</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Academic Year</label>
                    <select class="form-select" id="yearFilterResults">
                        <option value="">Current Year</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart Row -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-light">
                    <h6 class="mb-0">Subject Mean Scores</h6>
                </div>
                <div class="card-body">
                    <canvas id="subjectMeansChart" height="200"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-light">
                    <h6 class="mb-0">Grade Distribution</h6>
                </div>
                <div class="card-body">
                    <canvas id="gradeDistributionChart" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Data Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-bordered" id="resultsTable">
                    <thead class="table-light">
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
                    Showing <span id="showingFrom">0</span> to <span id="showingTo">0</span> of <span
                        id="totalRecords">0</span> subjects
                </small>
                <nav>
                    <ul class="pagination pagination-sm mb-0" id="pagination"></ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<script src="/Kingsway/js/pages/results_analysis.js?v=<?php echo time(); ?>"></script>