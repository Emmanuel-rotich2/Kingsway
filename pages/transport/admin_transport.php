<?php
/* PARTIAL — no DOCTYPE/html/head/body. Injected into app shell via fetch. */
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

<!-- Header Actions -->
<div class="header-actions" style="margin-bottom: 1rem;">
    <button class="btn btn-outline" onclick="exportData()">📥 Export</button>
    <button class="btn btn-primary" onclick="showAddRouteModal()">➕ Add Route</button>
</div>

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
    <button class="tab-btn" data-tab="billing">Monthly Billing</button>
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

<!-- Billing Tab (hidden by default) -->
<div class="admin-table-card" id="billingTab" style="display:none;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="d-flex align-items-center gap-2">
            <input type="month" id="billingMonthPicker" class="form-control form-control-sm" style="width:180px;"
                   value="<?= date('Y-m') ?>">
            <button class="btn btn-sm btn-outline-primary" onclick="loadBillingData()">
                <i class="bi bi-search me-1"></i>Load
            </button>
        </div>
        <button class="btn btn-sm btn-success" onclick="generateMonthlyBills()">
            <i class="bi bi-lightning-fill me-1"></i>Generate Bills for Month
        </button>
    </div>

    <!-- Summary cards -->
    <div class="row g-3 mb-3" id="billingSummaryRow"></div>

    <!-- Bills table -->
    <table class="admin-data-table" id="billingTable">
        <thead>
            <tr>
                <th>Student</th>
                <th>Adm No</th>
                <th>Route</th>
                <th>Amount Due</th>
                <th>Amount Paid</th>
                <th>Balance</th>
                <th>Status</th>
                <th>Due Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="billingTableBody">
            <tr><td colspan="9" class="text-center p-4">Select a month and click Load.</td></tr>
        </tbody>
    </table>
</div>

<!-- Record Transport Payment Modal -->
<div class="modal fade" id="transportPaymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Record Transport Payment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2 text-muted small" id="tpBillInfo"></div>
                <input type="hidden" id="tpBillId">
                <div class="row g-3">
                    <div class="col-6">
                        <label class="form-label">Amount Paying (KES)</label>
                        <input type="number" id="tpAmount" class="form-control" min="1" step="0.01">
                    </div>
                    <div class="col-6">
                        <label class="form-label">Payment Method</label>
                        <select id="tpMethod" class="form-select">
                            <option value="cash">Cash</option>
                            <option value="mpesa">M-Pesa</option>
                            <option value="bank">Bank</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Reference / Transaction No</label>
                        <input type="text" id="tpReference" class="form-control" placeholder="Optional">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <input type="text" id="tpNotes" class="form-control" placeholder="Optional">
                    </div>
                </div>
                <div id="tpError" class="alert alert-danger mt-3 d-none"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-success" id="tpSaveBtn" onclick="saveTransportPayment()">Record Payment</button>
            </div>
        </div>
    </div>
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

