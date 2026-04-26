/**
 * Deputy Head (Discipline) Dashboard Controller
 * Dual role: loads MY TEACHING data + DISCIPLINE ADMIN data in parallel.
 */
const deputyDisciplineDashboard = {
    state: {
        cards: {},
        charts: {},
        tables: {},
        myTeaching: {},
        lastRefresh: null,
        isLoading: false,
    },
    _disciplineChart: null,
    _attendanceChart: null,

    init() {
        this._greet();
        this.loadDashboardData();
        document.getElementById('refreshDashboard')
            ?.addEventListener('click', () => this.loadDashboardData());
    },

    _greet() {
        const user = (typeof AuthContext !== 'undefined') ? AuthContext.getUser() : null;
        if (!user) return;
        const hr = new Date().getHours();
        const g = hr < 12 ? 'Good morning' : hr < 17 ? 'Good afternoon' : 'Good evening';
        const name = user.first_name || user.name || '';
        const el = document.getElementById('dhdGreeting');
        if (el) el.textContent = g + (name ? ', ' + name : '') + '!';
    },

    async loadDashboardData() {
        if (this.state.isLoading) return;
        this.state.isLoading = true;
        try {
            const [adminData, teachingData] = await Promise.all([
                callAPI('/academic/deputy-discipline-summary').catch(() => null),
                callAPI('/academic/my-teaching-today').catch(() => null),
            ]);

            const a = adminData?.data || adminData || {};
            const t = teachingData?.data || teachingData || {};

            this.state.cards      = a.cards   || {};
            this.state.tables     = a.tables  || {};
            this.state.charts     = a.charts  || {};
            this.state.myTeaching = t;

            this._renderMyTeaching(t);
            this._renderAdminCards(a.cards || {});
            this._renderTables(a.tables || {});
            this._renderCharts(a.charts || {});

            this.state.lastRefresh = new Date();
            const el = document.getElementById('lastUpdated');
            if (el) el.textContent = this.state.lastRefresh.toLocaleTimeString();
        } catch (err) {
            console.error('Deputy discipline dashboard failed:', err);
        } finally {
            this.state.isLoading = false;
        }
    },

    _renderMyTeaching(t) {
        this._setText('dhdMyClassLabel', t.class_name
            ? `Assigned class: ${t.class_name} · ${t.stream_name || ''}`
            : 'No class assignment this term');

        this._setText('dhdMyStudents', t.my_students ?? '—');
        this._setText('dhdMyAttendance', t.my_attendance_rate != null ? `${t.my_attendance_rate}%` : '—');
        this._setText('dhdAttendanceSub', t.my_attendance_rate != null
            ? `${t.my_present ?? 0} present / ${t.my_absent ?? 0} absent` : '');
        this._setText('dhdMyLessonsToday', t.my_lessons_today ?? '—');
        this._setText('dhdMyPendingPlans', t.my_pending_plans ?? '—');

        const wrap = document.getElementById('dhdMySchedule');
        if (!wrap) return;
        const lessons = Array.isArray(t.today_schedule) ? t.today_schedule : [];
        if (!lessons.length) {
            wrap.innerHTML = '<span class="badge bg-secondary">No lessons scheduled today</span>';
            return;
        }
        wrap.innerHTML = lessons.map(l =>
            `<span class="badge" style="background:#fee2e2;color:#991b1b;font-size:.8rem;padding:.4rem .75rem">
                <i class="bi bi-clock me-1"></i>${l.time || ''} &middot; ${l.subject || ''} &middot; ${l.class_name || ''}
            </span>`
        ).join('');
    },

    _renderAdminCards(c) {
        this._setText('disciplineCasesValue', c.open_cases ?? '—');
        this._setText('disciplineDetail',     c.open_cases_detail ?? 'Active investigations');
        this._setText('suspensionsValue',     c.suspensions_this_term ?? '—');
        this._setText('truancyCasesValue',    c.truancy_cases ?? '—');
        this._setText('parentMeetingsValue',  c.parent_meetings_pending ?? '—');
        this._setText('counselingReferrals',  c.counseling_referrals_open ?? '—');
        this._setText('attendanceValue',      c.school_attendance != null ? `${c.school_attendance}%` : '—');
        this._setText('attendanceDetail',     c.school_attendance != null
            ? `${c.present ?? 0} present / ${c.absent ?? 0} absent` : '');
    },

    _renderTables(tables) {
        // Discipline cases
        const dBody = document.getElementById('disciplineTableBody');
        if (dBody) {
            const rows = tables.discipline_cases || [];
            const statusColors = { open: 'danger', investigating: 'warning', resolved: 'success', closed: 'secondary' };
            dBody.innerHTML = rows.length
                ? rows.map(r => `<tr>
                    <td>${r.student || '—'}</td>
                    <td>${r.class || '—'}</td>
                    <td>${r.issue || r.offence || '—'}</td>
                    <td>${r.date || '—'}</td>
                    <td><span class="badge bg-${statusColors[r.status] || 'secondary'}">${r.status || '—'}</span></td>
                  </tr>`).join('')
                : '<tr><td colspan="5" class="text-center text-muted py-3">No open cases</td></tr>';
        }

        // Parent meetings
        const pmList = document.getElementById('parentMeetingsList');
        if (pmList) {
            const meetings = tables.parent_meetings || [];
            pmList.innerHTML = meetings.length
                ? meetings.map(m => `<div class="list-group-item list-group-item-action py-2">
                    <div class="d-flex justify-content-between">
                        <span class="small fw-semibold">${m.parent_name || '—'}</span>
                        <small class="text-muted">${m.meeting_date || ''}</small>
                    </div>
                    <small class="text-muted">Re: ${m.student_name || ''} &mdash; ${m.reason || ''}</small>
                  </div>`).join('')
                : '<div class="list-group-item text-muted text-center py-3">No pending meetings</div>';
        }

        // Events
        const events = document.getElementById('eventsList');
        if (events) {
            const evs = tables.upcoming_events || [];
            events.innerHTML = evs.length
                ? evs.map(e => `<div class="list-group-item d-flex justify-content-between py-2">
                    <span class="small">${e.title || '—'}</span>
                    <small class="text-muted">${e.date || ''}</small>
                  </div>`).join('')
                : '<div class="list-group-item text-muted text-center py-3">No upcoming events</div>';
        }
    },

    _renderCharts(charts) {
        if (!window.Chart) return;

        const discCtx = document.getElementById('disciplineTrendChart');
        if (discCtx && charts.discipline_trend) {
            this._disciplineChart?.destroy();
            this._disciplineChart = new Chart(discCtx, {
                type: 'bar',
                data: {
                    labels: charts.discipline_trend.labels || [],
                    datasets: [{
                        label: 'Cases',
                        data: charts.discipline_trend.values || [],
                        backgroundColor: 'rgba(220,53,69,0.75)',
                        borderColor: '#dc3545',
                        borderWidth: 1,
                    }],
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
                },
            });
        }

        const attCtx = document.getElementById('disciplineAttendanceChart');
        if (attCtx && charts.attendance_trend) {
            this._attendanceChart?.destroy();
            this._attendanceChart = new Chart(attCtx, {
                type: 'line',
                data: {
                    labels: charts.attendance_trend.labels || [],
                    datasets: [
                        {
                            label: 'Present %',
                            data: charts.attendance_trend.present || [],
                            borderColor: '#16a34a',
                            backgroundColor: 'rgba(22,163,74,0.08)',
                            tension: 0.3,
                            fill: true,
                        },
                        {
                            label: 'Absent %',
                            data: charts.attendance_trend.absent || [],
                            borderColor: '#dc2626',
                            backgroundColor: 'rgba(220,38,38,0.08)',
                            tension: 0.3,
                            fill: true,
                        },
                    ],
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: true, position: 'top' } },
                    scales: { y: { min: 0, max: 100, ticks: { callback: v => v + '%' } } },
                },
            });
        }
    },

    _setText(id, val) {
        const el = document.getElementById(id);
        if (el) el.textContent = val ?? '—';
    },
};

window.deputyDisciplineDashboard = deputyDisciplineDashboard;

document.addEventListener('DOMContentLoaded', () => deputyDisciplineDashboard.init());
