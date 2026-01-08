<?php
/**
 * Academic Reports Page
 * 
 * Purpose: Generate and view academic reports
 * Features:
 * - Class performance reports
 * - Subject analysis
 * - Student progress tracking
 */
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1"><i class="fas fa-chart-bar me-2"></i>Academic Reports</h4>
                    <p class="text-muted mb-0">Generate and analyze academic performance reports</p>
                </div>
                <button class="btn btn-primary" id="generateReport">
                    <i class="fas fa-file-pdf me-1"></i> Generate Report
                </button>
            </div>
        </div>
    </div>

    <!-- Report Selection -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Academic Year</label>
                    <select class="form-select" id="selectYear">
                        <option value="">Select Year</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Term</label>
                    <select class="form-select" id="selectTerm">
                        <option value="">Select Term</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Report Type</label>
                    <select class="form-select" id="reportType">
                        <option value="">Select Type</option>
                        <option value="class">Class Performance</option>
                        <option value="subject">Subject Analysis</option>
                        <option value="student">Student Progress</option>
                        <option value="comparison">Term Comparison</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Class (Optional)</label>
                    <select class="form-select" id="selectClass">
                        <option value="">All Classes</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Report Content Tabs -->
    <div class="card">
        <div class="card-header">
            <ul class="nav nav-tabs card-header-tabs">
                <li class="nav-item">
                    <a class="nav-link active" data-bs-toggle="tab" href="#overviewTab">Overview</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#detailedTab">Detailed Analysis</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#trendsTab">Trends</a>
                </li>
            </ul>
        </div>
        <div class="card-body">
            <div class="tab-content">
                <div class="tab-pane fade show active" id="overviewTab">
                    <div class="row">
                        <div class="col-md-8">
                            <canvas id="performanceChart" height="300"></canvas>
                        </div>
                        <div class="col-md-4">
                            <h6>Quick Stats</h6>
                            <ul class="list-group" id="quickStats">
                                <li class="list-group-item d-flex justify-content-between">
                                    <span>Average Score</span>
                                    <strong id="avgScore">--</strong>
                                </li>
                                <li class="list-group-item d-flex justify-content-between">
                                    <span>Pass Rate</span>
                                    <strong id="passRate">--</strong>
                                </li>
                                <li class="list-group-item d-flex justify-content-between">
                                    <span>Top Performers</span>
                                    <strong id="topPerformers">--</strong>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="tab-pane fade" id="detailedTab">
                    <div class="table-responsive">
                        <table class="table" id="detailedTable">
                            <thead>
                                <tr>
                                    <th>Class/Subject</th>
                                    <th>Students</th>
                                    <th>Average</th>
                                    <th>Pass Rate</th>
                                    <th>Trend</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
                <div class="tab-pane fade" id="trendsTab">
                    <canvas id="trendsChart" height="300"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="js/pages/academic_reports.js"></script>