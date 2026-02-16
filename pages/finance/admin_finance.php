<?php
/**
 * Finance - Admin Layout
 * Full featured for System Administrator, Director, Headteacher
 * 
 * Features:
 * - Full sidebar (280px)
 * - 4 stat cards with trends
 * - 3 charts (revenue, expense breakdown, monthly comparison)
 * - Full data table with all columns
 * - All actions: Add, Edit, Delete, Approve, Export
 * - Budget management
 * - Bulk operations
 */
?>

<link rel="stylesheet" href="/Kingsway/css/school-theme.css">
<link rel="stylesheet" href="/Kingsway/css/roles/admin-theme.css">

<div class="admin-layout">
    <!-- Full Sidebar -->
    <aside class="admin-sidebar" id="adminSidebar">
        <div class="logo-section">
            <img src="/Kingsway/images/logo.png" alt="Kingsway Academy">
            <h3>Kingsway Academy</h3>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section">
                <span class="nav-section-title">Main</span>
                <a href="/Kingsway/home.php?route=dashboard" class="nav-item">🏠 Dashboard</a>
                <a href="/Kingsway/home.php?route=manage_finance" class="nav-item active">💰 Finance</a>
                <a href="/Kingsway/home.php?route=manage_fees" class="nav-item">🧾 Fees</a>
            </div>
            <div class="nav-section">
                <span class="nav-section-title">Reports</span>
                <a href="/Kingsway/home.php?route=finance_reports" class="nav-item">📊 Finance Reports</a>
                <a href="/Kingsway/home.php?route=budget_overview" class="nav-item">📈 Budget Overview</a>
                <a href="/Kingsway/home.php?route=financial_reports" class="nav-item">📉 Financial Analysis</a>
            </div>
            <div class="nav-section">
                <span class="nav-section-title">Operations</span>
                <a href="/Kingsway/home.php?route=fee_defaulters" class="nav-item">⚠️ Fee Defaulters</a>
                <a href="/Kingsway/home.php?route=finance_approvals" class="nav-item">✅ Approvals</a>
            </div>
        </nav>

        <div class="user-info" id="userInfo">
            <img src="/images/default-avatar.png" alt="User" class="user-avatar">
            <div class="user-details">
                <span class="user-name" id="userName"></span>
                <span class="user-role" id="userRole"></span>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="admin-main">
        <!-- Header -->
        <header class="admin-header">
            <div class="header-left">
                <h1 class="page-title">💰 Finance Management</h1>
                <p class="page-subtitle">Complete financial overview and transaction management</p>
            </div>
            <div class="header-actions">
                <button class="btn btn-outline" onclick="exportFinance()">📥 Export Report</button>
                <button class="btn btn-warning" onclick="showApprovals()">✅ Approve Pending</button>
                <button class="btn btn-primary" onclick="showAddTransactionModal()">➕ Add Transaction</button>
            </div>
        </header>

        <!-- Stats Row - 4 cards -->
        <div class="admin-stats-grid">
            <div class="stat-card">
                <div class="stat-icon bg-success">💵</div>
                <div class="stat-content">
                    <span class="stat-value" id="totalRevenue">KES 0</span>
                    <span class="stat-label">Total Revenue</span>
                    <span class="stat-trend up" id="revenueTrend">+0%</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon bg-danger">📉</div>
                <div class="stat-content">
                    <span class="stat-value" id="totalExpenses">KES 0</span>
                    <span class="stat-label">Total Expenses</span>
                    <span class="stat-trend down" id="expenseTrend">-0%</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon bg-primary">💎</div>
                <div class="stat-content">
                    <span class="stat-value" id="netBalance">KES 0</span>
                    <span class="stat-label">Net Balance</span>
                    <span class="stat-trend" id="balanceTrend">-</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon bg-warning">⏳</div>
                <div class="stat-content">
                    <span class="stat-value" id="pendingApprovals">0</span>
                    <span class="stat-label">Pending Approval</span>
                    <span class="stat-trend" id="pendingTrend">-</span>
                </div>
            </div>
        </div>

        <!-- Budget Overview - Admin/Director only -->
        <div class="admin-budget-section">
            <div class="section-header">
                <h2>📊 Budget Overview</h2>
                <button class="btn btn-outline-sm" onclick="showBudgetSettings()">⚙️ Settings</button>
            </div>
            <div class="budget-stats-grid">
                <div class="budget-card">
                    <div class="budget-label">Monthly Budget</div>
                    <div class="budget-value" id="monthlyBudget">KES 0</div>
                    <div class="budget-progress">
                        <div class="progress-bar" id="budgetProgress" style="width: 0%"></div>
                    </div>
                    <div class="budget-status" id="budgetStatus">0% utilized</div>
                </div>
                <div class="budget-card">
                    <div class="budget-label">Cash at Bank</div>
                    <div class="budget-value text-success" id="cashAtBank">KES 0</div>
                </div>
                <div class="budget-card">
                    <div class="budget-label">Petty Cash</div>
                    <div class="budget-value text-warning" id="pettyCash">KES 0</div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="admin-charts-grid">
            <div class="chart-card">
                <h3>📈 Revenue vs Expenses (Monthly)</h3>
                <div class="chart-container">
                    <canvas id="revenueExpenseChart"></canvas>
                </div>
            </div>
            <div class="chart-card">
                <h3>🥧 Expense Breakdown</h3>
                <div class="chart-container">
                    <canvas id="expenseBreakdownChart"></canvas>
                </div>
            </div>
            <div class="chart-card">
                <h3>📊 Cash Flow</h3>
                <div class="chart-container">
                    <canvas id="cashFlowChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="admin-filters">
            <div class="filter-group">
                <input type="text" class="form-input" id="financeSearch" placeholder="Search transactions...">
            </div>
            <div class="filter-group">
                <select class="form-select" id="transactionTypeFilter">
                    <option value="">All Types</option>
                    <option value="income">Income</option>
                    <option value="expense">Expense</option>
                </select>
            </div>
            <div class="filter-group">
                <select class="form-select" id="categoryFilter">
                    <option value="">All Categories</option>
                </select>
            </div>
            <div class="filter-group">
                <select class="form-select" id="approvalStatusFilter">
                    <option value="">All Status</option>
                    <option value="pending">Pending Approval</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>
            <div class="filter-group">
                <input type="date" class="form-input" id="dateFromFilter">
            </div>
            <div class="filter-group">
                <input type="date" class="form-input" id="dateToFilter">
            </div>
            <button class="btn btn-outline-sm" onclick="clearFilters()">Clear</button>
        </div>

        <!-- Bulk Actions -->
        <div class="admin-bulk-actions" id="bulkActions" style="display: none;">
            <span class="selected-count"><span id="selectedCount">0</span> selected</span>
            <button class="btn btn-outline-sm" onclick="bulkApprove()">✅ Approve Selected</button>
            <button class="btn btn-outline-sm" onclick="bulkReject()">❌ Reject Selected</button>
            <button class="btn btn-outline-sm" onclick="bulkExport()">📥 Export Selected</button>
            <button class="btn btn-danger-sm" onclick="bulkDelete()">🗑️ Delete Selected</button>
        </div>

        <!-- Data Table -->
        <div class="admin-table-container">
            <table class="admin-data-table" id="financeTable">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="selectAll" onchange="toggleSelectAll()"></th>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Category</th>
                        <th>Description</th>
                        <th>Amount</th>
                        <th>Recorded By</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="financeTableBody">
                    <tr><td colspan="9" class="loading-row">Loading transactions...</td></tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="admin-pagination">
            <span class="pagination-info" id="paginationInfo">Showing 0 of 0</span>
            <div class="pagination-controls" id="paginationControls"></div>
        </div>
    </main>
