<?php
/**
 * Finance - Viewer Layout
 * For Students, Parents (no access to finance details)
 * 
 * Features:
 * - No sidebar
 * - Fee balance card only
 * - Payment history
 * - No actions
 */
?>

<link rel="stylesheet" href="/Kingsway/css/school-theme.css">
<link rel="stylesheet" href="/Kingsway/css/roles/viewer-theme.css">

<div class="viewer-layout">
    <!-- Header -->
    <header class="viewer-header">
        <a href="/Kingsway/home.php?route=dashboard" class="back-link">← Dashboard</a>
        <h1 class="page-title">💰 My Fees</h1>
    </header>

    <!-- Main Content -->
    <main class="viewer-main">
        <!-- Fee Balance Card -->
        <div class="viewer-profile-card">
            <div class="profile-header">
                <div class="stat-icon bg-primary">🧾</div>
                <div class="profile-name-section">
                    <h2 class="profile-name">Fee Status</h2>
                    <span class="profile-id" id="currentTerm">Term 1, 2024</span>
                </div>
            </div>
            
            <div class="profile-body">
                <div class="fee-summary">
                    <div class="fee-item">
                        <span class="fee-label">Total Fee</span>
                        <span class="fee-value" id="totalFee">KES 0</span>
                    </div>
                    <div class="fee-item">
                        <span class="fee-label">Amount Paid</span>
                        <span class="fee-value text-success" id="amountPaid">KES 0</span>
                    </div>
                    <div class="fee-item highlight">
                        <span class="fee-label">Balance Due</span>
                        <span class="fee-value text-danger" id="balanceDue">KES 0</span>
                    </div>
                </div>
                
                <div class="fee-status-indicator" id="feeStatus">
                    <span class="status-badge">Loading...</span>
                </div>
            </div>
        </div>

        <!-- Payment History -->
        <div class="viewer-section">
            <h3>📜 Payment History</h3>
            
            <div class="viewer-list" id="paymentHistoryList">
                <div class="loading-item">Loading payment history...</div>
            </div>
        </div>

        <!-- Download Statement -->
        <div class="viewer-section">
            <button class="btn btn-outline-full" onclick="downloadStatement()">
                📥 Download Fee Statement
            </button>
        </div>
    </main>
</div>

<style>
    .fee-summary {
        display: flex;
        flex-direction: column;
        gap: var(--space-3);
        margin-bottom: var(--space-4);
    }
    
    .fee-item {
        display: flex;
        justify-content: space-between;
        padding: var(--space-3);
        background: var(--cream-50);
        border-radius: var(--radius-md);
    }
    
    .fee-item.highlight {
        background: var(--danger-100);
        border: 1px solid var(--danger-300);
    }
    
    .fee-label {
        color: var(--text-secondary);
        font-weight: 500;
    }
    
    .fee-value {
        font-weight: 700;
        font-size: var(--text-lg);
    }
    
    .fee-status-indicator {
        text-align: center;
        padding: var(--space-4);
    }
    
    .status-badge {
        display: inline-block;
        padding: var(--space-2) var(--space-4);
        border-radius: var(--radius-full);
        font-weight: 600;
    }
    
    .status-badge.paid {
        background: var(--success-100);
        color: var(--success-700);
    }
    
    .status-badge.partial {
        background: var(--warning-100);
        color: var(--warning-700);
    }
    
    .status-badge.overdue {
        background: var(--danger-100);
        color: var(--danger-700);
    }
    
    .viewer-list {
        display: flex;
        flex-direction: column;
        gap: var(--space-2);
    }
    
    .payment-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: var(--space-3);
        background: var(--white);
        border-radius: var(--radius-md);
        border: 1px solid var(--gray-200);
    }
    
    .payment-date {
        color: var(--text-secondary);
        font-size: var(--text-sm);
    }
    
    .payment-amount {
        font-weight: 600;
        color: var(--success-600);
    }
    
    .payment-method {
        font-size: var(--text-xs);
        color: var(--text-tertiary);
    }
    
    .btn-outline-full {
        width: 100%;
        padding: var(--space-3);
        border: 2px solid var(--primary-600);
        color: var(--primary-600);
        background: transparent;
        border-radius: var(--radius-md);
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .btn-outline-full:hover {
        background: var(--primary-600);
        color: var(--white);
    }
</style>

<script src="/js/components/RoleBasedUI.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        RoleBasedUI.applyLayout();
        loadFeeDetails();
    });
    
    async function loadFeeDetails() {
        try {
            // Load fee details for current user
            const response = await fetch('/api/?route=fees&action=my-balance');
            const data = await response.json();
            
            if (data.success) {
                const fee = data.data;
                document.getElementById('currentTerm').textContent = fee.term_name || 'Current Term';
                document.getElementById('totalFee').textContent = 'KES ' + formatNumber(fee.total_fee || 0);
                document.getElementById('amountPaid').textContent = 'KES ' + formatNumber(fee.amount_paid || 0);
                document.getElementById('balanceDue').textContent = 'KES ' + formatNumber(fee.balance || 0);
                
                // Update status badge
                const statusEl = document.getElementById('feeStatus');
                if (fee.balance <= 0) {
                    statusEl.innerHTML = '<span class="status-badge paid">✅ Fully Paid</span>';
                } else if (fee.amount_paid > 0) {
                    statusEl.innerHTML = '<span class="status-badge partial">⚠️ Partial Payment</span>';
                } else {
                    statusEl.innerHTML = '<span class="status-badge overdue">❌ Outstanding</span>';
                }
            }
            
            // Load payment history
            const historyResponse = await fetch('/api/?route=fees&action=my-payments');
            const historyData = await historyResponse.json();
            
            const historyList = document.getElementById('paymentHistoryList');
            if (historyData.success && historyData.data.length > 0) {
                historyList.innerHTML = historyData.data.map(payment => `
                    <div class="payment-item">
                        <div>
                            <div class="payment-date">${formatDate(payment.payment_date)}</div>
                            <div class="payment-method">${escapeHtml(payment.payment_method)}</div>
                        </div>
                        <div class="payment-amount">KES ${formatNumber(payment.amount)}</div>
                    </div>
                `).join('');
            } else {
                historyList.innerHTML = '<div class="empty-item">No payment records found</div>';
            }
        } catch (error) {
            console.error('Error loading fee details:', error);
        }
    }
    
    function downloadStatement() {
        window.open('/api/?route=fees&action=download-statement', '_blank');
    }
    
    function formatNumber(num) {
        return new Intl.NumberFormat('en-KE').format(num);
    }
    
    function formatDate(dateStr) {
        return new Date(dateStr).toLocaleDateString('en-KE', { 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric' 
        });
    }
    
    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>"']/g, m => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[m]));
    }
</script>
