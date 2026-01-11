<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../database/Database.php';
use App\Database\Database;
$db = Database::getInstance()->getConnection();
$stmt = $db->prepare('SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1');
$stmt->execute(['test_headteacher', 'test_headteacher']);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$pw = 'Pass123' . chr(33) . '@';
var_dump($user['username'] ?? null);
var_dump(isset($user['password']));
var_dump(substr($user['password'], 0, 10));
var_dump(password_verify($pw, $user['password']));
