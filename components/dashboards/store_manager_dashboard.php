<?php
/**
 * Store Manager Dashboard (Inventory Manager)
 * Role: Inventory Manager (ID 14) — stock, requisitions, purchase orders
 */
?>
<div class="container-fluid py-3" id="store-dashboard">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1"><i class="bi bi-box-seam me-2 text-brown"></i>Inventory Dashboard</h4>
            <p class="text-muted mb-0">Stock management, requisitions, and purchase orders</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-primary btn-sm" onclick="storeDashboardController.navigate('manage_stock')">
                <i class="bi bi-plus me-1"></i>Add Stock
            </button>
            <button class="btn btn-outline-secondary btn-sm" onclick="storeDashboardController.refresh()">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
        </div>
    </div>

    <!-- Stat Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body py-3">
                    <div class="fs-2 mb-1">📦</div>
                    <h4 class="mb-0 text-primary" id="totalItems">0</h4>
                    <small class="text-muted">Total Items</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body py-3">
                    <div class="fs-2 mb-1">⚠️</div>
                    <h4 class="mb-0 text-danger" id="lowStockCount">0</h4>
                    <small class="text-muted">Low Stock</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body py-3">
                    <div class="fs-2 mb-1">📋</div>
                    <h4 class="mb-0 text-warning" id="pendingRequisitions">0</h4>
                    <small class="text-muted">Pending Requisitions</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body py-3">
                    <div class="fs-2 mb-1">💰</div>
                    <h4 class="mb-0 text-success small" id="stockValue">KES 0</h4>
                    <small class="text-muted">Stock Value</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <!-- Low Stock Alerts -->
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-exclamation-triangle text-danger me-2"></i>Low Stock Alerts</h6>
                    <a href="#" onclick="storeDashboardController.navigate('manage_stock')" class="btn btn-sm btn-outline-danger">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr><th>Item</th><th>Current</th><th>Min Level</th><th>Action</th></tr>
                        </thead>
                        <tbody id="lowStockTableBody">
                            <tr><td colspan="4" class="text-center text-muted py-3">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Recent Requisitions -->
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-clipboard-check me-2"></i>Recent Requisitions</h6>
                    <a href="#" onclick="storeDashboardController.navigate('manage_requisitions')" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr><th>Item</th><th>Qty</th><th>Requested By</th><th>Status</th></tr>
                        </thead>
                        <tbody id="requisitionsTableBody">
                            <tr><td colspan="4" class="text-center text-muted py-3">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card shadow-sm mt-3">
                <div class="card-header bg-white"><h6 class="mb-0">Quick Actions</h6></div>
                <div class="card-body d-flex gap-2 flex-wrap">
                    <button class="btn btn-outline-primary btn-sm" onclick="storeDashboardController.navigate('purchase_orders')">
                        <i class="bi bi-bag me-1"></i>Purchase Orders
                    </button>
                    <button class="btn btn-outline-secondary btn-sm" onclick="storeDashboardController.navigate('vendors')">
                        <i class="bi bi-shop me-1"></i>Vendors
                    </button>
                    <button class="btn btn-outline-success btn-sm" onclick="storeDashboardController.navigate('manage_inventory')">
                        <i class="bi bi-clipboard2-data me-1"></i>Full Inventory
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
