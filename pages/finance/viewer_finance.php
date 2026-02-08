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

<script src="/Kingsway/js/components/RoleBasedUI.js"></script>
<script src="/Kingsway/js/pages/finance.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        RoleBasedUI.applyLayout();
        if (typeof FinanceController !== 'undefined') {
            FinanceController.init({ view: 'viewer' });
        }
    });

    function downloadStatement() {
        if (typeof FinanceController !== 'undefined') {
            FinanceController.downloadStatement();
        }
    }
</script>
