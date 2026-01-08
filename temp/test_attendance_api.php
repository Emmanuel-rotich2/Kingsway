<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../database/Database.php';
require_once __DIR__ . '/../api/services/DirectorAnalyticsService.php';

use App\API\Services\DirectorAnalyticsService;

try {
    $service = new DirectorAnalyticsService();
    $result = $service->getAttendanceTrends();
    
    echo "=== Attendance Trends API Test ===\n\n";
    
    echo "1. Data (30-day trend): " . count($result['data']) . " records\n";
    if (!empty($result['data'])) {
        $sample = $result['data'][0];
        echo "   Sample: " . $sample['date'] . " | Total: " . $sample['total'] . " | Present: " . $sample['present_count'] . " | Absent: " . $sample['absent_count'] . "\n";
    }
    
    echo "\n2. Absent Students Today: " . count($result['absent_students']) . " records\n";
    if (!empty($result['absent_students'])) {
        $sample = $result['absent_students'][0];
        echo "   Sample: " . $sample['name'] . " | " . $sample['class'] . "\n";
    }
    
    echo "\n3. Absent Staff Today: " . count($result['absent_staff']) . " records\n";
    if (!empty($result['absent_staff'])) {
        $sample = $result['absent_staff'][0];
        echo "   Sample: " . $sample['name'] . " | " . $sample['department'] . "\n";
    }
    
    echo "\n4. Summary:\n";
    echo "   Students - Total Marked: " . $result['summary']['students']['total_marked'] . "\n";
    echo "   Students - Present: " . $result['summary']['students']['present'] . "\n";
    echo "   Students - Absent: " . $result['summary']['students']['absent'] . "\n";
    echo "   Students - Late: " . $result['summary']['students']['late'] . "\n";
    echo "   Staff - Total Marked: " . $result['summary']['staff']['total_marked'] . "\n";
    echo "   Staff - Present: " . $result['summary']['staff']['present'] . "\n";
    echo "   Staff - Absent: " . $result['summary']['staff']['absent'] . "\n";
    echo "   Staff - Late: " . $result['summary']['staff']['late'] . "\n";
    
    echo "\n=== SUCCESS ===\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
