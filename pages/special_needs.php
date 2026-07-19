<?php
/**
 * Special Needs Page
 * Display and manage students with special educational needs and IEPs
 * Embedded in app_layout.php
 */

// Ensure $appBase is available for script loading
if (!isset($appBase)) {
    $appBase = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
    if ($appBase === '.' || $appBase === '/') {
        $appBase = '';
    }
}
?>

<div class="container-fluid py-4" id="specialNeedsPage">

    <!-- Print Header (only visible when printing) -->
    <div class="print-header">
        <h1>KINGSWAY PREPARATORY ACADEMY</h1>
        <h2>Special Needs & Student Support Report</h2>
        <div class="date">Printed on: <span id="printDate"></span></div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-info text-white">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h4 class="mb-0">
                        <i class="fas fa-hand-holding-heart me-2"></i>
                        Special Needs & Student Support
                    </h4>
                    <small id="scopeSubtitle">Track and manage students with special educational needs and IEPs</small>
                </div>
                <div class="btn-group">
                    <button class="btn btn-light btn-sm" id="exportRecordsBtn">
                        <i class="bi bi-download"></i> Export
                    </button>
                    <button class="btn btn-outline-light btn-sm" id="printRecordsBtn">
                        <i class="bi bi-printer"></i> Print
                    </button>
                    <button class="btn btn-light btn-sm" id="addRecordBtn" style="display: none;">
                        <i class="bi bi-plus-circle"></i> Add IEP
                    </button>
                </div>
            </div>
        </div>

        <div class="card-body">

            <!-- Filters -->
            <div class="row g-3 mb-4">
                <div class="col-xl-3 col-md-6">
                    <label class="form-label fw-semibold">Academic Year</label>
                    <select class="form-select" id="academicYearFilter">
                        <option value="">All Years</option>
                    </select>
                </div>

                <div class="col-xl-3 col-md-6">
                    <label class="form-label fw-semibold">Class</label>
                    <select class="form-select" id="classFilter">
                        <option value="">All Classes</option>
                    </select>
                </div>

                <div class="col-xl-3 col-md-6">
                    <label class="form-label fw-semibold">Stream</label>
                    <select class="form-select" id="streamFilter">
                        <option value="">All Streams</option>
                    </select>
                </div>

                <div class="col-xl-3 col-md-6">
                    <label class="form-label fw-semibold">IEP Status</label>
                    <select class="form-select" id="statusFilter">
                        <option value="">All Status</option>
                        <option value="draft">Draft</option>
                        <option value="active">Active</option>
                        <option value="completed">Completed</option>
                        <option value="archived">Archived</option>
                    </select>
                </div>

                <div class="col-xl-4 col-md-8">
                    <label class="form-label fw-semibold">Search</label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-search"></i>
                        </span>
                        <input type="text" class="form-control" id="searchBox"
                               placeholder="Search by student name, admission number, or IEP type">
                    </div>
                </div>

                <div class="col-xl-2 col-md-4 d-flex align-items-end">
                    <button class="btn btn-info w-100" id="applyFiltersBtn">
                        <i class="fas fa-filter me-1"></i> Apply
                    </button>
                </div>

                <div class="col-xl-2 col-md-4 d-flex align-items-end">
                    <button class="btn btn-outline-secondary w-100" id="resetFiltersBtn">
                        <i class="fas fa-undo me-1"></i> Reset
                    </button>
                </div>
            </div>

            <!-- Summary cards -->
            <div class="row g-3 mb-4">
                <div class="col-xl-2 col-md-4">
                    <div class="card border-0 bg-light h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle bg-info text-white p-3">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Total IEPs</small>
                                    <h4 class="mb-0" id="totalIEPs">0</h4>
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
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Active IEPs</small>
                                    <h4 class="mb-0" id="activeIEPs">0</h4>
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
                                    <i class="fas fa-edit"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Draft</small>
                                    <h4 class="mb-0" id="draftIEPs">0</h4>
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
                                    <i class="fas fa-graduation-cap"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Completed</small>
                                    <h4 class="mb-0" id="completedIEPs">0</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4">
                    <div class="card border-0 bg-light h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle bg-danger text-white p-3">
                                    <i class="fas fa-heartbeat"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Health Records</small>
                                    <h4 class="mb-0" id="healthRecords">0</h4>
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
                                    <i class="fas fa-archive"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Archived</small>
                                    <h4 class="mb-0" id="archivedIEPs">0</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- States -->
            <div id="iepsLoading" class="alert alert-info d-none">
                <i class="fas fa-spinner fa-spin me-2"></i> Loading special needs records...
            </div>

            <div id="iepsError" class="alert alert-danger d-none"></div>

            <div id="iepsEmpty" class="alert alert-warning d-none">
                <i class="fas fa-info-circle me-2"></i> No special needs records found for the selected filters.
            </div>

            <div id="iepsForbidden" class="alert alert-warning d-none">
                <i class="fas fa-lock me-2"></i> You do not have permission to view special needs records.
            </div>

            <!-- Main Table -->
            <div class="card border-0 shadow-sm" id="iepsCard">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <strong>
                        <i class="fas fa-list me-2 text-info"></i>
                        Individualized Education Programs (IEPs)
                    </strong>
                </div>

                <div class="card-body table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>IEP ID</th>
                                <th>Student</th>
                                <th>Adm No</th>
                                <th>Class</th>
                                <th>Stream</th>
                                <th>Dormitory</th>
                                <th>IEP Type</th>
                                <th>Category</th>
                                <th>Status</th>
                                <th>Academic Year</th>
                                <th>Created Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="iepsTableBody">
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

