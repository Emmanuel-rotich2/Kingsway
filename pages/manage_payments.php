<?php
/**
 * Manage Payments Page
 * HTML structure only - logic will be in js/pages/payments.js
 * Embedded in app_layout.php
 */
?>

<div class="card shadow-sm">
    <div class="card-header bg-gradient bg-primary text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="fas fa-money-bill-wave"></i> Payment Management</h4>
            <div class="btn-group">
                <button class="btn btn-light btn-sm" id="recordPaymentBtn" data-permission="payments_create">
                    <i class="bi bi-plus-circle"></i> Record Payment
                </button>
                <button class="btn btn-outline-light btn-sm" id="exportPaymentsBtn">
                    <i class="bi bi-download"></i> Export
                </button>
            </div>
        </div>
    </div>

    <div class="card-body">
        <!-- Payment Overview Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Total Received</h6>
                        <h3 class="text-success mb-0" id="totalReceived">KES 0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-warning">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Pending</h6>
                        <h3 class="text-warning mb-0" id="pendingPayments">KES 0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-danger">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Outstanding</h6>
                        <h3 class="text-danger mb-0" id="outstandingPayments">KES 0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-info">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Today's Collections</h6>
                        <h3 class="text-info mb-0" id="todayCollections">KES 0</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="row mb-3">
            <div class="col-md-2">
                <input type="text" class="form-control" id="paymentSearch" placeholder="Search...">
            </div>
            <div class="col-md-2">
                <select class="form-select" id="paymentStatusFilter">
                    <option value="">All Status</option>
                    <option value="completed">Completed</option>
                    <option value="pending">Pending</option>
                    <option value="failed">Failed</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" id="paymentMethodFilter">
                    <option value="">All Methods</option>
                    <option value="cash">Cash</option>
                    <option value="mpesa">M-Pesa</option>
                    <option value="bank_transfer">Bank Transfer</option>
                    <option value="cheque">Cheque</option>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control" id="dateFrom">
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control" id="dateTo">
            </div>
            <div class="col-md-2">
                <button class="btn btn-secondary w-100" id="clearFilters">Clear</button>
            </div>
        </div>

        <!-- Payments Table -->
        <div class="table-responsive">
            <table class="table table-hover" id="paymentsTable">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Receipt No.</th>
                        <th>Student</th>
                        <th>Amount</th>
                        <th>Method</th>
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
            <ul class="pagination justify-content-center" id="paymentsPagination">
                <!-- Dynamic pagination -->
            </ul>
        </nav>
    </div>
</div>

<!-- Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Record Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="paymentForm">
                    <input type="hidden" id="payment_id">

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Student*</label>
                            <select class="form-select" id="student_id" required></select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Payment Date*</label>
                            <input type="date" class="form-control" id="payment_date" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Amount (KES)*</label>
                            <input type="number" class="form-control" id="payment_amount" step="0.01" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Payment Method*</label>
                            <select class="form-select" id="payment_method" required>
                                <option value="">Select Method</option>
                                <option value="cash">Cash</option>
                                <option value="mpesa">M-Pesa</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="cheque">Cheque</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Transaction Reference</label>
                        <input type="text" class="form-control" id="transaction_reference">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" id="payment_notes" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="savePaymentBtn">Save Payment</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Initialize payments management when page loads
    document.addEventListener('DOMContentLoaded', function () {
        // TODO: Implement paymentsManagementController in js/pages/payments.js
        console.log('Payment Management page loaded');
    });
</script>