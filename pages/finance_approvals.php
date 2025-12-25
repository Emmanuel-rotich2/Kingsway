<?php
/**
 * Finance Approvals Page
 * HTML structure only - logic will be in js/pages/finance_approvals.js
 * Embedded in app_layout.php
 */
?>

<div class="card shadow-sm">
    <div class="card-header bg-gradient bg-warning text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="fas fa-clipboard-check"></i> Finance Approvals</h4>
            <button class="btn btn-light btn-sm" id="exportBtn">
                <i class="bi bi-download"></i> Export
            </button>
        </div>
    </div>

    <div class="card-body">
        <!-- Filter Row -->
        <div class="row mb-4">
            <div class="col-md-3">
                <input type="text" class="form-control" id="searchBox" placeholder="Search...">
            </div>
            <div class="col-md-2">
                <select class="form-select" id="statusFilter">
                    <option value="">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" id="typeFilter">
                    <option value="">All Types</option>
                    <option value="expense">Expenses</option>
                    <option value="budget">Budget Requests</option>
                    <option value="payment">Payments</option>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control" id="dateFrom" placeholder="From">
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control" id="dateTo" placeholder="To">
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-warning">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Pending Approvals</h6>
                        <h3 class="text-warning mb-0" id="pendingCount">0</h3>
                        <small class="text-muted">KES <span id="pendingAmount">0</span></small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Approved Today</h6>
                        <h3 class="text-success mb-0" id="approvedCount">0</h3>
                        <small class="text-muted">KES <span id="approvedAmount">0</span></small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-danger">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Rejected Today</h6>
                        <h3 class="text-danger mb-0" id="rejectedCount">0</h3>
                        <small class="text-muted">KES <span id="rejectedAmount">0</span></small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-info">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Total This Month</h6>
                        <h3 class="text-info mb-0" id="totalCount">0</h3>
                        <small class="text-muted">KES <span id="totalAmount">0</span></small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Approvals Table -->
        <div class="table-responsive">
            <table class="table table-hover" id="approvalsTable">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Type</th>
                        <th>Date</th>
                        <th>Requested By</th>
                        <th>Description</th>
                        <th>Amount (KES)</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Dynamic content -->
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <nav>
            <ul class="pagination justify-content-center" id="pagination">
                <!-- Dynamic pagination -->
            </ul>
        </nav>
    </div>
</div>

<!-- Approval Details Modal -->
<div class="modal fade" id="approvalModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title">Approval Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p><strong>Request ID:</strong> <span id="requestId"></span></p>
                        <p><strong>Type:</strong> <span id="requestType"></span></p>
                        <p><strong>Requested By:</strong> <span id="requestedBy"></span></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Date:</strong> <span id="requestDate"></span></p>
                        <p><strong>Amount:</strong> <span id="requestAmount" class="text-primary"></span></p>
                        <p><strong>Status:</strong> <span id="requestStatus"></span></p>
                    </div>
                </div>
                <div class="mb-3">
                    <strong>Description:</strong>
                    <p id="requestDescription" class="mt-2"></p>
                </div>
                <div class="mb-3">
                    <strong>Category:</strong> <span id="requestCategory"></span>
                </div>
                <div class="mb-3" id="attachmentsSection">
                    <strong>Attachments:</strong>
                    <ul id="attachmentsList"></ul>
                </div>

                <!-- Approval Form -->
                <div class="card bg-light mt-4" id="approvalForm">
                    <div class="card-body">
                        <h6 class="mb-3">Approval Decision</h6>
                        <div class="mb-3">
                            <label class="form-label">Comments/Remarks*</label>
                            <textarea class="form-control" id="approvalComments" rows="3" required></textarea>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-danger" id="rejectBtn" data-permission="finance_approve">
                    <i class="bi bi-x-circle"></i> Reject
                </button>
                <button type="button" class="btn btn-success" id="approveBtn" data-permission="finance_approve">
                    <i class="bi bi-check-circle"></i> Approve
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // TODO: Implement financeApprovalsController in js/pages/finance_approvals.js
        console.log('Finance Approvals page loaded');
    });
</script>