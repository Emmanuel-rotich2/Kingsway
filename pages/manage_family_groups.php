<?php
/**
 * Family Groups Page
 * Manage siblings, guardians, and household relationships
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

<div class="container-fluid py-4" id="familyGroupsPage">

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-success text-white">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h4 class="mb-0">
                        <i class="fas fa-users me-2"></i>
                        Family Groups
                    </h4>
                    <small id="scopeSubtitle">Manage siblings, guardians, and household relationships</small>
                </div>
                <div class="btn-group">
                    <button class="btn btn-light btn-sm" id="refreshBtn">
                        <i class="bi bi-arrow-clockwise"></i> Refresh
                    </button>
                    <button class="btn btn-outline-light btn-sm" id="exportBtn">
                        <i class="bi bi-download"></i> Export
                    </button>
                    <button class="btn btn-light btn-sm" id="addParentBtn">
                        <i class="bi bi-plus-circle"></i> Add Parent
                    </button>
                </div>
            </div>
        </div>

        <div class="card-body">

            <!-- Filters -->
            <div class="row g-3 mb-4">
                <div class="col-xl-3 col-md-6">
                    <label class="form-label fw-semibold">Class</label>
                    <select class="form-select" id="classFilter">
                        <option value="">All Classes</option>
                    </select>
                </div>

                <div class="col-xl-3 col-md-6">
                    <label class="form-label fw-semibold">Stream</label>
                    <select class="form-select" id="streamFilter">
                        <option value="">All Streams</option>
                    </select>
                </div>

                <div class="col-xl-3 col-md-6">
                    <label class="form-label fw-semibold">Guardian Name/Phone</label>
                    <input type="text" class="form-control" id="guardianFilter"
                           placeholder="Search guardian...">
                </div>

                <div class="col-xl-3 col-md-6">
                    <label class="form-label fw-semibold">Family Status</label>
                    <select class="form-select" id="statusFilter">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>

                <div class="col-xl-4 col-md-8">
                    <label class="form-label fw-semibold">Search</label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-search"></i>
                        </span>
                        <input type="text" class="form-control" id="searchBox"
                               placeholder="Search by student, guardian, phone, or family code">
                    </div>
                </div>

                <div class="col-xl-2 col-md-4 d-flex align-items-end">
                    <button class="btn btn-success w-100" id="applyFiltersBtn">
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
                                <div class="rounded-circle bg-success text-white p-3">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Total Families</small>
                                    <h4 class="mb-0" id="totalFamilies">0</h4>
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
                                    <i class="fas fa-user-graduate"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Students Linked</small>
                                    <h4 class="mb-0" id="studentsLinked">0</h4>
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
                                    <i class="fas fa-user-minus"></i>
                                </div>
                                <div>
                                    <small class="text-muted">No Family Group</small>
                                    <h4 class="mb-0" id="studentsWithoutFamily">0</h4>
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
                                    <i class="fas fa-users-cog"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Multiple Students</small>
                                    <h4 class="mb-0" id="multipleStudents">0</h4>
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
                                    <i class="fas fa-phone-slash"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Missing Contact</small>
                                    <h4 class="mb-0" id="missingContact">0</h4>
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
                                    <i class="fas fa-money-bill"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Outstanding</small>
                                    <h4 class="mb-0" id="outstandingBalance">0</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- States -->
            <div id="familiesLoading" class="alert alert-info d-none">
                <i class="fas fa-spinner fa-spin me-2"></i> Loading family groups...
            </div>

            <div id="familiesError" class="alert alert-danger d-none"></div>

            <div id="familiesEmpty" class="alert alert-warning d-none">
                <i class="fas fa-info-circle me-2"></i> No family groups found for the selected filters.
            </div>

            <!-- Main Table -->
            <div class="card border-0 shadow-sm" id="familiesCard">
                <div class="card-header bg-white">
                    <strong>
                        <i class="fas fa-list me-2 text-success"></i>
                        Parent/Guardian List
                    </strong>
                </div>

                <div class="card-body table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Guardian</th>
                                <th>Phone</th>
                                <th>Email</th>
                                <th>Students Count</th>
                                <th>Student Names</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="familiesTableBody">
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

<!-- Family Detail Modal -->
<div class="modal fade" id="familyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content border-0 shadow">

            <div class="modal-header bg-success text-white">
                <div>
                    <h5 class="modal-title mb-0">
                        <i class="fas fa-users me-2"></i>
                        Family Group Details
                    </h5>
                    <small id="modalSubtitle">Parent and linked students</small>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div id="modalLoading" class="alert alert-info d-none">
                    <i class="fas fa-spinner fa-spin me-2"></i> Loading family details...
                </div>

                <div id="modalError" class="alert alert-danger d-none"></div>

                <div id="modalFamilyContent">
                    <!-- Family details will be rendered here -->
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <button class="btn btn-success" id="linkStudentBtn">
                    <i class="bi bi-link me-1"></i> Link Student
                </button>
            </div>

        </div>
    </div>
</div>

<script src="<?php echo $appBase; ?>/js/pages/family_groups.js?v=<?php echo time(); ?>"></script>
