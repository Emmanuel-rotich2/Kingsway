<?php
/**
 * Kingsway role dashboard component.
 * Presentation only; data is supplied by the matching named JS controller.
 */
$dashboardConfig = [
    'root_id' => 'accountantDashboard',
    'title' => 'School Accountant Dashboard',
    'subtitle' => 'Collections, balances, reconciliation and finance action queues.',
    'icon' => 'bi-cash-stack',
    'controller_file' => 'school_accountant_dashboard.js',
    'cards' => [
        ['id' => 'accCollected', 'label' => 'Collected This Month', 'icon' => 'bi-cash-coin', 'colour' => 'dsc-green', 'subtitle_id' => 'accCollectedSub'],
        ['id' => 'accOutstanding', 'label' => 'Outstanding Fees', 'icon' => 'bi-exclamation-circle', 'colour' => 'dsc-red', 'subtitle_id' => 'accOutstandingSub'],
        ['id' => 'accReconciliation', 'label' => 'Reconciliation Rate', 'icon' => 'bi-check2-circle', 'colour' => 'dsc-blue', 'subtitle_id' => 'accReconciliationSub'],
        ['id' => 'accPendingExpenses', 'label' => 'Pending Expenses', 'icon' => 'bi-receipt', 'colour' => 'dsc-orange', 'subtitle_id' => 'accPendingExpensesSub']
    ],
    'charts' => [
        ['id' => 'accCollectionChart', 'title' => 'Collection Trend', 'icon' => 'bi-graph-up', 'column' => 'col-lg-7'],
        ['id' => 'accMethodChart', 'title' => 'Payments by Method', 'icon' => 'bi-pie-chart', 'column' => 'col-lg-5']
    ],
    'tables' => [
        ['body_id' => 'accTransactionsBody', 'title' => 'Recent Transactions', 'columns' => ['Reference', 'Student', 'Method', 'Amount', 'Date'], 'route' => 'manage_payments', 'column' => 'col-xl-8'],
        ['body_id' => 'accAlertsBody', 'title' => 'Finance Attention', 'columns' => ['Item', 'Count / Amount', 'Status'], 'route' => 'finance_reports', 'column' => 'col-xl-4']
    ],
    'quick_actions' => [
        ['label' => 'Record Payment', 'route' => 'manage_payments', 'icon' => 'bi-plus-circle'],
        ['label' => 'M-Pesa Reconciliation', 'route' => 'unmatched_payments', 'icon' => 'bi-phone'],
        ['label' => 'Bank Accounts', 'route' => 'bank_accounts', 'icon' => 'bi-bank'],
        ['label' => 'Financial Reports', 'route' => 'finance_reports', 'icon' => 'bi-file-earmark-bar-graph']
    ],
];

require __DIR__ . '/partials/role_dashboard_shell.php';
