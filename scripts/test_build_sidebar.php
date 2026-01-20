<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../api/services/MenuBuilderService.php';
use App\API\Services\MenuBuilderService;

$m = MenuBuilderService::getInstance();
$perms = ['finance_view', 'manage_payments', 'bank_accounts_view', 'bank_transactions_view', 'mpesa_view', 'payroll_view', 'payslips_view', 'vendors_manage', 'purchase_orders_manage', 'finance_reports_view', 'fee_structure_manage'];
$s = $m->buildSidebarForUser(9, 10, $perms);
print_r($s);
