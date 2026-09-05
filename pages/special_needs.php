<?php
/**
 * Special Needs Page
 * Display and manage students with special educational needs and IEPs
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

<div class="container-fluid py-4" id="specialNeedsPage">

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-info text-white">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h4 class="mb-0">
                        <i class="bi bi-heart me-2"></i>
                        Special Needs & Student Support
                    </h4>
                    <small id="scopeSubtitle">Track and manage students with special educational needs and IEPs</small>
                </div>
                <div class="btn-group">
                    <button class="btn btn-light btn-sm" id="exportRecordsBtn" data-permission="students_export">
                        <i class="bi bi-download"></i> Export
                    </button>
                    <button class="btn btn-outline-light btn-sm" id="printRecordsBtn">
                        <i class="bi bi-printer"></i> Print
                    </button>
                    <button class="btn btn-light btn-sm" id="addRecordBtn" data-permission="students_create">
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
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" class="form-control" id="searchBox"
                               placeholder="Search by student name, admission number, or IEP type">
                    </div>
                </div>

                <div class="col-xl-2 col-md-4 d-flex align-items-end">
                    <button class="btn btn-info w-100" id="applyFiltersBtn">
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
                                <div class="rounded-circle bg-info text-white p-3">
                                    <i class="bi bi-people"></i>
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
                                    <i class="bi bi-check-lg-circle"></i>
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
                                    <i class="bi bi-pencil"></i>
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
                                    <i class="bi bi-mortarboard"></i>
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
                                    <i class="bi bi-heartbeat"></i>
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
                                    <i class="bi bi-archive"></i>
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
                <i class="bi bi-arrow-clockwise fa-spin me-2"></i> Loading special needs records...
            </div>

            <div id="iepsError" class="alert alert-danger d-none"></div>

            <div id="iepsEmpty" class="alert alert-warning d-none">
                <i class="bi bi-info-circle me-2"></i> No special needs records found for the selected filters.
            </div>

            <div id="iepsForbidden" class="alert alert-warning d-none">
                <i class="bi bi-lock me-2"></i> You do not have permission to view special needs records.
            </div>

            <!-- Main Table -->
            <div class="card border-0 shadow-sm" id="iepsCard">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <strong>
                        <i class="bi bi-list-ul me-2 text-info"></i>
                        Individualized Education Programs (IEPs)
                    </strong>
                </div>

                <div class="card-body table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">IEP ID</th>
                                <th scope="col">Student</th>
                                <th scope="col">Adm No</th>
                                <th scope="col">Class</th>
                                <th scope="col">Stream</th>
                                <th scope="col">Dormitory</th>
                                <th scope="col">IEP Type</th>
                                <th scope="col">Category</th>
                                <th scope="col">Status</th>
                                <th scope="col">Academic Year</th>
                                <th scope="col">Created Date</th>
                                <th scope="col">Actions</th>
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
                        <i class="bi bi-file-medical me-2"></i>
                        IEP Details
                    </h5>
                    <small id="modalIepSubtitle">IEP #<span id="modalIepId"></span></small>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                <div id="modalLoading" class="alert alert-info d-none">
                    <i class="bi bi-arrow-clockwise fa-spin me-2"></i> Loading IEP details...
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

<!-- Add IEP Modal -->
<div class="modal fade" id="addIepModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">

            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">
                    <i class="bi bi-file-medical me-2"></i> New IEP Record
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <form id="addIepForm" novalidate>

                    <!-- Student Search -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Student <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="addIepStudentSearch"
                               placeholder="Type name or admission number to search..." autocomplete="off">
                        <input type="hidden" id="addIepStudentId">
                        <div id="addIepStudentResults" class="list-group mt-1 d-none"></div>
                        <small id="addIepStudentSelected" class="text-success d-none">
                            <i class="bi bi-check-circle me-1"></i><span></span>
                        </small>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Academic Year <span class="text-danger">*</span></label>
                            <select class="form-select" id="addIepAcademicYear">
                                <option value="">-- Select --</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">IEP Type</label>
                            <select class="form-select" id="addIepType">
                                <option value="">-- Select --</option>
                                <option value="learning">Learning</option>
                                <option value="behavioral">Behavioral</option>
                                <option value="physical">Physical</option>
                                <option value="medical">Medical</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Category</label>
                            <input type="text" class="form-control" id="addIepCategory"
                                   placeholder="e.g. Dyslexia, ADHD, Visual impairment">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Status</label>
                            <select class="form-select" id="addIepStatus">
                                <option value="draft" selected>Draft</option>
                                <option value="active">Active</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-3">
                        <label class="form-label fw-semibold">Goals Summary <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="addIepGoals" rows="3" required
                                  placeholder="Describe the goals for this learner..."></textarea>
                    </div>

                    <div class="mt-3">
                        <label class="form-label fw-semibold">Strategies</label>
                        <textarea class="form-control" id="addIepStrategies" rows="3"
                                  placeholder="Teaching/Intervention strategies..."></textarea>
                    </div>

                    <div class="mt-3">
                        <label class="form-label fw-semibold">Accommodations</label>
                        <textarea class="form-control" id="addIepAccommodations" rows="3"
                                  placeholder="Accommodations provided..."></textarea>
                    </div>

                    <div class="mt-3">
                        <label class="form-label fw-semibold">Progress Monitoring Plan</label>
                        <textarea class="form-control" id="addIepProgress" rows="3"
                                  placeholder="How progress will be monitored..."></textarea>
                    </div>

                </form>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-info" id="addIepSaveBtn">
                    <i class="bi bi-check-lg me-1"></i> Create IEP
                </button>
            </div>

        </div>
    </div>
</div>

<script src="<?php echo $appBase; ?>/js/pages/special_needs.js?v=<?php echo time(); ?>"></script>
