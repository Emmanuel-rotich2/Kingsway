<?php
header("Content-Type: application/json");

require "../config/db.php";
require "../manage_subjects.php";
$db = (new Database())->connect();
$subject = new Subject($db);

$stmt = $subject->read();
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($subjects);
