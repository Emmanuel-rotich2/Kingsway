<?php
/* PARTIAL — no DOCTYPE/html/head/body. Injected into app shell via fetch. */
/**
 * Fees - Admin Layout
 * Full featured for System Administrator, Director, Headteacher
 *
 * Features:
 * - Full sidebar (280px)
 * - 4 stat cards with trends
 * - 2 charts (collection trend, payment status distribution)
 * - Full data table with all columns
 * - All actions: Record, Edit, Waive, Export
 * - Bulk operations, reminders
 */
?>

<!-- Header Actions -->
<div class="header-actions" style="margin-bottom: 1rem;">
    <button class="btn btn-outline" onclick="exportFeesReport()">📥 Export</button>
    <button class="btn btn-warning" onclick="sendReminders()">🔔 Send Reminders</button>
    <button class="btn btn-primary" onclick="showRecordPaymentModal()">➕ Record Payment</button>
</div>

<!-- Stats Row - 4 cards -->
<div class="admin-stats-grid">
    <div class="stat-card">
        <div class="stat-icon bg-info">📋</div>
        <div class="stat-content">
            <span class="stat-value" id="totalExpected">KES 0</span>
            <span class="stat-label">Total Expected</span>
            <span class="stat-trend" id="expectedTrend">-</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-success">💵</div>
        <div class="stat-content">
            <span class="stat-value" id="totalCollected">KES 0</span>
            <span class="stat-label">Total Collected</span>
            <span class="stat-trend up" id="collectedTrend">+0%</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-warning">⏳</div>
        <div class="stat-content">
            <span class="stat-value" id="outstandingBalance">KES 0</span>
            <span class="stat-label">Outstanding</span>
            <span class="stat-trend" id="outstandingTrend">-</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-danger">⚠️</div>
        <div class="stat-content">
            <span class="stat-value" id="overdueAccounts">0</span>
            <span class="stat-label">Overdue Accounts</span>
            <span class="stat-trend" id="overdueTrend">-</span>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="admin-charts-grid">
    <div class="chart-card">
        <h3>📈 Collection Trend (Monthly)</h3>
        <div class="chart-container">
            <canvas id="collectionTrendChart"></canvas>
        </div>
    </div>
    <div class="chart-card">
        <h3>🥧 Payment Status Distribution</h3>
        <div class="chart-container">
            <canvas id="statusDistributionChart"></canvas>
        </div>
    </div>
</div>

<!-- Filters Section -->
<div class="admin-filters">
    <div class="filter-group">
        <input type="text" class="form-input" id="studentSearch" placeholder="Search student/admission no...">
    </div>
    <div class="filter-group">
        <select class="form-select" id="classFilter">
            <option value="">All Classes</option>
        </select>
    </div>
    <div class="filter-group">
        <select class="form-select" id="termFilter">
            <option value="">All Terms</option>
            <option value="1">Term 1</option>
            <option value="2">Term 2</option>
            <option value="3">Term 3</option>
        </select>
    </div>
    <div class="filter-group">
        <select class="form-select" id="paymentStatusFilter">
            <option value="">All Status</option>
            <option value="paid">Fully Paid</option>
            <option value="partial">Partial Payment</option>
            <option value="outstanding">Outstanding</option>
            <option value="overdue">Overdue</option>
        </select>
    </div>
    <div class="filter-group">
        <select class="form-select" id="yearFilter">
            <option value="">All Years</option>
        </select>
    </div>
    <button class="btn btn-outline-sm" onclick="clearFilters()">Clear</button>
</div>

<!-- Bulk Actions -->
<div class="admin-bulk-actions" id="bulkActions" style="display: none;">
    <span class="selected-count"><span id="selectedCount">0</span> selected</span>
    <button class="btn btn-outline-sm" onclick="bulkSendReminder()">🔔 Send Reminder</button>
    <button class="btn btn-outline-sm" onclick="bulkExport()">📥 Export Selected</button>
    <button class="btn btn-warning-sm" onclick="bulkWaive()">💸 Waive Selected</button>
