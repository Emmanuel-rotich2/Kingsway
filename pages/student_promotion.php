<?php
/**
 * Student Promotion Page
 * 
 * Purpose: Manage student class promotions
 * Features:
 * - Bulk promotion
 * - Repeat/retain students
 * - Promotion history
 */
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1"><i class="fas fa-level-up-alt me-2"></i>Student Promotion</h4>
                    <p class="text-muted mb-0">Promote or retain students for the new academic year</p>
                </div>
            </div>
        </div>
    </div>

    <div class="alert alert-info">
        <i class="fas fa-info-circle me-2"></i>
        <strong>Note:</strong> Ensure the new academic year is created before processing promotions.
    </div>

    <!-- Promotion Form -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Promotion Settings</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">From Academic Year</label>
                    <select class="form-select" id="fromYear" required>
                        <option value="">Select Year</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">To Academic Year</label>
                    <select class="form-select" id="toYear" required>
                        <option value="">Select Year</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Select Class</label>
                    <select class="form-select" id="selectClass" required>
                        <option value="">Select Class</option>
                    </select>
                </div>
            </div>
            <div class="mt-3">
                <button class="btn btn-primary" id="loadStudents">
                    <i class="fas fa-users me-1"></i> Load Students
                </button>
            </div>
        </div>
    </div>

    <!-- Students List -->
    <div class="card" id="studentsCard" style="display: none;">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Students for Promotion</h5>
                <div class="btn-group">
                    <button class="btn btn-sm btn-success" id="promoteAll">
                        <i class="fas fa-check-double me-1"></i> Promote All
                    </button>
                    <button class="btn btn-sm btn-warning" id="retainSelected">
                        <i class="fas fa-undo me-1"></i> Retain Selected
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="studentsTable">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAll"></th>
                            <th>Admission No</th>
                            <th>Student Name</th>
                            <th>Current Class</th>
                            <th>Average Score</th>
                            <th>Promote To</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
            <div class="mt-3">
                <button class="btn btn-primary btn-lg" id="processPromotion">
                    <i class="fas fa-check me-1"></i> Process Promotion
                </button>
            </div>
        </div>
    </div>
</div>

<script src="js/pages/student_promotion.js"></script>