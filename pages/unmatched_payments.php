<?php
/**
 * Unmatched Payments
 *
 * Purpose: View and match unmatched M-Pesa/bank payments to students
 * Features:
 * - List unmatched payments
 * - Match payments to students
 * - Filter by source, date, status
 * - Export functionality
 */
?>

<div>
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1"><i class="fas fa-question-circle me-2"></i>Unmatched Payments</h4>
                    <p class="text-muted mb-0">Review and match unidentified payments to student accounts</p>
                </div>
                <button class="btn btn-success" onclick="UnmatchedPaymentsController.exportCSV()">
                    <i class="fas fa-file-csv me-1"></i> Export
                </button>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-danger bg-opacity-10 p-3 me-3">
                            <i class="fas fa-exclamation-triangle text-danger fa-lg"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Total Unmatched</h6>
                            <h4 class="mb-0" id="statTotal">0</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-warning bg-opacity-10 p-3 me-3">
                            <i class="fas fa-money-bill-wave text-warning fa-lg"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Unmatched Amount</h6>
                            <h4 class="mb-0" id="statAmount">KES 0</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-success bg-opacity-10 p-3 me-3">
                            <i class="fas fa-check-circle text-success fa-lg"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Matched Today</h6>
                            <h4 class="mb-0" id="statMatchedToday">0</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-info bg-opacity-10 p-3 me-3">
                            <i class="fas fa-clock text-info fa-lg"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Pending Review</h6>
                            <h4 class="mb-0" id="statPending">0</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <input type="text" class="form-control" id="searchInput" placeholder="Search transactions...">
                </div>
                <div class="col-md-2">
                    <select class="form-select" id="sourceFilter">
                        <option value="">All Sources</option>
                        <option value="mpesa">M-Pesa</option>
                        <option value="bank">Bank</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="date" class="form-control" id="dateFrom" placeholder="From">
                </div>
                <div class="col-md-2">
                    <input type="date" class="form-control" id="dateTo" placeholder="To">
                </div>
                <div class="col-md-2">
                    <select class="form-select" id="statusFilter">
                        <option value="">All Status</option>
                        <option value="unmatched">Unmatched</option>
                        <option value="pending">Pending Review</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <button class="btn btn-outline-secondary w-100" onclick="UnmatchedPaymentsController.refresh()">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <h6 class="mb-0"><i class="fas fa-table me-2"></i>Unmatched Payments</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="dataTable">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Transaction ID</th>
                            <th>Date</th>
                            <th>Amount (KES)</th>
                            <th>Source</th>
                            <th>Payer Name</th>
                            <th>Reference</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">Loading...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Match Payment Modal -->
<div class="modal fade" id="matchModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-link me-2"></i>Match Payment to Student</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Transaction Details</label>
                    <div class="bg-light p-3 rounded" id="matchTransactionInfo">--</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Search Student</label>
                    <input type="text" class="form-control" id="studentSearch"
                        placeholder="Type student name or admission number...">
                </div>
                <div id="studentResults" class="list-group mb-3"></div>
                <input type="hidden" id="selectedStudentId">
                <div class="mb-3">
                    <label class="form-label">Notes</label>
                    <textarea class="form-control" id="matchNotes" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="UnmatchedPaymentsController.confirmMatch()">
                    <i class="fas fa-check me-1"></i> Match Payment
                </button>
            </div>
        </div>
    </div>
</div>

<script src="<?= $appBase ?>/js/pages/unmatched_payments.js?v=<?php echo time(); ?>"></script>