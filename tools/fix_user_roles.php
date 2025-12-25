<?php
/**
 * Fix User Roles Assignment
 * 
 * This script assigns existing users to their roles based on users.role_id
 * and automatically copies all role permissions to user_permissions table.
 * 
 * Usage: php fix_user_roles.php
 */

require_once __DIR__ . '/../config/config.php';

echo "🔧 User Roles Fixer\n";
echo str_repeat("=", 70) . "\n\n";

try {
    // Connect to database
    $db = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Get users without role assignments
    $checkStmt = $db->prepare('
        SELECT u.id, u.username, u.email, u.role_id
        FROM users u
        LEFT JOIN user_roles ur ON u.id = ur.user_id
        WHERE ur.id IS NULL
        ORDER BY u.id
    ');
    $checkStmt->execute();
    $usersWithoutRoles = $checkStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($usersWithoutRoles)) {
        echo "✅ All users already have role assignments!\n";
        echo str_repeat("=", 70) . "\n";
        exit(0);
    }

    echo "📋 Found " . count($usersWithoutRoles) . " users without role assignments\n\n";

    $db->beginTransaction();
    $successCount = 0;
    $failCount = 0;

    foreach ($usersWithoutRoles as $user) {
        $userId = $user['id'];
        $roleId = $user['role_id'] ?? 1;

        echo "Processing: [{$userId}] {$user['username']} ({$user['email']})";

        // Check if role exists
        $roleCheckStmt = $db->prepare('SELECT id FROM roles WHERE id = ?');
        $roleCheckStmt->execute([$roleId]);
        if (!$roleCheckStmt->fetch()) {
            echo " ⚠️  (Role ID {$roleId} doesn't exist, skipping)\n";
            $failCount++;
            continue;
        }

        try {
            // Step 1: Assign role to user
            $assignStmt = $db->prepare('
                INSERT INTO user_roles (user_id, role_id) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE user_id = user_id
            ');
            $assignStmt->execute([$userId, $roleId]);

            // Step 2: Copy all role permissions to user_permissions
            $permStmt = $db->prepare('
                INSERT IGNORE INTO user_permissions (user_id, permission_id, permission_type, granted_by, created_at)
                SELECT ?, permission_id, ?, ?, NOW()
                FROM role_permissions
                WHERE role_id = ?
            ');
            $permStmt->execute([$userId, 'grant', $roleId, $roleId]);
            $permsCopied = $permStmt->rowCount();

            echo " ✅ (Role assigned, {$permsCopied} permissions copied)\n";
            $successCount++;

        } catch (Exception $e) {
            echo " ❌ ({$e->getMessage()})\n";
            $failCount++;
        }
    }

    $db->commit();

    echo "\n" . str_repeat("=", 70) . "\n";
    echo "📊 Results:\n";
    echo "   ✅ Successfully assigned: {$successCount}\n";
    echo "   ⚠️  Failed/Skipped: {$failCount}\n";
    echo "   📈 Total processed: " . count($usersWithoutRoles) . "\n";
    echo str_repeat("=", 70) . "\n";

    if ($failCount === 0) {
        echo "🎉 All users have been assigned to their roles!\n";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
