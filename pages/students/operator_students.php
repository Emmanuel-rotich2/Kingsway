<?php
/**
 * Students - Operator Layout
 * Minimal layout for Class Teachers, Subject Teachers
 * 
 * Features:
 * - Mini sidebar (icons only)
 * - 2 stat cards
 * - No charts
 * - Essential table (4 columns)
 * - View only (can only see own class students)
 */
?>

<link rel="stylesheet" href="/css/school-theme.css">
<link rel="stylesheet" href="/css/roles/operator-theme.css">

<div class="operator-layout">
    <!-- Mini Sidebar -->
    <aside class="operator-sidebar" id="operatorSidebar">
        <div class="logo-section">
            <img src="/images/logo.png" alt="KA">
        </div>

        <nav class="operator-nav">
            <a href="/pages/dashboard.php" class="operator-nav-item" data-tooltip="Dashboard">🏠</a>
            <a href="/pages/all_students.php" class="operator-nav-item active" data-tooltip="My Students">👨‍🎓</a>
            <a href="/pages/my_classes.php" class="operator-nav-item" data-tooltip="My Classes">📚</a>
            <a href="/pages/manage_communications.php" class="operator-nav-item" data-tooltip="Messages">📧</a>
        </nav>

        <div class="user-avatar" id="userAvatar">T</div>
    </aside>

    <!-- Main Content -->
    <main class="operator-main">
        <!-- Header -->
        <header class="operator-header">
            <h1 class="page-title">👨‍🎓 My Students</h1>
            <div class="class-indicator">
                <span id="currentClass">Loading...</span>
            </div>
        </header>

        <!-- Content -->
        <div class="operator-content">
            <!-- Stats - 2 columns -->
            <div class="operator-stats">
                <div class="operator-stat-card">
                    <div class="stat-icon">👨‍🎓</div>
                    <div class="stat-info">
                        <div class="stat-value" id="totalStudents">0</div>
                        <div class="stat-label">Students</div>
                    </div>
                </div>
                <div class="operator-stat-card">
                    <div class="stat-icon">✅</div>
                    <div class="stat-info">
                        <div class="stat-value" id="presentToday">0</div>
                        <div class="stat-label">Present Today</div>
                    </div>
                </div>
            </div>

            <!-- Search -->
            <div class="operator-filters">
                <input type="text" class="search-input form-control" id="searchStudent"
                    placeholder="Search students...">
            </div>

            <!-- Table - 4 essential columns -->
            <div class="operator-table-card">
                <div class="operator-table-header">
                    <span class="table-title">Class Students</span>
                </div>

                <table class="operator-data-table" id="studentsTable">
                    <thead>
                        <tr>
                            <th>Adm No</th>
                            <th>Name</th>
                            <th>Gender</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="studentsTableBody">
                        <!-- Data loaded dynamically -->
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<script src="/js/components/RoleBasedUI.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        RoleBasedUI.applyLayout();

        const user = AuthContext.getUser();
        if (user) {
            document.getElementById('userAvatar').textContent = (user.name || 'T').charAt(0).toUpperCase();
        }

        loadMyClassStudents();

        document.getElementById('searchStudent').addEventListener('input', debounce(filterStudents, 300));
    });

    async function loadMyClassStudents() {
        try {
            // Load students from teacher's assigned class only
            const response = await API.students.getMyClass();
            if (response.success) {
                document.getElementById('currentClass').textContent = response.class_name || 'My Class';
                document.getElementById('totalStudents').textContent = response.data.length;
                renderStudentsTable(response.data);
            }
        } catch (error) {
            console.error('Error loading students:', error);
            document.getElementById('studentsTableBody').innerHTML = '<tr><td colspan="4" class="text-center p-4">Unable to load students</td></tr>';
        }
    }

    function renderStudentsTable(students) {
        const tbody = document.getElementById('studentsTableBody');
        tbody.innerHTML = '';

        if (students.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center p-4">No students in your class</td></tr>';
            return;
        }

        students.forEach(student => {
            const row = document.createElement('tr');
            row.innerHTML = `
            <td>${escapeHtml(student.admission_no)}</td>
            <td><strong>${escapeHtml(student.full_name)}</strong></td>
            <td>${student.gender === 'male' ? '♂ Male' : '♀ Female'}</td>
            <td class="operator-row-actions">
                <button class="action-btn" onclick="viewStudent(${student.id})">View</button>
            </td>
        `;
            tbody.appendChild(row);
        });
    }

    function filterStudents() {
        const search = document.getElementById('searchStudent').value.toLowerCase();
        document.querySelectorAll('#studentsTableBody tr').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(search) ? '' : 'none';
        });
    }

    function viewStudent(id) {
        console.log('View student:', id);
    }

    function escapeHtml(s) { return s ? s.replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m]) : ''; }
    function debounce(fn, d) { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), d); }; }
</script>