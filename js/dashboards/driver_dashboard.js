/**
 * Driver Dashboard Controller
 * Role: Driver (ID 23)
 */
const driverDashboardController = {
    routeData: null,

    init: function () {
        if (typeof AuthContext !== 'undefined' && !AuthContext.isAuthenticated()) {
            window.location.href = (window.APP_BASE || '') + '/index.php';
            return;
        }
        this.loadAll();
    },

    refresh: function () { this.loadAll(); },

    loadAll: async function () {
        try {
            const [routeRes, vehicleRes] = await Promise.allSettled([
                API.transport.getMyRoute ? API.transport.getMyRoute() : fetch((window.APP_BASE || '') + '/api/transport/my-route', {headers: {'Authorization': 'Bearer ' + localStorage.getItem('token')}}).then(r => r.json()),
                fetch((window.APP_BASE || '') + '/api/transport/my-vehicle', {headers: {'Authorization': 'Bearer ' + localStorage.getItem('token')}}).then(r => r.json())
            ]);

            if (routeRes.status === 'fulfilled') {
                const res = routeRes.value;
                const route = res?.data || res;
                if (route) {
                    this.routeData = route;
                    this.renderRoute(route);
                }
            }
            if (vehicleRes.status === 'fulfilled') {
                const v = vehicleRes.value?.data || vehicleRes.value;
                if (v) this.renderVehicle(v);
            }
        } catch (e) {
            console.error('Driver dashboard error:', e);
        }
    },

    renderRoute: function (route) {
        const setText = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v || '—'; };
        setText('routeNameCard', route.name);
        setText('amPickup', route.am_pickup);
        setText('pmDropoff', route.pm_dropoff);

        const students = route.students || [];
        const stops = route.stops || [];
        const present = students.filter(s => s.present).length;
        setText('totalStudents', students.length);
        setText('totalStops', stops.length);
        setText('presentToday', present);

        this.renderStudents(students);
        this.renderStops(stops);
    },

    renderStudents: function (students) {
        const el = document.getElementById('studentAttendanceList');
        if (!el) return;
        if (!students.length) { el.innerHTML = '<div class="text-center text-muted py-4">No students assigned.</div>'; return; }
        el.innerHTML = students.map(s => `
            <div class="d-flex align-items-center gap-3 px-3 py-2 border-bottom">
                <input type="checkbox" class="form-check-input student-att-check" data-id="${s.id}" ${s.present ? 'checked' : ''} style="width:20px;height:20px">
                <label class="d-flex justify-content-between flex-fill mb-0">
                    <span class="fw-500">${this.esc(s.full_name)}</span>
                    <small class="text-muted">${this.esc(s.stop_name || '')}</small>
                </label>
            </div>`).join('');
    },

    renderStops: function (stops) {
        const el = document.getElementById('stopsList');
        if (!el) return;
        if (!stops.length) { el.innerHTML = '<p class="text-muted small">No stops.</p>'; return; }
        el.innerHTML = stops.map((s, i) => `
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-primary rounded-pill">${i + 1}</span>
                <span class="small flex-fill">${this.esc(s.name)}</span>
                <small class="text-muted">${s.time || ''}</small>
            </div>`).join('');
    },

    renderVehicle: function (v) {
        const setText = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val || '—'; };
        setText('vehicleReg', v.registration_no || v.reg_no);
        setText('vehicleModel', v.model || v.make);
        setText('vehicleCapacity', v.capacity ? v.capacity + ' seats' : null);
        const statusEl = document.getElementById('vehicleStatus');
        if (statusEl) {
            const s = (v.status || 'unknown').toLowerCase();
            statusEl.textContent = s;
            statusEl.className = 'badge bg-' + (s === 'active' ? 'success' : s === 'maintenance' ? 'warning' : 'secondary');
        }
    },

    saveAttendance: async function () {
        const present = Array.from(document.querySelectorAll('.student-att-check:checked')).map(cb => cb.dataset.id);
        try {
            await fetch((window.APP_BASE || '') + '/api/transport/attendance', {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'Authorization': 'Bearer ' + localStorage.getItem('token')},
                body: JSON.stringify({ students: present, date: new Date().toISOString().slice(0, 10) })
            });
            document.getElementById('presentToday').textContent = present.length;
            if (typeof showNotification === 'function') showNotification('Attendance saved!', 'success');
        } catch (e) {
            if (typeof showNotification === 'function') showNotification('Failed to save attendance.', 'error');
        }
    },

    navigate: function (route) {
        window.location.href = (window.APP_BASE || '') + '/home.php?route=' + route;
    },

    esc: function (s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }
};

document.addEventListener('DOMContentLoaded', () => driverDashboardController.init());
