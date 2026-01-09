<?php
/**
 * Term Reports Page
 * 
 * Purpose: Generate and manage term report cards
 * Features:
 * - Report card generation
 * - Bulk printing
 * - Parent access
 */
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1"><i class="fas fa-file-alt me-2"></i>Term Reports</h4>
                    <p class="text-muted mb-0">Generate and manage student term report cards</p>
                </div>
                <div class="btn-group">
                    <button class="btn btn-primary" id="generateReports">
                        <i class="fas fa-magic me-1"></i> Generate Reports
                    </button>
                    <button class="btn btn-outline-primary" id="bulkPrint">
                        <i class="fas fa-print me-1"></i> Bulk Print
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Selection -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Academic Year</label>
                    <select class="form-select" id="academicYear">
                        <option value="">Select Year</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Term</label>
                    <select class="form-select" id="term">
                        <option value="">Select Term</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Class</label>
                    <select class="form-select" id="classFilter">
                        <option value="">All Classes</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-primary w-100" id="loadReports">
                        <i class="fas fa-search me-1"></i> Load Reports
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h3 id="totalStudents">--</h3>
                    <p class="text-muted mb-0">Total Students</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h3 id="reportsGenerated">--</h3>
                    <p class="text-muted mb-0">Reports Generated</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h3 id="reportsPrinted">--</h3>
                    <p class="text-muted mb-0">Printed</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h3 id="pendingRemarks">--</h3>
                    <p class="text-muted mb-0">Pending Remarks</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Reports Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="reportsTable">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAll"></th>
                            <th>Student</th>
                            <th>Class</th>
                            <th>Average</th>
                            <th>Position</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="js/pages/term_reports.js"></script>