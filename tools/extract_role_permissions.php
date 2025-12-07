<?php
/**
 * Extract role permissions from database to populate dashboards.php
 * This script queries the role_permissions table and generates a structured
 * output showing which permissions each role has.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../database/Database.php';

use App\Database\Database;

try {
    $db = Database::getInstance()->getConnection();

    // Get all roles
    $rolesStmt = $db->query("
        SELECT id, name, description 
        FROM roles 
        ORDER BY id
    ");
    $roles = $rolesStmt->fetchAll(PDO::FETCH_ASSOC);

    echo "=== ROLE PERMISSIONS MAPPING ===\n\n";

    foreach ($roles as $role) {
        echo "Role ID {$role['id']}: {$role['name']}\n";
        echo "Description: {$role['description']}\n";

        // Get permissions for this role
        $permStmt = $db->prepare("
            SELECT p.code, p.name, p.description, p.category
            FROM role_permissions rp
            JOIN permissions p ON rp.permission_id = p.id
            WHERE rp.role_id = :role_id
            ORDER BY p.category, p.code
        ");
        $permStmt->execute(['role_id' => $role['id']]);
        $permissions = $permStmt->fetchAll(PDO::FETCH_ASSOC);

        echo "Total Permissions: " . count($permissions) . "\n";

        if (count($permissions) > 0) {
            // Group by category
            $grouped = [];
            foreach ($permissions as $perm) {
                $category = $perm['category'] ?? 'general';
                if (!isset($grouped[$category])) {
                    $grouped[$category] = [];
                }
                $grouped[$category][] = $perm;
            }

            echo "\nPermissions by Category:\n";
            foreach ($grouped as $category => $perms) {
                echo "  {$category}:\n";
                foreach ($perms as $perm) {
                    echo "    - {$perm['code']} ({$perm['name']})\n";
                }
            }
        }

        echo "\n" . str_repeat("-", 80) . "\n\n";
    }

    // Now generate suggested menu items based on permission categories
    echo "\n\n=== SUGGESTED MENU ITEMS BY PERMISSION CATEGORY ===\n\n";

    $categoryStmt = $db->query("
        SELECT DISTINCT category 
        FROM permissions 
        WHERE category IS NOT NULL AND category != ''
        ORDER BY category
    ");
    $categories = $categoryStmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($categories as $category) {
        echo "Category: {$category}\n";

        $permStmt = $db->prepare("
            SELECT code, name 
            FROM permissions 
            WHERE category = :category
            AND code LIKE '%_view%'
            ORDER BY code
        ");
        $permStmt->execute(['category' => $category]);
        $viewPerms = $permStmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($viewPerms) > 0) {
            echo "  Suggested menu item for this category:\n";
            echo "  [\n";
            echo "    'label' => '" . ucwords(str_replace('_', ' ', $category)) . "',\n";
            echo "    'route' => '{$category}',\n";
            echo "    'icon' => 'bi-TODO',\n";
            echo "    'permissions' => ['" . implode("', '", array_column($viewPerms, 'code')) . "'],\n";
            echo "  ],\n";
        }

        echo "\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
