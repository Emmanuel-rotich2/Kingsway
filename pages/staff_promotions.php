<?php
/**
 * Staff Promotions Page
 * Promotions workflow: pending → approved → effective
 * JS controller: js/pages/staff_promotions.js
 */
?>

<div class="card shadow-sm">
    <div class="card-header bg-gradient bg-success text-white">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-0"><i class="fas fa-arrow-up me-2"></i>Staff Promotions</h4>
                <small>Manage promotions, transfers, and reclassifications</small>
            </div>
            <button class="btn btn-light btn-sm" id="addPromotionBtn" onclick="StaffPromotionsCtrl.openModal()">
                <i class="bi bi-plus-circle"></i> New Promotion
            </button>
        </div>
    </div>
    <div class="card-body">
        <!-- KPI Stats -->
        <div class="row mb-3">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center py-3">
                        <h6 class="text-muted mb-1">Total</h6>
                        <h3 class="mb-0 text-success" id="statTotal">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center py-3">
                        <h6 class="text-muted mb-1">Pending</h6>
                        <h3 class="mb-0 text-warning" id="statPending">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center py-3">
                        <h6 class="text-muted mb-1">Approved</h6>
                        <h3 class="mb-0 text-primary" id="statApproved">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center py-3">
                        <h6 class="text-muted mb-1">Effective</h6>
                        <h3 class="mb-0 text-success" id="statEffective">0</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="row mb-3 g-2">
            <div class="col-md-3">
                <input type="text" class="form-control" id="searchPromotions" placeholder="Search staff name...">
            </div>
            <div class="col-md-2">
                <select class="form-select" id="filterStatus">
                    <option value="">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="effective">Effective</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" id="filterType">
                    <option value="">All Types</option>
                    <option value="substantive">Substantive</option>
                    <option value="acting">Acting</option>
                    <option value="demotion">Demotion</option>
                    <option value="transfer">Transfer</option>
                    <option value="reclassification">Reclassification</option>
                </select>
            </div>
            <div class="col-md-3">
                <input type="date" class="form-control" id="filterDateFrom">
            </div>
            <div class="col-md-2">
                <button class="btn btn-outline-secondary w-100" onclick="StaffPromotionsCtrl.load()">
                    <i class="bi bi-search"></i> Filter
                </button>
            </div>
        </div>

        <!-- Promotions Table -->
        <div class="table-responsive">
            <table class="table table-hover align-middle" id="promotionsTable">
                <thead class="table-light">
                    <tr>
                        <th>Staff</th>
                        <th>Type</th>
                        <th>From → To</th>
                        <th>Salary Change</th>
                        <th>Effective Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="promotionsTableBody">
                    <tr><td colspan="7" class="text-center text-muted py-4">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- New / Edit Promotion Modal -->
<div class="modal fade" id="promotionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="promotionModalTitle">
                    <i class="fas fa-arrow-up me-1"></i> New Promotion
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="promotionForm">
                    <input type="hidden" id="promotionId">

                    <div class="row g-3">
                        <!-- Staff Selection -->
                        <div class="col-md-6">
                            <label class="form-label">Staff Member *</label>
                            <select class="form-select" id="promotionStaff" required>
                                <option value="">-- Select Staff --</option>
                            </select>
                            <div class="mt-2 small" id="currentPositionInfo"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Promotion Type *</label>
                            <select class="form-select" id="promotionType" required>
                                <option value="substantive">Substantive Promotion</option>
                                <option value="acting">Acting Appointment</option>
                                <option value="transfer">Transfer</option>
                                <option value="reclassification">Reclassification</option>
                                <option value="demotion">Demotion</option>
                            </select>
                        </div>
                    </div>

                    <!-- Position Change -->
                    <div class="row g-3 mt-2">
                        <div class="col-md-6">
                            <label class="form-label">Current Position</label>
                            <input type="text" class="form-control" id="fromPosition" readonly placeholder="Auto-filled from staff record">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">New Position *</label>
                            <input type="text" class="form-control" id="toPosition" required placeholder="e.g. Senior Teacher">
                        </div>
                    </div>

                    <!-- Department Change -->
                    <div class="row g-3 mt-2">
                        <div class="col-md-6">
                            <label class="form-label">Current Department</label>
                            <input type="text" class="form-control" id="fromDepartment" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">New Department</label>
                            <select class="form-select" id="toDepartment">
                                <option value="">-- Same Department --</option>
                            </select>
                        </div>
                    </div>

                    <!-- Salary Change -->
                    <div class="row g-3 mt-2">
                        <div class="col-md-4">
                            <label class="form-label">Current Salary</label>
                            <input type="number" class="form-control" id="fromSalary" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">New Salary</label>
                            <input type="number" class="form-control" id="toSalary" step="0.01" placeholder="e.g. 85000">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Effective Date *</label>
                            <input type="date" class="form-control" id="effectiveDate" required>
                        </div>
                    </div>

                    <!-- Reason -->
                    <div class="mt-3">
                        <label class="form-label">Reason / Notes</label>
                        <textarea class="form-control" id="promotionReason" rows="2" placeholder="Reason for promotion..."></textarea>
                    </div>

                    <!-- Approval Note -->
                    <div class="alert alert-info mt-3 mb-0">
                        <i class="bi bi-info-circle"></i> Promotions require approval from HR/Administration before becoming effective.
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="savePromotionBtn" onclick="StaffPromotionsCtrl.save()">
                    <i class="fas fa-paper-plane me-1"></i> Submit for Approval
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Approve/Reject Modal -->
<div class="modal fade" id="promotionApproveModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="approveModalTitle">Review Promotion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="approvePromotionId">
                <div id="approveDetails"></div>
                <div class="mb-3 mt-3">
                    <label class="form-label">Decision</label>
                    <select class="form-select" id="approveAction">
                        <option value="approve">Approve</option>
                        <option value="reject">Reject</option>
                    </select>
                </div>
                <div class="mb-2" id="rejectReasonField" style="display:none;">
                    <label class="form-label">Rejection Reason</label>
                    <textarea class="form-control" id="rejectReason" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="confirmApproveBtn" onclick="StaffPromotionsCtrl.confirmApproval()">
                    Confirm
                </button>
            </div>
        </div>
    </div>
</div>

<script src="<?= $appBase ?>js/pages/staff_promotions.js"></script>
