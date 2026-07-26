<?php
/**
 * Kingsway role dashboard component.
 * Presentation only; data is supplied by the matching named JS controller.
 */
$dashboardConfig = [
    'root_id' => 'cateringDashboard',
    'title' => 'Catering Manager Dashboard',
    'subtitle' => 'Today’s meals, serving readiness, food stock and consumption.',
    'icon' => 'bi-cup-hot',
    'controller_file' => 'catering_manager_cook_lead_dashboard.js',
    'cards' => [
        ['id' => 'catMeals', 'label' => 'Meals Planned Today', 'icon' => 'bi-list-check', 'colour' => 'dsc-blue', 'subtitle_id' => 'catMealsSub'],
        ['id' => 'catServings', 'label' => 'Planned Servings', 'icon' => 'bi-people', 'colour' => 'dsc-cyan', 'subtitle_id' => 'catServingsSub'],
        ['id' => 'catPrepared', 'label' => 'Prepared Meals', 'icon' => 'bi-check2-circle', 'colour' => 'dsc-green', 'subtitle_id' => 'catPreparedSub'],
        ['id' => 'catLowStock', 'label' => 'Low Food Stock', 'icon' => 'bi-exclamation-triangle', 'colour' => 'dsc-orange', 'subtitle_id' => 'catLowStockSub']
    ],
    'charts' => [
        ['id' => 'catMealChart', 'title' => 'Meal Readiness', 'icon' => 'bi-bar-chart', 'column' => 'col-lg-6'],
        ['id' => 'catConsumptionChart', 'title' => 'Serving Progress Today', 'icon' => 'bi-graph-up', 'column' => 'col-lg-6']
    ],
    'tables' => [
        ['body_id' => 'catMenuBody', 'title' => 'Today’s Meal Plan', 'columns' => ['Meal', 'Item', 'Servings', 'Status'], 'route' => 'catering_boarding_students', 'column' => 'col-xl-6'],
        ['body_id' => 'catStockBody', 'title' => 'Food Stock Attention', 'columns' => ['Item', 'Category', 'On Hand', 'Minimum'], 'route' => 'food_store', 'column' => 'col-xl-6']
    ],
    'quick_actions' => [
        ['label' => 'Today’s Menu', 'route' => 'catering_boarding_students', 'icon' => 'bi-card-list'],
        ['label' => 'Food Store', 'route' => 'food_store', 'icon' => 'bi-warehouse'],
        ['label' => 'Meal Allocations', 'route' => 'catering_boarding_students', 'icon' => 'bi-people'],
        ['label' => 'Consumption Records', 'route' => 'food_store', 'icon' => 'bi-clipboard-data']
    ],
];

require __DIR__ . '/partials/role_dashboard_shell.php';
