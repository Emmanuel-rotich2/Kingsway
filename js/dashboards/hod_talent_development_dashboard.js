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
        rootId: 'talentDevDashboard',
        refreshButtonId: 'talentDevDashboardRefresh',
        stateId: 'talentDevDashboardState',
        scopeId: 'talentDevDashboardScope',
        lastUpdatedId: 'talentDevDashboardLastUpdated',
        defaultPeriod: 'week',

        async apiMethod(filters = {}) {
            const [summaryResponse, activitiesResponse, schedulesResponse] = await Promise.all([
                window.API.activities.getSummary(filters),
                window.API.activities.list({ ...filters, limit: 20 }),
                window.API.activities.listSchedules(filters)
            ]);

            const stats = unwrap(summaryResponse) || {};
            const schedulesValue = unwrap(schedulesResponse);
            const activities = Array.isArray(unwrap(activitiesResponse))
                ? unwrap(activitiesResponse)
                : [];
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
                    upcoming_sessions: schedules.length,
                    events_this_month: Number(stats.completed || 0) + schedules.length
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
                activities: activeActivities,
                schedule: schedules.slice(0, 20)
            };
        },

        cards: [
            { id: 'talActiveActivities', path: 'cards.active_activities', subtitleId: 'talActiveSub', subtitle: 'Planned or ongoing programmes' },
            { id: 'talParticipants', path: 'cards.student_participants', subtitleId: 'talParticipantsSub', subtitle: 'Active student participation' },
            { id: 'talUpcomingEvents', path: 'cards.upcoming_sessions', subtitleId: 'talUpcomingSub', subtitle: 'Upcoming schedule entries' },
            { id: 'talEventsThisMonth', path: 'cards.events_this_month', subtitleId: 'talEventsSub', subtitle: 'Completed + scheduled' }
        ],
        chartDefinitions: [
            { id: 'talCategoryChart', path: 'charts.by_category', label: 'Activities', type: 'doughnut', showLegend: true },
            { id: 'talParticipationChart', path: 'charts.participation', label: 'Participants', type: 'bar' }
        ],
        tableDefinitions: [
            {
                bodyId: 'talCurrentActivitiesBody',
                rows: (data) => Array.isArray(data.activities) ? data.activities : [],
                emptyText: 'No active activities.',
                columns: [
                    { key: 'title' },
                    { key: 'category_name' },
                    { key: 'active_participants', format: 'number' },
                    { key: 'start_date', format: 'date' },
                    { key: 'end_date', format: 'date' },
                    {
                        key: 'status',
                        render: (value, row, instance) => instance.badge(value, {
                            planned: 'primary', ongoing: 'success', completed: 'secondary', cancelled: 'danger'
                        })
                    }
                ]
            },
            {
                bodyId: 'talWeeklyBody',
                rows: (data) => Array.isArray(data.schedule) ? data.schedule : [],
                emptyText: 'No activity schedule entries.',
                columns: [
                    { key: 'day_of_week' },
                    { value: (row) => `${String(row.start_time || '').slice(0, 5)}–${String(row.end_time || '').slice(0, 5)}` },
                    { key: 'activity_title' },
                    { key: 'venue' },
                    { key: 'category_name' }
                ]
            }
        ],

        afterRender() {
            this.fillEmptyRows([
                ['talUpcomingBody', 4, 'No upcoming events scheduled.'],
                ['talPastEventsBody', 4, 'No past events recorded.'],
                ['talBudgetBody', 4, 'No budget summary available.'],
                ['talTopActivitiesBody', 4, 'No participation data recorded.'],
                ['talStaffBody', 5, 'No coaches or supervisors assigned.'],
                ['talResourcesBody', 4, 'No equipment resources recorded.'],
                ['talAchievementsBody', 4, 'No recent achievements recorded.']
            ]);
        },

        fillEmptyRows(definitions) {
            definitions.forEach(([bodyId, colspan, message]) => {
                const body = document.getElementById(bodyId);
                if (!body) {
                    return;
                }
                body.innerHTML = `<tr><td colspan="${colspan}" class="text-center text-muted py-4">${this.escapeHtml(message)}</td></tr>`;
            });
        }
    });

    window.TalentDevelopmentDashboardController = controller;
    window.hodDashboardController = controller;
    DashboardBaseController.boot(controller, 'TalentDevelopmentDashboardController');
})();