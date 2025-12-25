<?php
/**
 * Role Permissions Setup Script
 * 
 * This script checks and sets up role_permissions mappings.
 * If the table is empty, it can assign all permissions to specific roles.
 * 
 * Usage: php setup_role_permissions.php [action]
 * Actions: check, assign_all_to_admin, assign_by_role
 */

require_once __DIR__ . '/../config/config.php';

echo "🔧 Role Permissions Setup Tool\n";
echo str_repeat("=", 70) . "\n\n";

try {
    $db = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $action = $argv[1] ?? 'check';

    // Check current role_permissions status
    $checkStmt = $db->prepare('
        SELECT 
            COUNT(DISTINCT role_id) as role_count,
            COUNT(*) as total_mappings
        FROM role_permissions
    ');
    $checkStmt->execute();
    $status = $checkStmt->fetch(PDO::FETCH_ASSOC);

    echo "📊 Current Status:\n";
    echo "   Roles with permissions: " . $status['role_count'] . "\n";
    echo "   Total role-permission mappings: " . $status['total_mappings'] . "\n\n";

    if ($action === 'check') {
        // List all roles and their permission counts
        $rolesStmt = $db->prepare('
            SELECT r.id, r.name, COUNT(rp.permission_id) as perm_count
            FROM roles r
            LEFT JOIN role_permissions rp ON r.id = rp.role_id
            GROUP BY r.id
            ORDER BY r.id
        ');
        $rolesStmt->execute();
        $roles = $rolesStmt->fetchAll(PDO::FETCH_ASSOC);

        echo "📋 Roles and their permissions:\n";
        foreach ($roles as $role) {
            echo "   [{$role['id']}] {$role['name']}: {$role['perm_count']} permissions\n";
        }

        // List all permissions
        $permsStmt = $db->prepare('SELECT COUNT(*) as total FROM permissions');
        $permsStmt->execute();
        $perms = $permsStmt->fetch(PDO::FETCH_ASSOC);
        echo "\n📌 Total permissions available: " . $perms['total'] . "\n";

    } elseif ($action === 'assign_all_to_admin') {
        // Assign all permissions to admin role (usually role_id = 2)
        echo "🔒 Assigning all permissions to System Administrator role...\n";

        $adminRoleId = 2; // System Administrator

        $stmt = $db->prepare('
            INSERT IGNORE INTO role_permissions (role_id, permission_id)
            SELECT ?, id FROM permissions
        ');
        $result = $stmt->execute([$adminRoleId]);
        $inserted = $stmt->rowCount();

        echo "✅ Assigned $inserted permission(s) to System Administrator\n";

    } elseif ($action === 'assign_by_role') {
        // Assign permissions based on role naming convention
        echo "🎯 Assigning permissions based on role/permission naming patterns...\n";

        // Example: Assign academic-related permissions to teacher roles
        $assignments = [
            // 'teacher' roles get view_grades, manage_attendance, view_assignments
            'teacher' => ['view_grades', 'manage_attendance', 'view_assignments'],
            'admin' => ['*'],  // All permissions
            'accountant' => ['view_finance', 'manage_payments', 'view_reports'],
        ];

        foreach ($assignments as $rolePattern => $permPatterns) {
            // Find roles matching this pattern
            $roleStmt = $db->prepare("SELECT id FROM roles WHERE LOWER(name) LIKE ?");
            $roleStmt->execute(['%' . $rolePattern . '%']);
            $roles = $roleStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($roles as $role) {
                if (in_array('*', $permPatterns)) {
                    // Assign all permissions
                    $stmt = $db->prepare('
                        INSERT IGNORE INTO role_permissions (role_id, permission_id)
                        SELECT ?, id FROM permissions
                    ');
                    $stmt->execute([$role['id']]);
                } else {
                    // Assign specific permissions
                    $placeholders = implode(',', array_fill(0, count($permPatterns), '?'));
                    $stmt = $db->prepare("
                        INSERT IGNORE INTO role_permissions (role_id, permission_id)
                        SELECT ?, id FROM permissions WHERE LOWER(code) IN ($placeholders)
                    ");
                    $stmt->execute(array_merge([$role['id']], $permPatterns));
                }

                echo "   ✅ Role ID {$role['id']}: {$stmt->rowCount()} permissions assigned\n";
            }
        }

    } else {
        echo "❓ Unknown action: $action\n";
        echo "Available actions: check, assign_all_to_admin, assign_by_role\n";
    }

    echo "\n" . str_repeat("=", 70) . "\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
