<?php
/**
 * New Applications Page
 * 
 * School Admin's primary entry point for receiving and tracking new applications.
 * Shows application queue with summary cards, filters, and table of applications.
 * Supports creating new applications and starting intake process.
 */
$appBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($appBase === '.')
    $appBase = '';
?>
<style>
    .new-applications-page {
        min-height: calc(100vh - 110px);
        padding: 1.5rem;
        background: linear-gradient(135deg, #f7fbf8 0%, #eef7f1 48%, #fff8e1 100%);
    }

    .new-applications-hero {
        border: 1px solid rgba(25, 135, 84, 0.18);
        border-radius: 1.25rem;
        background: linear-gradient(135deg, #198754 0%, #146c43 72%);
        color: #fff;
        box-shadow: 0 1rem 2.5rem rgba(20, 108, 67, 0.18);
    }

    .new-applications-hero .text-muted {
        color: rgba(255, 255, 255, 0.78) !important;
    }

    .new-applications-panel {
        border-radius: 1.25rem;
        background: rgba(255, 255, 255, 0.96);
        box-shadow: 0 0.75rem 2rem rgba(15, 81, 50, 0.08);
    }

    .new-applications-panel .card {
        border-color: rgba(25, 135, 84, 0.16);
    }

    .stat-card {
        border: none;
        border-radius: 1rem;
        background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
        box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.05);
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
    }

    .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }

    @media (max-width: 767.98px) {
        .new-applications-page {
            padding: 1rem;
        }
    }
</style>

<div class="new-applications-page">
    <!-- Hero Section -->
    <div class="new-applications-hero p-4 mb-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <p class="text-muted text-uppercase fw-semibold small mb-1">Admissions Workflow</p>
                <h4 class="mb-1">New Applications</h4>
                <p class="mb-0 text-muted">Receive and track new student applications</p>
            </div>
            <button type="button" class="btn btn-light btn-lg" id="newApplicationBtn">
                <i class="bi bi-plus-circle me-2"></i>New Application
            </button>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4" id="summaryCards">
        <div class="col-6 col-md-3">
            <div class="stat-card p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-file-earmark-text"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold" id="statTotalApplications">—</div>
                        <div class="text-muted small">Total Applications</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon bg-success bg-opacity-10 text-success">
                        <i class="bi bi-calendar-check"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold" id="statNewToday">—</div>
                        <div class="text-muted small">New Today</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold" id="statIntakePending">—</div>
                        <div class="text-muted small">Intake Pending</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon bg-info bg-opacity-10 text-info">
                        <i class="bi bi-clipboard-data"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold" id="statDocumentsPending">—</div>
                        <div class="text-muted small">Docs Pending</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Panel -->
    <div class="new-applications-panel p-3 p-lg-4">
        <!-- Filters -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Applicant Type</label>
                <select id="filterApplicantType" class="form-select">
                    <option value="">All Types</option>
                    <option value="new">New Student</option>
                    <option value="continuing">Continuing Student</option>
                    <option value="transfer">Transfer</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Class Applied For</label>
                <select id="filterClass" class="form-select">
                    <option value="">All Classes</option>
                    <option value="Playground">Playground</option>
                    <option value="PP1">PP1</option>
                    <option value="PP2">PP2</option>
                    <option value="Grade1">Grade 1</option>
                    <option value="Grade2">Grade 2</option>
                    <option value="Grade3">Grade 3</option>
                    <option value="Grade4">Grade 4</option>
                    <option value="Grade5">Grade 5</option>
                    <option value="Grade6">Grade 6</option>
                    <option value="Grade7">Grade 7</option>
                    <option value="Grade8">Grade 8</option>
                    <option value="Grade9">Grade 9</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Status</label>
                <select id="filterStatus" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="submitted">Submitted</option>
                    <option value="documents_pending">Documents Pending</option>
                    <option value="documents_verified">Documents Verified</option>
                    <option value="placement_offered">Placement Offered</option>
                    <option value="fees_pending">Fees Pending</option>
                    <option value="enrolled">Enrolled</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Search</label>
                <input type="text" id="searchApplications" class="form-control"
                    placeholder="Search by name or application no...">
            </div>
        </div>

        <!-- Applications Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Application No</th>
                                <th>Applicant Name</th>
                                <th>Gender</th>
                                <th>Class Applied For</th>
                                <th>Guardian Name</th>
                                <th>Guardian Phone</th>
                                <th>Status</th>
                                <th>Submitted Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="applicationsTableBody">
                            <tr>
                                <td colspan="9" class="text-center py-4">
                                    <div class="spinner-border text-success" role="status"></div>
                                    <div class="mt-2 text-muted">Loading applications...</div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- New Application Modal -->
