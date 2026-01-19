<?php
/**
 * Activities - Manager Layout
 * Compact layout for HODs, Deputy Heads, Accountant, Inventory Manager, etc.
 * 
 * Features:
 * - Compact sidebar (80px, expandable on hover)
 * - 3 stat cards
 * - 2 charts
 * - Standard table columns (7)
 * - View/Edit/Export actions (no delete, no bulk)
 */
?>

<link rel="stylesheet" href="/css/school-theme.css">
<link rel="stylesheet" href="/css/roles/manager-theme.css">

<div class="manager-layout">
    <!-- Compact Sidebar -->
    <aside class="manager-sidebar" id="managerSidebar">
        <div class="logo-section">
            <img src="/images/logo.png" alt="KA">
        </div>

        <nav class="manager-nav">
            <a href="/pages/dashboard.php" class="manager-nav-item" data-tooltip="Dashboard">
                <span class="nav-icon">🏠</span>
                <span class="nav-label">Dashboard</span>
            </a>
            <a href="/pages/manage_activities.php" class="manager-nav-item active" data-tooltip="Activities">
                <span class="nav-icon">🏆</span>
                <span class="nav-label">Activities</span>
            </a>
            <a href="/pages/manage_communications.php" class="manager-nav-item" data-tooltip="Communications">
                <span class="nav-icon">💬</span>
                <span class="nav-label">Communications</span>
            </a>
            <a href="/pages/all_students.php" class="manager-nav-item" data-tooltip="Students">
                <span class="nav-icon">👨‍🎓</span>
                <span class="nav-label">Students</span>
            </a>
            <a href="/pages/all_classes.php" class="manager-nav-item" data-tooltip="Classes">
                <span class="nav-icon">📚</span>
                <span class="nav-label">Classes</span>
            </a>
            <a href="/pages/reports.php" class="manager-nav-item" data-tooltip="Reports">
                <span class="nav-icon">📊</span>
                <span class="nav-label">Reports</span>
            </a>
        </nav>

        <div class="user-avatar" id="userAvatar" title="Profile">M</div>
    </aside>

    <!-- Main Content -->
    <main class="manager-main">
        <!-- Header -->
        <header class="manager-header">
            <h1 class="page-title">🏆 Activities Management</h1>
            <div class="manager-header-actions">
                <button class="btn btn-outline btn-sm" id="exportBtn">
                    📤 Export
                </button>
                <button class="btn btn-primary btn-sm" id="createActivityBtn" data-bs-toggle="modal"
                    data-bs-target="#addActivityModal">
                    ➕ Create
                </button>
            </div>
        </header>

        <!-- Content -->
        <div class="manager-content">
            <!-- Stats Grid - 3 columns -->
            <div class="manager-stats">
                <div class="manager-stat-card">
                    <div class="stat-icon">🏆</div>
                    <div class="stat-content">
                        <div class="stat-value" id="totalActivities">0</div>
                        <div class="stat-label">Total Activities</div>
                    </div>
                </div>
                <div class="manager-stat-card">
                    <div class="stat-icon">✅</div>
                    <div class="stat-content">
                        <div class="stat-value" id="activeActivities">0</div>
                        <div class="stat-label">Active</div>
                    </div>
                </div>
                <div class="manager-stat-card">
                    <div class="stat-icon">👥</div>
                    <div class="stat-content">
                        <div class="stat-value" id="totalParticipants">0</div>
                        <div class="stat-label">Participants</div>
                    </div>
                </div>
            </div>

            <!-- Charts - 2 charts -->
            <div class="manager-charts">
                <div class="chart-card">
                    <div class="chart-header">
                        <h3 class="chart-title">Monthly Trends</h3>
                    </div>
                    <div class="chart-body">
                        <canvas id="trendsChart" height="200"></canvas>
                    </div>
                </div>
                <div class="chart-card">
                    <div class="chart-header">
                        <h3 class="chart-title">By Category</h3>
                    </div>
                    <div class="chart-body">
                        <canvas id="categoryChart" height="200"></canvas>
                    </div>
                </div>
            </div>

            <!-- Data Table -->
            <div class="manager-table-card">
                <div class="manager-table-header">
                    <span class="table-title">All Activities</span>
                    <span class="table-count" id="recordCount">0 records</span>
                </div>

                <div class="manager-filters">
                    <input type="text" class="search-input form-control" id="searchActivities" placeholder="Search...">
                    <select class="filter-select" id="categoryFilter">
                        <option value="">All Categories</option>
                        <option value="sports">Sports</option>
                        <option value="arts">Arts</option>
                        <option value="clubs">Clubs</option>
                    </select>
                    <select class="filter-select" id="statusFilter">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="upcoming">Upcoming</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>

                <div class="table-responsive">
                    <table class="manager-data-table" id="activitiesTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Activity Name</th>
                                <th>Category</th>
                                <th>Date</th>
                                <th>Participants</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="activitiesTableBody">
                            <!-- Data loaded dynamically -->
                        </tbody>
                    </table>
                </div>

                <div class="table-pagination">
                    <span class="pagination-info">Showing 1-20 of <span id="totalRecords">0</span></span>
                    <div class="pagination-controls" id="paginationControls"></div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Include modals -->
