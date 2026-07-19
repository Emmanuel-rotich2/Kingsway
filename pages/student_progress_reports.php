<?php
/**
 * Student Progress Reports - Class Teacher Progress Reports Viewing
 * Role: Class Teacher (7)
 * Purpose: View progress reports for their assigned classes
 * Shows only progress reports for the teacher's assigned classes
 */
?>
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-graph-up-arrow me-2"></i>Student Progress Reports
                    </h5>
                    <small class="text-muted">Progress reports for your assigned classes</small>
                </div>
                <div class="card-body">
                    <div id="progressLoading" class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted">Loading progress reports...</p>
                    </div>
                    <div id="progressContent" style="display: none;">
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
                                <label class="form-label">Class</label>
                                <select id="classFilter" class="form-select" data-permission="academic_view">
                                    <option value="">All Classes</option>
                                </select>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="d-flex gap-2">
                                <button id="loadReportsBtn" class="btn btn-primary" data-permission="results_view">
                                    <i class="bi bi-search me-1"></i>Load Reports
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
                                <span class="badge bg-success">Improved: <span id="improvedStudents">0</span></span>
                                <span class="badge bg-warning">Declined: <span id="declinedStudents">0</span></span>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover" id="progressTable">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Adm No</th>
                                        <th>Class</th>
                                        <th>Previous Average</th>
                                        <th>Current Average</th>
                                        <th>Change</th>
                                        <th>Trend</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="progressTableBody">
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">
                                            Select filters and click Load Reports
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

<script src="js/pages/student_progress_reports.js"></script>
