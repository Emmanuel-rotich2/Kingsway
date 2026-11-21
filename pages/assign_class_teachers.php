<?php
/**
 * Assign Class Teachers Page
 * Purpose: Dedicated page for assigning teachers to classes as class teachers
 * Features: Class-teacher assignment, management, academic year filtering
 * Block 2: Curriculum and Teaching Setup
 */

?>

<div class="card shadow-sm">
    <div class="card-header bg-gradient bg-primary text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0">
                <i class="bi bi-person-badge"></i> Assign Class Teachers
            </h4>
            <div class="btn-group">
                <button class="btn btn-outline-light btn-sm" data-kw-onclick="AssignClassTeachersController.exportAssignments()">
                    <i class="bi bi-download"></i> Export
                </button>
                <button class="btn btn-outline-light btn-sm" data-kw-onclick="AssignClassTeachersController.printAssignments()">
                    <i class="bi bi-printer"></i> Print
                </button>
            </div>
        </div>
    </div>
    
    <div class="card-body">
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-primary">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Streams</h6>
                        <h3 class="text-primary mb-0" id="totalClassesCount">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">With a class teacher</h6>
                        <h3 class="text-success mb-0" id="assignedTeachersCount">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-warning">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Still to assign</h6>
                        <h3 class="text-warning mb-0" id="unassignedClassesCount">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-info">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Teachers available</h6>
                        <h3 class="text-info mb-0" id="totalTeachersCount">0</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="row g-2 mb-3">
            <div class="col-6 col-md-3">
                <label class="form-label small text-muted mb-1" for="academicYearFilter">Academic year</label>
                <select id="academicYearFilter" class="form-select" aria-label="Academic year"></select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small text-muted mb-1" for="gradeLevelFilter">Class</label>
                <select id="gradeLevelFilter" class="form-select">
                    <option value="">All classes</option>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small text-muted mb-1" for="assignmentStatusFilter">Status</label>
                <select id="assignmentStatusFilter" class="form-select">
                    <option value="">All streams</option>
                    <option value="assigned">With a class teacher</option>
                    <option value="unassigned">Still to assign</option>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small text-muted mb-1" for="searchAssignments">Search</label>
                <input type="search" id="searchAssignments" class="form-control" placeholder="Class, stream or teacher">
            </div>
        </div>

        <!-- Assignments Table -->
        <div class="table-responsive">
            <table class="table table-hover table-striped" id="assignmentsTable">
                <thead class="table-light">
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">Class</th>
                        <th scope="col">Stream</th>
                        <th scope="col">Class Teacher</th>
                        <th scope="col">Teacher Email</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="d-print-none">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="7" class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="text-muted mt-2">Loading assignments...</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Assign Teacher Modal -->
<div class="modal fade" id="assignTeacherModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Assign Class Teacher</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="assignTeacherForm">
                    <p class="mb-3"><span class="text-muted">Stream:</span> <strong id="assignmentStreamLabel">—</strong></p>
                    <div class="mb-1">
                        <label class="form-label" for="assignmentTeacherId">Class teacher *</label>
                        <select class="form-select" id="assignmentTeacherId" required>
                            <option value="">Select Teacher</option>
                        </select>
                    </div>
                    <p class="small text-muted mb-0" id="assignmentTeacherHint"></p>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" data-kw-onclick="AssignClassTeachersController.saveAssignment()" data-year-write>
                    Save Assignment
                </button>
            </div>
        </div>
    </div>
</div>

<?php asset_script($appBase, 'js/pages/staff_access.js'); ?>
<?php asset_script($appBase, 'js/pages/assign_class_teachers.js'); ?>
