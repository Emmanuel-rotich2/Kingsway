<?php
/**
 * Class Report Cards - Class Teacher Specific Report Card Generation
 * Role: Class Teacher (7)
 * Purpose: Generate report cards for their assigned classes
 * Shows only classes the teacher is assigned to
 */
?>
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-file-earmark-text me-2"></i>Class Report Cards
                    </h5>
                    <small class="text-muted">Generate report cards for your assigned classes</small>
                </div>
                <div class="card-body">
                    <div id="reportCardsLoading" class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted">Loading class data...</p>
                    </div>
                    <div id="reportCardsContent" style="display: none;">
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
                                <label class="form-label">Student</label>
                                <select id="studentFilter" class="form-select" data-permission="academic_view">
                                    <option value="">All Students</option>
                                </select>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="d-flex gap-2">
                                <button id="loadStudentsBtn" class="btn btn-primary" data-permission="report_cards_view">
                                    <i class="bi bi-search me-1"></i>Load Students
                                </button>
                                <button id="generateSingleBtn" class="btn btn-success" data-permission="report_cards_generate">
                                    <i class="bi bi-file-earmark-plus me-1"></i>Generate Report Card
                                </button>
                                <button id="generateBatchBtn" class="btn btn-outline-success" data-permission="report_cards_generate">
                                    <i class="bi bi-files me-1"></i>Generate All
                                </button>
                            </div>
                            <div id="statsContainer" class="d-flex gap-3">
                                <span class="badge bg-primary">Students: <span id="totalStudents">0</span></span>
                                <span class="badge bg-info">Generated: <span id="generatedCount">0</span></span>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover" id="studentsTable">
                                <thead>
                                    <tr>
                                        <th style="width: 50px;">
                                            <input type="checkbox" id="selectAllStudents">
                                        </th>
                                        <th>Student</th>
                                        <th>Adm No</th>
                                        <th>Class</th>
                                        <th>Term Average</th>
                                        <th>Overall Grade</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="studentsTableBody">
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">
                                            Select filters and click Load Students
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

<script src="js/pages/class_report_cards.js"></script>
