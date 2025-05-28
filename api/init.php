<?php
require_once __DIR__ . '/../config/db_connection.php';

// Define users to insert
$users = [
    [
        'username' => 'admin@kingsway.ac.ke',
        'password' => 'admin123', // plain password, will be hashed
        'role'     => 'admin',
        'status'   => 'Active'
    ],
    [
        'username' => 'teacher@kingsway.ac.ke',
        'password' => 'teacher123',
        'role'     => 'teacher',
        'status'   => 'Active'
    ],
    [
        'username' => 'accountant@kingsway.ac.ke',
        'password' => 'accountant123',
        'role'     => 'accountant',
        'status'   => 'Active'
    ],
    [
        'username' => 'registrar@kingsway.ac.ke',
        'password' => 'registrar123',
        'role'     => 'registrar',
        'status'   => 'Active'
    ],
    [
        'username' => 'parent@kingsway.ac.ke',
        'password' => 'parent123',
        'role'     => 'parent',
        'status'   => 'Active'
    ],
    [
        'username' => 'student@kingsway.ac.ke',
        'password' => 'student123',
        'role'     => 'student',
        'status'   => 'Active'
    ],
    [
        'username' => 'staff@kingsway.ac.ke',
        'password' => 'staff123',
        'role'     => 'staff',
        'status'   => 'Active'
    ]
];

foreach ($users as $user) {
    $hash = password_hash($user['password'], PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (username, password, role, status, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("ssss", $user['username'], $hash, $user['role'], $user['status']);
    if ($stmt->execute()) {
        echo "Inserted: {$user['username']}<br>";
    } else {
        echo "Error inserting {$user['username']}: " . $stmt->error . "<br>";
    }
    $stmt->close();
}
$conn->close();
?>