<!-- IEP Details Modal -->
<div class="modal fade" id="iepModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">

            <div class="modal-header bg-info text-white">
                <div>
                    <h5 class="modal-title mb-0">
                        <i class="fas fa-file-medical me-2"></i>
                        IEP Details
                    </h5>
                    <small id="modalIepSubtitle">IEP #<span id="modalIepId"></span></small>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                <div id="modalLoading" class="alert alert-info d-none">
                    <i class="fas fa-spinner fa-spin me-2"></i> Loading IEP details...
                </div>

                <div id="modalError" class="alert alert-danger d-none"></div>

                <div id="modalIepContent">

                    <!-- Student Info -->
                    <div class="card bg-light border-0 mb-4">
                        <div class="card-body">
                            <div class="row g-3 align-items-center">
                                <div class="col-md-2 text-center">
                                    <img id="studentPhoto" src="" class="rounded-circle border"
                                         style="width: 100px; height: 100px; object-fit: cover;"
                                         alt="Student Photo">
                                </div>

                                <div class="col-md-10">
                                    <h4 id="studentName" class="mb-2">-</h4>

                                    <div class="row g-2">
                                        <div class="col-md-4">
                                            <strong>Admission No:</strong>
                                            <span id="admNo">-</span>
                                        </div>
                                        <div class="col-md-4">
                                            <strong>Class:</strong>
                                            <span id="studentClass">-</span>
                                        </div>
                                        <div class="col-md-4">
                                            <strong>Stream:</strong>
                                            <span id="stream">-</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- IEP Details -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title">IEP Information</h5>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <p><strong>IEP Type:</strong> <span id="iepType">-</span></p>
                                    <p><strong>Category:</strong> <span id="iepCategory">-</span></p>
                                    <p><strong>Academic Year:</strong> <span id="academicYear">-</span></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Status:</strong> <span id="statusBadge">-</span></p>
                                    <p><strong>Created Date:</strong> <span id="createdDate">-</span></p>
                                    <p><strong>Approved Date:</strong> <span id="approvedDate">-</span></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Goals Summary -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title">Goals Summary</h5>
                            <p id="goalsSummary" class="p-3 bg-light rounded">-</p>
                        </div>
                    </div>

                    <!-- Strategies -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title">Strategies</h5>
                            <p id="strategies" class="p-3 bg-light rounded">-</p>
                        </div>
                    </div>

                    <!-- Accommodations -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title">Accommodations</h5>
                            <p id="accommodations" class="p-3 bg-light rounded">-</p>
                        </div>
                    </div>

                    <!-- Progress Monitoring -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title">Progress Monitoring Plan</h5>
                            <p id="progressMonitoring" class="p-3 bg-light rounded">-</p>
                        </div>
                    </div>

                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <button class="btn btn-info" id="printIepBtn">
                    <i class="bi bi-printer me-1"></i> Print IEP
                </button>
            </div>

        </div>
    </div>
</div>

<script src="<?php echo $appBase; ?>/js/pages/special_needs.js?v=<?php echo time(); ?>"></script>
