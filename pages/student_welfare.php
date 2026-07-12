<?php
/**
 * Student Welfare Page
 * Chaplain / School Counselor dashboard for welfare cases and referrals
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

<div class="container-fluid py-4" id="studentWelfarePage">

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-warning text-dark">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h4 class="mb-0">
                        <i class="fas fa-hands-helping me-2"></i>
                        Student Welfare
                    </h4>
                    <small id="scopeSubtitle">Track student wellbeing, referrals, follow-ups, chapel support, and care interventions</small>
                </div>
                <div class="btn-group">
                    <button class="btn btn-light btn-sm" id="refreshBtn">
                        <i class="bi bi-arrow-clockwise"></i> Refresh
                    </button>
                    <button class="btn btn-outline-dark btn-sm" id="newCaseBtn">
                        <i class="bi bi-plus-circle me-1"></i> New Welfare Case
                    </button>
                    <button class="btn btn-outline-dark btn-sm" id="scheduleFollowUpBtn">
                        <i class="bi bi-calendar me-1"></i> Schedule Follow-up
                    </button>
                    <button class="btn btn-light btn-sm" id="exportBtn">
                        <i class="bi bi-download me-1"></i> Export Summary
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
                    <label class="form-label fw-semibold">Gender</label>
                    <select class="form-select" id="genderFilter">
                        <option value="">All</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                    </select>
                </div>

                <div class="col-xl-2 col-md-4">
                    <label class="form-label fw-semibold">Welfare Category</label>
                    <select class="form-select" id="categoryFilter">
                        <option value="">All Categories</option>
                        <option value="emotional">Emotional</option>
                        <option value="social">Social</option>
                        <option value="behavioral">Behavioral</option>
                        <option value="family">Family</option>
                        <option value="chapel">Chapel</option>
                        <option value="pastoral">Pastoral</option>
                        <option value="referral">Referral</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <div class="col-xl-2 col-md-4">
                    <label class="form-label fw-semibold">Referral Source</label>
                    <select class="form-select" id="referralSourceFilter">
                        <option value="">All Sources</option>
                        <option value="self">Self</option>
                        <option value="teacher">Teacher</option>
                        <option value="parent">Parent</option>
                        <option value="discipline">Discipline</option>
                        <option value="health">Health</option>
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
                    <label class="form-label fw-semibold">Assigned To</label>
                    <select class="form-select" id="assignedToFilter">
                        <option value="">All Staff</option>
                    </select>
                </div>

                <div class="col-xl-3 col-md-6">
                    <label class="form-label fw-semibold">Search</label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-search"></i>
                        </span>
                        <input type="text" class="form-control" id="searchBox"
                               placeholder="Search by student name, admission number, case title, referral source">
                    </div>
                </div>

                <div class="col-xl-2 col-md-4 d-flex align-items-end">
                    <button class="btn btn-warning w-100" id="applyFiltersBtn">
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
                                <div class="rounded-circle bg-warning text-dark p-3">
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
                                    <small class="text-muted">Active Cases</small>
                                    <h4 class="mb-0" id="activeCases">0</h4>
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
                                <div class="rounded-circle bg-info text-white p-3">
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
                                <div class="rounded-circle bg-secondary text-white p-3">
                                    <i class="fas fa-exchange-alt"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Referrals</small>
                                    <h4 class="mb-0" id="referralsCount">0</h4>
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
            </div>

            <!-- States -->
            <div id="casesLoading" class="alert alert-info d-none">
                <i class="fas fa-spinner fa-spin me-2"></i> Loading welfare cases...
            </div>

            <div id="casesError" class="alert alert-danger d-none"></div>

            <div id="casesForbidden" class="alert alert-warning d-none">
                <i class="fas fa-exclamation-triangle me-2"></i> You do not have permission to access welfare data.
            </div>

            <div id="casesEmpty" class="alert alert-warning d-none">
                <i class="fas fa-info-circle me-2"></i> No welfare cases found for the selected filters.
            </div>

            <!-- Main Table -->
            <div class="card border-0 shadow-sm" id="casesCard">
                <div class="card-header bg-white">
                    <strong>
                        <i class="fas fa-list me-2 text-warning"></i>
                        Welfare Cases
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
                                <th>Category</th>
                                <th>Referral Source</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Assigned To</th>
                                <th>Last Interaction</th>
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

<!-- Welfare Case Modal -->
<div class="modal fade" id="welfareCaseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content border-0 shadow">

            <div class="modal-header bg-warning text-dark">
                <div>
                    <h5 class="modal-title mb-0">
                        <i class="fas fa-hands-helping me-2"></i>
                        Welfare Case Details
                    </h5>
                    <small id="modalSubtitle">Case #<span id="modalCaseId"></span></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
                <button class="btn btn-info" id="addNoteBtn">
                    <i class="bi bi-plus-circle me-1"></i> Add Note
                </button>
                <button class="btn btn-primary" id="scheduleFollowUpModalBtn">
                    <i class="bi bi-calendar me-1"></i> Schedule Follow-up
                </button>
                <button class="btn btn-success" id="resolveCaseBtn">
                    <i class="bi bi-check-circle me-1"></i> Mark Resolved
                </button>
                <button class="btn btn-danger" id="escalateBtn">
                    <i class="bi bi-exclamation-triangle me-1"></i> Escalate
                </button>
            </div>

        </div>
    </div>
</div>

<!-- Add Note Modal -->
<div class="modal fade" id="addNoteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title mb-0">Add Note</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addNoteForm">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Note Type</label>
                        <select class="form-select" id="noteType">
                            <option value="general">General</option>
                            <option value="observation">Observation</option>
                            <option value="intervention">Intervention</option>
                            <option value="outcome">Outcome</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Note</label>
                        <textarea class="form-control" id="noteContent" rows="4" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Follow-up Date (optional)</label>
                        <input type="date" class="form-control" id="noteFollowUpDate">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-info" id="saveNoteBtn">
                    <i class="bi bi-check-circle me-1"></i> Save Note
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Schedule Follow-up Modal -->
<div class="modal fade" id="scheduleFollowUpModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title mb-0">Schedule Follow-up</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="followUpForm">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Follow-up Date</label>
                        <input type="date" class="form-control" id="followUpDate" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Note (optional)</label>
                        <textarea class="form-control" id="followUpNote" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" id="saveFollowUpBtn">
                    <i class="bi bi-check-circle me-1"></i> Schedule
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Resolve Case Modal -->
<div class="modal fade" id="resolveCaseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title mb-0">Mark Case as Resolved</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="resolveForm">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Resolution Note</label>
                        <textarea class="form-control" id="resolutionNote" rows="4" placeholder="Describe how the issue was resolved..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-success" id="confirmResolveBtn">
                    <i class="bi bi-check-circle me-1"></i> Mark Resolved
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Escalate Case Modal -->
<div class="modal fade" id="escalateCaseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title mb-0">Escalate Case</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="escalateForm">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Escalate To</label>
                        <select class="form-select" id="escalateTo">
                            <option value="">Select Staff</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Escalation Note</label>
                        <textarea class="form-control" id="escalationNote" rows="4" placeholder="Describe why this case needs escalation..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-danger" id="confirmEscalateBtn">
                    <i class="bi bi-exclamation-triangle me-1"></i> Escalate
                </button>
            </div>
        </div>
    </div>
</div>

<!-- New Welfare Case Modal -->
<div class="modal fade" id="newCaseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">

            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title mb-0">
                    <i class="fas fa-plus-circle me-2"></i>
                    New Welfare Case
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <form id="newCaseForm">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Student</label>
                        <select class="form-select" id="newCaseStudent" required>
                            <option value="">Select Student</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Case Title</label>
                        <input type="text" class="form-control" id="newCaseTitle" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Welfare Category</label>
                        <select class="form-select" id="newCaseCategory" required>
                            <option value="">Select Category</option>
                            <option value="emotional">Emotional</option>
                            <option value="social">Social</option>
                            <option value="behavioral">Behavioral</option>
                            <option value="family">Family</option>
                            <option value="chapel">Chapel</option>
                            <option value="pastoral">Pastoral</option>
                            <option value="referral">Referral</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Referral Source</label>
                        <select class="form-select" id="newCaseReferralSource">
                            <option value="">Select Source</option>
                            <option value="self">Self</option>
                            <option value="teacher">Teacher</option>
                            <option value="parent">Parent</option>
                            <option value="discipline">Discipline</option>
                            <option value="health">Health</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Priority</label>
                        <select class="form-select" id="newCasePriority" required>
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea class="form-control" id="newCaseDescription" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Assigned Staff</label>
                        <select class="form-select" id="newCaseAssignedTo">
                            <option value="">Select Staff</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Next Follow-up Date</label>
                        <input type="date" class="form-control" id="newCaseFollowUpDate">
                    </div>
                </form>
            </div>

            <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-warning" id="saveNewCaseBtn">
                    <i class="bi bi-check-circle me-1"></i> Save Case
                </button>
            </div>

        </div>
    </div>
</div>

<script src="<?php echo $appBase; ?>/js/pages/student_welfare.js?v=<?php echo time(); ?>"></script>
