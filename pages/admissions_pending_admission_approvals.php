<?php
/**
 * Pending Admission Approvals Page
 * 
 * Headteacher final approval workflow for applications that have completed
 * placement and fee payment, ready for final admission approval.
 */
?>
<style>
    .pending-approvals-page {
        min-height: calc(100vh - 110px);
        padding: 1.5rem;
        background: linear-gradient(135deg, #f7fbf8 0%, #eef7f1 48%, #fff8e1 100%);
    }

    .pending-approvals-hero {
        border: 1px solid rgba(220, 53, 69, 0.18);
        border-radius: 1.25rem;
        background: linear-gradient(135deg, #dc3545 0%, #b02a37 72%);
        color: #fff;
        box-shadow: 0 1rem 2.5rem rgba(176, 42, 55, 0.18);
    }

    .pending-approvals-hero .text-muted {
        color: rgba(255, 255, 255, 0.78) !important;
    }

    .pending-approvals-panel {
        border-radius: 1.25rem;
        background: rgba(255, 255, 255, 0.96);
        box-shadow: 0 0.75rem 2rem rgba(220, 53, 69, 0.08);
    }

    .pending-approvals-panel .card {
        border-color: rgba(220, 53, 69, 0.16);
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

    .approval-card {
        border-left: 4px solid #6c757d;
        transition: all 0.2s;
    }

    .approval-card:hover {
        transform: translateX(4px);
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
    }

    .approval-card.ready {
        border-left-color: #198754;
    }

    .approval-card.review {
        border-left-color: #ffc107;
    }

    @media (max-width: 767.98px) {
        .pending-approvals-page {
            padding: 1rem;
        }
    }
</style>

<div class="pending-approvals-page">
    <!-- Hero Section -->
    <div class="pending-approvals-hero p-4 mb-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <p class="text-muted text-uppercase fw-semibold small mb-1">Admissions Workflow</p>
                <h4 class="mb-1">Pending Admission Approvals</h4>
                <p class="mb-0 text-muted">Final approval queue for placement and fee-paid applications</p>
            </div>
            <button class="btn btn-light btn-lg" onclick="pendingApprovalsController.refreshData()">
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
                        <div class="fs-4 fw-bold" id="statPendingApproval">—</div>
                        <div class="text-muted small">Pending Approval</div>
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
                        <div class="fs-4 fw-bold" id="statApprovedToday">—</div>
                        <div class="text-muted small">Approved Today</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon bg-info bg-opacity-10 text-info">
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold" id="statAvgProcessingTime">—</div>
                        <div class="text-muted small">Avg Processing</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-graph-up"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold" id="statApprovalRate">—</div>
                        <div class="text-muted small">Approval Rate</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Panel -->
    <div class="pending-approvals-panel p-3 p-lg-4">
        <!-- Filters -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <label class="form-label small fw-semibold">Approval Status</label>
                <select id="filterApprovalStatus" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="ready">Ready for Approval</option>
                    <option value="under_review">Under Review</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
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
        <div id="approvalsGrid" class="row g-3">
            <div class="col-12 text-center py-4">
                <div class="spinner-border text-danger" role="status"></div>
                <div class="mt-2 text-muted">Loading pending approvals...</div>
            </div>
        </div>
    </div>
</div>

<!-- Final Approval Modal -->
<div class="modal fade" id="finalApprovalModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-check-circle me-2"></i>Final Admission Approval</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="finalApprovalForm">
                <div class="modal-body">
                    <input type="hidden" id="finalApprovalApplicationId">
                    
                    <!-- Applicant Summary -->
                    <div class="alert alert-info" id="approvalApplicantSummary">
                        <div class="spinner-border spinner-border-sm me-2"></div>
                        Loading applicant details...
                    </div>
                    
                    <!-- Readiness Checklist -->
                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="bi bi-check-list me-2"></i>Admission Readiness Checklist</h6>
                        </div>
                        <div class="card-body" id="readinessChecklist">
                            <div class="spinner-border spinner-border-sm me-2"></div>
                            Loading readiness status...
                        </div>
                    </div>
                    
                    <!-- Approval Decision -->
                    <h6 class="fw-semibold mb-3">Final Approval Decision</h6>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Decision <span class="text-danger">*</span></label>
                            <select id="finalDecision" class="form-select" required>
                                <option value="">Select Decision</option>
                                <option value="approve">Approve Admission</option>
                                <option value="reject">Reject Admission</option>
                                <option value="request_info">Request More Information</option>
                                <option value="conditional">Conditional Approval</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Enrollment Start Date</label>
                            <input type="date" id="enrollmentStartDate" class="form-select">
                        </div>
                    </div>
                    
                    <!-- Conditions -->
                    <div class="mb-3" id="conditionsGroup" style="display:none;">
                        <label class="form-label fw-semibold">Approval Conditions</label>
                        <textarea id="approvalConditions" class="form-control" rows="2" placeholder="Any conditions for this approval..."></textarea>
                    </div>
                    
                    <!-- Remarks -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Approval Remarks</label>
                        <textarea id="approvalRemarks" class="form-control" rows="3" placeholder="Reasons for this decision..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check2-circle me-1"></i>Submit Approval
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
                <button type="button" class="btn btn-success" id="finalApprovalBtn">
                    <i class="bi bi-check-circle me-1"></i>Approve
                </button>
            </div>
        </div>
    </div>
</div>

<script src="js/pages/admissions_pending_admission_approvals.js"></script>
<script>
function initWhenAPIReady() {
    if (typeof API !== 'undefined' && API.callAPI) {
        if (typeof pendingApprovalsController !== 'undefined' && pendingApprovalsController.init) {
            pendingApprovalsController.init();
        }
    } else {
        setTimeout(initWhenAPIReady, 100);
    }
}
document.addEventListener('DOMContentLoaded', initWhenAPIReady);
</script>
