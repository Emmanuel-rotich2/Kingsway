<?php
/**
 * Manage Inventory Page
 * HTML structure only - logic will be in js/pages/inventory.js
 * Embedded in app_layout.php
 */
?>

<div class="card shadow-sm">
    <div class="card-header bg-gradient bg-success text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="fas fa-boxes"></i> Inventory Management</h4>
            <div class="btn-group">
                <button class="btn btn-light btn-sm" id="addItemBtn" data-permission="inventory_create">
                    <i class="bi bi-plus-circle"></i> Add Item
                </button>
                <button class="btn btn-outline-light btn-sm" id="exportInventoryBtn">
                    <i class="bi bi-download"></i> Export
                </button>
            </div>
        </div>
    </div>

    <div class="card-body">
        <!-- Inventory Stats -->
        <div class="row mb-4">
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
            <div class="col-md-2">
                <select class="form-select" id="locationFilter">
                    <option value="">All Locations</option>
                    <option value="main_store">Main Store</option>
                    <option value="library">Library</option>
                    <option value="lab">Laboratory</option>
                    <option value="kitchen">Kitchen</option>
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

<!-- Item Modal -->
<div class="modal fade" id="itemModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Item Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="itemForm">
                    <input type="hidden" id="item_id">

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Item Code*</label>
                            <input type="text" class="form-control" id="item_code" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Item Name*</label>
                            <input type="text" class="form-control" id="item_name" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Category*</label>
                            <select class="form-select" id="category" required>
                                <option value="">Select Category</option>
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

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Location*</label>
                            <select class="form-select" id="location" required>
                                <option value="">Select Location</option>
                                <option value="main_store">Main Store</option>
                                <option value="library">Library</option>
                                <option value="lab">Laboratory</option>
                                <option value="kitchen">Kitchen</option>
                                <option value="office">Office</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Current Quantity*</label>
                            <input type="number" class="form-control" id="quantity" min="0" required>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">Unit*</label>
                            <select class="form-select" id="unit" required>
                                <option value="">Select Unit</option>
                                <option value="pcs">Pieces</option>
                                <option value="boxes">Boxes</option>
                                <option value="packs">Packs</option>
                                <option value="kg">Kilograms</option>
                                <option value="liters">Liters</option>
                                <option value="sets">Sets</option>
                            </select>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">Reorder Level*</label>
                            <input type="number" class="form-control" id="reorder_level" min="0" required>
                            <small class="text-muted">Alert when stock falls below this</small>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Unit Price (KES)</label>
                            <input type="number" class="form-control" id="unit_price" step="0.01">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Supplier</label>
                            <input type="text" class="form-control" id="supplier">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" id="description" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveItemBtn">Save Item</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Initialize inventory management when page loads
    document.addEventListener('DOMContentLoaded', function () {
        // TODO: Implement inventoryManagementController in js/pages/inventory.js
        console.log('Inventory Management page loaded');
    });
</script>