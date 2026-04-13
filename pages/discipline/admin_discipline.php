<?php
/* PARTIAL — no DOCTYPE/html/head/body. Injected into app shell via fetch. */
/**
 * Discipline - Admin Layout
 * Full featured for System Admin, Director, Headteacher, Deputy Heads
 *
 * Features:
 * - Full sidebar
 * - 4 stat cards with trends
 * - Charts (cases by type, trend over time)
 * - Full data table with all columns
 * - All actions: View, Edit, Delete, Escalate, Close
 */
?>

<!-- Header Actions -->
<div class="header-actions" style="margin-bottom: 1rem;">
    <button class="btn btn-outline" onclick="exportCases()">📥 Export</button>
    <button class="btn btn-primary" onclick="showNewCaseModal()">➕ New Case</button>
</div>

<!-- Stats Row - 4 cards -->
<div class="admin-stats-grid">
    <div class="stat-card">
        <div class="stat-icon bg-warning">📋</div>
        <div class="stat-content">
            <span class="stat-value" id="totalCases">0</span>
            <span class="stat-label">Total Cases</span>
            <span class="stat-trend" id="casesTrend">This Term</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-danger">🔴</div>
        <div class="stat-content">
            <span class="stat-value" id="openCases">0</span>
            <span class="stat-label">Open</span>
            <span class="stat-trend" id="openTrend">Pending</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-success">✅</div>
        <div class="stat-content">
            <span class="stat-value" id="resolvedCases">0</span>
            <span class="stat-label">Resolved</span>
            <span class="stat-trend up" id="resolvedTrend">-</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-purple">⚠️</div>
        <div class="stat-content">
            <span class="stat-value" id="escalatedCases">0</span>
            <span class="stat-label">Escalated</span>
            <span class="stat-trend" id="escalatedTrend">Needs Attention</span>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="admin-charts-row">
    <div class="chart-card chart-wide">
        <div class="chart-header">
            <h3>Cases Trend</h3>
            <select class="chart-filter" id="trendPeriod">
                <option value="term">This Term</option>
                <option value="year">This Year</option>
            </select>
        </div>
        <canvas id="trendChart" height="200"></canvas>
    </div>
    <div class="chart-card chart-narrow">
        <div class="chart-header">
            <h3>By Category</h3>
        </div>
        <canvas id="categoryChart" height="200"></canvas>
    </div>
</div>

<!-- Tabs -->
<div class="admin-tabs">
    <button class="tab-btn active" data-tab="all">All Cases</button>
    <button class="tab-btn" data-tab="open">Open</button>
    <button class="tab-btn" data-tab="escalated">Escalated</button>
    <button class="tab-btn" data-tab="resolved">Resolved</button>
</div>

<!-- Filters -->
<div class="admin-filters">
    <div class="filter-row">
        <select class="filter-select" id="filterCategory">
            <option value="">All Categories</option>
            <option value="misconduct">Misconduct</option>
            <option value="truancy">Truancy</option>
            <option value="fighting">Fighting</option>
            <option value="bullying">Bullying</option>
            <option value="substance">Substance Abuse</option>
            <option value="other">Other</option>
        </select>
        <select class="filter-select" id="filterSeverity">
            <option value="">All Severity</option>
            <option value="minor">Minor</option>
            <option value="moderate">Moderate</option>
            <option value="major">Major</option>
            <option value="critical">Critical</option>
        </select>
        <select class="filter-select" id="filterClass">
            <option value="">All Classes</option>
        </select>
        <input type="text" class="filter-search" id="searchCase"
            placeholder="🔍 Search by student or case ID...">
    </div>
</div>

<!-- Data Table -->
<div class="admin-table-card">
    <table class="admin-data-table" id="casesTable">
        <thead>
            <tr>
                <th>Case ID</th>
                <th>Date</th>
                <th>Student</th>
                <th>Class</th>
                <th>Category</th>
                <th>Description</th>
                <th>Severity</th>
                <th>Status</th>
                <th>Reported By</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="casesTableBody">
            <!-- Data loaded dynamically -->
        </tbody>
    </table>

    <div class="table-footer">
        <div class="page-info">Showing <span id="showingCount">0</span> of <span id="totalCount">0</span></div>
        <div class="pagination" id="pagination"></div>
    </div>
</div>

