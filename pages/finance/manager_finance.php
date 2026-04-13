<?php
/**
 * Finance - Manager Layout
 * For Accountant, Bursar, Inventory Manager
 *
 * Features:
 * - 3 stat cards
 * - 2 charts (revenue/expense, category breakdown)
 * - Data table with limited columns
 * - Actions: Add, Edit, Export (no Delete, no Approve)
 */
/* PARTIAL — no DOCTYPE/html/head/body. Injected into app shell via fetch. */
?>

<!-- Stats Row - 3 cards -->
<div class="manager-stats-grid">
    <div class="stat-card">
        <div class="stat-icon bg-success">💵</div>
        <div class="stat-content">
            <span class="stat-value" id="totalRevenue">KES 0</span>
            <span class="stat-label">Total Revenue</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-danger">📉</div>
        <div class="stat-content">
            <span class="stat-value" id="totalExpenses">KES 0</span>
            <span class="stat-label">Total Expenses</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-primary">💎</div>
        <div class="stat-content">
            <span class="stat-value" id="netBalance">KES 0</span>
            <span class="stat-label">Net Balance</span>
        </div>
    </div>
</div>

<!-- Charts Row - 2 charts -->
<div class="manager-charts-grid">
    <div class="chart-card">
        <h3>📈 Revenue vs Expenses</h3>
        <div class="chart-container">
            <canvas id="revenueExpenseChart"></canvas>
        </div>
    </div>
    <div class="chart-card">
        <h3>🥧 Category Breakdown</h3>
        <div class="chart-container">
            <canvas id="categoryChart"></canvas>
        </div>
    </div>
</div>

<!-- Filters Section -->
<div class="manager-filters">
    <input type="text" class="form-input" id="financeSearch" placeholder="Search transactions...">
    <select class="form-select" id="transactionTypeFilter">
        <option value="">All Types</option>
        <option value="income">Income</option>
        <option value="expense">Expense</option>
    </select>
    <select class="form-select" id="categoryFilter">
        <option value="">All Categories</option>
    </select>
    <input type="date" class="form-input" id="dateFromFilter" placeholder="From">
    <input type="date" class="form-input" id="dateToFilter" placeholder="To">
    <button class="btn btn-outline-sm" onclick="clearFilters()">Clear</button>
    <button class="btn btn-outline" onclick="exportTransactions()">📥 Export</button>
    <button class="btn btn-primary" onclick="showAddTransactionModal()">➕ Add Transaction</button>
</div>

<!-- Data Table - Limited columns -->
<div class="manager-table-container">
    <table class="manager-data-table" id="financeTable">
        <thead>
            <tr>
                <th>Date</th>
                <th>Type</th>
                <th>Category</th>
                <th>Description</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="financeTableBody">
            <tr><td colspan="7" class="loading-row">Loading transactions...</td></tr>
        </tbody>
    </table>
</div>

<!-- Pagination -->
<div class="manager-pagination">
    <span class="pagination-info" id="paginationInfo">Showing 0 of 0</span>
    <div class="pagination-controls" id="paginationControls"></div>
</div>

<!-- Add/Edit Transaction Modal -->
<div class="modal" id="transactionModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Add Transaction</h3>
                <button class="btn-close" onclick="closeModal('transactionModal')">×</button>
            </div>
            <div class="modal-body">
                <form id="transactionForm">
                    <input type="hidden" id="transaction_id">

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

                    <div class="form-group">
                        <label>Amount (KES) *</label>
                        <input type="number" class="form-input" id="transaction_amount" step="0.01" required>
                    </div>

                    <div class="form-group">
                        <label>Date *</label>
                        <input type="date" class="form-input" id="transaction_date" required>
                    </div>

                    <div class="form-group">
                        <label>Description *</label>
                        <textarea class="form-textarea" id="transaction_description" rows="3" required></textarea>
                    </div>

                    <div class="form-group">
                        <label>Receipt/Document</label>
                        <input type="file" class="form-input" id="transaction_document">
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

<script src="<?= $appBase ?>js/pages/finance.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof FinanceController !== 'undefined') {
            FinanceController.init({ view: 'manager' });
        }
    });

    function showAddTransactionModal() {
        document.getElementById('modalTitle').textContent = 'Add Transaction';
        document.getElementById('transactionForm').reset();
        document.getElementById('transaction_id').value = '';
        document.getElementById('transactionModal').classList.add('show');
    }

    function closeModal(modalId) {
        document.getElementById(modalId).classList.remove('show');
    }

    function exportTransactions() {
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
        document.querySelectorAll('.manager-filters input, .manager-filters select').forEach(el => {
            el.value = '';
        });
        if (typeof FinanceController !== 'undefined') {
            FinanceController.loadData();
        }
    }
</script>
