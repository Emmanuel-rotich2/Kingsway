<?php
/**
 * Manage Stock Page (Stock Movement & Adjustments)
 * HTML structure only - logic will be in js/pages/stock.js
 * Embedded in app_layout.php
 */
?>

<div class="card shadow-sm">
    <div class="card-header bg-gradient bg-warning text-dark">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="fas fa-exchange-alt"></i> Stock Movement & Adjustments</h4>
            <div class="btn-group">
                <button class="btn btn-dark btn-sm" id="addStockBtn" data-permission="stock_manage">
                    <i class="bi bi-plus-circle"></i> Add Stock
                </button>
                <button class="btn btn-outline-dark btn-sm" id="removeStockBtn" data-permission="stock_manage">
                    <i class="bi bi-dash-circle"></i> Remove Stock
                </button>
                <button class="btn btn-outline-dark btn-sm" id="exportStockBtn">
                    <i class="bi bi-download"></i> Export
                </button>
            </div>
        </div>
    </div>

    <div class="card-body">
        <!-- Stock Movement Stats -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Stock In (Today)</h6>
                        <h3 class="text-success mb-0" id="stockInToday">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-danger">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Stock Out (Today)</h6>
                        <h3 class="text-danger mb-0" id="stockOutToday">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-warning">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Adjustments (This Month)</h6>
                        <h3 class="text-warning mb-0" id="adjustmentsMonth">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-info">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Total Transactions</h6>
                        <h3 class="text-info mb-0" id="totalTransactions">0</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="row mb-3">
            <div class="col-md-2">
                <input type="text" class="form-control" id="stockSearch" placeholder="Search...">
            </div>
            <div class="col-md-2">
                <select class="form-select" id="transactionTypeFilter">
                    <option value="">All Types</option>
                    <option value="stock_in">Stock In</option>
                    <option value="stock_out">Stock Out</option>
                    <option value="adjustment">Adjustment</option>
                    <option value="transfer">Transfer</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" id="itemFilter">
                    <option value="">All Items</option>
                    <!-- Dynamic options -->
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control" id="dateFromFilter">
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control" id="dateToFilter">
            </div>
            <div class="col-md-2">
                <button class="btn btn-secondary w-100" id="clearFiltersBtn">Clear</button>
            </div>
        </div>

        <!-- Stock Movements Table -->
        <div class="table-responsive">
            <table class="table table-hover" id="stockMovementsTable">
                <thead class="table-light">
                    <tr>
                        <th>Date/Time</th>
                        <th>Transaction Type</th>
                        <th>Item</th>
                        <th>Quantity</th>
                        <th>From/To</th>
                        <th>Performed By</th>
                        <th>Reference</th>
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
            <ul class="pagination justify-content-center" id="stockPagination">
                <!-- Dynamic pagination -->
            </ul>
        </nav>
    </div>
</div>

<!-- Stock In Modal -->
<div class="modal fade" id="stockInModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Add Stock</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="stockInForm">
                    <div class="mb-3">
                        <label class="form-label">Item*</label>
                        <select class="form-select" id="stock_in_item" required></select>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Quantity*</label>
                            <input type="number" class="form-control" id="stock_in_quantity" min="1" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Unit Price (KES)</label>
                            <input type="number" class="form-control" id="stock_in_unit_price" step="0.01">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Source*</label>
                            <select class="form-select" id="stock_in_source" required>
                                <option value="">Select Source</option>
                                <option value="purchase">Purchase</option>
                                <option value="donation">Donation</option>
                                <option value="transfer">Transfer</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Date*</label>
                            <input type="date" class="form-control" id="stock_in_date" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Supplier/Source Name</label>
                        <input type="text" class="form-control" id="stock_in_supplier">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Reference/Invoice Number</label>
                        <input type="text" class="form-control" id="stock_in_reference">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" id="stock_in_notes" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="saveStockInBtn">Add Stock</button>
            </div>
        </div>
    </div>
</div>

<!-- Stock Out Modal -->
<div class="modal fade" id="stockOutModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="bi bi-dash-circle"></i> Remove Stock</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="stockOutForm">
                    <div class="mb-3">
                        <label class="form-label">Item*</label>
                        <select class="form-select" id="stock_out_item" required></select>
                        <small class="text-muted">Available: <span id="available_quantity">0</span></small>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Quantity*</label>
                            <input type="number" class="form-control" id="stock_out_quantity" min="1" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Date*</label>
                            <input type="date" class="form-control" id="stock_out_date" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Reason*</label>
                            <select class="form-select" id="stock_out_reason" required>
                                <option value="">Select Reason</option>
                                <option value="issued">Issued to Department</option>
                                <option value="damaged">Damaged/Spoiled</option>
                                <option value="lost">Lost/Missing</option>
                                <option value="transfer">Transfer</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Issued To</label>
                            <input type="text" class="form-control" id="stock_out_issued_to">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Reference/Requisition Number</label>
                        <input type="text" class="form-control" id="stock_out_reference">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" id="stock_out_notes" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="saveStockOutBtn">Remove Stock</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Initialize stock management when page loads
    document.addEventListener('DOMContentLoaded', function () {
        // TODO: Implement stockManagementController in js/pages/stock.js
        console.log('Stock Management page loaded');
    });
</script>