<?php
/**
 * Manage Uniform Sales — PARTIAL (injected into app shell via app_layout.php)
 * JS controller: js/pages/uniform_sales.js
 */
?>

<div>
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1"><i class="bi bi-bag-check me-2 text-primary"></i>Uniform Sales</h4>
            <p class="text-muted mb-0">Manage uniform inventory, record sales, and track payments</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-primary" onclick="UniformSalesController.showNewSaleModal()">
                <i class="bi bi-cart-plus me-1"></i>New Sale
            </button>
            <button class="btn btn-success" onclick="UniformSalesController.showRestockModal()">
                <i class="bi bi-boxes me-1"></i>Restock
            </button>
            <button class="btn btn-outline-secondary" onclick="UniformSalesController.refresh()">
                <i class="bi bi-arrow-clockwise me-1"></i>Refresh
            </button>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="dash-stat dsc-blue">
                <div class="dash-stat-value" id="statMonthlySales">KES 0</div>
                <div class="dash-stat-label">This Month Sales</div>
                <div class="dash-stat-sub" id="statMonthlySalesCount">0 sales</div>
                <i class="bi bi-cart-fill dash-stat-icon"></i>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="dash-stat dsc-green">
                <div class="dash-stat-value" id="statPaidAmount">KES 0</div>
                <div class="dash-stat-label">Collected Revenue</div>
                <div class="dash-stat-sub">Fully paid</div>
                <i class="bi bi-check-circle-fill dash-stat-icon"></i>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="dash-stat dsc-amber">
                <div class="dash-stat-value" id="statPendingAmount">KES 0</div>
                <div class="dash-stat-label">Pending Payments</div>
                <div class="dash-stat-sub">Outstanding balance</div>
                <i class="bi bi-clock-fill dash-stat-icon"></i>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="dash-stat dsc-red">
                <div class="dash-stat-value" id="statLowStock">0</div>
                <div class="dash-stat-label">Low Stock Items</div>
                <div class="dash-stat-sub">Need restocking</div>
                <i class="bi bi-exclamation-triangle-fill dash-stat-icon"></i>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <ul class="nav nav-tabs mb-0" id="uniformTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="inventory-tab" data-bs-toggle="tab"
                    data-bs-target="#inventory" type="button">
                <i class="bi bi-boxes me-1"></i>Inventory
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="sales-tab" data-bs-toggle="tab"
                    data-bs-target="#sales" type="button">
                <i class="bi bi-receipt me-1"></i>Sales History
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="lowstock-tab" data-bs-toggle="tab"
                    data-bs-target="#lowstock" type="button">
                <i class="bi bi-exclamation-circle me-1"></i>Low Stock
                <span class="badge bg-danger ms-1" id="lowStockBadge">0</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="reports-tab" data-bs-toggle="tab"
                    data-bs-target="#reports" type="button">
                <i class="bi bi-bar-chart me-1"></i>Reports
            </button>
        </li>
    </ul>

    <div class="tab-content pt-4" id="uniformTabsContent">

        <!-- Inventory Tab -->
        <div class="tab-pane fade show active" id="inventory" role="tabpanel">
            <div class="row" id="uniformItemsContainer">
                <div class="col-12 text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="text-muted mt-2">Loading uniform inventory...</p>
                </div>
            </div>
        </div>

        <!-- Sales History Tab -->
        <div class="tab-pane fade" id="sales" role="tabpanel">
            <div class="card dash-card mb-3">
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-md-3">
                            <input type="text" id="searchSales" class="form-control"
                                   placeholder="Search student name...">
                        </div>
                        <div class="col-md-2">
                            <select id="filterItem" class="form-select">
                                <option value="">All Items</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select id="filterPaymentStatus" class="form-select">
                                <option value="">All Status</option>
                                <option value="paid">Paid</option>
                                <option value="pending">Pending</option>
                                <option value="partial">Partial</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <input type="date" id="filterDateFrom" class="form-control">
                        </div>
                        <div class="col-md-2">
                            <input type="date" id="filterDateTo" class="form-control">
                        </div>
                        <div class="col-md-1">
                            <button class="btn btn-outline-primary w-100"
                                    onclick="UniformSalesController.loadSales()">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card dash-card">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th><th>Student</th><th>Item</th><th>Size</th>
                                <th>Qty</th><th>Amount</th><th>Status</th><th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="salesTableBody">
                            <tr><td colspan="8" class="text-center py-4">
                                <div class="spinner-border spinner-border-sm text-primary me-2"></div>Loading sales...
                            </td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">
                    <nav><ul class="pagination pagination-sm mb-0 justify-content-center" id="salesPagination"></ul></nav>
                </div>
            </div>
        </div>

        <!-- Low Stock Tab -->
        <div class="tab-pane fade" id="lowstock" role="tabpanel">
            <div class="card dash-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-exclamation-triangle text-warning me-2"></i>Low Stock Alert</h6>
                    <button class="btn btn-success btn-sm" onclick="UniformSalesController.showRestockModal()">
                        <i class="bi bi-boxes me-1"></i>Restock Items
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr><th>Item</th><th>Size</th><th>Available</th><th>Sold</th><th>Unit Price</th><th>Status</th><th>Action</th></tr>
                        </thead>
                        <tbody id="lowStockTableBody">
                            <tr><td colspan="7" class="text-center py-4">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Reports Tab -->
        <div class="tab-pane fade" id="reports" role="tabpanel">
            <div class="row g-3 mb-3">
                <div class="col-md-3">
                    <label class="form-label">From Date</label>
                    <input type="date" id="reportDateFrom" class="form-control" value="<?= date('Y-m-01') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">To Date</label>
                    <input type="date" id="reportDateTo" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button class="btn btn-primary" onclick="UniformSalesController.loadReport()">
                        <i class="bi bi-bar-chart me-1"></i>Generate Report
                    </button>
                </div>
            </div>
            <div id="reportContainer">
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-pie-chart fs-1 mb-3 d-block"></i>
                    <p>Select date range and click Generate Report</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── New Sale Modal ──────────────────────────────────────────────── -->
