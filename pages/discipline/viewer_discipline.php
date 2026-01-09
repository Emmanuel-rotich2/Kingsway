<?php
/**
 * Discipline - Viewer Layout
 * Read-only for Students and Parents
 * 
 * Features:
 * - No sidebar
 * - Summary card
 * - Simple list of own discipline records
 * - No actions, read-only
 */
?>

<link rel="stylesheet" href="/css/school-theme.css">
<link rel="stylesheet" href="/css/roles/viewer-theme.css">

<div class="viewer-layout">
    <!-- Header -->
    <header class="viewer-header">
        <a href="/pages/dashboard.php" class="back-link">← Dashboard</a>
        <h1 class="page-title">⚖️ Discipline Records</h1>
    </header>

    <!-- Main Content -->
    <main class="viewer-main">
        <!-- Summary Card -->
        <div class="viewer-summary-card">
            <div class="summary-icon">⚖️</div>
            <div class="summary-stat">
                <span class="summary-value" id="totalCases">0</span>
                <span class="summary-label">Cases</span>
            </div>
            <div class="summary-stat">
                <span class="summary-value" id="resolvedCases">0</span>
                <span class="summary-label">Resolved</span>
            </div>
        </div>

        <!-- Cases List -->
        <div class="viewer-list-container" id="casesContainer">
            <div class="list-header">
                <span class="list-title">Discipline History</span>
            </div>

            <div class="viewer-list" id="casesList">
                <!-- Loaded dynamically -->
            </div>
        </div>
    </main>
</div>

<script src="/js/components/RoleBasedUI.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        RoleBasedUI.applyLayout();
        loadDisciplineRecords();
    });

    async function loadDisciplineRecords() {
        const container = document.getElementById('casesList');
        const user = AuthContext.getUser();

        if (!user) {
            container.innerHTML = '<div class="empty-list">Please log in to view records</div>';
            return;
        }

        try {
            let response;
            if (user.role === 'Student') {
                response = await API.discipline.getMyRecords();
            } else if (['Parent', 'Guardian'].includes(user.role)) {
                response = await API.discipline.getChildRecords();
            } else {
                container.innerHTML = '<div class="info-card">Access restricted</div>';
                return;
            }

            if (response.success && response.data.length > 0) {
                renderCasesList(response.data);
                updateSummary(response.data);
            } else {
                container.innerHTML = '<div class="empty-list">🎉 No discipline cases on record</div>';
            }
        } catch (error) {
            console.error('Error loading records:', error);
            container.innerHTML = '<div class="error-card">Unable to load records</div>';
        }
    }

    function renderCasesList(cases) {
        const container = document.getElementById('casesList');
        container.innerHTML = '';

        cases.forEach(c => {
            const item = document.createElement('div');
            item.className = 'viewer-list-item';
            item.innerHTML = `
            <div class="list-item-icon ${getSeverityColor(c.severity)}">⚖️</div>
            <div class="list-item-content">
                <div class="list-item-header">
                    <span class="list-item-title">${escapeHtml(c.category)}</span>
                    <span class="list-item-date">${formatDate(c.incident_date)}</span>
                </div>
                <div class="list-item-body">
                    <p>${escapeHtml(c.description)}</p>
                    ${c.action_taken ? `<p class="action-taken"><strong>Action:</strong> ${escapeHtml(c.action_taken)}</p>` : ''}
                </div>
                <div class="list-item-footer">
                    <span class="status-badge status-${c.status}">${c.status}</span>
                    ${c.student_name ? `<span class="student-name">${escapeHtml(c.student_name)}</span>` : ''}
                </div>
            </div>
        `;
            container.appendChild(item);
        });
    }

    function updateSummary(cases) {
        document.getElementById('totalCases').textContent = cases.length;
        document.getElementById('resolvedCases').textContent = cases.filter(c => c.status === 'resolved').length;
    }

    function getSeverityColor(severity) {
        const colors = { minor: 'bg-yellow', moderate: 'bg-orange', major: 'bg-red', critical: 'bg-darkred' };
        return colors[severity] || 'bg-gray';
    }

    function formatDate(d) { return d ? new Date(d).toLocaleDateString('en-KE', { day: 'numeric', month: 'short', year: 'numeric' }) : '-'; }
    function escapeHtml(s) { return s ? s.replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m]) : ''; }
</script>

<style>
    /* Viewer-specific styles for discipline list */
    .viewer-list-container {
        max-width: 600px;
        margin: 0 auto;
        padding: 1rem;
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    }

    .list-header {
        padding: 0.75rem 1rem;
        border-bottom: 1px solid #eee;
    }

    .list-title {
        font-weight: 600;
        color: var(--green-700);
    }

    .viewer-list-item {
        display: flex;
        gap: 1rem;
        padding: 1rem;
        border-bottom: 1px solid #f0f0f0;
    }

    .viewer-list-item:last-child {
        border-bottom: none;
    }

    .list-item-icon {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        flex-shrink: 0;
    }

    .bg-yellow {
        background: #fef3c7;
    }

    .bg-orange {
        background: #fed7aa;
    }

    .bg-red {
        background: #fecaca;
    }

    .bg-darkred {
        background: #fca5a5;
    }

    .bg-gray {
        background: #e5e7eb;
    }

    .list-item-content {
        flex: 1;
        min-width: 0;
    }

    .list-item-header {
        display: flex;
        justify-content: space-between;
        margin-bottom: 0.5rem;
    }

    .list-item-title {
        font-weight: 600;
        text-transform: capitalize;
    }

    .list-item-date {
        font-size: 0.75rem;
        color: #666;
    }

    .list-item-body {
        font-size: 0.9rem;
        color: #444;
        margin-bottom: 0.5rem;
    }

    .list-item-body p {
        margin: 0 0 0.25rem 0;
    }

    .action-taken {
        font-size: 0.85rem;
        color: #666;
        font-style: italic;
    }

    .list-item-footer {
        display: flex;
        gap: 0.5rem;
        align-items: center;
    }

    .student-name {
        font-size: 0.75rem;
        color: #666;
    }

    .empty-list,
    .info-card,
    .error-card {
        text-align: center;
        padding: 3rem 2rem;
        color: #666;
    }

    .error-card {
        color: #dc2626;
    }
</style>