<?php
/**
 * Student Promotion Page – Production UI
 * All logic handled in: js/pages/student_promotion.js
 * UI Theme: Green / White (Academic Professional)
 */
?>

<style>
/* =========================================================
   DESIGN TOKENS
========================================================= */
:root {
    --acad-primary: #198754;
    --acad-primary-dark: #146c43;
    --acad-primary-soft: #d1e7dd;
    --acad-bg-light: #f8f9fa;
    --acad-white: #ffffff;
    --acad-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}

.academic-header {
    background: linear-gradient(135deg, var(--acad-primary), var(--acad-primary-dark));
    color: #fff;
    border-radius: 12px;
    padding: 1.75rem 2rem;
    margin-bottom: 2rem;
    box-shadow: var(--acad-shadow);
}

.academic-card {
    background: var(--acad-white);
    border-radius: 12px;
    border-left: 4px solid var(--acad-primary);
    box-shadow: var(--acad-shadow);
    margin-bottom: 1.75rem;
}

.stat-card {
    background: var(--acad-primary-soft);
    border-radius: 10px;
    padding: 1.2rem;
    height: 100%;
}

.stat-number {
    font-size: 1.9rem;
    font-weight: 700;
    color: var(--acad-primary-dark);
}

.btn-academic {
    background: var(--acad-primary);
    color: #fff;
    border: none;
}

.btn-academic:hover {
    background: var(--acad-primary-dark);
    color: #fff;
}

.table-academic thead {
    background: var(--acad-primary);
    color: #fff;
}
</style>

<!-- =======================================================
 HEADER
======================================================= -->
<div class="academic-header d-flex justify-content-between align-items-center">
    <div>
        <h2 class="mb-1">
            <i class="bi bi-arrow-up-circle me-2"></i>Student Promotion
        </h2>
        <small class="opacity-75">
            Promote or retain students for the new academic year
        </small>
    </div>
</div>

<!-- =======================================================
 INFO ALERT
======================================================= -->
<div class="alert alert-info border-0 mb-4" style="background: var(--acad-primary-soft); color: var(--acad-primary-dark);">
    <i class="bi bi-info-circle me-2"></i>
    <strong>Note:</strong> Ensure the new academic year is created before processing promotions.
</div>

<!-- =======================================================
 STATISTICS
======================================================= -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-number" id="spStudentCount">0</div>
            <small>Students Loaded</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-number" id="spPromoteCount">0</div>
            <small>Selected to Promote</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-number" id="spRetainCount">0</div>
            <small>Marked for Retention</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-number" id="spCurrentClass">--</div>
            <small>Current Class</small>
        </div>
    </div>
</div>

<!-- =======================================================
 PROMOTION SETTINGS
======================================================= -->
<div class="academic-card p-3 mb-4">
    <h5 class="mb-3"><i class="bi bi-sliders me-2"></i>Promotion Settings</h5>
    <div class="row g-3">
        <div class="col-md-3">
            <label class="form-label fw-semibold">From Academic Year</label>
            <select class="form-select" id="fromYear" required>
                <option value="">Select Year</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold">To Academic Year</label>
            <select class="form-select" id="toYear" required>
                <option value="">Select Year</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold">Select Class</label>
            <select class="form-select" id="selectClass" required>
                <option value="">Select Class</option>
            </select>
        </div>
        <div class="col-md-3 d-flex align-items-end">
            <button class="btn btn-academic w-100" id="loadStudents">
                <i class="bi bi-people me-1"></i>Load Students
            </button>
        </div>
    </div>
</div>

<!-- =======================================================
 STUDENTS LIST
======================================================= -->
<div class="academic-card p-3" id="studentsCard" style="display: none;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><i class="bi bi-people-fill me-2"></i>Students for Promotion</h5>
        <div class="btn-group">
            <button class="btn btn-sm btn-academic" id="promoteAll">
                <i class="bi bi-check2-all me-1"></i>Promote All
            </button>
            <button class="btn btn-sm btn-outline-warning" id="retainSelected">
                <i class="bi bi-arrow-repeat me-1"></i>Retain Selected
            </button>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-academic" id="studentsTable">
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
        <button class="btn btn-academic btn-lg" id="processPromotion">
            <i class="bi bi-check-circle me-1"></i>Process Promotion
        </button>
    </div>
</div>

<!-- =======================================================
 TOAST NOTIFICATIONS
======================================================= -->
<div class="position-fixed top-0 end-0 p-3" style="z-index: 11000;">
    <div id="academicToast" class="toast">
        <div class="toast-header">
            <strong id="toastTitle" class="me-auto">Notice</strong>
            <button class="btn-close" data-bs-dismiss="toast"></button>
        </div>
        <div class="toast-body" id="toastBody"></div>
    </div>
</div>

<!-- =======================================================
 SCRIPTS
======================================================= -->
<script src="<?= $appBase ?>/js/pages/student_promotion.js"></script>
