<?php
require_once __DIR__ . '/../vendor/autoload.php';
use App\Config\Config;
Config::init();
require_once __DIR__ . '/../../vendor/autoload.php';
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

header('Content-Type: application/json');

// JWT check
$headers = getallheaders();
if(!isset($headers['Authorization'])){
    http_response_code(401);
    echo json_encode(['error'=>'Authorization header missing']); exit;
}
$jwt = str_replace('Bearer ','',$headers['Authorization']);

try {
    $decoded = JWT::decode($jwt, new Key(JWT_SECRET,'HS256'));
} catch(Exception $e){
    http_response_code(401);
    echo json_encode(['error'=>'Invalid token']); exit;
}

try {
    $stmt = $conn->query("SELECT * FROM categories ORDER BY name ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['categories'=>$categories]);
} catch(PDOException $e){
    http_response_code(500);
    echo json_encode(['error'=>$e->getMessage()]);
}
