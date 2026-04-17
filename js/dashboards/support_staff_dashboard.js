/**
 * Support Staff Dashboard Controller
 * Roles: Kitchen Staff (32), Security (33), Janitor (34)
 */
const supportStaffDashboardController = {
    init: function () {
        if (typeof AuthContext !== 'undefined' && !AuthContext.isAuthenticated()) {
            window.location.href = (window.APP_BASE || '') + '/index.php';
            return;
        }
        this.loadProfile();
        this.loadSchedule();
        this.loadAnnouncements();
    },

    refresh: function () { this.init(); },

    loadProfile: function () {
        try {
            const user = typeof AuthContext !== 'undefined' ? (AuthContext.getCurrentUser() || AuthContext.getUser()) : null;
            if (!user) return;
            const name = user.full_name || user.name || (user.first_name ? user.first_name + ' ' + (user.last_name || '') : '') || 'Staff';
            const initials = name.split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase();
            const setText = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v || '—'; };
            setText('staffFullName', name);
            setText('staffInitials', initials);
            const roles = typeof AuthContext !== 'undefined' ? AuthContext.getRoles() : [];
            setText('staffRoleName', roles.join(', ') || user.role_name || 'Staff');
            setText('staffPhone', user.phone || user.mobile || 'Not set');
            setText('staffEmail', user.email || 'Not set');
        } catch (e) { console.warn('Profile load error:', e); }
    },

    loadSchedule: async function () {
        const el = document.getElementById('todaySchedule');
        if (!el) return;
        try {
            const token = localStorage.getItem('token');
            const res = await fetch((window.APP_BASE || '') + '/api/staff/my-schedule', {
                headers: { 'Authorization': 'Bearer ' + token }
            }).then(r => r.json()).catch(() => null);

            const schedule = res?.data || res;
            if (!schedule || !Array.isArray(schedule) || !schedule.length) {
                el.innerHTML = '<p class="text-muted small mb-0 text-center">No schedule for today.</p>';
                return;
            }
            el.innerHTML = '<div class="list-group list-group-flush">' + schedule.map(s => `
                <div class="list-group-item py-2 d-flex justify-content-between align-items-center">
                    <div>
                        <span class="fw-semibold small">${this.esc(s.title || s.task || s.description)}</span>
                        <div class="text-muted small">${this.esc(s.location || s.venue || '')}</div>
                    </div>
                    <small class="text-muted">${s.start_time || s.time || ''}</small>
                </div>`).join('') + '</div>';
        } catch (e) {
            el.innerHTML = '<p class="text-muted small text-center mb-0">Schedule unavailable.</p>';
        }
    },

    loadAnnouncements: async function () {
        const el = document.getElementById('announcementsList');
        if (!el) return;
        try {
            const token = localStorage.getItem('token');
            const res = await fetch((window.APP_BASE || '') + '/api/announcements?limit=5&audience=staff', {
                headers: { 'Authorization': 'Bearer ' + token }
            }).then(r => r.json()).catch(() => null);

            const list = res?.data || res;
            if (!Array.isArray(list) || !list.length) {
                el.innerHTML = '<div class="text-center text-muted py-3 small">No recent announcements.</div>';
                return;
            }
            el.innerHTML = list.map(a => {
                const d = a.created_at || a.published_at || a.date;
                const dateStr = d ? new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' }) : '';
                return `<a href="#" class="list-group-item list-group-item-action py-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="small fw-semibold">${this.esc(a.title)}</span>
                        <small class="text-muted">${dateStr}</small>
                    </div>
                    <p class="text-muted small mb-0 text-truncate">${this.esc(a.message || a.content || '')}</p>
                </a>`;
            }).join('');
        } catch (e) {
            el.innerHTML = '<div class="text-center text-muted py-3 small">Announcements unavailable.</div>';
        }
    },

    navigate: function (route) {
        window.location.href = (window.APP_BASE || '') + '/home.php?route=' + route;
    },

    esc: function (s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }
};

document.addEventListener('DOMContentLoaded', function () {
    supportStaffDashboardController.init();
});
