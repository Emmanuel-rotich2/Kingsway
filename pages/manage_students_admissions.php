<?php
/**
 * Manage Student Admissions Page - Tabbed Workspace Interface
 *
 * Unified tabbed interface for managing the complete admissions workflow.
 * Loads role-specific content based on permissions but presents a cohesive workspace.
 */
$appBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($appBase === '.')
    $appBase = '';
?>

<style>
    .admissions-page-shell {
        min-height: calc(100vh - 110px);
        padding: 1.5rem;
        background:
            radial-gradient(circle at top left, rgba(255, 193, 7, 0.16), transparent 32rem),
            linear-gradient(135deg, #f7fbf8 0%, #eef7f1 48%, #fff8e1 100%);
    }

    .admissions-page-hero {
        border: 1px solid rgba(25, 135, 84, 0.18);
        border-radius: 1.25rem;
        background: linear-gradient(135deg, #198754 0%, #146c43 72%);
        color: #fff;
        box-shadow: 0 1rem 2.5rem rgba(20, 108, 67, 0.18);
    }

    .admissions-page-hero .text-muted {
        color: rgba(255, 255, 255, 0.78) !important;
    }

    .admissions-page-panel {
        border-radius: 1.25rem;
        background: rgba(255, 255, 255, 0.96);
        box-shadow: 0 0.75rem 2rem rgba(15, 81, 50, 0.08);
    }

    .admissions-page-panel .card {
        border-color: rgba(25, 135, 84, 0.16);
    }

    .admissions-tabs {
        border-bottom: 2px solid rgba(25, 135, 84, 0.1);
        margin-bottom: 1.5rem;
    }

    .admissions-tabs .nav-link {
        border: none;
        border-bottom: 3px solid transparent;
        color: #6c757d;
        font-weight: 500;
        padding: 0.75rem 1.25rem;
        transition: all 0.2s;
    }

    .admissions-tabs .nav-link:hover {
        color: #198754;
        background: rgba(25, 135, 84, 0.05);
    }

    .admissions-tabs .nav-link.active {
        color: #198754;
        border-bottom-color: #198754;
        background: rgba(25, 135, 84, 0.05);
    }

    .admissions-tabs .nav-link .badge {
        font-size: 0.7em;
        margin-left: 0.5rem;
    }

    .tab-pane {
        animation: fadeIn 0.3s ease-in-out;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
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
        .admissions-page-shell {
            padding: 1rem;
        }

        .admissions-tabs .nav-link {
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
        }
    }
</style>

<div class="admissions-page-shell">
    <div class="admissions-page-hero p-4 mb-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <p class="text-muted text-uppercase fw-semibold small mb-1">Admissions Workflow</p>
                <h4 class="mb-1">Student Admissions</h4>
                <p class="mb-0 text-muted">Complete admissions management workspace</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-light" onclick="admissionsWorkspaceController.refreshAll()">
                    <i class="bi bi-arrow-clockwise me-2"></i>Refresh
                </button>
                <button class="btn btn-light" onclick="admissionsWorkspaceController.newApplication()">
                    <i class="bi bi-plus-circle me-2"></i>New Application
                </button>
            </div>
        </div>
    </div>

    <div class="admissions-page-panel p-3 p-lg-4">
        <!-- Tab Navigation -->
        <ul class="nav nav-tabs admissions-tabs" id="admissionsTabs">
            <li class="nav-item">
                <button class="nav-link active" data-tab="applications" onclick="admissionsWorkspaceController.switchTab('applications')">
                    <i class="bi bi-file-earmark-text me-2"></i>Applications
                    <span class="badge bg-secondary" id="tabBadgeApplications">0</span>
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-tab="documents" onclick="admissionsWorkspaceController.switchTab('documents')">
                    <i class="bi bi-file-earmark-check me-2"></i>Documents
                    <span class="badge bg-warning" id="tabBadgeDocuments">0</span>
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-tab="interviews" onclick="admissionsWorkspaceController.switchTab('interviews')">
                    <i class="bi bi-calendar-check me-2"></i>Interviews
                    <span class="badge bg-info" id="tabBadgeInterviews">0</span>
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-tab="decisions" onclick="admissionsWorkspaceController.switchTab('decisions')">
                    <i class="bi bi-check-square me-2"></i>Decisions
                    <span class="badge bg-primary" id="tabBadgeDecisions">0</span>
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-tab="placements" onclick="admissionsWorkspaceController.switchTab('placements')">
                    <i class="bi bi-award me-2"></i>Placements
                    <span class="badge bg-success" id="tabBadgePlacements">0</span>
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-tab="enrollment" onclick="admissionsWorkspaceController.switchTab('enrollment')">
                    <i class="bi bi-person-check me-2"></i>Enrollment
                    <span class="badge bg-danger" id="tabBadgeEnrollment">0</span>
                </button>
            </li>
        </ul>

        <!-- Summary Cards Row -->
        <div class="row g-3 mb-4" id="summaryCards">
            <div class="col-6 col-md-2">
                <div class="stat-card p-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                            <i class="bi bi-file-earmark-text"></i>
                        </div>
                        <div>
                            <div class="fs-5 fw-bold" id="statTotal">—</div>
                            <div class="text-muted small">Total</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="stat-card p-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                            <i class="bi bi-hourglass-split"></i>
                        </div>
                        <div>
                            <div class="fs-5 fw-bold" id="statPending">—</div>
                            <div class="text-muted small">Pending</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="stat-card p-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="stat-icon bg-info bg-opacity-10 text-info">
                            <i class="bi bi-clock-history"></i>
                        </div>
                        <div>
                            <div class="fs-5 fw-bold" id="statInReview">—</div>
                            <div class="text-muted small">In Review</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="stat-card p-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="stat-icon bg-success bg-opacity-10 text-success">
                            <i class="bi bi-check-circle"></i>
                        </div>
                        <div>
                            <div class="fs-5 fw-bold" id="statApproved">—</div>
                            <div class="text-muted small">Approved</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="stat-card p-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                            <i class="bi bi-x-circle"></i>
                        </div>
                        <div>
                            <div class="fs-5 fw-bold" id="statRejected">—</div>
                            <div class="text-muted small">Rejected</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="stat-card p-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="stat-icon bg-secondary bg-opacity-10 text-secondary">
                            <i class="bi bi-people"></i>
                        </div>
                        <div>
                            <div class="fs-5 fw-bold" id="statEnrolled">—</div>
                            <div class="text-muted small">Enrolled</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab Content -->
        <div id="admissionsTabContent">
            <!-- Applications Tab -->
            <div id="tab-applications" class="tab-pane">
                <div id="applications-loading" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status"></div>
                    <div class="mt-2 text-muted">Loading applications...</div>
                </div>
                <div id="applications-content" style="display:none;"></div>
            </div>

            <!-- Documents Tab -->
            <div id="tab-documents" class="tab-pane" style="display:none;">
                <div id="documents-loading" class="text-center py-4">
                    <div class="spinner-border text-warning" role="status"></div>
                    <div class="mt-2 text-muted">Loading documents...</div>
                </div>
                <div id="documents-content" style="display:none;"></div>
            </div>

            <!-- Interviews Tab -->
            <div id="tab-interviews" class="tab-pane" style="display:none;">
                <div id="interviews-loading" class="text-center py-4">
                    <div class="spinner-border text-info" role="status"></div>
                    <div class="mt-2 text-muted">Loading interviews...</div>
                </div>
                <div id="interviews-content" style="display:none;"></div>
            </div>

            <!-- Decisions Tab -->
            <div id="tab-decisions" class="tab-pane" style="display:none;">
                <div id="decisions-loading" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status"></div>
                    <div class="mt-2 text-muted">Loading decisions...</div>
                </div>
                <div id="decisions-content" style="display:none;"></div>
            </div>

            <!-- Placements Tab -->
            <div id="tab-placements" class="tab-pane" style="display:none;">
                <div id="placements-loading" class="text-center py-4">
                    <div class="spinner-border text-success" role="status"></div>
                    <div class="mt-2 text-muted">Loading placements...</div>
                </div>
                <div id="placements-content" style="display:none;"></div>
            </div>

            <!-- Enrollment Tab -->
            <div id="tab-enrollment" class="tab-pane" style="display:none;">
                <div id="enrollment-loading" class="text-center py-4">
                    <div class="spinner-border text-danger" role="status"></div>
                    <div class="mt-2 text-muted">Loading enrollment...</div>
                </div>
                <div id="enrollment-content" style="display:none;"></div>
            </div>
        </div>
    </div>
</div>

<!-- Application Details Modal -->
<div class="modal fade" id="admissionsWorkspaceApplicationModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-person-badge me-2"></i>Application Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="admissionsWorkspaceApplicationContent">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    window.APP_BASE = window.APP_BASE || <?= json_encode($appBase) ?>;
</script>

<script
    src="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/js/pages/admissions_workspace.js?v=<?= time() ?>"
    onload="console.log('admissions_workspace.js script tag loaded successfully')"
    onerror="console.error('FAILED to load admissions_workspace.js. Check path:', this.src)">
</script>
