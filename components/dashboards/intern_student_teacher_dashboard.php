<?php
/**
 * Kingsway role dashboard component.
 * Presentation only; data is supplied by the matching named JS controller.
 */
$dashboardConfig = [
    'root_id' => 'internTeacherDashboard',
    'title' => 'Intern / Student Teacher Dashboard',
    'subtitle' => 'Teaching assignments, observations and professional development.',
    'icon' => 'bi-person-workspace',
    'controller_file' => 'intern_student_teacher_dashboard.js',
    'cards' => [
        ['id' => 'itClasses', 'label' => 'Assigned Classes', 'icon' => 'bi-building', 'colour' => 'dsc-blue', 'subtitle_id' => 'itClassesSub'],
        ['id' => 'itObservations', 'label' => 'Lesson Observations', 'icon' => 'bi-eye', 'colour' => 'dsc-purple', 'subtitle_id' => 'itObservationsSub'],
        ['id' => 'itResources', 'label' => 'Teaching Resources', 'icon' => 'bi-folder2-open', 'colour' => 'dsc-cyan', 'subtitle_id' => 'itResourcesSub'],
        ['id' => 'itProgress', 'label' => 'Development Progress', 'icon' => 'bi-graph-up-arrow', 'colour' => 'dsc-green', 'subtitle_id' => 'itProgressSub']
    ],
    'charts' => [
        ['id' => 'itCompetencyChart', 'title' => 'Competency Progress', 'icon' => 'bi-radar', 'column' => 'col-lg-6'],
        ['id' => 'itObservationChart', 'title' => 'Observation Outcomes', 'icon' => 'bi-bar-chart', 'column' => 'col-lg-6']
    ],
    'tables' => [
        ['body_id' => 'itClassesBody', 'title' => 'Assigned Classes', 'columns' => ['Class', 'Subject', 'Mentor', 'Schedule'], 'route' => 'timetable', 'column' => 'col-xl-6'],
        ['body_id' => 'itObservationsBody', 'title' => 'Recent Observations', 'columns' => ['Date', 'Observer', 'Focus', 'Status'], 'route' => 'teacher_performance_reviews', 'column' => 'col-xl-6']
    ],
    'quick_actions' => [
        ['label' => 'My Timetable', 'route' => 'timetable', 'icon' => 'bi-calendar3'],
        ['label' => 'Lesson Plans', 'route' => 'manage_lesson_plans', 'icon' => 'bi-journal-text'],
        ['label' => 'Teaching Resources', 'route' => 'teaching_materials', 'icon' => 'bi-folder2-open'],
        ['label' => 'My Profile', 'route' => 'complete_staff_profile', 'icon' => 'bi-person']
    ],
];

require __DIR__ . '/partials/role_dashboard_shell.php';
