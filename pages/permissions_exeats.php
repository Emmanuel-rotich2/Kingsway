<?php
/**
 * Permissions & Exeats Page
 * HTML structure only - logic in js/pages/permissions_exeats.js
 * Embedded in app_layout.php
 *
 * Role-based access:
 * - Boarding Master: Create and manage permission/exeat requests
 * - Director: View all, approve/deny requests
 * - Headteacher: View all, approve/deny requests
 * - Admin: Full access
 */
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1"><i class="fas fa-door-open me-2"></i>Permissions & Exeats</h4>
                    <p class="text-muted mb-0">Manage student permission and exeat requests for boarding students</p>
                </div>
                <div class="btn-group">
                    <button class="btn btn-primary btn-sm" id="newRequestBtn"
                            data-role="boarding_master,headteacher,admin">
                        <i class="bi bi-plus-circle me-1"></i> New Request
                    </button>
                    <button class="btn btn-outline-primary btn-sm" id="exportRequestsBtn"
                            data-role="director,headteacher,admin">
                        <i class="bi bi-download me-1"></i> Export
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- KPI Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-primary">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Total Requests</h6>
                    <h3 class="text-primary mb-0" id="totalRequests">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-warning">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Pending</h6>
                    <h3 class="text-warning mb-0" id="pendingRequests">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-success">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Approved</h6>
                    <h3 class="text-success mb-0" id="approvedRequests">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-danger">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Denied</h6>
                    <h3 class="text-danger mb-0" id="deniedRequests">0</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter / Search Row -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" id="statusFilter">
                        <option value="">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="denied">Denied</option>
                        <option value="returned">Returned</option>
                        <option value="overdue">Overdue</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Search Student</label>
                    <input type="text" class="form-control" id="searchBox"
                           placeholder="Search by name or admission no...">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date From</label>
                    <input type="date" class="form-control" id="dateFrom">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date To</label>
                    <input type="date" class="form-control" id="dateTo">
                </div>
            </div>
        </div>
    </div>

    <!-- Main Data Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="requestsTable">
                    <thead class="table-light">
                        <tr>
                            <th>Student</th>
                            <th>Class</th>
                            <th>Type</th>
                            <th>Reason</th>
                            <th>Requested Date</th>
                            <th>Return Date</th>
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
</div>

<!-- New / Edit Request Modal -->
<div class="modal fade" id="requestModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="requestModalTitle">New Permission / Exeat Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="requestForm">
                    <input type="hidden" id="requestId">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Student*</label>
                            <select class="form-select" id="requestStudent" required>
                                <option value="">Select Student</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Request Type*</label>
                            <select class="form-select" id="requestType" required>
                                <option value="">Select Type</option>
                                <option value="permission">Permission</option>
                                <option value="exeat">Exeat</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Departure Date*</label>
                            <input type="date" class="form-control" id="departureDate" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Expected Return Date*</label>
                            <input type="date" class="form-control" id="returnDate" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reason*</label>
                        <textarea class="form-control" id="requestReason" rows="3" required
                                  placeholder="Provide the reason for this request..."></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Guardian / Parent Contact</label>
                            <input type="text" class="form-control" id="guardianContact"
                                   placeholder="Phone number or email">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Destination</label>
                            <input type="text" class="form-control" id="destination"
                                   placeholder="Where the student is going">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Additional Notes</label>
                        <textarea class="form-control" id="requestNotes" rows="2"
                                  placeholder="Any additional information..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveRequestBtn">Submit Request</button>
            </div>
        </div>
    </div>
</div>

<!-- Approve / Deny Modal -->
<div class="modal fade" id="approvalModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="approvalModalTitle">Review Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <p><strong>Student:</strong> <span id="approvalStudent"></span></p>
                    <p><strong>Type:</strong> <span id="approvalType"></span></p>
                    <p><strong>Reason:</strong> <span id="approvalReason"></span></p>
                    <p><strong>Dates:</strong> <span id="approvalDates"></span></p>
                </div>
                <input type="hidden" id="approvalRequestId">
                <div class="mb-3">
                    <label class="form-label">Decision*</label>
                    <select class="form-select" id="approvalDecision" required>
                        <option value="">Select Decision</option>
                        <option value="approved">Approve</option>
                        <option value="denied">Deny</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Comments</label>
                    <textarea class="form-control" id="approvalComments" rows="3"
                              placeholder="Add comments for the decision..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="submitApprovalBtn">Submit Decision</button>
            </div>
        </div>
    </div>
</div>

<script src="/Kingsway/js/pages/permissions_exeats.js"></script>
