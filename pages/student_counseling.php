<?php
/**
 * Student Counseling Page
 * Track counseling cases, welfare concerns, interventions, and follow-ups
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

<div class="container-fluid py-4" id="studentCounselingPage">

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-info text-white">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h4 class="mb-0">
                        <i class="fas fa-hands-helping me-2"></i>
                        Student Counseling
                    </h4>
                    <small id="scopeSubtitle">Track counseling cases, welfare concerns, interventions, and follow-ups</small>
                </div>
                <div class="btn-group">
                    <button class="btn btn-light btn-sm" id="refreshBtn">
                        <i class="bi bi-arrow-clockwise"></i> Refresh
                    </button>
                    <button class="btn btn-outline-light btn-sm" id="exportBtn">
                        <i class="bi bi-download"></i> Export
                    </button>
                    <button class="btn btn-light btn-sm" id="addCaseBtn">
                        <i class="bi bi-plus-circle"></i> Add Case
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
                    <label class="form-label fw-semibold">Term</label>
                    <select class="form-select" id="termFilter">
                        <option value="">All Terms</option>
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
                    <label class="form-label fw-semibold">Case Type</label>
                    <select class="form-select" id="caseTypeFilter">
                        <option value="">All Types</option>
                        <option value="academic">Academic</option>
                        <option value="behavioral">Behavioral</option>
                        <option value="personal">Personal</option>
                        <option value="family">Family</option>
                        <option value="career">Career</option>
                        <option value="disciplinary">Disciplinary</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <div class="col-xl-2 col-md-4">
                    <label class="form-label fw-semibold">Priority</label>
                    <select class="form-select" id="priorityFilter">
                        <option value="">All</option>
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>

                <div class="col-xl-2 col-md-4">
                    <label class="form-label fw-semibold">Status</label>
                    <select class="form-select" id="statusFilter">
                        <option value="">All Status</option>
                        <option value="open">Open</option>
                        <option value="in_progress">In Progress</option>
                        <option value="resolved">Resolved</option>
                        <option value="closed">Closed</option>
                    </select>
                </div>

                <div class="col-xl-2 col-md-4">
                    <label class="form-label fw-semibold">Gender</label>
                    <select class="form-select" id="genderFilter">
                        <option value="">All</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                    </select>
                </div>

                <div class="col-xl-4 col-md-8">
                    <label class="form-label fw-semibold">Search</label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-search"></i>
                        </span>
                        <input type="text" class="form-control" id="searchBox"
                               placeholder="Search by student name, admission number, counselor, or case title">
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
                                    <i class="fas fa-folder-open"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Total Cases</small>
                                    <h4 class="mb-0" id="totalCases">0</h4>
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
                                    <i class="fas fa-folder-open"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Open Cases</small>
                                    <h4 class="mb-0" id="openCases">0</h4>
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
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Follow-ups Due</small>
                                    <h4 class="mb-0" id="followUpsDue">0</h4>
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
                                    <small class="text-muted">Resolved</small>
                                    <h4 class="mb-0" id="resolvedCases">0</h4>
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
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                                <div>
                                    <small class="text-muted">High Priority</small>
                                    <h4 class="mb-0" id="highPriorityCases">0</h4>
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
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                                <div>
                                    <small class="text-muted">This Term</small>
                                    <h4 class="mb-0" id="thisTermCases">0</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- States -->
            <div id="casesLoading" class="alert alert-info d-none">
                <i class="fas fa-spinner fa-spin me-2"></i> Loading counseling cases...
            </div>

            <div id="casesError" class="alert alert-danger d-none"></div>

            <div id="casesEmpty" class="alert alert-warning d-none">
                <i class="fas fa-info-circle me-2"></i> No counseling cases found for the selected filters.
            </div>

            <!-- Main Table -->
            <div class="card border-0 shadow-sm" id="casesCard">
                <div class="card-header bg-white">
                    <strong>
                        <i class="fas fa-list me-2 text-info"></i>
                        Counseling Cases
                    </strong>
                </div>

                <div class="card-body table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Case ID</th>
                                <th>Student Name</th>
                                <th>Adm No</th>
                                <th>Class</th>
                                <th>Stream</th>
                                <th>Case Type</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Counselor</th>
                                <th>Last Session</th>
                                <th>Next Follow-up</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="casesTableBody">
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

<!-- Case Details Modal -->
<div class="modal fade" id="caseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content border-0 shadow">

            <div class="modal-header bg-info text-white">
                <div>
                    <h5 class="modal-title mb-0">
                        <i class="fas fa-hands-helping me-2"></i>
                        Counseling Case Details
                    </h5>
                    <small id="modalSubtitle">Case #<span id="modalCaseId"></span></small>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div id="modalLoading" class="alert alert-info d-none">
                    <i class="fas fa-spinner fa-spin me-2"></i> Loading case details...
                </div>

                <div id="modalError" class="alert alert-danger d-none"></div>

                <div id="modalCaseContent">
                    <!-- Case details will be rendered here -->
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <button class="btn btn-success" id="addSessionBtn">
                    <i class="bi bi-plus-circle me-1"></i> Add Session Note
                </button>
                <button class="btn btn-warning" id="scheduleFollowUpBtn">
                    <i class="bi bi-calendar me-1"></i> Schedule Follow-up
                </button>
                <button class="btn btn-danger" id="closeCaseBtn">
                    <i class="bi bi-x-circle me-1"></i> Close Case
                </button>
            </div>

        </div>
    </div>
</div>

<script src="<?php echo $appBase; ?>/js/pages/student_counseling.js?v=<?php echo time(); ?>"></script>
