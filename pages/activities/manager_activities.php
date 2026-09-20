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
$navBase = $appBase ?? rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '')),'/');
if ($navBase === '.') $navBase = '';
?>

<link rel="stylesheet" href="<?= $navBase ?>/css/school-theme.css?v=<?= asset_version('css/school-theme.css') ?>">
<link rel="stylesheet" href="<?= $navBase ?>/css/roles/manager-theme.css?v=<?= asset_version('css/roles/manager-theme.css') ?>">

<div class="manager-layout">
    <!-- Compact Sidebar -->
    <aside class="manager-sidebar" id="managerSidebar">
        <div class="logo-section">
            <img src="<?= $appBase ?>/uploads/school_assets/official_school_logo.png" alt="Kingsway Logo" onerror="this.onerror=null;this.src='<?= $appBase ?>/images/official_school_logo.png';">
        </div>

        <nav class="manager-nav">
            <a href="<?= $navBase ?>/home.php?route=dashboard" class="manager-nav-item" data-tooltip="Dashboard">
                <span class="nav-icon">🏠</span>
                <span class="nav-label">Dashboard</span>
            </a>
            <a href="<?= $navBase ?>/home.php?route=manage_activities" class="manager-nav-item active" data-tooltip="Activities">
                <span class="nav-icon">🏆</span>
                <span class="nav-label">Activities</span>
            </a>
            <a href="<?= $navBase ?>/home.php?route=manage_communications" class="manager-nav-item" data-tooltip="Communications">
                <span class="nav-icon">💬</span>
                <span class="nav-label">Communications</span>
            </a>
            <a href="<?= $navBase ?>/home.php?route=all_students" class="manager-nav-item" data-tooltip="Students">
                <span class="nav-icon">👨‍🎓</span>
                <span class="nav-label">Students</span>
            </a>
            <a href="<?= $navBase ?>/home.php?route=class_streams" class="manager-nav-item" data-tooltip="Classes">
                <span class="nav-icon">📚</span>
                <span class="nav-label">Classes</span>
            </a>
            <a href="<?= $navBase ?>/home.php?route=activity_reports" class="manager-nav-item" data-tooltip="Reports">
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
                                <th scope="col">ID</th>
                                <th scope="col">Activity Name</th>
                                <th scope="col">Category</th>
                                <th scope="col">Date</th>
                                <th scope="col">Participants</th>
                                <th scope="col">Status</th>
                                <th scope="col">Actions</th>
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

<?php asset_script($appBase, 'js/components/RoleBasedUI.js'); ?>
<?php asset_script($appBase, 'js/pages/activities.js'); ?>
