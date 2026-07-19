<?php
/**
 * Class Placement Page
 *
 * Dedicated interface for class placement management with capacity tracking,
 * stream assignment, and placement analytics.
 */
$appBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($appBase === '.')
    $appBase = '';
?>
<style>
    .class-placement-page {
        min-height: calc(100vh - 110px);
        padding: 1.5rem;
        background: linear-gradient(135deg, #f7fbf8 0%, #eef7f1 48%, #fff8e1 100%);
    }

    .class-placement-hero {
        border: 1px solid rgba(23, 162, 184, 0.18);
        border-radius: 1.25rem;
        background: linear-gradient(135deg, #17a2b8 0%, #138496 72%);
        color: #fff;
        box-shadow: 0 1rem 2.5rem rgba(19, 132, 150, 0.18);
    }

    .class-placement-hero .text-muted {
        color: rgba(255, 255, 255, 0.78) !important;
    }

    .class-placement-panel {
        border-radius: 1.25rem;
        background: rgba(255, 255, 255, 0.96);
        box-shadow: 0 0.75rem 2rem rgba(23, 162, 184, 0.08);
    }

    .class-placement-panel .card {
        border-color: rgba(23, 162, 184, 0.16);
    }

    .capacity-card {
        border: none;
        border-radius: 1rem;
        background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
        box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.05);
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .capacity-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
    }

    .capacity-icon {
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
        .class-placement-page {
            padding: 1rem;
        }
    }
</style>

<div class="class-placement-page">
    <!-- Hero Section -->
    <div class="class-placement-hero p-4 mb-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <p class="text-muted text-uppercase fw-semibold small mb-1">Admissions Workflow</p>
                <h4 class="mb-1">Class Placement</h4>
                <p class="mb-0 text-muted">Manage class capacities and assign student placements</p>
            </div>
            <button class="btn btn-light btn-lg" onclick="classPlacementController.refreshData()">
                <i class="bi bi-arrow-clockwise me-2"></i>Refresh
            </button>
        </div>
    </div>

    <!-- Capacity Overview -->
    <div class="row g-3 mb-4" id="capacityCards">
        <div class="col-6 col-md-3">
            <div class="capacity-card p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="capacity-icon bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-building"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold" id="statTotalClasses">—</div>
                        <div class="text-muted small">Total Classes</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="capacity-card p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="capacity-icon bg-success bg-opacity-10 text-success">
                        <i class="bi bi-people"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold" id="statTotalStudents">—</div>
                        <div class="text-muted small">Total Students</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="capacity-card p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="capacity-icon bg-warning bg-opacity-10 text-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold" id="statPendingPlacement">—</div>
                        <div class="text-muted small">Pending Placement</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="capacity-card p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="capacity-icon bg-info bg-opacity-10 text-info">
                        <i class="bi bi-graph-up"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold" id="statAvgCapacity">—</div>
                        <div class="text-muted small">Avg Capacity</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Panel -->
    <div class="class-placement-panel p-3 p-lg-4">
        <!-- Tabs -->
        <ul class="nav nav-tabs mb-4" id="placementTabs">
            <li class="nav-item">
                <button class="nav-link active" data-tab="classes" onclick="classPlacementController.switchTab('classes')">
                    <i class="bi bi-building me-2"></i>Classes
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-tab="placements" onclick="classPlacementController.switchTab('placements')">
                    <i class="bi bi-people me-2"></i>Placements
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-tab="capacity" onclick="classPlacementController.switchTab('capacity')">
                    <i class="bi bi-graph-up me-2"></i>Capacity
                </button>
            </li>
        </ul>

        <!-- Classes Tab Content -->
        <div id="classesTab" class="tab-content">
            <div class="row g-3" id="classesGrid">
                <div class="col-12 text-center py-4">
                    <div class="spinner-border text-info" role="status"></div>
                    <div class="mt-2 text-muted">Loading classes...</div>
                </div>
            </div>
        </div>

        <!-- Placements Tab Content -->
        <div id="placementsTab" class="tab-content" style="display:none;">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>Application No</th>
                                    <th>Applicant Name</th>
                                    <th>Applied Grade</th>
                                    <th>Assigned Class</th>
                                    <th>Stream</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="placementsTableBody">
                                <tr>
                                    <td colspan="7" class="text-center py-4">
                                        <div class="spinner-border text-info" role="status"></div>
                                        <div class="mt-2 text-muted">Loading placements...</div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Capacity Tab Content -->
        <div id="capacityTab" class="tab-content" style="display:none;">
            <!-- Admission Stage 5: period-aware, cohort-aware capacity projection -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-light d-flex align-items-center justify-content-between">
                    <h6 class="mb-0">
                        <i class="bi bi-graph-up-arrow me-2"></i>Future Cohort Projection
                    </h6>
                    <span class="badge bg-info" id="projectionResolutionBadge" style="display:none;"></span>
                </div>
                <div class="card-body">
                    <div class="row g-3 align-items-end">
                        <div class="col-12 col-md-3">
                            <label class="form-label fw-semibold small">Academic Year <span class="text-danger">*</span></label>
                            <select id="projectionYear" class="form-select">
                                <option value="">Select Year</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label fw-semibold small">Target Class <span class="text-danger">*</span></label>
                            <select id="projectionClass" class="form-select">
                                <option value="">Select Class</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-2">
                            <label class="form-label fw-semibold small">Term</label>
                            <select id="projectionTerm" class="form-select">
                                <option value="">Any</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-2">
                            <label class="form-label fw-semibold small">Stream</label>
                            <select id="projectionStream" class="form-select">
                                <option value="">Auto</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-2">
                            <button class="btn btn-info w-100" id="btnProjectCapacity" onclick="classPlacementController.projectCapacity()">
                                <i class="bi bi-lightbulb me-1"></i>Project
                            </button>
                        </div>
                    </div>
                    <div class="mt-3" id="projectionResult"></div>
                </div>
            </div>

            <div class="row g-3" id="capacityGrid">
                <div class="col-12 text-center py-4">
                    <div class="spinner-border text-info" role="status"></div>
                    <div class="mt-2 text-muted">Loading capacity data...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Placement Modal -->
<div class="modal fade" id="editPlacementModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Placement</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="editPlacementForm">
                <div class="modal-body">
                    <input type="hidden" id="editPlacementApplicationId">
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Applicant</label>
                        <input type="text" id="editPlacementApplicant" class="form-control" readonly>
                    </div>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Class <span class="text-danger">*</span></label>
                            <select id="editPlacementClass" class="form-select" required>
                                <option value="">Select Class</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Stream</label>
                            <select id="editPlacementStream" class="form-select">
                                <option value="">No Stream</option>
                                <option value="A">Stream A</option>
                                <option value="B">Stream B</option>
                                <option value="C">Stream C</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Remarks</label>
                        <textarea id="editPlacementRemarks" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info">
                        <i class="bi bi-check2-circle me-1"></i>Update Placement
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    window.APP_BASE = window.APP_BASE || <?= json_encode($appBase) ?>;
</script>

<script
    src="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/js/pages/admissions_class_placement.js?v=<?= time() ?>"
    onload="console.log('admissions_class_placement.js script tag loaded successfully')"
    onerror="console.error('FAILED to load admissions_class_placement.js. Check path:', this.src)">
</script>
