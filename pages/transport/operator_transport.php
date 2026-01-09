<?php
/**
 * Transport - Operator Layout
 * For Drivers (view their assigned route and students)
 * 
 * Features:
 * - Mini sidebar
 * - 2 stat cards (students, stops)
 * - Simple list of students on route
 * - Mark attendance
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
            <a href="/pages/manage_transport.php" class="operator-nav-item active" data-tooltip="My Route">🚌</a>
        </nav>

        <div class="user-avatar" id="userAvatar">D</div>
    </aside>

    <!-- Main Content -->
    <main class="operator-main">
        <!-- Header -->
        <header class="operator-header">
            <h1 class="page-title">🚌 My Route</h1>
            <span class="route-indicator" id="routeName">Loading...</span>
        </header>

        <!-- Content -->
        <div class="operator-content">
            <!-- Stats - 2 columns -->
            <div class="operator-stats">
                <div class="operator-stat-card">
                    <div class="stat-icon">👨‍🎓</div>
                    <div class="stat-info">
                        <div class="stat-value" id="studentCount">0</div>
                        <div class="stat-label">Students</div>
                    </div>
                </div>
                <div class="operator-stat-card">
                    <div class="stat-icon">📍</div>
                    <div class="stat-info">
                        <div class="stat-value" id="stopCount">0</div>
                        <div class="stat-label">Stops</div>
                    </div>
                </div>
            </div>

            <!-- Route Schedule -->
            <div class="operator-schedule">
                <div class="schedule-item">
                    <span class="schedule-label">AM Pickup</span>
                    <span class="schedule-time" id="amPickup">--:--</span>
                </div>
                <div class="schedule-item">
                    <span class="schedule-label">PM Dropoff</span>
                    <span class="schedule-time" id="pmDropoff">--:--</span>
                </div>
            </div>

            <!-- Student List -->
            <div class="operator-table-card">
                <div class="operator-table-header">
                    <span class="table-title">Students on My Route</span>
                    <button class="btn btn-sm btn-success" id="markAttendanceBtn">✅ Mark Attendance</button>
                </div>

                <div class="student-list" id="studentList">
                    <!-- Data loaded dynamically -->
                </div>
            </div>

            <!-- Stops List -->
            <div class="operator-table-card">
                <div class="operator-table-header">
                    <span class="table-title">Route Stops</span>
                </div>

                <div class="stops-list" id="stopsList">
                    <!-- Data loaded dynamically -->
                </div>
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
            document.getElementById('userAvatar').textContent = (user.name || 'D').charAt(0).toUpperCase();
        }

        loadMyRoute();

        document.getElementById('markAttendanceBtn').addEventListener('click', markAttendance);
    });

    async function loadMyRoute() {
        try {
            const response = await API.transport.getMyRoute();
            if (response.success && response.data) {
                const route = response.data;
                document.getElementById('routeName').textContent = route.name;
                document.getElementById('studentCount').textContent = route.students?.length || 0;
                document.getElementById('stopCount').textContent = route.stops?.length || 0;
                document.getElementById('amPickup').textContent = route.am_pickup || '--:--';
                document.getElementById('pmDropoff').textContent = route.pm_dropoff || '--:--';

                renderStudentList(route.students || []);
                renderStopsList(route.stops || []);
            } else {
                document.getElementById('routeName').textContent = 'No route assigned';
            }
        } catch (error) {
            console.error('Error loading route:', error);
            document.getElementById('routeName').textContent = 'Error loading route';
        }
    }

    function renderStudentList(students) {
        const container = document.getElementById('studentList');
        container.innerHTML = '';

        if (students.length === 0) {
            container.innerHTML = '<div class="empty-list p-4 text-center text-muted">No students assigned</div>';
            return;
        }

        students.forEach(s => {
            const item = document.createElement('div');
            item.className = 'student-item';
            item.innerHTML = `
            <input type="checkbox" class="student-check" id="student_${s.id}" ${s.present ? 'checked' : ''}>
            <label for="student_${s.id}">
                <span class="student-name">${escapeHtml(s.full_name)}</span>
                <span class="student-stop">${escapeHtml(s.stop_name || '')}</span>
            </label>
        `;
            container.appendChild(item);
        });
    }

    function renderStopsList(stops) {
        const container = document.getElementById('stopsList');
        container.innerHTML = '';

        if (stops.length === 0) {
            container.innerHTML = '<div class="empty-list p-4 text-center text-muted">No stops defined</div>';
            return;
        }

        stops.forEach((stop, index) => {
            const item = document.createElement('div');
            item.className = 'stop-item';
            item.innerHTML = `
            <span class="stop-number">${index + 1}</span>
            <span class="stop-name">${escapeHtml(stop.name)}</span>
            <span class="stop-time">${stop.time || ''}</span>
        `;
            container.appendChild(item);
        });
    }

    async function markAttendance() {
        const present = [];
        document.querySelectorAll('.student-check:checked').forEach(cb => {
            present.push(cb.id.replace('student_', ''));
        });

        try {
            await API.transport.markAttendance({ students: present });
            alert('Attendance saved!');
        } catch (error) {
            console.error('Error saving attendance:', error);
        }
    }

    function escapeHtml(s) { return s ? s.replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m]) : ''; }
</script>

<style>
    /* Operator-specific transport styles */
    .operator-schedule {
        display: flex;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .schedule-item {
        flex: 1;
        background: white;
        border-radius: 8px;
        padding: 1rem;
        text-align: center;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
    }

    .schedule-label {
        display: block;
        font-size: 0.75rem;
        text-transform: uppercase;
        color: #666;
        margin-bottom: 0.25rem;
    }

    .schedule-time {
        font-size: 1.5rem;
        font-weight: 600;
        color: var(--green-700);
    }

    .student-item {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 0.75rem 1rem;
        border-bottom: 1px solid #f0f0f0;
    }

    .student-item:last-child {
        border-bottom: none;
    }

    .student-check {
        width: 20px;
        height: 20px;
        accent-color: var(--green-600);
    }

    .student-item label {
        flex: 1;
        display: flex;
        justify-content: space-between;
        cursor: pointer;
    }

    .student-name {
        font-weight: 500;
    }

    .student-stop {
        font-size: 0.85rem;
        color: #666;
    }

    .stop-item {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 0.75rem 1rem;
        border-bottom: 1px solid #f0f0f0;
    }

    .stop-number {
        width: 28px;
        height: 28px;
        background: var(--green-100);
        color: var(--green-700);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 0.85rem;
    }

    .stop-name {
        flex: 1;
        font-weight: 500;
    }

    .stop-time {
        font-size: 0.85rem;
        color: #666;
    }
</style>