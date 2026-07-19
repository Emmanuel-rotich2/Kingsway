<?php
/**
 * Generate Subject Report - Subject Teacher Subject Report Generation
 * Role: Subject Teacher (8)
 * Purpose: Generate various subject reports for their assigned subjects
 * Shows only subjects the teacher is assigned to
 */
?>
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-file-earmark-text me-2"></i>Generate Subject Report
                    </h5>
                    <small class="text-muted">Generate various reports for your assigned subjects</small>
                </div>
                <div class="card-body">
                    <div id="reportLoading" class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted">Loading subject data...</p>
                    </div>
                    <div id="reportContent" style="display: none;">
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label class="form-label">Report Type</label>
                                <select id="reportType" class="form-select" data-permission="reports_view">
                                    <option value="">Select Report Type</option>
                                    <option value="performance">Subject Performance Report</option>
                                    <option value="assessment">Assessment Summary</option>
                                    <option value="progress">Student Progress Report</option>
                                    <option value="comparison">Class Comparison Report</option>
                                </select>
                            </div>
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
                                <label class="form-label">Subject</label>
                                <select id="subjectFilter" class="form-select" data-permission="academic_view">
                                    <option value="">All Subjects</option>
                                </select>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="d-flex gap-2">
                                <button id="generateBtn" class="btn btn-primary" data-permission="reports_generate">
                                    <i class="bi bi-file-earmark-plus me-1"></i>Generate Report
                                </button>
                                <button id="refreshBtn" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                                </button>
                            </div>
                            <div id="statsContainer" class="d-flex gap-3">
                                <span class="badge bg-primary">Students: <span id="totalStudents">0</span></span>
                                <span class="badge bg-success">Reports Generated: <span id="generatedCount">0</span></span>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover" id="reportsTable">
                                <thead>
                                    <tr>
                                        <th>Report Type</th>
                                        <th>Subject</th>
                                        <th>Year</th>
                                        <th>Term</th>
                                        <th>Generated Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="reportsTableBody">
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">
                                            Select report type and click Generate
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

<script src="js/pages/generate_subject_report.js"></script>
