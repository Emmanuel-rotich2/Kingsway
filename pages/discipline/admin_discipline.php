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

    let _allCases = [];

    async function loadCases(filters = {}) {
        const tbody = document.getElementById('casesTableBody');
        if (tbody) tbody.innerHTML = '<tr><td colspan="10" class="text-center py-4"><div class="spinner-border text-danger spinner-border-sm"></div> Loading…</td></tr>';
        try {
            const params = new URLSearchParams();
            if (filters.status)   params.set('status', filters.status);
            if (filters.category) params.set('category', filters.category);
            if (filters.severity) params.set('severity', filters.severity);
            if (filters.class_id) params.set('class_id', filters.class_id);
            if (filters.search)   params.set('search', filters.search);
            const qs = params.toString();
            const response = await callAPI('/students/discipline-get' + (qs ? '?' + qs : ''), 'GET');
            _allCases = Array.isArray(response?.data) ? response.data : (Array.isArray(response) ? response : []);
            renderCasesTable(_allCases);
            const el = document.getElementById('totalCount');
            if (el) el.textContent = _allCases.length;
        } catch (error) {
            console.error('Error loading cases:', error);
            if (tbody) tbody.innerHTML = '<tr><td colspan="10" class="text-danger text-center py-4">Failed to load discipline cases.</td></tr>';
        }
    }

    async function loadStats() {
        try {
            const response = await callAPI('/students/discipline-get', 'GET');
            const cases = Array.isArray(response?.data) ? response.data : (Array.isArray(response) ? response : []);
            const total     = cases.length;
            const open      = cases.filter(c => c.status === 'open' || c.status === 'under_review').length;
            const resolved  = cases.filter(c => c.status === 'resolved').length;
            const escalated = cases.filter(c => c.status === 'escalated').length;
            const setEl = (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; };
            setEl('totalCases',    total);
            setEl('openCases',     open);
            setEl('resolvedCases', resolved);
            setEl('escalatedCases',escalated);
            _updateCharts(cases);
        } catch (error) {
            console.error('Error loading stats:', error);
        }
    }

    function _updateCharts(cases) {
        const catCounts = {};
        cases.forEach(c => { catCounts[c.category] = (catCounts[c.category] || 0) + 1; });
        if (window._categoryChartInst) {
            window._categoryChartInst.data.labels   = Object.keys(catCounts);
            window._categoryChartInst.data.datasets[0].data = Object.values(catCounts);
            window._categoryChartInst.update();
        }
    }

    async function loadFilters() {
        try {
            const studentsRes = await callAPI('/students?status=active&limit=500', 'GET');
            const students = Array.isArray(studentsRes?.data) ? studentsRes.data : (Array.isArray(studentsRes) ? studentsRes : []);
            const sel = document.getElementById('studentId');
            if (sel) {
                sel.innerHTML = '<option value="">Select Student</option>' +
                    students.map(s => `<option value="${s.id}">${escapeHtml((s.first_name||'')+' '+(s.last_name||''))+(s.admission_no ? ' ('+s.admission_no+')' : '')}</option>`).join('');
            }
        } catch (error) { console.warn('Error loading student filter:', error); }

        try {
            const classRes = await callAPI('/academic/classes?status=active', 'GET');
            const classes = Array.isArray(classRes?.data) ? classRes.data : (Array.isArray(classRes) ? classRes : []);
            const sel2 = document.getElementById('filterClass');
            if (sel2) classes.forEach(c => sel2.add(new Option(c.name, c.id)));
        } catch (error) { console.warn('Error loading class filter:', error); }
    }

    function initCharts() {
        const trendCanvas = document.getElementById('trendChart');
        if (trendCanvas) {
            new Chart(trendCanvas, {
                type: 'line',
                data: {
                    labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4', 'Week 5', 'Week 6'],
                    datasets: [{ label: 'Cases', data: [0,0,0,0,0,0], borderColor: '#f59e0b', backgroundColor: 'rgba(245,158,11,0.1)', fill: true }]
                },
                options: { responsive: true, maintainAspectRatio: false }
            });
        }
        const catCanvas = document.getElementById('categoryChart');
        if (catCanvas) {
            window._categoryChartInst = new Chart(catCanvas, {
                type: 'doughnut',
                data: {
                    labels: [],
                    datasets: [{ data: [], backgroundColor: ['#f59e0b','#ef4444','#8b5cf6','#ec4899','#6b7280','#3b82f6'] }]
                },
                options: { responsive: true, maintainAspectRatio: false }
            });
        }
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

    async function saveCase() {
        const form = document.getElementById('caseForm');
        if (!form || !form.checkValidity()) { form?.reportValidity(); return; }
        const id = document.getElementById('caseId').value;
        const payload = {
            student_id:    document.getElementById('studentId').value,
            incident_date: document.getElementById('incidentDate').value,
            category:      document.getElementById('category').value,
            severity:      document.getElementById('severity').value,
            description:   document.getElementById('description').value.trim(),
            witnesses:     document.getElementById('witnesses')?.value?.trim() || null,
        };
        try {
            if (id) {
                await callAPI('/students/discipline-update/' + id, 'PUT', payload);
                showNotification('Case updated', 'success');
            } else {
                await callAPI('/students/discipline-record', 'POST', { ...payload, reported_by: AuthContext.getUser()?.user_id });
                showNotification('Case recorded', 'success');
            }
            bootstrap.Modal.getInstance(document.getElementById('caseModal'))?.hide();
            loadCases();
            loadStats();
        } catch (e) {
            showNotification('Failed to save: ' + (e.message || e), 'error');
        }
    }

    function viewCase(id) {
        const c = _allCases.find(x => x.id == id);
        if (!c) return;
        document.getElementById('caseModalTitle').textContent = 'Case #' + (c.case_id || 'DC-' + c.id);
        document.getElementById('caseId').value          = c.id;
        document.getElementById('studentId').value       = c.student_id || '';
        document.getElementById('incidentDate').value    = c.incident_date || '';
        document.getElementById('category').value        = c.category || '';
        document.getElementById('severity').value        = c.severity || '';
        document.getElementById('description').value     = c.description || '';
        if (document.getElementById('witnesses')) document.getElementById('witnesses').value = c.witnesses || '';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('caseModal')).show();
    }

    function editCase(id) { viewCase(id); }

    async function escalateCase(id) {
        if (!confirm('Escalate this case to the head of discipline?')) return;
        try {
            await callAPI('/students/discipline-update/' + id, 'PUT', { status: 'escalated' });
            showNotification('Case escalated', 'success');
            loadCases(); loadStats();
        } catch (e) { showNotification('Failed: ' + (e.message || e), 'error'); }
    }

    async function resolveCase(id) {
        const notes = prompt('Resolution notes (optional):');
        if (notes === null) return;
        try {
            await callAPI('/students/discipline-resolve', 'POST', { record_id: id, resolution_notes: notes });
            showNotification('Case resolved', 'success');
            loadCases(); loadStats();
        } catch (e) {
            // Fallback to update endpoint
            try {
                await callAPI('/students/discipline-update/' + id, 'PUT', { status: 'resolved', resolution_notes: notes });
                showNotification('Case resolved', 'success');
                loadCases(); loadStats();
            } catch (e2) { showNotification('Failed: ' + (e2.message || e2), 'error'); }
        }
    }

    async function notifyParent(id) {
        const c = _allCases.find(x => x.id == id);
        if (!c) return;
        if (!confirm('Send notification to parent/guardian of ' + (c.student_name || 'student') + '?')) return;
        try {
            await callAPI('/communications/send', 'POST', {
                recipient_type: 'parent',
                student_id: c.student_id,
                subject: 'Discipline Case Notification',
                message: 'A discipline case has been recorded for your child. Please contact the school for details.',
                channel: 'sms',
            });
            showNotification('Parent notified', 'success');
        } catch (e) { showNotification('Notification failed: ' + (e.message || e), 'error'); }
    }

    async function deleteCase(id) {
        if (!confirm('Permanently delete this discipline case? This cannot be undone.')) return;
        try {
            await callAPI('/students/discipline-update/' + id, 'PUT', { status: 'deleted', deleted: true });
            showNotification('Case deleted', 'success');
            loadCases(); loadStats();
        } catch (e) { showNotification('Failed: ' + (e.message || e), 'error'); }
    }

    function exportCases() {
        if (!_allCases.length) { showNotification('No cases to export', 'warning'); return; }
        const rows = [['Case ID','Date','Student','Class','Category','Severity','Status','Reported By']];
        _allCases.forEach(c => rows.push([
            c.case_id || 'DC-'+c.id, c.incident_date || '', c.student_name || '',
            c.class_name || '', c.category || '', c.severity || '', c.status || '', c.reported_by_name || '',
        ]));
        const csv  = rows.map(r => r.map(v => '"' + String(v).replace(/"/g,'""') + '"').join(',')).join('\n');
        const blob = new Blob([csv], { type: 'text/csv' });
        const a    = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'discipline_cases_' + new Date().toISOString().slice(0,10) + '.csv';
        a.click();
    }

    function formatDate(d) { return d ? new Date(d).toLocaleDateString() : '-'; }
    function escapeHtml(s) { return s ? s.replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m]) : ''; }
    function debounce(fn, d) { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), d); }; }
</script>
