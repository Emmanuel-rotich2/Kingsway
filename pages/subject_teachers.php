<?php
/**
 * Subject Teachers Page
 * Purpose: View all teachers with their subject assignments and workload
 * Features: Teacher workload overview, qualification display, subject-class mapping
 */
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1"><i class="bi bi-person-badge"></i> Subject Teachers</h4>
            <small class="text-muted">View teacher workload, subjects, and qualifications</small>
        </div>
        <div class="btn-group">
            <button class="btn btn-outline-primary btn-sm" id="exportSubjectTeachersBtn">
                <i class="bi bi-download"></i> Export
            </button>
        </div>
    </div>

    <!-- KPI Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-primary">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Total Teachers</h6>
                    <h3 class="text-primary mb-0" id="totalTeachers">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-success">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Full-Time</h6>
                    <h3 class="text-success mb-0" id="fullTimeTeachers">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-info">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Contract / Part-Time</h6>
                    <h3 class="text-info mb-0" id="contractTeachers">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-warning">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Avg Workload (Periods/Wk)</h6>
                    <h3 class="text-warning mb-0" id="avgWorkload">0</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter / Search -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <input type="text" class="form-control" id="searchSubjectTeachers"
                        placeholder="Search teacher name or employee ID...">
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="departmentFilterST">
                        <option value="">All Departments</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="employmentTypeFilter">
                        <option value="">All Types</option>
                        <option value="full-time">Full-Time</option>
                        <option value="contract">Contract</option>
                        <option value="part-time">Part-Time</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" id="statusFilterST">
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
                <table class="table table-hover table-bordered" id="subjectTeachersTable">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Teacher Name</th>
                            <th>Employee ID</th>
                            <th>Subjects</th>
                            <th>Classes</th>
                            <th>Periods/Week</th>
                            <th>Qualification</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="subjectTeachersTableBody">
                        <!-- Dynamic content -->
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-3">
                <small class="text-muted">
                    Showing <span id="showingFrom">0</span> to <span id="showingTo">0</span> of <span
                        id="totalRecords">0</span> teachers
                </small>
                <nav>
                    <ul class="pagination pagination-sm mb-0" id="pagination"></ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<script src="/Kingsway/js/pages/subject_teachers.js?v=<?php echo time(); ?>"></script>