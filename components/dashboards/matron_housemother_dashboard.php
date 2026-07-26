<?php
/**
 * Kingsway role dashboard component.
 * Presentation only; data is supplied by the matching named JS controller.
 */
$dashboardConfig = [
    'root_id' => 'boardingDashboard',
    'title' => 'Boarding Master Dashboard',
    'subtitle' => 'Dormitory occupancy, roll calls, permissions and welfare alerts.',
    'icon' => 'bi-house-heart',
    'controller_file' => 'matron_housemother_dashboard.js',
    'cards' => [
        ['id' => 'brdBoarders', 'label' => 'Active Boarders', 'icon' => 'bi-people', 'colour' => 'dsc-blue', 'subtitle_id' => 'brdBoardersSub'],
        ['id' => 'brdOccupancy', 'label' => 'Occupancy Rate', 'icon' => 'bi-house-door', 'colour' => 'dsc-cyan', 'subtitle_id' => 'brdOccupancySub'],
        ['id' => 'brdAbsent', 'label' => 'Absent / Unknown', 'icon' => 'bi-person-x', 'colour' => 'dsc-red', 'subtitle_id' => 'brdAbsentSub'],
        ['id' => 'brdNotes', 'label' => 'Urgent Welfare Notes', 'icon' => 'bi-exclamation-triangle', 'colour' => 'dsc-orange', 'subtitle_id' => 'brdNotesSub']
    ],
    'charts' => [
        ['id' => 'brdOccupancyChart', 'title' => 'Dormitory Occupancy', 'icon' => 'bi-bar-chart', 'column' => 'col-lg-6'],
        ['id' => 'brdRollCallChart', 'title' => 'Today’s Roll Call', 'icon' => 'bi-pie-chart', 'column' => 'col-lg-6']
    ],
    'tables' => [
        ['body_id' => 'brdRollCallsBody', 'title' => 'Roll-call Summary', 'columns' => ['Dormitory', 'Session', 'Present', 'Absent', 'Rate'], 'route' => 'boarding_roll_call', 'column' => 'col-xl-7'],
        ['body_id' => 'brdNotesBody', 'title' => 'Current Permissions & Exeats', 'columns' => ['Student', 'Type', 'Dates', 'Status'], 'route' => 'permissions_exeats', 'column' => 'col-xl-5']
    ],
    'quick_actions' => [
        ['label' => 'Roll Call', 'route' => 'boarding_roll_call', 'icon' => 'bi-check2-square'],
        ['label' => 'Dormitories', 'route' => 'dormitory_management', 'icon' => 'bi-house-door'],
        ['label' => 'Permissions & Exeats', 'route' => 'permissions_exeats', 'icon' => 'bi-box-arrow-right'],
        ['label' => 'Boarding Notes', 'route' => 'student_welfare', 'icon' => 'bi-journal-medical']
    ],
];

require __DIR__ . '/partials/role_dashboard_shell.php';
