<?php
/**
 * All Teachers Page
 * 
 * Purpose: View all teaching staff
 * Features:
 * - List all teachers
 * - Subject assignments
 * - Performance overview
 */
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1"><i class="fas fa-chalkboard-teacher me-2"></i>All Teachers</h4>
                    <p class="text-muted mb-0">View and manage teaching staff</p>
                </div>
                <a href="home.php?route=manage_staff" class="btn btn-primary">
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
                    <p class="mb-0">HODs</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Teachers Table -->
    <div class="card">
        <div class="card-header">
            <div class="row g-2">
                <div class="col-md-4">
                    <input type="text" class="form-control" id="searchTeacher" placeholder="Search teacher...">
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="filterDepartment">
                        <option value="">All Departments</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="filterSubject">
                        <option value="">All Subjects</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-outline-secondary w-100" id="exportTeachers">
                        <i class="fas fa-download"></i> Export
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
                            <th>Name</th>
                            <th>Employee ID</th>
                            <th>Department</th>
                            <th>Subjects</th>
                            <th>Role</th>
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

<script src="js/pages/all_teachers.js"></script>