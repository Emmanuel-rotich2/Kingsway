<?php
// When loaded as AJAX sub-template, $appBase is not inherited from app_layout.php.
// Compute it locally: go up 3 levels from pages/fee_structure/this_file.php → /Kingsway
?>
<div class="admin-layout fee-director-page" data-user-role="director_owner">
<style>
/* Fee structures: the quiet ledger look of Fee accounts (css/kw-clean.css). */
.fee-director-page {
    --fd-ink: #1f2a44; --fd-muted: #667085; --fd-rule: #e3e6eb; --fd-wash: #f6f7f9; --fd-paper: #fff; --fd-info: #4c5ba8;
    color: var(--fd-ink); background: var(--fd-paper); border: 1px solid var(--fd-rule); border-radius: 10px; padding: 1.25rem;
}
.fee-director-page .executive-hero { padding: 0 0 .9rem; border-bottom: 2px solid var(--fd-ink); background: none; box-shadow: none; border-radius: 0; color: var(--fd-ink); margin: 0; }
.fee-director-page .hero-eyebrow { display: none; }
.fee-director-page .hero-title { font-size: 1.5rem; font-weight: 650; letter-spacing: -.01em; margin: 0; color: var(--fd-ink); }
.fee-director-page .hero-copy { color: var(--fd-muted); margin: .2rem 0 0; max-width: 60ch; }
.fee-director-page .hero-actions { display: flex; flex-wrap: wrap; gap: .4rem; justify-content: flex-end; }
.fee-director-page .executive-btn { border: 1px solid var(--fd-rule); background: var(--fd-paper); color: var(--fd-ink); border-radius: 6px; font-size: .82rem; font-weight: 500; padding: .35rem .7rem; }
.fee-director-page .executive-btn:hover { border-color: var(--fd-ink); }
.fee-director-page .executive-btn.primary { background: var(--fd-ink); border-color: var(--fd-ink); color: #fff; }
/* Figures: one ruled strip */
.fee-director-page .metrics-board { display: grid; grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr)); border-bottom: 1px solid var(--fd-rule); margin: 0 0 1.25rem; gap: 0; }
.fee-director-page .metric-stack, .fee-director-page .metric-pair { display: contents; }
.fee-director-page .metric-card { background: none !important; border: 0; border-radius: 0; box-shadow: none; padding: .8rem 1rem .9rem; color: var(--fd-ink); }
.fee-director-page .metric-card::before, .fee-director-page .metric-card::after { display: none; }
.fee-director-page .metric-card + .metric-card, .fee-director-page .metric-stack .metric-card { border-left: 1px solid var(--fd-rule); }
.fee-director-page .metric-card.featured { background: var(--fd-wash) !important; }
.fee-director-page .metric-label { font-size: .78rem; color: var(--fd-muted); font-weight: 500; text-transform: none; letter-spacing: 0; }
.fee-director-page .metric-value { font-size: 1.35rem; font-weight: 650; font-variant-numeric: tabular-nums; color: var(--fd-ink) !important; margin: .1rem 0; font-family: inherit; }
.fee-director-page .metric-note { font-size: .78rem; color: var(--fd-muted) !important; }
.fee-director-page .board-caption { display: none; }
/* Sections and tables */
.fee-director-page .console-grid { display: grid; gap: 1.25rem; }
.fee-director-page .console-card { background: none; border: 0; border-radius: 0; box-shadow: none; margin-bottom: 1.25rem; }
.fee-director-page .console-card .card-header { background: none; border-bottom: 0; padding: 0 0 .5rem; }
.fee-director-page .console-card h5, .fee-director-page .console-card h6 { font-size: 1rem; font-weight: 600; font-family: inherit; color: var(--fd-ink); }
.fee-director-page .console-card .badge { background: none !important; color: #9a6a00 !important; padding: 0; font-weight: 600; }
.fee-director-page .filter-panel { background: none; border: 0; padding: 0 0 .75rem; }
.fee-director-page .form-label { font-size: .78rem; color: var(--fd-muted); font-weight: 500; text-transform: none; letter-spacing: 0; }
.fee-director-page :is(.form-control, .form-select) { border-color: var(--fd-rule); font-size: .875rem; border-radius: 6px; }
.fee-director-page .fee-table-shell { border: 0; box-shadow: none; }
.fee-director-page .table { font-size: .875rem; --bs-table-bg: transparent; }
.fee-director-page .table thead th, .fee-director-page #feeStructuresTable thead th { background: none !important; color: var(--fd-muted); font-size: .78rem; font-weight: 600; text-transform: none; letter-spacing: 0; border-bottom: 1px solid var(--fd-ink); white-space: nowrap; }
.fee-director-page .table tbody td { border-color: var(--fd-rule); vertical-align: middle; }
.fee-director-page .pagination-footer { border-top: 1px solid var(--fd-rule); padding-top: .75rem; }
.fee-director-page :is(.btn, button, a):focus-visible { outline: 2px solid var(--fd-info); outline-offset: 2px; }
@media (max-width: 768px) {
    .fee-director-page .hero-actions { justify-content: flex-start; }
    .fee-director-page .metric-card + .metric-card, .fee-director-page .metric-stack .metric-card { border-left: 0; border-top: 1px solid var(--fd-rule); }
}
</style>
<!-- Fee Structure Admin Component - Full management features -->

<section class="executive-hero">
    <div class="row g-4 align-items-end">
        <div class="col-lg-7">
            <div class="hero-eyebrow"><i class="bi bi-bank2"></i> Director Finance Console</div>
            <h1 class="hero-title">Fee Structure Governance</h1>
            <p class="hero-copy">Review annual fee exposure, approval readiness, and student impact before structures go live across the school.</p>
        </div>
        <div class="col-lg-5">
            <div class="hero-actions">
                <!-- One academic year at a time: current, upcoming or completed.
                     It scopes every figure, the list and the matrix below. -->
                <label for="adminMatrixAcademicYearFilter" class="small fw-semibold text-muted mb-0">Academic year</label>
                <select class="form-select form-select-sm" id="adminMatrixAcademicYearFilter" style="width: 13rem" aria-label="Academic year">
                    <option value="">Loading…</option>
                </select>
                <button class="executive-btn secondary" data-kw-onclick="exportFeeStructures()"><i class="bi bi-download me-2"></i>Export</button>
                <button class="executive-btn secondary" data-kw-onclick="showDuplicateModal()" data-year-write><i class="bi bi-files me-2"></i>Duplicate Year</button>
                <button class="executive-btn primary" data-kw-onclick="showCreateFeeStructureModal()" data-year-write><i class="bi bi-plus-lg me-2"></i>Create Structure</button>
            </div>
        </div>
    </div>
</section>

<!-- Director Decision Board -->
<div class="metrics-board">
    <div class="metric-card featured">
        <div class="metric-label">Expected Revenue</div>
        <div class="metric-value" id="totalExpectedRevenue">KES 0</div>
        <div class="metric-note">Fees billed to learners for <span id="feeSummaryYearLabel">the selected year</span>.</div>
        <div class="board-caption"><i class="bi bi-graph-up-arrow"></i> Director revenue exposure</div>
    </div>

    <div class="metric-card approval-focus">
        <div class="metric-label">Pending Approval</div>
        <div class="metric-value" id="pendingApproval">0</div>
        <div class="metric-note">Structures awaiting governance action before activation.</div>
    </div>

    <div class="metric-stack">
        <div class="metric-pair">
            <div class="metric-card metric-mini" style="--accent:#1b6df2;">
                <div class="metric-label">Total Structures</div>
                <div class="metric-value" id="totalStructures">0</div>
                <div class="metric-note">Billing groups</div>
            </div>
            <div class="metric-card metric-mini" style="--accent:#168653;">
                <div class="metric-label">Active</div>
                <div class="metric-value" id="activeStructures">0</div>
                <div class="metric-note">Currently live</div>
            </div>
        </div>
        <div class="metric-card metric-mini" style="--accent:#4b5563;">
            <div class="metric-label">Students Affected</div>
            <div class="metric-value" id="affectedStudents">0</div>
            <div class="metric-note">Distinct learners with billed obligations</div>
        </div>
    </div>
</div>

<!-- Submitted structures awaiting review/final approval -->
<div class="console-card mb-4">
    <div class="card-header d-flex align-items-center justify-content-between">
        <div>
            <h5 class="mb-1">Submitted Fee Structures</h5>
            <small class="text-muted">Review feedback first, then final-approve to make the structure effective. Authorised School Administrators and Directors may bypass an outstanding Headteacher review.</small>
        </div>
        <span class="badge rounded-pill text-bg-warning" id="pendingApprovalBadge">0 pending</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr>
                <th>Academic Year</th><th>Terms</th><th>Student Types</th><th>Classes</th><th>Submitted By</th><th>Status</th><th class="text-end">Actions</th>
            </tr></thead>
            <tbody id="pendingApprovalsBody"><tr><td colspan="7" class="text-center text-muted py-4">Loading submitted structures…</td></tr></tbody>
        </table>
    </div>
    <div class="card-header d-flex align-items-center justify-content-between border-top mt-2 py-3">
        <div>
            <h6 class="mb-1">Submitted Extra Charges</h6>
            <small class="text-muted">One-off and recurring add-on charges awaiting the same review/approval gate.</small>
        </div>
        <span class="badge rounded-pill text-bg-warning" id="pendingExtraChargesBadge">0 pending</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr>
                <th>Charge</th><th>Amount</th><th>Billing</th><th>Target</th><th>Created By</th><th>Status</th><th class="text-end">Actions</th>
            </tr></thead>
            <tbody id="pendingExtraChargesBody"><tr><td colspan="7" class="text-center text-muted py-4">Loading submitted extra charges…</td></tr></tbody>
        </table>
    </div>
</div>

<!-- Charts + Filters -->
<div class="console-grid">
    <div>
        <div class="console-card h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5>Fee Distribution by Level</h5>
                <span class="badge rounded-pill text-bg-success">Structure Mix</span>
            </div>
            <div class="card-body p-4">
                <canvas id="feeDistributionChart" height="250"></canvas>
            </div>
        </div>
    </div>
    <div>
        <div class="console-card h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5>Projected Revenue by Term</h5>
                <span class="badge rounded-pill text-bg-warning">Revenue Outlook</span>
            </div>
            <div class="card-body p-4">
                <canvas id="revenueProjectionChart" height="250"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Canonical active fee matrix -->
<div class="console-card mb-4">
    <div class="card-header d-flex align-items-center justify-content-between">
        <div>
            <h5 class="mb-1">Active Fee Structure</h5>
            <small class="text-muted">The approved Day and Full Boarder amounts exactly as configured by grade and term.</small>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-sm btn-outline-secondary" type="button" data-kw-onclick="clearFilters()">Reset</button>
            <button class="btn btn-sm btn-outline-primary" type="button" id="adminMatrixPrint" data-kw-onclick="printActiveFeeStructure()" title="PDF of the active fee structure for the selected year"><i class="bi bi-printer me-1"></i>Print PDF</button>
            <span class="badge rounded-pill text-bg-success">Active</span>
        </div>
    </div>
    <div class="card-body" id="adminActiveFeeMatrix">
        <div class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading active fee matrix…</div>
    </div>
</div>

<!-- Create/Edit Fee Structure Modal -->
<div class="modal" id="feeStructureModal">
    <div class="modal-dialog modal-dialog-scrollable modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Create New Fee Structure</h3>
                <button class="btn-close" data-bs-dismiss="modal" data-kw-onclick="closeModal('feeStructureModal')">×</button>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Form will be loaded here -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal" data-kw-onclick="closeModal('feeStructureModal')">Cancel</button>
                <button class="btn btn-primary" data-kw-onclick="saveFeeStructure()" id="saveBtn" data-year-write>💾 Save</button>
            </div>
        </div>
    </div>
</div>

<!-- View Fee Structure Details Modal -->
<div class="modal" id="viewFeeStructureModal">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Fee Structure Details</h3>
                <button class="btn-close" data-bs-dismiss="modal" data-kw-onclick="closeModal('viewFeeStructureModal')">×</button>
            </div>
            <div class="modal-body" id="viewModalBody">
                <!-- Details will be loaded here -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal" data-kw-onclick="closeModal('viewFeeStructureModal')">Close</button>
                <button class="btn btn-warning" data-kw-onclick="editFromView()" id="editFromViewBtn" data-year-write>✏️ Edit</button>
                <button class="btn btn-success" data-kw-onclick="approveFromView()" id="approveFromViewBtn" data-year-write>✅
                    Approve</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal" id="deleteConfirmModal">
    <div class="modal-dialog modal-dialog-scrollable modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Confirm Delete</h3>
                <button class="btn-close" data-bs-dismiss="modal" data-kw-onclick="closeModal('deleteConfirmModal')">×</button>
            </div>
            <div class="modal-body">
                <p>⚠️ Are you sure you want to delete this fee structure?</p>
                <p class="text-danger"><strong>This action cannot be undone.</strong></p>
                <p id="deleteWarning"></p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal" data-kw-onclick="closeModal('deleteConfirmModal')">Cancel</button>
                <button class="btn btn-danger" data-kw-onclick="confirmDelete()" id="confirmDeleteBtn" data-year-write>🗑️ Delete</button>
            </div>
        </div>
    </div>
</div>

<!-- Duplicate Structure Modal -->
<div class="modal" id="duplicateStructureModal">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Duplicate Fee Structure</h3>
                <button class="btn-close" data-bs-dismiss="modal" data-kw-onclick="closeModal('duplicateStructureModal')">×</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Target Academic Year *</label>
                    <select class="form-select" id="duplicateTargetYear">
                        <option value="">Select Year</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Price Adjustment (%)</label>
                    <input type="number" class="form-input" id="priceAdjustment" value="0" step="0.5" min="-50"
                        max="100">
                    <small class="form-text">Positive values increase fees, negative values decrease</small>
                </div>
                <div class="form-group">
                    <label>Scope</label>
                    <p class="text-muted small mb-0">Duplication runs for the entire academic year.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal" data-kw-onclick="closeModal('duplicateStructureModal')">Cancel</button>
                <button class="btn btn-primary" data-kw-onclick="confirmDuplicate()" id="confirmDuplicateBtn" data-year-write>📑
                    Duplicate</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="adminFeeMatrixModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header"><div><h5 class="modal-title mb-1">Fee Structure Matrix</h5><small class="text-muted" id="adminFeeMatrixMeta"></small></div><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body" id="adminFeeMatrixBody"></div>
    </div></div>
</div>
</div>
