<?php
/**
 * Staff - Operator Layout
 * For Class Teacher, Subject Teacher (view colleagues)
 * 
 * Features:
 * - Icon-only sidebar (60px)
 * - 1 stat card
 * - Simple directory list
 * - View-only contact info
 */
?>

<link rel="stylesheet" href="/css/school-theme.css">
<link rel="stylesheet" href="/css/roles/operator-theme.css">

<div class="operator-layout">
    <!-- Icon-only Sidebar -->
    <aside class="operator-sidebar">
        <a href="/pages/dashboard.php" class="nav-icon-item" title="Dashboard">🏠</a>
        <a href="/pages/all_staff.php" class="nav-icon-item active" title="Staff">👥</a>
        <a href="/pages/all_teachers.php" class="nav-icon-item" title="Teachers">👩‍🏫</a>
    </aside>

    <!-- Main Content -->
    <main class="operator-main">
        <!-- Header -->
        <header class="operator-header">
            <h1 class="page-title">👥 Staff Directory</h1>
            <p class="page-subtitle">View staff contact information</p>
        </header>

        <!-- Stats Row - 1 card -->
        <div class="operator-stats-grid">
            <div class="stat-card">
                <div class="stat-icon bg-primary">👥</div>
                <div class="stat-content">
                    <span class="stat-value" id="totalStaff">0</span>
                    <span class="stat-label">Total Colleagues</span>
                </div>
            </div>
        </div>

        <!-- Search -->
        <div class="operator-section">
            <div class="search-container">
                <input type="text" class="form-input full-width" id="staffSearch"
                    placeholder="🔍 Search staff by name or department...">
            </div>
        </div>

        <!-- Staff Directory List -->
        <div class="operator-section">
            <div class="directory-list" id="staffDirectory">
                <div class="loading-item">Loading staff directory...</div>
            </div>
        </div>
    </main>
</div>

<style>
    .search-container {
        margin-bottom: var(--space-4);
    }

    .full-width {
        width: 100%;
    }

    .directory-list {
        display: flex;
        flex-direction: column;
        gap: var(--space-3);
    }

    .staff-card {
        display: flex;
        align-items: center;
        gap: var(--space-3);
        padding: var(--space-3);
        background: var(--white);
        border-radius: var(--radius-md);
        border: 1px solid var(--gray-200);
    }

    .staff-avatar {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        object-fit: cover;
    }

    .staff-info {
        flex: 1;
    }

    .staff-name {
        font-weight: 600;
        color: var(--text-primary);
    }

    .staff-role {
        font-size: var(--text-sm);
        color: var(--text-secondary);
    }

    .staff-department {
        font-size: var(--text-xs);
        color: var(--text-tertiary);
    }

    .staff-contact {
        text-align: right;
    }

    .staff-phone {
        font-size: var(--text-sm);
        color: var(--primary-600);
    }

    .staff-email {
        font-size: var(--text-xs);
        color: var(--text-tertiary);
    }
</style>

<script src="/js/components/RoleBasedUI.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        RoleBasedUI.applyLayout();
        loadStaffDirectory();

        document.getElementById('staffSearch').addEventListener('input', function () {
            filterStaff(this.value);
        });
    });

    let allStaff = [];

    async function loadStaffDirectory() {
        const directory = document.getElementById('staffDirectory');

        try {
            const response = await fetch('/api/?route=staff&action=list');
            const data = await response.json();

            if (data.success) {
                allStaff = data.data;
                document.getElementById('totalStaff').textContent = allStaff.length;
                renderStaffList(allStaff);
            } else {
                directory.innerHTML = '<div class="empty-item">Unable to load staff</div>';
            }
        } catch (error) {
            console.error('Error loading staff:', error);
            directory.innerHTML = '<div class="error-item">Error loading staff directory</div>';
        }
    }

    function renderStaffList(staff) {
        const directory = document.getElementById('staffDirectory');

        if (staff.length === 0) {
            directory.innerHTML = '<div class="empty-item">No staff found</div>';
            return;
        }

        directory.innerHTML = staff.map(s => `
            <div class="staff-card">
                <img src="${s.photo || '/images/default-avatar.png'}" alt="${escapeHtml(s.name)}" class="staff-avatar">
                <div class="staff-info">
                    <div class="staff-name">${escapeHtml(s.name)}</div>
                    <div class="staff-role">${escapeHtml(s.role || '-')}</div>
                    <div class="staff-department">${escapeHtml(s.department || '-')}</div>
                </div>
                <div class="staff-contact">
                    <div class="staff-phone">${escapeHtml(s.phone || '-')}</div>
                    <div class="staff-email">${escapeHtml(s.email || '-')}</div>
                </div>
            </div>
        `).join('');
    }

    function filterStaff(query) {
        const q = query.toLowerCase();
        const filtered = allStaff.filter(s =>
            (s.name && s.name.toLowerCase().includes(q)) ||
            (s.department && s.department.toLowerCase().includes(q)) ||
            (s.role && s.role.toLowerCase().includes(q))
        );
        renderStaffList(filtered);
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>"']/g, m => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[m]));
    }
</script>