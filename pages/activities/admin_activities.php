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
$navBase = $appBase ?? rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '')),'/');
if ($navBase === '.') $navBase = '';
?>

<link rel="stylesheet" href="<?= $navBase ?>/css/school-theme.css?v=<?= asset_version('css/school-theme.css') ?>">
<link rel="stylesheet" href="<?= $navBase ?>/css/roles/admin-theme.css?v=<?= asset_version('css/roles/admin-theme.css') ?>">

<div class="admin-layout">
    <!-- Sidebar Navigation -->
    <aside class="admin-sidebar" id="adminSidebar">
        <div class="logo-section">
            <img src="<?= $appBase ?>/uploads/school_assets/official_school_logo.png" alt="Kingsway Logo" onerror="this.onerror=null;this.src='<?= $appBase ?>/images/official_school_logo.png';">
            <span class="logo-text">Kingsway Preparatory School</span>
        </div>

        <nav class="admin-nav">
            <div class="nav-section">
                <span class="nav-section-title">Main</span>
                <a href="<?= $navBase ?>/home.php?route=dashboard" class="admin-nav-item">
                    <span class="nav-icon">🏠</span>
                    <span class="nav-label">Dashboard</span>
                </a>
                <a href="<?= $navBase ?>/home.php?route=manage_activities" class="admin-nav-item active">
                    <span class="nav-icon">🏆</span>
                    <span class="nav-label">Activities</span>
                </a>
                <a href="<?= $navBase ?>/home.php?route=manage_communications" class="admin-nav-item">
                    <span class="nav-icon">💬</span>
                    <span class="nav-label">Communications</span>
                </a>
            </div>

            <div class="nav-section">
                <span class="nav-section-title">Academics</span>
                <a href="<?= $navBase ?>/home.php?route=all_students" class="admin-nav-item">
                    <span class="nav-icon">👨‍🎓</span>
                    <span class="nav-label">Students</span>
                </a>
                <a href="<?= $navBase ?>/home.php?route=all_teachers" class="admin-nav-item">
                    <span class="nav-icon">👨‍🏫</span>
                    <span class="nav-label">Teachers</span>
                </a>
                <a href="<?= $navBase ?>/home.php?route=class_streams" class="admin-nav-item">
                    <span class="nav-icon">📚</span>
                    <span class="nav-label">Classes</span>
                </a>
            </div>

            <div class="nav-section">
                <span class="nav-section-title">Administration</span>
                <a href="<?= $navBase ?>/home.php?route=manage_finance" class="admin-nav-item">
                    <span class="nav-icon">💰</span>
                    <span class="nav-label">Finance</span>
                </a>
                <a href="<?= $navBase ?>/home.php?route=all_staff" class="admin-nav-item">
                    <span class="nav-icon">👥</span>
                    <span class="nav-label">Staff</span>
                </a>
                <a href="<?= $navBase ?>/home.php?route=account_settings" class="admin-nav-item">
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
                <a href="<?= $navBase ?>/home.php?route=dashboard">Dashboard</a>
                <span>/</span>
                <a href="<?= $navBase ?>/home.php?route=manage_activities">Activities</a>
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
                            <button class="btn btn-danger btn-sm" id="bulkDeleteBtn">Delete Selected</button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="admin-data-table" id="activitiesTable">
                            <thead>
                                <tr>
                                    <th scope="col"><input type="checkbox" class="select-all" id="selectAll"></th>
                                    <th data-sortable>ID</th>
                                    <th data-sortable>Activity Name</th>
                                    <th data-sortable>Category</th>
                                    <th scope="col">Description</th>
                                    <th data-sortable>Start Date</th>
                                    <th data-sortable>End Date</th>
                                    <th data-sortable>Participants</th>
                                    <th data-sortable>Status</th>
                                    <th scope="col">Created By</th>
                                    <th scope="col">Actions</th>
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

<?php asset_script($appBase, 'js/components/RoleBasedUI.js'); ?>
<?php asset_script($appBase, 'js/pages/activities.js'); ?>
