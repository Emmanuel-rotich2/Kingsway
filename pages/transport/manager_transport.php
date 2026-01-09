<?php
/**
 * Transport - Manager Layout
 * For Transport Coordinator, HOD Operations
 * 
 * Features:
 * - Compact sidebar
 * - 3 stat cards
 * - Route overview table
 * - Can manage routes and assign students
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
            <a href="/pages/dashboard.php" class="manager-nav-item" data-label="Dashboard">🏠</a>
            <a href="/pages/all_students.php" class="manager-nav-item" data-label="Students">👨‍🎓</a>
            <a href="/pages/manage_transport.php" class="manager-nav-item active" data-label="Transport">🚌</a>
        </nav>

        <div class="user-section" id="userSection">
            <span class="user-initial" id="userInitial">M</span>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="manager-main">
        <!-- Header -->
        <header class="manager-header">
            <div class="header-left">
                <h1 class="page-title">🚌 Transport</h1>
            </div>
            <div class="header-actions">
                <button class="btn btn-primary btn-sm" onclick="showAddRouteModal()">➕ Add Route</button>
            </div>
        </header>

        <!-- Stats - 3 columns -->
        <div class="manager-stats-grid">
            <div class="manager-stat-card">
                <div class="stat-icon">🛣️</div>
                <div class="stat-info">
                    <span class="stat-value" id="totalRoutes">0</span>
                    <span class="stat-label">Routes</span>
                </div>
            </div>
            <div class="manager-stat-card">
                <div class="stat-icon">🚌</div>
                <div class="stat-info">
                    <span class="stat-value" id="totalVehicles">0</span>
                    <span class="stat-label">Vehicles</span>
                </div>
            </div>
            <div class="manager-stat-card">
                <div class="stat-icon">👨‍🎓</div>
                <div class="stat-info">
                    <span class="stat-value" id="studentsCount">0</span>
                    <span class="stat-label">Students</span>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="manager-filters">
            <select class="filter-select" id="filterStatus">
                <option value="">All Status</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
            <input type="text" class="filter-search" id="searchRoute" placeholder="🔍 Search...">
        </div>

        <!-- Table - 6 columns -->
        <div class="manager-table-card">
            <table class="manager-data-table" id="routesTable">
                <thead>
                    <tr>
                        <th>Route</th>
                        <th>Vehicle</th>
                        <th>Driver</th>
                        <th>Students</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="routesTableBody">
                    <!-- Data loaded dynamically -->
                </tbody>
            </table>

            <div class="table-footer">
                <span class="page-info">Showing <span id="showingCount">0</span> routes</span>
            </div>
        </div>
    </main>
</div>

<!-- Add Route Modal -->
<div class="modal fade" id="routeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Add Route</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="routeForm">
                    <div class="mb-3">
                        <label class="form-label">Route Name</label>
                        <input type="text" class="form-control" id="routeName" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Vehicle</label>
                        <select class="form-select" id="vehicleId"></select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Driver</label>
                        <select class="form-select" id="driverId"></select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" id="saveRouteBtn">Save</button>
            </div>
        </div>
    </div>
</div>

<script src="/js/components/RoleBasedUI.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        RoleBasedUI.applyLayout();

        const user = AuthContext.getUser();
        if (user) {
            document.getElementById('userInitial').textContent = (user.name || 'M').charAt(0).toUpperCase();
        }

        loadRoutes();
        loadStats();
        initEventListeners();
    });

    async function loadRoutes() {
        try {
            const response = await API.transport.getRoutes();
            if (response.success) {
                renderRoutesTable(response.data);
            }
        } catch (error) {
            console.error('Error loading routes:', error);
        }
    }

    async function loadStats() {
        try {
            const response = await API.transport.getStats();
            if (response.success) {
                document.getElementById('totalRoutes').textContent = response.data.routes || 0;
                document.getElementById('totalVehicles').textContent = response.data.vehicles || 0;
                document.getElementById('studentsCount').textContent = response.data.students || 0;
            }
        } catch (error) {
            console.error('Error loading stats:', error);
        }
    }

    function initEventListeners() {
        document.getElementById('filterStatus').addEventListener('change', applyFilters);
        document.getElementById('searchRoute').addEventListener('input', debounce(applyFilters, 300));
        document.getElementById('saveRouteBtn').addEventListener('click', saveRoute);
    }

    function applyFilters() {
        const search = document.getElementById('searchRoute').value.toLowerCase();
        document.querySelectorAll('#routesTableBody tr').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(search) ? '' : 'none';
        });
    }

    function renderRoutesTable(routes) {
        const tbody = document.getElementById('routesTableBody');
        tbody.innerHTML = '';

        if (routes.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center p-4">No routes found</td></tr>';
            return;
        }

        routes.forEach(r => {
            const row = document.createElement('tr');
            row.innerHTML = `
            <td><strong>${escapeHtml(r.name)}</strong></td>
            <td>${escapeHtml(r.vehicle_reg || '-')}</td>
            <td>${escapeHtml(r.driver_name || '-')}</td>
            <td>${r.student_count || 0}</td>
            <td><span class="status-badge status-${r.status}">${r.status}</span></td>
            <td class="manager-row-actions">
                <button class="action-btn" onclick="viewRoute(${r.id})">👁</button>
                <button class="action-btn" onclick="editRoute(${r.id})">✏️</button>
            </td>
        `;
            tbody.appendChild(row);
        });

        document.getElementById('showingCount').textContent = routes.length;
    }

    function showAddRouteModal() {
        document.getElementById('routeForm').reset();
        new bootstrap.Modal(document.getElementById('routeModal')).show();
    }

    async function saveRoute() { console.log('Save route'); }
    function viewRoute(id) { console.log('View:', id); }
    function editRoute(id) { console.log('Edit:', id); }

    function escapeHtml(s) { return s ? s.replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m]) : ''; }
    function debounce(fn, d) { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), d); }; }
</script>