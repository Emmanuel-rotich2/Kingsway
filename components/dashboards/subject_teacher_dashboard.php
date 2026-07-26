<?php
/**
 * Kingsway role dashboard component.
 * Presentation only; data is supplied by the matching named JS controller.
 */
$dashboardConfig = [
    'root_id' => 'subjectTeacherDashboard',
    'title' => 'Subject Teacher Dashboard',
    'subtitle' => 'Your teaching load, assessment queue and subject performance.',
    'icon' => 'bi-book',
    'controller_file' => 'subject_teacher_dashboard.js',
    'cards' => [
        ['id' => 'stClasses', 'label' => 'Assigned Classes', 'icon' => 'bi-building', 'colour' => 'dsc-blue', 'subtitle_id' => 'stClassesSub'],
        ['id' => 'stSections', 'label' => 'Teaching Sections', 'icon' => 'bi-diagram-3', 'colour' => 'dsc-cyan', 'subtitle_id' => 'stSectionsSub'],
        ['id' => 'stAssessments', 'label' => 'Assessments Due', 'icon' => 'bi-clipboard2-data', 'colour' => 'dsc-orange', 'subtitle_id' => 'stAssessmentsSub'],
        ['id' => 'stGraded', 'label' => 'Graded This Week', 'icon' => 'bi-check2-circle', 'colour' => 'dsc-green', 'subtitle_id' => 'stGradedSub']
    ],
    'charts' => [
        ['id' => 'stPerformanceChart', 'title' => 'Subject Performance', 'icon' => 'bi-bar-chart', 'column' => 'col-lg-6'],
        ['id' => 'stTrendChart', 'title' => 'Assessment Trend', 'icon' => 'bi-graph-up', 'column' => 'col-lg-6']
    ],
    'tables' => [
        ['body_id' => 'stAssessmentsBody', 'title' => 'Pending Assessments', 'columns' => ['Assessment', 'Class', 'Due Date', 'Status'], 'route' => 'formative_assessments', 'column' => 'col-xl-6'],
        ['body_id' => 'stExamsBody', 'title' => 'Upcoming Exams', 'columns' => ['Class', 'Date', 'Time', 'Room'], 'route' => 'exam_schedule', 'column' => 'col-xl-6']
    ],
    'quick_actions' => [
        ['label' => 'My Timetable', 'route' => 'timetable', 'icon' => 'bi-calendar3'],
        ['label' => 'Enter Marks', 'route' => 'formative_assessments', 'icon' => 'bi-pencil-square'],
        ['label' => 'Lesson Plans', 'route' => 'manage_lesson_plans', 'icon' => 'bi-journal-text'],
        ['label' => 'Exam Schedule', 'route' => 'exam_schedule', 'icon' => 'bi-calendar-event']
    ],
];

require __DIR__ . '/partials/role_dashboard_shell.php';