<div class="modal fade" id="newApplicationModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>New Admission Application</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="newApplicationForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <!-- Tab Navigation -->
                    <ul class="nav nav-tabs mb-3" id="applicationTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="tab-applicant" data-bs-toggle="tab" data-bs-target="#content-applicant" type="button" role="tab">
                                <i class="bi bi-person me-1"></i> Applicant
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-academic" data-bs-toggle="tab" data-bs-target="#content-academic" type="button" role="tab">
                                <i class="bi bi-book me-1"></i> Academic
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-parent" data-bs-toggle="tab" data-bs-target="#content-parent" type="button" role="tab">
                                <i class="bi bi-people me-1"></i> Parent
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-health" data-bs-toggle="tab" data-bs-target="#content-health" type="button" role="tab">
                                <i class="bi bi-heart-pulse me-1"></i> Health
                            </button>
                        </li>
                    </ul>

                    <!-- Tab Content -->
                    <div class="tab-content">
                        <!-- Applicant Details Tab -->
                        <div class="tab-pane fade show active" id="content-applicant" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" name="applicant_name" class="form-control" required placeholder="Enter full name">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Date of Birth <span class="text-danger">*</span></label>
                                    <input type="date" name="date_of_birth" class="form-control" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Gender <span class="text-danger">*</span></label>
                                    <select name="gender" class="form-select" required>
                                        <option value="">Select</option>
                                        <option value="male">Male</option>
                                        <option value="female">Female</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Nationality</label>
                                    <input type="text" name="nationality" class="form-control" value="Kenyan">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Religion</label>
                                    <select name="religion" class="form-select">
                                        <option value="">Select</option>
                                        <option value="christian">Christian</option>
                                        <option value="muslim">Muslim</option>
                                        <option value="hindu">Hindu</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Place of Birth</label>
                                    <input type="text" name="place_of_birth" class="form-control" placeholder="Hospital/City">
                                </div>
                            </div>
                        </div>

                        <!-- Academic Information Tab -->
                        <div class="tab-pane fade" id="content-academic" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Grade Applying For <span class="text-danger">*</span></label>
                                    <select name="grade_applying_for" id="gradeSelect" class="form-select" required>
                                        <option value="">Select Grade</option>
                                        <option value="Playground">Playground</option>
                                        <option value="PP1">PP1</option>
                                        <option value="PP2">PP2</option>
                                        <option value="Grade1">Grade 1</option>
                                        <option value="Grade2">Grade 2</option>
                                        <option value="Grade3">Grade 3</option>
                                        <option value="Grade4">Grade 4</option>
                                        <option value="Grade5">Grade 5</option>
                                        <option value="Grade6">Grade 6</option>
                                        <option value="Grade7">Grade 7</option>
                                        <option value="Grade8">Grade 8</option>
                                        <option value="Grade9">Grade 9</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Academic Year <span class="text-danger">*</span></label>
                                    <select name="academic_year" id="academicYearSelect" class="form-select" required>
                                        <option value="">Select Year</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Application Source</label>
                                    <select name="application_source" class="form-select">
                                        <option value="physical">Physical / Front Office</option>
                                        <option value="online">Online</option>
                                        <option value="referral">Referral</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Intake Type</label>
                                    <select name="admission_category" class="form-select">
                                        <option value="standard">Standard Admission</option>
                                        <option value="nursery_term_1">Nursery Term 1 Intake</option>
                                        <option value="nursery_term_3">Nursery Term 3 Intake</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Previous School</label>
                                    <input type="text" name="previous_school" class="form-control" placeholder="Previous school name">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Previous Grade</label>
                                    <input type="text" name="previous_grade" class="form-control" placeholder="Last grade completed">
                                </div>
                            </div>
                        </div>

                        <!-- Parent/Guardian Tab -->
                        <div class="tab-pane fade" id="content-parent" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <label class="form-label fw-semibold">Parent/Guardian <span class="text-danger">*</span></label>
                                    <select name="parent_id" id="parentSelect" class="form-select" required>
                                        <option value="">Select Parent/Guardian</option>
                                    </select>
                                    <small class="text-muted">Parent must exist in the system. If not, please add parent first.</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Relationship</label>
                                    <select name="relationship" class="form-select">
                                        <option value="father">Father</option>
                                        <option value="mother">Mother</option>
                                        <option value="guardian">Guardian</option>
                                        <option value="grandparent">Grandparent</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Emergency Contact Phone</label>
                                    <input type="tel" name="emergency_phone" class="form-control" placeholder="Alternative emergency number">
                                </div>
                            </div>
                        </div>

                        <!-- Health & Special Needs Tab -->
                        <div class="tab-pane fade" id="content-health" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-12">
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" id="hasSpecialNeeds" name="has_special_needs" value="1">
                                        <label class="form-check-label fw-semibold" for="hasSpecialNeeds">
                                            Learner has special educational needs or medical conditions
                                        </label>
                                    </div>
                                </div>
                                <div class="col-12" id="specialNeedsDetailsGroup" style="display:none;">
                                    <label class="form-label fw-semibold">Special Needs Details</label>
                                    <textarea name="special_needs_details" class="form-control" rows="3" placeholder="Describe any special needs, allergies, or medical conditions"></textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold">Medical Conditions</label>
                                    <textarea name="medical_conditions" class="form-control" rows="2" placeholder="Any known medical conditions"></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Blood Group</label>
                                    <select name="blood_group" class="form-select">
                                        <option value="">Unknown</option>
                                        <option value="A+">A+</option>
                                        <option value="A-">A-</option>
                                        <option value="B+">B+</option>
                                        <option value="B-">B-</option>
                                        <option value="AB+">AB+</option>
                                        <option value="AB-">AB-</option>
                                        <option value="O+">O+</option>
                                        <option value="O-">O-</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Allergies</label>
                                    <input type="text" name="allergies" class="form-control" placeholder="Any known allergies">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-send me-1"></i>Submit Application
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Application Modal -->
<div class="modal fade" id="viewApplicationModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-person-badge me-2"></i>Application Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="viewApplicationContent">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success" id="startIntakeBtn">
                    <i class="bi bi-arrow-right me-1"></i>Start Intake
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    window.APP_BASE = window.APP_BASE || <?= json_encode($appBase) ?>;
    console.log("new_applications.php loaded. APP_BASE:", window.APP_BASE);
</script>

<script
    src="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/js/pages/new_applications.js?v=<?= time() ?>"
    onload="console.log('new_applications.js script tag loaded successfully')"
    onerror="console.error('FAILED to load new_applications.js. Check path:', this.src)">
</script>