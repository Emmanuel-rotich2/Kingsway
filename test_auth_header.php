<?php
// Simple test to check if Authorization header is received by PHP

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Test-Token');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$authHeader = null;
$methods = [];

// Method 1: getallheaders()
if (function_exists('getallheaders')) {
    $headers = getallheaders();
    $methods['getallheaders'] = $headers;
    if (isset($headers['Authorization'])) {
        $authHeader = $headers['Authorization'];
    }
}

// Method 2: $_SERVER['HTTP_AUTHORIZATION']
if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $methods['HTTP_AUTHORIZATION'] = $_SERVER['HTTP_AUTHORIZATION'];
    if (!$authHeader) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    }
}

// Method 3: Apache-specific
if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
    $methods['REDIRECT_HTTP_AUTHORIZATION'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    if (!$authHeader) {
        $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }
}

// All HTTP_* headers
$httpHeaders = [];
foreach ($_SERVER as $key => $value) {
    if (strpos($key, 'HTTP_') === 0) {
        $httpHeaders[$key] = $value;
    }
}

echo json_encode([
    'found_authorization' => $authHeader !== null,
    'authorization_value' => $authHeader ? substr($authHeader, 0, 50) . '...' : null,
    'methods_checked' => $methods,
    'all_http_headers' => $httpHeaders,
    'request_method' => $_SERVER['REQUEST_METHOD'],
    'request_uri' => $_SERVER['REQUEST_URI']
], JSON_PRETTY_PRINT);
