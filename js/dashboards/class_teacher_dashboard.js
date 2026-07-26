/** Class Teacher Dashboard Controller */
const ClassTeacherDashboardController = DashboardBaseController.create({
    controllerName:'ClassTeacherDashboardController',rootId:'classTeacherDashboard',
    refreshButtonId:'classTeacherDashboardRefresh',stateId:'classTeacherDashboardState',
    scopeId:'classTeacherDashboardScope',lastUpdatedId:'classTeacherDashboardLastUpdated',
    apiMethod:()=>window.API.dashboard.getClassTeacherFull(),
    cards:[
        {id:'ctStudents',path:'cards.my_students.total',subtitleId:'ctStudentsSub',subtitle:d=>d.cards?.my_students?.class_name||'No class assigned'},
        {id:'ctAttendance',path:'cards.today_attendance.percentage',format:'percent',subtitleId:'ctAttendanceSub',subtitle:d=>`${Number(d.cards?.today_attendance?.present||0)} present · ${Number(d.cards?.today_attendance?.absent||0)} absent`},
        {id:'ctAssessments',path:'cards.pending_assessments.pending',subtitleId:'ctAssessmentsSub',subtitle:d=>`${Number(d.cards?.pending_assessments?.overdue||0)} overdue`},
        {id:'ctLessonPlans',path:'cards.lesson_plans.this_week',subtitleId:'ctLessonPlansSub',subtitle:d=>`${Number(d.cards?.lesson_plans?.pending||0)} awaiting review`}
    ],
    chartDefinitions:[
        {id:'ctAttendanceChart',path:'charts.attendance_trend',label:'Attendance %',type:'line'},
        {id:'ctPerformanceChart',path:'charts.assessment_performance',label:'Average score',type:'bar'}
    ],
    tableDefinitions:[
        {bodyId:'ctScheduleBody',path:'tables.today_schedule',emptyText:'No lessons scheduled today.',columns:[
            {key:'time'},{key:'subject_name'},{key:'room'},{key:'status',render:(v,r,c)=>c.badge(v,{scheduled:'primary',completed:'success',cancelled:'danger'})}
        ]},
        {bodyId:'ctRosterBody',path:'tables.student_roster',emptyText:'No students assigned.',columns:[
            {key:'admission_no'},{value:r=>r.student_name||[r.first_name,r.last_name].filter(Boolean).join(' ')},{key:'gender'},{key:'status',render:(v,r,c)=>c.badge(v,{active:'success',inactive:'secondary',suspended:'danger'})}
        ]}
    ]
});
window.classTeacherDashboardController=ClassTeacherDashboardController;
DashboardBaseController.boot(ClassTeacherDashboardController,'ClassTeacherDashboardController');