<div class="modal fade" id="newSaleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-cart-plus me-2"></i>New Uniform Sale</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="newSaleForm">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Student <span class="text-danger">*</span></label>
                            <select id="saleStudentId" name="student_id" class="form-select" required>
                                <option value="">Select Student...</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Uniform Item <span class="text-danger">*</span></label>
                            <select id="saleItemId" name="item_id" class="form-select" required>
                                <option value="">Select Uniform...</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Size <span class="text-danger">*</span></label>
                            <select id="saleSize" name="size" class="form-select" required>
                                <option value="">Select Size...</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Quantity <span class="text-danger">*</span></label>
                            <input type="number" id="saleQuantity" name="quantity" class="form-control"
                                   min="1" value="1" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Unit Price (KES) <span class="text-danger">*</span></label>
                            <input type="number" id="saleUnitPrice" name="unit_price" class="form-control"
                                   step="0.01" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Total Amount</label>
                            <input type="text" id="saleTotalAmount" class="form-control bg-light" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Stock Available</label>
                            <input type="text" id="saleStockAvailable" class="form-control bg-light" readonly>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea id="saleNotes" name="notes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submitSaleBtn">
                        <i class="bi bi-check me-1"></i>Record Sale
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── Restock Modal ───────────────────────────────────────────────── -->
<div class="modal fade" id="restockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-boxes me-2"></i>Restock Uniform</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="restockForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Uniform Item <span class="text-danger">*</span></label>
                        <select id="restockItemId" name="item_id" class="form-select" required>
                            <option value="">Select Uniform...</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Size <span class="text-danger">*</span></label>
                        <select id="restockSize" name="size" class="form-select" required>
                            <option value="">Select Size...</option>
                        </select>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Quantity to Add <span class="text-danger">*</span></label>
                            <input type="number" id="restockQuantity" name="quantity" class="form-control" min="1" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Unit Price (KES)</label>
                            <input type="number" id="restockUnitPrice" name="unit_price" class="form-control" step="0.01">
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label">Notes</label>
                        <textarea id="restockNotes" name="notes" class="form-control" rows="2"
                                  placeholder="Supplier invoice #, batch number..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success" id="submitRestockBtn">
                        <i class="bi bi-boxes me-1"></i>Restock
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── View Sizes Modal ────────────────────────────────────────────── -->
<div class="modal fade" id="viewSizesModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewSizesTitle">Uniform Sizes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="viewSizesContent"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- ── Record Payment Modal ────────────────────────────────────────── -->
<div class="modal fade" id="uniformPaymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-cash-coin me-2"></i>Record Payment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3" id="upSaleInfo"></p>
                <input type="hidden" id="upSaleId">
                <div class="row g-3">
                    <div class="col-6">
                        <label class="form-label fw-semibold">Amount (KES) <span class="text-danger">*</span></label>
                        <input type="number" id="upAmount" class="form-control" min="1" step="0.01">
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">Payment Method</label>
                        <select id="upMethod" class="form-select">
                            <option value="cash">Cash</option>
                            <option value="mpesa">M-Pesa</option>
                            <option value="bank">Bank Transfer</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Reference / M-Pesa Code</label>
                        <input type="text" id="upReference" class="form-control" placeholder="Optional">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <input type="text" id="upNotes" class="form-control" placeholder="Optional">
                    </div>
                </div>
                <div id="upError" class="alert alert-danger mt-3 d-none"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-success" id="upSaveBtn"
                        onclick="UniformSalesController.saveUniformPayment()">
                    <i class="bi bi-check me-1"></i>Record Payment
                </button>
            </div>
        </div>
    </div>
</div>

<script src="<?= $appBase ?>/js/pages/uniform_sales.js"></script>
