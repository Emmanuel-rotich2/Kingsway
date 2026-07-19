<?php
/**
 * Student Subject Performance - Subject Teacher Student Performance Viewing
 * Role: Subject Teacher (8)
 * Purpose: View subject-specific performance analysis for their assigned subjects
 * Shows only students in classes where the teacher teaches the subject
 */
?>
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-graph-up me-2"></i>Student Subject Performance
                    </h5>
                    <small class="text-muted">Subject-specific performance analysis for your students</small>
                </div>
                <div class="card-body">
                    <div id="performanceLoading" class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted">Loading performance data...</p>
                    </div>
                    <div id="performanceContent" style="display: none;">
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
                                <button id="loadPerformanceBtn" class="btn btn-primary" data-permission="results_view">
                                    <i class="bi bi-search me-1"></i>Load Performance
                                </button>
                                <button id="exportBtn" class="btn btn-outline-success" data-permission="results_export">
                                    <i class="bi bi-download me-1"></i>Export
                                </button>
                                <button id="printBtn" class="btn btn-outline-secondary" data-permission="results_export">
                                    <i class="bi bi-printer me-1"></i>Print
                                </button>
                            </div>
                            <div id="statsContainer" class="d-flex gap-3">
                                <span class="badge bg-primary">Students: <span id="totalStudents">0</span></span>
                                <span class="badge bg-success">Above Average: <span id="aboveAverage">0</span></span>
                                <span class="badge bg-info">Subject Average: <span id="subjectAverage">0%</span></span>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover" id="performanceTable">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Adm No</th>
                                        <th>Class</th>
                                        <th>Subject Average</th>
                                        <th>Best Assessment</th>
                                        <th>Needs Improvement</th>
                                        <th>Trend</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="performanceTableBody">
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">
                                            Select filters and click Load Performance
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

<script src="js/pages/student_subject_performance.js"></script>
