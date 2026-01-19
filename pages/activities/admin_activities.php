<?php
/**
 * Activities - Admin Layout
 * Full-featured layout for System Administrator, Director, Headteacher, School Administrator
 * 
 * Features:
 * - Full 280px sidebar
 * - 4 stat cards (Total, Active, Upcoming, Participants)
 * - Charts (Activity trends, Category distribution)
 * - All table columns with full actions
 * - Bulk operations enabled
 * - Create/Edit/Delete capabilities
 */

// This template is included by manage_activities.php based on role category
// Access session variables set by the parent
?>

<link rel="stylesheet" href="/css/school-theme.css">
<link rel="stylesheet" href="/css/roles/admin-theme.css">

<div class="admin-layout">
    <!-- Sidebar Navigation -->
    <aside class="admin-sidebar" id="adminSidebar">
        <div class="logo-section">
            <img src="/images/logo.png" alt="Kingsway Academy">
            <span class="logo-text">Kingsway Academy</span>
        </div>

        <nav class="admin-nav">
            <div class="nav-section">
                <span class="nav-section-title">Main</span>
                <a href="/pages/dashboard.php" class="admin-nav-item">
                    <span class="nav-icon">🏠</span>
                    <span class="nav-label">Dashboard</span>
                </a>
                <a href="/pages/manage_activities.php" class="admin-nav-item active">
                    <span class="nav-icon">🏆</span>
                    <span class="nav-label">Activities</span>
                </a>
                <a href="/pages/manage_communications.php" class="admin-nav-item">
                    <span class="nav-icon">💬</span>
                    <span class="nav-label">Communications</span>
                </a>
            </div>

            <div class="nav-section">
                <span class="nav-section-title">Academics</span>
                <a href="/pages/all_students.php" class="admin-nav-item">
                    <span class="nav-icon">👨‍🎓</span>
                    <span class="nav-label">Students</span>
                </a>
                <a href="/pages/all_teachers.php" class="admin-nav-item">
                    <span class="nav-icon">👨‍🏫</span>
                    <span class="nav-label">Teachers</span>
                </a>
                <a href="/pages/all_classes.php" class="admin-nav-item">
                    <span class="nav-icon">📚</span>
                    <span class="nav-label">Classes</span>
                </a>
            </div>

            <div class="nav-section">
                <span class="nav-section-title">Administration</span>
                <a href="/pages/manage_finance.php" class="admin-nav-item">
                    <span class="nav-icon">💰</span>
                    <span class="nav-label">Finance</span>
                </a>
                <a href="/pages/all_staff.php" class="admin-nav-item">
                    <span class="nav-icon">👥</span>
                    <span class="nav-label">Staff</span>
                </a>
                <a href="/pages/settings.php" class="admin-nav-item">
                    <span class="nav-icon">⚙️</span>
                    <span class="nav-label">Settings</span>
                </a>
            </div>
        </nav>

        <div class="user-section">
            <div class="user-avatar" id="userAvatar">A</div>
            <div class="user-info">
                <span class="user-name" id="userName">Administrator</span>
                <span class="user-role" id="userRole">System Admin</span>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="admin-main">
        <!-- Page Header -->
        <header class="admin-header">
            <div class="breadcrumb">
                <a href="/pages/dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="/pages/manage_activities.php">Activities</a>
            </div>
            <h1 class="page-title">Activities Management</h1>
            <div class="admin-header-actions">
                <button class="btn btn-outline btn-sm" id="advancedFiltersBtn">
                    <span>🔍</span> Advanced Filters
                </button>
                <button class="btn btn-outline btn-sm" id="exportBtn">
                    <span>📤</span> Export
                </button>
                <button class="btn btn-primary" id="createActivityBtn" data-bs-toggle="modal"
                    data-bs-target="#addActivityModal">
                    <span>➕</span> Create Activity
                </button>
            </div>
        </header>

        <!-- Content Area -->
        <div class="admin-content">
            <!-- Stats Grid - 4 columns -->
            <div class="admin-stats" id="statsContainer">
                <div class="admin-stat-card">
                    <div class="stat-icon">🏆</div>
                    <div class="stat-content">
                        <div class="stat-value" id="totalActivities">0</div>
                        <div class="stat-label">Total Activities</div>
                        <div class="stat-change positive">↑ 12%</div>
                    </div>
                </div>
                <div class="admin-stat-card">
                    <div class="stat-icon">✅</div>
                    <div class="stat-content">
                        <div class="stat-value" id="activeActivities">0</div>
                        <div class="stat-label">Active</div>
                        <div class="stat-change positive">↑ 8%</div>
                    </div>
                </div>
                <div class="admin-stat-card">
                    <div class="stat-icon">📅</div>
                    <div class="stat-content">
                        <div class="stat-value" id="upcomingActivities">0</div>
                        <div class="stat-label">Upcoming</div>
                        <div class="stat-change neutral">→ 0%</div>
                    </div>
                </div>
                <div class="admin-stat-card">
                    <div class="stat-icon">👥</div>
                    <div class="stat-content">
                        <div class="stat-value" id="totalParticipants">0</div>
                        <div class="stat-label">Participants</div>
                        <div class="stat-change positive">↑ 15%</div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="admin-charts">
                <div class="chart-card large">
                    <div class="chart-header">
                        <h3 class="chart-title">Activity Trends</h3>
                        <div class="chart-actions">
                            <select class="chart-filter" id="trendPeriod">
                                <option value="7days">Last 7 Days</option>
                                <option value="30days" selected>Last 30 Days</option>
                                <option value="quarter">This Quarter</option>
                                <option value="year">This Year</option>
                            </select>
                        </div>
                    </div>
                    <div class="chart-body">
                        <canvas id="activityTrendsChart"></canvas>
                    </div>
                </div>
                <div class="chart-card">
                    <div class="chart-header">
                        <h3 class="chart-title">By Category</h3>
                    </div>
                    <div class="chart-body">
                        <canvas id="categoryChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Tabs for Activity Categories -->
            <ul class="nav nav-tabs mb-3" id="activityTabs">
                <li class="nav-item">
                    <a class="nav-link active" data-bs-toggle="tab" href="#allActivities">All Activities</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#sports">Sports</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#arts">Arts & Culture</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#clubs">Clubs</a>
                </li>
            </ul>

            <!-- Data Table -->
            <div class="admin-table-card tab-content">
                <div id="allActivities" class="tab-pane fade show active">
                    <div class="admin-table-header">
                        <span class="table-title">All Activities</span>
                        <div class="table-info">
                            <span id="tableRecordCount">0 records</span>
                        </div>
                    </div>

                    <div class="admin-filters">
                        <input type="text" class="search-input form-control" id="searchActivities"
                            placeholder="Search activities...">
                        <select class="filter-select" id="categoryFilter">
                            <option value="">All Categories</option>
                            <option value="sports">Sports</option>
                            <option value="arts">Arts & Culture</option>
                            <option value="clubs">Clubs</option>
                            <option value="academic">Academic</option>
                        </select>
                        <select class="filter-select" id="statusFilter">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="upcoming">Upcoming</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                        <div class="bulk-actions" id="bulkActions" style="display: none;">
                            <span class="selected-count">0 selected</span>
                            <button class="btn btn-warning btn-sm">Bulk Edit</button>
                            <button class="btn btn-danger btn-sm">Delete Selected</button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="admin-data-table" id="activitiesTable">
                            <thead>
                                <tr>
                                    <th><input type="checkbox" class="select-all" id="selectAll"></th>
                                    <th data-sortable>ID</th>
                                    <th data-sortable>Activity Name</th>
                                    <th data-sortable>Category</th>
                                    <th>Description</th>
                                    <th data-sortable>Start Date</th>
                                    <th data-sortable>End Date</th>
                                    <th data-sortable>Participants</th>
                                    <th data-sortable>Status</th>
                                    <th>Created By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="activitiesTableBody">
                                <!-- Data loaded dynamically -->
                            </tbody>
                        </table>
                    </div>

                    <div class="table-pagination">
                        <div class="pagination-info">
                            Showing <span id="showingFrom">0</span> to <span id="showingTo">0</span> of <span
                                id="totalRecords">0</span>
                        </div>
                        <div class="pagination-controls" id="paginationControls">
                            <!-- Pagination buttons rendered by JS -->
                        </div>
                    </div>
                </div>

                <div id="sports" class="tab-pane fade">
                    <div class="admin-table-header">
                        <span class="table-title">Sports Activities</span>
                    </div>
                    <div class="p-4 text-muted">Sports activities will be filtered here...</div>
                </div>

                <div id="arts" class="tab-pane fade">
                    <div class="admin-table-header">
                        <span class="table-title">Arts & Culture</span>
                    </div>
                    <div class="p-4 text-muted">Arts & culture activities will be filtered here...</div>
                </div>

                <div id="clubs" class="tab-pane fade">
                    <div class="admin-table-header">
                        <span class="table-title">Clubs</span>
                    </div>
                    <div class="p-4 text-muted">Club activities will be filtered here...</div>
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
        // Initialize role-based UI
        RoleBasedUI.applyLayout();

        // Load user info
        const user = AuthContext.getUser();
        if (user) {
            document.getElementById('userName').textContent = user.name || 'Administrator';
            document.getElementById('userRole').textContent = user.role || 'Admin';
            document.getElementById('userAvatar').textContent = (user.name || 'A').charAt(0).toUpperCase();
        }

        // Load activities data
        loadActivities();

        // Initialize charts
        initCharts();

        // Event listeners
        document.getElementById('searchActivities').addEventListener('input', debounce(filterActivities, 300));
        document.getElementById('categoryFilter').addEventListener('change', filterActivities);
        document.getElementById('statusFilter').addEventListener('change', filterActivities);
        document.getElementById('selectAll').addEventListener('change', toggleSelectAll);
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
            showToast('Failed to load activities', 'error');
        }
    }

    function renderActivitiesTable(activities) {
        const tbody = document.getElementById('activitiesTableBody');
        tbody.innerHTML = '';

        activities.forEach(activity => {
            const row = document.createElement('tr');
            row.innerHTML = `
            <td><input type="checkbox" class="row-select" data-id="${activity.id}"></td>
            <td>${activity.id}</td>
            <td><strong>${escapeHtml(activity.name)}</strong></td>
            <td><span class="badge category-${activity.category}">${activity.category}</span></td>
            <td class="text-truncate" style="max-width: 200px;">${escapeHtml(activity.description || '')}</td>
            <td>${formatDate(activity.start_date)}</td>
            <td>${formatDate(activity.end_date)}</td>
            <td>${activity.participant_count || 0}</td>
            <td><span class="status-badge status-${activity.status}">${activity.status}</span></td>
            <td>${escapeHtml(activity.created_by_name || 'System')}</td>
            <td class="admin-row-actions">
                <button class="action-btn view-btn" onclick="viewActivity(${activity.id})" title="View">👁️</button>
                <button class="action-btn edit-btn" onclick="editActivity(${activity.id})" title="Edit">✏️</button>
                <button class="action-btn delete-btn" onclick="deleteActivity(${activity.id})" title="Delete">🗑️</button>
            </td>
        `;
            tbody.appendChild(row);
        });

        document.getElementById('tableRecordCount').textContent = `${activities.length} records`;
        document.getElementById('totalRecords').textContent = activities.length;
        document.getElementById('showingFrom').textContent = activities.length > 0 ? 1 : 0;
        document.getElementById('showingTo').textContent = Math.min(activities.length, 20);

        // Attach row select handlers
        document.querySelectorAll('.row-select').forEach(cb => {
            cb.addEventListener('change', updateBulkActions);
        });
    }

    function updateStats(activities) {
        const total = activities.length;
        const active = activities.filter(a => a.status === 'active').length;
        const upcoming = activities.filter(a => a.status === 'upcoming').length;
        const participants = activities.reduce((sum, a) => sum + (a.participant_count || 0), 0);

        document.getElementById('totalActivities').textContent = total;
        document.getElementById('activeActivities').textContent = active;
        document.getElementById('upcomingActivities').textContent = upcoming;
        document.getElementById('totalParticipants').textContent = participants;
    }

    function initCharts() {
        // Activity Trends Chart
        const trendsCtx = document.getElementById('activityTrendsChart');
        if (trendsCtx) {
            new Chart(trendsCtx, {
                type: 'line',
                data: {
                    labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
                    datasets: [{
                        label: 'Activities',
                        data: [12, 19, 15, 22],
                        borderColor: '#166534',
                        backgroundColor: 'rgba(22, 101, 52, 0.1)',
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        }

        // Category Chart
        const categoryCtx = document.getElementById('categoryChart');
        if (categoryCtx) {
            new Chart(categoryCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Sports', 'Arts', 'Clubs', 'Academic'],
                    datasets: [{
                        data: [35, 25, 20, 20],
                        backgroundColor: ['#166534', '#ca8a04', '#0369a1', '#7c3aed']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
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
            const matchesCategory = !category || row.querySelector('.badge').textContent === category;
            const matchesStatus = !status || row.querySelector('.status-badge').textContent === status;

            row.style.display = matchesSearch && matchesCategory && matchesStatus ? '' : 'none';
        });
    }

    function toggleSelectAll(e) {
        document.querySelectorAll('.row-select').forEach(cb => {
            cb.checked = e.target.checked;
        });
        updateBulkActions();
    }

    function updateBulkActions() {
        const selected = document.querySelectorAll('.row-select:checked').length;
        const bulkActions = document.getElementById('bulkActions');
        bulkActions.style.display = selected > 0 ? 'flex' : 'none';
        bulkActions.querySelector('.selected-count').textContent = `${selected} selected`;
    }

    function viewActivity(id) {
        window.location.href = `/pages/view_activity.php?id=${id}`;
    }

    function editActivity(id) {
        // Open modal with activity data
        console.log('Edit activity:', id);
    }

    async function deleteActivity(id) {
        if (!confirm('Are you sure you want to delete this activity?')) return;

        try {
            const response = await API.activities.delete(id);
            if (response.success) {
                showToast('Activity deleted successfully', 'success');
                loadActivities();
            }
        } catch (error) {
            showToast('Failed to delete activity', 'error');
        }
    }

    // Utility functions
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
        return function (...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => fn.apply(this, args), delay);
        };
    }

    function showToast(message, type = 'info') {
        // Toast notification implementation
        console.log(`[${type.toUpperCase()}] ${message}`);
    }
</script>