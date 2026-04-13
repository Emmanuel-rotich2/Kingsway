/**
 * School Counselor / Chaplain Dashboard Controller
 * Role: Chaplain (ID 24)
 */
const counselorDashboardController = {
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

        const [stats, sessions, chapel] = await Promise.allSettled([
            get('/api/counseling/stats'),
            get('/api/counseling/sessions?limit=8&sort=recent'),
            get('/api/chapel/services?limit=5&upcoming=1')
        ]);

        if (stats.value) this.renderStats(stats.value?.data || stats.value);
        if (sessions.value) this.renderSessions(sessions.value?.data || sessions.value || []);
        if (chapel.value) this.renderChapel(chapel.value?.data || chapel.value || []);
    },

    renderStats: function (d) {
        const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v ?? 0; };
        set('sessionsThisWeek', d.sessions_this_week || d.sessions_week || 0);
        set('studentsSeen', d.students_seen || d.unique_students || 0);
        set('pendingReferrals', d.pending_referrals || d.referrals || 0);
        set('chapelServices', d.chapel_services || d.services_this_term || 0);
    },

    renderSessions: function (list) {
        const tbody = document.getElementById('sessionsTableBody');
        if (!tbody) return;
        if (!list.length) { tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3">No sessions recorded.</td></tr>'; return; }
        tbody.innerHTML = list.map(s => {
            const d = s.session_date || s.date;
            const dateStr = d ? new Date(d).toLocaleDateString('en-GB', {day:'numeric', month:'short'}) : '—';
            return `<tr>
                <td>${this.esc(s.student_name || s.student?.full_name || '—')}</td>
                <td><span class="badge bg-info text-dark">${this.esc(s.session_type || s.type || 'General')}</span></td>
                <td>${dateStr}</td>
                <td>${s.follow_up ? '<span class="badge bg-warning text-dark">Needed</span>' : '<span class="badge bg-success">None</span>'}</td>
            </tr>`;
        }).join('');
    },

    renderChapel: function (list) {
        const el = document.getElementById('chapelScheduleList');
        if (!el) return;
        if (!list.length) { el.innerHTML = '<div class="text-center text-muted py-3 small">No upcoming services.</div>'; return; }
        el.innerHTML = list.map(s => {
            const d = s.service_date || s.date;
            const dateStr = d ? new Date(d).toLocaleDateString('en-GB', {weekday:'short', day:'numeric', month:'short'}) : '—';
            return `<a href="#" class="list-group-item list-group-item-action py-2">
                <div class="d-flex justify-content-between">
                    <span class="small fw-semibold">${this.esc(s.title || s.theme || 'Chapel Service')}</span>
                    <small class="text-muted">${dateStr}</small>
                </div>
                <small class="text-muted">${s.time || ''} ${this.esc(s.venue || '')}</small>
            </a>`;
        }).join('');
    },

    showNewSessionModal: function () {
        this.navigate('student_counseling');
    },

    navigate: function (route) {
        window.location.href = (window.APP_BASE || '') + '/home.php?route=' + route;
    },

    esc: function (s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }
};

document.addEventListener('DOMContentLoaded', () => counselorDashboardController.init());
