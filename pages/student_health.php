<?php
/**
 * Student Health Records Page
 * Track health alerts, clinic visits, medication, allergies, and emergency information
 * Embedded in app_layout.php
 */

// Ensure $appBase is available for script loading
if (!isset($appBase)) {
    $appBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    if ($appBase === '.' || $appBase === '/') {
        $appBase = '';
    }
}
?>

<div class="container-fluid py-4" id="studentHealthPage">

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-danger text-white">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h4 class="mb-0">
                        <i class="bi bi-heart-pulse me-2"></i>
                        Student Health Records
                    </h4>
                    <small id="scopeSubtitle">Track health alerts, clinic visits, medication, allergies, and emergency information</small>
                </div>
                <div class="btn-group">
                    <button class="btn btn-light btn-sm" id="refreshBtn">
                        <i class="bi bi-arrow-clockwise"></i> Refresh
                    </button>
                    <button class="btn btn-outline-light btn-sm" id="exportBtn">
                        <i class="bi bi-download"></i> Export
                    </button>
                    <button class="btn btn-light btn-sm" id="addRecordBtn">
                        <i class="bi bi-plus-circle"></i> Add Record
                    </button>
                </div>
            </div>
        </div>

        <div class="card-body">

            <!-- Filters -->
            <div class="row g-3 mb-4">
                <div class="col-xl-2 col-md-4">
                    <label class="form-label fw-semibold">Academic Year</label>
                    <select class="form-select" id="academicYearFilter">
                        <option value="">All Years</option>
                    </select>
                </div>

                <div class="col-xl-2 col-md-4">
                    <label class="form-label fw-semibold">Class</label>
                    <select class="form-select" id="classFilter">
                        <option value="">All Classes</option>
                    </select>
                </div>

                <div class="col-xl-2 col-md-4">
                    <label class="form-label fw-semibold">Stream</label>
                    <select class="form-select" id="streamFilter">
                        <option value="">All Streams</option>
                    </select>
                </div>

                <div class="col-xl-2 col-md-4">
                    <label class="form-label fw-semibold">Health Category</label>
                    <select class="form-select" id="healthCategoryFilter">
                        <option value="">All Categories</option>
                        <option value="general">General</option>
                        <option value="allergy">Allergy</option>
                        <option value="condition">Condition</option>
                        <option value="medication">Medication</option>
                        <option value="injury">Injury</option>
                        <option value="incident">Incident</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <div class="col-xl-2 col-md-4">
                    <label class="form-label fw-semibold">Alert Status</label>
                    <select class="form-select" id="alertStatusFilter">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="resolved">Resolved</option>
                        <option value="monitoring">Monitoring</option>
                    </select>
                </div>

                <div class="col-xl-2 col-md-4">
                    <label class="form-label fw-semibold">Severity</label>
                    <select class="form-select" id="severityFilter">
                        <option value="">All</option>
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                        <option value="critical">Critical</option>
                    </select>
                </div>

                <div class="col-xl-3 col-md-6">
                    <label class="form-label fw-semibold">Search</label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" class="form-control" id="searchBox"
                               placeholder="Search by student name, admission number, condition, allergy, or medication">
                    </div>
                </div>

                <div class="col-xl-2 col-md-4 d-flex align-items-end">
                    <button class="btn btn-danger w-100" id="applyFiltersBtn">
                        <i class="bi bi-funnel me-1"></i> Apply
                    </button>
                </div>

                <div class="col-xl-2 col-md-4 d-flex align-items-end">
                    <button class="btn btn-outline-secondary w-100" id="resetFiltersBtn">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                    </button>
                </div>
            </div>

            <!-- Summary cards -->
            <div class="row g-3 mb-4">
                <div class="col-xl-2 col-md-4">
                    <div class="card border-0 bg-light h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle bg-danger text-white p-3">
                                    <i class="bi bi-file-medical"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Total Records</small>
                                    <h4 class="mb-0" id="totalRecords">0</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4">
                    <div class="card border-0 bg-light h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle bg-warning text-dark p-3">
                                    <i class="bi bi-exclamation-triangle"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Active Alerts</small>
                                    <h4 class="mb-0" id="activeAlerts">0</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4">
                    <div class="card border-0 bg-light h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle bg-info text-white p-3">
                                    <i class="bi bi-truck"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Clinic Visits</small>
                                    <h4 class="mb-0" id="clinicVisits">0</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4">
                    <div class="card border-0 bg-light h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle bg-primary text-white p-3">
                                    <i class="bi bi-person-standing"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Allergies</small>
                                    <h4 class="mb-0" id="allergiesCount">0</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4">
                    <div class="card border-0 bg-light h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle bg-success text-white p-3">
                                    <i class="bi bi-capsule"></i>
                                </div>
                                <div>
                                    <small class="text-muted">On Medication</small>
                                    <h4 class="mb-0" id="medicationCount">0</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4">
                    <div class="card border-0 bg-light h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle bg-secondary text-white p-3">
                                    <i class="bi bi-exclamation-circle"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Emergency</small>
                                    <h4 class="mb-0" id="emergencyCount">0</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- States -->
            <div id="recordsLoading" class="alert alert-info d-none">
                <i class="bi bi-arrow-clockwise fa-spin me-2"></i> Loading health records...
            </div>

            <div id="recordsError" class="alert alert-danger d-none"></div>

            <div id="recordsEmpty" class="alert alert-warning d-none">
                <i class="bi bi-info-circle me-2"></i> No health records found for the selected filters.
            </div>

            <!-- Main Table -->
            <div class="card border-0 shadow-sm" id="recordsCard">
                <div class="card-header bg-white">
                    <strong>
                        <i class="bi bi-list-ul me-2 text-danger"></i>
                        Health Records
                    </strong>
                </div>

                <div class="card-body table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Record ID</th>
                                <th scope="col">Student Name</th>
                                <th scope="col">Adm No</th>
                                <th scope="col">Class</th>
                                <th scope="col">Stream</th>
                                <th scope="col">Category</th>
                                <th scope="col">Alert Type</th>
                                <th scope="col">Severity</th>
                                <th scope="col">Status</th>
                                <th scope="col">Last Visit</th>
                                <th scope="col">Next Review</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="recordsTableBody">
                            <tr>
                                <td class="text-center text-muted">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Health Record Details Modal -->
<div class="modal fade" id="recordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-xl">
        <div class="modal-content border-0 shadow">

            <div class="modal-header bg-danger text-white">
                <div>
                    <h5 class="modal-title mb-0">
                        <i class="bi bi-heart-pulse me-2"></i>
                        Health Record Details
                    </h5>
                    <small id="modalSubtitle">Record #<span id="modalRecordId"></span></small>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div id="modalLoading" class="alert alert-info d-none">
                    <i class="bi bi-arrow-clockwise fa-spin me-2"></i> Loading record details...
                </div>

                <div id="modalError" class="alert alert-danger d-none"></div>

                <div id="modalRecordContent">
                    <!-- Record details will be rendered here -->
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <button class="btn btn-success" id="addClinicVisitBtn">
                    <i class="bi bi-plus-circle me-1"></i> Add Clinic Visit
                </button>
                <button class="btn btn-warning" id="addMedicationBtn">
                    <i class="bi bi-pill me-1"></i> Add Medication Note
                </button>
                <button class="btn btn-info" id="markReviewedBtn">
                    <i class="bi bi-check-circle me-1"></i> Mark Reviewed
                </button>
            </div>

        </div>
    </div>
</div>

<script src="<?php echo $appBase; ?>/js/pages/student_health.js?v=<?php echo time(); ?>"></script>
