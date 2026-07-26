/** Subject Teacher Dashboard Controller */
const SubjectTeacherDashboardController = DashboardBaseController.create({
    controllerName:'SubjectTeacherDashboardController',rootId:'subjectTeacherDashboard',
    refreshButtonId:'subjectTeacherDashboardRefresh',stateId:'subjectTeacherDashboardState',
    scopeId:'subjectTeacherDashboardScope',lastUpdatedId:'subjectTeacherDashboardLastUpdated',
    apiMethod:()=>window.API.dashboard.getSubjectTeacherFull(),
    cards:[
        {id:'stClasses',path:'cards.classes.total_classes',subtitleId:'stClassesSub',subtitle:d=>`${Number(d.cards?.classes?.total_students||0)} students`},
        {id:'stSections',path:'cards.sections.total_sections',subtitleId:'stSectionsSub',subtitle:d=>(d.cards?.sections?.forms_taught||[]).join(', ')||'No forms assigned'},
        {id:'stAssessments',path:'cards.assessments_due.pending_assessments',subtitleId:'stAssessmentsSub',subtitle:d=>`${Number(d.cards?.assessments_due?.overdue||0)} overdue`},
        {id:'stGraded',path:'cards.graded.graded_this_week',subtitleId:'stGradedSub',subtitle:d=>`${Number(d.cards?.graded?.average_score||0).toFixed(1)} average`}
    ],
    chartDefinitions:[
        {id:'stPerformanceChart',path:'charts.subject_performance',label:'Average score',type:'bar'},
        {id:'stTrendChart',path:'charts.assessment_trends',label:'Assessment trend',type:'line'}
    ],
    tableDefinitions:[
        {bodyId:'stAssessmentsBody',path:'tables.pending_assessments',emptyText:'No pending assessments.',columns:[
            {key:'title'},{key:'class'},{key:'due_date',format:'date'},{value:()=> 'Pending',render:(v,r,c)=>c.badge(v,{pending:'warning'})}
        ]},
        {bodyId:'stExamsBody',path:'tables.exam_schedule',emptyText:'No upcoming exams.',columns:[
            {key:'class'},{key:'date',format:'date'},{key:'time',format:'time'},{key:'room'}
        ]}
    ]
});
window.subjectTeacherDashboardController=SubjectTeacherDashboardController;
DashboardBaseController.boot(SubjectTeacherDashboardController,'SubjectTeacherDashboardController');
