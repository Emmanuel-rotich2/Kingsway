<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

use App\API\Modules\students\StudentsAPI;
use App\API\Includes\BaseAPI;

// Handle CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Initialize API
$api = new StudentsAPI();

// Get student ID from URL
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$segments = explode('/', trim($path, '/'));
$id = end($segments);

if (!$id) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Student ID is required'
    ]);
    exit;
}

// Get student QR info
$result = $api->getQRInfo($id);

// Return JSON response
header('Content-Type: application/json');
echo json_encode($result); 