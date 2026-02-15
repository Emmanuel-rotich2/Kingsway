<?php
/**
 * Assign Subjects to Teachers Page
 * Purpose: Manage subject-teacher-class assignments
 * Features: Assignment CRUD, teacher workload view, bulk assign, conflict detection
 */
?>

<div>
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1"><i class="bi bi-person-lines-fill"></i> Assign Subjects to Teachers</h4>
            <small class="text-muted">Manage teacher-subject-class assignments and workload distribution</small>
        </div>
        <div class="btn-group">
            <button class="btn btn-outline-primary btn-sm" id="exportAssignmentsBtn">
                <i class="bi bi-download"></i> Export
            </button>
            <button class="btn btn-success btn-sm" id="addAssignmentBtn">
                <i class="bi bi-plus-circle"></i> New Assignment
            </button>
        </div>
    </div>

    <!-- KPI Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-primary">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Total Assignments</h6>
                    <h3 class="text-primary mb-0" id="totalAssignments">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-success">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Teachers Assigned</h6>
                    <h3 class="text-success mb-0" id="teachersAssigned">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-info">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Subjects Covered</h6>
                    <h3 class="text-info mb-0" id="subjectsCovered">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-warning">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Unassigned Slots</h6>
                    <h3 class="text-warning mb-0" id="unassignedSlots">0</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter / Search Card -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <select class="form-select" id="teacherFilter">
                        <option value="">All Teachers</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="subjectFilter">
                        <option value="">All Subjects</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="classFilter">
                        <option value="">All Classes</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="text" class="form-control" id="searchAssignments" placeholder="Search assignments...">
                </div>
            </div>
        </div>
    </div>

    <!-- Main Data Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-bordered" id="assignmentsTable">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Teacher Name</th>
                            <th>Subject</th>
                            <th>Class / Stream</th>
                            <th>Periods/Week</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="assignmentsTableBody">
                        <!-- Dynamic content -->
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-3">
                <small class="text-muted">
                    Showing <span id="showingFrom">0</span> to <span id="showingTo">0</span> of <span id="totalRecords">0</span> assignments
                </small>
                <nav>
                    <ul class="pagination pagination-sm mb-0" id="pagination"></ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Assignment Modal -->
<div class="modal fade" id="assignmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="assignmentModalLabel">New Assignment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="assignmentForm">
                    <input type="hidden" id="assignmentId">
                    <div class="mb-3">
                        <label class="form-label">Teacher *</label>
                        <select class="form-select" id="assignTeacher" required>
                            <option value="">Select Teacher</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Subject *</label>
                        <select class="form-select" id="assignSubject" required>
                            <option value="">Select Subject</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Class / Stream *</label>
                        <select class="form-select" id="assignClass" required>
                            <option value="">Select Class</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Periods Per Week</label>
                        <input type="number" class="form-control" id="assignPeriods" min="1" max="20" value="5">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" id="assignStatus">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveAssignmentBtn">Save Assignment</button>
            </div>
        </div>
    </div>
</div>

<script src="/Kingsway/js/pages/assign_subjects_to_teachers.js?v=<?php echo time(); ?>"></script>
