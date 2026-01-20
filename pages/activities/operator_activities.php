<?php
/**
 * Activities - Operator Layout
 * Minimal layout for Class Teachers, Subject Teachers, Chaplain, Cateress, Driver, etc.
 * 
 * Features:
 * - Mini sidebar (icons only)
 * - 2 stat cards
 * - No charts (task-focused)
 * - Essential table columns (4)
 * - View action only
 */
?>

<link rel="stylesheet" href="/css/school-theme.css">
<link rel="stylesheet" href="/css/roles/operator-theme.css">

<div class="operator-layout">
    <!-- Mini Sidebar -->
    <aside class="operator-sidebar" id="operatorSidebar">
        <div class="logo-section">
            <img src="/images/logo.png" alt="KA">
        </div>

        <nav class="operator-nav">
            <a href="/pages/dashboard.php" class="operator-nav-item" data-tooltip="Dashboard">🏠</a>
            <a href="/pages/manage_activities.php" class="operator-nav-item active" data-tooltip="Activities">🏆</a>
            <a href="/pages/manage_communications.php" class="operator-nav-item" data-tooltip="Messages">💬</a>
            <a href="/pages/all_students.php" class="operator-nav-item" data-tooltip="Students">👨‍🎓</a>
            <a href="/pages/my_classes.php" class="operator-nav-item" data-tooltip="My Classes">📚</a>
        </nav>

        <div class="user-avatar" id="userAvatar">O</div>
    </aside>

    <!-- Main Content -->
    <main class="operator-main">
        <!-- Header -->
        <header class="operator-header">
            <h1 class="page-title">🏆 Activities</h1>
        </header>

        <!-- Content -->
        <div class="operator-content">
            <!-- Stats - 2 columns -->
            <div class="operator-stats">
                <div class="operator-stat-card">
                    <div class="stat-icon">🏆</div>
                    <div class="stat-info">
                        <div class="stat-value" id="totalActivities">0</div>
                        <div class="stat-label">Activities</div>
                    </div>
                </div>
                <div class="operator-stat-card">
                    <div class="stat-icon">✅</div>
                    <div class="stat-info">
                        <div class="stat-value" id="activeActivities">0</div>
                        <div class="stat-label">Active</div>
                    </div>
                </div>
            </div>

            <!-- Simple Filter -->
            <div class="operator-filters">
                <input type="text" class="search-input form-control" id="searchActivities"
                    placeholder="Search activities...">
            </div>

            <!-- Data Table - Essential columns only -->
            <div class="operator-table-card">
                <div class="operator-table-header">
                    <span class="table-title">All Activities</span>
                </div>

                <table class="operator-data-table" id="activitiesTable">
                    <thead>
                        <tr>
                            <th>Activity</th>
                            <th>Category</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="activitiesTableBody">
                        <!-- Data loaded dynamically -->
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<script src="/js/components/RoleBasedUI.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        RoleBasedUI.applyLayout();

        const user = AuthContext.getUser();
        if (user) {
            document.getElementById('userAvatar').textContent = (user.name || 'O').charAt(0).toUpperCase();
        }

        loadActivities();

        document.getElementById('searchActivities').addEventListener('input', debounce(filterActivities, 300));
    });

    async function loadActivities() {
        try {
            const response = await API.activities.getAll();
            if (response.success) {
                renderActivitiesTable(response.data);
                updateStats(response.data);
            }
        } catch (error) {
            console.error('Error loading activities:', error);
        }
    }

    function renderActivitiesTable(activities) {
        const tbody = document.getElementById('activitiesTableBody');
        tbody.innerHTML = '';

        activities.forEach(activity => {
            const row = document.createElement('tr');
            row.innerHTML = `
            <td><strong>${escapeHtml(activity.name)}</strong></td>
            <td><span class="badge">${activity.category}</span></td>
            <td>${formatDate(activity.start_date)}</td>
            <td class="operator-row-actions">
                <button class="action-btn" onclick="viewActivity(${activity.id})" title="View">View</button>
            </td>
        `;
            tbody.appendChild(row);
        });
    }

    function updateStats(activities) {
        document.getElementById('totalActivities').textContent = activities.length;
        document.getElementById('activeActivities').textContent = activities.filter(a => a.status === 'active').length;
    }

    function filterActivities() {
        const search = document.getElementById('searchActivities').value.toLowerCase();
        document.querySelectorAll('#activitiesTableBody tr').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(search) ? '' : 'none';
        });
    }

    function viewActivity(id) {
        window.location.href = `/pages/view_activity.php?id=${id}`;
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m]);
    }

    function formatDate(dateStr) {
        if (!dateStr) return '-';
        return new Date(dateStr).toLocaleDateString('en-GB', { day: '2-digit', month: 'short' });
    }

    function debounce(fn, delay) {
        let timeout;
        return function (...args) { clearTimeout(timeout); timeout = setTimeout(() => fn.apply(this, args), delay); };
    }
</script>