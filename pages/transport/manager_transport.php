<?php
/**
 * Transport - Manager Layout
 * For Transport Coordinator, HOD Operations
 *
 * Features:
 * - 3 stat cards
 * - Route overview table
 * - Can manage routes and assign students
 */
/* PARTIAL — no DOCTYPE/html/head/body. Injected into app shell via fetch. */
?>

<!-- Stats - 3 columns -->
<div class="manager-stats-grid d-flex gap-3 mb-4">
    <div class="card flex-fill shadow-sm border-0">
        <div class="card-body text-center">
            <div class="fs-3">🛣️</div>
            <h4 id="totalRoutes" class="mb-0">0</h4>
            <small class="text-muted">Routes</small>
        </div>
    </div>
    <div class="card flex-fill shadow-sm border-0">
        <div class="card-body text-center">
            <div class="fs-3">🚌</div>
            <h4 id="totalVehicles" class="mb-0">0</h4>
            <small class="text-muted">Vehicles</small>
        </div>
    </div>
    <div class="card flex-fill shadow-sm border-0">
        <div class="card-body text-center">
            <div class="fs-3">👨‍🎓</div>
            <h4 id="studentsCount" class="mb-0">0</h4>
            <small class="text-muted">Students</small>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <select class="form-select" id="filterStatus">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div class="col-md-5">
                <input type="text" class="form-control" id="searchRoute" placeholder="🔍 Search routes...">
            </div>
            <div class="col-md-4 text-end">
                <button class="btn btn-primary" onclick="showAddRouteModal()">
                    <i class="fas fa-plus me-1"></i> Add Route
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Routes Table -->
<div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="fas fa-route me-2"></i>Transport Routes</h6>
        <span class="text-muted small">Showing <span id="showingCount">0</span> routes</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="routesTable">
                <thead class="table-light">
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
                    <tr><td colspan="6" class="text-center text-muted py-4">Loading routes...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit Route Modal -->
<div class="modal fade" id="routeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Add Route</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="routeForm">
                    <input type="hidden" id="routeId">
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
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" id="saveRouteBtn">Save Route</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    document.addEventListener('DOMContentLoaded', function () {
        loadRoutes();
        loadStats();
        document.getElementById('filterStatus').addEventListener('change', applyFilters);
        document.getElementById('searchRoute').addEventListener('input', debounce(applyFilters, 300));
        document.getElementById('saveRouteBtn').addEventListener('click', saveRoute);
    });

    async function loadRoutes() {
        try {
            const response = await API.transport.getRoutes();
            if (response?.success || Array.isArray(response?.data)) {
                renderRoutesTable(response.data || []);
            }
        } catch (error) {
            document.getElementById('routesTableBody').innerHTML =
                '<tr><td colspan="6" class="text-center text-danger py-3">Failed to load routes.</td></tr>';
        }
    }

    async function loadStats() {
        try {
            const response = await API.transport.getStats();
            if (response?.success) {
                document.getElementById('totalRoutes').textContent = response.data?.routes || 0;
                document.getElementById('totalVehicles').textContent = response.data?.vehicles || 0;
                document.getElementById('studentsCount').textContent = response.data?.students || 0;
            }
        } catch (e) { /* stats are optional */ }
    }

    function applyFilters() {
        const search = document.getElementById('searchRoute').value.toLowerCase();
        const status = document.getElementById('filterStatus').value;
        let shown = 0;
        document.querySelectorAll('#routesTableBody tr[data-status]').forEach(function (row) {
            const matchText = row.textContent.toLowerCase().includes(search);
            const matchStatus = !status || row.dataset.status === status;
            row.style.display = (matchText && matchStatus) ? '' : 'none';
            if (matchText && matchStatus) shown++;
        });
        document.getElementById('showingCount').textContent = shown;
    }

    function renderRoutesTable(routes) {
        const tbody = document.getElementById('routesTableBody');
        if (!routes.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No routes found.</td></tr>';
            document.getElementById('showingCount').textContent = 0;
            return;
        }
        tbody.innerHTML = routes.map(function (r) {
            const status = r.status || 'active';
            return `<tr data-status="${esc(status)}">
                <td><strong>${esc(r.name)}</strong></td>
                <td>${esc(r.vehicle_reg || '—')}</td>
                <td>${esc(r.driver_name || '—')}</td>
                <td>${r.student_count || 0}</td>
                <td><span class="badge bg-${status === 'active' ? 'success' : 'secondary'}">${esc(status)}</span></td>
                <td>
                    <button class="btn btn-sm btn-outline-primary me-1" onclick="viewRoute(${r.id})"><i class="fas fa-eye"></i></button>
                    <button class="btn btn-sm btn-outline-secondary" onclick="editRoute(${r.id})"><i class="fas fa-edit"></i></button>
                </td>
            </tr>`;
        }).join('');
        document.getElementById('showingCount').textContent = routes.length;
    }

    window.showAddRouteModal = function () {
        document.getElementById('routeForm').reset();
        document.getElementById('routeId').value = '';
        document.querySelector('#routeModal .modal-title').textContent = 'Add Route';
        new bootstrap.Modal(document.getElementById('routeModal')).show();
    };

    window.viewRoute = function (id) {
        window.location.href = (window.APP_BASE || '') + '/home.php?route=my_routes&id=' + id;
    };

    window.editRoute = async function (id) {
        // Open modal pre-populated
        document.querySelector('#routeModal .modal-title').textContent = 'Edit Route';
        document.getElementById('routeId').value = id;
        new bootstrap.Modal(document.getElementById('routeModal')).show();
    };

    async function saveRoute() {
        const name = document.getElementById('routeName').value.trim();
        if (!name) { alert('Route name is required.'); return; }
        const id = document.getElementById('routeId').value;
        const data = {
            name,
            vehicle_id: document.getElementById('vehicleId').value || null,
            driver_id: document.getElementById('driverId').value || null,
        };
        try {
            if (id) {
                await API.transport.updateRoute(id, data);
            } else {
                await API.transport.createRoute(data);
            }
            bootstrap.Modal.getInstance(document.getElementById('routeModal'))?.hide();
            await loadRoutes();
            await loadStats();
        } catch (e) {
            alert('Failed to save route: ' + (e.message || 'Error'));
        }
    }

    function esc(s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }
    function debounce(fn, d) {
        let t;
        return function () { clearTimeout(t); t = setTimeout(fn, d); };
    }
})();
</script>
