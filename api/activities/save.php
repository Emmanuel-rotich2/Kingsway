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
    $decoded = JWT::decode($jwt, new Key(JWT_SECRET,'HS256'));
    $userId = $decoded->id;
} catch(Exception $e){
    http_response_code(401); echo json_encode(['error'=>'Invalid token']); exit;
}

// Read input
$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? null;

try {
    if($id){ // Update
        $stmt = $conn->prepare("UPDATE activities SET 
            title=:title, description=:description, category_id=:category_id,
            start_date=:start_date, end_date=:end_date, status=:status,
            max_participants=:max_participants, target_audience=:target_audience
            WHERE id=:id
        ");
        $stmt->execute([
            'title'=>$data['title'],
            'description'=>$data['description'],
            'category_id'=>$data['category_id'],
            'start_date'=>$data['start_date'],
            'end_date'=>$data['end_date'],
            'status'=>$data['status'],
            'max_participants'=>$data['max_participants'] ?? null,
            'target_audience'=>$data['target_audience'] ?? '',
            'id'=>$id
        ]);
    } else { // Insert
        $stmt = $conn->prepare("INSERT INTO activities 
            (title, description, category_id, start_date, end_date, status, max_participants, started_by, target_audience)
            VALUES (:title,:description,:category_id,:start_date,:end_date,:status,:max_participants,:started_by,:target_audience)
        ");
        $stmt->execute([
            'title'=>$data['title'],
            'description'=>$data['description'],
            'category_id'=>$data['category_id'],
            'start_date'=>$data['start_date'],
            'end_date'=>$data['end_date'],
            'status'=>$data['status'],
            'max_participants'=>$data['max_participants'] ?? null,
            'started_by'=>$userId,
            'target_audience'=>$data['target_audience'] ?? ''
        ]);
    }
    echo json_encode(['success'=>true]);
} catch(PDOException $e){
    http_response_code(500);
    echo json_encode(['error'=>$e->getMessage()]);
}
