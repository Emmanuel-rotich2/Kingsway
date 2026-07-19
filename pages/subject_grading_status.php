<?php
/**
 * Subject Grading Status - Subject Teacher Grading Status Viewing
 * Role: Subject Teacher (8)
 * Purpose: View grading status for their assigned subjects
 * Shows only grading status for the teacher's assigned subjects
 */
?>
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-bar-chart me-2"></i>Subject Grading Status
                    </h5>
                    <small class="text-muted">Grading status for your assigned subjects</small>
                </div>
                <div class="card-body">
                    <div id="statusLoading" class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted">Loading grading status...</p>
                    </div>
                    <div id="statusContent" style="display: none;">
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
                                <button id="refreshBtn" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                                </button>
                                <button id="exportBtn" class="btn btn-outline-success" data-permission="assessments_export">
                                    <i class="bi bi-download me-1"></i>Export
                                </button>
                            </div>
                            <div id="statsContainer" class="d-flex gap-3">
                                <span class="badge bg-primary">Total Assessments: <span id="totalAssessments">0</span></span>
                                <span class="badge bg-success">Graded: <span id="gradedAssessments">0</span></span>
                                <span class="badge bg-warning">Pending: <span id="pendingAssessments">0</span></span>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover" id="statusTable">
                                <thead>
                                    <tr>
                                        <th>Assessment</th>
                                        <th>Subject</th>
                                        <th>Class</th>
                                        <th>Type</th>
                                        <th>Date</th>
                                        <th>Total Students</th>
                                        <th>Graded</th>
                                        <th>Pending</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody id="statusTableBody">
                                    <tr>
                                        <td colspan="9" class="text-center text-muted py-4">
                                            No grading status found for your subjects
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

<script src="js/pages/subject_grading_status.js"></script>
