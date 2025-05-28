<?php
// filepath: /home/opt/lampp/htdocs/Kingsway/api/students.php
require_once __DIR__ . '/../config/db_connection.php';
header('Content-Type: application/json');
$result = $conn->query("SELECT id, name, class FROM students");
$students = [];
while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}
echo json_encode($students);
?>