<?php
/**
 * Performance Reports Page
 * HTML structure only - logic will be in js/pages/performance_reports.js
 * Embedded in app_layout.php
 */
?>

<div class="card shadow-sm">
    <div class="card-header bg-gradient bg-primary text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="fas fa-chart-line"></i> Academic Performance Reports</h4>
            <div class="btn-group">
                <button class="btn btn-light btn-sm" id="exportReportBtn">
                    <i class="bi bi-file-excel"></i> Export
                </button>
                <button class="btn btn-outline-light btn-sm" id="printBtn">
                    <i class="bi bi-printer"></i> Print
                </button>
            </div>
        </div>
    </div>

    <div class="card-body">
        <!-- Report Configuration -->
        <div class="row mb-4">
            <div class="col-md-3">
                <label class="form-label">Report Type</label>
                <select class="form-select" id="reportType">
                    <option value="class_performance">Class Performance Summary</option>
                    <option value="subject_analysis">Subject Analysis</option>
                    <option value="top_performers">Top Performers</option>
                    <option value="improvement_tracking">Improvement Tracking</option>
                    <option value="grade_distribution">Grade Distribution</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Exam/Term</label>
                <select class="form-select" id="examTerm"></select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Class</label>
                <select class="form-select" id="classFilter"></select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Subject</label>
                <select class="form-select" id="subjectFilter">
                    <option value="">All Subjects</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">&nbsp;</label>
                <button class="btn btn-primary w-100" id="generateBtn">Generate Report</button>
            </div>
        </div>

        <!-- Performance Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Class Average</h6>
                        <h3 class="text-success mb-0" id="classAverage">0%</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-primary">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Pass Rate</h6>
                        <h3 class="text-primary mb-0" id="passRate">0%</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-info">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Top Score</h6>
                        <h3 class="text-info mb-0" id="topScore">0%</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-warning">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Students Analyzed</h6>
                        <h3 class="text-warning mb-0" id="studentsCount">0</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Performance by Subject</h5>
                        <canvas id="subjectPerformanceChart" height="80"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Grade Distribution</h5>
                        <canvas id="gradeDistributionChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Performance Table -->
        <div class="card">
            <div class="card-body">
                <h5 class="card-title" id="tableTitle">Performance Report</h5>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="performanceTable">
                        <thead class="table-light">
                            <tr id="tableHeaders">
                                <!-- Dynamic headers -->
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <!-- Dynamic content -->
                        </tbody>
                        <tfoot class="table-light fw-bold" id="tableFooter">
                            <!-- Dynamic totals/averages -->
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- Subject Analysis Section (conditional) -->
        <div class="card mt-4" id="subjectAnalysisCard" style="display: none;">
            <div class="card-body">
                <h5 class="card-title">Subject-wise Analysis</h5>
                <div class="row">
                    <div class="col-md-6">
                        <canvas id="strengthsWeaknessChart"></canvas>
                    </div>
                    <div class="col-md-6">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Subject</th>
                                        <th>Mean</th>
                                        <th>Highest</th>
                                        <th>Lowest</th>
                                    </tr>
                                </thead>
                                <tbody id="subjectStatsBody">
                                    <!-- Dynamic content -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // TODO: Implement performanceReportsController in js/pages/performance_reports.js
        console.log('Performance Reports page loaded');
    });
</script>