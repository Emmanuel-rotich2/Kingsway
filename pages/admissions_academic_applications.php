<?php
/**
 * Academic Applications Page
 * 
 * Deputy Academic-focused view for class placement and academic readiness assessment.
 * Shows applications that have been approved by Headteacher and need class placement.
 */
?>
<style>
    .academic-applications-page {
        min-height: calc(100vh - 110px);
        padding: 1.5rem;
        background: linear-gradient(135deg, #f7fbf8 0%, #eef7f1 48%, #fff8e1 100%);
    }

    .academic-applications-hero {
        border: 1px solid rgba(255, 193, 7, 0.18);
        border-radius: 1.25rem;
        background: linear-gradient(135deg, #ffc107 0%, #e0a800 72%);
        color: #fff;
        box-shadow: 0 1rem 2.5rem rgba(224, 168, 0, 0.18);
    }

    .academic-applications-hero .text-muted {
        color: rgba(255, 255, 255, 0.78) !important;
    }

    .academic-applications-panel {
        border-radius: 1.25rem;
        background: rgba(255, 255, 255, 0.96);
        box-shadow: 0 0.75rem 2rem rgba(224, 168, 0, 0.08);
    }

    .academic-applications-panel .card {
        border-color: rgba(255, 193, 7, 0.16);
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

    .capacity-bar {
        height: 8px;
        border-radius: 4px;
        background: #e9ecef;
        overflow: hidden;
    }

    .capacity-fill {
        height: 100%;
        border-radius: 4px;
        transition: width 0.3s ease;
    }

    @media (max-width: 767.98px) {
        .academic-applications-page {
            padding: 1rem;
        }
    }
</style>

<div class="academic-applications-page">
    <!-- Hero Section -->
    <div class="academic-applications-hero p-4 mb-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <p class="text-muted text-uppercase fw-semibold small mb-1">Admissions Workflow</p>
                <h4 class="mb-1">Academic Applications</h4>
                <p class="mb-0 text-muted">Review approved applications and assign class placements</p>
            </div>
            <button class="btn btn-light btn-lg" onclick="academicApplicationsController.refreshData()">
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
                        <div class="fs-4 fw-bold" id="statPendingPlacement">—</div>
                        <div class="text-muted small">Pending Placement</div>
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
                        <div class="fs-4 fw-bold" id="statPlaced">—</div>
                        <div class="text-muted small">Placed</div>
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
                        <div class="fs-4 fw-bold" id="statPlacementTests">—</div>
                        <div class="text-muted small">Tests Required</div>
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
                        <div class="fs-4 fw-bold" id="statCapacity">—</div>
                        <div class="text-muted small">Avg Capacity</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Panel -->
    <div class="academic-applications-panel p-3 p-lg-4">
        <!-- Filters -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <label class="form-label small fw-semibold">Placement Status</label>
                <select id="filterPlacementStatus" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="pending">Pending Placement</option>
                    <option value="recommended">Recommended</option>
                    <option value="approved">Approved</option>
                    <option value="assigned">Assigned</option>
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
                                <th>Applied Grade</th>
                                <th>Interview Score</th>
                                <th>Placement Status</th>
                                <th>Recommended Class</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="applicationsTableBody">
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <div class="spinner-border text-warning" role="status"></div>
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

<!-- Class Placement Modal -->
<div class="modal fade" id="classPlacementModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="bi bi-award me-2"></i>Class Placement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="classPlacementForm">
                <div class="modal-body">
                    <input type="hidden" id="placementApplicationId">
                    
                    <!-- Applicant Summary -->
                    <div class="alert alert-info" id="applicantSummary">
                        <div class="spinner-border spinner-border-sm me-2"></div>
                        Loading applicant details...
                    </div>
                    
                    <!-- Academic Background -->
                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="bi bi-book me-2"></i>Academic Background</h6>
                        </div>
                        <div class="card-body" id="academicBackground">
                        </div>
                    </div>
                    
                    <!-- Placement Test Results -->
                    <div class="card mb-3" id="placementTestCard" style="display:none;">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="bi bi-clipboard-check me-2"></i>Placement Test Results</h6>
                        </div>
                        <div class="card-body" id="placementTestResults">
                        </div>
                    </div>
                    
                    <!-- Class Selection -->
                    <h6 class="fw-semibold mb-3">Class Placement</h6>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Recommended Class <span class="text-danger">*</span></label>
                            <select id="recommendedClass" class="form-select" required>
                                <option value="">Select Class</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Stream</label>
                            <select id="recommendedStream" class="form-select">
                                <option value="">No Stream</option>
                                <option value="A">Stream A</option>
                                <option value="B">Stream B</option>
                                <option value="C">Stream C</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Class Capacity Display -->
                    <div class="card mb-3" id="classCapacityCard" style="display:none;">
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2">
                                <small class="text-muted">Class Capacity:</small>
                                <small id="classCapacityText">—</small>
                            </div>
                            <div class="capacity-bar">
                                <div class="capacity-fill bg-warning" id="classCapacityFill" style="width: 0%"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Placement Type -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Placement Type</label>
                        <select id="placementType" class="form-select">
                            <option value="automatic">Automatic (Based on Applied Grade)</option>
                            <option value="test_based">Based on Placement Test</option>
                            <option value="interview_based">Based on Interview Results</option>
                        </select>
                    </div>
                    
                    <!-- Remarks -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Placement Remarks</label>
                        <textarea id="placementRemarks" class="form-control" rows="2" placeholder="Any notes on this placement decision..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-check2-circle me-1"></i>Submit Placement
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
                <button type="button" class="btn btn-warning" id="makePlacementBtn">
                    <i class="bi bi-award me-1"></i>Make Placement
                </button>
            </div>
        </div>
    </div>
</div>

<script src="js/pages/admissions_academic_applications.js"></script>
<script>
function initWhenAPIReady() {
    if (typeof API !== 'undefined' && API.callAPI) {
        if (typeof academicApplicationsController !== 'undefined' && academicApplicationsController.init) {
            academicApplicationsController.init();
        }
    } else {
        setTimeout(initWhenAPIReady, 100);
    }
}
document.addEventListener('DOMContentLoaded', initWhenAPIReady);
</script>
