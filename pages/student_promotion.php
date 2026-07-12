<?php
/**
 * Student Promotion Page
 * Promote students between academic years, classes, and streams
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

<div class="container-fluid py-4" id="studentPromotionPage">

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-success text-white">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h4 class="mb-0">
                        <i class="bi bi-arrow-up-circle me-2"></i>
                        Student Promotion
                    </h4>
                    <small id="scopeSubtitle">Promote students between academic years, classes, and streams</small>
                </div>
                <div class="btn-group">
                    <button class="btn btn-light btn-sm" id="refreshBtn">
                        <i class="bi bi-arrow-clockwise"></i> Refresh
                    </button>
                    <button class="btn btn-outline-light btn-sm" id="historyBtn">
                        <i class="bi bi-clock-history"></i> History
                    </button>
                    <button class="btn btn-light btn-sm" id="newBatchBtn">
                        <i class="bi bi-plus-circle"></i> New Batch
                    </button>
                </div>
            </div>
        </div>

        <div class="card-body">

            <!-- Promotion Setup Card -->
            <div class="card border-0 bg-light mb-4">
                <div class="card-body">
                    <h5 class="card-title"><i class="bi bi-sliders me-2"></i>Promotion Settings</h5>
                    <div class="row g-3">
                        <div class="col-xl-3 col-md-6">
                            <label class="form-label fw-semibold">From Academic Year</label>
                            <select class="form-select" id="fromYear">
                                <option value="">Select Year</option>
                            </select>
                        </div>
                        <div class="col-xl-3 col-md-6">
                            <label class="form-label fw-semibold">To Academic Year</label>
                            <select class="form-select" id="toYear">
                                <option value="">Select Year</option>
                            </select>
                        </div>
                        <div class="col-xl-3 col-md-6">
                            <label class="form-label fw-semibold">From Class</label>
                            <select class="form-select" id="fromClass">
                                <option value="">Select Class</option>
                            </select>
                        </div>
                        <div class="col-xl-3 col-md-6">
                            <label class="form-label fw-semibold">To Class</label>
                            <select class="form-select" id="toClass">
                                <option value="">Select Class</option>
                            </select>
                        </div>
                        <div class="col-xl-3 col-md-6">
                            <label class="form-label fw-semibold">From Stream</label>
                            <select class="form-select" id="fromStream">
                                <option value="">All Streams</option>
                            </select>
                        </div>
                        <div class="col-xl-3 col-md-6">
                            <label class="form-label fw-semibold">To Stream</label>
                            <select class="form-select" id="toStream">
                                <option value="">All Streams</option>
                            </select>
                        </div>
                        <div class="col-xl-3 col-md-6">
                            <label class="form-label fw-semibold">Promotion Rule</label>
                            <select class="form-select" id="promotionRule">
                                <option value="promote_all">Promote All</option>
                                <option value="promote_passed">Promote Passed Only</option>
                                <option value="repeat_failed">Repeat Failed</option>
                                <option value="custom">Custom Selection</option>
                            </select>
                        </div>
                        <div class="col-xl-3 col-md-6 d-flex align-items-end">
                            <button class="btn btn-success w-100" id="loadCandidatesBtn">
                                <i class="bi bi-people me-1"></i> Load Students
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="row g-3 mb-4">
                <div class="col-xl-3 col-md-6">
                    <label class="form-label fw-semibold">Gender</label>
                    <select class="form-select" id="genderFilter">
                        <option value="">All</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                    </select>
                </div>
                <div class="col-xl-3 col-md-6">
                    <label class="form-label fw-semibold">Search</label>
                    <input type="text" class="form-control" id="searchBox"
                           placeholder="Search by name or admission number">
                </div>
                <div class="col-xl-2 col-md-4 d-flex align-items-end">
                    <button class="btn btn-outline-success w-100" id="applyFiltersBtn">
                        <i class="fas fa-filter me-1"></i> Apply
                    </button>
                </div>
            </div>

            <!-- Summary cards -->
            <div class="row g-3 mb-4">
                <div class="col-xl-2 col-md-4">
                    <div class="card border-0 bg-light h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle bg-success text-white p-3">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Candidates</small>
                                    <h4 class="mb-0" id="candidatesCount">0</h4>
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
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Selected</small>
                                    <h4 class="mb-0" id="selectedCount">0</h4>
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
                                    <i class="fas fa-arrow-repeat"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Retain</small>
                                    <h4 class="mb-0" id="retainCount">0</h4>
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
                                    <i class="fas fa-exclamation-circle"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Needs Review</small>
                                    <h4 class="mb-0" id="reviewCount">0</h4>
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
                                    <small class="text-muted">Fee Issues</small>
                                    <h4 class="mb-0" id="feeIssuesCount">0</h4>
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
                                    <i class="fas fa-gavel"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Discipline</small>
                                    <h4 class="mb-0" id="disciplineCount">0</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- States -->
            <div id="candidatesLoading" class="alert alert-info d-none">
                <i class="fas fa-spinner fa-spin me-2"></i> Loading promotion candidates...
            </div>

            <div id="candidatesError" class="alert alert-danger d-none"></div>

            <div id="candidatesEmpty" class="alert alert-warning d-none">
                <i class="fas fa-info-circle me-2"></i> No candidates found. Select promotion settings and load students.
            </div>

            <!-- Main Table -->
            <div class="card border-0 shadow-sm" id="candidatesCard">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <strong>
                        <i class="fas fa-list me-2 text-success"></i>
                        Promotion Candidates
                    </strong>
                    <div>
                        <button class="btn btn-sm btn-success" id="promoteAllBtn">
                            <i class="bi bi-check2-all"></i> Promote All
                        </button>
                        <button class="btn btn-sm btn-outline-warning" id="retainAllBtn">
                            <i class="bi bi-arrow-repeat"></i> Retain All
                        </button>
                    </div>
                </div>

                <div class="card-body table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th><input type="checkbox" id="selectAllCandidates"></th>
                                <th>Adm No</th>
                                <th>Student Name</th>
                                <th>Current Class</th>
                                <th>Current Stream</th>
                                <th>Academic Year</th>
                                <th>Recommended</th>
                                <th>Final Action</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody id="candidatesTableBody">
                            <tr>
                                <td class="text-center text-muted">Load students to view candidates</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="card-footer">
                    <button class="btn btn-success btn-lg" id="executePromotionBtn">
                        <i class="bi bi-check-circle me-1"></i> Execute Promotion
                    </button>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Promotion History Modal -->
<div class="modal fade" id="historyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content border-0 shadow">

            <div class="modal-header bg-success text-white">
                <div>
                    <h5 class="modal-title mb-0">
                        <i class="bi bi-clock-history me-2"></i>
                        Promotion History
                    </h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div id="historyLoading" class="alert alert-info d-none">
                    <i class="fas fa-spinner fa-spin me-2"></i> Loading promotion history...
                </div>

                <div id="historyError" class="alert alert-danger d-none"></div>

                <div id="historyContent">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Batch ID</th>
                                <th>From Year</th>
                                <th>To Year</th>
                                <th>Status</th>
                                <th>Students</th>
                                <th>Promoted</th>
                                <th>Created Date</th>
                            </tr>
                        </thead>
                        <tbody id="historyTableBody">
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>

        </div>
    </div>
</div>

<script src="<?php echo $appBase; ?>/js/pages/student_promotion.js?v=<?php echo time(); ?>"></script>
