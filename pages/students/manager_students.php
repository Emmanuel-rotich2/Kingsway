<?php
/**
 * Students - Manager Layout
 * Compact layout for Deputy Heads, HODs, Accountant, etc.
 * 
 * Features:
 * - Compact sidebar (expands on hover)
 * - 3 stat cards
 * - 2 charts
 * - Standard table with 7 columns
 * - Actions: View, Edit, Export
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
            <a href="/pages/all_students.php" class="manager-nav-item active" data-label="Students">👨‍🎓</a>
            <a href="/pages/all_teachers.php" class="manager-nav-item" data-label="Teachers">👩‍🏫</a>
            <a href="/pages/all_classes.php" class="manager-nav-item" data-label="Classes">📚</a>
            <a href="/pages/manage_communications.php" class="manager-nav-item" data-label="Messages">📧</a>
            <a href="/pages/manage_activities.php" class="manager-nav-item" data-label="Activities">🏆</a>
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
                <h1 class="page-title">👨‍🎓 Students</h1>
            </div>
            <div class="header-actions">
                <button class="btn btn-outline btn-sm" onclick="exportStudents()">📥 Export</button>
                <button class="btn btn-primary btn-sm" onclick="showAddStudentModal()">➕ Add</button>
            </div>
        </header>

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
    </main>
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

<script src="/js/components/RoleBasedUI.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        RoleBasedUI.applyLayout();

        const user = AuthContext.getUser();
        if (user) {
            document.getElementById('userInitial').textContent = (user.name || 'M').charAt(0).toUpperCase();
        }

        loadStudents();
        loadStats();
        loadFilters();
        initCharts();
        initEventListeners();
    });

    async function loadStudents(filters = {}) {
        try {
            const response = await API.students.getAll(filters);
            if (response.success) {
                renderStudentsTable(response.data);
            }
        } catch (error) {
            console.error('Error loading students:', error);
        }
    }

    async function loadStats() {
        try {
            const response = await API.students.getStats();
            if (response.success) {
                document.getElementById('totalStudents').textContent = response.data.total || 0;
                document.getElementById('activeStudents').textContent = response.data.active || 0;
                document.getElementById('newThisTerm').textContent = response.data.newThisTerm || 0;
            }
        } catch (error) {
            console.error('Error loading stats:', error);
        }
    }

    async function loadFilters() {
        try {
            const classRes = await API.classes.getAll();
            if (classRes.success) {
                const select = document.getElementById('filterClass');
                const modalSelect = document.getElementById('classId');
                classRes.data.forEach(cls => {
                    select.add(new Option(cls.name, cls.id));
                    modalSelect.add(new Option(cls.name, cls.id));
                });
            }
        } catch (error) {
            console.error('Error loading filters:', error);
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

    async function viewStudent(id) { console.log('View student:', id); }
    async function editStudent(id) { console.log('Edit student:', id); }
    async function saveStudent() { console.log('Save student'); }
    function exportStudents() { console.log('Export'); }

    function escapeHtml(s) { return s ? s.replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m]) : ''; }
    function debounce(fn, d) { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), d); }; }
</script>