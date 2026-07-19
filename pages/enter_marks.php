<?php
/**
 * Enter Marks - Class Teacher Marks Entry
 * Role: Class Teacher (7)
 * Purpose: Enter marks for assessments for their assigned classes
 * Shows only students in the teacher's assigned classes
 */
?>
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-pencil-square me-2"></i>Enter Assessment Marks
                    </h5>
                    <small class="text-muted">Enter marks for your class assessments</small>
                </div>
                <div class="card-body">
                    <div id="marksLoading" class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted">Loading assessment data...</p>
                    </div>
                    <div id="marksContent" style="display: none;">
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label class="form-label">Assessment</label>
                                <select id="assessmentFilter" class="form-select" data-permission="assessments_view">
                                    <option value="">Select Assessment</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Class</label>
                                <select id="classFilter" class="form-select" data-permission="academic_view">
                                    <option value="">All Classes</option>
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
                        </div>
                        
                        <div id="assessmentInfo" class="alert alert-info mb-3" style="display: none;">
                            <strong>Assessment:</strong> <span id="assessmentName"></span><br>
                            <strong>Type:</strong> <span id="assessmentType"></span><br>
                            <strong>Max Marks:</strong> <span id="assessmentMaxMarks"></span><br>
                            <strong>Date:</strong> <span id="assessmentDate"></span>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="d-flex gap-2">
                                <button id="saveAllBtn" class="btn btn-primary" data-permission="assessments_edit">
                                    <i class="bi bi-save me-1"></i>Save All Marks
                                </button>
                                <button id="autoCalculateBtn" class="btn btn-outline-secondary">
                                    <i class="bi bi-calculator me-1"></i>Auto Calculate
                                </button>
                                <button id="exportBtn" class="btn btn-outline-success" data-permission="assessments_export">
                                    <i class="bi bi-download me-1"></i>Export
                                </button>
                            </div>
                            <div id="statsContainer" class="d-flex gap-3">
                                <span class="badge bg-primary">Students: <span id="totalStudents">0</span></span>
                                <span class="badge bg-success">Marked: <span id="markedStudents">0</span></span>
                                <span class="badge bg-warning">Pending: <span id="pendingStudents">0</span></span>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered" id="marksTable">
                                <thead>
                                    <tr>
                                        <th style="width: 50px;">#</th>
                                        <th>Adm No</th>
                                        <th>Student Name</th>
                                        <th style="width: 120px;">Marks</th>
                                        <th style="width: 120px;">Grade</th>
                                        <th style="width: 100px;">Remarks</th>
                                        <th style="width: 150px;">Last Updated</th>
                                    </tr>
                                </thead>
                                <tbody id="marksTableBody">
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">
                                            Select an assessment to enter marks
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

<script src="js/pages/enter_marks.js"></script>
