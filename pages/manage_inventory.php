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

<!-- SECTION: Uniform Sales Management -->
<div class="card shadow-sm mt-4">
    <div class="card-header bg-gradient bg-info text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="fas fa-shirt"></i> Uniform Sales Management</h4>
            <button class="btn btn-light btn-sm" id="registerUniformSaleBtn" data-permission="inventory_uniforms_manage">
                <i class="bi bi-plus-circle"></i> Register Uniform Sale
            </button>
        </div>
    </div>

    <div class="card-body">
        <!-- Uniform Stats Row -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-info">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Total Uniforms</h6>
                        <h3 class="text-info mb-0" id="totalUniformItems">9</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Total Sold</h6>
                        <h3 class="text-success mb-0" id="totalUniformsSold">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-primary">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Revenue</h6>
                        <h3 class="text-primary mb-0" id="uniformSalesRevenue">KES 0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-warning">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Pending Payments</h6>
                        <h3 class="text-warning mb-0" id="pendingUniformPayments">KES 0</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Uniform Items Overview -->
        <div class="row mb-4">
            <div class="col-12">
                <h6 class="mb-3"><i class="fas fa-list"></i> Uniform Inventory</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-hover" id="uniformItemsTable">
                        <thead class="table-light">
                            <tr>
                                <th>Uniform Item</th>
                                <th>Item Code</th>
                                <th>Sizes</th>
                                <th>In Stock</th>
                                <th>Total Sold</th>
                                <th>Unit Price</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Dynamic uniform items -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Recent Uniform Sales -->
        <div class="row">
            <div class="col-12">
                <h6 class="mb-3"><i class="fas fa-shopping-cart"></i> Recent Uniform Sales</h6>
                <div class="table-responsive">
                    <table class="table table-sm" id="recentUniformSalesTable">
                        <thead class="table-light">
                            <tr>
                                <th>Student</th>
                                <th>Admission #</th>
                                <th>Item</th>
                                <th>Size</th>
                                <th>Qty</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Sale Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Dynamic sales data -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
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

<!-- Uniform Sale Registration Modal -->
<div class="modal fade" id="uniformSaleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-shirt"></i> Register Uniform Sale</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="uniformSaleForm">
                    <!-- Student Selection -->
                    <div class="mb-3">
                        <label class="form-label">Select Student*</label>
                        <div class="input-group">
                            <input type="hidden" id="uniform_student_id">
                            <input type="text" class="form-control" id="uniform_student_search" 
                                   placeholder="Search by name or admission number..." autocomplete="off">
                            <button class="btn btn-outline-secondary" type="button" id="clearStudentBtn">
                                <i class="bi bi-x"></i>
                            </button>
                        </div>
                        <div id="studentSearchResults" class="list-group mt-2" style="max-height: 200px; overflow-y: auto; display: none;">
                            <!-- Dynamic search results -->
                        </div>
                        <small class="text-info" id="studentProfileInfo"></small>
                    </div>

                    <!-- Uniform Item Selection -->
                    <div class="mb-3">
                        <label class="form-label">Select Uniform Item*</label>
                        <select class="form-select" id="uniform_item_id" required>
                            <option value="">Choose uniform...</option>
                            <option value="11">School Sweater (All Sizes)</option>
                            <option value="12">School Socks (Pack of 3)</option>
                            <option value="13">School Shorts (All Sizes)</option>
                            <option value="14">School Trousers (All Sizes)</option>
                            <option value="15">School Shirt (Boys All Sizes)</option>
                            <option value="16">School Blouse (Girls All Sizes)</option>
                            <option value="17">School Skirt (Girls All Sizes)</option>
                            <option value="18">Games Skirt (All Sizes)</option>
                            <option value="19">Sleeping Pajamas (All Sizes)</option>
                        </select>
                    </div>

                    <!-- Size and Quantity Row -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Size*</label>
                            <select class="form-select" id="uniform_size" required>
                                <option value="">Select size...</option>
                                <option value="XS">XS - Extra Small</option>
                                <option value="S">S - Small</option>
                                <option value="M">M - Medium</option>
                                <option value="L">L - Large</option>
                                <option value="XL">XL - Extra Large</option>
                                <option value="XXL">XXL - Extra Extra Large</option>
                            </select>
                            <small class="text-success" id="sizeAvailability"></small>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Quantity*</label>
                            <input type="number" class="form-control" id="uniform_quantity" min="1" value="1" required>
                        </div>
                    </div>

                    <!-- Pricing Section -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Unit Price (KES)</label>
                            <input type="number" class="form-control" id="uniform_unit_price" 
                                   step="0.01" readonly style="background-color: #f0f0f0;">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Total Amount (KES)</label>
                            <input type="number" class="form-control" id="uniform_total_amount" 
                                   step="0.01" readonly style="background-color: #f0f0f0;">
                        </div>
                    </div>

                    <!-- Payment Status -->
                    <div class="mb-3">
                        <label class="form-label">Payment Status*</label>
                        <select class="form-select" id="uniform_payment_status" required>
                            <option value="pending">Pending Payment</option>
                            <option value="partial">Partially Paid</option>
                            <option value="paid">Fully Paid</option>
                        </select>
                    </div>

                    <!-- Additional Notes -->
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" id="uniform_notes" rows="2" 
                                  placeholder="E.g., Purchased by parent, special order, etc."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-info" id="saveUniformSaleBtn">
                    <i class="fas fa-save"></i> Register Sale
                </button>
            </div>
        </div>
    </div>
</div>

<!-- View Student Uniform Profile Modal -->
<div class="modal fade" id="studentUniformProfileModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-tie"></i> Student Uniform Profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="studentUniformProfileForm">
                    <input type="hidden" id="profile_student_id">

                    <div class="alert alert-info">
                        <strong id="profileStudentName"></strong>
                        <br>
                        <small>Admission: <span id="profileAdmissionNo"></span></small>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Uniform Size</label>
                            <select class="form-select" id="profile_uniform_size">
                                <option value="">XS</option>
                                <option value="S">S</option>
                                <option value="M">M</option>
                                <option value="L">L</option>
                                <option value="XL">XL</option>
                                <option value="XXL">XXL</option>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Shirt Size</label>
                            <input type="text" class="form-control" id="profile_shirt_size" placeholder="e.g., M, L, XL">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Trousers Size</label>
                            <input type="text" class="form-control" id="profile_trousers_size" placeholder="e.g., 28, 30, 32">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Skirt Size</label>
                            <input type="text" class="form-control" id="profile_skirt_size" placeholder="e.g., S, M, L">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Sweater Size</label>
                            <input type="text" class="form-control" id="profile_sweater_size" placeholder="e.g., M, L, XL">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Shoe Size</label>
                            <input type="text" class="form-control" id="profile_shoes_size" placeholder="e.g., 8, 9, 10">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Additional Notes</label>
                        <textarea class="form-control" id="profile_notes" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="saveProfileBtn">
                    <i class="fas fa-save"></i> Save Profile
                </button>
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