<?php
/**
 * All Subjects Page
 * Purpose: View and manage all subjects in the school curriculum
 * Features: Subject CRUD, department grouping, teacher assignment overview, subject type filtering
 */
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1"><i class="bi bi-book"></i> All Subjects</h4>
            <small class="text-muted">Manage school subjects and curriculum assignments</small>
        </div>
        <div class="btn-group">
            <button class="btn btn-outline-primary btn-sm" id="exportSubjectsBtn">
                <i class="bi bi-download"></i> Export
            </button>
            <button class="btn btn-success btn-sm" id="addSubjectBtn">
                <i class="bi bi-plus-circle"></i> Add Subject
            </button>
        </div>
    </div>

    <!-- KPI Summary Cards -->
    <div class="row g-3 mb-4">
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
                    <h6 class="text-muted mb-2">Core Subjects</h6>
                    <h3 class="text-success mb-0" id="coreSubjects">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-info">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Elective Subjects</h6>
                    <h3 class="text-info mb-0" id="electiveSubjects">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-warning">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Active Teachers</h6>
                    <h3 class="text-warning mb-0" id="activeTeachers">0</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter / Search Card -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <input type="text" class="form-control" id="searchSubjects" placeholder="Search subjects...">
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="departmentFilter">
                        <option value="">All Departments</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="typeFilter">
                        <option value="">All Types</option>
                        <option value="core">Core</option>
                        <option value="elective">Elective</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" id="statusFilterSubject">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Data Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-bordered" id="subjectsTable">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Subject Name</th>
                            <th>Code</th>
                            <th>Department</th>
                            <th>Type</th>
                            <th>Teachers Assigned</th>
                            <th>Classes</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="subjectsTableBody">
                        <!-- Dynamic content -->
                    </tbody>
                </table>
            </div>
            <!-- Pagination -->
            <div class="d-flex justify-content-between align-items-center mt-3">
                <small class="text-muted">
                    Showing <span id="showingFrom">0</span> to <span id="showingTo">0</span> of <span id="totalRecords">0</span> subjects
                </small>
                <nav>
                    <ul class="pagination pagination-sm mb-0" id="pagination"></ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Subject Modal -->
<div class="modal fade" id="subjectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="subjectModalLabel">Add Subject</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="subjectForm">
                    <input type="hidden" id="subjectId">
                    <div class="mb-3">
                        <label class="form-label">Subject Name *</label>
                        <input type="text" class="form-control" id="subjectName" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Subject Code *</label>
                        <input type="text" class="form-control" id="subjectCode" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Department</label>
                        <select class="form-select" id="subjectDepartment">
                            <option value="">Select Department</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Type *</label>
                        <select class="form-select" id="subjectType" required>
                            <option value="core">Core</option>
                            <option value="elective">Elective</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" id="subjectDescription" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" id="subjectStatus">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveSubjectBtn">Save Subject</button>
            </div>
        </div>
    </div>
</div>

<script src="/Kingsway/js/pages/all_subjects.js?v=<?php echo time(); ?>"></script>
