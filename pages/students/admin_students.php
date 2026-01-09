<?php
/**
 * Students - Admin Layout
 * Full featured for System Administrator, Director, Headteacher, School Admin
 * 
 * Features:
 * - Full sidebar
 * - 4 stat cards with trends
 * - 3 charts (enrollment, gender, class distribution)
 * - Full data table with all columns
 * - All actions: View, Edit, Delete, Transfer, Graduate, Export
 * - Bulk operations
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
                <a href="/pages/all_students.php" class="nav-item active">👨‍🎓 Students</a>
                <a href="/pages/all_teachers.php" class="nav-item">👩‍🏫 Teachers</a>
                <a href="/pages/all_staff.php" class="nav-item">👥 Staff</a>
            </div>
            <div class="nav-section">
                <span class="nav-section-title">Academic</span>
                <a href="/pages/all_classes.php" class="nav-item">📚 Classes</a>
                <a href="/pages/academic_years.php" class="nav-item">📅 Academic Years</a>
                <a href="/pages/assessments_exams.php" class="nav-item">📝 Assessments</a>
            </div>
            <div class="nav-section">
                <span class="nav-section-title">Operations</span>
                <a href="/pages/manage_communications.php" class="nav-item">📧 Communications</a>
                <a href="/pages/manage_activities.php" class="nav-item">🏆 Activities</a>
                <a href="/pages/manage_transport.php" class="nav-item">🚌 Transport</a>
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
                <h1 class="page-title">👨‍🎓 Student Management</h1>
                <p class="page-subtitle">View, manage, and analyze student data</p>
            </div>
            <div class="header-actions">
                <button class="btn btn-outline" onclick="exportStudents()">📥 Export</button>
                <button class="btn btn-primary" onclick="showAddStudentModal()">➕ Add Student</button>
            </div>
        </header>

        <!-- Stats Row - 4 cards -->
        <div class="admin-stats-grid">
            <div class="stat-card">
                <div class="stat-icon bg-primary">👨‍🎓</div>
                <div class="stat-content">
                    <span class="stat-value" id="totalStudents">0</span>
                    <span class="stat-label">Total Students</span>
                    <span class="stat-trend up" id="enrollmentTrend">+0%</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon bg-success">✅</div>
                <div class="stat-content">
                    <span class="stat-value" id="activeStudents">0</span>
                    <span class="stat-label">Active</span>
                    <span class="stat-trend" id="activeTrend">-</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon bg-warning">🆕</div>
                <div class="stat-content">
                    <span class="stat-value" id="newAdmissions">0</span>
                    <span class="stat-label">New This Term</span>
                    <span class="stat-trend" id="newTrend">-</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon bg-info">🎓</div>
                <div class="stat-content">
                    <span class="stat-value" id="graduatingCount">0</span>
                    <span class="stat-label">Graduating</span>
                    <span class="stat-trend" id="gradTrend">-</span>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="admin-charts-row">
            <div class="chart-card chart-wide">
                <div class="chart-header">
                    <h3>Enrollment Trend</h3>
                    <select class="chart-filter" id="enrollmentPeriod">
                        <option value="year">This Year</option>
                        <option value="5years">5 Years</option>
                    </select>
                </div>
                <canvas id="enrollmentChart" height="200"></canvas>
            </div>
            <div class="chart-card chart-narrow">
                <div class="chart-header">
                    <h3>Gender Distribution</h3>
                </div>
                <canvas id="genderChart" height="200"></canvas>
            </div>
        </div>

        <!-- Tabs for different views -->
        <div class="admin-tabs">
            <button class="tab-btn active" data-tab="all">All Students</button>
            <button class="tab-btn" data-tab="boarders">Boarders</button>
            <button class="tab-btn" data-tab="dayScholars">Day Scholars</button>
            <button class="tab-btn" data-tab="specialNeeds">Special Needs</button>
        </div>

        <!-- Filters -->
        <div class="admin-filters">
            <div class="filter-row">
                <select class="filter-select" id="filterClass">
                    <option value="">All Classes</option>
                </select>
                <select class="filter-select" id="filterStream">
                    <option value="">All Streams</option>
                </select>
                <select class="filter-select" id="filterStatus">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="graduated">Graduated</option>
                    <option value="transferred">Transferred</option>
                </select>
                <select class="filter-select" id="filterGender">
                    <option value="">All Genders</option>
                    <option value="male">Male</option>
                    <option value="female">Female</option>
                </select>
                <input type="text" class="filter-search" id="searchStudent"
                    placeholder="🔍 Search by name or admission no...">
            </div>
            <div class="bulk-actions" id="bulkActions" style="display:none;">
                <span class="selected-count"><span id="selectedCount">0</span> selected</span>
                <button class="btn btn-sm" onclick="bulkExport()">📥 Export</button>
                <button class="btn btn-sm" onclick="bulkMessage()">📧 Message</button>
                <button class="btn btn-sm btn-danger" onclick="bulkDelete()">🗑️ Delete</button>
            </div>
        </div>

        <!-- Data Table - All columns -->
        <div class="admin-table-card">
            <table class="admin-data-table" id="studentsTable">
                <thead>
                    <tr>
                        <th class="checkbox-col"><input type="checkbox" id="selectAll"></th>
                        <th>Photo</th>
                        <th>Adm No</th>
                        <th>Name</th>
                        <th>Class</th>
                        <th>Stream</th>
                        <th>Gender</th>
                        <th>DOB</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Parent Contact</th>
                        <th>Fee Balance</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="studentsTableBody">
                    <!-- Data loaded dynamically -->
                </tbody>
            </table>

            <div class="table-footer">
                <div class="page-info">Showing <span id="showingCount">0</span> of <span id="totalCount">0</span></div>
                <div class="pagination" id="pagination"></div>
            </div>
        </div>
    </main>
</div>

<!-- Add/Edit Student Modal -->
<div class="modal fade" id="studentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="studentModalTitle">Add Student</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="studentForm">
                    <input type="hidden" id="studentId">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">First Name *</label>
                            <input type="text" class="form-control" id="firstName" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Last Name *</label>
                            <input type="text" class="form-control" id="lastName" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Admission Number *</label>
                            <input type="text" class="form-control" id="admissionNo" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Date of Birth *</label>
                            <input type="date" class="form-control" id="dob" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Gender *</label>
                            <select class="form-select" id="gender" required>
                                <option value="">Select</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Class *</label>
                            <select class="form-select" id="classId" required></select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Stream</label>
                            <select class="form-select" id="streamId"></select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Student Type</label>
                            <select class="form-select" id="studentType">
                                <option value="day">Day Scholar</option>
                                <option value="boarder">Boarder</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <hr>
                            <h6>Parent/Guardian Info</h6>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Parent Name</label>
                            <input type="text" class="form-control" id="parentName">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Parent Phone</label>
                            <input type="tel" class="form-control" id="parentPhone">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveStudentBtn">Save Student</button>
            </div>
        </div>
    </div>
</div>

<!-- View Student Modal -->
<div class="modal fade" id="viewStudentModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Student Profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="studentProfileContent">
                <!-- Loaded dynamically -->
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
                document.getElementById('totalCount').textContent = response.total || response.data.length;
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
                document.getElementById('newAdmissions').textContent = response.data.newThisTerm || 0;
                document.getElementById('graduatingCount').textContent = response.data.graduating || 0;
            }
        } catch (error) {
            console.error('Error loading stats:', error);
        }
    }

    async function loadFilters() {
        try {
            const [classRes, streamRes] = await Promise.all([
                API.classes.getAll(),
                API.streams.getAll()
            ]);

            if (classRes.success) {
                const select = document.getElementById('filterClass');
                const modalSelect = document.getElementById('classId');
                classRes.data.forEach(cls => {
                    select.add(new Option(cls.name, cls.id));
                    modalSelect?.add(new Option(cls.name, cls.id));
                });
            }

            if (streamRes.success) {
                const select = document.getElementById('filterStream');
                const modalSelect = document.getElementById('streamId');
                streamRes.data.forEach(stream => {
                    select.add(new Option(stream.name, stream.id));
                    modalSelect?.add(new Option(stream.name, stream.id));
                });
            }
        } catch (error) {
            console.error('Error loading filters:', error);
        }
    }

    function initCharts() {
        // Enrollment chart
        new Chart(document.getElementById('enrollmentChart'), {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                datasets: [{
                    label: 'Enrollment',
                    data: [],
                    borderColor: 'var(--green-600)',
                    backgroundColor: 'var(--green-100)',
                    fill: true
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });

        // Gender chart
        new Chart(document.getElementById('genderChart'), {
            type: 'doughnut',
            data: {
                labels: ['Male', 'Female'],
                datasets: [{
                    data: [0, 0],
                    backgroundColor: ['var(--green-500)', 'var(--gold-500)']
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });
    }

    function initEventListeners() {
        // Select all checkbox
        document.getElementById('selectAll').addEventListener('change', function () {
            document.querySelectorAll('.student-checkbox').forEach(cb => cb.checked = this.checked);
            updateBulkActions();
        });

        // Filters
        ['filterClass', 'filterStream', 'filterStatus', 'filterGender'].forEach(id => {
            document.getElementById(id).addEventListener('change', applyFilters);
        });

        document.getElementById('searchStudent').addEventListener('input', debounce(applyFilters, 300));

        // Tabs
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                loadStudents({ type: this.dataset.tab });
            });
        });

        // Save student
        document.getElementById('saveStudentBtn').addEventListener('click', saveStudent);
    }

    function applyFilters() {
        const filters = {
            class_id: document.getElementById('filterClass').value,
            stream_id: document.getElementById('filterStream').value,
            status: document.getElementById('filterStatus').value,
            gender: document.getElementById('filterGender').value,
            search: document.getElementById('searchStudent').value
        };
        loadStudents(filters);
    }

    function renderStudentsTable(students) {
        const tbody = document.getElementById('studentsTableBody');
        tbody.innerHTML = '';

        if (students.length === 0) {
            tbody.innerHTML = '<tr><td colspan="13" class="text-center p-4">No students found</td></tr>';
            return;
        }

        students.forEach(student => {
            const row = document.createElement('tr');
            row.innerHTML = `
            <td><input type="checkbox" class="student-checkbox" value="${student.id}" onchange="updateBulkActions()"></td>
            <td><img src="${student.photo || '/images/default-avatar.png'}" alt="" class="student-photo"></td>
            <td>${escapeHtml(student.admission_no)}</td>
            <td><strong>${escapeHtml(student.full_name)}</strong></td>
            <td>${escapeHtml(student.class_name || '-')}</td>
            <td>${escapeHtml(student.stream_name || '-')}</td>
            <td>${student.gender === 'male' ? '♂' : '♀'}</td>
            <td>${formatDate(student.dob)}</td>
            <td><span class="badge badge-${student.student_type}">${student.student_type}</span></td>
            <td><span class="status-badge status-${student.status}">${student.status}</span></td>
            <td>${escapeHtml(student.parent_phone || '-')}</td>
            <td class="${student.fee_balance > 0 ? 'text-danger' : ''}">KES ${formatNumber(student.fee_balance || 0)}</td>
            <td class="admin-row-actions">
                <button class="action-btn" onclick="viewStudent(${student.id})">👁</button>
                <button class="action-btn" onclick="editStudent(${student.id})">✏️</button>
                <button class="action-btn" onclick="messageParent(${student.id})">📧</button>
                <button class="action-btn dropdown-toggle" data-bs-toggle="dropdown">⋮</button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" onclick="transferStudent(${student.id})">🔄 Transfer</a></li>
                    <li><a class="dropdown-item" onclick="graduateStudent(${student.id})">🎓 Graduate</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" onclick="deleteStudent(${student.id})">🗑️ Delete</a></li>
                </ul>
            </td>
        `;
            tbody.appendChild(row);
        });

        document.getElementById('showingCount').textContent = students.length;
    }

    function updateBulkActions() {
        const checked = document.querySelectorAll('.student-checkbox:checked').length;
        document.getElementById('selectedCount').textContent = checked;
        document.getElementById('bulkActions').style.display = checked > 0 ? 'flex' : 'none';
    }

    function showAddStudentModal() {
        document.getElementById('studentModalTitle').textContent = 'Add Student';
        document.getElementById('studentForm').reset();
        document.getElementById('studentId').value = '';
        new bootstrap.Modal(document.getElementById('studentModal')).show();
    }

    async function viewStudent(id) {
        try {
            const response = await API.students.get(id);
            if (response.success) {
                document.getElementById('studentProfileContent').innerHTML = renderStudentProfile(response.data);
                new bootstrap.Modal(document.getElementById('viewStudentModal')).show();
            }
        } catch (error) {
            console.error('Error loading student:', error);
        }
    }

    function renderStudentProfile(student) {
        return `
        <div class="row">
            <div class="col-md-4 text-center">
                <img src="${student.photo || '/images/default-avatar.png'}" class="rounded-circle mb-3" style="width:150px;height:150px;object-fit:cover;">
                <h4>${escapeHtml(student.full_name)}</h4>
                <p class="text-muted">${student.admission_no}</p>
            </div>
            <div class="col-md-8">
                <h6>Personal Info</h6>
                <table class="table table-sm">
                    <tr><td>Class</td><td>${student.class_name || '-'}</td></tr>
                    <tr><td>Stream</td><td>${student.stream_name || '-'}</td></tr>
                    <tr><td>Gender</td><td>${student.gender}</td></tr>
                    <tr><td>DOB</td><td>${formatDate(student.dob)}</td></tr>
                    <tr><td>Type</td><td>${student.student_type}</td></tr>
                    <tr><td>Status</td><td>${student.status}</td></tr>
                </table>
                <h6>Parent/Guardian</h6>
                <table class="table table-sm">
                    <tr><td>Name</td><td>${student.parent_name || '-'}</td></tr>
                    <tr><td>Phone</td><td>${student.parent_phone || '-'}</td></tr>
                </table>
            </div>
        </div>
    `;
    }

    async function editStudent(id) {
        try {
            const response = await API.students.get(id);
            if (response.success) {
                const s = response.data;
                document.getElementById('studentModalTitle').textContent = 'Edit Student';
                document.getElementById('studentId').value = s.id;
                document.getElementById('firstName').value = s.first_name;
                document.getElementById('lastName').value = s.last_name;
                document.getElementById('admissionNo').value = s.admission_no;
                document.getElementById('dob').value = s.dob;
                document.getElementById('gender').value = s.gender;
                document.getElementById('classId').value = s.class_id;
                document.getElementById('streamId').value = s.stream_id || '';
                document.getElementById('studentType').value = s.student_type;
                document.getElementById('parentName').value = s.parent_name || '';
                document.getElementById('parentPhone').value = s.parent_phone || '';
                new bootstrap.Modal(document.getElementById('studentModal')).show();
            }
        } catch (error) {
            console.error('Error loading student for edit:', error);
        }
    }

    async function saveStudent() {
        const id = document.getElementById('studentId').value;
        const data = {
            first_name: document.getElementById('firstName').value,
            last_name: document.getElementById('lastName').value,
            admission_no: document.getElementById('admissionNo').value,
            dob: document.getElementById('dob').value,
            gender: document.getElementById('gender').value,
            class_id: document.getElementById('classId').value,
            stream_id: document.getElementById('streamId').value || null,
            student_type: document.getElementById('studentType').value,
            parent_name: document.getElementById('parentName').value,
            parent_phone: document.getElementById('parentPhone').value
        };

        try {
            const response = id ? await API.students.update(id, data) : await API.students.create(data);
            if (response.success) {
                bootstrap.Modal.getInstance(document.getElementById('studentModal')).hide();
                loadStudents();
                loadStats();
            } else {
                alert(response.message || 'Error saving student');
            }
        } catch (error) {
            console.error('Error saving student:', error);
        }
    }

    async function deleteStudent(id) {
        if (!confirm('Are you sure you want to delete this student?')) return;
        try {
            await API.students.delete(id);
            loadStudents();
            loadStats();
        } catch (error) {
            console.error('Error deleting student:', error);
        }
    }

    function transferStudent(id) { console.log('Transfer student:', id); }
    function graduateStudent(id) { console.log('Graduate student:', id); }
    function messageParent(id) { console.log('Message parent:', id); }
    function bulkExport() { console.log('Bulk export'); }
    function bulkMessage() { console.log('Bulk message'); }
    function bulkDelete() { console.log('Bulk delete'); }
    function exportStudents() { console.log('Export students'); }

    function formatDate(d) { return d ? new Date(d).toLocaleDateString() : '-'; }
    function formatNumber(n) { return n?.toLocaleString() || '0'; }
    function escapeHtml(s) { return s ? s.replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m]) : ''; }
    function debounce(fn, delay) { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), delay); }; }
</script>