<?php
/**
 * Students - Accountant Layout
 * Focused on fee tracking and student billing history.
 */
?>

<link rel="stylesheet" href="/Kingsway/css/school-theme.css">
<link rel="stylesheet" href="/Kingsway/css/roles/manager-theme.css">

<div class="manager-layout" data-user-role="accountant">
    <!-- Compact Sidebar -->
    <aside class="manager-sidebar" id="managerSidebar">
        <div class="logo-section">
            <img src="/images/logo.png" alt="KA">
        </div>

        <nav class="manager-nav">
            <a href="/pages/dashboard.php" class="manager-nav-item" data-label="Dashboard">🏠</a>
            <a href="/pages/all_students.php" class="manager-nav-item active" data-label="Students">👨‍🎓</a>
            <a href="/pages/manage_finance.php" class="manager-nav-item" data-label="Finance">💳</a>
            <a href="/pages/fee_invoices.php" class="manager-nav-item" data-label="Invoices">🧾</a>
        </nav>

        <div class="user-section" id="userSection">
            <span class="user-initial" id="userInitial">A</span>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="manager-main">
        <!-- Header -->
        <header class="manager-header">
            <div class="header-left">
                <h1 class="page-title">💳 Student Fee Tracker</h1>
                <p class="page-subtitle">View all students and track fee payments by year and term</p>
            </div>
            <div class="header-actions">
                <button class="btn btn-outline btn-sm" id="exportStudentsBtn">📥 Export</button>
            </div>
        </header>

        <!-- Filters -->
        <div class="manager-filters">
            <select class="filter-select" id="filterClass">
                <option value="">All Classes</option>
            </select>
            <select class="filter-select" id="filterStream">
                <option value="">All Streams</option>
            </select>
            <select class="filter-select" id="filterStatus">
                <option value="">All Status</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="graduated">Graduated</option>
                <option value="transferred">Transferred</option>
            </select>
            <input type="text" class="filter-search search-input" id="searchStudent" placeholder="Search name or admission no">
        </div>

        <!-- Table -->
        <div class="manager-table-card">
            <table class="manager-data-table" id="studentsTable">
                <thead>
                    <tr>
                        <th>Adm No</th>
                        <th>Name</th>
                        <th>Class</th>
                        <th>Stream</th>
                        <th>Gender</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="studentsTableBody">
                    <tr>
                        <td colspan="8" class="text-center">Loading students...</td>
                    </tr>
                </tbody>
            </table>

            <div class="table-footer">
                <span class="page-info">Showing <span id="showingCount">0</span> of <span id="totalCount">0</span></span>
                <div class="pagination" id="pagination"></div>
            </div>
        </div>
    </main>
</div>

<!-- Fee Track Modal -->
<div class="modal fade" id="feeTrackModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="feeTrackTitle">Fee Track</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="feeTrackContent" class="text-muted">Select a student to view fee history.</div>
            </div>
        </div>
    </div>
</div>

<script src="/Kingsway/js/pages/all_students_accountant.js"></script>
