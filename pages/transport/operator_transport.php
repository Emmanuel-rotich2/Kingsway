<?php
/**
 * Transport - Operator Layout
 * For Drivers (view their assigned route and students)
 * Features: 2 stat cards, student list, mark attendance, route stops
 */
/* PARTIAL — no DOCTYPE/html/head/body. Injected into app shell via fetch. */
?>

<!-- Stats - 2 columns -->
<div class="row mb-4">
    <div class="col-6">
        <div class="card shadow-sm border-0 text-center">
            <div class="card-body">
                <div class="fs-2">👨‍🎓</div>
                <h4 id="studentCount" class="mb-0">0</h4>
                <small class="text-muted">Students</small>
            </div>
        </div>
    </div>
    <div class="col-6">
        <div class="card shadow-sm border-0 text-center">
            <div class="card-body">
                <div class="fs-2">📍</div>
                <h4 id="stopCount" class="mb-0">0</h4>
                <small class="text-muted">Stops</small>
            </div>
        </div>
    </div>
</div>

<!-- Route Info -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white">
        <h6 class="mb-0"><i class="fas fa-route me-2"></i>My Route: <span id="routeName" class="text-primary">Loading...</span></h6>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-6 text-center">
                <small class="text-muted d-block">AM Pickup</small>
                <strong id="amPickup" class="text-success">--:--</strong>
            </div>
            <div class="col-6 text-center">
                <small class="text-muted d-block">PM Dropoff</small>
                <strong id="pmDropoff" class="text-warning">--:--</strong>
            </div>
        </div>
    </div>
</div>

<!-- Student List -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="fas fa-users me-2"></i>Students on My Route</h6>
        <button class="btn btn-sm btn-success" id="markAttendanceBtn">
            <i class="fas fa-check me-1"></i> Mark Attendance
        </button>
    </div>
    <div class="card-body p-0" id="studentList">
        <div class="text-center text-muted py-4">Loading students...</div>
    </div>
</div>

<!-- Stops List -->
<div class="card shadow-sm">
    <div class="card-header bg-white">
        <h6 class="mb-0"><i class="fas fa-map-marker-alt me-2"></i>Route Stops</h6>
    </div>
    <div class="card-body p-0" id="stopsList">
        <div class="text-center text-muted py-4">Loading stops...</div>
    </div>
</div>

<script>
(function () {
    document.addEventListener('DOMContentLoaded', function () {
        loadMyRoute();
        document.getElementById('markAttendanceBtn').addEventListener('click', markAttendance);
    });

    async function loadMyRoute() {
        try {
            const response = await API.transport.getMyRoute();
            if (response?.success && response.data) {
                const route = response.data;
                document.getElementById('routeName').textContent = route.name || 'My Route';
                document.getElementById('studentCount').textContent = route.students?.length || 0;
                document.getElementById('stopCount').textContent = route.stops?.length || 0;
                document.getElementById('amPickup').textContent = route.am_pickup || '--:--';
                document.getElementById('pmDropoff').textContent = route.pm_dropoff || '--:--';
                renderStudentList(route.students || []);
                renderStopsList(route.stops || []);
            } else {
                document.getElementById('routeName').textContent = 'No route assigned';
                document.getElementById('studentList').innerHTML = '<div class="text-center text-muted py-4">No route assigned to you.</div>';
            }
        } catch (error) {
            document.getElementById('routeName').textContent = 'Error loading route';
        }
    }

    function renderStudentList(students) {
        const container = document.getElementById('studentList');
        if (!students.length) {
            container.innerHTML = '<div class="text-center text-muted py-4">No students assigned.</div>';
            return;
        }
        container.innerHTML = students.map(function (s) {
            return `<div class="d-flex align-items-center gap-3 px-3 py-2 border-bottom">
                <input type="checkbox" class="form-check-input student-check" id="student_${s.id}" ${s.present ? 'checked' : ''} style="width:20px;height:20px">
                <label for="student_${s.id}" class="d-flex justify-content-between flex-fill cursor-pointer mb-0">
                    <span class="fw-500">${esc(s.full_name)}</span>
                    <small class="text-muted">${esc(s.stop_name || '')}</small>
                </label>
            </div>`;
        }).join('');
    }

    function renderStopsList(stops) {
        const container = document.getElementById('stopsList');
        if (!stops.length) {
            container.innerHTML = '<div class="text-center text-muted py-4">No stops defined.</div>';
            return;
        }
        container.innerHTML = stops.map(function (stop, i) {
            return `<div class="d-flex align-items-center gap-3 px-3 py-2 border-bottom">
                <span class="badge bg-success rounded-circle" style="width:28px;height:28px;line-height:20px">${i+1}</span>
                <span class="flex-fill fw-500">${esc(stop.name)}</span>
                <small class="text-muted">${stop.time || ''}</small>
            </div>`;
        }).join('');
    }

    async function markAttendance() {
        const present = Array.from(document.querySelectorAll('.student-check:checked'))
            .map(function (cb) { return cb.id.replace('student_', ''); });
        try {
            await API.transport.markAttendance({ students: present });
            if (typeof showNotification === 'function') {
                showNotification('Attendance saved!', 'success');
            } else {
                alert('Attendance saved!');
            }
        } catch (e) {
            alert('Failed to save attendance.');
        }
    }

    function esc(s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }
})();
</script>
