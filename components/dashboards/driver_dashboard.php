<?php
/**
 * Kingsway role dashboard component.
 * Presentation only; data is supplied by the matching named JS controller.
 */
$dashboardConfig = [
    'root_id' => 'driverDashboard',
    'title' => 'Driver Dashboard',
    'subtitle' => 'Your assigned vehicle, route, schedule and passenger manifest.',
    'icon' => 'bi-bus-front',
    'controller_file' => 'driver_dashboard.js',
    'cards' => [
        ['id' => 'drvVehicle', 'label' => 'Assigned Vehicle', 'icon' => 'bi-bus-front', 'colour' => 'dsc-blue', 'subtitle_id' => 'drvVehicleSub'],
        ['id' => 'drvRoute', 'label' => 'Active Route', 'icon' => 'bi-signpost-split', 'colour' => 'dsc-cyan', 'subtitle_id' => 'drvRouteSub'],
        ['id' => 'drvPassengers', 'label' => 'Passengers', 'icon' => 'bi-people', 'colour' => 'dsc-green', 'subtitle_id' => 'drvPassengersSub'],
        ['id' => 'drvIncidents', 'label' => 'Recent Incidents', 'icon' => 'bi-exclamation-triangle', 'colour' => 'dsc-orange', 'subtitle_id' => 'drvIncidentsSub']
    ],
    'charts' => [
        ['id' => 'drvManifestChart', 'title' => 'Passenger Distribution', 'icon' => 'bi-pie-chart', 'column' => 'col-lg-5'],
        ['id' => 'drvScheduleChart', 'title' => 'Weekly Trips', 'icon' => 'bi-bar-chart', 'column' => 'col-lg-7']
    ],
    'tables' => [
        ['body_id' => 'drvScheduleBody', 'title' => 'Upcoming Trips', 'columns' => ['Date', 'Time', 'Route', 'Vehicle'], 'route' => 'my_routes', 'column' => 'col-xl-6'],
        ['body_id' => 'drvManifestBody', 'title' => 'Current Manifest', 'columns' => ['Admission No.', 'Student', 'Pickup', 'Drop-off'], 'route' => 'transport_passengers', 'column' => 'col-xl-6']
    ],
    'quick_actions' => [
        ['label' => 'My Routes', 'route' => 'my_routes', 'icon' => 'bi-signpost-split'],
        ['label' => 'Passenger Manifest', 'route' => 'transport_passengers', 'icon' => 'bi-people'],
        ['label' => 'Vehicle Inspection', 'route' => 'my_vehicle', 'icon' => 'bi-clipboard-check'],
        ['label' => 'Report Incident', 'route' => 'manage_transport', 'icon' => 'bi-exclamation-octagon']
    ],
];

require __DIR__ . '/partials/role_dashboard_shell.php';
