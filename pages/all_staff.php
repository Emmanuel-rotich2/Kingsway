<?php
/**
 * All Staff Page
 * 
 * Purpose: View all staff members
 * Features:
 * - List all staff (teaching and non-teaching)
 * - Filter by department/role
 * - Quick actions
 */
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1"><i class="fas fa-users me-2"></i>All Staff</h4>
                    <p class="text-muted mb-0">View teaching and non-teaching staff</p>
                </div>
                <a href="home.php?route=manage_staff" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i> Add Staff
                </a>
            </div>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h2 id="totalStaff">--</h2>
                    <p class="mb-0">Total Staff</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h2 id="teachingStaff">--</h2>
                    <p class="mb-0">Teaching Staff</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h2 id="nonTeachingStaff">--</h2>
                    <p class="mb-0">Non-Teaching Staff</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark">
                <div class="card-body text-center">
                    <h2 id="onLeave">--</h2>
                    <p class="mb-0">On Leave</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters and Table -->
    <div class="card">
        <div class="card-header">
            <div class="row g-2">
                <div class="col-md-3">
                    <select class="form-select" id="filterDepartment">
                        <option value="">All Departments</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="filterRole">
                        <option value="">All Roles</option>
                        <option value="teaching">Teaching</option>
                        <option value="non-teaching">Non-Teaching</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <input type="text" class="form-control" id="searchStaff" placeholder="Search by name or ID...">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-outline-secondary w-100" id="exportStaff">
                        <i class="fas fa-download"></i> Export
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="staffTable">
                    <thead>
                        <tr>
                            <th>Photo</th>
                            <th>Staff ID</th>
                            <th>Name</th>
                            <th>Role</th>
                            <th>Department</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="8" class="text-center">Loading staff...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="js/pages/all_staff.js"></script>