<?php
/**
 * Test: DelegationService atomic delegation + permission grant
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
chdir(__DIR__ . '/..');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../database/Database.php';
require_once __DIR__ . '/../api/services/DelegationService.php';

use App\API\Services\DelegationService;
use App\Database\Database;

$db = Database::getInstance()->getConnection();
$service = new DelegationService();

// Use headteacher (user id 4) as delegator and deputy academic (user id 5) as delegate
$delegator = 4;
$delegate = 5;
$menuItemId = 99999; // test item

// Ensure test menu item exists (from previous test)
$db->exec("INSERT IGNORE INTO sidebar_menu_items (id, label, url, is_active, display_order) VALUES (99999, 'Headteacher Test Item', 'headteacher_test_item', 1, 999)");
$db->exec("INSERT IGNORE INTO routes (id, name, url, domain, is_active) VALUES (99998, 'headteacher_test_item', '/headteacher_test_item', 'SCHOOL', 1)");
$db->exec("UPDATE sidebar_menu_items SET route_id = 99998 WHERE id = 99999");
$db->exec("INSERT IGNORE INTO permissions (id, code, description) VALUES (99998, 'headteacher_test_permission', 'Test permission for headteacher item')");
$db->exec("INSERT IGNORE INTO route_permissions (route_id, permission_id, access_type, is_required) VALUES (99998, 99998, 'view', 1)");

try {
    $granted = $service->delegateMenuItemToUser($delegator, $delegate, $menuItemId, true, null);
    $hasRow = (int) $db->query("SELECT COUNT(*) FROM user_delegations_items WHERE delegator_user_id = $delegator AND delegate_user_id = $delegate AND menu_item_id = $menuItemId")->fetchColumn();
    $hasPerm = (int) $db->query("SELECT COUNT(*) FROM user_permissions up JOIN permissions p ON p.id = up.permission_id WHERE up.user_id = $delegate AND p.code = 'headteacher_test_permission'")->fetchColumn();

    $result = [
        'granted_permissions' => $granted,
        'user_delegation_row_exists' => $hasRow > 0,
        'user_permission_granted' => $hasPerm > 0,
        'passed' => ($hasRow > 0 && $hasPerm > 0)
    ];
} catch (\Exception $e) {
    $result = ['error' => $e->getMessage(), 'passed' => false];
}

header('Content-Type: application/json');
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
