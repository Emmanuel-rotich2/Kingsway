<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../database/Database.php';
require_once __DIR__ . '/../api/services/DirectorAnalyticsService.php';

use App\API\Services\DirectorAnalyticsService;

try {
    $service = new DirectorAnalyticsService();
    $result = $service->getAttendanceTrends();
    
    // Simulate controller response
    $response = [
        'status' => 'success',
        'message' => 'Attendance trends retrieved',
        'data' => $result
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
