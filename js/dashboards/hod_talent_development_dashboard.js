/**
 * HOD Talent Development Dashboard Controller
 * Role: HOD Talent Development (ID 21)
 */
const hodDashboardController = {
    init: function () {
        if (typeof AuthContext !== 'undefined' && !AuthContext.isAuthenticated()) {
            window.location.href = (window.APP_BASE || '') + '/index.php';
            return;
        }
        this.loadAll();
    },

    refresh: function () { this.loadAll(); },

    loadAll: async function () {
        const token = localStorage.getItem('token');
        const h = { 'Authorization': 'Bearer ' + token };
        const get = url => fetch((window.APP_BASE || '') + url, { headers: h }).then(r => r.json()).catch(() => null);

        const [stats, activities, events] = await Promise.allSettled([
            get('/api/activities/stats'),
            get('/api/activities?limit=8&status=active'),
            get('/api/events?limit=5&upcoming=1')
        ]);

        if (stats.value) this.renderStats(stats.value?.data || stats.value);
        if (activities.value) this.renderActivities(activities.value?.data || activities.value || []);
        if (events.value) this.renderEvents(events.value?.data || events.value || []);
    },

    renderStats: function (d) {
        const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v ?? 0; };
        set('activeActivities', d.active_activities || d.total_activities || 0);
        set('studentsEnrolled', d.students_enrolled || d.total_participants || 0);
        set('upcomingEvents', d.upcoming_events || 0);
        set('awardsThisTerm', d.awards || d.awards_this_term || 0);
    },

    renderActivities: function (list) {
        const tbody = document.getElementById('activitiesTableBody');
        if (!tbody) return;
        if (!list.length) { tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">No active activities.</td></tr>'; return; }
        tbody.innerHTML = list.map(a => {
            const status = a.status || 'active';
            return `<tr>
                <td><strong>${this.esc(a.name || a.title)}</strong></td>
                <td><span class="badge bg-warning text-dark">${this.esc(a.category || '—')}</span></td>
                <td>${a.participant_count || a.participants || 0}</td>
                <td>${this.esc(a.coach_name || a.teacher_name || '—')}</td>
                <td><span class="badge bg-${status === 'active' ? 'success' : 'secondary'}">${status}</span></td>
            </tr>`;
        }).join('');
    },

    renderEvents: function (list) {
        const el = document.getElementById('upcomingEventsList');
        if (!el) return;
        if (!list.length) { el.innerHTML = '<div class="text-center text-muted py-3 small">No upcoming events.</div>'; return; }
        el.innerHTML = list.map(e => {
            const date = e.event_date || e.date;
            const d = date ? new Date(date).toLocaleDateString('en-GB', {day:'numeric', month:'short'}) : '—';
            return `<a href="#" class="list-group-item list-group-item-action py-2">
                <div class="d-flex justify-content-between">
                    <span class="small fw-semibold">${this.esc(e.name || e.title)}</span>
                    <small class="text-muted">${d}</small>
                </div>
                <small class="text-muted">${this.esc(e.venue || e.location || '')}</small>
            </a>`;
        }).join('');
    },

    navigate: function (route) {
        window.location.href = (window.APP_BASE || '') + '/home.php?route=' + route;
    },

    esc: function (s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }
};

document.addEventListener('DOMContentLoaded', () => hodDashboardController.init());
