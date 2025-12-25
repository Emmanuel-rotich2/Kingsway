<?php
/**
 * Student Fees Page (Per-student fee tracking)
 * HTML structure only - logic will be in js/pages/student_fees.js
 * Embedded in app_layout.php
 */
?>

<div class="card shadow-sm">
    <div class="card-header bg-gradient bg-primary text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="fas fa-money-check-alt"></i> Student Fees Management</h4>
            <div class="btn-group">
                <button class="btn btn-light btn-sm" id="recordPaymentBtn" data-permission="payments_create">
                    <i class="bi bi-plus-circle"></i> Record Payment
                </button>
                <button class="btn btn-outline-light btn-sm" id="exportBtn">
                    <i class="bi bi-download"></i> Export
                </button>
            </div>
        </div>
    </div>

    <div class="card-body">
        <!-- Search & Filter -->
        <div class="row mb-4">
            <div class="col-md-3">
                <input type="text" class="form-control" id="searchStudent" placeholder="Search student...">
            </div>
            <div class="col-md-2">
                <select class="form-select" id="classFilter">
                    <option value="">All Classes</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" id="statusFilter">
                    <option value="">All Status</option>
                    <option value="paid">Fully Paid</option>
                    <option value="partial">Partial Payment</option>
                    <option value="unpaid">Not Paid</option>
                    <option value="overpaid">Overpaid</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" id="termFilter">
                    <option value="">Current Term</option>
                    <option value="1">Term 1</option>
                    <option value="2">Term 2</option>
                    <option value="3">Term 3</option>
                </select>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-primary">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Total Expected</h6>
                        <h3 class="text-primary mb-0" id="totalExpected">KES 0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Total Collected</h6>
                        <h3 class="text-success mb-0" id="totalCollected">KES 0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-warning">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Total Outstanding</h6>
                        <h3 class="text-warning mb-0" id="totalOutstanding">KES 0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-info">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Collection Rate</h6>
                        <h3 class="text-info mb-0" id="collectionRate">0%</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Students Table -->
        <div class="table-responsive">
            <table class="table table-hover" id="feesTable">
                <thead class="table-light">
                    <tr>
                        <th>Admission No</th>
                        <th>Student Name</th>
                        <th>Class</th>
                        <th>Expected (KES)</th>
                        <th>Paid (KES)</th>
                        <th>Balance (KES)</th>
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

<!-- Student Fee Details Modal -->
<div class="modal fade" id="feeDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <div>
                    <h5 class="modal-title">Fee Details</h5>
                    <p class="mb-0"><strong>Student:</strong> <span id="studentName"></span> (<span id="admNo"></span>)
                    </p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Fee Summary -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card border-primary">
                            <div class="card-body text-center">
                                <h6>Total Fee</h6>
                                <h4 id="modalTotalFee">KES 0</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-success">
                            <div class="card-body text-center">
                                <h6>Total Paid</h6>
                                <h4 id="modalTotalPaid">KES 0</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-warning">
                            <div class="card-body text-center">
                                <h6>Balance</h6>
                                <h4 id="modalBalance">KES 0</h4>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Fee Breakdown -->
                <h6 class="mb-2">Fee Breakdown</h6>
                <table class="table table-sm table-bordered mb-4">
                    <thead class="table-light">
                        <tr>
                            <th>Fee Type</th>
                            <th>Amount (KES)</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="feeBreakdownBody">
                        <!-- Dynamic content -->
                    </tbody>
                </table>

                <!-- Payment History -->
                <h6 class="mb-2">Payment History</h6>
                <table class="table table-sm table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Receipt No</th>
                            <th>Amount (KES)</th>
                            <th>Method</th>
                            <th>Received By</th>
                        </tr>
                    </thead>
                    <tbody id="paymentHistoryBody">
                        <!-- Dynamic content -->
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="printStatementBtn">
                    <i class="bi bi-printer"></i> Print Statement
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Record Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Record Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="paymentForm">
                    <input type="hidden" id="studentId">
                    <div class="mb-3">
                        <label class="form-label">Student*</label>
                        <select class="form-select" id="paymentStudent" required></select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Amount (KES)*</label>
                        <input type="number" class="form-control" id="amount" required min="1" step="0.01">
                        <small class="text-muted">Outstanding: <span id="outstandingAmount">KES 0</span></small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payment Method*</label>
                        <select class="form-select" id="paymentMethod" required>
                            <option value="cash">Cash</option>
                            <option value="mpesa">M-Pesa</option>
                            <option value="bank">Bank Transfer</option>
                            <option value="cheque">Cheque</option>
                        </select>
                    </div>
                    <div class="mb-3" id="referenceDiv">
                        <label class="form-label">Transaction Reference</label>
                        <input type="text" class="form-control" id="reference" placeholder="e.g., M-Pesa code">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payment Date*</label>
                        <input type="date" class="form-control" id="paymentDate" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" rows="2"></textarea>
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
    document.addEventListener('DOMContentLoaded', function () {
        // TODO: Implement studentFeesController in js/pages/student_fees.js
        console.log('Student Fees page loaded');
    });
</script>