<?php /** Active Users - Monitor currently active user sessions */ ?>
<div>
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1"><i class="fas fa-users me-2"></i>Active Users</h4>
                    <p class="text-muted mb-0">Monitor users currently active in the system</p>
                </div>
                <div>
                    <span id="lastUpdated" class="text-muted me-3"></span>
                    <button class="btn btn-outline-primary btn-sm me-2" onclick="ActiveUsersController.loadData()"><i class="fas fa-sync-alt me-1"></i> Refresh</button>
                    <button class="btn btn-outline-success btn-sm" onclick="ActiveUsersController.exportCSV()"><i class="fas fa-download me-1"></i> Export</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-primary bg-opacity-10 p-3 me-3">
                            <i class="fas fa-users text-primary fa-lg"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Total Active</h6>
                            <h4 class="mb-0" id="statTotalActive">0</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-success bg-opacity-10 p-3 me-3">
                            <i class="fas fa-user-shield text-success fa-lg"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Admins Online</h6>
                            <h4 class="mb-0" id="statAdmins">0</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-info bg-opacity-10 p-3 me-3">
                            <i class="fas fa-chalkboard-teacher text-info fa-lg"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Teachers Online</h6>
                            <h4 class="mb-0" id="statTeachers">0</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-warning bg-opacity-10 p-3 me-3">
                            <i class="fas fa-user-graduate text-warning fa-lg"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Others Online</h6>
                            <h4 class="mb-0" id="statOthers">0</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Role Distribution Chart + Filters -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white"><h6 class="mb-0"><i class="fas fa-chart-pie me-2"></i>By Role</h6></div>
                <div class="card-body" style="height:250px;">
                    <canvas id="roleDistChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Users</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-2">
                            <input type="text" id="userSearch" class="form-control form-control-sm" placeholder="Search by name or email..."
                                   oninput="ActiveUsersController.applyFilters()">
                        </div>
                        <div class="col-md-4 mb-2">
                            <select id="roleFilter" class="form-select form-select-sm" onchange="ActiveUsersController.applyFilters()">
                                <option value="">All Roles</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-2">
                            <select id="statusFilter" class="form-select form-select-sm" onchange="ActiveUsersController.applyFilters()">
                                <option value="">All Status</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Users Table -->
    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="fas fa-users me-2"></i>Active User Sessions (<span id="filteredCount">0</span>)</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>User</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Last Login</th>
                        </tr>
                    </thead>
                    <tbody id="usersTableBody">
                        <tr><td colspan="6" class="text-center text-muted py-4">Loading active users...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
