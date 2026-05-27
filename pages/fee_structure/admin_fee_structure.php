<?php
// When loaded as AJAX sub-template, $appBase is not inherited from app_layout.php.
// Compute it locally: go up 3 levels from pages/fee_structure/this_file.php → /Kingsway
if (!isset($appBase)) {
    $p = $_SERVER['SCRIPT_NAME'] ?? '';
    $appBase = rtrim(dirname(dirname(dirname($p))), '/');
    if ($appBase === '.' || $appBase === '/') $appBase = '';
}
?>
<div class="admin-layout fee-director-page" data-user-role="director_owner">
<style>
    .fee-director-page {
        --ledger-ink: #0d1b16;
        --ledger-green: #0f6b43;
        --ledger-emerald: #168653;
        --ledger-gold: #c99425;
        --ledger-mint: #dff5e9;
        --ledger-cream: #fbf7ec;
        --ledger-paper: #fffdf7;
        --ledger-line: rgba(15, 107, 67, 0.16);
        --ledger-shadow: 0 24px 60px rgba(13, 27, 22, 0.12);
        color: var(--ledger-ink);
        font-family: "Aptos", "Trebuchet MS", sans-serif;
        padding: 22px 28px 40px;
        background:
            radial-gradient(circle at 8% 6%, rgba(22, 134, 83, 0.08), transparent 28%),
            linear-gradient(180deg, #fffdf7 0%, #f5f0e3 100%);
    }

    .fee-director-page .executive-hero {
        position: relative;
        overflow: hidden;
        border-radius: 28px;
        padding: 28px;
        margin: 8px 0 22px;
        background:
            radial-gradient(circle at 12% 18%, rgba(255, 255, 255, 0.18), transparent 28%),
            linear-gradient(135deg, #0d1b16 0%, #104f35 48%, #178053 100%);
        box-shadow: var(--ledger-shadow);
        color: #fff;
    }

    .fee-director-page .executive-hero::after {
        content: "";
        position: absolute;
        inset: auto -8% -55% 48%;
        height: 260px;
        border-radius: 999px;
        background: rgba(201, 148, 37, 0.24);
        filter: blur(8px);
        transform: rotate(-8deg);
    }

    .fee-director-page .hero-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        padding: 7px 12px;
        border: 1px solid rgba(255, 255, 255, 0.22);
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.1);
        font-size: 0.78rem;
        letter-spacing: 0.16em;
        text-transform: uppercase;
    }

    .fee-director-page .hero-title {
        max-width: 720px;
        margin: 16px 0 8px;
        font-family: Georgia, "Times New Roman", serif;
        font-size: clamp(2rem, 3.6vw, 4rem);
        line-height: 0.96;
        letter-spacing: -0.05em;
    }

    .fee-director-page .hero-copy {
        max-width: 640px;
        margin: 0;
        color: rgba(255, 255, 255, 0.78);
        font-size: 1rem;
    }

    .fee-director-page .hero-actions {
        position: relative;
        z-index: 1;
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        justify-content: flex-end;
        align-items: center;
    }

    .fee-director-page .executive-btn {
        border: 0;
        border-radius: 999px;
        padding: 11px 16px;
        font-weight: 800;
        letter-spacing: -0.02em;
        box-shadow: 0 14px 30px rgba(0, 0, 0, 0.16);
    }

    .fee-director-page .executive-btn.primary {
        background: #f4bd46;
        color: #111b14;
    }

    .fee-director-page .executive-btn.secondary {
        background: rgba(255, 255, 255, 0.12);
        color: #fff;
        border: 1px solid rgba(255, 255, 255, 0.24);
    }

    .fee-director-page .metric-card {
        position: relative;
        overflow: hidden;
        min-height: 128px;
        border: 1px solid var(--ledger-line);
        border-radius: 24px;
        background: linear-gradient(180deg, #fff 0%, var(--ledger-paper) 100%);
        box-shadow: 0 18px 44px rgba(13, 27, 22, 0.08);
    }

    .fee-director-page .metric-card::before {
        content: "";
        position: absolute;
        inset: 0 auto 0 0;
        width: 5px;
        background: var(--accent, var(--ledger-green));
    }

    .fee-director-page .metric-label {
        color: #69756f;
        font-size: 0.75rem;
        font-weight: 900;
        letter-spacing: 0.12em;
        text-transform: uppercase;
    }

    .fee-director-page .metric-value {
        margin-top: 8px;
        color: var(--ledger-ink);
        font-family: Georgia, "Times New Roman", serif;
        font-size: clamp(1.7rem, 2.4vw, 2.55rem);
        font-weight: 800;
        letter-spacing: -0.05em;
    }

    .fee-director-page .metric-note {
        color: #7d8781;
        font-size: 0.82rem;
    }

    .fee-director-page .metrics-board {
        display: grid;
        grid-template-columns: minmax(320px, 1.45fr) minmax(260px, 0.85fr) minmax(300px, 1fr);
        gap: 18px;
        margin: 24px 0 28px;
        align-items: stretch;
    }

    .fee-director-page .metric-stack {
        display: grid;
        gap: 18px;
    }

    .fee-director-page .metric-pair {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 18px;
    }

    .fee-director-page .metric-card.featured {
        min-height: 236px;
        padding: 30px;
        background:
            radial-gradient(circle at 90% 10%, rgba(244, 189, 70, 0.32), transparent 32%),
            linear-gradient(135deg, #0d1b16 0%, #104f35 62%, #168653 100%);
        color: #fff;
    }

    .fee-director-page .metric-card.featured::before {
        width: 0;
    }

    .fee-director-page .metric-card.featured .metric-label,
    .fee-director-page .metric-card.featured .metric-note {
        color: rgba(255, 255, 255, 0.74);
    }

    .fee-director-page .metric-card.featured .metric-value {
        color: #fff;
        font-size: clamp(2.35rem, 4vw, 4.2rem);
        line-height: 0.96;
    }

    .fee-director-page .metric-card.approval-focus {
        min-height: 236px;
        padding: 28px;
        background:
            linear-gradient(160deg, rgba(255, 249, 232, 0.98), #fff 62%),
            radial-gradient(circle at 100% 0%, rgba(201, 148, 37, 0.2), transparent 36%);
        border-color: rgba(201, 148, 37, 0.38);
    }

    .fee-director-page .approval-focus .metric-value {
        font-size: clamp(2.4rem, 4vw, 4rem);
        color: #9a6b08;
    }

    .fee-director-page .metric-mini {
        min-height: 109px;
        padding: 22px;
    }

    .fee-director-page .metric-mini .metric-value {
        font-size: clamp(1.55rem, 2.2vw, 2.1rem);
    }

    .fee-director-page .board-caption {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-top: 22px;
        padding: 8px 12px;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.12);
        color: rgba(255, 255, 255, 0.76);
        font-size: 0.82rem;
    }

    @media (max-width: 1180px) {
        .fee-director-page .metrics-board {
            grid-template-columns: 1fr 1fr;
        }

        .fee-director-page .metric-stack {
            grid-column: 1 / -1;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 760px) {
        .fee-director-page {
            padding: 16px;
        }

        .fee-director-page .metrics-board,
        .fee-director-page .metric-stack,
        .fee-director-page .metric-pair {
            grid-template-columns: 1fr;
        }

        .fee-director-page .hero-actions {
            justify-content: flex-start;
        }
    }

    .fee-director-page .console-grid {
        display: grid;
        grid-template-columns: minmax(360px, 0.95fr) minmax(420px, 1.05fr);
        gap: 22px;
        margin-bottom: 28px;
    }

    @media (max-width: 992px) {
        .fee-director-page .console-grid {
            grid-template-columns: 1fr;
        }
    }
    .fee-director-page .console-card {
        border: 1px solid var(--ledger-line);
        border-radius: 26px;
        background: rgba(255, 255, 255, 0.86);
        box-shadow: 0 18px 44px rgba(13, 27, 22, 0.08);
        overflow: hidden;
    }

    .fee-director-page .console-card .card-header {
        border: 0;
        background: linear-gradient(90deg, rgba(223, 245, 233, 0.8), rgba(251, 247, 236, 0.95));
        padding: 18px 22px;
    }

    .fee-director-page .console-card h5 {
        margin: 0;
        font-family: Georgia, "Times New Roman", serif;
        font-weight: 800;
        letter-spacing: -0.04em;
    }

    .fee-director-page .filter-panel {
        border: 1px solid rgba(201, 148, 37, 0.28);
        border-radius: 26px;
        background:
            linear-gradient(135deg, rgba(251, 247, 236, 0.92), rgba(255, 255, 255, 0.96)),
            repeating-linear-gradient(90deg, transparent, transparent 18px, rgba(15, 107, 67, 0.04) 19px);
        box-shadow: 0 18px 44px rgba(13, 27, 22, 0.07);
    }

    .fee-director-page .form-label {
        color: #536158;
        font-size: 0.72rem;
        font-weight: 900;
        letter-spacing: 0.12em;
        text-transform: uppercase;
    }

    .fee-director-page .form-select,
    .fee-director-page .form-control {
        border-color: rgba(15, 107, 67, 0.18);
        border-radius: 16px;
        min-height: 46px;
        background-color: #fffdf8;
        font-weight: 700;
    }

    .fee-director-page .fee-table-shell {
        border: 1px solid var(--ledger-line);
        border-radius: 26px;
        overflow: hidden;
        background: #fff;
        box-shadow: 0 18px 44px rgba(13, 27, 22, 0.08);
    }

    .fee-director-page #feeStructuresTable {
        margin-bottom: 0;
        vertical-align: middle;
    }

    .fee-director-page #feeStructuresTable thead th {
        border: 0;
        background: #0f2c22;
        color: rgba(255, 255, 255, 0.86);
        font-size: 0.72rem;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        padding: 16px 18px;
    }

    .fee-director-page #feeStructuresTable tbody td {
        border-color: rgba(15, 107, 67, 0.1);
        padding: 16px 18px;
    }

    .fee-director-page .pagination-footer {
        border: 1px solid var(--ledger-line);
        border-radius: 18px;
        padding: 12px 14px;
        background: var(--ledger-cream);
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
                <button class="executive-btn secondary" onclick="exportFeeStructures()"><i class="bi bi-download me-2"></i>Export</button>
                <button class="executive-btn secondary" onclick="showDuplicateModal()"><i class="bi bi-files me-2"></i>Duplicate Year</button>
                <button class="executive-btn primary" onclick="showCreateFeeStructureModal()"><i class="bi bi-plus-lg me-2"></i>Create Structure</button>
            </div>
        </div>
    </div>
</section>

<!-- Director Decision Board -->
<div class="metrics-board">
    <div class="metric-card featured">
        <div class="metric-label">Expected Revenue</div>
        <div class="metric-value" id="totalExpectedRevenue">KES 0</div>
        <div class="metric-note">Projected collection basis across the currently filtered fee book.</div>
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
            <div class="metric-note">Learners covered by selected structures</div>
        </div>
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

<!-- Filters Section -->
<div class="filter-panel p-4 mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <div class="metric-label">Decision Filters</div>
            <h5 class="mb-0" style="font-family: Georgia, 'Times New Roman', serif; font-weight:800;">Narrow the fee book</h5>
        </div>
        <button class="btn btn-outline-success rounded-pill px-4" onclick="clearFilters()"><i class="bi bi-arrow-counterclockwise me-2"></i>Reset</button>
    </div>
    <div class="row g-3">
        <div class="col-xl-2 col-md-4">
            <label class="form-label">Academic Year</label>
            <select class="form-select" id="academicYearFilter">
                <option value="">All Years</option>
            </select>
        </div>
        <div class="col-xl-2 col-md-4">
            <label class="form-label">School Level</label>
            <select class="form-select" id="schoolLevelFilter">
                <option value="">All Levels</option>
            </select>
        </div>
        <div class="col-xl-2 col-md-4">
            <label class="form-label">Student Type</label>
            <select class="form-select" id="studentTypeFilter">
                <option value="">All Types</option>
            </select>
        </div>
        <div class="col-xl-2 col-md-4">
            <label class="form-label">Term</label>
            <select class="form-select" id="termFilter">
                <option value="">All Terms</option>
                <option value="1">Term 1</option>
                <option value="2">Term 2</option>
                <option value="3">Term 3</option>
            </select>
        </div>
        <div class="col-xl-2 col-md-4">
            <label class="form-label">Status</label>
            <select class="form-select" id="statusFilter">
                <option value="">All Status</option>
                <option value="active">Active</option>
                <option value="draft">Draft</option>
                <option value="reviewed">Reviewed</option>
                <option value="approved">Approved</option>
                <option value="archived">Archived</option>
            </select>
        </div>
        <div class="col-xl-2 col-md-4">
            <label class="form-label">Search</label>
            <input type="text" class="form-control" id="searchFeeStructure" placeholder="Level, type, year...">
        </div>
    </div>
</div>

<!-- Data Table -->
<div class="fee-table-shell table-responsive">
    <table class="table table-hover" id="feeStructuresTable">
        <thead>
            <tr>
                <th>Academic Year</th>
                <th>Term</th>
                <th>Level</th>
                <th>Student Type</th>
                <th class="text-end">Total Amount</th>
                <th>Students</th>
                <th class="text-end">Expected Revenue</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="feeStructuresBody">
            <tr>
                <td colspan="9" class="text-center py-5 text-muted">Loading fee structures...</td>
            </tr>
        </tbody>
    </table>
</div>

<!-- Pagination -->
<div class="pagination-footer d-flex justify-content-between align-items-center mt-3">
    <span class="text-muted fw-semibold" id="paginationInfo">Showing 0 of 0</span>
    <div class="btn-group" id="paginationControls"></div>
</div>

<!-- Create/Edit Fee Structure Modal -->
<div class="modal" id="feeStructureModal">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Create New Fee Structure</h3>
                <button class="btn-close" onclick="closeModal('feeStructureModal')">×</button>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Form will be loaded here -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('feeStructureModal')">Cancel</button>
                <button class="btn btn-primary" onclick="saveFeeStructure()" id="saveBtn">💾 Save</button>
            </div>
        </div>
    </div>
</div>

<!-- View Fee Structure Details Modal -->
<div class="modal" id="viewFeeStructureModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Fee Structure Details</h3>
                <button class="btn-close" onclick="closeModal('viewFeeStructureModal')">×</button>
            </div>
            <div class="modal-body" id="viewModalBody">
                <!-- Details will be loaded here -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('viewFeeStructureModal')">Close</button>
                <button class="btn btn-warning" onclick="editFromView()" id="editFromViewBtn">✏️ Edit</button>
                <button class="btn btn-success" onclick="approveFromView()" id="approveFromViewBtn">✅
                    Approve</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal" id="deleteConfirmModal">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Confirm Delete</h3>
                <button class="btn-close" onclick="closeModal('deleteConfirmModal')">×</button>
            </div>
            <div class="modal-body">
                <p>⚠️ Are you sure you want to delete this fee structure?</p>
                <p class="text-danger"><strong>This action cannot be undone.</strong></p>
                <p id="deleteWarning"></p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('deleteConfirmModal')">Cancel</button>
                <button class="btn btn-danger" onclick="confirmDelete()" id="confirmDeleteBtn">🗑️ Delete</button>
            </div>
        </div>
    </div>
</div>

<!-- Duplicate Structure Modal -->
<div class="modal" id="duplicateStructureModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Duplicate Fee Structure</h3>
                <button class="btn-close" onclick="closeModal('duplicateStructureModal')">×</button>
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
                <button class="btn btn-secondary" onclick="closeModal('duplicateStructureModal')">Cancel</button>
                <button class="btn btn-primary" onclick="confirmDuplicate()" id="confirmDuplicateBtn">📑
                    Duplicate</button>
            </div>
        </div>
    </div>
</div>

<script src="<?= $appBase ?>/js/pages/fee_structure_admin.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof window.FeeStructureAdminController !== 'undefined') {
            window.FeeStructureAdminController.init();
        } else {
            console.error('FeeStructureAdminController not found');
        }
    });
</script>
</div>
