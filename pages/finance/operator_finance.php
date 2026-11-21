<?php
/**
 * Finance - Operator Layout
 * For Class Teacher, Subject Teacher (view-only for budget/expense awareness)
 *
 * Features:
 * - 2 stat cards (relevant to them)
 * - Simple table (no charts)
 * - No actions (read-only)
 * - Budget request section
 */
/* PARTIAL — no DOCTYPE/html/head/body. Injected into app shell via fetch. */
?>

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
                    <th scope="col">Date</th>
                    <th scope="col">Type</th>
                    <th scope="col">Category</th>
                    <th scope="col">Description</th>
                    <th scope="col">Amount</th>
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
    <button class="btn btn-primary" data-kw-onclick="showRequestModal()">📝 Submit Resource Request</button>
</div>

<!-- Resource Request Modal -->
<div class="modal" id="requestModal">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Resource Request</h3>
                <button class="btn-close" data-bs-dismiss="modal" data-kw-onclick="closeModal('requestModal')">×</button>
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
                <button class="btn btn-outline" data-bs-dismiss="modal" data-kw-onclick="closeModal('requestModal')">Cancel</button>
                <button class="btn btn-primary" data-kw-onclick="submitRequest()" data-year-write>Submit Request</button>
            </div>
        </div>
    </div>
</div>

<?php asset_script($appBase, 'js/pages/finance.js'); ?>
<script nonce="<?= csp_nonce() ?>">
    function initializeFinanceRole() {
        if (typeof FinanceController !== 'undefined') {
            FinanceController.init({ view: 'operator' });
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeFinanceRole, { once: true });
    } else {
        initializeFinanceRole();
    }

    function showRequestModal() {
        document.getElementById('requestForm').reset();
        openFeeModal('requestModal');
    }

    // Open through the shared helper so Bootstrap owns the instance. Adding the
    // class by hand creates no Modal object, which left backdrop/body-lock
    // reconciliation to the close path only.
    function openFeeModal(modalId) {
        if (window.showKwModal) { window.showKwModal(modalId); return; }
        const el = document.getElementById(modalId);
        if (el && window.bootstrap && window.bootstrap.Modal) {
            window.bootstrap.Modal.getOrCreateInstance(el).show();
        } else if (el) {
            el.classList.add('show');
        }
    }

    // Delegate to Bootstrap: removing only the `show` class left the
    // .modal-backdrop in the DOM and body.modal-open behind, so the page stayed
    // dark and unscrollable after closing. hide() also fires hidden.bs.modal,
    // which the shared sweep in js/api.js uses to reconcile state.
    function closeModal(modalId) {
        const el = document.getElementById(modalId);
        if (!el) return;
        const inst = window.bootstrap && window.bootstrap.Modal
            ? window.bootstrap.Modal.getInstance(el)
            : null;
        if (inst) { inst.hide(); return; }
        el.classList.remove('show');
    }

    function submitRequest() {
        if (typeof FinanceController !== 'undefined') {
            FinanceController.submitRequest();
        }
    }
</script>
