<?php
/**
 * Fee Structure - Headteacher/Viewer Layout
 * For Headteacher, Deputy Headteacher, HODs
 *
 * Features:
 * - 3 stat cards (overview only)
 * - 1 chart (fee structure summary)
 * - Read-only data table
 * - Actions: View Details, Export (no Create, Edit, or Delete)
 * - Focus on oversight and reporting
 */
/* PARTIAL — no DOCTYPE/html/head/body. Injected into app shell via fetch. */
?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <div class="text-uppercase text-muted small fw-semibold">Finance oversight</div>
        <h2 class="mb-1"><i class="bi bi-wallet2 me-2"></i>Fee Structure Review</h2>
        <p class="text-muted mb-0">Review submitted structures and monitor active fees.</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-secondary btn-sm" onclick="exportReport()"><i class="bi bi-download me-1"></i>Export</button>
        <button class="btn btn-outline-secondary btn-sm" onclick="printSummary()"><i class="bi bi-printer me-1"></i>Print</button>
    </div>
</div>

<div class="card border-warning shadow-sm mb-4" id="headteacherFeeReviewCard">
    <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center bg-warning-subtle">
        <div><strong><i class="bi bi-inbox me-1"></i>Pending review</strong><br><small class="text-muted">Record feedback before final approval.</small></div>
        <span class="badge text-bg-warning" id="viewerPendingFeeCount">0 pending</span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead><tr><th>Academic Year</th><th>Terms</th><th>Student Types</th><th>Classes</th><th class="text-end">Action</th></tr></thead>
            <tbody id="viewerPendingFeeBody"><tr><td colspan="5" class="text-center text-muted py-3">Loading…</td></tr></tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="feeReviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-1">Review Fee Structure</h5>
                    <small class="text-muted" id="feeReviewMeta">Loading saved grade amounts…</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="feeReviewModalBody">
                <div class="text-center text-muted py-5"><div class="spinner-border spinner-border-sm me-2"></div>Loading saved structure…</div>
            </div>
            <div class="modal-footer d-block">
                <label for="feeReviewNotes" class="form-label fw-semibold">Review feedback</label>
                <textarea id="feeReviewNotes" class="form-control mb-3" rows="3" placeholder="Record observations for the School Administrator or Director"></textarea>
                <div class="text-end"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button><button type="button" class="btn btn-primary" id="submitFeeReviewBtn">Save review feedback</button></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-primary">
            <div class="card-body text-center">
        <h6 class="text-muted mb-2">Active structures</h6>
                <h3 class="text-primary mb-0" id="activeStructures">0</h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-success">
            <div class="card-body text-center">
        <h6 class="text-muted mb-2">Expected revenue</h6>
                <h3 class="text-success mb-0" id="totalExpectedRevenue">KES 0</h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-info">
            <div class="card-body text-center">
        <h6 class="text-muted mb-2">Students covered</h6>
                <h3 class="text-info mb-0" id="totalStudents">0</h3>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong><i class="bi bi-filter me-1"></i>Active fee structures</strong>
        <button class="btn btn-sm btn-link text-decoration-none" onclick="clearFilters()">Reset filters</button>
    </div>
    <div class="card-body">
        <div class="row g-2 align-items-center">
            <div class="col-lg-2 col-md-4">
                <select class="form-select form-select-sm" id="academicYearFilter">
                    <option value="">All Academic Years</option>
                </select>
            </div>
            <div class="col-lg-2 col-md-4">
                <select class="form-select form-select-sm" id="termFilter">
                    <option value="">All Terms</option>
                    <option value="1">Term 1</option>
                    <option value="2">Term 2</option>
                    <option value="3">Term 3</option>
                </select>
            </div>
            <div class="col-lg-2 col-md-4">
                <select class="form-select form-select-sm" id="levelFilter">
                    <option value="">All Levels</option>
                </select>
            </div>
            <div class="col-lg-2 col-md-4">
                <select class="form-select form-select-sm" id="studentTypeFilter">
                    <option value="">All Student Types</option>
                </select>
            </div>
            <div class="col-lg-3 col-md-8">
                <input type="search" class="form-control form-control-sm" id="searchInput" placeholder="Search fee item...">
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div><strong><i class="bi bi-grid-3x3-gap me-1"></i>Current fee matrix</strong><div class="small text-muted">Saved Day and Full Boarder amounts by grade and term</div></div>
        <span class="badge text-bg-success">Active</span>
    </div>
    <div class="card-body" id="activeFeeMatrix">
        <div class="text-center text-muted py-5"><div class="spinner-border spinner-border-sm me-2"></div>Loading saved fee matrix…</div>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mt-3 mb-2">
    <h6 class="text-muted mb-0"><i class="bi bi-bar-chart me-1"></i>Fee distribution by level</h6>
</div>
<div class="card shadow-sm mb-4"><div class="card-body"><canvas id="feeDistributionChart" height="120"></canvas></div></div>

<!-- View Fee Structure Details Modal (Read-Only) -->
<div class="modal" id="viewFeeStructureModal">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Fee Structure Details</h3>
                <button class="btn-close" onclick="closeModal('viewFeeStructureModal')">×</button>
            </div>
            <div class="modal-body" id="viewModalBody">
                <!-- Read-only details -->
                <div id="structureDetails"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('viewFeeStructureModal')">Close</button>
                <button class="btn btn-outline" onclick="printStructure()">
                    <i class="bi bi-printer"></i> Print
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Export Options Modal -->
<div class="modal" id="exportModal">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Export Fee Structures</h3>
                <button class="btn-close" onclick="closeModal('exportModal')">×</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Export Format</label>
                    <select class="form-select" id="exportFormat">
                        <option value="pdf">PDF Report</option>
                        <option value="excel">Excel Spreadsheet</option>
                        <option value="csv">CSV File</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Include:</label>
                    <div class="checkbox-group">
                        <label><input type="checkbox" checked> Fee Items Breakdown</label>
                        <label><input type="checkbox" checked> Student Count</label>
                        <label><input type="checkbox" checked> Revenue Projection</label>
                        <label><input type="checkbox"> Charts & Graphs</label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('exportModal')">Cancel</button>
                <button class="btn btn-primary" onclick="confirmExport()">
                    <i class="bi bi-download"></i> Export
                </button>
            </div>
        </div>
    </div>
</div>
