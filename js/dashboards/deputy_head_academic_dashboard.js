(() => {
    const controller = DashboardBaseController.create({
        controllerName: 'DeputyAcademicDashboardController',
        rootId: 'deputyAcademicDashboard',
        refreshButtonId: 'deputyAcademicDashboardRefresh',
        stateId: 'deputyAcademicDashboardState',
        scopeId: 'deputyAcademicDashboardScope',
        lastUpdatedId: 'deputyAcademicDashboardLastUpdated',
        defaultPeriod: 'today',
        viewSpec: { entity: 'dashboards', view: 'deputy-head-academic-references', sourceKey: 'stats', input: (filters) => filters },
        apiMethod: ({ period }) => window.API.dashboard.getDeputyAcademicFull({ period }),
        cards: [
            {
                id: 'daLessonPlans',
                path: 'cards.lesson_plans.pending',
                subtitleId: 'daLessonPlansSub',
                subtitle: d => `${Number(d.cards?.lesson_plans?.approved || 0)} approved`
            },
            {
                id: 'daGrading',
                path: 'cards.grading_status.pending',
                subtitleId: 'daGradingSub',
                subtitle: d => `${Number(d.cards?.grading_status?.overdue || 0)} overdue`
            },
            {
                id: 'daTimetable',
                path: 'cards.timetable_coverage.percentage',
                format: 'percent',
                subtitleId: 'daTimetableSub',
                subtitle: d => `${Number(d.cards?.timetable_coverage?.total_periods || 0)} periods`
            },
            {
                id: 'daAdmissions',
                path: 'cards.pending_admissions.pending',
                subtitleId: 'daAdmissionsSub',
                subtitle: d => `${Number(d.cards?.pending_admissions?.awaiting_placement || 0)} awaiting placement`
            },
            {
                id: 'daAttendance',
                path: 'cards.attendance_today.percentage',
                format: 'percent',
                subtitleId: 'daAttendanceSub',
                subtitle: d => `${Number(d.cards?.attendance_today?.present || 0)} present`
            },
            {
                id: 'daWorkload',
                path: 'cards.workload_alerts.count',
                subtitleId: 'daWorkloadSub',
                subtitle: d => `${Number(d.cards?.workload_alerts?.critical || 0)} critical`
            }
        ],
        chartDefinitions: [
            {
                id: 'daLessonPlanChart',
                path: 'charts.lesson_plan_trend',
                label: 'Submitted',
                type: 'line'
            },
            {
                id: 'daPerformanceChart',
                path: 'charts.academic_performance',
                label: 'Average score',
                type: 'bar'
            }
        ],
        tableDefinitions: [
            {
                bodyId: 'daPlacementsBody',
                path: 'tables.pending_placements',
                emptyText: 'No pending placements.',
                columns: [
                    { key: 'student_name' },
                    { key: 'applied_class' },
                    { key: 'test_score', format: 'number' },
                    {
                        key: 'status',
                        render: (v, r, c) => c.badge(v, {
                            pending: 'warning',
                            assessed: 'info',
                            placed: 'success',
                            rejected: 'danger'
                        })
                    }
                ]
            },
            {
                bodyId: 'daGradesBody',
                path: 'tables.incomplete_grades',
                emptyText: 'All grades are up to date.',
                columns: [
                    { key: 'teacher_name' },
                    { key: 'class_name' },
                    {
                        key: 'percent_pending',
                        format: 'percent',
                        render: (v, r, c) => {
                            const num = Number(v || 0);
                            const variant = num > 50 ? 'danger' : num > 20 ? 'warning' : 'success';
                            return `<span class="badge bg-${variant}">${num.toFixed(1)}%</span>`;
                        }
                    },
                    {
                        key: 'status',
                        render: (v, r, c) => c.badge(v || 'overdue', {
                            on_track: 'success',
                            overdue: 'danger',
                            at_risk: 'warning'
                        })
                    }
                ]
            }
        ]
    });

    window.deputyAcademicDashboard = controller;
    DashboardBaseController.boot(controller, 'DeputyAcademicDashboardController');
})();
