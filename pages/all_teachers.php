<?php
/**
 * All Teachers Page - Pure UI/UX Layout
 * Controller: all_teachers.js
 * Authentication: JWT via api.js + backend middleware
 * Role-based access: JavaScript AuthContext + permission system
 */
?>
<div class="teachers-container">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1"><i class="fas fa-chalkboard-teacher me-2"></i>All Teachers</h4>
                    <p class="text-muted mb-0">Teaching staff by learning areas, class assignments, school level, and teaching role</p>
                </div>
                <a href="home.php?route=manage_staff" class="btn btn-primary" data-permission-module="staff" data-permission-action="create">
                    <i class="fas fa-plus me-1"></i> Add Teacher
                </a>
            </div>
        </div>
    </div>

    <!-- Stats -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h2 id="totalTeachers">--</h2>
                    <p class="mb-0">Total Teachers</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h2 id="classTeachers">--</h2>
                    <p class="mb-0">Class Teachers</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h2 id="hods">--</h2>
                    <p class="mb-0">Subject Teachers</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Teachers Table -->
    <div class="card">
        <div class="card-header">
            <div class="row g-2">
                <div class="col-md-3">
                    <input type="text" class="form-control" id="searchTeacher" placeholder="Search teacher...">
                </div>
                <div class="col-md-2">
                    <select class="form-select" id="filterDepartment">
                        <option value="">Teaching Departments</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" id="filterSubject">
                        <option value="">Learning Areas</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" id="filterLevel">
                        <option value="">School Levels</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" id="filterTeachingRole">
                        <option value="">Teaching Roles</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <button class="btn btn-outline-secondary w-100" id="exportTeachers" data-permission-module="staff" data-permission-action="export">
                        <i class="fas fa-download"></i>
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="teachersTable">
                    <thead>
                        <tr>
                            <th>Photo</th>
                            <th>Teacher</th>
                            <th>Staff No</th>
                            <th>Teaching Role</th>
                            <th>Learning Areas</th>
                            <th>Classes / Levels</th>
                            <th>Department</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="teacherInsightModal" tabindex="-1" aria-labelledby="teacherInsightModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="teacherInsightModalLabel">Teacher</h5>
                    <small class="text-muted" id="teacherInsightModalSubtitle"></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="teacherInsightModalBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="teacherInsightPrintBtn">
                    <i class="fas fa-print me-1"></i> Print Summary
                </button>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php $allTeachersJs = __DIR__ . '/../js/pages/all_teachers.js'; ?>
<script src="<?= $appBase ?>/js/pages/all_teachers.js?v=<?= file_exists($allTeachersJs) ? filemtime($allTeachersJs) : time() ?>"></script>
