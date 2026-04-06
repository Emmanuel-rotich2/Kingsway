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
    http_response_code(401); echo json_encode(['error'=>'Authorization header missing']); exit;
}
$jwt = str_replace('Bearer ','',$headers['Authorization']);
try {
    $decoded = JWT::decode($jwt,new Key(JWT_SECRET,'HS256'));
} catch(Exception $e){
    http_response_code(401); echo json_encode(['error'=>'Invalid token']); exit;
}

$id = $_GET['id'] ?? null;
if(!$id){
    http_response_code(400); echo json_encode(['error'=>'Activity ID required']); exit;
}

try {
    $stmt = $conn->prepare("DELETE FROM activities WHERE id=:id");
    $stmt->execute(['id'=>$id]);
    echo json_encode(['success'=>true]);
} catch(PDOException $e){
    http_response_code(500);
    echo json_encode(['error'=>$e->getMessage()]);
}