<script>
    document.addEventListener('DOMContentLoaded', function () {
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
                ['routes', 'vehicles', 'drivers', 'students', 'billing'].forEach(tab => {
                    const el = document.getElementById(tab + 'Tab');
                    if (el) el.style.display = this.dataset.tab === tab ? 'block' : 'none';
                });
                if (this.dataset.tab === 'billing') loadBillingData();
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

    // ---- Transport Billing ----

    async function loadBillingData() {
        const month = document.getElementById('billingMonthPicker').value;
        if (!month) return;
        const billingMonth = month + '-01';
        const tbody = document.getElementById('billingTableBody');
        tbody.innerHTML = '<tr><td colspan="9" class="text-center p-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
        try {
            const [bills, summary] = await Promise.all([
                window.API.apiCall('/transport/bills?billing_month=' + encodeURIComponent(billingMonth) + '&limit=200', 'GET'),
                window.API.apiCall('/transport/bills/summary?billing_month=' + encodeURIComponent(billingMonth), 'GET'),
            ]);
            renderBillingSummary(summary);
            renderBillingTable(bills);
        } catch (e) {
            tbody.innerHTML = '<tr><td colspan="9" class="text-center text-danger p-3">Failed to load billing data.</td></tr>';
        }
    }

    function renderBillingSummary(data) {
        const s = data?.summary || {};
        document.getElementById('billingSummaryRow').innerHTML = `
            <div class="col-md-3"><div class="card border-0 shadow-sm text-center p-3">
                <div class="fw-bold fs-4">${s.total_bills || 0}</div><div class="text-muted small">Total Bills</div>
            </div></div>
            <div class="col-md-3"><div class="card border-0 shadow-sm text-center p-3">
                <div class="fw-bold fs-4 text-primary">KES ${Number(s.total_due || 0).toLocaleString()}</div><div class="text-muted small">Total Due</div>
            </div></div>
            <div class="col-md-3"><div class="card border-0 shadow-sm text-center p-3">
                <div class="fw-bold fs-4 text-success">KES ${Number(s.total_paid || 0).toLocaleString()}</div><div class="text-muted small">Collected</div>
            </div></div>
            <div class="col-md-3"><div class="card border-0 shadow-sm text-center p-3">
                <div class="fw-bold fs-4 text-danger">KES ${Number(s.total_outstanding || 0).toLocaleString()}</div><div class="text-muted small">Outstanding</div>
            </div></div>`;
    }

    function renderBillingTable(bills) {
        const tbody = document.getElementById('billingTableBody');
        if (!Array.isArray(bills) || bills.length === 0) {
            tbody.innerHTML = '<tr><td colspan="9" class="text-center p-4">No bills found for this month.</td></tr>';
            return;
        }
        tbody.innerHTML = bills.map(b => `
            <tr>
                <td>${escapeHtml(b.first_name + ' ' + b.last_name)}</td>
                <td>${escapeHtml(b.admission_no || '')}</td>
                <td>${escapeHtml(b.route_name || '')}</td>
                <td>KES ${Number(b.amount_due).toLocaleString()}</td>
                <td>KES ${Number(b.amount_paid || 0).toLocaleString()}</td>
                <td>KES ${Number(b.balance || 0).toLocaleString()}</td>
                <td><span class="badge bg-${b.payment_status === 'paid' ? 'success' : b.payment_status === 'partial' ? 'warning' : 'secondary'}">${b.payment_status}</span></td>
                <td>${b.due_date || ''}</td>
                <td>${b.payment_status !== 'paid' ? `<button class="btn btn-xs btn-outline-success" onclick="openTransportPayment(${b.id}, '${escapeHtml(b.first_name + ' ' + b.last_name)}', ${b.balance || b.amount_due})">Pay</button>` : ''}</td>
            </tr>`).join('');
    }

    async function generateMonthlyBills() {
        const month = document.getElementById('billingMonthPicker').value;
        if (!month) { alert('Select a billing month first.'); return; }
        const billingMonth = month + '-01';
        if (!confirm('Generate transport bills for ' + month + '? Existing bills for this month will be skipped.')) return;
        try {
            const res = await window.API.apiCall('/transport/bills/generate', 'POST', { billing_month: billingMonth });
            alert('Generated: ' + (res.bills_generated || 0) + ', Skipped: ' + (res.bills_skipped || 0));
            loadBillingData();
        } catch (e) {
            alert('Error: ' + (e.message || 'Failed to generate bills'));
        }
    }

    function openTransportPayment(billId, studentName, balance) {
        document.getElementById('tpBillId').value = billId;
        document.getElementById('tpBillInfo').textContent = studentName + ' — Balance: KES ' + Number(balance).toLocaleString();
        document.getElementById('tpAmount').value = Number(balance).toFixed(2);
        document.getElementById('tpReference').value = '';
        document.getElementById('tpNotes').value = '';
        document.getElementById('tpError').classList.add('d-none');
        new bootstrap.Modal(document.getElementById('transportPaymentModal')).show();
    }

    async function saveTransportPayment() {
        const billId = document.getElementById('tpBillId').value;
        const amount = parseFloat(document.getElementById('tpAmount').value);
        const errEl = document.getElementById('tpError');
        errEl.classList.add('d-none');
        if (!amount || amount <= 0) { errEl.textContent = 'Enter a valid amount.'; errEl.classList.remove('d-none'); return; }
        document.getElementById('tpSaveBtn').disabled = true;
        try {
            await window.API.apiCall('/transport/bills/' + billId + '/record-payment', 'POST', {
                amount_paid: amount,
                payment_method: document.getElementById('tpMethod').value,
                reference_no: document.getElementById('tpReference').value || null,
                notes: document.getElementById('tpNotes').value || null,
            });
            bootstrap.Modal.getInstance(document.getElementById('transportPaymentModal')).hide();
            loadBillingData();
        } catch (e) {
            errEl.textContent = e.message || 'Failed to record payment.';
            errEl.classList.remove('d-none');
        } finally {
            document.getElementById('tpSaveBtn').disabled = false;
        }
    }

    function escapeHtml(s) { return s ? s.replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m]) : ''; }
    function debounce(fn, d) { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), d); }; }
</script>
