/**
 * Talent Development Dashboard Controller
 * Composes the existing Activities API, manager and schedule endpoints.
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
        controllerName: 'TalentDevelopmentDashboardController',
        rootId: 'talentDashboard',
        refreshButtonId: 'talentDashboardRefresh',
        stateId: 'talentDashboardState',
        scopeId: 'talentDashboardScope',
        lastUpdatedId: 'talentDashboardLastUpdated',

        async apiMethod() {
            const [summaryResponse, activitiesResponse, schedulesResponse] = await Promise.all([
                window.API.activities.getSummary(),
                window.API.activities.list({ limit: 20 }),
                window.API.activities.listSchedules({})
            ]);

            const stats = unwrap(summaryResponse) || {};
            const activitiesValue = unwrap(activitiesResponse);
            const schedulesValue = unwrap(schedulesResponse);
            const activities = Array.isArray(activitiesValue) ? activitiesValue : [];
            const schedules = Array.isArray(schedulesValue) ? schedulesValue : [];

            const categories = activities.reduce((totals, row) => {
                const category = row.category_name || 'Uncategorised';
                totals[category] = (totals[category] || 0) + 1;
                return totals;
            }, {});
            const activeActivities = activities.filter((row) =>
                ['planned', 'ongoing'].includes(String(row.status || '').toLowerCase())
            );
            const participantTotal = activities.reduce(
                (sum, row) => sum + Number(row.active_participants || 0),
                0
            );

            return {
                meta: { scope_label: 'Talent Development' },
                cards: {
                    active_activities: Number(stats.planned || 0) + Number(stats.ongoing || 0),
                    student_participants: participantTotal,
                    completed_activities: Number(stats.completed || 0),
                    upcoming_sessions: schedules.length
                },
                charts: {
                    by_category: {
                        labels: Object.keys(categories),
                        data: Object.values(categories)
                    },
                    participation: {
                        labels: activities.slice(0, 10).map((row) => row.title || 'Activity'),
                        data: activities.slice(0, 10).map((row) => Number(row.active_participants || 0))
                    }
                },
                tables: {
                    activities: activeActivities,
                    schedule: schedules.slice(0, 20)
                }
            };
        },

        cards: [
            { id: 'talActivities', path: 'cards.active_activities', subtitleId: 'talActivitiesSub', subtitle: 'Planned or ongoing programmes' },
            { id: 'talParticipants', path: 'cards.student_participants', subtitleId: 'talParticipantsSub', subtitle: 'Active student participation' },
            { id: 'talStaff', path: 'cards.completed_activities', subtitleId: 'talStaffSub', subtitle: 'Programmes completed' },
            { id: 'talUpcoming', path: 'cards.upcoming_sessions', subtitleId: 'talUpcomingSub', subtitle: 'Recurring schedule entries' }
        ],
        chartDefinitions: [
            { id: 'talCategoryChart', path: 'charts.by_category', label: 'Activities', type: 'doughnut', showLegend: true },
            { id: 'talParticipationChart', path: 'charts.participation', label: 'Participants', type: 'bar' }
        ],
        tableDefinitions: [
            {
                bodyId: 'talActivitiesBody',
                path: 'tables.activities',
                emptyText: 'No active activities.',
                columns: [
                    { key: 'title' },
                    { value: (row) => row.category_name || 'Uncategorised' },
                    { value: (row) => [row.start_date, row.end_date].filter(Boolean).join(' – ') },
                    {
                        key: 'status',
                        render: (value, row, instance) => instance.badge(value, {
                            planned: 'primary', ongoing: 'success', completed: 'secondary', cancelled: 'danger'
                        })
                    }
                ]
            },
            {
                bodyId: 'talScheduleBody',
                path: 'tables.schedule',
                emptyText: 'No activity schedule entries.',
                columns: [
                    { key: 'activity_title' },
                    { key: 'day_of_week' },
                    { value: (row) => `${String(row.start_time || '').slice(0, 5)}–${String(row.end_time || '').slice(0, 5)}` },
                    { key: 'venue' }
                ]
            }
        ]
    });

    window.TalentDevelopmentDashboardController = controller;
    window.hodDashboardController = controller;
    DashboardBaseController.boot(controller, 'TalentDevelopmentDashboardController');
})();
