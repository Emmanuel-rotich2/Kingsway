<?php
require_once __DIR__ . '/../config/db_connection.php';

// Define required columns and their SQL definitions
$required_columns = [
    'id'         => "INT AUTO_INCREMENT PRIMARY KEY",
    'username'   => "VARCHAR(100) NOT NULL UNIQUE",
    'password'   => "VARCHAR(255) NOT NULL",
    'role'       => "VARCHAR(255) NOT NULL",
    'status'     => "ENUM('active','inactive') NOT NULL DEFAULT 'active'",
    'created_at' => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP"
];

// 1. Check if users table exists, if not, create it
$table_exists = false;
$res = $conn->query("SHOW TABLES LIKE 'users'");
if ($res && $res->num_rows > 0) {
    $table_exists = true;
} else {
    // Create table
    $columns_sql = [];
    foreach ($required_columns as $col => $def) {
        $columns_sql[] = "`$col` $def";
    }
    $sql = "CREATE TABLE users (" . implode(", ", $columns_sql) . ")";
    if ($conn->query($sql)) {
        echo "Created users table.<br>";
        $table_exists = true;
    } else {
        die("Failed to create users table: " . $conn->error);
    }
}

// 2. Check for missing columns and add them if needed
if ($table_exists) {
    $existing_cols = [];
    $res = $conn->query("SHOW COLUMNS FROM users");
    while ($row = $res->fetch_assoc()) {
        $existing_cols[] = $row['Field'];
    }
    foreach ($required_columns as $col => $def) {
        if (!in_array($col, $existing_cols)) {
            $sql = "ALTER TABLE users ADD COLUMN `$col` $def";
            if ($conn->query($sql)) {
                echo "Added column $col.<br>";
            } else {
                echo "Error adding column $col: " . $conn->error . "<br>";
            }
        }
    }
}

// 3. Insert or update users
$users = [
    [
        'username' => 'admin@kingsway.ac.ke',
        'password' => 'admin123',
        'role'     => 'admin',
        'status'   => 'active'
    ],
    [
        'username' => 'teacher@kingsway.ac.ke',
        'password' => 'teacher123',
        'role'     => 'teacher',
        'status'   => 'active'
    ],
    [
        'username' => 'accountant@kingsway.ac.ke',
        'password' => 'accountant123',
        'role'     => 'accountant',
        'status'   => 'active'
    ],
    [
        'username' => 'registrar@kingsway.ac.ke',
        'password' => 'registrar123',
        'role'     => 'registrar',
        'status'   => 'active'
    ],
    [
        'username' => 'parent@kingsway.ac.ke',
        'password' => 'parent123',
        'role'     => 'parent',
        'status'   => 'active'
    ],
    [
        'username' => 'student@kingsway.ac.ke',
        'password' => 'student123',
        'role'     => 'student',
        'status'   => 'active'
    ],
    [
        'username' => 'staff@kingsway.ac.ke',
        'password' => 'staff123',
        'role'     => 'staff',
        'status'   => 'active'
    ]
];

foreach ($users as $user) {
    $hash = password_hash($user['password'], PASSWORD_DEFAULT);
    // Check if user exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE username=? LIMIT 1");
    $stmt->bind_param("s", $user['username']);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        // Update existing user
        $stmt->close();
        $stmt2 = $conn->prepare("UPDATE users SET password=?, role=?, status=? WHERE username=?");
        $stmt2->bind_param("ssss", $hash, $user['role'], $user['status'], $user['username']);
        if ($stmt2->execute()) {
            echo "Updated: {$user['username']}<br>";
        } else {
            echo "Error updating {$user['username']}: " . $stmt2->error . "<br>";
        }
        $stmt2->close();
    } else {
        // Insert new user
        $stmt->close();
        $stmt2 = $conn->prepare("INSERT INTO users (username, password, role, status, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt2->bind_param("ssss", $user['username'], $hash, $user['role'], $user['status']);
        if ($stmt2->execute()) {
            echo "Inserted: {$user['username']}<br>";
        } else {
            echo "Error inserting {$user['username']}: " . $stmt2->error . "<br>";
        }
        $stmt2->close();
    }
}
$conn->close();
?>