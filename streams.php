<?php
include __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

$classId = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;

if ($classId > 0) {
    $stmt = $conn->prepare("SELECT id, stream_name FROM streams WHERE class_id = ? ORDER BY stream_name ASC");
    $stmt->execute([$classId]);
    $streams = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['streams' => $streams]);
} else {
    echo json_encode(['streams' => []]);
}
