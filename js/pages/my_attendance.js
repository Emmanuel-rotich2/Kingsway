/**
 * My HR — My Attendance. The signed-in staff member's own records (the
 * server limits staff history to self unless the viewer manages attendance),
 * with punctuality insights, monthly breakdown, approved leave, CSV and print.
 */
const MyAttendanceController = {
    staffId: null,
    records: [],
    leaveDays: 0,

    async init() {
        if (window.AuthContext?.ready) await window.AuthContext.ready();
        this.staffId = await this.resolveStaffId();
        if (!this.staffId) {
            document.getElementById('myAttendanceBody').innerHTML = '<tr><td colspan="6" class="text-center text-danger py-4">No staff profile is linked to your account.</td></tr>';
            return;
        }
        this.applyPeriod('month');
        document.getElementById('myAttendancePeriod')?.addEventListener('change', (e) => { this.applyPeriod(e.target.value); this.load(); });
        // Rule 6: the date range auto-loads on change.
        ['myAttendanceFrom', 'myAttendanceTo'].forEach((id) => document.getElementById(id)?.addEventListener('change', () => {
            document.getElementById('myAttendancePeriod').value = 'custom';
            this.load();
        }));
        document.getElementById('myAttendanceCsv')?.addEventListener('click', () => this.exportCsv());
        document.getElementById('myAttendancePrint')?.addEventListener('click', () => window.print());
        await this.load();
    },

    /** The login payload may not carry staff_id; the staff profile always does. */
    async resolveStaffId() {
        const user = window.AuthContext?.getUser?.() || {};
        if (user.staff_id) return Number(user.staff_id);
        try {
            const res = await window.API.apiCall('/staff/profile-get', 'GET', null, {}, { checkPermission: false });
            const p = res?.data || res || {};
            return p._domain === 'system' ? null : (Number(p.staff_id || p.id || 0) || null);
        } catch (_) { return null; }
    },

    applyPeriod(period) {
        const iso = (d) => new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
        const now = new Date();
        let from = new Date(now.getFullYear(), now.getMonth(), 1);
        let to = now;
        if (period === 'last_month') { from = new Date(now.getFullYear(), now.getMonth() - 1, 1); to = new Date(now.getFullYear(), now.getMonth(), 0); }
        if (period === 'term') from = new Date(now.getFullYear(), now.getMonth() - 2, 1);
        if (period === 'year') from = new Date(now.getFullYear(), 0, 1);
        if (period === 'custom') return;
        document.getElementById('myAttendanceFrom').value = iso(from);
        document.getElementById('myAttendanceTo').value = iso(to);
    },

    async load() {
        const body = document.getElementById('myAttendanceBody');
        body.innerHTML = '<tr><td colspan="6" class="text-center py-4"><span class="spinner-border spinner-border-sm"></span></td></tr>';
        const from = document.getElementById('myAttendanceFrom').value;
        const to = document.getElementById('myAttendanceTo').value;
        try {
            const params = { start_date: from, end_date: to };
            const [history, leave] = await Promise.all([
                API.attendance.getStaffHistory(this.staffId, params),
                API.staff.getLeaveRequests({}).catch(() => []),
            ]);
            this.records = Array.isArray(history) ? history : (history?.records || history?.data || []);
            const leaveRows = Array.isArray(leave) ? leave : (leave?.data || leave?.requests || []);
            this.leaveDays = leaveRows
                .filter((l) => String(l.status) === 'approved' && String(l.end_date || '') >= from && String(l.start_date || '') <= to)
                .reduce((sum, l) => sum + (Number(l.days_requested || l.days || 0) || 0), 0);
            this.render();
        } catch (e) {
            body.innerHTML = `<tr><td colspan="6" class="text-danger text-center py-4">${this.esc(e.message)}</td></tr>`;
        }
    },

    minutes(t) {
        const m = /^(\d{1,2}):(\d{2})/.exec(String(t || '').slice(-8).trim()) || /(\d{1,2}):(\d{2})/.exec(String(t || ''));
        return m ? Number(m[1]) * 60 + Number(m[2]) : null;
    },

    render() {
        const rows = [...this.records].sort((a, b) => String(a.date).localeCompare(String(b.date)));
        const count = (st) => rows.filter((r) => r.status === st).length;
        const present = count('present'), late = count('late'), absent = count('absent');
        const attended = present + late;
        const total = rows.length;
        const set = (id, v) => { document.getElementById(id).textContent = v; };
        set('myPresent', present); set('myLate', late); set('myAbsent', absent);
        set('myRate', total ? `${Math.round((attended / total) * 100)}%` : '—');
        set('myOnTime', attended ? `${Math.round((present / attended) * 100)}%` : '—');
        const ins = rows.map((r) => this.minutes(r.check_in)).filter((m) => m !== null);
        const avg = ins.length ? Math.round(ins.reduce((a, b) => a + b, 0) / ins.length) : null;
        set('myAvgIn', avg === null ? '—' : `${String(Math.floor(avg / 60)).padStart(2, '0')}:${String(avg % 60).padStart(2, '0')}`);

        // Longest run of attended days and the most recent late/absent day.
        let best = 0, run = 0;
        rows.forEach((r) => { run = ['present', 'late'].includes(r.status) ? run + 1 : 0; best = Math.max(best, run); });
        const lastMiss = [...rows].reverse().find((r) => r.status === 'absent');
        const lastLate = [...rows].reverse().find((r) => r.status === 'late');
        const weekday = {};
        rows.filter((r) => r.status === 'late').forEach((r) => { const d = new Date(`${r.date}T00:00:00`).toLocaleDateString(undefined, { weekday: 'long' }); weekday[d] = (weekday[d] || 0) + 1; });
        const lateDay = Object.entries(weekday).sort((a, b) => b[1] - a[1])[0];
        const tips = [];
        if (!total) tips.push('No attendance has been recorded for this period.');
        else {
            tips.push(`You attended <strong>${attended}</strong> of <strong>${total}</strong> recorded days.`);
            tips.push(`Your best run was <strong>${best}</strong> day${best === 1 ? '' : 's'} in a row.`);
            if (late) tips.push(`You were late <strong>${late}</strong> time${late === 1 ? '' : 's'}${lateDay ? `, most often on ${this.esc(lateDay[0])}` : ''}${lastLate ? ` (last: ${this.esc(lastLate.date)})` : ''}.`);
            else tips.push('You were on time every day you attended.');
            if (absent) tips.push(`You were absent <strong>${absent}</strong> day${absent === 1 ? '' : 's'}${lastMiss ? ` (last: ${this.esc(lastMiss.date)})` : ''}. Absence covered by approved leave should show as leave; speak to the office if a day is wrong.`);
        }
        tips.push(`Approved leave in this period: <strong>${this.leaveDays}</strong> day${this.leaveDays === 1 ? '' : 's'}.`);
        document.getElementById('myInsights').innerHTML = `<ul class="mb-0 ps-3">${tips.map((t) => `<li class="mb-1">${t}</li>`).join('')}</ul>`;

        const months = new Map();
        rows.forEach((r) => {
            const k = String(r.date || '').slice(0, 7);
            if (!months.has(k)) months.set(k, { days: 0, present: 0, late: 0, absent: 0 });
            const m = months.get(k); m.days++; if (m[r.status] !== undefined) m[r.status]++;
        });
        document.getElementById('myMonthly').innerHTML = months.size ? [...months].map(([k, m]) => {
            const label = new Date(`${k}-01T00:00:00`).toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
            return `<tr><td>${this.esc(label)}</td><td>${m.days}</td><td>${m.present}</td><td>${m.late}</td><td>${m.absent}</td><td>${m.days ? Math.round(((m.present + m.late) / m.days) * 100) : 0}%</td></tr>`;
        }).join('') : '<tr><td colspan="6" class="text-center text-muted py-3">No records.</td></tr>';

        const badge = (st) => st === 'present' ? 'success' : st === 'late' ? 'warning text-dark' : st === 'absent' ? 'danger' : 'secondary';
        document.getElementById('myAttendanceBody').innerHTML = rows.length ? [...rows].reverse().map((r) => `<tr><td>${this.esc(r.date)}</td><td>${this.esc(String(r.shift || 'full_day').replace(/_/g, ' '))}</td><td><span class="badge bg-${badge(r.status)}">${this.esc(r.status)}</span></td><td>${this.esc(r.check_in || '—')}</td><td>${this.esc(r.check_out || '—')}</td><td>${this.esc(r.notes || r.reason || '—')}</td></tr>`).join('')
            : '<tr><td colspan="6" class="text-center text-muted py-4">No attendance records found.</td></tr>';
    },

    async exportCsv() {
        const cell = (v) => { const t = String(v ?? ''); return /[",\r\n]/.test(t) ? `"${t.replace(/"/g, '""')}"` : t; };
        const lines = [['Date', 'Shift', 'Status', 'Check in', 'Check out', 'Notes'].join(',')]
            .concat(this.records.map((r) => [r.date, r.shift, r.status, r.check_in, r.check_out, r.notes || r.reason].map(cell).join(',')));
        try { await window.KingswayFileLifecycle.exportText(`﻿${lines.join('\r\n')}`, 'my_attendance.csv', 'text/csv;charset=utf-8'); }
        catch (e) { window.showNotification?.(e.message || 'Export failed', 'danger'); }
    },

    esc(v) {
        const d = document.createElement('div');
        d.textContent = String(v ?? '');
        return d.innerHTML;
    }
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => MyAttendanceController.init().catch(() => {}));
} else {
    MyAttendanceController.init().catch(() => {});
}

window.MyAttendanceController = MyAttendanceController;
