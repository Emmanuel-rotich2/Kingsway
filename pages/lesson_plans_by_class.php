<?php
/**
 * Lesson Plans by Class Page
 * Purpose: View lesson plan coverage broken down by class
 * Features: Class-level coverage overview, subject coverage tracking, drill-down
 */
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1"><i class="bi bi-building"></i> Lesson Plans by Class</h4>
            <small class="text-muted">View lesson plan coverage and submission status per class</small>
        </div>
        <div class="btn-group">
            <button class="btn btn-outline-primary btn-sm" id="exportByClassBtn">
                <i class="bi bi-download"></i> Export Report
            </button>
        </div>
    </div>

    <!-- KPI Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-primary">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Classes With Plans</h6>
                    <h3 class="text-primary mb-0" id="classesWithPlans">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-success">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Full Coverage</h6>
                    <h3 class="text-success mb-0" id="fullCoverage">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-warning">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Partial Coverage</h6>
                    <h3 class="text-warning mb-0" id="partialCoverage">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-danger">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">No Plans</h6>
                    <h3 class="text-danger mb-0" id="noPlans">0</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter / Search -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <select class="form-select" id="classFilterLPC">
                        <option value="">All Classes</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <select class="form-select" id="coverageFilter">
                        <option value="">All Coverage</option>
                        <option value="full">Full Coverage (100%)</option>
                        <option value="partial">Partial Coverage</option>
                        <option value="none">No Plans</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <input type="text" class="form-control" id="searchByClass" placeholder="Search class...">
                </div>
            </div>
        </div>
    </div>

    <!-- Main Data Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-bordered" id="byClassTable">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Class</th>
                            <th>Total Subjects</th>
                            <th>With Plans</th>
                            <th>Without Plans</th>
                            <th>Coverage %</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="byClassTableBody">
                        <!-- Dynamic content -->
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-3">
                <small class="text-muted">
                    Showing <span id="showingFrom">0</span> to <span id="showingTo">0</span> of <span id="totalRecords">0</span> classes
                </small>
                <nav>
                    <ul class="pagination pagination-sm mb-0" id="pagination"></ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<!-- Class Detail Modal -->
<div class="modal fade" id="classDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="classDetailLabel">Class Lesson Plan Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="classDetailContent">
                <!-- Dynamic content -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="/Kingsway/js/pages/lesson_plans_by_class.js?v=<?php echo time(); ?>"></script>
