<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../api/includes/helpers.php';

use App\API\Modules\finance\ReportingManager;

$reporting = new ReportingManager();

// call financial dashboard
$fin = $reporting->getFinancialDashboard(['academic_year' => date('Y')]);
print_r($fin);

// call trends
$trends = $reporting->getFeeCollectionTrends(['date_from' => date('Y-01-01'), 'date_to' => date('Y-m-d')]);
print_r($trends);

// call cash flow
$cash = $reporting->getCashFlowStatement(['date_from' => date('Y-01-01'), 'date_to' => date('Y-m-d')]);
print_r($cash);

// call recent transactions
$recent = $reporting->getRecentTransactions(5);
print_r($recent);

