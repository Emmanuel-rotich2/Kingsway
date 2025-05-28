<?php
// filepath: /home/opt/lampp/htdocs/Kingsway/api/add_student.php
require_once __DIR__ . '/../config/db_connection.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $conn->real_escape_string($_POST['name']);
    $class = $conn->real_escape_string($_POST['class']);
    $conn->query("INSERT INTO students (name, class) VALUES ('$name', '$class')");
    echo json_encode(['success' => true]);
}
?>