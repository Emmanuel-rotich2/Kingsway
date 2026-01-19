<?php
/**
 * All Students - Stateless JWT-based Router
 *
 * Uses JavaScript to determine user role from JWT token and load appropriate template
 */

// Default template (will be overridden by JavaScript)
$template = 'students/manager_students.php'; // Default fallback

// Include the template (JavaScript will replace content based on role)
include __DIR__ . '/' . $template;
exit;
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1"><i class="fas fa-user-graduate me-2"></i>All Students</h4>
                    <p class="text-muted mb-0">View and manage enrolled students</p>
                </div>
                <div class="btn-group">
                    <a href="home.php?route=manage_students" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i> Add Student
                    </a>
                    <button class="btn btn-outline-secondary" id="exportStudents">
                        <i class="fas fa-download me-1"></i> Export
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Class</label>
                    <select class="form-select" id="filterClass">
                        <option value="">All Classes</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Stream</label>
                    <select class="form-select" id="filterStream">
                        <option value="">All Streams</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" id="filterStatus">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="graduated">Graduated</option>
                        <option value="transferred">Transferred</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input type="text" class="form-control" id="searchStudent" placeholder="Name or Admission No.">
                </div>
            </div>
        </div>
    </div>

    <!-- Students Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="studentsTable">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAll"></th>
                            <th>Photo</th>
                            <th>Admission No</th>
                            <th>Name</th>
                            <th>Class</th>
                            <th>Gender</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="8" class="text-center">
                                <div class="spinner-border spinner-border-sm text-primary"></div>
                                Loading students...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <nav aria-label="Page navigation" class="mt-3">
                <ul class="pagination justify-content-center" id="pagination"></ul>
            </nav>
        </div>
    </div>
</div>

<script src="js/pages/all_students.js"></script>