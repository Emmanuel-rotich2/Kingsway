<?php
/**
 * Asset Purchases Page
 * Capital expenditure log — record and track school asset purchases.
 * Partial — no DOCTYPE/html/head/body (included by home.php shell).
 */
?>

<div>
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1"><i class="fas fa-boxes me-2 text-primary"></i>Asset Purchases</h2>
            <p class="text-muted mb-0">Capital expenditure log — track all school asset acquisitions</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-secondary" onclick="assetPurchasesController.exportCSV()">
                <i class="fas fa-download me-1"></i> Export CSV
            </button>
            <button class="btn btn-primary" onclick="assetPurchasesController.showModal()">
                <i class="fas fa-plus me-1"></i> Add Purchase
            </button>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-primary bg-opacity-10 p-3 me-3">
                            <i class="fas fa-layer-group fa-lg text-primary"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Total Assets</h6>
                            <h3 class="mb-0" id="apStatTotal">—</h3>
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
                            <i class="fas fa-coins fa-lg text-success"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Total Value (KES)</h6>
                            <h3 class="mb-0" id="apStatValue">—</h3>
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
                            <i class="fas fa-calendar-alt fa-lg text-info"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">This Year Value</h6>
                            <h3 class="mb-0" id="apStatThisYear">—</h3>
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
                            <i class="fas fa-truck fa-lg text-warning"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Pending Delivery</h6>
                            <h3 class="mb-0" id="apStatPending">—</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small text-muted">Search</label>
                    <input type="text" class="form-control" id="apSearch" placeholder="Asset name, number, vendor..." oninput="assetPurchasesController._applyFilters()">
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted">Category</label>
                    <select class="form-select" id="apFilterCategory" onchange="assetPurchasesController._applyFilters()">
                        <option value="">All Categories</option>
                        <option value="Furniture">Furniture</option>
                        <option value="Electronics">Electronics</option>
                        <option value="Vehicles">Vehicles</option>
                        <option value="Equipment">Equipment</option>
                        <option value="Building">Building</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted">Year</label>
                    <select class="form-select" id="apFilterYear" onchange="assetPurchasesController._applyFilters()">
                        <option value="">All Years</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted">Status</label>
                    <select class="form-select" id="apFilterStatus" onchange="assetPurchasesController._applyFilters()">
                        <option value="">All Statuses</option>
                        <option value="Active">Active</option>
                        <option value="Disposed">Disposed</option>
                        <option value="Under Repair">Under Repair</option>
                        <option value="Pending Delivery">Pending Delivery</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <button class="btn btn-outline-secondary w-100" onclick="assetPurchasesController._clearFilters()" title="Clear filters">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Asset No</th>
                            <th>Asset Name</th>
                            <th>Category</th>
                            <th>Vendor</th>
                            <th>Purchase Date</th>
                            <th class="text-end">Cost (KES)</th>
                            <th>Condition</th>
                            <th>Location</th>
                            <th class="text-center">Status</th>
                            <th class="text-center" width="110">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="apTableBody">
                        <tr>
                            <td colspan="10" class="text-center py-4">
                                <div class="spinner-border spinner-border-sm text-primary me-2"></div>
                                Loading assets...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white">
            <span class="text-muted small" id="apTableInfo">Showing 0 records</span>
        </div>
    </div>
</div>

<!-- Add / Edit Asset Modal -->
<div class="modal fade" id="apModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="apModalTitle"><i class="fas fa-boxes me-2"></i>Add Asset Purchase</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger d-none" id="apError"></div>
                <input type="hidden" id="apId">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Asset No <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="apAssetNo" placeholder="e.g. AST-2026-001">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Asset Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="apName" placeholder="e.g. Dell Laptop OptiPlex 3000">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Category <span class="text-danger">*</span></label>
                        <select class="form-select" id="apCategory">
                            <option value="">Select category</option>
                            <option value="Furniture">Furniture</option>
                            <option value="Electronics">Electronics</option>
                            <option value="Vehicles">Vehicles</option>
                            <option value="Equipment">Equipment</option>
                            <option value="Building">Building</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Vendor</label>
                        <select class="form-select" id="apVendor">
                            <option value="">Select vendor</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Purchase Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="apDate">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Cost (KES) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="apCost" min="0" step="0.01" placeholder="0.00">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Quantity</label>
                        <input type="number" class="form-control" id="apQuantity" min="1" value="1">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Condition</label>
                        <select class="form-select" id="apCondition">
                            <option value="New">New</option>
                            <option value="Good">Good</option>
                            <option value="Fair">Fair</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Location</label>
                        <input type="text" class="form-control" id="apLocation" placeholder="e.g. Classroom 3A, Lab 2">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Invoice Number</label>
                        <input type="text" class="form-control" id="apInvoice" placeholder="Invoice / LPO No">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <select class="form-select" id="apStatus">
                            <option value="Active">Active</option>
                            <option value="Pending Delivery">Pending Delivery</option>
                            <option value="Under Repair">Under Repair</option>
                            <option value="Disposed">Disposed</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" id="apNotes" rows="2" placeholder="Additional notes..."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="assetPurchasesController.saveAsset()">
                    <i class="fas fa-save me-1"></i> Save Asset
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Dispose Asset Modal -->
<div class="modal fade" id="apDisposeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-trash me-2"></i>Dispose Asset</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger d-none" id="apDisposeError"></div>
                <input type="hidden" id="apDisposeId">
                <p class="text-muted mb-3">Recording disposal for: <strong id="apDisposeName"></strong></p>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Disposal Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="apDisposeDate">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Disposal Method <span class="text-danger">*</span></label>
                        <select class="form-select" id="apDisposeMethod">
                            <option value="">Select method</option>
                            <option value="Sold">Sold</option>
                            <option value="Written-off">Written-off</option>
                            <option value="Donated">Donated</option>
                            <option value="Scrapped">Scrapped</option>
                        </select>
                    </div>
                    <div class="col-md-6" id="apDisposeSaleRow">
                        <label class="form-label">Amount Received (KES)</label>
                        <input type="number" class="form-control" id="apDisposeSaleAmt" min="0" step="0.01" placeholder="0.00">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" id="apDisposeNotes" rows="2" placeholder="Disposal notes..."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="assetPurchasesController.confirmDispose()">
                    <i class="fas fa-trash me-1"></i> Confirm Disposal
                </button>
            </div>
        </div>
    </div>
</div>

<script src="<?= $appBase ?>/js/pages/asset_purchases.js"></script>
