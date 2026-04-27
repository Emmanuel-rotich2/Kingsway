<?php
/**
 * Students - Manager Layout
 * Compact layout for Deputy Heads, HODs, Accountant, etc.
 *
 * Features:
 * - 3 stat cards
 * - 2 charts
 * - Standard table with 7 columns
 * - Actions: View, Edit, Export
 */
/* PARTIAL — no DOCTYPE/html/head/body. Injected into app shell via fetch. */
?>

<!-- Stats - 3 columns -->
<div class="manager-stats-grid">
    <div class="manager-stat-card">
        <div class="stat-icon">👨‍🎓</div>
        <div class="stat-info">
            <span class="stat-value" id="totalStudents">0</span>
            <span class="stat-label">Total</span>
        </div>
    </div>
    <div class="manager-stat-card">
        <div class="stat-icon">✅</div>
        <div class="stat-info">
            <span class="stat-value" id="activeStudents">0</span>
            <span class="stat-label">Active</span>
        </div>
    </div>
    <div class="manager-stat-card">
        <div class="stat-icon">🆕</div>
        <div class="stat-info">
            <span class="stat-value" id="newThisTerm">0</span>
            <span class="stat-label">New</span>
        </div>
    </div>
</div>

<!-- Charts - 2 columns -->
<div class="manager-charts-row">
    <div class="manager-chart-card">
        <h4>Class Distribution</h4>
        <canvas id="classChart" height="180"></canvas>
    </div>
    <div class="manager-chart-card">
        <h4>Gender</h4>
        <canvas id="genderChart" height="180"></canvas>
    </div>
</div>

<!-- Filters -->
<div class="manager-filters">
    <select class="filter-select" id="filterClass">
        <option value="">All Classes</option>
    </select>
    <select class="filter-select" id="filterStatus">
        <option value="">All Status</option>
        <option value="active">Active</option>
        <option value="inactive">Inactive</option>
    </select>
    <input type="text" class="filter-search" id="searchStudent" placeholder="🔍 Search...">
    <button class="btn btn-outline btn-sm" onclick="exportStudents()">📥 Export</button>
    <button class="btn btn-primary btn-sm" onclick="showAddStudentModal()">➕ Add</button>
</div>

