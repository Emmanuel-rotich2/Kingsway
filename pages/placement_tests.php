<?php
/**
 * Placement Tests Page
 *
 * Management interface for academic placement tests.
 * Used by Deputy Academic to create tests, assign applicants, record scores,
 * and make placement recommendations based on test results.
 */
$appBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($appBase === '.')
    $appBase = '';
?>
<style>
    .placement-tests-page {
        min-height: calc(100vh - 110px);
        padding: 1.5rem;
        background: linear-gradient(135deg, #f7fbf8 0%, #eef7f1 48%, #fff8e1 100%);
    }

    .placement-tests-hero {
        border: 1px solid rgba(111, 66, 193, 0.18);
        border-radius: 1.25rem;
        background: linear-gradient(135deg, #6f42c1 0%, #5a32a3 72%);
        color: #fff;
        box-shadow: 0 1rem 2.5rem rgba(90, 50, 163, 0.18);
    }

    .placement-tests-hero .text-muted {
        color: rgba(255, 255, 255, 0.78) !important;
    }

    .placement-tests-panel {
        border-radius: 1.25rem;
        background: rgba(255, 255, 255, 0.96);
        box-shadow: 0 0.75rem 2rem rgba(90, 50, 163, 0.08);
    }

    .placement-tests-panel .card {
        border-color: rgba(111, 66, 193, 0.16);
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

    .test-card {
        border-left: 4px solid #6c757d;
        transition: all 0.2s;
    }

    .test-card:hover {
        transform: translateX(4px);
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
    }

    .test-card.scheduled {
        border-left-color: #0d6efd;
    }

    .test-card.completed {
        border-left-color: #198754;
    }

    .test-card.pending {
        border-left-color: #ffc107;
    }

    @media (max-width: 767.98px) {
        .placement-tests-page {
            padding: 1rem;
        }
    }
</style>

<div class="placement-tests-page">
    <!-- Hero Section -->
    <div class="placement-tests-hero p-4 mb-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <p class="text-muted text-uppercase fw-semibold small mb-1">Academic Assessment</p>
                <h4 class="mb-1">Placement Tests</h4>
                <p class="mb-0 text-muted">Manage academic placement tests and assessments</p>
            </div>
            <button type="button" class="btn btn-light btn-lg" id="createPlacementTestBtn">
                <i class="bi bi-plus-circle me-2"></i>Create Test
            </button>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4" id="summaryCards">
        <div class="col-6 col-md-3">
            <div class="stat-card p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-clipboard-list"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold" id="statTotalTests">—</div>
                        <div class="text-muted small">Total Tests</div>
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
                        <div class="fs-4 fw-bold" id="statPending">—</div>
                        <div class="text-muted small">Pending</div>
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
                        <div class="fs-4 fw-bold" id="statCompleted">—</div>
                        <div class="text-muted small">Completed</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon bg-info bg-opacity-10 text-info">
                        <i class="bi bi-graph-up"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold" id="statAvgScore">—</div>
                        <div class="text-muted small">Avg Score</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Panel -->
    <div class="placement-tests-panel p-3 p-lg-4">
        <!-- Filters -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <label class="form-label small fw-semibold">Test Status</label>
                <select id="filterTestStatus" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="scheduled">Scheduled</option>
                    <option value="completed">Completed</option>
                    <option value="pending">Pending</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold">Subject Area</label>
                <select id="filterSubject" class="form-select">
                    <option value="">Loading learning areas...</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold">Search</label>
                <input type="text" id="searchTests" class="form-control" placeholder="Search by test code or applicant...">
            </div>
        </div>

        <!-- Tests Grid -->
        <div id="testsGrid" class="row g-3">
            <div class="col-12 text-center py-4">
                <div class="spinner-border text-primary" role="status"></div>
                <div class="mt-2 text-muted">Loading placement tests...</div>
            </div>
        </div>
    </div>
</div>

<!-- Create Test Modal -->
<div class="modal fade" id="createTestModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Create Placement Test</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="createTestForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Test Code <span class="text-danger">*</span></label>
                        <input type="text" id="testCode" class="form-control" placeholder="e.g., PT-2024-001" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Test Date <span class="text-danger">*</span></label>
                        <input type="date" id="testDate" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Subject Area</label>
                        <select id="subjectArea" name="subjectArea" class="form-select">
                            <option value="">Loading learning areas...</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Max Score</label>
                        <input type="number" id="createMaxScore" class="form-control" value="100" min="1">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-1"></i>Create Test
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Record Results Modal -->
<div class="modal fade" id="recordResultsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-clipboard-check me-2"></i>Record Test Results</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="recordResultsForm">
                <div class="modal-body">
                    <input type="hidden" id="recordTestId">
                    <input type="hidden" id="resultMaxScore" value="100">
                    
                    <!-- Test Summary -->
                    <div class="alert alert-info" id="testSummary">
                        <div class="spinner-border spinner-border-sm me-2"></div>
                        Loading test details...
                    </div>
                    
                    <!-- Score Recording -->
                    <h6 class="fw-semibold mb-3">Test Scores</h6>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Score Obtained <span class="text-danger">*</span></label>
                            <input type="number" id="scoreObtained" class="form-control" min="0" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Percentage</label>
                            <input type="text" id="scorePercentage" class="form-control" readonly>
                        </div>
                    </div>
                    
                    <!-- Recommendation -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Recommendation <span class="text-danger">*</span></label>
                            <select id="testRecommendation" class="form-select" required>
                                <option value="">Select Recommendation</option>
                                <option value="promote">Promote to Applied Grade</option>
                                <option value="retain">Retain in Lower Grade</option>
                                <option value="conditional">Conditional Admission</option>
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
                            </select>
                        </div>
                    </div>
                    
                    <!-- Remarks -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Test Remarks</label>
                        <textarea id="testRemarks" class="form-control" rows="3" placeholder="Observations and notes from the test..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check2-circle me-1"></i>Record Results
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
    src="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/js/pages/placement_tests.js?v=<?= time() ?>"
    onload="console.log('placement_tests.js script tag loaded successfully')",
    onerror="console.error('FAILED to load placement_tests.js. Check path:', this.src)">
</script>
