/** Deputy Head Discipline Dashboard Controller */
const DeputyDisciplineDashboardController = DashboardBaseController.create({
    controllerName: 'DeputyDisciplineDashboardController', rootId: 'deputyDisciplineDashboard',
    refreshButtonId: 'deputyDisciplineDashboardRefresh', stateId: 'deputyDisciplineDashboardState',
    scopeId: 'deputyDisciplineDashboardScope', lastUpdatedId: 'deputyDisciplineDashboardLastUpdated',
    apiMethod: () => window.API.dashboard.getDeputyDisciplineFull(),
    cards: [
        { id:'dhdOpenCases', path:'cards.discipline_cases.open', subtitleId:'dhdOpenCasesSub', subtitle:d=>`${Number(d.cards?.discipline_cases?.in_progress||0)} in progress` },
        { id:'dhdUrgentCases', path:'cards.discipline_cases.urgent', subtitleId:'dhdUrgentCasesSub', subtitle:'Require immediate attention' },
        { id:'dhdAttendance', path:'cards.attendance_today.percentage', format:'percent', subtitleId:'dhdAttendanceSub', subtitle:d=>`${Number(d.cards?.attendance_today?.absent||0)} absent` },
        { id:'dhdCommunications', path:'cards.parent_communications.pending', subtitleId:'dhdCommunicationsSub', subtitle:d=>`${Number(d.cards?.parent_communications?.sent_this_week||0)} sent this week` }
    ],
    chartDefinitions:[
        {id:'dhdDisciplineChart',path:'charts.discipline_trend',label:'Cases',type:'line'},
        {id:'dhdAttendanceChart',path:'charts.attendance_trend',label:'Attendance %',type:'line'}
    ],
    tableDefinitions:[
        {bodyId:'dhdCasesBody',path:'tables.discipline_cases',emptyText:'No active discipline cases.',columns:[
            {key:'student_name'},{key:'case_type'},{key:'priority',render:(v,r,c)=>c.badge(v,{low:'secondary',medium:'info',high:'warning',urgent:'danger'})},{key:'status',render:(v,r,c)=>c.badge(v,{open:'danger',in_progress:'warning',resolved:'success',closed:'secondary'})}
        ]},
        {bodyId:'dhdEventsBody',path:'tables.upcoming_events',emptyText:'No upcoming meetings.',columns:[
            {key:'title'},{key:'event_date',format:'date'},{key:'event_type'},{key:'status',render:(v,r,c)=>c.badge(v,{scheduled:'primary',completed:'success'})}
        ]}
    ]
});
window.deputyDisciplineDashboard = DeputyDisciplineDashboardController;
DashboardBaseController.boot(DeputyDisciplineDashboardController, 'DeputyDisciplineDashboardController');
