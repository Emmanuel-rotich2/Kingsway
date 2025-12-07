<?php
/**
 * Create test users for each role
 * Creates users with format: test_<rolename_lowercase_underscored>
 * Email: testrole@kingsway.ac.ke format
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../database/Database.php';

use App\Database\Database;

$db = Database::getInstance()->getConnection();

// Get all roles
$rolesStmt = $db->prepare('SELECT id, name FROM roles ORDER BY id');
$rolesStmt->execute();
$roles = $rolesStmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($roles)) {
    echo "No roles found\n";
    exit(1);
}

echo "Creating test users for " . count($roles) . " roles...\n\n";

$password = password_hash('testpass', PASSWORD_BCRYPT);
$created = 0;
$failed = 0;

foreach ($roles as $role) {
    try {
        // Convert role name to username format
        $roleName = strtolower($role['name']);
        $roleName = str_replace(['/', ' - '], '_', $roleName);
        $roleName = preg_replace('/[^a-z0-9_]/', '', $roleName);
        $username = 'test_' . $roleName;

        // Create email
        $emailName = str_replace('_', '', $roleName);
        $email = $emailName . '@kingsway.ac.ke';

        // Insert user
        $insertStmt = $db->prepare(
            'INSERT INTO users (username, email, password, first_name, last_name, role_id, status, created_at, updated_at) 
             VALUES (?, ?, ?, ?, ?, ?, "active", NOW(), NOW())'
        );

        $firstName = 'Test';
        $lastName = str_replace('_', ' ', ucfirst($roleName));

        $insertStmt->execute([
            $username,
            $email,
            $password,
            $firstName,
            $lastName,
            $role['id']
        ]);

        echo "[+] Created: $username (" . $role['name'] . ")\n";
        $created++;

    } catch (Exception $e) {
        echo "[-] Failed: " . $role['name'] . " - " . $e->getMessage() . "\n";
        $failed++;
    }
}

echo "\n" . str_repeat('=', 60) . "\n";
echo "SUMMARY - Test Users Created\n";
echo str_repeat('=', 60) . "\n";
echo "   Created: $created\n";
echo "   Failed:  $failed\n";
echo "   Total:   " . ($created + $failed) . "\n";
echo str_repeat('=', 60) . "\n";

if ($failed === 0) {
    echo "All test users created successfully!\n";
    exit(0);
} else {
    echo "Some users failed to create\n";
    exit(1);
}
