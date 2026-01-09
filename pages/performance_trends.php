<?php
/**
 * Performance Trends Page
 * 
 * Purpose: Analyze academic performance trends
 * Features:
 * - Subject-wise analysis
 * - Class comparisons
 * - Year-over-year tracking
 */
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1"><i class="fas fa-chart-area me-2"></i>Performance Trends</h4>
                    <p class="text-muted mb-0">Track and analyze academic performance patterns</p>
                </div>
                <button class="btn btn-outline-primary" id="exportAnalysis">
                    <i class="fas fa-file-pdf me-1"></i> Export Analysis
                </button>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Academic Year</label>
                    <select class="form-select" id="academicYear">
                        <option value="">Select Year</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Class</label>
                    <select class="form-select" id="classFilter">
                        <option value="">All Classes</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Subject</label>
                    <select class="form-select" id="subjectFilter">
                        <option value="">All Subjects</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Analysis Type</label>
                    <select class="form-select" id="analysisType">
                        <option value="mean">Mean Score</option>
                        <option value="pass_rate">Pass Rate</option>
                        <option value="grade_distribution">Grade Distribution</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Term-wise Performance</h5>
                </div>
                <div class="card-body">
                    <canvas id="termPerformanceChart" height="250"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Subject Comparison</h5>
                </div>
                <div class="card-body">
                    <canvas id="subjectComparisonChart" height="250"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Top/Bottom Performers -->
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-arrow-up me-2"></i>Top Improving</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush" id="topImproving"></ul>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Need Attention</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush" id="needAttention"></ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="js/pages/performance_trends.js"></script>