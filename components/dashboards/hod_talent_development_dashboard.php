<?php
/**
 * Kingsway role dashboard component.
 * Presentation only; data is supplied by the matching named JS controller.
 */
$dashboardConfig = [
    'root_id' => 'talentDashboard',
    'title' => 'Talent Development Dashboard',
    'subtitle' => 'Activities, participation, schedules and programme progress.',
    'icon' => 'bi-trophy',
    'controller_file' => 'hod_talent_development_dashboard.js',
    'cards' => [
        ['id' => 'talActivities', 'label' => 'Active Activities', 'icon' => 'bi-trophy', 'colour' => 'dsc-blue', 'subtitle_id' => 'talActivitiesSub'],
        ['id' => 'talParticipants', 'label' => 'Student Participants', 'icon' => 'bi-people', 'colour' => 'dsc-cyan', 'subtitle_id' => 'talParticipantsSub'],
        ['id' => 'talStaff', 'label' => 'Completed Activities', 'icon' => 'bi-check2-circle', 'colour' => 'dsc-purple', 'subtitle_id' => 'talStaffSub'],
        ['id' => 'talUpcoming', 'label' => 'Upcoming Sessions', 'icon' => 'bi-calendar-event', 'colour' => 'dsc-green', 'subtitle_id' => 'talUpcomingSub']
    ],
    'charts' => [
        ['id' => 'talCategoryChart', 'title' => 'Activities by Category', 'icon' => 'bi-pie-chart', 'column' => 'col-lg-5'],
        ['id' => 'talParticipationChart', 'title' => 'Participation by Activity', 'icon' => 'bi-bar-chart', 'column' => 'col-lg-7']
    ],
    'tables' => [
        ['body_id' => 'talActivitiesBody', 'title' => 'Current Activities', 'columns' => ['Activity', 'Category', 'Dates', 'Status'], 'route' => 'manage_activities', 'column' => 'col-xl-7'],
        ['body_id' => 'talScheduleBody', 'title' => 'Activity Schedule', 'columns' => ['Activity', 'Day', 'Time', 'Venue'], 'route' => 'school_events', 'column' => 'col-xl-5']
    ],
    'quick_actions' => [
        ['label' => 'Manage Activities', 'route' => 'manage_activities', 'icon' => 'bi-trophy'],
        ['label' => 'School Events', 'route' => 'school_events', 'icon' => 'bi-calendar-event'],
        ['label' => 'Participants', 'route' => 'manage_activities', 'icon' => 'bi-people'],
        ['label' => 'Resources', 'route' => 'manage_activities', 'icon' => 'bi-box-seam']
    ],
];

require __DIR__ . '/partials/role_dashboard_shell.php';
