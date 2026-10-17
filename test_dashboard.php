<?php

require_once 'vendor/autoload.php';

$db = \App\Database\Database::getInstance()->getConnection();

// Instantiate the service
$service = new \App\API\Services\SchoolAdminAnalyticsService($db);

echo "=== Testing getActiveStudentsStats ===\n";
var_dump($service->getActiveStudentsStats());

echo "\n=== Testing getTeachingStaffStats ===\n";
var_dump($service->getTeachingStaffStats());

echo "\n=== Testing getStaffActivitiesStats ===\n";
var_dump($service->getStaffActivitiesStats());

echo "\n=== Testing getClassTimetablesStats ===\n";
var_dump($service->getClassTimetablesStats());

echo "\n=== Testing getDailyAttendanceStats ===\n";
var_dump($service->getDailyAttendanceStats());

echo "\n=== Testing getAnnouncementsStats ===\n";
var_dump($service->getAnnouncementsStats());

echo "\n=== Testing getStudentAdmissionsStats ===\n";
var_dump($service->getStudentAdmissionsStats());

echo "\n=== Testing getStaffLeavesStats ===\n";
var_dump($service->getStaffLeavesStats());

echo "\n=== Testing getClassDistributionStats ===\n";
var_dump($service->getClassDistributionStats());

echo "\n=== Testing getSystemStatus ===\n";
var_dump($service->getSystemStatus());