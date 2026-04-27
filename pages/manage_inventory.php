<?php
/**
 * Manage Inventory Page
 * HTML structure only - logic will be in js/pages/inventory.js
 * Embedded in app_layout.php
 * 
 * Role-based access:
 * - Inventory Manager/Store Manager: Full access (create, edit, issue, restock)
 * - Cateress/Cook: View and issue food items only
 * - Director: View all, approve large requisitions
 * - Admin: Full access
 * - Librarian: View/issue library supplies
 * - Lab Technician: View/issue lab supplies
 */
?>

<div class="card shadow-sm">
    <div class="card-header bg-gradient bg-success text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="bi bi-boxes"></i> Inventory Management</h4>
            <div class="btn-group">
                <!-- Add Item - Inventory Manager only -->
                <button class="btn btn-light btn-sm" id="addItemBtn" 
                        data-permission="inventory_create"
                        data-role="inventory_manager,store_manager,admin">
                    <i class="bi bi-plus-circle"></i> Add Item
                </button>
                <!-- Restock - Inventory Manager only -->
                <button class="btn btn-outline-light btn-sm" id="restockBtn"
                        data-permission="inventory_restock"
                        data-role="inventory_manager,store_manager,admin">
                    <i class="bi bi-box-arrow-in-down"></i> Restock
                </button>
                <!-- Issue Stock - Multiple roles -->
                <button class="btn btn-outline-light btn-sm" id="issueStockBtn"
                        data-permission="inventory_issue"
                        data-role="inventory_manager,store_manager,cateress,cook,librarian,lab_technician">
                    <i class="bi bi-box-arrow-right"></i> Issue Stock
                </button>
                <!-- Export - All with view permission -->
                <button class="btn btn-outline-light btn-sm" id="exportInventoryBtn"
                        data-permission="inventory_view">
                    <i class="bi bi-download"></i> Export
                </button>
            </div>
        </div>
    </div>

    <div class="card-body">
        <!-- Inventory Stats - Visible to all with view permission -->
        <div class="row mb-4" data-permission="inventory_view">
            <div class="col-md-3">
                <div class="card border-primary">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Total Items</h6>
                        <h3 class="text-primary mb-0" id="totalItems">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">In Stock</h6>
                        <h3 class="text-success mb-0" id="inStockItems">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-warning">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Low Stock</h6>
                        <h3 class="text-warning mb-0" id="lowStockItems">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-danger">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Out of Stock</h6>
                        <h3 class="text-danger mb-0" id="outOfStockItems">0</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Value Stats - Inventory Manager and Director only -->
        <div class="row mb-4" data-role="inventory_manager,store_manager,director,admin" data-permission="inventory_value">
            <div class="col-md-4">
                <div class="card border-info">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Total Stock Value</h6>
                        <h3 class="text-info mb-0" id="totalStockValue">KES 0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-primary">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Pending Requisitions</h6>
                        <h3 class="text-primary mb-0" id="pendingRequisitions">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-warning">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Expiring Soon</h6>
                        <h3 class="text-warning mb-0" id="expiringSoon">0</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="row mb-3">
            <div class="col-md-3">
                <input type="text" class="form-control" id="itemSearch" placeholder="Search items...">
            </div>
            <div class="col-md-2">
                <select class="form-select" id="categoryFilter">
                    <option value="">All Categories</option>
                    <option value="stationery">Stationery</option>
                    <option value="textbooks">Textbooks</option>
                    <option value="uniforms">Uniforms</option>
                    <option value="equipment">Equipment</option>
                    <option value="furniture">Furniture</option>
                    <option value="electronics">Electronics</option>
                    <option value="food">Food & Supplies</option>
                    <option value="cleaning">Cleaning Supplies</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" id="stockStatusFilter">
                    <option value="">All Status</option>
                    <option value="in_stock">In Stock</option>
                    <option value="low_stock">Low Stock</option>
                    <option value="out_of_stock">Out of Stock</option>
                </select>
            </div>
            <!-- Location filter - role-based preselection -->
            <div class="col-md-2">
                <select class="form-select" id="locationFilter">
                    <option value="">All Locations</option>
                    <option value="main_store">Main Store</option>
                    <option value="library" data-role="librarian">Library</option>
                    <option value="lab" data-role="lab_technician">Laboratory</option>
                    <option value="kitchen" data-role="cateress,cook">Kitchen</option>
                    <option value="office">Office</option>
                </select>
            </div>
        </div>

        <!-- Inventory Table -->
        <div class="table-responsive">
            <table class="table table-hover" id="inventoryTable">
                <thead class="table-light">
                    <tr>
                        <th>Item Code</th>
                        <th>Item Name</th>
                        <th>Category</th>
                        <th>Quantity</th>
                        <th>Unit</th>
                        <th>Location</th>
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
            <ul class="pagination justify-content-center" id="inventoryPagination">
                <!-- Dynamic pagination -->
            </ul>
        </nav>
    </div>
</div>

<!-- Uniform Sales — dedicated page link -->
<div class="card dash-card mt-4">
    <div class="card-body d-flex align-items-center justify-content-between gap-3 flex-wrap">
        <div class="d-flex align-items-center gap-3">
            <div class="bg-primary bg-opacity-10 rounded-3 p-3">
                <i class="bi bi-bag-check fs-2 text-primary"></i>
            </div>
            <div>
                <h6 class="mb-1">Uniform Sales</h6>
                <p class="text-muted small mb-0">
                    Manage uniform inventory by size, record student sales, track payments, and view low-stock alerts.
                    Use the dedicated Uniform Sales page for full sales management.
                </p>
            </div>
        </div>
        <a href="home.php?route=manage_uniform_sales" class="btn btn-primary flex-shrink-0">
            <i class="bi bi-bag-check me-1"></i>Open Uniform Sales
        </a>
    </div>
</div>
<script src="<?= $appBase ?>/js/pages/manage_inventory.js"></script>