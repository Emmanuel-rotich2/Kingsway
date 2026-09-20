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
$navBase = $appBase ?? rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '')),'/');
if ($navBase === '.') $navBase = '';
?>

<link rel="stylesheet" href="<?= $navBase ?>/css/school-theme.css?v=<?= asset_version('css/school-theme.css') ?>">
<link rel="stylesheet" href="<?= $navBase ?>/css/roles/operator-theme.css?v=<?= asset_version('css/roles/operator-theme.css') ?>">

<div class="operator-layout">
    <!-- Mini Sidebar -->
    <aside class="operator-sidebar" id="operatorSidebar">
        <div class="logo-section">
            <img src="<?= $appBase ?>/uploads/school_assets/official_school_logo.png" alt="Kingsway Logo" onerror="this.onerror=null;this.src='<?= $appBase ?>/images/official_school_logo.png';">
        </div>

        <nav class="operator-nav">
            <a href="<?= $navBase ?>/home.php?route=dashboard" class="operator-nav-item" data-tooltip="Dashboard">🏠</a>
            <a href="<?= $navBase ?>/home.php?route=manage_activities" class="operator-nav-item active" data-tooltip="Activities">🏆</a>
            <a href="<?= $navBase ?>/home.php?route=manage_communications" class="operator-nav-item" data-tooltip="Messages">💬</a>
            <a href="<?= $navBase ?>/home.php?route=all_students" class="operator-nav-item" data-tooltip="Students">👨‍🎓</a>
            <a href="<?= $navBase ?>/home.php?route=my_classes_taught" class="operator-nav-item" data-tooltip="My Classes">📚</a>
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
                            <th scope="col">Activity</th>
                            <th scope="col">Category</th>
                            <th scope="col">Date</th>
                            <th scope="col">Action</th>
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

<?php asset_script($appBase, 'js/components/RoleBasedUI.js'); ?>
<?php asset_script($appBase, 'js/pages/activities.js'); ?>
