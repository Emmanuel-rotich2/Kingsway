<?php
/**
 * Student Management Page
 *
 * Presentation layer only (HTML & minimal PHP).
 * All business logic, permissions, and events are handled in:
 *   /js/pages/manage_students.js (studentsManagementController)
 *
 * This page is embedded within: app_layout.php
 *
 * ------------------------------------------------------------
 * ROLE-BASED ACCESS OVERVIEW
 * ------------------------------------------------------------
 * Admin / Director:
 *   - Full access (view, create, edit, delete, promote, transfer)
 *
 * Headteacher:
 *   - Full access except permanent deletion
 *
 * Deputy Head (Academic):
 *   - View, edit, promote
 *
 * Class Teacher:
 *   - View students assigned to own class only
 *
 * Registrar / Secretary:
 *   - View, create, edit
 *
 * Accountant / Bursar:
 *   - View only (including fee status)
 *
 * Parent:
 *   - View own children only
 * ------------------------------------------------------------
 */
?>

<div class="card shadow-sm">
    <!-- Page Header -->
    <div class="card-header bg-gradient bg-primary text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0">
                <i class="bi bi-people-fill"></i> Student Management
            </h4>

            <div class="btn-group">
                <!-- Add Student -->
                <button
                    class="btn btn-light btn-sm"
                    onclick="studentsManagementController.showStudentModal()"
                    data-permission="students_create"
                >
                    <i class="bi bi-plus-circle"></i> Add Student
                </button>

                <!-- Bulk Import -->
                <button
                    class="btn btn-outline-light btn-sm"
                    onclick="studentsManagementController.showBulkImportModal()"
                    data-permission="students_create"
                    data-role="registrar,school_administrator,admin"
                >
                    <i class="bi bi-upload"></i> Bulk Import
                </button>

                <!-- Export -->
                <button
                    class="btn btn-outline-light btn-sm"
                    onclick="studentsManagementController.exportStudents()"
                    data-permission="students_view"
                >
                    <i class="bi bi-download"></i> Export
                </button>
            </div>
        </div>
    </div>

    <div class="card-body">

        <!-- ===================== -->
        <!-- STUDENT STATISTICS -->
        <!-- ===================== -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-primary text-center">
                    <div class="card-body">
                        <h6 class="text-muted">Total Students</h6>
                        <h3 class="text-primary mb-0" id="totalStudentsCount">0</h3>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card border-success text-center">
                    <div class="card-body">
                        <h6 class="text-muted">Active</h6>
                        <h3 class="text-success mb-0" id="activeStudentsCount">0</h3>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card border-warning text-center">
                    <div class="card-body">
                        <h6 class="text-muted">New This Term</h6>
                        <h3 class="text-warning mb-0" id="newStudentsCount">0</h3>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card border-danger text-center">
                    <div class="card-body">
                        <h6 class="text-muted">Inactive</h6>
                        <h3 class="text-danger mb-0" id="inactiveStudentsCount">0</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===================== -->
        <!-- FINANCIAL OVERVIEW -->
        <!-- ===================== -->
        <div
            class="row mb-4"
            data-role="accountant,bursar,director,admin"
            data-permission="fees_view"
        >
            <div class="col-md-4">
                <div class="card border-info text-center">
                    <div class="card-body">
                        <h6 class="text-muted">Outstanding Balances</h6>
                        <h3 class="text-info mb-0" id="studentsWithBalanceCount">0</h3>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card border-success text-center">
                    <div class="card-body">
                        <h6 class="text-muted">Fully Paid</h6>
                        <h3 class="text-success mb-0" id="studentsPaidCount">0</h3>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card border-danger text-center">
                    <div class="card-body">
                        <h6 class="text-muted">Total Outstanding</h6>
                        <h3 class="text-danger mb-0" id="totalOutstandingFees">KES 0</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===================== -->
        <!-- FILTERS & SEARCH -->
        <!-- ===================== -->
        <div class="row mb-3">
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="bi bi-search"></i>
                    </span>
                    <input
                        type="text"
                        id="searchStudents"
                        class="form-control"
                        placeholder="Search by name, admission number, or ID..."
                        onkeyup="studentsManagementController.searchStudents(this.value)"
                    >
                </div>
            </div>

            <!-- Class filter hidden for class teachers -->
            <div class="col-md-2" data-role-exclude="class_teacher">
                <select
                    id="classFilter"
                    class="form-select"
                    onchange="studentsManagementController.filterByClass(this.value)"
                >
                    <option value="">All Classes</option>
                </select>
            </div>

            <div class="col-md-2">
                <select
                    id="streamFilter"
                    class="form-select"
                    onchange="studentsManagementController.filterByStream(this.value)"
                >
                    <option value="">All Streams</option>
                </select>
            </div>

            <div class="col-md-2">
                <select
                    id="genderFilter"
                    class="form-select"
                    onchange="studentsManagementController.filterByGender(this.value)"
                >
                    <option value="">All Genders</option>
                    <option value="M">Male</option>
                    <option value="F">Female</option>
                </select>
            </div>

            <div class="col-md-2">
                <select
                    id="statusFilter"
                    class="form-select"
                    onchange="studentsManagementController.filterByStatus(this.value)"
                >
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="suspended">Suspended</option>
                    <option value="graduated">Graduated</option>
                </select>
            </div>
        </div>

        <!-- ===================== -->
        <!-- STUDENTS TABLE -->
        <!-- ===================== -->
        <div class="table-responsive" id="studentsTableContainer">
            <table class="table table-hover table-striped">
                <thead class="table-light">
                    <tr>
                        <th>Photo</th>
                        <th>#</th>
                        <th>Admission No.</th>
                        <th>Name</th>
                        <th>Class / Stream</th>
                        <th>Gender</th>
                        <th>Contact</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody id="studentsTableBody">
                    <tr>
                        <td colspan="9" class="text-center py-4">
                            <div class="spinner-border text-primary"></div>
                            <p class="text-muted mt-2 mb-0">
                                Loading student records...
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- ===================== -->
        <!-- PAGINATION -->
        <!-- ===================== -->
        <div class="d-flex justify-content-between align-items-center mt-3">
            <span class="text-muted">
                Showing <span id="showingFrom">0</span> to
                <span id="showingTo">0</span> of
                <span id="totalRecords">0</span> students
            </span>

            <nav>
                <ul class="pagination mb-0" id="pagination"></ul>
            </nav>
        </div>
    </div>
</div>

<!-- Page Controller -->
<script src="/Kingsway/js/pages/manage_students.js"></script>
