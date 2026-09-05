/**
 * Chaplain / Counselor Dashboard Controller
 * Uses the canonical CounselingAPI summary; no dashboard-specific service.
 */
(() => {
    const unwrap = (response) => {
        let value = response;
        for (let depth = 0; depth < 4; depth += 1) {
            if (value && typeof value === 'object' && !Array.isArray(value)
                && Object.prototype.hasOwnProperty.call(value, 'data')) {
                value = value.data;
                continue;
            }
            break;
        }
        return value;
    };

    const controller = DashboardBaseController.create({
        controllerName: 'ChaplainDashboardController',
        rootId: 'counselorDashboard',
        refreshButtonId: 'counselorDashboardRefresh',
        stateId: 'counselorDashboardState',
        scopeId: 'counselorDashboardScope',
        lastUpdatedId: 'counselorDashboardLastUpdated',
        defaultPeriod: 'week',

        async apiMethod(filters = {}) {
            const summary = unwrap(await window.API.counseling.getSummary(filters)) || {};
            const byType = Array.isArray(summary.by_type) ? summary.by_type : [];
            const trend = Array.isArray(summary.session_trend) ? summary.session_trend : [];
            const referralSources = Array.isArray(summary.referrals_by_source) ? summary.referrals_by_source : [];
            const byGrade = Array.isArray(summary.cases_by_grade) ? summary.cases_by_grade : [];

            return {
                meta: { scope_label: 'Student Wellbeing' },
                cards: {
                    open_cases: Number(summary.open_cases || summary.active || 0),
                    urgent_cases: Number(summary.urgent_cases || 0),
                    follow_ups_due: Number(summary.follow_ups_due || 0),
                    sessions_this_month: Number(summary.sessions_this_month || 0),
                    resolved_and_closed: Number(summary.completed || 0),
                    referrals: Number(summary.referrals || 0)
                },
                charts: {
                    by_type: {
                        labels: byType.map((row) => row.case_type || 'Other'),
                        data: byType.map((row) => Number(row.case_count || 0))
                    },
                    session_trend: {
                        labels: trend.map((row) => row.month || 'Unknown'),
                        data: trend.map((row) => Number(row.session_count || 0))
                    },
                    referral_sources: {
                        labels: referralSources.map((row) => row.source || 'Not specified'),
                        data: referralSources.map((row) => Number(row.case_count || 0))
                    },
                    by_grade: {
                        labels: byGrade.map((row) => row.grade || 'Unassigned'),
                        data: byGrade.map((row) => Number(row.case_count || 0))
                    }
                },
                tables: {
                    active_cases: Array.isArray(summary.active_cases) ? summary.active_cases : [],
                    follow_ups: Array.isArray(summary.follow_ups) ? summary.follow_ups : [],
                    recent_referrals: Array.isArray(summary.recent_referrals) ? summary.recent_referrals : [],
                    status_breakdown: Array.isArray(summary.by_status) ? summary.by_status : []
                },
                recent_sessions: Array.isArray(summary.recent_sessions) ? summary.recent_sessions : []
            };
        },

        cards: [
            { id: 'chpOpenCases', path: 'cards.open_cases', subtitleId: 'chpOpenCasesSub', subtitle: 'Open or in progress' },
            { id: 'chpUrgentCases', path: 'cards.urgent_cases', subtitleId: 'chpUrgentSub', subtitle: 'Urgent priority' },
            { id: 'chpFollowUps', path: 'cards.follow_ups_due', subtitleId: 'chpFollowUpsSub', subtitle: 'Due or overdue' },
            { id: 'chpSessionsWeek', path: 'cards.sessions_this_month', subtitleId: 'chpSessionsSub', subtitle: 'Recorded counseling sessions' },
            { id: 'chpResolvedMonth', path: 'cards.resolved_and_closed', subtitleId: 'chpResolvedSub', subtitle: 'Cases resolved or closed' },
            { id: 'chpReferrals', path: 'cards.referrals', subtitleId: 'chpReferralsSub', subtitle: 'This period' }
        ],
        chartDefinitions: [
            { id: 'chpTypeChart', path: 'charts.by_type', label: 'Cases', type: 'doughnut', showLegend: true },
            { id: 'chpTrendChart', path: 'charts.session_trend', label: 'Sessions', type: 'line' },
            { id: 'chpReferralChart', path: 'charts.referral_sources', label: 'Referrals', type: 'doughnut', showLegend: true },
            { id: 'chpGradeChart', path: 'charts.by_grade', label: 'Cases', type: 'bar' }
        ],
        tableDefinitions: [
            {
                bodyId: 'chpCasesBody',
                path: 'tables.active_cases',
                emptyText: 'No active counseling cases.',
                columns: [
                    { key: 'case_code' },
                    { key: 'counselee_name' },
                    { key: 'case_type' },
                    {
                        key: 'priority',
                        render: (value, row, instance) => instance.badge(value, {
                            low: 'secondary', medium: 'info', high: 'warning', urgent: 'danger'
                        })
                    },
                    {
                        key: 'status',
                        render: (value, row, instance) => instance.badge(value, {
                            open: 'danger', in_progress: 'warning', resolved: 'success', closed: 'secondary'
                        })
                    },
                    { key: 'next_follow_up_at', format: 'date' }
                ]
            },
            {
                bodyId: 'chpFollowUpBody',
                path: 'tables.follow_ups',
                emptyText: 'No follow-ups due.',
                columns: [
                    { key: 'counselee_name' },
                    { key: 'case_code' },
                    { key: 'case_type' },
                    { key: 'next_follow_up_at', format: 'date' },
                    {
                        key: 'status',
                        render: (value, row, instance) => instance.badge(value, {
                            open: 'danger', in_progress: 'warning', resolved: 'success', closed: 'secondary'
                        })
                    }
                ]
            },
            {
                bodyId: 'chpReferralsBody',
                path: 'tables.recent_referrals',
                emptyText: 'No referrals received in this period.',
                columns: [
                    { key: 'opened_at', format: 'date' },
                    { key: 'counselee_name' },
                    { key: 'referred_by' },
                    { key: 'title' },
                    {
                        key: 'status',
                        render: (value, row, instance) => instance.badge(value, {
                            open: 'danger', in_progress: 'warning', resolved: 'success', closed: 'secondary'
                        })
                    }
                ]
            },
            {
                bodyId: 'chpOutcomeBody',
                rows: (data) => {
                    const rows = (data.tables && Array.isArray(data.tables.status_breakdown))
                        ? data.tables.status_breakdown
                        : [];
                    const total = rows.reduce((sum, row) => sum + Number(row.case_count || 0), 0);
                    return rows.map((row) => ({
                        ...row,
                        percent: total > 0
                            ? `${((Number(row.case_count || 0) / total) * 100).toFixed(1)}%`
                            : '0.0%'
                    }));
                },
                emptyText: 'No case outcome data for this period.',
                columns: [
                    { key: 'status' },
                    { key: 'case_count', format: 'number' },
                    { key: 'percent' }
                ]
            }
        ],

        afterRender(data) {
            this.renderSessionTimeline(Array.isArray(data.recent_sessions) ? data.recent_sessions : []);
            this.renderWellbeingFlags();
            void this.loadChaplaincySummary();
        },

        async loadChaplaincySummary() {
            let summary = null;
            try {
                summary = unwrap(await window.API.chaplaincy.getDashboardSummary()) || {};
            } catch (error) {
                console.error('[ChaplainDashboardController] Chaplaincy summary failed:', error);
                return;
            }

            const cards = [
                ['chpSabbathWeek', 'chpSabbathWeekSub', summary.sabbath_services_this_week, 'this week'],
                ['chpSessionsWeek2', 'chpSessionsWeek2Sub', summary.program_sessions_this_week, 'this week'],
                ['chpAttendanceWeek', 'chpAttendanceWeekSub', summary.attendance_recorded_this_week, 'present marks']
            ];
            cards.forEach(([id, subId, value, sub]) => {
                this.setText(id, this.formatValue(Number(value || 0), 'number'));
                this.setText(subId, sub);
            });

            const membersCards = [
                ['chpActiveGroups', 'chpActiveGroupsSub', summary.active_groups, 'Pathfinders, AY, choirs…'],
                ['chpGroupMembers', 'chpGroupMembersSub', summary.active_group_members, 'students & staff'],
                ['chpPastoralOverdue', 'chpPastoralOverdueSub', summary.pastoral_followups_overdue, 'open / follow-up visits'],
                ['chpBaptized', 'chpBaptizedSub', summary.baptized_learners, 'spiritual profile']
            ];
            membersCards.forEach(([id, subId, value, sub]) => {
                this.setText(id, this.formatValue(Number(value || 0), 'number'));
                this.setText(subId, sub);
            });
        },

        renderSessionTimeline(sessions) {
            const timeline = document.getElementById('chpTimeline');
            if (!timeline) {
                return;
            }
            if (!sessions.length) {
                timeline.innerHTML = '<div class="text-center text-muted py-3"><i class="bi bi-inbox me-2"></i>No sessions recorded in this period.</div>';
                return;
            }
            timeline.innerHTML = sessions.map((session) => `
                <div class="dash-tl-item mb-3">
                    <div class="dash-tl-head d-flex justify-content-between align-items-center">
                        <strong>${this.escapeHtml(session.counselee_name || 'Anonymous')}</strong>
                        <span class="text-muted small">${this.escapeHtml(String(session.session_date || '').slice(0, 10))}</span>
                    </div>
                    <div class="small text-muted">
                        ${this.escapeHtml(session.session_type || 'Session')}
                        ${session.case_code ? `<span class="badge bg-light text-dark ms-2">${this.escapeHtml(session.case_code)}</span>` : ''}
                    </div>
                </div>
            `).join('');
        },

        renderWellbeingFlags() {
            const body = document.getElementById('chpWellbeingBody');
            if (!body) {
                return;
            }
            body.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4"><i class="bi bi-check2-circle me-2"></i>No wellbeing flags recorded.</td></tr>';
        }
    });

    window.ChaplainDashboardController = controller;
    window.counselorDashboardController = controller;
    DashboardBaseController.boot(controller, 'ChaplainDashboardController');
})();