<?php include __DIR__ . '/../components/modals/activity_modal.php'; ?>

<script src="/js/components/RoleBasedUI.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        RoleBasedUI.applyLayout();

        // Load user info
        const user = AuthContext.getUser();
        if (user) {
            document.getElementById('userAvatar').textContent = (user.name || 'M').charAt(0).toUpperCase();
        }

        loadActivities();
        initCharts();

        // Event listeners
        document.getElementById('searchActivities').addEventListener('input', debounce(filterActivities, 300));
        document.getElementById('categoryFilter').addEventListener('change', filterActivities);
        document.getElementById('statusFilter').addEventListener('change', filterActivities);
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
            <td>${activity.id}</td>
            <td><strong>${escapeHtml(activity.name)}</strong></td>
            <td><span class="badge category-${activity.category}">${activity.category}</span></td>
            <td>${formatDate(activity.start_date)}</td>
            <td>${activity.participant_count || 0}</td>
            <td><span class="status-badge status-${activity.status}">${activity.status}</span></td>
            <td class="manager-row-actions">
                <button class="action-btn view-btn" onclick="viewActivity(${activity.id})" title="View">👁️</button>
                <button class="action-btn edit-btn" onclick="editActivity(${activity.id})" title="Edit">✏️</button>
            </td>
        `;
            tbody.appendChild(row);
        });

        document.getElementById('recordCount').textContent = `${activities.length} records`;
        document.getElementById('totalRecords').textContent = activities.length;
    }

    function updateStats(activities) {
        document.getElementById('totalActivities').textContent = activities.length;
        document.getElementById('activeActivities').textContent = activities.filter(a => a.status === 'active').length;
        document.getElementById('totalParticipants').textContent = activities.reduce((sum, a) => sum + (a.participant_count || 0), 0);
    }

    function initCharts() {
        // Trends Chart
        const trendsCtx = document.getElementById('trendsChart');
        if (trendsCtx) {
            new Chart(trendsCtx, {
                type: 'bar',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr'],
                    datasets: [{
                        label: 'Activities',
                        data: [8, 12, 10, 15],
                        backgroundColor: '#166534'
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false }
            });
        }

        // Category Chart
        const categoryCtx = document.getElementById('categoryChart');
        if (categoryCtx) {
            new Chart(categoryCtx, {
                type: 'pie',
                data: {
                    labels: ['Sports', 'Arts', 'Clubs'],
                    datasets: [{ data: [40, 35, 25], backgroundColor: ['#166534', '#ca8a04', '#0369a1'] }]
                },
                options: { responsive: true, maintainAspectRatio: false }
            });
        }
    }

    function filterActivities() {
        const search = document.getElementById('searchActivities').value.toLowerCase();
        const category = document.getElementById('categoryFilter').value;
        const status = document.getElementById('statusFilter').value;

        document.querySelectorAll('#activitiesTableBody tr').forEach(row => {
            const text = row.textContent.toLowerCase();
            const matchesSearch = text.includes(search);
            const matchesCategory = !category || row.querySelector('.badge')?.textContent === category;
            const matchesStatus = !status || row.querySelector('.status-badge')?.textContent === status;
            row.style.display = matchesSearch && matchesCategory && matchesStatus ? '' : 'none';
        });
    }

    function viewActivity(id) { window.location.href = `/pages/view_activity.php?id=${id}`; }
    function editActivity(id) { console.log('Edit activity:', id); }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m]);
    }

    function formatDate(dateStr) {
        if (!dateStr) return '-';
        return new Date(dateStr).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    }

    function debounce(fn, delay) {
        let timeout;
        return function (...args) { clearTimeout(timeout); timeout = setTimeout(() => fn.apply(this, args), delay); };
    }
</script>