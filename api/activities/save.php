<?php
include __DIR__ . '/../../config/db.php';

$id = $_POST['id'] ?? null;
$name = $_POST['name'];
$category = $_POST['category'];
$date = $_POST['date'];
$participants = $_POST['participants'];
$status = $_POST['status'];
$description = $_POST['description'] ?? '';

if($id){ // Edit
    $stmt = $conn->prepare("UPDATE activities SET name=?, category=?, activity_date=?, participants=?, status=?, description=? WHERE id=?");
    $stmt->bind_param('sssissi', $name, $category, $date, $participants, $status, $description, $id);
    $stmt->execute();
    echo json_encode(['success'=>true, 'message'=>'Activity updated']);
}else{ // Add
    $stmt = $conn->prepare("INSERT INTO activities (name, category, activity_date, participants, status, description) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('sssiss', $name, $category, $date, $participants, $status, $description);
    $stmt->execute();
    echo json_encode(['success'=>true, 'message'=>'Activity added', 'id'=>$conn->insert_id]);
}
