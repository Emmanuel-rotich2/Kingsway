<?php
/**
 * Finance - Operator Layout
 * For Class Teacher, Subject Teacher (view-only for budget/expense awareness)
 * 
 * Features:
 * - Icon-only sidebar (60px)
 * - 2 stat cards (relevant to them)
 * - Simple table (no charts)
 * - No actions (read-only)
 */
?>

<link rel="stylesheet" href="/Kingsway/css/school-theme.css">
<link rel="stylesheet" href="/Kingsway/css/roles/operator-theme.css">

<div class="operator-layout">
    <!-- Icon-only Sidebar -->
    <aside class="operator-sidebar">
        <a href="/Kingsway/home.php?route=dashboard" class="nav-icon-item" title="Dashboard">🏠</a>
        <a href="/Kingsway/home.php?route=manage_finance" class="nav-icon-item active" title="Finance">💰</a>
        <a href="/Kingsway/home.php?route=budget_overview" class="nav-icon-item" title="Budget">📊</a>
    </aside>

    <!-- Main Content -->
    <main class="operator-main">
        <!-- Header -->
        <header class="operator-header">
            <h1 class="page-title">💰 Finance Overview</h1>
            <p class="page-subtitle">View school financial status</p>
        </header>

        <!-- Stats Row - 2 cards -->
        <div class="operator-stats-grid">
            <div class="stat-card">
                <div class="stat-icon bg-success">💵</div>
                <div class="stat-content">
                    <span class="stat-value" id="budgetAvailable">KES 0</span>
                    <span class="stat-label">Department Budget Available</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon bg-info">📋</div>
                <div class="stat-content">
                    <span class="stat-value" id="budgetUtilized">0%</span>
                    <span class="stat-label">Budget Utilized</span>
                </div>
            </div>
        </div>

        <!-- Recent Transactions (Read-only) -->
        <div class="operator-section">
            <div class="section-header">
                <h2>Recent School Transactions</h2>
            </div>

            <!-- Simple Table -->
            <div class="operator-table-container">
                <table class="operator-data-table" id="financeTable">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody id="financeTableBody">
                        <tr><td colspan="5" class="loading-row">Loading transactions...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Budget Request Section -->
        <div class="operator-section">
            <div class="section-header">
                <h2>💼 Request Resources</h2>
            </div>
            <p class="text-muted">Need materials or resources? Submit a request to the Accountant.</p>
            <button class="btn btn-primary" onclick="showRequestModal()">📝 Submit Resource Request</button>
        </div>
    </main>
</div>

<!-- Resource Request Modal -->
<div class="modal" id="requestModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Resource Request</h3>
                <button class="btn-close" onclick="closeModal('requestModal')">×</button>
            </div>
            <div class="modal-body">
                <form id="requestForm">
                    <div class="form-group">
                        <label>Department *</label>
                        <select class="form-select" id="request_department" required>
                            <option value="">Select Department</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Item/Resource *</label>
                        <input type="text" class="form-input" id="request_item" required>
                    </div>
                    <div class="form-group">
                        <label>Quantity *</label>
                        <input type="number" class="form-input" id="request_quantity" min="1" required>
                    </div>
                    <div class="form-group">
                        <label>Estimated Cost (KES)</label>
                        <input type="number" class="form-input" id="request_cost" step="0.01">
                    </div>
                    <div class="form-group">
                        <label>Justification *</label>
                        <textarea class="form-textarea" id="request_justification" rows="3" required></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeModal('requestModal')">Cancel</button>
                <button class="btn btn-primary" onclick="submitRequest()">Submit Request</button>
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
            FinanceController.init({ view: 'operator' });
        }
    });
    
    function showRequestModal() {
        document.getElementById('requestForm').reset();
        document.getElementById('requestModal').classList.add('show');
    }
    
    function closeModal(modalId) {
        document.getElementById(modalId).classList.remove('show');
    }
    
    function submitRequest() {
        if (typeof FinanceController !== 'undefined') {
            FinanceController.submitRequest();
        }
    }
</script>
