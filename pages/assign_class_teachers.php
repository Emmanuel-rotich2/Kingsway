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
                <button class="btn btn-light btn-sm" onclick="AssignClassTeachersController.showAssignModal()" data-permission="academic_create">
                    <i class="bi bi-plus-circle"></i> Assign Teacher
                </button>
                <button class="btn btn-outline-light btn-sm" onclick="AssignClassTeachersController.exportAssignments()">
                    <i class="bi bi-download"></i> Export
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
                        <h6 class="text-muted mb-2">Total Classes</h6>
                        <h3 class="text-primary mb-0" id="totalClassesCount">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Assigned Teachers</h6>
                        <h3 class="text-success mb-0" id="assignedTeachersCount">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-warning">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Unassigned Classes</h6>
                        <h3 class="text-warning mb-0" id="unassignedClassesCount">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-info">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Total Teachers</h6>
                        <h3 class="text-info mb-0" id="totalTeachersCount">0</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="row mb-3">
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" id="searchAssignments" class="form-control" 
                           placeholder="Search assignments...">
                </div>
            </div>
            <div class="col-md-3">
                <select id="gradeLevelFilter" class="form-select">
                    <option value="">All Grade Levels</option>
                    <option value="grade_1">Grade 1</option>
                    <option value="grade_2">Grade 2</option>
                    <option value="grade_3">Grade 3</option>
                    <option value="grade_4">Grade 4</option>
                    <option value="grade_5">Grade 5</option>
                    <option value="grade_6">Grade 6</option>
                    <option value="form_1">Form 1</option>
                    <option value="form_2">Form 2</option>
                    <option value="form_3">Form 3</option>
                    <option value="form_4">Form 4</option>
                </select>
            </div>
            <div class="col-md-3">
                <select id="assignmentStatusFilter" class="form-select">
                    <option value="">All Status</option>
                    <option value="assigned">Assigned</option>
                    <option value="unassigned">Unassigned</option>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-outline-secondary w-100" onclick="AssignClassTeachersController.refresh()">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
            </div>
        </div>

        <!-- Assignments Table -->
        <div class="table-responsive">
            <table class="table table-hover table-striped" id="assignmentsTable">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Class</th>
                        <th>Stream</th>
                        <th>Class Teacher</th>
                        <th>Teacher Email</th>
                        <th>Assigned Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="8" class="text-center py-4">
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
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Assign Class Teacher</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="assignTeacherForm">
                    <input type="hidden" id="assignmentId">
                    <div class="mb-3">
                        <label class="form-label">Class *</label>
                        <select class="form-select" id="assignmentClassId" required>
                            <option value="">Select Class</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Stream</label>
                        <select class="form-select" id="assignmentStreamId">
                            <option value="">All Streams</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Teacher *</label>
                        <select class="form-select" id="assignmentTeacherId" required>
                            <option value="">Select Teacher</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Academic Year *</label>
                        <select class="form-select" id="assignmentAcademicYearId" required>
                            <option value="">Select Academic Year</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="AssignClassTeachersController.saveAssignment()">
                    Save Assignment
                </button>
            </div>
        </div>
    </div>
</div>

<script src="js/pages/staff_access.js"></script>
<script src="<?= $appBase ?>/js/pages/assign_class_teachers.js"></script>
