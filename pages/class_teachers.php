<?php
/**
 * Class Teachers Page
 * Purpose: View and manage class teacher assignments
 * Features: Assign class teachers to classes/streams, view student counts, contact info
 */
?>

<div>
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1"><i class="bi bi-people"></i> Class Teachers</h4>
            <small class="text-muted">Manage class teacher assignments for each class and stream</small>
        </div>
        <div class="btn-group">
            <button class="btn btn-outline-primary btn-sm" id="exportClassTeachersBtn">
                <i class="bi bi-download"></i> Export
            </button>
            <button class="btn btn-success btn-sm" id="assignClassTeacherBtn">
                <i class="bi bi-plus-circle"></i> Assign Teacher
            </button>
        </div>
    </div>

    <!-- KPI Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-primary">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Total Classes</h6>
                    <h3 class="text-primary mb-0" id="totalClasses">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-success">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Assigned</h6>
                    <h3 class="text-success mb-0" id="assignedClasses">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-warning">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Unassigned</h6>
                    <h3 class="text-warning mb-0" id="unassignedClasses">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-info">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Teachers Involved</h6>
                    <h3 class="text-info mb-0" id="teachersInvolved">0</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter / Search -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <input type="text" class="form-control" id="searchClassTeachers" placeholder="Search by class or teacher name...">
                </div>
                <div class="col-md-4">
                    <select class="form-select" id="statusFilterCT">
                        <option value="">All Status</option>
                        <option value="assigned">Assigned</option>
                        <option value="unassigned">Unassigned</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <select class="form-select" id="levelFilter">
                        <option value="">All Levels</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Data Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-bordered" id="classTeachersTable">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Class</th>
                            <th>Stream</th>
                            <th>Class Teacher</th>
                            <th>Phone</th>
                            <th>Students Count</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="classTeachersTableBody">
                        <!-- Dynamic content -->
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-3">
                <small class="text-muted">
                    Showing <span id="showingFrom">0</span> to <span id="showingTo">0</span> of <span id="totalRecords">0</span> entries
                </small>
                <nav>
                    <ul class="pagination pagination-sm mb-0" id="pagination"></ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<!-- Assign Class Teacher Modal -->
<div class="modal fade" id="classTeacherModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="classTeacherModalLabel">Assign Class Teacher</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="classTeacherForm">
                    <input type="hidden" id="ctAssignmentId">
                    <div class="mb-3">
                        <label class="form-label">Class *</label>
                        <select class="form-select" id="ctClass" required>
                            <option value="">Select Class</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Stream</label>
                        <select class="form-select" id="ctStream">
                            <option value="">Select Stream</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Teacher *</label>
                        <select class="form-select" id="ctTeacher" required>
                            <option value="">Select Teacher</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveClassTeacherBtn">Save Assignment</button>
            </div>
        </div>
    </div>
</div>

<script src="/Kingsway/js/pages/class_teachers.js?v=<?php echo time(); ?>"></script>
