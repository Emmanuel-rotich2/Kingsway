/** Intern / Student Teacher Dashboard Controller */
const InternStudentTeacherDashboardController = DashboardBaseController.create({
    controllerName:'InternStudentTeacherDashboardController',rootId:'internTeacherDashboard',
    refreshButtonId:'internTeacherDashboardRefresh',stateId:'internTeacherDashboardState',
    scopeId:'internTeacherDashboardScope',lastUpdatedId:'internTeacherDashboardLastUpdated',
    apiMethod:()=>window.API.dashboard.getInternTeacherFull(),
    cards:[
        {id:'itClasses',path:'cards.assigned_classes.total',subtitleId:'itClassesSub',subtitle:d=>`${Number(d.cards?.assigned_classes?.subjects||0)} subjects`},
        {id:'itObservations',path:'cards.lesson_observations.total',subtitleId:'itObservationsSub',subtitle:d=>`${Number(d.cards?.lesson_observations?.pending||0)} pending`},
        {id:'itResources',path:'cards.teaching_resources.total',subtitleId:'itResourcesSub',subtitle:d=>`${Number(d.cards?.teaching_resources?.available||0)} available`},
        {id:'itProgress',path:'cards.development_progress.percentage',format:'percent',subtitleId:'itProgressSub',subtitle:d=>`${Number(d.cards?.development_progress?.completed||0)} competencies completed`}
    ],
    chartDefinitions:[
        {id:'itCompetencyChart',data:d=>({labels:(d.tables?.competencies||[]).map(x=>x.competency||x.name),data:(d.tables?.competencies||[]).map(x=>Number(x.score||x.progress||0))}),label:'Progress %',type:'radar',showLegend:false},
        {id:'itObservationChart',data:d=>({labels:['Completed','Pending'],data:[Number(d.cards?.lesson_observations?.completed||0),Number(d.cards?.lesson_observations?.pending||0)]}),label:'Observations',type:'doughnut',showLegend:true}
    ],
    tableDefinitions:[
        {bodyId:'itClassesBody',path:'tables.assigned_classes',emptyText:'No classes assigned.',columns:[
            {key:'class_name'},{key:'subject_name'},{key:'mentor_name'},{key:'schedule'}
        ]},
        {bodyId:'itObservationsBody',path:'tables.observations',emptyText:'No observations recorded.',columns:[
            {key:'observation_date',format:'date'},{key:'observer_name'},{key:'focus_area'},{key:'status',render:(v,r,c)=>c.badge(v,{scheduled:'primary',completed:'success',pending:'warning'})}
        ]}
    ]
});
window.internDashboardController=InternStudentTeacherDashboardController;
DashboardBaseController.boot(InternStudentTeacherDashboardController,'InternStudentTeacherDashboardController');
