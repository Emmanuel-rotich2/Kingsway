<?php
// Authentication handled by JWT middleware and JavaScript
$pageTitle = 'Uniform Sales Management';
$pageDescription = 'Manage school uniform inventory, sales, and stock levels';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - Kingsway Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/Kingsway/king.css">
    <style>
        .uniform-card {
            transition: all 0.3s ease;
            border-radius: 12px;
            overflow: hidden;
        }
        .uniform-card:hover {
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            transform: translateY(-3px);
        }
        .size-badge {
            display: inline-block;
            padding: 4px 10px;
            margin: 2px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        .size-badge.in-stock { background: #d4edda; color: #155724; }
        .size-badge.low-stock { background: #fff3cd; color: #856404; }
        .size-badge.out-of-stock { background: #f8d7da; color: #721c24; }
        .stats-card {
            border: none;
            border-radius: 15px;
            transition: transform 0.2s;
        }
        .stats-card:hover { transform: translateY(-5px); }
        .stats-icon {
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            font-size: 1.5rem;
        }
        .stock-progress {
            height: 8px;
            border-radius: 4px;
        }
        .tab-content { padding-top: 20px; }
        .sale-row:hover { background-color: #f8f9fa; }
        .payment-badge { font-size: 0.8rem; }
    </style>
</head>
<body>
    <?php include_once '../layouts/app_layout.php'; ?>
    
    <div class="container-fluid py-4">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1"><i class="fas fa-tshirt me-2"></i><?= $pageTitle ?></h2>
                <p class="text-muted mb-0"><?= $pageDescription ?></p>
            </div>
            <div class="btn-group">
                <button class="btn btn-primary" onclick="UniformSalesController.showNewSaleModal()">
                    <i class="fas fa-plus me-1"></i>New Sale
                </button>
                <button class="btn btn-success" onclick="UniformSalesController.showRestockModal()">
                    <i class="fas fa-boxes me-1"></i>Restock
                </button>
                <button class="btn btn-outline-secondary" onclick="UniformSalesController.refresh()">
                    <i class="fas fa-sync-alt me-1"></i>Refresh
                </button>
            </div>
        </div>

        <!-- Dashboard Stats -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stats-card bg-primary text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-white-50 mb-1">This Month Sales</h6>
                                <h3 class="mb-0" id="statMonthlySales">KES 0</h3>
                                <small id="statMonthlySalesCount">0 sales</small>
                            </div>
                            <div class="stats-icon bg-white bg-opacity-25">
                                <i class="fas fa-shopping-cart"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stats-card bg-success text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-white-50 mb-1">Paid Amount</h6>
                                <h3 class="mb-0" id="statPaidAmount">KES 0</h3>
                                <small>Collected revenue</small>
                            </div>
                            <div class="stats-icon bg-white bg-opacity-25">
                                <i class="fas fa-check-circle"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stats-card bg-warning text-dark h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-dark-50 mb-1">Pending Payments</h6>
                                <h3 class="mb-0" id="statPendingAmount">KES 0</h3>
                                <small>Outstanding balance</small>
                            </div>
                            <div class="stats-icon bg-white bg-opacity-50">
                                <i class="fas fa-clock"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stats-card bg-danger text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-white-50 mb-1">Low Stock Items</h6>
                                <h3 class="mb-0" id="statLowStock">0</h3>
                                <small>Need restocking</small>
                            </div>
                            <div class="stats-icon bg-white bg-opacity-25">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <ul class="nav nav-tabs" id="uniformTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="inventory-tab" data-bs-toggle="tab" 
                        data-bs-target="#inventory" type="button">
                    <i class="fas fa-boxes me-1"></i>Inventory
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="sales-tab" data-bs-toggle="tab" 
                        data-bs-target="#sales" type="button">
                    <i class="fas fa-receipt me-1"></i>Sales History
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="lowstock-tab" data-bs-toggle="tab" 
                        data-bs-target="#lowstock" type="button">
                    <i class="fas fa-exclamation-circle me-1"></i>Low Stock
                    <span class="badge bg-danger ms-1" id="lowStockBadge">0</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="reports-tab" data-bs-toggle="tab" 
                        data-bs-target="#reports" type="button">
                    <i class="fas fa-chart-bar me-1"></i>Reports
                </button>
            </li>
        </ul>

        <div class="tab-content" id="uniformTabsContent">
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
                <!-- Filters -->
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="row g-3">
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
                                <input type="date" id="filterDateFrom" class="form-control" 
                                       placeholder="From Date">
                            </div>
                            <div class="col-md-2">
                                <input type="date" id="filterDateTo" class="form-control" 
                                       placeholder="To Date">
                            </div>
                            <div class="col-md-1">
                                <button class="btn btn-outline-primary w-100" onclick="UniformSalesController.loadSales()">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Student</th>
                                        <th>Item</th>
                                        <th>Size</th>
                                        <th>Qty</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="salesTableBody">
                                    <tr>
                                        <td colspan="8" class="text-center py-4">
                                            <div class="spinner-border spinner-border-sm text-primary"></div>
                                            Loading sales...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer">
                        <nav>
                            <ul class="pagination pagination-sm mb-0 justify-content-center" id="salesPagination"></ul>
                        </nav>
                    </div>
                </div>
            </div>

            <!-- Low Stock Tab -->
            <div class="tab-pane fade" id="lowstock" role="tabpanel">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="fas fa-exclamation-triangle text-warning me-2"></i>Low Stock Alert</h6>
                        <button class="btn btn-success btn-sm" onclick="UniformSalesController.showRestockModal()">
                            <i class="fas fa-boxes me-1"></i>Restock Items
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Item</th>
                                        <th>Size</th>
                                        <th>Available</th>
                                        <th>Sold</th>
                                        <th>Unit Price</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="lowStockTableBody">
                                    <tr>
                                        <td colspan="7" class="text-center py-4">Loading...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Reports Tab -->
            <div class="tab-pane fade" id="reports" role="tabpanel">
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label class="form-label">From Date</label>
                        <input type="date" id="reportDateFrom" class="form-control" 
                               value="<?= date('Y-m-01') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">To Date</label>
                        <input type="date" id="reportDateTo" class="form-control" 
                               value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button class="btn btn-primary" onclick="UniformSalesController.loadReport()">
                            <i class="fas fa-chart-bar me-1"></i>Generate Report
                        </button>
                    </div>
                </div>

                <div id="reportContainer">
                    <!-- Report content will be loaded here -->
                    <div class="text-center py-5 text-muted">
                        <i class="fas fa-chart-pie fa-4x mb-3"></i>
                        <p>Select date range and click Generate Report</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- New Sale Modal -->
    <div class="modal fade" id="newSaleModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-shopping-cart me-2"></i>New Uniform Sale</h5>
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
                        </div>

                        <div class="row g-3 mt-2">
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
                        </div>

                        <div class="row g-3 mt-2">
                            <div class="col-md-6">
                                <label class="form-label">Total Amount</label>
                                <input type="text" id="saleTotalAmount" class="form-control bg-light" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Stock Available</label>
                                <input type="text" id="saleStockAvailable" class="form-control bg-light" readonly>
                            </div>
                        </div>

                        <div class="mt-3">
                            <label class="form-label">Notes</label>
                            <textarea id="saleNotes" name="notes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="submitSaleBtn">
                            <i class="fas fa-check me-1"></i>Record Sale
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Restock Modal -->
    <div class="modal fade" id="restockModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-boxes me-2"></i>Restock Uniform</h5>
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
                                <input type="number" id="restockQuantity" name="quantity" class="form-control" 
                                       min="1" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Unit Price (KES)</label>
                                <input type="number" id="restockUnitPrice" name="unit_price" class="form-control" 
                                       step="0.01">
                            </div>
                        </div>
                        <div class="mt-3">
                            <label class="form-label">Notes</label>
                            <textarea id="restockNotes" name="notes" class="form-control" rows="2" 
                                      placeholder="e.g., Supplier invoice #, batch number"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success" id="submitRestockBtn">
                            <i class="fas fa-boxes me-1"></i>Restock
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Size Stock Modal -->
    <div class="modal fade" id="viewSizesModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewSizesTitle">Uniform Sizes</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="viewSizesContent">
                    <!-- Size details loaded dynamically -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="/Kingsway/js/api.js"></script>
    <script src="/Kingsway/js/pages/uniform_sales.js"></script>
</body>
</html>
