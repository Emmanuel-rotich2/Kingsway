/** Deputy Head Academic Dashboard Controller */
const DeputyAcademicDashboardController = DashboardBaseController.create({
    controllerName: 'DeputyAcademicDashboardController', rootId: 'deputyAcademicDashboard',
    refreshButtonId: 'deputyAcademicDashboardRefresh', stateId: 'deputyAcademicDashboardState',
    scopeId: 'deputyAcademicDashboardScope', lastUpdatedId: 'deputyAcademicDashboardLastUpdated',
    apiMethod: () => window.API.dashboard.getDeputyAcademicFull(),
    cards: [
        { id: 'dhaPendingAdmissions', path: 'cards.pending_admissions.pending', subtitleId: 'dhaPendingAdmissionsSub', subtitle: d => `${Number(d.cards?.pending_admissions?.awaiting_placement || 0)} awaiting placement` },
        { id: 'dhaSchedules', path: 'cards.class_schedules.total', subtitleId: 'dhaSchedulesSub', subtitle: d => `${Number(d.cards?.class_schedules?.unassigned || 0)} unassigned` },
        { id: 'dhaAssessments', path: 'cards.student_assessments.pending', subtitleId: 'dhaAssessmentsSub', subtitle: d => `${Number(d.cards?.student_assessments?.overdue || 0)} overdue` },
        { id: 'dhaAttendance', path: 'cards.attendance_today.percentage', format: 'percent', subtitleId: 'dhaAttendanceSub', subtitle: d => `${Number(d.cards?.attendance_today?.present || 0)} present` }
    ],
    chartDefinitions: [
        { id: 'dhaAttendanceChart', path: 'charts.attendance_trend', label: 'Attendance %', type: 'line' },
        { id: 'dhaPerformanceChart', path: 'charts.class_performance', label: 'Average score', type: 'bar' }
    ],
    tableDefinitions: [
        { bodyId: 'dhaAdmissionsBody', path: 'tables.pending_admissions', emptyText: 'No pending admissions.', columns: [
            { key: 'applicant_name' }, { key: 'class_name' }, { key: 'status', render: (v,r,c)=>c.badge(v,{pending:'warning',approved:'success',rejected:'danger'}) }, { key: 'created_at', format: 'date' }
        ]},
        { bodyId: 'dhaEventsBody', path: 'tables.upcoming_events', emptyText: 'No upcoming events.', columns: [
            { key: 'title' }, { key: 'event_date', format: 'date' }, { key: 'event_type' }, { key: 'status', render:(v,r,c)=>c.badge(v,{scheduled:'primary',active:'success',completed:'secondary'}) }
        ]}
    ]
});
window.deputyAcademicDashboard = DeputyAcademicDashboardController;
DashboardBaseController.boot(DeputyAcademicDashboardController, 'DeputyAcademicDashboardController');
