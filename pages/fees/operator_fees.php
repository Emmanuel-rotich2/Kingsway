<?php
/**
 * Fees - Operator Layout
 * For Class Teacher (view class fee status)
 * 
 * Features:
 * - Icon-only sidebar (60px)
 * - 2 stat cards (class-specific)
 * - Simple table (class students only)
 * - No payment actions
 */
?>

<link rel="stylesheet" href="/css/school-theme.css">
<link rel="stylesheet" href="/css/roles/operator-theme.css">

<div class="operator-layout">
    <!-- Icon-only Sidebar -->
    <aside class="operator-sidebar">
        <a href="/pages/dashboard.php" class="nav-icon-item" title="Dashboard">🏠</a>
        <a href="/pages/manage_fees.php" class="nav-icon-item active" title="Fees">🧾</a>
        <a href="/pages/all_students.php" class="nav-icon-item" title="Students">👨‍🎓</a>
    </aside>

    <!-- Main Content -->
    <main class="operator-main">
        <!-- Header -->
        <header class="operator-header">
            <h1 class="page-title">🧾 Class Fee Status</h1>
            <p class="page-subtitle">View fee payment status for your class</p>
        </header>

        <!-- Stats Row - 2 cards -->
        <div class="operator-stats-grid">
            <div class="stat-card">
                <div class="stat-icon bg-success">✅</div>
                <div class="stat-content">
                    <span class="stat-value" id="paidStudents">0</span>
                    <span class="stat-label">Fully Paid</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon bg-warning">⏳</div>
                <div class="stat-content">
                    <span class="stat-value" id="pendingStudents">0</span>
                    <span class="stat-label">Outstanding</span>
                </div>
            </div>
        </div>

        <!-- Class Students Fee Status -->
        <div class="operator-section">
            <div class="section-header">
                <h2>📋 Student Fee Status</h2>
                <select class="form-select small-select" id="termFilter">
                    <option value="1">Term 1</option>
                    <option value="2">Term 2</option>
                    <option value="3">Term 3</option>
                </select>
            </div>

            <!-- Simple Table -->
            <div class="operator-table-container">
                <table class="operator-data-table" id="classFeesTable">
                    <thead>
                        <tr>
                            <th>Adm. No.</th>
                            <th>Student Name</th>
                            <th class="text-end">Balance</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="feesTableBody">
                        <tr>
                            <td colspan="4" class="loading-row">Loading...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Note -->
        <div class="operator-section">
            <div class="info-box">
                <strong>Note:</strong> For payment concerns, please direct parents to the Accountant's office.
            </div>
        </div>
    </main>
</div>

<style>
    .small-select {
        width: auto;
        min-width: 120px;
    }

    .info-box {
        background: var(--info-50);
        border: 1px solid var(--info-200);
        border-radius: var(--radius-md);
        padding: var(--space-3);
        color: var(--info-700);
    }
</style>

<script src="/js/components/RoleBasedUI.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        RoleBasedUI.applyLayout();
        loadClassFees();

        document.getElementById('termFilter').addEventListener('change', loadClassFees);
    });

    async function loadClassFees() {
        const term = document.getElementById('termFilter').value;
        const tbody = document.getElementById('feesTableBody');

        try {
            const response = await fetch(`/api/?route=fees&action=my-class&term=${term}`);
            const data = await response.json();

            if (data.success) {
                const students = data.data;

                // Update stats
                const paid = students.filter(s => s.status === 'paid').length;
                const pending = students.filter(s => s.status !== 'paid').length;

                document.getElementById('paidStudents').textContent = paid;
                document.getElementById('pendingStudents').textContent = pending;

                // Render table
                if (students.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="4" class="empty-row">No students in your class</td></tr>';
                    return;
                }

                tbody.innerHTML = students.map(s => `
                    <tr>
                        <td>${escapeHtml(s.admission_no)}</td>
                        <td>${escapeHtml(s.name)}</td>
                        <td class="text-end ${s.balance > 0 ? 'text-danger' : 'text-success'}">
                            KES ${formatNumber(s.balance || 0)}
                        </td>
                        <td>
                            <span class="status-badge ${s.status}">${formatStatus(s.status)}</span>
                        </td>
                    </tr>
                `).join('');
            }
        } catch (error) {
            console.error('Error loading fees:', error);
            tbody.innerHTML = '<tr><td colspan="4" class="error-row">Error loading data</td></tr>';
        }
    }

    function formatNumber(num) {
        return new Intl.NumberFormat('en-KE').format(num);
    }

    function formatStatus(status) {
        const map = {
            'paid': '✅ Paid',
            'partial': '⚠️ Partial',
            'outstanding': '⏳ Outstanding',
            'overdue': '❌ Overdue'
        };
        return map[status] || status;
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>"']/g, m => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[m]));
    }
</script>