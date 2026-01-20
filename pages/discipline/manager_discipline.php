<?php
/**
 * Discipline - Manager Layout
 * Compact layout for HODs, Boarding Master, Counselors
 * 
 * Features:
 * - Compact sidebar
 * - 3 stat cards
 * - 2 charts
 * - Standard table (7 columns)
 * - Can report and manage cases in their department
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
            <a href="/pages/discipline_cases.php" class="manager-nav-item active" data-label="Discipline">⚖️</a>
            <a href="/pages/counseling_records.php" class="manager-nav-item" data-label="Counseling">🧠</a>
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
                <h1 class="page-title">⚖️ Discipline Cases</h1>
            </div>
            <div class="header-actions">
                <button class="btn btn-primary btn-sm" onclick="showNewCaseModal()">➕ Report Case</button>
            </div>
        </header>

        <!-- Stats - 3 columns -->
        <div class="manager-stats-grid">
            <div class="manager-stat-card">
                <div class="stat-icon">📋</div>
                <div class="stat-info">
                    <span class="stat-value" id="totalCases">0</span>
                    <span class="stat-label">Total</span>
                </div>
            </div>
            <div class="manager-stat-card">
                <div class="stat-icon">🔴</div>
                <div class="stat-info">
                    <span class="stat-value" id="openCases">0</span>
                    <span class="stat-label">Open</span>
                </div>
            </div>
            <div class="manager-stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-info">
                    <span class="stat-value" id="resolvedCases">0</span>
                    <span class="stat-label">Resolved</span>
                </div>
            </div>
        </div>

        <!-- Charts - 2 columns -->
        <div class="manager-charts-row">
            <div class="manager-chart-card">
                <h4>Weekly Trend</h4>
                <canvas id="trendChart" height="180"></canvas>
            </div>
            <div class="manager-chart-card">
                <h4>By Category</h4>
                <canvas id="categoryChart" height="180"></canvas>
            </div>
        </div>

        <!-- Filters -->
        <div class="manager-filters">
            <select class="filter-select" id="filterCategory">
                <option value="">All Categories</option>
                <option value="misconduct">Misconduct</option>
                <option value="truancy">Truancy</option>
                <option value="fighting">Fighting</option>
                <option value="bullying">Bullying</option>
            </select>
            <select class="filter-select" id="filterStatus">
                <option value="">All Status</option>
                <option value="open">Open</option>
                <option value="resolved">Resolved</option>
            </select>
            <input type="text" class="filter-search" id="searchCase" placeholder="🔍 Search...">
        </div>

        <!-- Table - 7 columns -->
        <div class="manager-table-card">
            <table class="manager-data-table" id="casesTable">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Student</th>
                        <th>Class</th>
                        <th>Category</th>
                        <th>Severity</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="casesTableBody">
                    <!-- Data loaded dynamically -->
                </tbody>
            </table>

            <div class="table-footer">
                <span class="page-info">Showing <span id="showingCount">0</span> cases</span>
            </div>
        </div>
    </main>
</div>

<!-- Report Case Modal -->
<div class="modal fade" id="caseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title">Report Discipline Case</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="caseForm">
                    <div class="mb-3">
                        <label class="form-label">Student *</label>
                        <select class="form-select" id="studentId" required></select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Category *</label>
                        <select class="form-select" id="category" required>
                            <option value="">Select</option>
                            <option value="misconduct">Misconduct</option>
                            <option value="truancy">Truancy</option>
                            <option value="fighting">Fighting</option>
                            <option value="bullying">Bullying</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Severity</label>
                        <select class="form-select" id="severity">
                            <option value="minor">Minor</option>
                            <option value="moderate">Moderate</option>
                            <option value="major">Major</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description *</label>
                        <textarea class="form-control" id="description" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Action Taken</label>
                        <textarea class="form-control" id="actionTaken" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-warning" id="saveCaseBtn">Submit</button>
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
            document.getElementById('userInitial').textContent = (user.name || 'M').charAt(0).toUpperCase();
        }

        loadCases();
        loadStats();
        loadStudents();
        initCharts();
        initEventListeners();
    });

    async function loadCases(filters = {}) {
        try {
            const response = await API.discipline.getAll(filters);
            if (response.success) {
                renderCasesTable(response.data);
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
            }
        } catch (error) {
            console.error('Error loading stats:', error);
        }
    }

    async function loadStudents() {
        try {
            const response = await API.students.getAll({ status: 'active' });
            if (response.success) {
                const select = document.getElementById('studentId');
                response.data.forEach(s => {
                    select.add(new Option(`${s.full_name} (${s.admission_no})`, s.id));
                });
            }
        } catch (error) {
            console.error('Error loading students:', error);
        }
    }

    function initCharts() {
        new Chart(document.getElementById('trendChart'), {
            type: 'bar',
            data: { labels: [], datasets: [{ data: [], backgroundColor: 'var(--gold-500)' }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
        });

        new Chart(document.getElementById('categoryChart'), {
            type: 'doughnut',
            data: { labels: [], datasets: [{ data: [], backgroundColor: ['#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'] }] },
            options: { responsive: true, maintainAspectRatio: false }
        });
    }

    function initEventListeners() {
        document.getElementById('filterCategory').addEventListener('change', applyFilters);
        document.getElementById('filterStatus').addEventListener('change', applyFilters);
        document.getElementById('searchCase').addEventListener('input', debounce(applyFilters, 300));
        document.getElementById('saveCaseBtn').addEventListener('click', saveCase);
    }

    function applyFilters() {
        loadCases({
            category: document.getElementById('filterCategory').value,
            status: document.getElementById('filterStatus').value,
            search: document.getElementById('searchCase').value
        });
    }

    function renderCasesTable(cases) {
        const tbody = document.getElementById('casesTableBody');
        tbody.innerHTML = '';

        if (cases.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center p-4">No cases found</td></tr>';
            return;
        }

        cases.forEach(c => {
            const row = document.createElement('tr');
            row.innerHTML = `
            <td>${formatDate(c.incident_date)}</td>
            <td><strong>${escapeHtml(c.student_name)}</strong></td>
            <td>${escapeHtml(c.class_name || '-')}</td>
            <td><span class="badge">${c.category}</span></td>
            <td><span class="severity-badge severity-${c.severity}">${c.severity}</span></td>
            <td><span class="status-badge status-${c.status}">${c.status}</span></td>
            <td class="manager-row-actions">
                <button class="action-btn" onclick="viewCase(${c.id})">👁</button>
                <button class="action-btn" onclick="editCase(${c.id})">✏️</button>
            </td>
        `;
            tbody.appendChild(row);
        });

        document.getElementById('showingCount').textContent = cases.length;
    }

    function showNewCaseModal() {
        document.getElementById('caseForm').reset();
        new bootstrap.Modal(document.getElementById('caseModal')).show();
    }

    async function saveCase() { console.log('Save case'); }
    function viewCase(id) { console.log('View:', id); }
    function editCase(id) { console.log('Edit:', id); }

    function formatDate(d) { return d ? new Date(d).toLocaleDateString() : '-'; }
    function escapeHtml(s) { return s ? s.replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m]) : ''; }
    function debounce(fn, d) { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), d); }; }
</script>