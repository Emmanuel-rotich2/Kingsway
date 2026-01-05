<?php
/**
 * Manage Fees (Student Payments & Balances) Page
 * Tracks student fee payments, balances, and payment status by class and term
 * HTML structure only - logic will be in js/pages/studentFees.js
 * Embedded in app_layout.php
 */
?>

<div class="card shadow-sm">
    <div class="card-header bg-gradient bg-warning text-dark">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="fas fa-receipt"></i> Student Fees & Payments</h4>
            <div class="btn-group">
                <button class="btn btn-dark btn-sm" id="recordPaymentBtn" data-permission="fees_payment_create">
                    <i class="bi bi-plus-circle"></i> Record Payment
                </button>
                <button class="btn btn-outline-dark btn-sm" id="generateRemindersBtn" data-permission="fees_reminder">
                    <i class="bi bi-bell"></i> Send Reminders
                </button>
                <button class="btn btn-outline-dark btn-sm" id="exportFeesReportBtn">
                    <i class="bi bi-download"></i> Export Report
                </button>
            </div>
        </div>
    </div>

    <div class="card-body">
        <!-- Fee Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-info">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Total Expected</h6>
                        <h3 class="text-info mb-0" id="totalExpected">KES 0</h3>
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
                        <h6 class="text-muted mb-2">Outstanding Balance</h6>
                        <h3 class="text-warning mb-0" id="outstandingBalance">KES 0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-danger">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Overdue Accounts</h6>
                        <h3 class="text-danger mb-0" id="overdueCount">0</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="row mb-3">
            <div class="col-md-3">
                <input type="text" class="form-control" id="studentSearch" placeholder="Search student/admission no...">
            </div>
            <div class="col-md-2">
                <select class="form-select" id="classFilterFees">
                    <option value="">All Classes</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" id="termFilterFees">
                    <option value="">All Terms</option>
                    <option value="1">Term 1</option>
                    <option value="2">Term 2</option>
                    <option value="3">Term 3</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" id="paymentStatusFilter">
                    <option value="">All Status</option>
                    <option value="paid">Fully Paid</option>
                    <option value="partial">Partial Payment</option>
                    <option value="outstanding">Outstanding</option>
                    <option value="overdue">Overdue</option>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select" id="yearFilterFees">
                    <option value="">All Academic Years</option>
                </select>
            </div>
        </div>

        <!-- Student Fees Table -->
        <div class="table-responsive">
            <table class="table table-hover table-sm" id="studentFeesTable">
                <thead class="table-light">
                    <tr>
                        <th>Admission No.</th>
                        <th>Student Name</th>
                        <th>Class</th>
                        <th>Term</th>
                        <th>Year</th>
                        <th class="text-end">Expected Fee (KES)</th>
                        <th class="text-end">Paid (KES)</th>
                        <th class="text-end">Balance (KES)</th>
                        <th>Status</th>
                        <th>Last Payment</th>
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
            <ul class="pagination justify-content-center" id="feesPagination">
                <!-- Dynamic pagination -->
            </ul>
        </nav>
    </div>
</div>

<!-- Record Payment Modal -->
<div class="modal fade" id="recordPaymentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Record Student Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="paymentForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Select Student*</label>
                            <select class="form-select" id="payment_student" required></select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Class*</label>
                            <input type="text" class="form-control" id="payment_class" readonly>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Term*</label>
                            <select class="form-select" id="payment_term" required>
                                <option value="">Select Term</option>
                                <option value="1">Term 1</option>
                                <option value="2">Term 2</option>
                                <option value="3">Term 3</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Academic Year*</label>
                            <input type="text" class="form-control" id="payment_year" placeholder="2025" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Expected Fee (KES)</label>
                            <input type="text" class="form-control" id="payment_expected" readonly>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Already Paid (KES)</label>
                            <input type="text" class="form-control" id="payment_already_paid" readonly>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Outstanding Balance (KES)</label>
                            <input type="text" class="form-control" id="payment_balance" readonly>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Amount to Pay (KES)*</label>
                            <input type="number" class="form-control" id="payment_amount" step="0.01" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Payment Method*</label>
                            <select class="form-select" id="payment_method" required>
                                <option value="">Select Method</option>
                                <option value="cash">Cash</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="cheque">Cheque</option>
                                <option value="mpesa">M-Pesa</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Payment Date*</label>
                            <input type="date" class="form-control" id="payment_date" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Reference Number</label>
                        <input type="text" class="form-control" id="payment_reference" placeholder="Check/Receipt/Ref number">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" id="payment_notes" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="savePaymentBtn">Record Payment</button>
            </div>
        </div>
    </div>
</div>

<!-- Student Fee Details Modal -->
<div class="modal fade" id="feeDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Student Fee Payment History</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="feeDetailsContainer">
                    <!-- Dynamic content: Payment history -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="printReceiptBtn">
                    <i class="bi bi-printer"></i> Print Receipt
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    // Initialize student fees management when page loads
    document.addEventListener('DOMContentLoaded', function () {
        console.log('Student Fees & Payments page loaded');
        // TODO: Implement studentFeesController in js/pages/studentFees.js
    });
</script>