<?php
/**
 * Class Results - Class Teacher Specific Results Viewing
 * Role: Class Teacher (7)
 * Purpose: View results for their assigned classes
 * Shows only results for the teacher's assigned classes
 */
?>
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-graph-up me-2"></i>Class Results
                    </h5>
                    <small class="text-muted">Results for your assigned classes</small>
                </div>
                <div class="card-body">
                    <div id="resultsLoading" class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted">Loading class results...</p>
                    </div>
                    <div id="resultsContent" style="display: none;">
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label class="form-label">Academic Year</label>
                                <select id="yearFilter" class="form-select" data-permission="academic_view">
                                    <option value="">All Years</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Term</label>
                                <select id="termFilter" class="form-select" data-permission="academic_view">
                                    <option value="">All Terms</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Class</label>
                                <select id="classFilter" class="form-select" data-permission="academic_view">
                                    <option value="">All Classes</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Subject</label>
                                <select id="subjectFilter" class="form-select" data-permission="academic_view">
                                    <option value="">All Subjects</option>
                                </select>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="d-flex gap-2">
                                <button id="loadResultsBtn" class="btn btn-primary" data-permission="results_view">
                                    <i class="bi bi-search me-1"></i>Load Results
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
                                <span class="badge bg-success">Average: <span id="averageScore">0%</span></span>
                                <span class="badge bg-info">Above Avg: <span id="aboveAverage">0</span></span>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover" id="resultsTable">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Adm No</th>
                                        <th>Class</th>
                                        <th>Subject</th>
                                        <th>Marks</th>
                                        <th>Grade</th>
                                        <th>Remarks</th>
                                    </tr>
                                </thead>
                                <tbody id="resultsTableBody">
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">
                                            Select filters and click Load Results
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

<script src="js/pages/class_results.js"></script>
