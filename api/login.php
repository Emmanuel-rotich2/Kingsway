<?php
// filepath: /home/opt/lampp/htdocs/Kingsway/api/login.php
session_start();
require_once __DIR__ . '/../config/db_connection.php';

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

$stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username=? LIMIT 1");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($user && password_verify($password, $user['password'])) {
    $_SESSION['loggedin'] = true;
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
}
?>