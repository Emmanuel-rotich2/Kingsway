(() => {
    const controller = DashboardBaseController.create({
        controllerName: 'InternStudentTeacherDashboardController',
        rootId: 'internDashboard',
        refreshButtonId: 'internDashboardRefresh',
        stateId: 'internDashboardState',
        scopeId: 'internDashboardScope',
        lastUpdatedId: 'internDashboardLastUpdated',
        viewSpec: { entity: 'dashboards', view: 'intern-student-teacher-references', sourceKey: 'stats', input: (filters) => filters },

        async apiMethod({ period } = {}) {
            try {
                const response = await window.API.dashboard.getInternTeacherFull({ period });
                const data = response?.data?.data || response?.data || response || {};
                return {
                    meta: data.meta || {},
                    cards: data.cards || {},
                    charts: data.charts || {},
                    tables: data.tables || {}
                };
            } catch (e) {
                return { meta: {}, cards: {}, charts: {}, tables: {} };
            }
        },

        cards: [
            { id: 'internClasses', path: 'cards.assigned_classes.total', subtitleId: 'internClassesSub', subtitle: d => `${Number(d.cards?.assigned_classes?.subjects || 0)} subjects` },
            { id: 'internObservations', path: 'cards.lesson_observations.total', subtitleId: 'internObservationsSub', subtitle: d => `${Number(d.cards?.lesson_observations?.pending || 0)} pending` },
            { id: 'internMentor', path: 'cards.mentor_meetings.total', subtitleId: 'internMentorSub', subtitle: d => `${Number(d.cards?.mentor_meetings?.upcoming || 0)} upcoming` },
            { id: 'internProgress', path: 'cards.development_progress.percentage', format: 'percent', subtitleId: 'internProgressSub', subtitle: d => `${Number(d.cards?.development_progress?.completed || 0)} competencies completed` }
        ],
        chartDefinitions: [
            {
                id: 'internCompetencyChart',
                data: d => ({
                    labels: (d.tables?.competencies || []).map(x => x.competency || x.name),
                    data: (d.tables?.competencies || []).map(x => Number(x.score || x.progress || 0))
                }),
                label: 'Progress %', type: 'radar', showLegend: false
            },
            {
                id: 'internObsChart',
                data: d => ({
                    labels: ['Completed', 'Pending'],
                    data: [Number(d.cards?.lesson_observations?.completed || 0), Number(d.cards?.lesson_observations?.pending || 0)]
                }),
                label: 'Observations', type: 'doughnut', showLegend: true
            }
        ],
        tableDefinitions: [
            {
                bodyId: 'internClassesBody', path: 'tables.assigned_classes', emptyText: 'No classes assigned.', columns: [
                    { key: 'class_name' }, { key: 'subject_name' },
                    { value: r => Number(r.student_count || r.students || 0) }
                ]
            },
            {
                bodyId: 'internFeedbackBody', path: 'tables.observations', emptyText: 'No feedback recorded.', columns: [
                    { key: 'observation_date', format: 'date' }, { key: 'observer_name' }, { key: 'focus_area' },
                    { key: 'status', render: (v, r, c) => c.badge(v, { scheduled: 'primary', completed: 'success', pending: 'warning' }) }
                ]
            }
        ]
    });

    window.InternStudentTeacherDashboardController = controller;
    window.internDashboardController = controller;
    DashboardBaseController.boot(controller, 'InternStudentTeacherDashboardController');
})();
