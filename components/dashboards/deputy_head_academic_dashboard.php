<?php
/**
 * Kingsway role dashboard component.
 * Presentation only; data is supplied by the matching named JS controller.
 */
$dashboardConfig = [
    'root_id' => 'deputyAcademicDashboard',
    'title' => 'Deputy Head – Academic',
    'subtitle' => 'Academic operations, review queues and school-wide performance.',
    'icon' => 'bi-mortarboard',
    'controller_file' => 'deputy_head_academic_dashboard.js',
    'cards' => [
        ['id' => 'dhaPendingAdmissions', 'label' => 'Pending Admissions', 'icon' => 'bi-person-plus', 'colour' => 'dsc-blue', 'subtitle_id' => 'dhaPendingAdmissionsSub'],
        ['id' => 'dhaSchedules', 'label' => 'Class Schedules', 'icon' => 'bi-calendar-week', 'colour' => 'dsc-cyan', 'subtitle_id' => 'dhaSchedulesSub'],
        ['id' => 'dhaAssessments', 'label' => 'Assessments', 'icon' => 'bi-clipboard-data', 'colour' => 'dsc-orange', 'subtitle_id' => 'dhaAssessmentsSub'],
        ['id' => 'dhaAttendance', 'label' => 'Attendance Today', 'icon' => 'bi-person-check', 'colour' => 'dsc-green', 'subtitle_id' => 'dhaAttendanceSub']
    ],
    'charts' => [
        ['id' => 'dhaAttendanceChart', 'title' => 'Attendance Trend', 'icon' => 'bi-graph-up', 'column' => 'col-lg-6'],
        ['id' => 'dhaPerformanceChart', 'title' => 'Class Performance', 'icon' => 'bi-bar-chart', 'column' => 'col-lg-6']
    ],
    'tables' => [
        ['body_id' => 'dhaAdmissionsBody', 'title' => 'Pending Admissions', 'columns' => ['Applicant', 'Class', 'Stage', 'Applied'], 'route' => 'admissions_academic_applications', 'column' => 'col-xl-6'],
        ['body_id' => 'dhaEventsBody', 'title' => 'Upcoming Academic Events', 'columns' => ['Event', 'Date', 'Type', 'Status'], 'route' => 'academic_calendar', 'column' => 'col-xl-6']
    ],
    'quick_actions' => [
        ['label' => 'My Timetable', 'route' => 'timetable', 'icon' => 'bi-calendar3'],
        ['label' => 'Review Lesson Plans', 'route' => 'lesson_plan_approval', 'icon' => 'bi-journal-check'],
        ['label' => 'Manage Timetable', 'route' => 'manage_timetable', 'icon' => 'bi-calendar2-week'],
        ['label' => 'Exam Setup', 'route' => 'exam_setup', 'icon' => 'bi-file-earmark-text']
    ],
];

require __DIR__ . '/partials/role_dashboard_shell.php';
