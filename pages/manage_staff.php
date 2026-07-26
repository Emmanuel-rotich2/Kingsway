<?php
/**
 * Manage Staff Page - Pure UI/UX Layout
 * Controller: staff_production_ui.js
 * Authentication: JWT via api.js + backend middleware
 * Role-based access: JavaScript AuthContext + permission system
 */
if (!isset($staffPageTitle)) {
    $staffPageTitle = 'Staff Management';
}
if (!isset($staffPageDescription)) {
    $staffPageDescription = 'Manage all staff members and their assignments';
}
if (!isset($staffPageIcon)) {
    $staffPageIcon = 'fas fa-chalkboard-teacher';
}
if (isset($staffPageContext) && is_array($staffPageContext)) {
    echo '<script>window.STAFF_PAGE_CONTEXT = ' .
        json_encode($staffPageContext, JSON_UNESCAPED_SLASHES) .
        ';</script>' . PHP_EOL;
}
?>
    <div class="staff-management-container" data-staff-directory-page>
    <!-- Page Header -->
    <div class="page-header mb-4">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-1"><i class="<?= htmlspecialchars($staffPageIcon, ENT_QUOTES, 'UTF-8') ?> me-2"></i><?= htmlspecialchars($staffPageTitle, ENT_QUOTES, 'UTF-8') ?></h4>
                <p class="text-muted mb-0"><?= htmlspecialchars($staffPageDescription, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <div class="btn-group">
                <button class="btn btn-primary" id="addStaffBtn" data-permission-module="staff" data-permission-action="create">
                    <i class="fas fa-plus me-1"></i>Add Staff
                </button>
                <button class="btn btn-outline-primary" id="importStaffBtn" data-permission="staff.directory.manage">
                    <i class="fas fa-file-import me-1"></i>Import Staff
                </button>
                <button class="btn btn-outline-secondary" id="exportStaffBtn" data-permission-module="staff" data-permission-action="export">
                    <i class="fas fa-download me-1"></i>Export
                </button>
            </div>
        </div>
    </div>

    <!-- Role-specific summary cards -->
    <div class="row mb-4" id="staffStatsRow"></div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-2">
                <div class="col-md-3">
                    <input type="text" class="form-control" id="searchStaff" placeholder="Search staff...">
                </div>
                <div class="col-md-2">
                    <select class="form-select" id="filterDepartment">
                        <option value="">All Departments</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" id="filterStaffType">
                        <option value="">All Types</option>
                        <option value="1">Teaching</option>
                        <option value="2">Non-Teaching</option>
                        <option value="3">Admin</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" id="filterStatus">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="on_leave">On Leave</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-outline-secondary w-100" id="resetFilters">
                        <i class="fas fa-redo me-1"></i>Reset Filters
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Staff Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="staffTable">
                    <thead>
                        <tr id="staffTableHead">
                            <th>Staff</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="staffTableBody">
                        <tr>
                            <td colspan="7" class="text-center py-4">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Staff Import Modal -->
<div class="modal fade" id="staffImportModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Import Staff</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="staffImportState" class="alert alert-info">
                    Download the template, complete the staff rows, then upload the file for validation.
                </div>
                <div class="row g-3">
                    <div class="col-lg-5">
                        <div class="border rounded p-3 h-100">
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                <button type="button" class="btn btn-outline-primary btn-sm" id="downloadStaffCsvTemplate">
                                    <i class="fas fa-file-csv me-1"></i>CSV Template
                                </button>
                                <button type="button" class="btn btn-outline-success btn-sm" id="downloadStaffExcelTemplate">
                                    <i class="fas fa-file-excel me-1"></i>Excel Template
                                </button>
                            </div>
                            <label class="form-label fw-semibold" for="staffImportFile">Completed staff file</label>
                            <input class="form-control" type="file" id="staffImportFile" accept=".csv,.xlsx,.xls">
                            <div class="form-text">Required fields include names, email, phone, department code, role, employment date, contract type, position, and payroll identifiers.</div>
                            <button type="button" class="btn btn-primary mt-3" id="validateStaffImportBtn" disabled>
                                <i class="fas fa-shield-alt me-1"></i>Validate File
                            </button>
                        </div>
                    </div>
                    <div class="col-lg-7">
                        <div class="border rounded p-3 h-100">
                            <h6 class="mb-3">Reference Values</h6>
                            <div id="staffImportReference" class="small text-muted">Loading reference values...</div>
                        </div>
                    </div>
                </div>
                <div class="border rounded mt-3 d-none" id="staffImportPreviewCard">
                    <div class="d-flex justify-content-between align-items-center border-bottom p-3">
                        <h6 class="mb-0">Validation Result</h6>
                        <button type="button" class="btn btn-success btn-sm" id="commitStaffImportBtn" disabled>
                            <i class="fas fa-database me-1"></i>Commit Import
                        </button>
                    </div>
                    <div class="p-3">
                        <div class="row g-2 mb-3" id="staffImportSummary"></div>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Row</th>
                                        <th>Staff</th>
                                        <th>Email</th>
                                        <th>Department</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody id="staffImportRows"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Staff Editor Modal -->
<div class="modal fade" id="staffModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="staffModalTitle">Add Staff</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="staffForm">
                    <input type="hidden" id="staffId">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Staff No</label>
                            <input type="text" class="form-control" id="staffNo" placeholder="Auto generated">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">First Name *</label>
                            <input type="text" class="form-control" id="firstName" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Last Name *</label>
                            <input type="text" class="form-control" id="lastName" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" id="email">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="text" class="form-control" id="phone">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Department</label>
                            <select class="form-select" id="department">
                                <option value="">Select Department</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Staff Type</label>
                            <select class="form-select" id="staff_type_id">
                                <option value="">Select Type</option>
                                <option value="1">Teaching</option>
                                <option value="2">Non-Teaching</option>
                                <option value="3">Admin</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Job Title / Position</label>
                            <input type="text" class="form-control" id="position">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">System Role</label>
                            <select class="form-select" id="roleId">
                                <option value="">Select Role</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Employment Date</label>
                            <input type="date" class="form-control" id="employmentDate">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contract Type</label>
                            <select class="form-select" id="contractType">
                                <option value="permanent">Permanent</option>
                                <option value="contract">Contract</option>
                                <option value="temporary">Temporary</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select class="form-select" id="status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="on_leave">On Leave</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Gender</label>
                            <select class="form-select" id="gender">
                                <option value="">Select Gender</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Date of Birth</label>
                            <input type="date" class="form-control" id="dateOfBirth">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Marital Status</label>
                            <select class="form-select" id="maritalStatus">
                                <option value="">Select Status</option>
                                <option value="single">Single</option>
                                <option value="married">Married</option>
                                <option value="divorced">Divorced</option>
                                <option value="widowed">Widowed</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">TSC No</label>
                            <input type="text" class="form-control" id="tscNo">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">KRA PIN</label>
                            <input type="text" class="form-control" id="kraPin">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">NSSF No</label>
                            <input type="text" class="form-control" id="nssfNo">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">NHIF/SHIF No</label>
                            <input type="text" class="form-control" id="nhifNo">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Bank Name</label>
                            <input type="text" class="form-control" id="bankName">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Bank Account</label>
                            <input type="text" class="form-control" id="bankAccount">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Basic Salary</label>
                            <input type="number" min="0" step="0.01" class="form-control" id="salary">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" id="address" rows="2"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveStaffBtn">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Staff Profile Modal -->
<div class="modal fade" id="staffViewModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="staffViewModalTitle">Staff Profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="staffViewModalBody">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="editFromViewBtn">
                    <i class="fas fa-pen me-1"></i>Edit Staff
                </button>
            </div>
        </div>
    </div>
</div>

<?php
$staffAccessJsPath = __DIR__ . '/../js/pages/staff_access.js';
$staffProductionJsPath = __DIR__ . '/../js/pages/staff_production_ui.js';
$staffAccessJsVersion = is_file($staffAccessJsPath) ? filemtime($staffAccessJsPath) : time();
$staffProductionJsVersion = is_file($staffProductionJsPath) ? filemtime($staffProductionJsPath) : time();
?>
<script src="<?= $appBase ?>/js/pages/staff_access.js?v=<?= $staffAccessJsVersion ?>"></script>
<script src="<?= $appBase ?>/js/pages/staff_production_ui.js?v=<?= $staffProductionJsVersion ?>"></script>
