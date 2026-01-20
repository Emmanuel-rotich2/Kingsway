<?php
/**
 * Transport - Admin Layout
 * Full featured for System Admin, Director, Transport Manager
 * 
 * Features:
 * - Full sidebar
 * - 4 stat cards (routes, vehicles, students, drivers)
 * - Route map and charts
 * - Full management of routes, vehicles, drivers
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
                <a href="/pages/all_students.php" class="nav-item">👨‍🎓 Students</a>
            </div>
            <div class="nav-section">
                <span class="nav-section-title">Transport</span>
                <a href="/pages/manage_transport.php" class="nav-item active">🚌 Transport</a>
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
                <h1 class="page-title">🚌 Transport Management</h1>
                <p class="page-subtitle">Manage routes, vehicles, and student transport</p>
            </div>
            <div class="header-actions">
                <button class="btn btn-outline" onclick="exportData()">📥 Export</button>
                <button class="btn btn-primary" onclick="showAddRouteModal()">➕ Add Route</button>
            </div>
        </header>

        <!-- Stats Row - 4 cards -->
        <div class="admin-stats-grid">
            <div class="stat-card">
                <div class="stat-icon bg-primary">🛣️</div>
                <div class="stat-content">
                    <span class="stat-value" id="totalRoutes">0</span>
                    <span class="stat-label">Routes</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon bg-success">🚌</div>
                <div class="stat-content">
                    <span class="stat-value" id="totalVehicles">0</span>
                    <span class="stat-label">Vehicles</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon bg-info">👨‍🎓</div>
                <div class="stat-content">
                    <span class="stat-value" id="studentsUsingTransport">0</span>
                    <span class="stat-label">Students</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon bg-warning">👨‍✈️</div>
                <div class="stat-content">
                    <span class="stat-value" id="totalDrivers">0</span>
                    <span class="stat-label">Drivers</span>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="admin-charts-row">
            <div class="chart-card chart-wide">
                <div class="chart-header">
                    <h3>Students by Route</h3>
                </div>
                <canvas id="routeChart" height="200"></canvas>
            </div>
            <div class="chart-card chart-narrow">
                <div class="chart-header">
                    <h3>Vehicle Status</h3>
                </div>
                <canvas id="vehicleChart" height="200"></canvas>
            </div>
        </div>

        <!-- Tabs -->
        <div class="admin-tabs">
            <button class="tab-btn active" data-tab="routes">Routes</button>
            <button class="tab-btn" data-tab="vehicles">Vehicles</button>
            <button class="tab-btn" data-tab="drivers">Drivers</button>
            <button class="tab-btn" data-tab="students">Student Assignments</button>
        </div>

        <!-- Filters -->
        <div class="admin-filters">
            <div class="filter-row">
                <select class="filter-select" id="filterStatus">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
                <input type="text" class="filter-search" id="searchTransport" placeholder="🔍 Search...">
            </div>
        </div>

        <!-- Routes Table -->
        <div class="admin-table-card" id="routesTab">
            <table class="admin-data-table" id="routesTable">
                <thead>
                    <tr>
                        <th>Route Name</th>
                        <th>Vehicle</th>
                        <th>Driver</th>
                        <th>Stops</th>
                        <th>Students</th>
                        <th>AM Pickup</th>
                        <th>PM Dropoff</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="routesTableBody">
                    <!-- Data loaded dynamically -->
                </tbody>
            </table>
        </div>

        <!-- Vehicles Table (hidden by default) -->
        <div class="admin-table-card" id="vehiclesTab" style="display:none;">
            <table class="admin-data-table">
                <thead>
                    <tr>
                        <th>Reg No</th>
                        <th>Make/Model</th>
                        <th>Capacity</th>
                        <th>Year</th>
                        <th>Insurance Expiry</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="vehiclesTableBody"></tbody>
            </table>
        </div>

        <!-- Drivers Table (hidden by default) -->
        <div class="admin-table-card" id="driversTab" style="display:none;">
            <table class="admin-data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>License No</th>
                        <th>License Expiry</th>
                        <th>Assigned Vehicle</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="driversTableBody"></tbody>
            </table>
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
                        <label class="form-label">Route Name *</label>
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
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">AM Pickup Time</label>
                            <input type="time" class="form-control" id="amPickup" value="06:30">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">PM Dropoff Time</label>
                            <input type="time" class="form-control" id="pmDropoff" value="16:30">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Stops (one per line)</label>
                        <textarea class="form-control" id="stops" rows="3"
                            placeholder="Stop 1&#10;Stop 2&#10;Stop 3"></textarea>
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
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        RoleBasedUI.applyLayout();

        const user = AuthContext.getUser();
        if (user) {
            document.getElementById('userName').textContent = user.name;
            document.getElementById('userRole').textContent = user.role;
        }

        loadRoutes();
        loadStats();
        initCharts();
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
                document.getElementById('studentsUsingTransport').textContent = response.data.students || 0;
                document.getElementById('totalDrivers').textContent = response.data.drivers || 0;
            }
        } catch (error) {
            console.error('Error loading stats:', error);
        }
    }

    function initCharts() {
        new Chart(document.getElementById('routeChart'), {
            type: 'bar',
            data: { labels: [], datasets: [{ data: [], backgroundColor: 'var(--green-500)' }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
        });

        new Chart(document.getElementById('vehicleChart'), {
            type: 'doughnut',
            data: { labels: ['Active', 'Maintenance', 'Inactive'], datasets: [{ data: [0, 0, 0], backgroundColor: ['#22c55e', '#f59e0b', '#ef4444'] }] },
            options: { responsive: true, maintainAspectRatio: false }
        });
    }

    function initEventListeners() {
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');

                // Show/hide appropriate tab
                ['routes', 'vehicles', 'drivers', 'students'].forEach(tab => {
                    const el = document.getElementById(tab + 'Tab');
                    if (el) el.style.display = this.dataset.tab === tab ? 'block' : 'none';
                });
            });
        });

        document.getElementById('searchTransport').addEventListener('input', debounce(filterTable, 300));
        document.getElementById('saveRouteBtn').addEventListener('click', saveRoute);
    }

    function renderRoutesTable(routes) {
        const tbody = document.getElementById('routesTableBody');
        tbody.innerHTML = '';

        if (routes.length === 0) {
            tbody.innerHTML = '<tr><td colspan="9" class="text-center p-4">No routes found</td></tr>';
            return;
        }

        routes.forEach(r => {
            const row = document.createElement('tr');
            row.innerHTML = `
            <td><strong>${escapeHtml(r.name)}</strong></td>
            <td>${escapeHtml(r.vehicle_reg || '-')}</td>
            <td>${escapeHtml(r.driver_name || '-')}</td>
            <td>${r.stop_count || 0}</td>
            <td>${r.student_count || 0}</td>
            <td>${r.am_pickup || '-'}</td>
            <td>${r.pm_dropoff || '-'}</td>
            <td><span class="status-badge status-${r.status}">${r.status}</span></td>
            <td class="admin-row-actions">
                <button class="action-btn" onclick="viewRoute(${r.id})">👁</button>
                <button class="action-btn" onclick="editRoute(${r.id})">✏️</button>
                <button class="action-btn" onclick="deleteRoute(${r.id})">🗑️</button>
            </td>
        `;
            tbody.appendChild(row);
        });
    }

    function showAddRouteModal() {
        document.getElementById('routeForm').reset();
        new bootstrap.Modal(document.getElementById('routeModal')).show();
    }

    function filterTable() {
        const search = document.getElementById('searchTransport').value.toLowerCase();
        document.querySelectorAll('#routesTableBody tr').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(search) ? '' : 'none';
        });
    }

    async function saveRoute() { console.log('Save route'); }
    function viewRoute(id) { console.log('View route:', id); }
    function editRoute(id) { console.log('Edit route:', id); }
    function deleteRoute(id) { console.log('Delete route:', id); }
    function exportData() { console.log('Export'); }

    function escapeHtml(s) { return s ? s.replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m]) : ''; }
    function debounce(fn, d) { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), d); }; }
</script>