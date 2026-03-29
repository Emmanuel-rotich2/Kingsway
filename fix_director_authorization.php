<?php
/**
 * DATABASE FIX: Director Authorization Route Mapping
 *
 * Purpose: Populate role_routes with sidebar-assigned routes
 * so MenuBuilderService authorization filter passes
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/database/Database.php';

try {
    $db = Database::getInstance();

    echo "=== DATABASE AUTHORIZATION FIX ===\n\n";

    // Find Director role
    $stmt = $db->prepare("SELECT id, name FROM roles WHERE name = 'Director' LIMIT 1");
    $stmt->execute();
    $director = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$director) {
        echo "ERROR: Director role not found\n";
        exit(1);
    }

    $directorRoleId = $director['id'];
    echo "✓ Found Director role (ID: $directorRoleId)\n\n";

    // Backup current role_routes
    echo "SECTION 1: Backing up role_routes...\n";
    $db->exec("CREATE TABLE IF NOT EXISTS backup_role_routes_20260329_fix AS SELECT * FROM role_routes");
    echo "✓ Backup created: backup_role_routes_20260329_fix\n\n";

    // Analysis: Count sidebar items
    echo "SECTION 2: Analyzing current state...\n";
    $stmt = $db->prepare("
        SELECT COUNT(*) as count FROM role_sidebar_menus
        WHERE role_id = ?
    ");
    $stmt->execute([$directorRoleId]);
    $sidebarCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "✓ Sidebar items assigned to Director: $sidebarCount\n";

    // Count current role_routes for Director
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM role_routes WHERE role_id = ?");
    $stmt->execute([$directorRoleId]);
    $currentRouteCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "✓ Current role_routes entries for Director: $currentRouteCount\n\n";

    // Identify missing routes
    echo "SECTION 3: Identifying missing role_routes entries...\n";
    $stmt = $db->prepare("
        SELECT COUNT(*) as missing FROM sidebar_menu_items smi
        JOIN role_sidebar_menus rsm ON rsm.sidebar_menu_id = smi.id
        WHERE rsm.role_id = ?
        AND smi.route_id IS NOT NULL
        AND NOT EXISTS (
            SELECT 1 FROM role_routes rr
            WHERE rr.role_id = ?
            AND rr.route_id = smi.route_id
        )
    ");
    $stmt->execute([$directorRoleId, $directorRoleId]);
    $missingCount = $stmt->fetch(PDO::FETCH_ASSOC)['missing'];
    echo "✓ Missing role_routes entries: $missingCount\n\n";

    // Execute INSERT
    echo "SECTION 4: Inserting missing role_routes entries...\n";
    $stmt = $db->prepare("
        INSERT IGNORE INTO role_routes (role_id, route_id, created_at, updated_at)
        SELECT DISTINCT ?, smi.route_id, NOW(), NOW()
        FROM sidebar_menu_items smi
        JOIN role_sidebar_menus rsm ON rsm.sidebar_menu_id = smi.id
        WHERE rsm.role_id = ?
        AND smi.route_id IS NOT NULL
        AND NOT EXISTS (
            SELECT 1 FROM role_routes rr
            WHERE rr.role_id = ?
            AND rr.route_id = smi.route_id
        )
    ");
    $stmt->execute([$directorRoleId, $directorRoleId, $directorRoleId]);
    $insertedCount = $stmt->rowCount();
    echo "✓ Inserted $insertedCount new role_routes entries\n\n";

    // Verification
    echo "SECTION 5: Verification\n";
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM role_routes WHERE role_id = ?");
    $stmt->execute([$directorRoleId]);
    $newRouteCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "✓ role_routes entries for Director AFTER fix: $newRouteCount\n";

    // Coverage check
    $stmt = $db->prepare("
        SELECT COUNT(*) as coverage FROM role_sidebar_menus rsm
        JOIN sidebar_menu_items smi ON rsm.sidebar_menu_id = smi.id
        WHERE rsm.role_id = ?
        AND smi.route_id IS NOT NULL
        AND EXISTS (
            SELECT 1 FROM role_routes rr
            WHERE rr.role_id = ? AND rr.route_id = smi.route_id
        )
    ");
    $stmt->execute([$directorRoleId, $directorRoleId]);
    $coverage = $stmt->fetch(PDO::FETCH_ASSOC)['coverage'];
    echo "✓ Sidebar items with route_routes coverage: $coverage\n\n";

    // Summary
    echo "=== SUMMARY ===\n";
    echo "Before: $currentRouteCount role_routes entries\n";
    echo "After:  $newRouteCount role_routes entries\n";
    echo "Added:  $insertedCount entries (+$insertedCount)\n\n";

    if ($coverage == $sidebarCount) {
        echo "✅ SUCCESS: All sidebar items now have route_routes coverage\n";
        echo "   Director login will now return full sidebar menu (~$sidebarCount items)\n";
    } else {
        echo "⚠️  PARTIAL SUCCESS: $coverage/$sidebarCount items covered\n";
        echo "   Check for sidebar items with NULL route_id\n";
    }

    echo "\n=== NEXT STEPS ===\n";
    echo "1. Test Director login via curl\n";
    echo "2. Verify sidebar_items array in response\n";
    echo "3. Apply same fix to other roles\n";
    echo "4. Deploy to production\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>
