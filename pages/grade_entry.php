<?php
/**
 * Grade Entry - School Admin Exam Grade Entry
 * Role: School Administrator (4)
 * Purpose: Enter exam grades for all students
 * Full access to all classes and subjects for exam grade entry
 */
?>
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-pencil-square me-2"></i>Exam Grade Entry
                    </h5>
                    <small class="text-muted">Enter exam grades for all students</small>
                </div>
                <div class="card-body">
                    <div id="gradesLoading" class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted">Loading exam data...</p>
                    </div>
                    <div id="gradesContent" style="display: none;">
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label class="form-label">Exam</label>
                                <select id="examFilter" class="form-select" data-permission="assessments_view">
                                    <option value="">Select Exam</option>
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
                            <div class="col-md-3">
                                <label class="form-label">Academic Year</label>
                                <select id="yearFilter" class="form-select" data-permission="academic_view">
                                    <option value="">All Years</option>
                                </select>
                            </div>
                        </div>
                        
                        <div id="examInfo" class="alert alert-info mb-3" style="display: none;">
                            <strong>Exam:</strong> <span id="examName"></span><br>
                            <strong>Subject:</strong> <span id="examSubject"></span><br>
                            <strong>Type:</strong> <span id="examType"></span><br>
                            <strong>Max Marks:</strong> <span id="examMaxMarks"></span><br>
                            <strong>Date:</strong> <span id="examDate"></span>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="d-flex gap-2">
                                <button id="saveAllBtn" class="btn btn-primary" data-permission="assessments_edit">
                                    <i class="bi bi-save me-1"></i>Save All Grades
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
                                <span class="badge bg-success">Graded: <span id="gradedStudents">0</span></span>
                                <span class="badge bg-warning">Pending: <span id="pendingStudents">0</span></span>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered" id="gradesTable">
                                <thead>
                                    <tr>
                                        <th style="width: 50px;">#</th>
                                        <th>Adm No</th>
                                        <th>Student Name</th>
                                        <th>Class</th>
                                        <th style="width: 120px;">Marks</th>
                                        <th style="width: 120px;">Grade</th>
                                        <th style="width: 100px;">Remarks</th>
                                        <th style="width: 150px;">Last Updated</th>
                                    </tr>
                                </thead>
                                <tbody id="gradesTableBody">
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">
                                            Select an exam to enter grades
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

<script src="js/pages/grade_entry.js"></script>
