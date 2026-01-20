<?php
/**
 * Staff - Admin Layout
 * Full featured for System Administrator, Director, Headteacher
 * 
 * Features:
 * - Full sidebar (280px)
 * - 4 stat cards with trends
 * - 2 charts (department distribution, role breakdown)
 * - Full data table with all columns
 * - All actions: View, Edit, Delete, Deactivate, Export
 * - Bulk operations
 */
?>

<link rel="stylesheet" href="/css/school-theme.css">
<link rel="stylesheet" href="/css/roles/admin-theme.css">

<div class="admin-layout">
    <!-- Full Sidebar -->
    <aside class="admin-sidebar" id="adminSidebar">
        <div class="logo-section">
            <img src="/images/logo.png" alt="Kingsway Academy">
            <h3>Kingsway Academy</h3>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section">
                <span class="nav-section-title">Main</span>
                <a href="/pages/dashboard.php" class="nav-item">🏠 Dashboard</a>
                <a href="/pages/all_staff.php" class="nav-item active">👥 Staff</a>
                <a href="/pages/all_teachers.php" class="nav-item">👩‍🏫 Teachers</a>
            </div>
            <div class="nav-section">
                <span class="nav-section-title">HR Management</span>
                <a href="/pages/manage_staff.php" class="nav-item">➕ Add Staff</a>
                <a href="/pages/staff_attendance.php" class="nav-item">📋 Attendance</a>
                <a href="/pages/payroll.php" class="nav-item">💵 Payroll</a>
            </div>
            <div class="nav-section">
                <span class="nav-section-title">Reports</span>
                <a href="/pages/staff_reports.php" class="nav-item">📊 Staff Reports</a>
            </div>
        </nav>

        <div class="user-info" id="userInfo">
            <img src="/images/default-avatar.png" alt="User" class="user-avatar">
            <div class="user-details">
                <span class="user-name" id="userName"></span>
                <span class="user-role" id="userRole"></span>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="admin-main">
        <!-- Header -->
        <header class="admin-header">
            <div class="header-left">
                <h1 class="page-title">👥 Staff Management</h1>
                <p class="page-subtitle">Manage all teaching and non-teaching staff</p>
            </div>
            <div class="header-actions">
                <button class="btn btn-outline" onclick="exportStaff()">📥 Export</button>
                <a href="/pages/manage_staff.php" class="btn btn-primary">➕ Add Staff</a>
            </div>
        </header>

        <!-- Stats Row - 4 cards -->
        <div class="admin-stats-grid">
            <div class="stat-card">
                <div class="stat-icon bg-primary">👥</div>
                <div class="stat-content">
                    <span class="stat-value" id="totalStaff">0</span>
                    <span class="stat-label">Total Staff</span>
                    <span class="stat-trend up" id="staffTrend">+0%</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon bg-success">👩‍🏫</div>
                <div class="stat-content">
                    <span class="stat-value" id="teachingStaff">0</span>
                    <span class="stat-label">Teaching Staff</span>
                    <span class="stat-trend" id="teachingTrend">-</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon bg-info">🔧</div>
                <div class="stat-content">
                    <span class="stat-value" id="nonTeachingStaff">0</span>
                    <span class="stat-label">Non-Teaching</span>
                    <span class="stat-trend" id="nonTeachingTrend">-</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon bg-warning">🏖️</div>
                <div class="stat-content">
                    <span class="stat-value" id="onLeave">0</span>
                    <span class="stat-label">On Leave</span>
                    <span class="stat-trend" id="leaveTrend">-</span>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="admin-charts-grid">
            <div class="chart-card">
                <h3>📊 Department Distribution</h3>
                <div class="chart-container">
                    <canvas id="departmentChart"></canvas>
                </div>
            </div>
            <div class="chart-card">
                <h3>🥧 Role Breakdown</h3>
                <div class="chart-container">
                    <canvas id="roleChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="admin-filters">
            <div class="filter-group">
                <input type="text" class="form-input" id="staffSearch" placeholder="Search by name or ID...">
            </div>
            <div class="filter-group">
                <select class="form-select" id="departmentFilter">
                    <option value="">All Departments</option>
                </select>
            </div>
            <div class="filter-group">
                <select class="form-select" id="roleTypeFilter">
                    <option value="">All Types</option>
                    <option value="teaching">Teaching</option>
                    <option value="non-teaching">Non-Teaching</option>
                </select>
            </div>
            <div class="filter-group">
                <select class="form-select" id="statusFilter">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="on_leave">On Leave</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <button class="btn btn-outline-sm" onclick="clearFilters()">Clear</button>
        </div>

        <!-- Bulk Actions -->
        <div class="admin-bulk-actions" id="bulkActions" style="display: none;">
            <span class="selected-count"><span id="selectedCount">0</span> selected</span>
            <button class="btn btn-outline-sm" onclick="bulkExport()">📥 Export Selected</button>
            <button class="btn btn-outline-sm" onclick="bulkEmail()">📧 Send Email</button>
            <button class="btn btn-warning-sm" onclick="bulkDeactivate()">⏸️ Deactivate Selected</button>
        </div>

        <!-- Data Table -->
        <div class="admin-table-container">
            <table class="admin-data-table" id="staffTable">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="selectAll" onchange="toggleSelectAll()"></th>
                        <th>Photo</th>
                        <th>Staff ID</th>
                        <th>Name</th>
                        <th>Role</th>
                        <th>Department</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="staffTableBody">
                    <tr>
                        <td colspan="10" class="loading-row">Loading staff...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="admin-pagination">
            <span class="pagination-info" id="paginationInfo">Showing 0 of 0</span>
            <div class="pagination-controls" id="paginationControls"></div>
        </div>
    </main>
</div>

<!-- View/Edit Staff Modal -->
<div class="modal" id="staffModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Staff Details</h3>
                <button class="btn-close" onclick="closeModal('staffModal')">×</button>
            </div>
            <div class="modal-body" id="staffModalBody">
                <!-- Dynamic content -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeModal('staffModal')">Close</button>
                <button class="btn btn-primary" id="editStaffBtn" onclick="editStaff()">Edit</button>
            </div>
        </div>
    </div>
</div>

<script src="/js/components/RoleBasedUI.js"></script>
<script src="/js/pages/all_staff.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        RoleBasedUI.applyLayout();
        if (typeof StaffController !== 'undefined') {
            StaffController.init({ view: 'admin' });
        }
    });

    function closeModal(modalId) {
        document.getElementById(modalId).classList.remove('show');
    }

    function toggleSelectAll() {
        const checked = document.getElementById('selectAll').checked;
        document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = checked);
        updateBulkActions();
    }

    function updateBulkActions() {
        const selected = document.querySelectorAll('.row-checkbox:checked').length;
        document.getElementById('selectedCount').textContent = selected;
        document.getElementById('bulkActions').style.display = selected > 0 ? 'flex' : 'none';
    }

    function exportStaff() {
        if (typeof StaffController !== 'undefined') {
            StaffController.exportReport();
        }
    }

    function clearFilters() {
        document.querySelectorAll('.admin-filters input, .admin-filters select').forEach(el => {
            el.value = '';
        });
        if (typeof StaffController !== 'undefined') {
            StaffController.loadData();
        }
    }
</script>