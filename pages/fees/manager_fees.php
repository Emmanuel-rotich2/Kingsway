<?php
/**
 * Fees - Manager Layout
 * For Accountant, Bursar
 *
 * Features:
 * - 4 stat cards
 * - 1 chart (collection trend)
 * - Full data table
 * - Actions: Record Payment, Export
 */
/* PARTIAL — no DOCTYPE/html/head/body. Injected into app shell via fetch. */
?>

<!-- Stats Row - 4 cards -->
<div class="manager-stats-grid" style="grid-template-columns: repeat(4, 1fr);">
    <div class="stat-card">
        <div class="stat-icon bg-info">📋</div>
        <div class="stat-content">
            <span class="stat-value" id="totalExpected">KES 0</span>
            <span class="stat-label">Expected</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-success">💵</div>
        <div class="stat-content">
            <span class="stat-value" id="totalCollected">KES 0</span>
            <span class="stat-label">Collected</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-warning">⏳</div>
        <div class="stat-content">
            <span class="stat-value" id="outstandingBalance">KES 0</span>
            <span class="stat-label">Outstanding</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-danger">⚠️</div>
        <div class="stat-content">
            <span class="stat-value" id="overdueCount">0</span>
            <span class="stat-label">Overdue</span>
        </div>
    </div>
</div>

<!-- Chart -->
<div class="manager-charts-grid">
    <div class="chart-card">
        <h3>📈 Collection Trend</h3>
        <div class="chart-container">
            <canvas id="collectionChart"></canvas>
        </div>
    </div>
</div>

<!-- Filters Section -->
<div class="manager-filters">
    <input type="text" class="form-input" id="studentSearch" placeholder="Search student/admission no...">
    <select class="form-select" id="classFilter">
        <option value="">All Classes</option>
    </select>
    <select class="form-select" id="termFilter">
        <option value="">All Terms</option>
        <option value="1">Term 1</option>
        <option value="2">Term 2</option>
        <option value="3">Term 3</option>
    </select>
    <select class="form-select" id="statusFilter">
        <option value="">All Status</option>
        <option value="paid">Paid</option>
        <option value="partial">Partial</option>
        <option value="outstanding">Outstanding</option>
        <option value="overdue">Overdue</option>
    </select>
    <button class="btn btn-outline-sm" onclick="clearFilters()">Clear</button>
    <button class="btn btn-outline" onclick="exportFees()">📥 Export</button>
    <button class="btn btn-primary" onclick="showRecordPaymentModal()">➕ Record Payment</button>
</div>

<!-- Data Table -->
<div class="manager-table-container">
    <table class="manager-data-table" id="feesTable">
        <thead>
            <tr>
                <th>Admission No.</th>
                <th>Student Name</th>
                <th>Class</th>
                <th>Term</th>
                <th class="text-end">Expected</th>
                <th class="text-end">Paid</th>
                <th class="text-end">Balance</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="feesTableBody">
            <tr>
                <td colspan="9" class="loading-row">Loading fees...</td>
            </tr>
        </tbody>
    </table>
</div>

<!-- Pagination -->
<div class="manager-pagination">
    <span class="pagination-info" id="paginationInfo">Showing 0 of 0</span>
    <div class="pagination-controls" id="paginationControls"></div>
</div>

<!-- Record Payment Modal -->
<div class="modal" id="paymentModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Record Payment</h3>
                <button class="btn-close" onclick="closeModal('paymentModal')">×</button>
            </div>
            <div class="modal-body">
                <form id="paymentForm">
                    <div class="form-group">
                        <label>Select Student *</label>
                        <select class="form-select" id="payment_student" required></select>
                    </div>

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

                    <div class="form-group">
                        <label>Payment Amount (KES) *</label>
                        <input type="number" class="form-input" id="payment_amount" step="0.01" required>
                    </div>

                    <div class="form-group">
                        <label>Payment Date *</label>
                        <input type="date" class="form-input" id="payment_date" required>
                    </div>

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
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeModal('paymentModal')">Cancel</button>
                <button class="btn btn-primary" onclick="savePayment()">Record Payment</button>
            </div>
        </div>
    </div>
</div>

<script src="<?= $appBase ?>js/pages/studentFees.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof FeesController !== 'undefined') {
            FeesController.init({ view: 'manager' });
        }
    });

    function showRecordPaymentModal() {
        document.getElementById('paymentForm').reset();
        document.getElementById('paymentModal').classList.add('show');
    }

    function closeModal(modalId) {
        document.getElementById(modalId).classList.remove('show');
    }

    function exportFees() {
        if (typeof FeesController !== 'undefined') {
            FeesController.exportReport();
        }
    }

    function savePayment() {
        if (typeof FeesController !== 'undefined') {
            FeesController.recordPayment();
        }
    }

    function clearFilters() {
        document.querySelectorAll('.manager-filters input, .manager-filters select').forEach(el => {
            el.value = '';
        });
        if (typeof FeesController !== 'undefined') {
            FeesController.loadData();
        }
    }
</script>
