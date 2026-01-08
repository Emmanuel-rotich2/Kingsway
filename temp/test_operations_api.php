<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

use App\API\Services\HeadteacherAnalyticsService;
use App\API\Services\DirectorAnalyticsService;

echo "=== Operations & Compliance API Test ===\n\n";

// Test Admissions Queue
$htService = new HeadteacherAnalyticsService();
$admissions = $htService->getPendingAdmissions();
echo "1. Admissions Queue: " . count($admissions['data']) . " records\n";
if (!empty($admissions['data'])) {
    $first = $admissions['data'][0];
    echo "   Fields: " . implode(", ", array_keys($first)) . "\n";
    echo "   Sample: {$first['student_name']} | {$first['class_applied']} | {$first['parent_name']} | {$first['contact']} | {$first['status']} | {$first['days_pending']} days\n";
}

// Test Discipline Cases
$discipline = $htService->getDisciplineCases();
echo "\n2. Discipline Cases: " . count($discipline['data']) . " records\n";
if (!empty($discipline['data'])) {
    $first = $discipline['data'][0];
    echo "   Fields: " . implode(", ", array_keys($first)) . "\n";
    echo "   Sample: {$first['student_name']} | {$first['class_name']} | {$first['violation']} | {$first['severity']} | {$first['status']} | {$first['incident_date']}\n";
}

// Test Audit Logs
$dirService = new DirectorAnalyticsService();
$risks = $dirService->getOperationalRisks();
echo "\n3. Audit Logs: " . count($risks['audit_logs']) . " records\n";
if (!empty($risks['audit_logs'])) {
    $first = $risks['audit_logs'][0];
    echo "   Fields: " . implode(", ", array_keys($first)) . "\n";
    echo "   Sample: {$first['action']} | {$first['entity']} | {$first['user_name']} | {$first['ip_address']} | {$first['created_at']}\n";
}

echo "\n=== SUCCESS ===\n";
