<?php
/**
 * Purchase Orders Management Page
 *
 * Features:
 * - 4 KPI stat cards (Total POs, Pending Approval, Approved, Total Value)
 * - Filters: search, status, vendor, date range
 * - Data table with CRUD actions
 * - Create/Edit PO modal
 * - Icon: fa-file-invoice
 */
?>


<div>
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1"><i class="fas fa-file-invoice me-2 text-primary"></i>Purchase Orders</h2>
            <p class="text-muted mb-0">Create and manage purchase orders for vendors and suppliers</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-secondary" onclick="PurchaseOrdersController.exportCSV()">
                <i class="fas fa-download me-1"></i> Export CSV
            </button>
            <button class="btn btn-primary" onclick="PurchaseOrdersController.showCreateModal()">
                <i class="fas fa-plus me-1"></i> New Purchase Order
            </button>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-primary bg-opacity-10 p-3 me-3">
                            <i class="fas fa-file-invoice fa-lg text-primary"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Total POs</h6>
                            <h3 class="mb-0" id="kpiTotalPOs">0</h3>
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
                            <i class="fas fa-clock fa-lg text-warning"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Pending Approval</h6>
                            <h3 class="mb-0" id="kpiPendingApproval">0</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-success bg-opacity-10 p-3 me-3">
                            <i class="fas fa-check-circle fa-lg text-success"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Approved</h6>
                            <h3 class="mb-0" id="kpiApproved">0</h3>
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
                            <i class="fas fa-money-bill-wave fa-lg text-info"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Total Value (KES)</h6>
                            <h3 class="mb-0" id="kpiTotalValue">KES 0</h3>
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
                <div class="col-md-3">
                    <label class="form-label small text-muted">Search</label>
                    <input type="text" class="form-control" id="poSearch" placeholder="Search PO number, vendor, description..." oninput="PurchaseOrdersController.filterData()">
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted">Status</label>
                    <select class="form-select" id="poStatusFilter" onchange="PurchaseOrdersController.filterData()">
                        <option value="">All Statuses</option>
                        <option value="Draft">Draft</option>
                        <option value="Pending">Pending</option>
                        <option value="Approved">Approved</option>
                        <option value="Received">Received</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted">Vendor</label>
                    <select class="form-select" id="poVendorFilter" onchange="PurchaseOrdersController.filterData()">
                        <option value="">All Vendors</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted">Date From</label>
                    <input type="date" class="form-control" id="poDateFrom" onchange="PurchaseOrdersController.filterData()">
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted">Date To</label>
                    <input type="date" class="form-control" id="poDateTo" onchange="PurchaseOrdersController.filterData()">
                </div>
                <div class="col-md-1">
                    <button class="btn btn-outline-secondary w-100" onclick="PurchaseOrdersController.clearFilters()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="purchaseOrdersTable">
                    <thead class="table-light">
                        <tr>
                            <th width="50">#</th>
                            <th>PO Number</th>
                            <th>Date</th>
                            <th>Vendor</th>
                            <th>Description</th>
                            <th class="text-center">Items Count</th>
                            <th class="text-end">Total (KES)</th>
                            <th class="text-center">Status</th>
                            <th class="text-center" width="140">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="purchaseOrdersTableBody">
                        <tr>
                            <td colspan="9" class="text-center py-4">
                                <div class="spinner-border text-primary spinner-border-sm me-2" role="status"></div>
                                Loading purchase orders...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white d-flex justify-content-between align-items-center">
            <span class="text-muted small" id="poTableInfo">Showing 0 records</span>
            <nav>
                <ul class="pagination pagination-sm mb-0" id="poPagination"></ul>
            </nav>
        </div>
    </div>
</div>

<!-- Create/Edit PO Modal -->
<div class="modal fade" id="purchaseOrderModal" tabindex="-1" aria-labelledby="purchaseOrderModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="purchaseOrderModalLabel">
                    <i class="fas fa-file-invoice me-2"></i> New Purchase Order
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="purchaseOrderForm">
                    <input type="hidden" id="po_id">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Vendor <span class="text-danger">*</span></label>
                            <select class="form-select" id="po_vendor" required>
                                <option value="">Select Vendor</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">PO Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="po_date" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="po_description" placeholder="Brief description of the purchase order" required>
                        </div>

                        <!-- PO Items -->
                        <div class="col-12">
                            <label class="form-label">Items <span class="text-danger">*</span></label>
                            <div id="poItemsContainer">
                                <div class="row g-2 mb-2 po-item-row" data-index="0">
                                    <div class="col-md-4">
                                        <input type="text" class="form-control form-control-sm" placeholder="Item name" name="item_name[]" required>
                                    </div>
                                    <div class="col-md-2">
                                        <input type="number" class="form-control form-control-sm" placeholder="Qty" name="item_qty[]" min="1" value="1" required onchange="PurchaseOrdersController.recalcTotal()">
                                    </div>
                                    <div class="col-md-3">
                                        <input type="number" class="form-control form-control-sm" placeholder="Unit Price" name="item_price[]" step="0.01" min="0" required onchange="PurchaseOrdersController.recalcTotal()">
                                    </div>
                                    <div class="col-md-2">
                                        <input type="text" class="form-control form-control-sm" placeholder="Total" name="item_total[]" readonly>
                                    </div>
                                    <div class="col-md-1">
                                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="PurchaseOrdersController.removeItemRow(this)" title="Remove">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm mt-2" onclick="PurchaseOrdersController.addItemRow()">
                                <i class="fas fa-plus me-1"></i> Add Item
                            </button>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Grand Total (KES)</label>
                            <input type="text" class="form-control fw-bold" id="po_grand_total" readonly>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" id="po_notes" rows="3" placeholder="Additional notes or instructions"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="PurchaseOrdersController.savePO()">
                    <i class="fas fa-save me-1"></i> Save Purchase Order
                </button>
            </div>
        </div>
    </div>
</div>

<script src="<?= $appBase ?>js/pages/purchase_orders.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof PurchaseOrdersController !== 'undefined') {
            PurchaseOrdersController.init();
        }
    });
</script>
