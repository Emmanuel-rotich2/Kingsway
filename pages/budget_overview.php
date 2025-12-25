<?php
/**
 * Budget Overview Page
 * HTML structure only - logic will be in js/pages/budget_overview.js
 * Embedded in app_layout.php
 */
?>

<div class="card shadow-sm">
    <div class="card-header bg-gradient bg-info text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="fas fa-calculator"></i> Budget Overview</h4>
            <div class="btn-group">
                <button class="btn btn-light btn-sm" id="addBudgetBtn" data-permission="budget_create">
                    <i class="bi bi-plus-circle"></i> New Budget
                </button>
                <button class="btn btn-outline-light btn-sm" id="exportBtn">
                    <i class="bi bi-download"></i> Export
                </button>
            </div>
        </div>
    </div>

    <div class="card-body">
        <!-- Filter Row -->
        <div class="row mb-4">
            <div class="col-md-3">
                <label class="form-label">Financial Year</label>
                <select class="form-select" id="financialYear"></select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Department</label>
                <select class="form-select" id="department">
                    <option value="">All Departments</option>
                    <option value="academic">Academic</option>
                    <option value="admin">Administration</option>
                    <option value="maintenance">Maintenance</option>
                    <option value="kitchen">Kitchen</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select class="form-select" id="statusFilter">
                    <option value="">All Status</option>
                    <option value="draft">Draft</option>
                    <option value="approved">Approved</option>
                    <option value="active">Active</option>
                </select>
            </div>
        </div>

        <!-- Budget Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-primary">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Total Budget</h6>
                        <h3 class="text-primary mb-0" id="totalBudget">KES 0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Spent</h6>
                        <h3 class="text-success mb-0" id="totalSpent">KES 0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-warning">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Remaining</h6>
                        <h3 class="text-warning mb-0" id="remaining">KES 0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-info">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Utilization</h6>
                        <h3 class="text-info mb-0" id="utilization">0%</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Budget vs Actual (Monthly)</h5>
                        <canvas id="budgetVsActualChart" height="80"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Department Allocation</h5>
                        <canvas id="departmentChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Budget Categories Table -->
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Budget Breakdown by Category</h5>
                <div class="table-responsive">
                    <table class="table table-hover" id="budgetTable">
                        <thead class="table-light">
                            <tr>
                                <th>Category</th>
                                <th>Department</th>
                                <th>Allocated</th>
                                <th>Spent</th>
                                <th>Remaining</th>
                                <th>% Used</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Dynamic content -->
                        </tbody>
                        <tfoot class="table-light fw-bold">
                            <tr>
                                <td colspan="2">TOTALS</td>
                                <td id="totalAllocated">KES 0</td>
                                <td id="footerSpent">KES 0</td>
                                <td id="footerRemaining">KES 0</td>
                                <td id="footerPercent">0%</td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Budget Modal -->
<div class="modal fade" id="budgetModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="budgetModalTitle">Add Budget Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="budgetForm">
                    <input type="hidden" id="budgetId">
                    <div class="mb-3">
                        <label class="form-label">Financial Year*</label>
                        <select class="form-select" id="fyear" required></select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Category*</label>
                        <input type="text" class="form-control" id="category" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Department*</label>
                        <select class="form-select" id="budgetDepartment" required>
                            <option value="academic">Academic</option>
                            <option value="admin">Administration</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="kitchen">Kitchen</option>
                            <option value="transport">Transport</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Allocated Amount (KES)*</label>
                        <input type="number" class="form-control" id="amount" required min="0" step="0.01">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" id="budgetDescription" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status*</label>
                        <select class="form-select" id="budgetStatus" required>
                            <option value="draft">Draft</option>
                            <option value="approved">Approved</option>
                            <option value="active">Active</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveBudgetBtn">Save Budget</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // TODO: Implement budgetOverviewController in js/pages/budget_overview.js
        console.log('Budget Overview page loaded');
    });
</script>