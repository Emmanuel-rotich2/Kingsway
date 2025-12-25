<?php
/**
 * Food Store Page (Kitchen inventory)
 * HTML structure only - logic will be in js/pages/food_store.js
 * Embedded in app_layout.php
 */
?>

<div class="card shadow-sm">
    <div class="card-header bg-gradient bg-warning text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="fas fa-warehouse"></i> Food Store Management</h4>
            <div class="btn-group">
                <button class="btn btn-light btn-sm" id="addItemBtn" data-permission="foodstore_manage">
                    <i class="bi bi-plus-circle"></i> Add Item
                </button>
                <button class="btn btn-outline-light btn-sm" id="issueItemBtn" data-permission="foodstore_issue">
                    <i class="bi bi-box-arrow-right"></i> Issue Items
                </button>
            </div>
        </div>
    </div>

    <div class="card-body">
        <!-- Summary Cards -->
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
                        <h3 class="text-success mb-0" id="inStock">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-warning">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Low Stock</h6>
                        <h3 class="text-warning mb-0" id="lowStock">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-danger">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Out of Stock</h6>
                        <h3 class="text-danger mb-0" id="outOfStock">0</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Row -->
        <div class="row mb-3">
            <div class="col-md-3">
                <input type="text" class="form-control" id="searchBox" placeholder="Search items...">
            </div>
            <div class="col-md-2">
                <select class="form-select" id="categoryFilter">
                    <option value="">All Categories</option>
                    <option value="grains">Grains & Cereals</option>
                    <option value="vegetables">Vegetables</option>
                    <option value="proteins">Proteins</option>
                    <option value="dairy">Dairy</option>
                    <option value="spices">Spices & Condiments</option>
                    <option value="beverages">Beverages</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" id="stockStatus">
                    <option value="">All Status</option>
                    <option value="in_stock">In Stock</option>
                    <option value="low_stock">Low Stock</option>
                    <option value="out_of_stock">Out of Stock</option>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-outline-secondary w-100" id="exportBtn">
                    <i class="bi bi-download"></i> Export
                </button>
            </div>
        </div>

        <!-- Items Table -->
        <div class="table-responsive">
            <table class="table table-hover" id="foodStoreTable">
                <thead class="table-light">
                    <tr>
                        <th>Item Name</th>
                        <th>Category</th>
                        <th>Quantity</th>
                        <th>Unit</th>
                        <th>Reorder Level</th>
                        <th>Unit Price (KES)</th>
                        <th>Total Value (KES)</th>
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
            <ul class="pagination justify-content-center" id="pagination">
                <!-- Dynamic pagination -->
            </ul>
        </nav>
    </div>
</div>

<!-- Add/Edit Item Modal -->
<div class="modal fade" id="itemModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="itemModalTitle">Add Food Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="itemForm">
                    <input type="hidden" id="itemId">
                    <div class="mb-3">
                        <label class="form-label">Item Name*</label>
                        <input type="text" class="form-control" id="itemName" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Category*</label>
                            <select class="form-select" id="category" required>
                                <option value="grains">Grains & Cereals</option>
                                <option value="vegetables">Vegetables</option>
                                <option value="proteins">Proteins</option>
                                <option value="dairy">Dairy</option>
                                <option value="spices">Spices & Condiments</option>
                                <option value="beverages">Beverages</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Unit*</label>
                            <select class="form-select" id="unit" required>
                                <option value="kg">Kilogram (kg)</option>
                                <option value="g">Gram (g)</option>
                                <option value="liters">Liters (L)</option>
                                <option value="ml">Milliliter (ml)</option>
                                <option value="pcs">Pieces</option>
                                <option value="bags">Bags</option>
                                <option value="crates">Crates</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Quantity*</label>
                            <input type="number" class="form-control" id="quantity" required min="0" step="0.01">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Reorder Level*</label>
                            <input type="number" class="form-control" id="reorderLevel" required min="0" step="0.01">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Unit Price (KES)*</label>
                        <input type="number" class="form-control" id="unitPrice" required min="0" step="0.01">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Supplier</label>
                        <input type="text" class="form-control" id="supplier">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Storage Location</label>
                        <select class="form-select" id="storageLocation">
                            <option value="main_store">Main Store</option>
                            <option value="cold_room">Cold Room</option>
                            <option value="dry_store">Dry Store</option>
                            <option value="kitchen">Kitchen</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" rows="2"></textarea>
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

<!-- Issue Items Modal -->
<div class="modal fade" id="issueModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">Issue Food Items</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="issueForm">
                    <div class="mb-3">
                        <label class="form-label">Item*</label>
                        <select class="form-select" id="issueItem" required></select>
                        <small class="text-muted">Available: <span id="availableQty">0</span> <span
                                id="availableUnit"></span></small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Quantity to Issue*</label>
                        <input type="number" class="form-control" id="issueQuantity" required min="0.01" step="0.01">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Issued To*</label>
                        <input type="text" class="form-control" id="issuedTo" required
                            placeholder="e.g., Kitchen Staff">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Purpose*</label>
                        <select class="form-select" id="purpose" required>
                            <option value="breakfast">Breakfast Preparation</option>
                            <option value="lunch">Lunch Preparation</option>
                            <option value="supper">Supper Preparation</option>
                            <option value="snacks">Snacks</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Date*</label>
                        <input type="date" class="form-control" id="issueDate" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" id="issueNotes" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveIssueBtn">Issue Items</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // TODO: Implement foodStoreController in js/pages/food_store.js
        console.log('Food Store page loaded');
    });
</script>