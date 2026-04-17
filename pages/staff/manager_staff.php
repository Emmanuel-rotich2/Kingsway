<?php
/**
 * Staff - Manager Layout
 * For Deputy Heads, HODs
 *
 * Features:
 * - 3 stat cards
 * - 1 chart (department view)
 * - Data table with department filter
 * - Actions: View, limited Edit (no Delete)
 */
/* PARTIAL — no DOCTYPE/html/head/body. Injected into app shell via fetch. */
?>

<!-- Stats Row - 3 cards -->
<div class="manager-stats-grid">
    <div class="stat-card">
        <div class="stat-icon bg-primary">👥</div>
        <div class="stat-content">
            <span class="stat-value" id="totalStaff">0</span>
            <span class="stat-label">Department Staff</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-success">✅</div>
        <div class="stat-content">
            <span class="stat-value" id="activeStaff">0</span>
            <span class="stat-label">Active</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-warning">🏖️</div>
        <div class="stat-content">
            <span class="stat-value" id="onLeave">0</span>
            <span class="stat-label">On Leave</span>
        </div>
    </div>
</div>

<!-- Chart -->
<div class="manager-charts-grid">
    <div class="chart-card">
        <h3>📊 Role Distribution</h3>
        <div class="chart-container">
            <canvas id="roleChart"></canvas>
        </div>
    </div>
</div>

<!-- Filters Section -->
<div class="manager-filters">
    <input type="text" class="form-input" id="staffSearch" placeholder="Search by name or ID...">
    <select class="form-select" id="roleTypeFilter">
        <option value="">All Types</option>
        <option value="teaching">Teaching</option>
        <option value="non-teaching">Non-Teaching</option>
    </select>
    <select class="form-select" id="statusFilter">
        <option value="">All Status</option>
        <option value="active">Active</option>
        <option value="on_leave">On Leave</option>
    </select>
    <button class="btn btn-outline-sm" onclick="clearFilters()">Clear</button>
    <button class="btn btn-outline" onclick="exportStaff()">📥 Export</button>
</div>

<!-- Data Table -->
<div class="manager-table-container">
    <table class="manager-data-table" id="staffTable">
        <thead>
            <tr>
                <th>Photo</th>
                <th>Staff ID</th>
                <th>Name</th>
                <th>Role</th>
                <th>Phone</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="staffTableBody">
            <tr>
                <td colspan="7" class="loading-row">Loading staff...</td>
            </tr>
        </tbody>
    </table>
</div>

<!-- Pagination -->
<div class="manager-pagination">
    <span class="pagination-info" id="paginationInfo">Showing 0 of 0</span>
    <div class="pagination-controls" id="paginationControls"></div>
</div>

<!-- View Staff Modal -->
<div class="modal" id="staffModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Staff Details</h3>
                <button class="btn-close" onclick="closeModal('staffModal')">×</button>
            </div>
            <div class="modal-body" id="staffModalBody">
                <!-- Dynamic content -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeModal('staffModal')">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="<?= $appBase ?>js/pages/all_staff.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof StaffController !== 'undefined') {
            StaffController.init({ view: 'manager' });
        }
    });

    function closeModal(modalId) {
        document.getElementById(modalId).classList.remove('show');
    }

    function exportStaff() {
        if (typeof StaffController !== 'undefined') {
            StaffController.exportReport();
        }
    }

    function clearFilters() {
        document.querySelectorAll('.manager-filters input, .manager-filters select').forEach(el => {
            el.value = '';
        });
        if (typeof StaffController !== 'undefined') {
            StaffController.loadData();
        }
    }
</script>
