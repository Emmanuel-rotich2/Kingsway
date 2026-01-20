<?php
/**
 * Activities - Viewer Layout
 * Read-only layout for Students, Parents, Guardians, Interns
 * 
 * Features:
 * - No sidebar (full-width content)
 * - Single summary card
 * - Simple list view (not table)
 * - Read-only (no actions)
 * - Clean, minimal interface
 */
?>

<link rel="stylesheet" href="/css/school-theme.css">
<link rel="stylesheet" href="/css/roles/viewer-theme.css">

<div class="viewer-layout">
    <!-- Header -->
    <header class="viewer-header">
        <div class="logo-title">
            <img src="/images/logo.png" alt="Kingsway">
            <span>Kingsway Academy</span>
        </div>
        <h1 class="page-title">Activities</h1>
        <div class="viewer-user-info">
            <span class="user-name" id="userName">Student</span>
            <div class="user-avatar" id="userAvatar">S</div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="viewer-main">
        <!-- Notice Banner -->
        <div class="viewer-notice">
            <span class="notice-icon">ℹ️</span>
            <div class="notice-content">
                <div class="notice-title">Welcome to School Activities</div>
                <div class="notice-text">Browse available activities and see upcoming events.</div>
            </div>
        </div>

        <!-- Summary Card -->
        <div class="viewer-summary-card">
            <div class="summary-icon">🏆</div>
            <div class="summary-value" id="activeCount">0</div>
            <div class="summary-label">Active Activities</div>
        </div>

        <!-- Info Cards -->
        <div class="viewer-info-grid">
            <div class="viewer-info-card">
                <div class="info-label">Total Activities</div>
                <div class="info-value" id="totalActivities">0</div>
            </div>
            <div class="viewer-info-card">
                <div class="info-label">Upcoming</div>
                <div class="info-value" id="upcomingActivities">0</div>
            </div>
        </div>

        <!-- Activity List -->
        <div class="viewer-list-card">
            <div class="viewer-list-header">
                <span class="list-title">School Activities</span>
                <span class="list-count" id="listCount">0</span>
            </div>
            <ul class="viewer-list" id="activitiesList">
                <!-- Activities loaded dynamically -->
            </ul>
        </div>
    </main>

    <!-- Footer -->
    <footer class="viewer-footer">
        Kingsway Academy &copy; <?php echo date('Y'); ?>
    </footer>
</div>

<script src="/js/components/RoleBasedUI.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        RoleBasedUI.applyLayout();

        const user = AuthContext.getUser();
        if (user) {
            document.getElementById('userName').textContent = user.name || 'Student';
            document.getElementById('userAvatar').textContent = (user.name || 'S').charAt(0).toUpperCase();
        }

        loadActivities();
    });

    async function loadActivities() {
        try {
            const response = await API.activities.getAll();
            if (response.success) {
                renderActivitiesList(response.data);
                updateStats(response.data);
            }
        } catch (error) {
            console.error('Error loading activities:', error);
            showEmptyState();
        }
    }

    function renderActivitiesList(activities) {
        const list = document.getElementById('activitiesList');
        list.innerHTML = '';

        if (activities.length === 0) {
            showEmptyState();
            return;
        }

        activities.forEach(activity => {
            const li = document.createElement('li');
            li.className = 'viewer-list-item';
            li.innerHTML = `
            <div class="item-icon">${getCategoryIcon(activity.category)}</div>
            <div class="item-content">
                <div class="item-title">${escapeHtml(activity.name)}</div>
                <div class="item-subtitle">${activity.category} • ${formatDate(activity.start_date)}</div>
            </div>
            <span class="item-status status-${activity.status}">${activity.status}</span>
        `;
            list.appendChild(li);
        });

        document.getElementById('listCount').textContent = activities.length;
    }

    function updateStats(activities) {
        const active = activities.filter(a => a.status === 'active').length;
        const upcoming = activities.filter(a => a.status === 'upcoming').length;

        document.getElementById('activeCount').textContent = active;
        document.getElementById('totalActivities').textContent = activities.length;
        document.getElementById('upcomingActivities').textContent = upcoming;
    }

    function showEmptyState() {
        const list = document.getElementById('activitiesList');
        list.innerHTML = `
        <div class="viewer-empty-state">
            <div class="empty-icon">📭</div>
            <div class="empty-text">No activities available at the moment.</div>
        </div>
    `;
    }

    function getCategoryIcon(category) {
        const icons = {
            sports: '⚽',
            arts: '🎨',
            clubs: '🎭',
            academic: '📖',
            default: '🏆'
        };
        return icons[category] || icons.default;
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m]);
    }

    function formatDate(dateStr) {
        if (!dateStr) return '';
        return new Date(dateStr).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    }
</script>