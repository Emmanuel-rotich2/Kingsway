<?php
/**
 * Staff Performance Overview Page
 * Purpose: View staff performance ratings, attendance, and task completion metrics
 * Features: Performance distribution chart, department filtering, rating breakdown, export
 */
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1"><i class="bi bi-graph-up"></i> Staff Performance Overview</h4>
            <small class="text-muted">Monitor staff performance ratings, attendance, and productivity</small>
        </div>
        <div class="btn-group">
            <button class="btn btn-outline-primary btn-sm" id="exportPerformanceBtn">
                <i class="bi bi-download"></i> Export Report
            </button>
            <button class="btn btn-outline-info btn-sm" id="printPerformanceBtn">
                <i class="bi bi-printer"></i> Print
            </button>
        </div>
    </div>

    <!-- KPI Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-primary">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Total Staff</h6>
                    <h3 class="text-primary mb-0" id="totalStaff">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-success">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Excellent</h6>
                    <h3 class="text-success mb-0" id="excellentCount">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-info">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Good</h6>
                    <h3 class="text-info mb-0" id="goodCount">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-warning">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Needs Improvement</h6>
                    <h3 class="text-warning mb-0" id="needsImprovementCount">0</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart Row -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-light">
                    <h6 class="mb-0">Performance Distribution</h6>
                </div>
                <div class="card-body">
                    <canvas id="performanceChart" height="200"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-light">
                    <h6 class="mb-0">Rating Breakdown</h6>
                </div>
                <div class="card-body">
                    <canvas id="ratingPieChart" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter / Search Card -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <select class="form-select" id="departmentFilterPerf">
                        <option value="">All Departments</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="ratingFilter">
                        <option value="">All Ratings</option>
                        <option value="excellent">Excellent</option>
                        <option value="good">Good</option>
                        <option value="average">Average</option>
                        <option value="needs_improvement">Needs Improvement</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="periodFilter">
                        <option value="">Current Period</option>
                        <option value="term1">Term 1</option>
                        <option value="term2">Term 2</option>
                        <option value="term3">Term 3</option>
                        <option value="annual">Annual</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="text" class="form-control" id="searchPerformance" placeholder="Search staff...">
                </div>
            </div>
        </div>
    </div>

    <!-- Main Data Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-bordered" id="performanceTable">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Staff Name</th>
                            <th>Department</th>
                            <th>Role</th>
                            <th>Rating</th>
                            <th>Attendance %</th>
                            <th>Tasks Completed</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="performanceTableBody">
                        <!-- Dynamic content -->
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-3">
                <small class="text-muted">
                    Showing <span id="showingFrom">0</span> to <span id="showingTo">0</span> of <span
                        id="totalRecords">0</span> staff
                </small>
                <nav>
                    <ul class="pagination pagination-sm mb-0" id="pagination"></ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<!-- View Staff Performance Detail Modal -->
<div class="modal fade" id="performanceDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="perfDetailLabel">Staff Performance Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="performanceDetailContent">
                <!-- Dynamic detail content -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="/Kingsway/js/pages/staff_performance_overview.js?v=<?php echo time(); ?>"></script>