</div>

<!-- Add/Edit Transaction Modal -->
<div class="modal" id="transactionModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Add Transaction</h3>
                <button class="btn-close" onclick="closeModal('transactionModal')">×</button>
            </div>
            <div class="modal-body">
                <form id="transactionForm">
                    <input type="hidden" id="transaction_id">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Type *</label>
                            <select class="form-select" id="transaction_type" required>
                                <option value="">Select Type</option>
                                <option value="income">Income</option>
                                <option value="expense">Expense</option>
                            </select>
                        </div>
                        <div class="form-group" id="transactionStudentGroup" style="display: none;">
                            <label>Student ID (for fee payments)</label>
                            <input type="number" class="form-input" id="transaction_student_id" min="1">
                        </div>
                        <div class="form-group">
                            <label>Category *</label>
                            <select class="form-select" id="transaction_category" required></select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Amount (KES) *</label>
                            <input type="number" class="form-input" id="transaction_amount" step="0.01" required>
                        </div>
                        <div class="form-group">
                            <label>Date *</label>
                            <input type="date" class="form-input" id="transaction_date" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Description *</label>
                        <textarea class="form-textarea" id="transaction_description" rows="3" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Receipt/Document</label>
                        <input type="file" class="form-input" id="transaction_document">
                    </div>
                    
                    <div class="form-group" id="approvalSection" style="display: none;">
                        <label>Approval Notes</label>
                        <textarea class="form-textarea" id="approval_notes" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeModal('transactionModal')">Cancel</button>
                <button class="btn btn-primary" onclick="saveTransaction()">Save Transaction</button>
            </div>
        </div>
    </div>
</div>

<!-- Approval Modal -->
<div class="modal" id="approvalModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Pending Approvals</h3>
                <button class="btn-close" onclick="closeModal('approvalModal')">×</button>
            </div>
            <div class="modal-body">
                <div id="approvalList">
                    <!-- Dynamic content -->
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeModal('approvalModal')">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="/Kingsway/js/components/RoleBasedUI.js"></script>
<script src="/Kingsway/js/pages/finance.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        RoleBasedUI.applyLayout();
        if (typeof FinanceController !== 'undefined') {
            FinanceController.init({ view: 'admin' });
        }
    });
    
    function showAddTransactionModal() {
        document.getElementById('modalTitle').textContent = 'Add Transaction';
        document.getElementById('transactionForm').reset();
        document.getElementById('transaction_id').value = '';
        document.getElementById('transactionModal').classList.add('show');
    }
    
    function showApprovals() {
        document.getElementById('approvalModal').classList.add('show');
        loadPendingApprovals();
    }
    
    function closeModal(modalId) {
        document.getElementById(modalId).classList.remove('show');
    }
    
    function toggleSelectAll() {
        const checked = document.getElementById('selectAll').checked;
        document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = checked);
        updateBulkActions();
        if (typeof FinanceController !== 'undefined') {
            FinanceController.syncSelectionFromDom();
        }
    }
    
    function updateBulkActions() {
        const selected = document.querySelectorAll('.row-checkbox:checked').length;
        document.getElementById('selectedCount').textContent = selected;
        document.getElementById('bulkActions').style.display = selected > 0 ? 'flex' : 'none';
    }
    
    function exportFinance() {
        if (typeof FinanceController !== 'undefined') {
            FinanceController.exportReport();
        }
    }
    
    function saveTransaction() {
        if (typeof FinanceController !== 'undefined') {
            FinanceController.saveTransaction();
        }
    }
    
    function clearFilters() {
        document.querySelectorAll('.admin-filters input, .admin-filters select').forEach(el => {
            el.value = '';
        });
        if (typeof FinanceController !== 'undefined') {
            FinanceController.loadData();
        }
    }
</script>
