<?php
/**
 * Headteacher Applications Page
 * 
 * Headteacher-focused view of applications that are ready for interview
 * or have been scheduled. Shows applications where HT needs to conduct
 * interviews or make admission decisions.
 */
?>
<style>
    .ht-applications-page {
        min-height: calc(100vh - 110px);
        padding: 1.5rem;
        background: linear-gradient(135deg, #f7fbf8 0%, #eef7f1 48%, #fff8e1 100%);
    }

    .ht-applications-hero {
        border: 1px solid rgba(13, 110, 253, 0.18);
        border-radius: 1.25rem;
        background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 72%);
        color: #fff;
        box-shadow: 0 1rem 2.5rem rgba(13, 110, 253, 0.18);
    }

    .ht-applications-hero .text-muted {
        color: rgba(255, 255, 255, 0.78) !important;
    }

    .ht-applications-panel {
        border-radius: 1.25rem;
        background: rgba(255, 255, 255, 0.96);
        box-shadow: 0 0.75rem 2rem rgba(13, 110, 253, 0.08);
    }

    .ht-applications-panel .card {
        border-color: rgba(13, 110, 253, 0.16);
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
        .ht-applications-page {
            padding: 1rem;
        }
    }
</style>

<div class="ht-applications-page">
    <!-- Hero Section -->
    <div class="ht-applications-hero p-4 mb-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <p class="text-muted text-uppercase fw-semibold small mb-1">Admissions Workflow</p>
                <h4 class="mb-1">Headteacher Applications</h4>
                <p class="mb-0 text-muted">Review applications and conduct admission interviews</p>
            </div>
            <button class="btn btn-light btn-lg" onclick="headteacherApplicationsController.refreshData()">
                <i class="bi bi-arrow-clockwise me-2"></i>Refresh
            </button>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4" id="summaryCards">
        <div class="col-6 col-md-3">
            <div class="stat-card p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-calendar-check"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold" id="statScheduledToday">—</div>
                        <div class="text-muted small">Scheduled Today</div>
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
                        <div class="fs-4 fw-bold" id="statPendingInterview">—</div>
                        <div class="text-muted small">Pending Interview</div>
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
                        <div class="fs-4 fw-bold" id="statCompletedThisWeek">—</div>
                        <div class="text-muted small">Completed This Week</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon bg-info bg-opacity-10 text-info">
                        <i class="bi bi-person-check"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold" id="statAwaitingDecision">—</div>
                        <div class="text-muted small">Awaiting Decision</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Panel -->
    <div class="ht-applications-panel p-3 p-lg-4">
        <!-- Filters -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <label class="form-label small fw-semibold">Interview Status</label>
                <select id="filterInterviewStatus" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="scheduled">Scheduled</option>
                    <option value="completed">Completed</option>
                    <option value="pending">Pending Scheduling</option>
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

        <!-- Applications Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Application No</th>
                                <th>Applicant Name</th>
                                <th>Grade</th>
                                <th>Interview Date</th>
                                <th>Interview Time</th>
                                <th>Status</th>
                                <th>Documents</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="applicationsTableBody">
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <div class="spinner-border text-primary" role="status"></div>
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

<!-- Conduct Interview Modal -->
<div class="modal fade" id="conductInterviewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-clipboard-check me-2"></i>Conduct Interview</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="conductInterviewForm">
                <div class="modal-body">
                    <input type="hidden" id="interviewApplicationId">
                    <input type="hidden" id="interviewId">
                    
                    <!-- Applicant Summary -->
                    <div class="alert alert-info" id="applicantSummary">
                        <div class="spinner-border spinner-border-sm me-2"></div>
                        Loading applicant details...
                    </div>
                    
                    <!-- Interview Scores -->
                    <h6 class="fw-semibold mb-3">Interview Assessment</h6>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Academic Readiness</label>
                            <input type="number" id="academicReadinessScore" class="form-control" min="0" max="100" placeholder="0-100">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Behavior/Social</label>
                            <input type="number" id="behaviorScore" class="form-control" min="0" max="100" placeholder="0-100">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Communication</label>
                            <input type="number" id="communicationScore" class="form-control" min="0" max="100" placeholder="0-100">
                        </div>
                    </div>
                    
                    <!-- Overall Recommendation -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Overall Recommendation</label>
                            <select id="recommendation" class="form-select" required>
                                <option value="">Select Recommendation</option>
                                <option value="recommended">Recommended</option>
                                <option value="not_recommended">Not Recommended</option>
                                <option value="conditional">Conditional</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Next Step</label>
                            <select id="nextStep" class="form-select" required>
                                <option value="">Select Next Step</option>
                                <option value="proceed_to_admission">Proceed to Admission</option>
                                <option value="waitlist">Waitlist</option>
                                <option value="decline">Decline</option>
                                <option value="placement_test_required">Require Placement Test</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Remarks -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Interview Remarks</label>
                        <textarea id="interviewRemarks" class="form-control" rows="3" placeholder="Key observations from the interview..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check2-circle me-1"></i>Submit Interview Results
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
                <button type="button" class="btn btn-success" id="conductInterviewBtn">
                    <i class="bi bi-clipboard-check me-1"></i>Conduct Interview
                </button>
            </div>
        </div>
    </div>
</div>

<script src="js/pages/admissions_headteacher_applications.js"></script>
<script>
function initWhenAPIReady() {
    if (typeof API !== 'undefined' && API.callAPI) {
        if (typeof headteacherApplicationsController !== 'undefined' && headteacherApplicationsController.init) {
            headteacherApplicationsController.init();
        }
    } else {
        setTimeout(initWhenAPIReady, 100);
    }
}
document.addEventListener('DOMContentLoaded', initWhenAPIReady);
</script>
