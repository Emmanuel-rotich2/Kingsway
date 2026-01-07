<?php
require '../../config/db.php';

$id = $_POST['id'];
$stmt = $conn->prepare("DELETE FROM activities WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();

echo json_encode([
    "success" => true,
    "message" => "Activity deleted"
]);
