/**
 * My Routes Page Controller
 * For bus drivers: view assigned routes, manage trips, show today's schedule.
 */

(function () {
    "use strict";

    function showToast(message, type = 'success') {
        const el = document.createElement('div');
        el.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible position-fixed top-0 end-0 m-3`;
        el.style.zIndex = '9999';
        el.innerHTML = message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        document.body.appendChild(el);
        setTimeout(() => el.remove(), 4000);
    }

    function esc(str) {
        return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function formatTime(ts) {
        if (!ts) return '--';
        return new Date(ts).toLocaleTimeString('en-KE', { hour: '2-digit', minute: '2-digit' });
    }

    function todayStr() {
        return new Date().toISOString().slice(0, 10);
    }

    const Controller = {
        data: [],          // all routes for this driver
        activeTrip: null,  // currently active trip object
        driverId: null,

        init: async function () {
            if (!AuthContext.isAuthenticated()) {
                window.location.href = (window.APP_BASE || '') + '/index.php';
                return;
            }
            const user = AuthContext.getUser();
            this.driverId = user.staff_id || user.id || null;
            this.bindEvents();
            await this.loadData();
        },

        loadData: async function () {
            try {
                const [routesRes, vehiclesRes] = await Promise.all([
                    window.API.transport.getRoutes({ driver_id: this.driverId }),
                    window.API.transport.getVehicles()
                ]);
                const allRoutes = (routesRes && routesRes.data) ? routesRes.data : (Array.isArray(routesRes) ? routesRes : []);
                // Filter routes assigned to this driver
                this.data = allRoutes.filter(r =>
                    !this.driverId ||
                    String(r.driver_id) === String(this.driverId) ||
                    String(r.staff_id)  === String(this.driverId)
                );
                this.vehicles = (vehiclesRes && vehiclesRes.data) ? vehiclesRes.data : (Array.isArray(vehiclesRes) ? vehiclesRes : []);
                this.render();
            } catch (error) {
                console.error('My routes load error:', error);
                showToast('Failed to load route data.', 'error');
            }
        },

        render: function () {
            this.renderStats();
            this.renderRoutesTable();
            this.renderTodaySchedule();
        },

        renderStats: function () {
            const routes = this.data;
            const today = todayStr();
            const tripsToday = routes.filter(r => {
                const trips = r.trips || [];
                return trips.some(t => (t.trip_date || '').startsWith(today));
            }).length;
            const students = routes.reduce((s, r) => s + Number(r.student_count || r.students_count || 0), 0);
            const weekKm = routes.reduce((s, r) => s + Number(r.weekly_km || r.distance_km || 0), 0);

            const setText = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
            setText('assignedRoutes',   routes.length);
            setText('tripsToday',       tripsToday);
            setText('studentsAssigned', students);
            setText('kmThisWeek',       weekKm > 0 ? weekKm.toFixed(1) + ' km' : '--');
        },

        renderRoutesTable: function () {
            const tbody = document.querySelector('#routesTable');
            if (!tbody) return;

            if (!this.data.length) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No routes assigned.</td></tr>';
                return;
            }

            tbody.innerHTML = this.data.map(r => {
                const typeBadge = r.route_type === 'morning' ? 'bg-info' :
                                  r.route_type === 'evening' ? 'bg-warning text-dark' : 'bg-secondary';
                return `<tr>
                    <td><strong>${esc(r.route_name || r.name)}</strong></td>
                    <td><span class="badge ${typeBadge}">${esc(r.route_type || 'N/A')}</span></td>
                    <td>${esc(r.student_count || r.students_count || 0)}</td>
                    <td>${esc(r.distance_km ? r.distance_km + ' km' : '--')}</td>
                    <td>${esc(r.schedule || r.departure_time || '--')}</td>
                    <td class="text-nowrap">
                        <button class="btn btn-sm btn-outline-primary view-route-btn me-1" data-id="${esc(r.id)}">View</button>
                        <button class="btn btn-sm btn-success start-trip-btn" data-id="${esc(r.id)}" data-name="${esc(r.route_name || r.name)}">Start Trip</button>
                    </td>
                </tr>`;
            }).join('');

            this.bindTableActions();
        },

        renderTodaySchedule: function () {
            const tbody = document.querySelector('#scheduleBody');
            if (!tbody) return;
            const today = todayStr();
            const schedule = [];
            this.data.forEach(r => {
                const time = r.departure_time || r.schedule || '';
                schedule.push({
                    time,
                    route: r.route_name || r.name || '',
                    type: r.route_type || '',
                    stops: r.stops_count || (r.pickup_points || []).length || '--',
                    students: r.student_count || r.students_count || 0
                });
            });
            schedule.sort((a, b) => a.time.localeCompare(b.time));

            if (!schedule.length) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">No schedule for today.</td></tr>';
                return;
            }
            tbody.innerHTML = schedule.map(s => `<tr>
                <td>${esc(s.time || '--')}</td>
                <td>${esc(s.route)}</td>
                <td>${esc(s.type)}</td>
                <td>${esc(s.stops)}</td>
                <td>${esc(s.students)}</td>
            </tr>`).join('');
        },

        updateActiveTripUI: function () {
            const card = document.getElementById('currentTripCard');
            if (!card) return;
            if (this.activeTrip) {
                card.classList.remove('d-none');
                const nameEl = document.getElementById('activeTripRoute');
                const timeEl = document.getElementById('tripStartTime');
                if (nameEl) nameEl.textContent = this.activeTrip.routeName || '';
                if (timeEl) timeEl.textContent = formatTime(this.activeTrip.startTime);
            } else {
                card.classList.add('d-none');
            }
        },

        startTrip: function (routeId, routeName) {
            if (this.activeTrip) {
                showToast('End your current trip before starting a new one.', 'warning');
                return;
            }
            this.activeTrip = { routeId, routeName, startTime: new Date().toISOString() };
            this.updateActiveTripUI();
            showToast(`Trip started: ${routeName}`);
        },

        endTrip: async function () {
            if (!this.activeTrip) return;
            const endTime = new Date().toISOString();
            try {
                // Attempt to persist trip record via transport index (best-effort)
                await window.API.transport.index({
                    action:     'end_trip',
                    route_id:   this.activeTrip.routeId,
                    driver_id:  this.driverId,
                    start_time: this.activeTrip.startTime,
                    end_time:   endTime
                });
            } catch (e) {
                console.warn('Trip record save failed (non-critical):', e);
            }
            showToast(`Trip ended for route: ${this.activeTrip.routeName}`);
            this.activeTrip = null;
            this.updateActiveTripUI();
            await this.loadData();
        },

        viewRouteDetails: async function (routeId) {
            const route = this.data.find(r => String(r.id) === String(routeId));
            if (!route) return;
            try {
                const detail = await window.API.transport.getRoute(routeId);
                const r = (detail && detail.data) ? detail.data : (detail || route);
                this.populateRouteModal(r);
                const modalEl = document.getElementById('routeDetailsModal');
                if (modalEl && window.bootstrap) new window.bootstrap.Modal(modalEl).show();
            } catch (err) {
                console.error('Route detail error:', err);
                this.populateRouteModal(route);
                const modalEl = document.getElementById('routeDetailsModal');
                if (modalEl && window.bootstrap) new window.bootstrap.Modal(modalEl).show();
            }
        },

        populateRouteModal: function (r) {
            const setText = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val || '--'; };
            setText('routeName',     r.route_name || r.name);
            setText('routeType',     r.route_type);
            setText('distance',      r.distance_km ? r.distance_km + ' km' : '--');
            setText('totalStudents', r.student_count || r.students_count || 0);
            setText('schedule',      r.schedule || r.departure_time || '--');

            // Vehicle name
            const vehicle = this.vehicles.find(v => String(v.id) === String(r.vehicle_id));
            setText('vehicle', vehicle ? (vehicle.registration || vehicle.reg_number || vehicle.name) : (r.vehicle || '--'));

            // Pickup points
            const ppTbody = document.querySelector('#pickupPointsBody');
            if (ppTbody) {
                const points = r.pickup_points || [];
                ppTbody.innerHTML = points.length
                    ? points.map((p, i) => `<tr>
                        <td>${i + 1}</td>
                        <td>${esc(p.name || p.location || p)}</td>
                        <td>${esc(p.time || '--')}</td>
                        <td>${esc(p.students_count || '--')}</td>
                      </tr>`).join('')
                    : '<tr><td colspan="4" class="text-muted text-center">No pickup points recorded.</td></tr>';
            }

            // Students list
            const stTbody = document.querySelector('#studentsListBody');
            if (stTbody) {
                const students = r.students || [];
                stTbody.innerHTML = students.length
                    ? students.map(s => `<tr>
                        <td>${esc(s.name || s.student_name)}</td>
                        <td>${esc(s.class || s.grade || '--')}</td>
                        <td>${esc(s.pickup_point || '--')}</td>
                      </tr>`).join('')
                    : '<tr><td colspan="3" class="text-muted text-center">No students listed.</td></tr>';
            }
        },

        bindEvents: function () {
            const startBtn = document.getElementById('startTripBtn');
            if (startBtn) {
                startBtn.addEventListener('click', () => {
                    if (this.data.length === 1) {
                        this.startTrip(this.data[0].id, this.data[0].route_name || this.data[0].name);
                    } else {
                        showToast('Select a specific route from the table to start a trip.', 'warning');
                    }
                });
            }
            const endBtn = document.getElementById('endTripBtn');
            if (endBtn) endBtn.addEventListener('click', () => this.endTrip());
        },

        bindTableActions: function () {
            document.querySelectorAll('.view-route-btn').forEach(btn => {
                btn.addEventListener('click', () => this.viewRouteDetails(btn.dataset.id));
            });
            document.querySelectorAll('.start-trip-btn').forEach(btn => {
                btn.addEventListener('click', () => this.startTrip(btn.dataset.id, btn.dataset.name));
            });
        }
    };

    document.addEventListener('DOMContentLoaded', () => Controller.init());
})();
