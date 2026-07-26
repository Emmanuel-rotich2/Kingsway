<?php
/**
 * Kingsway role dashboard component.
 * Presentation only; data is supplied by the matching named JS controller.
 */
$dashboardConfig = [
    'root_id' => 'deputyDisciplineDashboard',
    'title' => 'Deputy Head – Discipline',
    'subtitle' => 'Student conduct, attendance risks and discipline follow-up.',
    'icon' => 'bi-shield-exclamation',
    'controller_file' => 'deputy_head_discipline_dashboard.js',
    'cards' => [
        ['id' => 'dhdOpenCases', 'label' => 'Open Cases', 'icon' => 'bi-exclamation-octagon', 'colour' => 'dsc-red', 'subtitle_id' => 'dhdOpenCasesSub'],
        ['id' => 'dhdUrgentCases', 'label' => 'Urgent Cases', 'icon' => 'bi-alarm', 'colour' => 'dsc-orange', 'subtitle_id' => 'dhdUrgentCasesSub'],
        ['id' => 'dhdAttendance', 'label' => 'Attendance Today', 'icon' => 'bi-person-check', 'colour' => 'dsc-green', 'subtitle_id' => 'dhdAttendanceSub'],
        ['id' => 'dhdCommunications', 'label' => 'Parent Communications', 'icon' => 'bi-chat-dots', 'colour' => 'dsc-purple', 'subtitle_id' => 'dhdCommunicationsSub']
    ],
    'charts' => [
        ['id' => 'dhdDisciplineChart', 'title' => 'Discipline Trend', 'icon' => 'bi-graph-down-arrow', 'column' => 'col-lg-6'],
        ['id' => 'dhdAttendanceChart', 'title' => 'Attendance Trend', 'icon' => 'bi-graph-up', 'column' => 'col-lg-6']
    ],
    'tables' => [
        ['body_id' => 'dhdCasesBody', 'title' => 'Active Discipline Cases', 'columns' => ['Student', 'Case', 'Priority', 'Status'], 'route' => 'discipline_cases', 'column' => 'col-xl-6'],
        ['body_id' => 'dhdEventsBody', 'title' => 'Upcoming Meetings & Events', 'columns' => ['Event', 'Date', 'Type', 'Status'], 'route' => 'parent_meetings', 'column' => 'col-xl-6']
    ],
    'quick_actions' => [
        ['label' => 'Log Discipline Case', 'route' => 'log_discipline_case', 'icon' => 'bi-plus-circle'],
        ['label' => 'All Cases', 'route' => 'discipline_cases', 'icon' => 'bi-folder2-open'],
        ['label' => 'Attendance Reports', 'route' => 'attendance_reports', 'icon' => 'bi-clipboard-data'],
        ['label' => 'Refer to Counseling', 'route' => 'student_counseling', 'icon' => 'bi-heart-pulse']
    ],
];

require __DIR__ . '/partials/role_dashboard_shell.php';
