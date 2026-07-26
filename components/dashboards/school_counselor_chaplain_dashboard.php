<?php
/**
 * Kingsway role dashboard component.
 * Presentation only; data is supplied by the matching named JS controller.
 */
$dashboardConfig = [
    'root_id' => 'chaplainDashboard',
    'title' => 'Chaplain / Counselor Dashboard',
    'subtitle' => 'Counseling cases, follow-ups, wellbeing and pastoral activities.',
    'icon' => 'bi-heart-pulse',
    'controller_file' => 'school_counselor_chaplain_dashboard.js',
    'cards' => [
        ['id' => 'chpOpenCases', 'label' => 'Open Cases', 'icon' => 'bi-folder2-open', 'colour' => 'dsc-blue', 'subtitle_id' => 'chpOpenCasesSub'],
        ['id' => 'chpUrgent', 'label' => 'Urgent Cases', 'icon' => 'bi-exclamation-triangle', 'colour' => 'dsc-red', 'subtitle_id' => 'chpUrgentSub'],
        ['id' => 'chpFollowUps', 'label' => 'Follow-ups Due', 'icon' => 'bi-calendar-check', 'colour' => 'dsc-orange', 'subtitle_id' => 'chpFollowUpsSub'],
        ['id' => 'chpSessions', 'label' => 'Sessions This Month', 'icon' => 'bi-chat-heart', 'colour' => 'dsc-green', 'subtitle_id' => 'chpSessionsSub']
    ],
    'charts' => [
        ['id' => 'chpTypeChart', 'title' => 'Cases by Type', 'icon' => 'bi-pie-chart', 'column' => 'col-lg-5'],
        ['id' => 'chpTrendChart', 'title' => 'Counseling Trend', 'icon' => 'bi-graph-up', 'column' => 'col-lg-7']
    ],
    'tables' => [
        ['body_id' => 'chpCasesBody', 'title' => 'Active Cases', 'columns' => ['Case', 'Student', 'Type', 'Priority', 'Status'], 'route' => 'student_counseling', 'column' => 'col-xl-7'],
        ['body_id' => 'chpFollowUpBody', 'title' => 'Upcoming Follow-ups', 'columns' => ['Student', 'Case', 'Date', 'Status'], 'route' => 'student_counseling', 'column' => 'col-xl-5']
    ],
    'quick_actions' => [
        ['label' => 'Counseling Cases', 'route' => 'student_counseling', 'icon' => 'bi-heart-pulse'],
        ['label' => 'New Session', 'route' => 'student_counseling', 'icon' => 'bi-plus-circle'],
        ['label' => 'Student Welfare', 'route' => 'student_welfare', 'icon' => 'bi-person-hearts'],
        ['label' => 'Announcements', 'route' => 'manage_announcements', 'icon' => 'bi-megaphone']
    ],
];

require __DIR__ . '/partials/role_dashboard_shell.php';