<!-- New/Edit Case Modal -->
<div class="modal fade" id="caseModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="caseModalTitle">New Discipline Case</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="caseForm">
                    <input type="hidden" id="caseId">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Student *</label>
                            <select class="form-select" id="studentId" required></select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Date of Incident *</label>
                            <input type="date" class="form-control" id="incidentDate" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Category *</label>
                            <select class="form-select" id="category" required>
                                <option value="">Select Category</option>
                                <option value="misconduct">Misconduct</option>
                                <option value="truancy">Truancy</option>
                                <option value="fighting">Fighting</option>
                                <option value="bullying">Bullying</option>
                                <option value="substance">Substance Abuse</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Severity *</label>
                            <select class="form-select" id="severity" required>
                                <option value="minor">Minor</option>
                                <option value="moderate">Moderate</option>
                                <option value="major">Major</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">Description *</label>
                            <textarea class="form-control" id="description" rows="3" required></textarea>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">Action Taken</label>
                            <textarea class="form-control" id="actionTaken" rows="2"></textarea>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Witnesses</label>
                            <input type="text" class="form-control" id="witnesses" placeholder="Names of witnesses">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Parent Notified</label>
                            <select class="form-select" id="parentNotified">
                                <option value="no">No</option>
                                <option value="yes">Yes</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-warning" id="saveCaseBtn">Save Case</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        loadCases();
        loadStats();
        loadFilters();
        initCharts();
        initEventListeners();
    });

    async function loadCases(filters = {}) {
        try {
            const response = await API.discipline.getAll(filters);
            if (response.success) {
                renderCasesTable(response.data);
                document.getElementById('totalCount').textContent = response.total || response.data.length;
            }
        } catch (error) {
            console.error('Error loading cases:', error);
        }
    }

    async function loadStats() {
        try {
            const response = await API.discipline.getStats();
            if (response.success) {
                document.getElementById('totalCases').textContent = response.data.total || 0;
                document.getElementById('openCases').textContent = response.data.open || 0;
                document.getElementById('resolvedCases').textContent = response.data.resolved || 0;
                document.getElementById('escalatedCases').textContent = response.data.escalated || 0;
            }
        } catch (error) {
            console.error('Error loading stats:', error);
        }
    }

    async function loadFilters() {
        try {
            // Load students for dropdown
            const studentsRes = await API.students.getAll({ status: 'active' });
            if (studentsRes.success) {
                const select = document.getElementById('studentId');
                studentsRes.data.forEach(s => {
                    select.add(new Option(`${s.full_name} (${s.admission_no})`, s.id));
                });
            }

            // Load classes
            const classRes = await API.classes.getAll();
            if (classRes.success) {
                const select = document.getElementById('filterClass');
                classRes.data.forEach(c => select.add(new Option(c.name, c.id)));
            }
        } catch (error) {
            console.error('Error loading filters:', error);
        }
    }

    function initCharts() {
        new Chart(document.getElementById('trendChart'), {
            type: 'line',
            data: {
                labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4', 'Week 5', 'Week 6'],
                datasets: [{
                    label: 'Cases',
                    data: [],
                    borderColor: 'var(--gold-500)',
                    backgroundColor: 'var(--gold-100)',
                    fill: true
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });

        new Chart(document.getElementById('categoryChart'), {
            type: 'doughnut',
            data: {
                labels: ['Misconduct', 'Truancy', 'Fighting', 'Bullying', 'Other'],
                datasets: [{
                    data: [0, 0, 0, 0, 0],
                    backgroundColor: ['#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#6b7280']
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });
    }

    function initEventListeners() {
        ['filterCategory', 'filterSeverity', 'filterClass'].forEach(id => {
            document.getElementById(id).addEventListener('change', applyFilters);
        });
        document.getElementById('searchCase').addEventListener('input', debounce(applyFilters, 300));

        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                loadCases({ status: this.dataset.tab === 'all' ? null : this.dataset.tab });
            });
        });

        document.getElementById('saveCaseBtn').addEventListener('click', saveCase);
    }

    function applyFilters() {
        loadCases({
            category: document.getElementById('filterCategory').value,
            severity: document.getElementById('filterSeverity').value,
            class_id: document.getElementById('filterClass').value,
            search: document.getElementById('searchCase').value
        });
    }

    function renderCasesTable(cases) {
        const tbody = document.getElementById('casesTableBody');
        tbody.innerHTML = '';

        if (cases.length === 0) {
            tbody.innerHTML = '<tr><td colspan="10" class="text-center p-4">No cases found</td></tr>';
            return;
        }

        cases.forEach(c => {
            const row = document.createElement('tr');
            row.innerHTML = `
            <td><code>${c.case_id || 'DC-' + c.id}</code></td>
            <td>${formatDate(c.incident_date)}</td>
            <td><strong>${escapeHtml(c.student_name)}</strong></td>
            <td>${escapeHtml(c.class_name || '-')}</td>
            <td><span class="badge badge-${c.category}">${c.category}</span></td>
            <td class="text-truncate" style="max-width:150px;">${escapeHtml(c.description)}</td>
            <td><span class="severity-badge severity-${c.severity}">${c.severity}</span></td>
            <td><span class="status-badge status-${c.status}">${c.status}</span></td>
            <td>${escapeHtml(c.reported_by_name || '-')}</td>
            <td class="admin-row-actions">
                <button class="action-btn" onclick="viewCase(${c.id})">👁</button>
                <button class="action-btn" onclick="editCase(${c.id})">✏️</button>
                <button class="action-btn dropdown-toggle" data-bs-toggle="dropdown">⋮</button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" onclick="escalateCase(${c.id})">⚠️ Escalate</a></li>
                    <li><a class="dropdown-item" onclick="resolveCase(${c.id})">✅ Resolve</a></li>
                    <li><a class="dropdown-item" onclick="notifyParent(${c.id})">📧 Notify Parent</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" onclick="deleteCase(${c.id})">🗑️ Delete</a></li>
                </ul>
            </td>
        `;
            tbody.appendChild(row);
        });

        document.getElementById('showingCount').textContent = cases.length;
    }

    function showNewCaseModal() {
        document.getElementById('caseModalTitle').textContent = 'New Discipline Case';
        document.getElementById('caseForm').reset();
        document.getElementById('incidentDate').value = new Date().toISOString().split('T')[0];
        new bootstrap.Modal(document.getElementById('caseModal')).show();
    }

    async function saveCase() { console.log('Save case'); }
    function viewCase(id) { console.log('View case:', id); }
    function editCase(id) { console.log('Edit case:', id); }
    function escalateCase(id) { console.log('Escalate case:', id); }
    function resolveCase(id) { console.log('Resolve case:', id); }
    function notifyParent(id) { console.log('Notify parent:', id); }
    function deleteCase(id) { console.log('Delete case:', id); }
    function exportCases() { console.log('Export cases'); }

    function formatDate(d) { return d ? new Date(d).toLocaleDateString() : '-'; }
    function escapeHtml(s) { return s ? s.replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m]) : ''; }
    function debounce(fn, d) { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), d); }; }
</script>
