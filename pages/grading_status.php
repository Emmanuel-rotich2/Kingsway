<?php
/**
 * Grading Status Page
 * HTML structure only - logic in js/pages/grading_status.js
 * Embedded in app_layout.php
 *
 * Role-based access:
 * - Headteacher: View all grading progress across subjects and teachers
 * - Subject Teacher: View own grading progress
 * - Admin: Full access
 */
?>

<div>
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1"><i class="fas fa-tasks me-2"></i>Grading Status</h4>
                    <p class="text-muted mb-0">Monitor grading completion progress across all subjects and classes</p>
                </div>
                <div class="btn-group">
                    <button class="btn btn-outline-primary btn-sm" id="refreshBtn">
                        <i class="bi bi-arrow-clockwise me-1"></i> Refresh
                    </button>
                    <button class="btn btn-outline-primary btn-sm" id="exportGradingBtn"
                            data-role="headteacher,admin">
                        <i class="bi bi-download me-1"></i> Export
                    </button>
                    <button class="btn btn-outline-primary btn-sm" id="printGradingBtn">
                        <i class="bi bi-printer me-1"></i> Print
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- KPI Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-primary">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Total Subjects</h6>
                    <h3 class="text-primary mb-0" id="totalSubjects">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-success">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Fully Graded</h6>
                    <h3 class="text-success mb-0" id="fullyGraded">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-warning">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Partially Graded</h6>
                    <h3 class="text-warning mb-0" id="partiallyGraded">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-danger">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Not Started</h6>
                    <h3 class="text-danger mb-0" id="notStarted">0</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Overall Progress Bar -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0">Overall Grading Progress</h6>
                <span class="badge bg-primary" id="overallPercentage">0%</span>
            </div>
            <div class="progress" style="height: 24px;">
                <div class="progress-bar bg-success" role="progressbar" id="overallProgressBar"
                     style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
            </div>
        </div>
    </div>

    <!-- Filter / Search Row -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Term</label>
                    <select class="form-select" id="termFilter">
                        <option value="">All Terms</option>
                        <option value="1">Term 1</option>
                        <option value="2">Term 2</option>
                        <option value="3">Term 3</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Class</label>
                    <select class="form-select" id="classFilter">
                        <option value="">All Classes</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <select class="form-select" id="statusFilter">
                        <option value="">All Status</option>
                        <option value="complete">Fully Graded</option>
                        <option value="partial">Partially Graded</option>
                        <option value="not_started">Not Started</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Data Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="gradingStatusTable">
                    <thead class="table-light">
                        <tr>
                            <th>Subject</th>
                            <th>Teacher</th>
                            <th>Class</th>
                            <th>Total Students</th>
                            <th>Graded</th>
                            <th>Pending</th>
                            <th>Completion</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Dynamic content -->
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <nav>
                <ul class="pagination justify-content-center" id="pagination">
                    <!-- Dynamic pagination -->
                </ul>
            </nav>
        </div>
    </div>
</div>

<script src="/Kingsway/js/pages/grading_status.js"></script>