<!-- Table - 7 columns -->
<div class="manager-table-card">
    <table class="manager-data-table" id="studentsTable">
        <thead>
            <tr>
                <th>Adm No</th>
                <th>Name</th>
                <th>Class</th>
                <th>Gender</th>
                <th>Type</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="studentsTableBody">
            <!-- Data loaded dynamically -->
        </tbody>
    </table>

    <div class="table-footer">
        <span class="page-info">Showing <span id="showingCount">0</span> students</span>
        <div class="pagination" id="pagination"></div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="studentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="studentModalTitle">Add Student</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="studentForm">
                    <input type="hidden" id="studentId">
                    <div class="mb-3">
                        <label class="form-label">First Name *</label>
                        <input type="text" class="form-control" id="firstName" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Last Name *</label>
                        <input type="text" class="form-control" id="lastName" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Admission Number *</label>
                        <input type="text" class="form-control" id="admissionNo" required>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Gender</label>
                            <select class="form-select" id="gender">
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                            </select>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Class</label>
                            <select class="form-select" id="classId"></select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Student Type</label>
                        <select class="form-select" id="studentType">
                            <option value="day">Day Scholar</option>
                            <option value="boarder">Boarder</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" id="saveStudentBtn">Save</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        loadStudents();
        loadStats();
        loadFilters();
        initCharts();
        initEventListeners();
    });

    let _allStudentsData = [];

    async function loadStudents(filters = {}) {
        const tbody = document.getElementById('studentsTableBody');
        if (tbody) tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4"><div class="spinner-border spinner-border-sm"></div></td></tr>';
        try {
            const params = new URLSearchParams({ status: 'active', limit: 500 });
            if (filters.class_id) params.set('class_id', filters.class_id);
            if (filters.search)   params.set('search',   filters.search);
            const response = await callAPI('/students?' + params.toString(), 'GET');
            _allStudentsData = Array.isArray(response?.data) ? response.data : (Array.isArray(response) ? response : []);
            renderStudentsTable(_allStudentsData);
            document.getElementById('totalStudents').textContent  = _allStudentsData.length;
            document.getElementById('activeStudents').textContent = _allStudentsData.filter(s => s.status === 'active').length;
        } catch (error) {
            console.error('Error loading students:', error);
            if (tbody) tbody.innerHTML = '<tr><td colspan="8" class="text-danger text-center py-4">Failed to load students.</td></tr>';
        }
    }

    async function loadStats() {
        // Stats computed from loaded data - no separate API call needed
    }

    async function loadFilters() {
        try {
            const classRes = await callAPI('/academic/classes?status=active', 'GET');
            const classes = Array.isArray(classRes?.data) ? classRes.data : (Array.isArray(classRes) ? classRes : []);
            const filterSel = document.getElementById('filterClass');
            const modalSel  = document.getElementById('classId');
            classes.forEach(cls => {
                if (filterSel) filterSel.add(new Option(cls.name, cls.id));
                if (modalSel)  modalSel.add(new Option(cls.name, cls.id));
            });
        } catch (error) {
            console.warn('Error loading class filter:', error);
        }
    }

    function initCharts() {
        new Chart(document.getElementById('classChart'), {
            type: 'bar',
            data: { labels: [], datasets: [{ data: [], backgroundColor: 'var(--green-500)' }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
        });

        new Chart(document.getElementById('genderChart'), {
            type: 'doughnut',
            data: { labels: ['Male', 'Female'], datasets: [{ data: [0, 0], backgroundColor: ['var(--green-500)', 'var(--gold-500)'] }] },
            options: { responsive: true, maintainAspectRatio: false }
        });
    }

    function initEventListeners() {
        document.getElementById('filterClass').addEventListener('change', applyFilters);
        document.getElementById('filterStatus').addEventListener('change', applyFilters);
        document.getElementById('searchStudent').addEventListener('input', debounce(applyFilters, 300));
        document.getElementById('saveStudentBtn').addEventListener('click', saveStudent);
    }

    function applyFilters() {
        loadStudents({
            class_id: document.getElementById('filterClass').value,
            status: document.getElementById('filterStatus').value,
            search: document.getElementById('searchStudent').value
        });
    }

    function renderStudentsTable(students) {
        const tbody = document.getElementById('studentsTableBody');
        tbody.innerHTML = '';

        if (students.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center p-4">No students found</td></tr>';
            return;
        }

        students.forEach(student => {
            const row = document.createElement('tr');
            row.innerHTML = `
            <td>${escapeHtml(student.admission_no)}</td>
            <td><strong>${escapeHtml(student.full_name)}</strong></td>
            <td>${escapeHtml(student.class_name || '-')}</td>
            <td>${student.gender === 'male' ? '♂' : '♀'}</td>
            <td><span class="badge">${student.student_type}</span></td>
            <td><span class="status-badge status-${student.status}">${student.status}</span></td>
            <td class="manager-row-actions">
                <button class="action-btn" onclick="viewStudent(${student.id})">👁</button>
                <button class="action-btn" onclick="editStudent(${student.id})">✏️</button>
            </td>
        `;
            tbody.appendChild(row);
        });

        document.getElementById('showingCount').textContent = students.length;
    }

    function showAddStudentModal() {
        document.getElementById('studentModalTitle').textContent = 'Add Student';
        document.getElementById('studentForm').reset();
        new bootstrap.Modal(document.getElementById('studentModal')).show();
    }

    async function viewStudent(id) {
        const s = _allStudentsData.find(x => x.id == id);
        if (!s) return;
        document.getElementById('studentModalTitle').textContent = 'Edit Student';
        document.getElementById('studentId').value    = s.id;
        document.getElementById('firstName').value   = s.first_name || '';
        document.getElementById('lastName').value    = s.last_name  || '';
        document.getElementById('admissionNo').value = s.admission_no || '';
        if (document.getElementById('gender'))  document.getElementById('gender').value  = s.gender  || 'male';
        if (document.getElementById('classId')) document.getElementById('classId').value = s.class_id || '';
        if (document.getElementById('dob'))     document.getElementById('dob').value     = s.date_of_birth || '';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('studentModal')).show();
    }

    async function editStudent(id) { await viewStudent(id); }

    async function saveStudent() {
        const form = document.getElementById('studentForm');
        if (!form || !form.checkValidity()) { form?.reportValidity(); return; }
        const id = document.getElementById('studentId').value;
        const payload = {
            first_name:    document.getElementById('firstName').value.trim(),
            last_name:     document.getElementById('lastName').value.trim(),
            admission_no:  document.getElementById('admissionNo').value.trim(),
            gender:        document.getElementById('gender')?.value  || 'male',
            class_id:      document.getElementById('classId')?.value || null,
            date_of_birth: document.getElementById('dob')?.value     || null,
        };
        try {
            if (id) {
                await callAPI('/students/' + id, 'PUT', payload);
                showNotification('Student updated', 'success');
            } else {
                await callAPI('/students', 'POST', payload);
                showNotification('Student added', 'success');
            }
            bootstrap.Modal.getInstance(document.getElementById('studentModal'))?.hide();
            loadStudents();
        } catch (e) { showNotification('Failed: ' + (e.message || e), 'error'); }
    }

    function exportStudents() {
        if (!_allStudentsData.length) { showNotification('No students to export', 'warning'); return; }
        const rows = [['Admission No','First Name','Last Name','Gender','Class','Status']];
        _allStudentsData.forEach(s => rows.push([s.admission_no||'', s.first_name||'', s.last_name||'', s.gender||'', s.class_name||'', s.status||'']));
        const csv  = rows.map(r => r.map(v => '"'+String(v).replace(/"/g,'""')+'"').join(',')).join('\n');
        const blob = new Blob([csv], { type: 'text/csv' });
        const a    = document.createElement('a');
        a.href = URL.createObjectURL(blob); a.download = 'students.csv'; a.click();
    }

    function escapeHtml(s) { return s ? s.replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m]) : ''; }
    function debounce(fn, d) { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), d); }; }
</script>
