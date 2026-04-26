/**
 * Deputy Head (Academic) Dashboard Controller
 * Dual role: loads MY TEACHING data + ACADEMIC ADMIN data in parallel.
 */
const deputyAcademicDashboard = {
    state: {
        cards: {},
        charts: {},
        tables: {},
        myTeaching: {},
        lastRefresh: null,
        isLoading: false,
    },
    _attendanceChart: null,
    _performanceChart: null,

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
        const el = document.getElementById('dhaGreeting');
        if (el) el.textContent = g + (name ? ', ' + name : '') + '!';
    },

    async loadDashboardData() {
        if (this.state.isLoading) return;
        this.state.isLoading = true;
        try {
            // Load admin KPIs and teaching data in parallel
            const [adminData, teachingData] = await Promise.all([
                callAPI('/academic/deputy-academic-summary').catch(() => null),
                callAPI('/academic/my-teaching-today').catch(() => null),
            ]);

            const a = adminData?.data || adminData || {};
            const t = teachingData?.data || teachingData || {};

            this.state.cards   = a.cards   || {};
            this.state.tables  = a.tables  || {};
            this.state.charts  = a.charts  || {};
            this.state.myTeaching = t;

            this._renderMyTeaching(t);
            this._renderAdminCards(a.cards || {});
            this._renderTables(a.tables || {});
            this._renderCharts(a.charts || {});

            this.state.lastRefresh = new Date();
            const el = document.getElementById('lastUpdated');
            if (el) el.textContent = this.state.lastRefresh.toLocaleTimeString();
        } catch (err) {
            console.error('Deputy academic dashboard failed:', err);
        } finally {
            this.state.isLoading = false;
        }
    },

    _renderMyTeaching(t) {
        this._setText('dhaMyClassLabel', t.class_name
            ? `Assigned class: ${t.class_name} · ${t.stream_name || ''}`
            : 'No class assignment this term');

        this._setText('dhaMyStudents', t.my_students ?? '—');
        this._setText('dhaMyAttendance', t.my_attendance_rate != null ? `${t.my_attendance_rate}%` : '—');
        this._setText('dhaAttendanceSub', t.my_attendance_rate != null
            ? `${t.my_present ?? 0} present / ${t.my_absent ?? 0} absent` : '');
        this._setText('dhaMyLessonsToday', t.my_lessons_today ?? '—');
        this._setText('dhaMyPendingPlans', t.my_pending_plans ?? '—');

        // Today's schedule chips
        const wrap = document.getElementById('dhaMySchedule');
        if (!wrap) return;
        const lessons = Array.isArray(t.today_schedule) ? t.today_schedule : [];
        if (!lessons.length) {
            wrap.innerHTML = '<span class="badge bg-secondary">No lessons scheduled today</span>';
            return;
        }
        wrap.innerHTML = lessons.map(l =>
            `<span class="badge" style="background:#e0e7ff;color:#3730a3;font-size:.8rem;padding:.4rem .75rem">
                <i class="bi bi-clock me-1"></i>${l.time || ''} &middot; ${l.subject || ''} &middot; ${l.class_name || ''}
            </span>`
        ).join('');
    },

    _renderAdminCards(c) {
        this._setText('pendingAdmissionsValue',  c.pending_admissions?.count ?? '—');
        this._setText('pendingAdmissionsDetail', c.pending_admissions?.details ?? 'Awaiting placement');
        this._setText('pendingLPReviewValue',    c.lesson_plans_pending_review ?? '—');
        this._setText('examSetupValue',          c.exams_scheduled ?? '—');
        this._setText('gradingPendingValue',     c.grading_pending ?? '—');
        this._setText('classSchedulesValue',     c.active_timetables ?? '—');
        this._setText('attendanceValue',         c.school_attendance != null ? `${c.school_attendance}%` : '—');
        this._setText('attendanceDetail',        c.school_attendance != null
            ? `${c.present ?? 0} present / ${c.absent ?? 0} absent` : '');
    },

    _renderTables(tables) {
        // Admissions
        const aBody = document.getElementById('admissionsTableBody');
        if (aBody) {
            const rows = tables.pending_admissions || [];
            aBody.innerHTML = rows.length
                ? rows.map(r => `<tr>
                    <td>${r.name || '—'}</td>
                    <td>${r.class || '—'}</td>
                    <td>${r.date || '—'}</td>
                    <td><span class="badge bg-warning text-dark">${r.status || '—'}</span></td>
                  </tr>`).join('')
                : '<tr><td colspan="4" class="text-center text-muted py-3">No pending applications</td></tr>';
        }

        // Lesson plan reviews
        const lpBody = document.getElementById('lpReviewTableBody');
        if (lpBody) {
            const rows = tables.lesson_plans_pending || [];
            lpBody.innerHTML = rows.length
                ? rows.map(r => `<tr>
                    <td>${r.teacher_name || '—'}</td>
                    <td>${r.class_name || '—'}</td>
                    <td>${r.subject || '—'}</td>
                    <td>${r.week_label || '—'}</td>
                    <td>
                        <a href="home.php?route=lesson_plan_approval&id=${r.id || ''}" class="btn btn-xs btn-sm btn-outline-warning py-0 px-2">
                            Review
                        </a>
                    </td>
                  </tr>`).join('')
                : '<tr><td colspan="5" class="text-center text-muted py-3">No lesson plans pending review</td></tr>';
        }

        // Events
        const events = document.getElementById('eventsList');
        if (events) {
            const evs = tables.upcoming_events || [];
            events.innerHTML = evs.length
                ? evs.map(e => `<li class="list-group-item d-flex justify-content-between align-items-center py-2">
                    <span class="small">${e.title || '—'}</span>
                    <small class="text-muted">${e.date || ''}</small>
                  </li>`).join('')
                : '<li class="list-group-item text-muted text-center py-3">No upcoming events</li>';
        }
    },

    _renderCharts(charts) {
        if (!window.Chart) return;

        // Attendance trend
        const attCtx = document.getElementById('academicAttendanceChart');
        if (attCtx && charts.attendance_trend) {
            this._attendanceChart?.destroy();
            this._attendanceChart = new Chart(attCtx, {
                type: 'line',
                data: {
                    labels: charts.attendance_trend.labels || [],
                    datasets: [{
                        label: 'Attendance %',
                        data: charts.attendance_trend.values || [],
                        borderColor: '#0d6efd',
                        backgroundColor: 'rgba(13,110,253,0.08)',
                        tension: 0.3,
                        fill: true,
                    }],
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { min: 0, max: 100, ticks: { callback: v => v + '%' } } },
                },
            });
        }

        // Class performance
        const perfCtx = document.getElementById('academicPerformanceChart');
        if (perfCtx && charts.class_performance) {
            this._performanceChart?.destroy();
            this._performanceChart = new Chart(perfCtx, {
                type: 'bar',
                data: {
                    labels: charts.class_performance.labels || [],
                    datasets: [{
                        label: 'Avg Score',
                        data: charts.class_performance.values || [],
                        backgroundColor: charts.class_performance.values?.map(v =>
                            v >= 75 ? '#16a34a' : v >= 60 ? '#2563eb' : v >= 40 ? '#d97706' : '#dc2626'
                        ) || '#16a34a',
                    }],
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
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

window.deputyAcademicDashboard = deputyAcademicDashboard;

document.addEventListener('DOMContentLoaded', () => deputyAcademicDashboard.init());
