<?php
// Quick test file to see what headers the API receives

header('Content-Type: application/json');

$allHeaders = function_exists('getallheaders') ? getallheaders() : [];

$response = [
    'received_headers' => $allHeaders,
    'has_authorization' => isset($allHeaders['Authorization']),
    'authorization_value' => $allHeaders['Authorization'] ?? null,
    'server_http_authorization' => $_SERVER['HTTP_AUTHORIZATION'] ?? null,
    'request_uri' => $_SERVER['REQUEST_URI'],
    'request_method' => $_SERVER['REQUEST_METHOD']
];

echo json_encode($response, JSON_PRETTY_PRINT);
