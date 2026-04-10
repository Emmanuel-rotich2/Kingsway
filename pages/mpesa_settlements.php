<?php
/**
 * M-Pesa Settlements Management Page
 *
 * Features:
 * - 4 KPI stat cards (Total Settlements, Total Amount, Pending Settlement, Last Settlement Date)
 * - Filters: search, date range, status
 * - Data table with actions
 * - View settlement details modal (transaction list)
 * - Icon: fa-mobile-alt
 */
?>


<div>
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1"><i class="fas fa-mobile-alt me-2 text-success"></i>M-Pesa Settlements</h2>
            <p class="text-muted mb-0">View and track M-Pesa settlement reports and transaction breakdowns</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-secondary" onclick="MpesaSettlementsController.exportCSV()">
                <i class="fas fa-download me-1"></i> Export CSV
            </button>
            <button class="btn btn-outline-success" onclick="MpesaSettlementsController.refreshData()">
                <i class="fas fa-sync-alt me-1"></i> Refresh
            </button>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-success bg-opacity-10 p-3 me-3">
                            <i class="fas fa-mobile-alt fa-lg text-success"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Total Settlements</h6>
                            <h3 class="mb-0" id="kpiTotalSettlements">0</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-primary bg-opacity-10 p-3 me-3">
                            <i class="fas fa-money-bill-wave fa-lg text-primary"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Total Amount (KES)</h6>
                            <h3 class="mb-0" id="kpiTotalAmount">KES 0</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-warning bg-opacity-10 p-3 me-3">
                            <i class="fas fa-hourglass-half fa-lg text-warning"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Pending Settlement</h6>
                            <h3 class="mb-0" id="kpiPendingSettlement">KES 0</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-info bg-opacity-10 p-3 me-3">
                            <i class="fas fa-calendar-day fa-lg text-info"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Last Settlement Date</h6>
                            <h4 class="mb-0" id="kpiLastSettlementDate">N/A</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters Row -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small text-muted">Search</label>
                    <input type="text" class="form-control" id="msSearch" placeholder="Search reference, status..." oninput="MpesaSettlementsController.filterData()">
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted">Date From</label>
                    <input type="date" class="form-control" id="msDateFrom" onchange="MpesaSettlementsController.filterData()">
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted">Date To</label>
                    <input type="date" class="form-control" id="msDateTo" onchange="MpesaSettlementsController.filterData()">
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted">Status</label>
                    <select class="form-select" id="msStatusFilter" onchange="MpesaSettlementsController.filterData()">
                        <option value="">All Statuses</option>
                        <option value="Settled">Settled</option>
                        <option value="Pending">Pending</option>
                        <option value="Failed">Failed</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-outline-secondary w-100" onclick="MpesaSettlementsController.clearFilters()">
                        <i class="fas fa-times me-1"></i> Clear
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="mpesaSettlementsTable">
                    <thead class="table-light">
                        <tr>
                            <th width="50">#</th>
                            <th>Settlement Date</th>
                            <th>Reference</th>
                            <th class="text-center">Transaction Count</th>
                            <th class="text-end">Gross Amount (KES)</th>
                            <th class="text-end">Charges (KES)</th>
                            <th class="text-end">Net Amount (KES)</th>
                            <th class="text-center">Status</th>
                            <th class="text-center" width="120">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="mpesaSettlementsTableBody">
                        <tr>
                            <td colspan="9" class="text-center py-4">
                                <div class="spinner-border text-success spinner-border-sm me-2" role="status"></div>
                                Loading M-Pesa settlements...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white d-flex justify-content-between align-items-center">
            <span class="text-muted small" id="msTableInfo">Showing 0 records</span>
            <nav>
                <ul class="pagination pagination-sm mb-0" id="msPagination"></ul>
            </nav>
        </div>
    </div>
</div>

<!-- View Settlement Details Modal -->
<div class="modal fade" id="settlementDetailsModal" tabindex="-1" aria-labelledby="settlementDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="settlementDetailsModalLabel">
                    <i class="fas fa-mobile-alt me-2"></i> Settlement Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Settlement Summary -->
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="card bg-light border-0">
                            <div class="card-body text-center py-2">
                                <small class="text-muted">Reference</small>
                                <h6 class="mb-0" id="detailRef">-</h6>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-light border-0">
                            <div class="card-body text-center py-2">
                                <small class="text-muted">Settlement Date</small>
                                <h6 class="mb-0" id="detailDate">-</h6>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-light border-0">
                            <div class="card-body text-center py-2">
                                <small class="text-muted">Net Amount</small>
                                <h6 class="mb-0 text-success" id="detailNetAmount">KES 0</h6>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Transaction List -->
                <h6 class="mb-3"><i class="fas fa-list me-1"></i> Transactions in this Settlement</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-striped">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Transaction ID</th>
                                <th>Phone Number</th>
                                <th>Name</th>
                                <th class="text-end">Amount (KES)</th>
                                <th>Date/Time</th>
                            </tr>
                        </thead>
                        <tbody id="settlementTransactionsBody">
                            <tr><td colspan="6" class="text-center py-3 text-muted">No transactions loaded</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-outline-success" onclick="MpesaSettlementsController.printSettlement()">
                    <i class="fas fa-print me-1"></i> Print
                </button>
            </div>
        </div>
    </div>
</div>

<script src="<?= $appBase ?>js/pages/mpesa_settlements.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof MpesaSettlementsController !== 'undefined') {
            MpesaSettlementsController.init();
        }
    });
</script>