</div>

<!-- Data Table -->
<div class="admin-table-container">
    <table class="admin-data-table" id="feesTable">
        <thead>
            <tr>
                <th><input type="checkbox" id="selectAll" onchange="toggleSelectAll()"></th>
                <th>Admission No.</th>
                <th>Student Name</th>
                <th>Class</th>
                <th>Term</th>
                <th>Year</th>
                <th class="text-end">Expected</th>
                <th class="text-end">Paid</th>
                <th class="text-end">Balance</th>
                <th>Status</th>
                <th>Last Payment</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="feesTableBody">
            <tr>
                <td colspan="12" class="loading-row">Loading fees...</td>
            </tr>
        </tbody>
    </table>
</div>

<!-- Pagination -->
<div class="admin-pagination">
    <span class="pagination-info" id="paginationInfo">Showing 0 of 0</span>
    <div class="pagination-controls" id="paginationControls"></div>
</div>

<!-- Record Payment Modal -->
<div class="modal" id="paymentModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Record Student Payment</h3>
                <button class="btn-close" onclick="closeModal('paymentModal')">×</button>
            </div>
            <div class="modal-body">
                <form id="paymentForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Select Student *</label>
                            <select class="form-select" id="payment_student" required></select>
                        </div>
                        <div class="form-group">
                            <label>Class</label>
                            <input type="text" class="form-input" id="payment_class" readonly>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Term *</label>
                            <select class="form-select" id="payment_term" required>
                                <option value="">Select Term</option>
                                <option value="1">Term 1</option>
                                <option value="2">Term 2</option>
                                <option value="3">Term 3</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Outstanding Balance</label>
                            <input type="text" class="form-input" id="payment_balance" readonly>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Payment Amount (KES) *</label>
                            <input type="number" class="form-input" id="payment_amount" step="0.01" required>
                        </div>
                        <div class="form-group">
                            <label>Payment Date *</label>
                            <input type="date" class="form-input" id="payment_date" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Payment Method *</label>
                            <select class="form-select" id="payment_method" required>
                                <option value="">Select Method</option>
                                <option value="cash">Cash</option>
                                <option value="mpesa">M-Pesa</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="cheque">Cheque</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Reference/Transaction ID</label>
                            <input type="text" class="form-input" id="payment_reference">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Notes</label>
                        <textarea class="form-textarea" id="payment_notes" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeModal('paymentModal')">Cancel</button>
                <button class="btn btn-primary" onclick="savePayment()">Record Payment</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof FeesController !== 'undefined') {
            FeesController.init({ view: 'admin' });
        }
    });

    function showRecordPaymentModal() {
        document.getElementById('paymentForm').reset();
        document.getElementById('paymentModal').classList.add('show');
    }

    function closeModal(modalId) {
        document.getElementById(modalId).classList.remove('show');
    }

    function toggleSelectAll() {
        const checked = document.getElementById('selectAll').checked;
        document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = checked);
        updateBulkActions();
    }

    function updateBulkActions() {
        const selected = document.querySelectorAll('.row-checkbox:checked').length;
        document.getElementById('selectedCount').textContent = selected;
        document.getElementById('bulkActions').style.display = selected > 0 ? 'flex' : 'none';
    }

    function exportFeesReport() {
        if (typeof FeesController !== 'undefined') {
            FeesController.exportReport();
        }
    }

    function sendReminders() {
        if (typeof FeesController !== 'undefined') {
            FeesController.sendReminders();
        }
    }

    function savePayment() {
        if (typeof FeesController !== 'undefined') {
            FeesController.recordPayment();
        }
    }

    function clearFilters() {
        document.querySelectorAll('.admin-filters input, .admin-filters select').forEach(el => {
            el.value = '';
        });
        if (typeof FeesController !== 'undefined') {
            FeesController.loadData();
        }
    }
</script>
