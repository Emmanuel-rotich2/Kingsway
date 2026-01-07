<?php
include __DIR__ . '/../../config/db.php'; // DB connection

$stmt = $conn->prepare("SELECT * FROM activities ORDER BY activity_date DESC");
$stmt->execute();
$result = $stmt->get_result();

$activities = [];
while($row = $result->fetch_assoc()){
    $activities[] = $row;
}

echo json_encode($activities);
