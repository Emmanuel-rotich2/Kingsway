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
                    <small class="text-muted">How far marking has gone for every assessment in the classes and learning areas you teach</small>
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
                            <div class="col-md-3">
                                <label class="form-label">Academic Year</label>
                                <select id="yearFilter" class="form-select" data-permission="academic_view" aria-label="Academic year"></select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Term</label>
                                <select id="termFilter" class="form-select" data-permission="academic_view" aria-label="Term"></select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="subjectFilter">Class &amp; learning area</label>
                                <select id="subjectFilter" class="form-select">
                                    <option value="">All my classes and learning areas</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="kindFilter">Assessment kind</label>
                                <select id="kindFilter" class="form-select">
                                    <option value="">Formative and summative</option>
                                    <option value="1">Formative (classroom)</option>
                                    <option value="0">Summative (exams)</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="progressFilter">Marking progress</label>
                                <select id="progressFilter" class="form-select">
                                    <option value="">All</option>
                                    <option value="pending">Marks outstanding</option>
                                    <option value="complete">Fully marked</option>
                                </select>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="d-flex gap-2">
                                <button id="refreshBtn" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                                </button>
                                <button id="exportBtn" class="btn btn-outline-success">
                                    <i class="bi bi-download me-1"></i>CSV
                                </button>
                                <button id="printBtn" class="btn btn-outline-secondary">
                                    <i class="bi bi-printer me-1"></i>Print / PDF
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
                                        <th scope="col">Assessment</th>
                                        <th scope="col">Subject</th>
                                        <th scope="col">Class</th>
                                        <th scope="col">Type</th>
                                        <th scope="col">Date</th>
                                        <th scope="col">Total Students</th>
                                        <th scope="col">Graded</th>
                                        <th scope="col">Pending</th>
                                        <th scope="col">Status</th>
                                        <th scope="col" class="d-print-none">Marks</th>
                                    </tr>
                                </thead>
                                <tbody id="statusTableBody">
                                    <tr>
                                        <td colspan="10" class="text-center text-muted py-4">
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

<?php asset_script($appBase, 'js/pages/subject_grading_status.js'); ?>
