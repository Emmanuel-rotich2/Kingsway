<?php
/**
 * Enrollment Trends Page
 * 
 * Purpose: Analyze student enrollment trends
 * Features:
 * - Historical enrollment data
 * - Trend analysis and projections
 * - Demographic breakdown
 */
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1"><i class="fas fa-chart-line me-2"></i>Enrollment Trends</h4>
                    <p class="text-muted mb-0">Analyze student enrollment patterns and projections</p>
                </div>
                <button class="btn btn-outline-primary" id="exportReport">
                    <i class="fas fa-download me-1"></i> Export Report
                </button>
            </div>
        </div>
    </div>

    <!-- Year Selection -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Compare Years</label>
                    <select class="form-select" id="yearRange" multiple>
                        <!-- Populated dynamically -->
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Group By</label>
                    <select class="form-select" id="groupBy">
                        <option value="class">Class/Grade</option>
                        <option value="gender">Gender</option>
                        <option value="boarding">Boarding Status</option>
                        <option value="stream">Stream</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <button class="btn btn-primary w-100" id="analyzeBtn">
                        <i class="fas fa-chart-bar me-1"></i> Analyze
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">Enrollment Over Time</h5>
                </div>
                <div class="card-body">
                    <canvas id="enrollmentTrendChart" height="300"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">Current Distribution</h5>
                </div>
                <div class="card-body">
                    <canvas id="distributionChart" height="300"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Enrollment by Class</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table" id="enrollmentTable">
                    <thead>
                        <tr>
                            <th>Class</th>
                            <th>Current Year</th>
                            <th>Previous Year</th>
                            <th>Change</th>
                            <th>% Change</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="js/pages/enrollment_trends.js"></script>