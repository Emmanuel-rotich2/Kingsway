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
        rootId: 'chaplainDashboard',
        refreshButtonId: 'chaplainDashboardRefresh',
        stateId: 'chaplainDashboardState',
        scopeId: 'chaplainDashboardScope',
        lastUpdatedId: 'chaplainDashboardLastUpdated',

        async apiMethod() {
            const summary = unwrap(await window.API.counseling.getSummary()) || {};
            const byType = Array.isArray(summary.by_type) ? summary.by_type : [];
            const trend = Array.isArray(summary.session_trend) ? summary.session_trend : [];

            return {
                meta: { scope_label: 'Student Wellbeing' },
                cards: {
                    open_cases: Number(summary.open_cases || summary.active || 0),
                    urgent_cases: Number(summary.urgent_cases || 0),
                    follow_ups_due: Number(summary.follow_ups_due || 0),
                    sessions_this_month: Number(summary.sessions_this_month || 0)
                },
                charts: {
                    by_type: {
                        labels: byType.map((row) => row.case_type || 'Other'),
                        data: byType.map((row) => Number(row.case_count || 0))
                    },
                    session_trend: {
                        labels: trend.map((row) => row.month || 'Unknown'),
                        data: trend.map((row) => Number(row.session_count || 0))
                    }
                },
                tables: {
                    active_cases: Array.isArray(summary.active_cases) ? summary.active_cases : [],
                    follow_ups: Array.isArray(summary.follow_ups) ? summary.follow_ups : []
                }
            };
        },

        cards: [
            { id: 'chpOpenCases', path: 'cards.open_cases', subtitleId: 'chpOpenCasesSub', subtitle: 'Open or in progress' },
            { id: 'chpUrgent', path: 'cards.urgent_cases', subtitleId: 'chpUrgentSub', subtitle: 'Urgent priority' },
            { id: 'chpFollowUps', path: 'cards.follow_ups_due', subtitleId: 'chpFollowUpsSub', subtitle: 'Due or overdue' },
            { id: 'chpSessions', path: 'cards.sessions_this_month', subtitleId: 'chpSessionsSub', subtitle: 'Recorded counseling sessions' }
        ],
        chartDefinitions: [
            { id: 'chpTypeChart', path: 'charts.by_type', label: 'Cases', type: 'doughnut', showLegend: true },
            { id: 'chpTrendChart', path: 'charts.session_trend', label: 'Sessions', type: 'line' }
        ],
        tableDefinitions: [
            {
                bodyId: 'chpCasesBody',
                path: 'tables.active_cases',
                emptyText: 'No active counseling cases.',
                columns: [
                    { key: 'case_code' },
                    { key: 'student_name' },
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
                    }
                ]
            },
            {
                bodyId: 'chpFollowUpBody',
                path: 'tables.follow_ups',
                emptyText: 'No follow-ups due.',
                columns: [
                    { key: 'student_name' },
                    { key: 'case_code' },
                    { key: 'next_follow_up_at', format: 'date' },
                    {
                        key: 'status',
                        render: (value, row, instance) => instance.badge(value, {
                            open: 'danger', in_progress: 'warning', resolved: 'success'
                        })
                    }
                ]
            }
        ]
    });

    window.ChaplainDashboardController = controller;
    window.counselorDashboardController = controller;
    DashboardBaseController.boot(controller, 'ChaplainDashboardController');
})();
