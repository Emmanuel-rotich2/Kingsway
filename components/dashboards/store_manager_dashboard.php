<?php
/**
 * Store Manager Dashboard — Inventory Manager (Role ID 14)
 */
?>
<div class="container-fluid py-3" id="store-dashboard">

    <!-- Greeting Bar -->
    <div class="dash-greeting-bar mb-4">
        <div>
            <h5 id="storeGreeting">Good morning!</h5>
            <p>Stock management, requisitions, and purchase orders</p>
        </div>
        <div class="dash-meta">
            <button class="btn btn-sm btn-light" onclick="storeDashboardController.navigate('manage_stock')">
                <i class="bi bi-plus me-1"></i>Add Stock
            </button>
            <button class="dash-refresh-btn" onclick="storeDashboardController.refresh()">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="dash-stat dsc-blue">
                <div class="dash-stat-value" id="totalItems">0</div>
                <div class="dash-stat-label">Total Items</div>
                <i class="bi bi-box-seam dash-stat-icon"></i>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="dash-stat dsc-red">
                <div class="dash-stat-value" id="lowStockCount">0</div>
                <div class="dash-stat-label">Low Stock</div>
                <i class="bi bi-exclamation-triangle-fill dash-stat-icon"></i>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="dash-stat dsc-amber">
                <div class="dash-stat-value" id="pendingRequisitions">0</div>
                <div class="dash-stat-label">Pending Requisitions</div>
                <i class="bi bi-clipboard-check dash-stat-icon"></i>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="dash-stat dsc-green">
                <div class="dash-stat-value small" id="stockValue">KES 0</div>
                <div class="dash-stat-label">Stock Value</div>
                <i class="bi bi-currency-dollar dash-stat-icon"></i>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <!-- Low Stock Alerts -->
        <div class="col-md-6">
            <div class="card dash-card">
                <div class="card-header d-flex justify-content-between align-items-center">
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
            <div class="card dash-card">
                <div class="card-header d-flex justify-content-between align-items-center">
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
            <div class="card dash-card mt-3">
                <div class="card-header"><h6 class="mb-0"><i class="bi bi-lightning me-2 text-warning"></i>Quick Actions</h6></div>
                <div class="card-body">
                    <a href="#" onclick="storeDashboardController.navigate('purchase_orders')" class="dash-quick-link">
                        <i class="bi bi-bag ql-icon bg-primary text-white"></i>
                        <span>Purchase Orders</span><i class="bi bi-chevron-right ql-arrow"></i>
                    </a>
                    <a href="#" onclick="storeDashboardController.navigate('vendors')" class="dash-quick-link">
                        <i class="bi bi-shop ql-icon bg-secondary text-white"></i>
                        <span>Vendors</span><i class="bi bi-chevron-right ql-arrow"></i>
                    </a>
                    <a href="#" onclick="storeDashboardController.navigate('manage_inventory')" class="dash-quick-link">
                        <i class="bi bi-clipboard2-data ql-icon bg-success text-white"></i>
                        <span>Full Inventory</span><i class="bi bi-chevron-right ql-arrow"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
