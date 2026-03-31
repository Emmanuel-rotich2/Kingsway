<?php
/**
 * Lesson Plans by Teacher Page
 * Purpose: View lesson plan submission status broken down by teacher
 * Features: Teacher submission tracking, department filtering, coverage metrics
 */
?>

<div>
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1"><i class="bi bi-person-workspace"></i> Lesson Plans by Teacher</h4>
            <small class="text-muted">Track lesson plan submissions and compliance per teacher</small>
        </div>
        <div class="btn-group">
            <button class="btn btn-outline-primary btn-sm" id="exportByTeacherBtn">
                <i class="bi bi-download"></i> Export Report
            </button>
        </div>
    </div>

    <!-- KPI Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-primary">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Teachers Total</h6>
                    <h3 class="text-primary mb-0" id="teachersTotal">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-success">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Fully Submitted</h6>
                    <h3 class="text-success mb-0" id="fullySubmitted">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-warning">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Partially Submitted</h6>
                    <h3 class="text-warning mb-0" id="partiallySubmitted">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-danger">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Not Submitted</h6>
                    <h3 class="text-danger mb-0" id="notSubmitted">0</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter / Search -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <select class="form-select" id="departmentFilterLPT">
                        <option value="">All Departments</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <select class="form-select" id="submissionStatusFilter">
                        <option value="">All Status</option>
                        <option value="fully_submitted">Fully Submitted</option>
                        <option value="partially_submitted">Partially Submitted</option>
                        <option value="not_submitted">Not Submitted</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <input type="text" class="form-control" id="searchByTeacher" placeholder="Search teacher...">
                </div>
            </div>
        </div>
    </div>

    <!-- Main Data Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-bordered" id="byTeacherTable">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Teacher Name</th>
                            <th>Department</th>
                            <th>Plans Expected</th>
                            <th>Submitted</th>
                            <th>Approved</th>
                            <th>Pending</th>
                            <th>Coverage %</th>
                        </tr>
                    </thead>
                    <tbody id="byTeacherTableBody">
                        <!-- Dynamic content -->
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-3">
                <small class="text-muted">
                    Showing <span id="showingFrom">0</span> to <span id="showingTo">0</span> of <span
                        id="totalRecords">0</span> teachers
                </small>
                <nav>
                    <ul class="pagination pagination-sm mb-0" id="pagination"></ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<script src="<?= $appBase ?>js/pages/lesson_plans_by_teacher.js?v=<?php echo time(); ?>"></script>