<?php
/**
 * Manage Requisitions Page
 * HTML structure only - logic will be in js/pages/requisitions.js
 * Embedded in app_layout.php
 */
?>

<div class="card shadow-sm">
    <div class="card-header bg-gradient bg-primary text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="fas fa-clipboard-list"></i> Requisitions Management</h4>
            <div class="btn-group">
                <button class="btn btn-light btn-sm" id="createRequisitionBtn" data-permission="requisitions_create">
                    <i class="bi bi-plus-circle"></i> New Requisition
                </button>
                <button class="btn btn-outline-light btn-sm" id="exportRequisitionsBtn">
                    <i class="bi bi-download"></i> Export
                </button>
            </div>
        </div>
    </div>

    <div class="card-body">
        <!-- Requisition Stats -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-info">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Total Requisitions</h6>
                        <h3 class="text-info mb-0" id="totalRequisitions">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-warning">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Pending Approval</h6>
                        <h3 class="text-warning mb-0" id="pendingRequisitions">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Approved</h6>
                        <h3 class="text-success mb-0" id="approvedRequisitions">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-primary">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Fulfilled</h6>
                        <h3 class="text-primary mb-0" id="fulfilledRequisitions">0</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="row mb-3">
            <div class="col-md-3">
                <input type="text" class="form-control" id="requisitionSearch" placeholder="Search requisitions...">
            </div>
            <div class="col-md-2">
                <select class="form-select" id="statusFilter">
                    <option value="">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                    <option value="fulfilled">Fulfilled</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" id="departmentFilter">
                    <option value="">All Departments</option>
                    <option value="academic">Academic</option>
                    <option value="administration">Administration</option>
                    <option value="kitchen">Kitchen</option>
                    <option value="maintenance">Maintenance</option>
                    <option value="library">Library</option>
                    <option value="lab">Laboratory</option>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control" id="dateFromFilter">
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control" id="dateToFilter">
            </div>
        </div>

        <!-- Requisitions Table -->
        <div class="table-responsive">
            <table class="table table-hover" id="requisitionsTable">
                <thead class="table-light">
                    <tr>
                        <th>Req. No.</th>
                        <th>Date</th>
                        <th>Requested By</th>
                        <th>Department</th>
                        <th>Items</th>
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
            <ul class="pagination justify-content-center" id="requisitionsPagination">
                <!-- Dynamic pagination -->
            </ul>
        </nav>
    </div>
</div>

<!-- Requisition Modal -->
<div class="modal fade" id="requisitionModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Requisition Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="requisitionForm">
                    <input type="hidden" id="requisition_id">

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Department*</label>
                            <select class="form-select" id="department" required>
                                <option value="">Select Department</option>
                                <option value="academic">Academic</option>
                                <option value="administration">Administration</option>
                                <option value="kitchen">Kitchen</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="library">Library</option>
                                <option value="lab">Laboratory</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Purpose*</label>
                            <input type="text" class="form-control" id="purpose" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Items Requested*</label>
                        <div id="requisitionItems">
                            <!-- Dynamic item rows -->
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="addItemRowBtn">
                            <i class="bi bi-plus"></i> Add Item
                        </button>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Notes/Justification</label>
                        <textarea class="form-control" id="notes" rows="3"></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Priority</label>
                            <select class="form-select" id="priority">
                                <option value="normal">Normal</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Required By</label>
                            <input type="date" class="form-control" id="required_by">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="submitRequisitionBtn">Submit Requisition</button>
            </div>
        </div>
    </div>
</div>

<!-- View/Approve Requisition Modal -->
<div class="modal fade" id="viewRequisitionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Requisition Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="requisitionDetailsContent">
                <!-- Dynamic content -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-danger" id="rejectRequisitionBtn"
                    data-permission="requisitions_approve">Reject</button>
                <button type="button" class="btn btn-success" id="approveRequisitionBtn"
                    data-permission="requisitions_approve">Approve</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Initialize requisitions management when page loads
    document.addEventListener('DOMContentLoaded', function () {
        // TODO: Implement requisitionsManagementController in js/pages/requisitions.js
        console.log('Requisitions Management page loaded');
    });
</script>