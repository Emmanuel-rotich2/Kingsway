<?php
/**
 * Subject Class Comparison - Subject Teacher Class Comparison
 * Role: Subject Teacher (8)
 * Purpose: Compare subject performance across different classes
 * Shows only classes where the teacher teaches the subject
 */
?>
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-bar-chart me-2"></i>Subject Class Comparison
                    </h5>
                    <small class="text-muted">Compare subject performance across your classes</small>
                </div>
                <div class="card-body">
                    <div id="comparisonLoading" class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted">Loading comparison data...</p>
                    </div>
                    <div id="comparisonContent" style="display: none;">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Academic Year</label>
                                <select id="yearFilter" class="form-select" data-permission="academic_view">
                                    <option value="">All Years</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Term</label>
                                <select id="termFilter" class="form-select" data-permission="academic_view">
                                    <option value="">All Terms</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Subject</label>
                                <select id="subjectFilter" class="form-select" data-permission="academic_view">
                                    <option value="">All Subjects</option>
                                </select>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="d-flex gap-2">
                                <button id="loadComparisonBtn" class="btn btn-primary" data-permission="results_view">
                                    <i class="bi bi-search me-1"></i>Load Comparison
                                </button>
                                <button id="exportBtn" class="btn btn-outline-success" data-permission="results_export">
                                    <i class="bi bi-download me-1"></i>Export
                                </button>
                                <button id="printBtn" class="btn btn-outline-secondary" data-permission="results_export">
                                    <i class="bi bi-printer me-1"></i>Print
                                </button>
                            </div>
                            <div id="statsContainer" class="d-flex gap-3">
                                <span class="badge bg-primary">Classes: <span id="totalClasses">0</span></span>
                                <span class="badge bg-success">Best Class: <span id="bestClass">—</span></span>
                                <span class="badge bg-info">Overall Average: <span id="overallAverage">0%</span></span>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover" id="comparisonTable">
                                <thead>
                                    <tr>
                                        <th>Class</th>
                                        <th>Subject</th>
                                        <th>Students</th>
                                        <th>Average Score</th>
                                        <th>Highest Score</th>
                                        <th>Lowest Score</th>
                                        <th>Pass Rate</th>
                                        <th>Grade Distribution</th>
                                    </tr>
                                </thead>
                                <tbody id="comparisonTableBody">
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">
                                            Select filters and click Load Comparison
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="js/pages/subject_class_comparison.js"></script>
