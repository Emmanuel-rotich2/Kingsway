<?php
/**
 * Teacher Workload Page
 * 
 * Purpose: View and manage teacher workload distribution
 * Features:
 * - Lessons per teacher
 * - Subject assignments
 * - Workload balancing
 */
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1"><i class="fas fa-tasks me-2"></i>Teacher Workload</h4>
                    <p class="text-muted mb-0">View and balance teacher workload across subjects and classes</p>
                </div>
                <button class="btn btn-outline-primary" id="exportWorkload">
                    <i class="fas fa-download me-1"></i> Export Report
                </button>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h3 id="totalTeachers">--</h3>
                    <p class="text-muted mb-0">Total Teachers</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h3 id="avgLessons">--</h3>
                    <p class="text-muted mb-0">Avg Lessons/Week</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body text-center">
                    <h3 id="overloaded">--</h3>
                    <p class="mb-0">Overloaded</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark">
                <div class="card-body text-center">
                    <h3 id="underloaded">--</h3>
                    <p class="mb-0">Underloaded</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Workload Chart -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Workload Distribution</h5>
        </div>
        <div class="card-body">
            <canvas id="workloadChart" height="150"></canvas>
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
                    <select class="form-select" id="filterWorkload">
                        <option value="">All Status</option>
                        <option value="overloaded">Overloaded</option>
                        <option value="optimal">Optimal</option>
                        <option value="underloaded">Underloaded</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="workloadTable">
                    <thead>
                        <tr>
                            <th>Teacher</th>
                            <th>Department</th>
                            <th>Subjects</th>
                            <th>Classes</th>
                            <th>Lessons/Week</th>
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

<script src="js/pages/teacher_workload.js"></script>