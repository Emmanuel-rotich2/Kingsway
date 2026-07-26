<?php
/**
 * Kingsway role dashboard component.
 * Presentation only; data is supplied by the matching named JS controller.
 */
$dashboardConfig = [
    'root_id' => 'inventoryDashboard',
    'title' => 'Inventory Manager Dashboard',
    'subtitle' => 'Stock health, requisitions, expiry and inventory value.',
    'icon' => 'bi-box-seam',
    'controller_file' => 'store_manager_dashboard.js',
    'cards' => [
        ['id' => 'invItems', 'label' => 'Active Items', 'icon' => 'bi-boxes', 'colour' => 'dsc-blue', 'subtitle_id' => 'invItemsSub'],
        ['id' => 'invLowStock', 'label' => 'Low Stock', 'icon' => 'bi-exclamation-triangle', 'colour' => 'dsc-orange', 'subtitle_id' => 'invLowStockSub'],
        ['id' => 'invOutStock', 'label' => 'Out of Stock', 'icon' => 'bi-x-octagon', 'colour' => 'dsc-red', 'subtitle_id' => 'invOutStockSub'],
        ['id' => 'invValue', 'label' => 'Inventory Value', 'icon' => 'bi-currency-exchange', 'colour' => 'dsc-green', 'subtitle_id' => 'invValueSub']
    ],
    'charts' => [
        ['id' => 'invCategoryChart', 'title' => 'Stock by Category', 'icon' => 'bi-pie-chart', 'column' => 'col-lg-5'],
        ['id' => 'invStatusChart', 'title' => 'Stock Health', 'icon' => 'bi-bar-chart', 'column' => 'col-lg-7']
    ],
    'tables' => [
        ['body_id' => 'invLowStockBody', 'title' => 'Low-stock Items', 'columns' => ['Item', 'Category', 'On Hand', 'Minimum'], 'route' => 'manage_inventory', 'column' => 'col-xl-6'],
        ['body_id' => 'invRequisitionsBody', 'title' => 'Pending Requisitions', 'columns' => ['Reference', 'Department', 'Priority', 'Required'], 'route' => 'manage_requisitions', 'column' => 'col-xl-6']
    ],
    'quick_actions' => [
        ['label' => 'Inventory Items', 'route' => 'manage_inventory', 'icon' => 'bi-boxes'],
        ['label' => 'Stock Count', 'route' => 'manage_inventory', 'icon' => 'bi-clipboard-check'],
        ['label' => 'Requisitions', 'route' => 'manage_requisitions', 'icon' => 'bi-card-checklist'],
        ['label' => 'Suppliers', 'route' => 'vendors', 'icon' => 'bi-truck']
    ],
];

require __DIR__ . '/partials/role_dashboard_shell.php';
