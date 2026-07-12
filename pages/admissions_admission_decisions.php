<?php
/**
 * Admission Decisions Page
 * 
 * Headteacher decision-making interface for admission applications.
 * Shows applications that have completed interviews and await final admission decisions.
 */
?>
<style>
    .admission-decisions-page {
        min-height: calc(100vh - 110px);
        padding: 1.5rem;
        background: linear-gradient(135deg, #f7fbf8 0%, #eef7f1 48%, #fff8e1 100%);
    }

    .admission-decisions-hero {
        border: 1px solid rgba(25, 135, 84, 0.18);
        border-radius: 1.25rem;
        background: linear-gradient(135deg, #198754 0%, #146c43 72%);
        color: #fff;
        box-shadow: 0 1rem 2.5rem rgba(20, 108, 67, 0.18);
    }

    .admission-decisions-hero .text-muted {
        color: rgba(255, 255, 255, 0.78) !important;
    }

    .admission-decisions-panel {
        border-radius: 1.25rem;
        background: rgba(255, 255, 255, 0.96);
        box-shadow: 0 0.75rem 2rem rgba(15, 81, 50, 0.08);
    }

    .admission-decisions-panel .card {
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

    .decision-card {
        border-left: 4px solid #6c757d;
        transition: all 0.2s;
    }

    .decision-card:hover {
        transform: translateX(4px);
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
    }

    .decision-card.approved {
        border-left-color: #198754;
    }

    .decision-card.rejected {
        border-left-color: #dc3545;
    }

    .decision-card.waitlisted {
        border-left-color: #ffc107;
    }

    @media (max-width: 767.98px) {
        .admission-decisions-page {
            padding: 1rem;
        }
    }
</style>

<div class="admission-decisions-page">
    <!-- Hero Section -->
    <div class="admission-decisions-hero p-4 mb-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <p class="text-muted text-uppercase fw-semibold small mb-1">Admissions Workflow</p>
                <h4 class="mb-1">Admission Decisions</h4>
                <p class="mb-0 text-muted">Review applications and make admission decisions</p>
            </div>
            <button class="btn btn-light btn-lg" onclick="admissionDecisionsController.refreshData()">
                <i class="bi bi-arrow-clockwise me-2"></i>Refresh
            </button>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4" id="summaryCards">
        <div class="col-6 col-md-3">
            <div class="stat-card p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold" id="statPendingDecision">—</div>
                        <div class="text-muted small">Pending Decision</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon bg-success bg-opacity-10 text-success">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold" id="statApproved">—</div>
                        <div class="text-muted small">Approved</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                        <i class="bi bi-x-circle"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold" id="statRejected">—</div>
                        <div class="text-muted small">Rejected</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon bg-info bg-opacity-10 text-info">
                        <i class="bi bi-pause-circle"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold" id="statWaitlisted">—</div>
                        <div class="text-muted small">Waitlisted</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Panel -->
    <div class="admission-decisions-panel p-3 p-lg-4">
        <!-- Filters -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <label class="form-label small fw-semibold">Decision Status</label>
                <select id="filterDecisionStatus" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="pending">Pending Decision</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                    <option value="waitlisted">Waitlisted</option>
                </select>
            </div>
            <div class="col-md-4">
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
            <div class="col-md-4">
                <label class="form-label small fw-semibold">Search</label>
                <input type="text" id="searchApplications" class="form-control" placeholder="Search by name or application no...">
            </div>
        </div>

        <!-- Applications Grid -->
        <div id="applicationsGrid" class="row g-3">
            <div class="col-12 text-center py-4">
                <div class="spinner-border text-success" role="status"></div>
                <div class="mt-2 text-muted">Loading applications...</div>
            </div>
        </div>
    </div>
</div>

<!-- Make Decision Modal -->
<div class="modal fade" id="makeDecisionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-check-square me-2"></i>Make Admission Decision</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="makeDecisionForm">
                <div class="modal-body">
                    <input type="hidden" id="decisionApplicationId">
                    
                    <!-- Applicant Summary -->
                    <div class="alert alert-info" id="applicantSummary">
                        <div class="spinner-border spinner-border-sm me-2"></div>
                        Loading applicant details...
                    </div>
                    
                    <!-- Interview Results Summary -->
                    <div class="card mb-3" id="interviewResultsCard" style="display:none;">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="bi bi-clipboard-check me-2"></i>Interview Results</h6>
                        </div>
                        <div class="card-body" id="interviewResultsContent">
                        </div>
                    </div>
                    
                    <!-- Decision -->
                    <h6 class="fw-semibold mb-3">Admission Decision</h6>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Decision <span class="text-danger">*</span></label>
                            <select id="decision" class="form-select" required>
                                <option value="">Select Decision</option>
                                <option value="approved">Approve Admission</option>
                                <option value="rejected">Reject Admission</option>
                                <option value="waitlisted">Waitlist</option>
                                <option value="more_info_required">Request More Information</option>
                                <option value="placement_test_required">Require Placement Test</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Recommended Class</label>
                            <select id="recommendedClass" class="form-select">
                                <option value="">Same as Applied</option>
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
                    </div>
                    
                    <!-- Conditions -->
                    <div class="mb-3" id="conditionsGroup" style="display:none;">
                        <label class="form-label fw-semibold">Conditions</label>
                        <textarea id="decisionConditions" class="form-control" rows="2" placeholder="Any conditions for this decision..."></textarea>
                    </div>
                    
                    <!-- Remarks -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Decision Remarks</label>
                        <textarea id="decisionRemarks" class="form-control" rows="3" placeholder="Reasons for this decision..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check2-circle me-1"></i>Submit Decision
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
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="bi bi-person-badge me-2"></i>Application Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="viewApplicationContent">
                <div class="text-center py-4">
                    <div class="spinner-border text-info"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="makeDecisionBtn">
                    <i class="bi bi-check-square me-1"></i>Make Decision
                </button>
            </div>
        </div>
    </div>
</div>

<script src="js/pages/admissions_admission_decisions.js"></script>
<script>
function initWhenAPIReady() {
    if (typeof API !== 'undefined' && API.callAPI) {
        if (typeof admissionDecisionsController !== 'undefined' && admissionDecisionsController.init) {
            admissionDecisionsController.init();
        }
    } else {
        setTimeout(initWhenAPIReady, 100);
    }
}
document.addEventListener('DOMContentLoaded', initWhenAPIReady);
</script>