const ActiveUsersController = (() => {
    let allSessions = [];
    let filteredSessions = [];
    let chart = null;
    let refreshTimer = null;

    function esc(str) {
        if (!str) return '';
        return String(str).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#39;"}[m]));
    }

    async function loadData() {
        try {
            const response = await window.API.dashboard.getActiveSessions();
            const data = response?.data || response || {};
            allSessions = data.sessions || [];
            const summary = data.summary || {};

            // Stats
            document.getElementById('statTotalActive').textContent = summary.total_active_users || allSessions.length;

            // Count roles
            const byRole = summary.by_role || {};
            const adminCount = (byRole['System Administrator'] || 0) + (byRole['School Administrator'] || 0) + (byRole['Director'] || 0) + (byRole['Director/Owner'] || 0);
            const teacherCount = Object.entries(byRole)
                .filter(([k]) => k.toLowerCase().includes('teacher') || k.toLowerCase().includes('hod'))
                .reduce((sum, [, v]) => sum + v, 0);
            const othersCount = (summary.total_active_users || allSessions.length) - adminCount - teacherCount;

            document.getElementById('statAdmins').textContent = adminCount;
            document.getElementById('statTeachers').textContent = teacherCount;
            document.getElementById('statOthers').textContent = Math.max(0, othersCount);

            // Populate role filter
            const roleFilter = document.getElementById('roleFilter');
            const existingVal = roleFilter.value;
            roleFilter.innerHTML = '<option value="">All Roles</option>';
            const roles = [...new Set(allSessions.map(s => s.role_name).filter(Boolean))].sort();
            roles.forEach(r => {
                roleFilter.innerHTML += `<option value="${esc(r)}">${esc(r)}</option>`;
            });
            roleFilter.value = existingVal;

            // Chart
            renderChart(byRole);

            // Apply filters
            applyFilters();

            // Last updated
            document.getElementById('lastUpdated').textContent = 'Updated: ' + new Date().toLocaleTimeString();
        } catch (error) {
            console.error('Error loading active sessions:', error);
            document.getElementById('usersTableBody').innerHTML =
                '<tr><td colspan="6" class="text-center text-danger py-4">Failed to load active users</td></tr>';
        }
    }

    function applyFilters() {
        const search = (document.getElementById('userSearch')?.value || '').toLowerCase();
        const role = document.getElementById('roleFilter')?.value || '';
        const status = document.getElementById('statusFilter')?.value || '';

        filteredSessions = allSessions.filter(s => {
            const name = `${s.first_name || ''} ${s.last_name || ''} ${s.email || ''}`.toLowerCase();
            if (search && !name.includes(search)) return false;
            if (role && (s.role_name || '') !== role) return false;
            if (status && (s.status || '').toLowerCase() !== status.toLowerCase()) return false;
            return true;
        });

        document.getElementById('filteredCount').textContent = filteredSessions.length;
        renderTable();
    }

    function renderTable() {
        const tbody = document.getElementById('usersTableBody');
        if (!filteredSessions.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No active users matching filters</td></tr>';
            return;
        }

        tbody.innerHTML = filteredSessions.map((s, i) => {
            const name = `${s.first_name || ''} ${s.last_name || ''}`.trim() || 'Unknown';
            const statusClass = (s.status || '').toLowerCase() === 'active' ? 'bg-success' : 'bg-secondary';
            const lastLogin = s.last_login ? new Date(s.last_login).toLocaleString() : 'Never';
            return `<tr>
                <td>${i + 1}</td>
                <td><strong>${esc(name)}</strong></td>
                <td>${esc(s.email || '—')}</td>
                <td><span class="badge bg-info text-dark">${esc(s.role_name || 'N/A')}</span></td>
                <td><span class="badge ${statusClass}">${esc(s.status || 'Unknown')}</span></td>
                <td>${esc(lastLogin)}</td>
            </tr>`;
        }).join('');
    }

    function renderChart(byRole) {
        const canvas = document.getElementById('roleDistChart');
        if (!canvas || typeof Chart === 'undefined') return;

        const labels = Object.keys(byRole);
        const data = Object.values(byRole);
        const colors = ['#4e73df','#1cc88a','#36b9cc','#f6c23e','#e74a3b','#858796','#5a5c69','#6610f2','#fd7e14','#20c9a6'];

        if (chart) chart.destroy();
        chart = new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels,
                datasets: [{ data, backgroundColor: colors.slice(0, labels.length) }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } }
            }
        });
    }

    function exportCSV() {
        const data = filteredSessions.length ? filteredSessions : allSessions;
        if (!data.length) return;
        const headers = ['Name','Email','Role','Status','Last Login'];
        const rows = data.map(s => [
            `${s.first_name || ''} ${s.last_name || ''}`.trim(),
            s.email || '',
            s.role_name || '',
            s.status || '',
            s.last_login || ''
        ]);
        const csv = [headers.join(','), ...rows.map(r => r.map(c => `"${c}"`).join(','))].join('\n');
        const a = document.createElement('a');
        a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }));
        a.download = 'active_users_' + new Date().toISOString().slice(0,10) + '.csv';
        a.click();
    }

    // Auto-refresh every 30 seconds
    function startAutoRefresh() {
        if (refreshTimer) clearInterval(refreshTimer);
        refreshTimer = setInterval(loadData, 30000);
    }

    // Init
    document.addEventListener('DOMContentLoaded', () => {
        loadData();
        startAutoRefresh();
    });

    return { loadData, applyFilters, exportCSV };
})();
</script>