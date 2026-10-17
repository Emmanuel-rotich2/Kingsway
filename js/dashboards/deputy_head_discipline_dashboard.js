(() => {
    const unwrap = (response) => response?.data?.data || response?.data || response || {};

    const controller = DashboardBaseController.create({
        controllerName: 'DeputyDisciplineDashboardController',
        rootId: 'deputyDisciplineDashboard',
        refreshButtonId: 'deputyDisciplineDashboardRefresh',
        stateId: 'deputyDisciplineDashboardState',
        scopeId: 'deputyDisciplineDashboardScope',
        lastUpdatedId: 'deputyDisciplineDashboardLastUpdated',
        defaultPeriod: 'today',
        viewSpec: { entity: 'dashboards', view: 'deputy-head-discipline-references', sourceKey: 'stats', input: (filters) => filters },
        composeFromSources(sources) {
            const source = sources.stats || {};
            const discipline = source.cards?.discipline_cases || {};
            const attendance = source.cards?.attendance_today || {};
            const communications = source.cards?.parent_communications || {};
            const cases = source.tables?.discipline_cases?.data || source.tables?.discipline_cases || [];

            return {
                ...source,
                cards: {
                    discipline_cases: {
                        open: Number(discipline.open_cases || 0),
                        in_progress: Number(discipline.escalated || 0),
                        urgent: Number(discipline.high_severity || 0)
                    },
                    attendance_today: {
                        absent_count: Number(attendance.absent || 0),
                        unexcused: Number(attendance.absent || 0)
                    },
                    chronic_truancy: {
                        count: Number(attendance.absent || 0),
                        new_this_week: Number(attendance.late || 0)
                    },
                    counseling_referrals: {
                        pending: Number(discipline.escalated || 0),
                        completed: Number(discipline.resolved_this_month || 0)
                    },
                    parent_meetings: {
                        scheduled: Number(communications.drafts || 0),
                        completed: Number(communications.sent_this_week || 0)
                    }
                },
                charts: {
                    discipline_trend: source.charts?.attendance_trend || { labels: [], data: [] },
                    cases_by_category: {
                        labels: ['Low', 'Medium', 'High'],
                        data: [
                            Number(discipline.low_severity || 0),
                            Number(discipline.medium_severity || 0),
                            Number(discipline.high_severity || 0)
                        ]
                    }
                },
                tables: {
                    active_cases: cases.map((item) => ({
                        ...item,
                        category: item.violation || 'Discipline case'
                    })),
                    at_risk_students: []
                },
                meta: { scope_label: 'Whole-school discipline and safeguarding' }
            };
        },
        apiMethod: async ({ period }) => {
            const source = unwrap(await window.API.dashboard.getDeputyDisciplineFull({ period }));
            return this.composeFromSources({ stats: source });
        },
        cards: [
            {
                id: 'ddOpenCases',
                path: 'cards.discipline_cases.open',
                subtitleId: 'ddOpenCasesSub',
                subtitle: d => `${Number(d.cards?.discipline_cases?.in_progress || 0)} in progress`
            },
            {
                id: 'ddUrgent',
                path: 'cards.discipline_cases.urgent',
                subtitleId: 'ddUrgentSub',
                subtitle: d => `${Number(d.cards?.discipline_cases?.urgent || 0)} require attention`
            },
            {
                id: 'ddAbsentees',
                path: 'cards.attendance_today.absent_count',
                subtitleId: 'ddAbsenteesSub',
                subtitle: d => `${Number(d.cards?.attendance_today?.unexcused || 0)} unexcused`
            },
            {
                id: 'ddTruancy',
                path: 'cards.chronic_truancy.count',
                subtitleId: 'ddTruancySub',
                subtitle: d => `${Number(d.cards?.chronic_truancy?.new_this_week || 0)} new this week`
            },
            {
                id: 'ddCounseling',
                path: 'cards.counseling_referrals.pending',
                subtitleId: 'ddCounselingSub',
                subtitle: d => `${Number(d.cards?.counseling_referrals?.completed || 0)} completed`
            },
            {
                id: 'ddMeetings',
                path: 'cards.parent_meetings.scheduled',
                subtitleId: 'ddMeetingsSub',
                subtitle: d => `${Number(d.cards?.parent_meetings?.completed || 0)} completed`
            }
        ],
        chartDefinitions: [
            {
                id: 'ddTrendChart',
                path: 'charts.discipline_trend',
                label: 'Cases',
                type: 'line'
            },
            {
                id: 'ddCategoryChart',
                path: 'charts.cases_by_category',
                label: 'Cases',
                type: 'doughnut',
                showLegend: true
            }
        ],
        tableDefinitions: [
            {
                bodyId: 'ddCasesBody',
                path: 'tables.active_cases',
                emptyText: 'No active discipline cases.',
                columns: [
                    { key: 'student_name' },
                    { key: 'category' },
                    {
                        key: 'severity',
                        render: (v, r, c) => c.badge(v, {
                            low: 'secondary',
                            medium: 'info',
                            high: 'warning',
                            critical: 'danger'
                        })
                    },
                    { key: 'incident_date', format: 'date' },
                    {
                        key: 'status',
                        render: (v, r, c) => c.badge(v, {
                            open: 'danger',
                            in_progress: 'warning',
                            under_review: 'info',
                            resolved: 'success',
                            closed: 'secondary'
                        })
                    }
                ]
            },
            {
                bodyId: 'ddAtRiskBody',
                path: 'tables.at_risk_students',
                emptyText: 'No at-risk students identified.',
                columns: [
                    { key: 'student_name' },
                    { key: 'class_name' },
                    {
                        key: 'infraction_count',
                        render: (v) => {
                            const n = Number(v || 0);
                            const variant = n >= 5 ? 'danger' : n >= 3 ? 'warning' : 'info';
                            return `<span class="badge bg-${variant}">${n}</span>`;
                        }
                    },
                    { key: 'last_incident_date', format: 'date' }
                ]
            }
        ]
    });

    window.deputyDisciplineDashboard = controller;
    DashboardBaseController.boot(controller, 'DeputyDisciplineDashboardController');
})();
