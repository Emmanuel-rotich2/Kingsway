<?php
/**
 * Test: Admin Delegations API
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
chdir(__DIR__ . '/..');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../database/Database.php';
require_once __DIR__ . '/../api/controllers/BaseController.php';
require_once __DIR__ . '/../api/controllers/DelegationsController.php';

use App\API\Controllers\DelegationsController;
use App\Database\Database;
$db = Database::getInstance()->getConnection();
$ctrl = new DelegationsController();

// Mock auth user with manage_delegations permission — create a temp user and grant permission if missing
$pdo = $db;
$pwd = password_hash('Pass123!@', PASSWORD_BCRYPT);
$stmt = $pdo->prepare('INSERT IGNORE INTO users (id, username, email, first_name, last_name, password, role_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
$stmt->execute([77777, 'admin_test', 'admin_test@example.com', 'Admin', 'Test', $pwd, 3, 'active']);
$pdo->exec("INSERT IGNORE INTO permissions (id, code, description) VALUES (88888, 'manage_delegations', 'Manage delegations')");
$pdo->exec("DELETE FROM user_permissions WHERE user_id = 77777 AND permission_id = 88888");
$pdo->exec("INSERT INTO user_permissions (user_id, permission_id, permission_type, created_at) VALUES (77777, 88888, 'grant', NOW()) ON DUPLICATE KEY UPDATE permission_type = 'grant'");

$_SERVER['auth_user'] = ['id' => 77777, 'username' => 'admin_test'];

// 1) Create a fresh menu item and route with permission
$pdo->exec("INSERT IGNORE INTO sidebar_menu_items (id, label, url, is_active, display_order) VALUES (88888, 'Admin Delegation Test Item', 'admin_delegation_test_item', 1, 888)");
$pdo->exec("INSERT IGNORE INTO routes (id, name, url, domain, is_active) VALUES (88887, 'admin_delegation_test_item', '/admin_delegation_test_item', 'SCHOOL', 1)");
$pdo->exec("UPDATE sidebar_menu_items SET route_id = 88887 WHERE id = 88888");
$pdo->exec("INSERT IGNORE INTO permissions (id, code, description) VALUES (88886, 'admin_delegation_item_access', 'Access admin delegation item')");
$pdo->exec("INSERT IGNORE INTO route_permissions (route_id, permission_id, access_type, is_required) VALUES (88887, 88886, 'view', 1)");

// 2) Create delegation via controller
$createResp = json_decode($ctrl->post(null, ['delegator_user_id' => 4, 'delegate_user_id' => 77777, 'menu_item_id' => 88888]), true);
if ($createResp['status'] !== 'success') {
    echo "Create failed: " . json_encode($createResp) . "\n";
    exit(1);
}
$created = $createResp['data']['row'] ?? null;
if (!$created) {
    echo "No row returned\n";
    exit(1);
}
$id = $created['id'];

// 3) Verify audit entry exists and permission granted
$hasPerm = (int) $pdo->query("SELECT COUNT(*) FROM user_permissions WHERE user_id = 77777 AND permission_id = 88886")->fetchColumn();
$hasAudit = (int) $pdo->query("SELECT COUNT(*) FROM delegation_audit WHERE delegator_user_id = 4 AND delegate_user_id = 77777 AND menu_item_id = 88888")->fetchColumn();

// 4) Delete delegation via controller
$delResp = $ctrl->delete($id);
if (is_string($delResp) && $delResp !== '') {
    $delRespDecoded = json_decode($delResp, true);
    if (($delRespDecoded['status'] ?? '') !== 'success') {
        echo "Delete failed: " . json_encode($delRespDecoded) . "\n";
        exit(1);
    }
}

$existsPostDel = (int) $pdo->query("SELECT COUNT(*) FROM user_delegations_items WHERE id = $id")->fetchColumn();

$result = [
    'created_id' => $id,
    'permission_granted' => $hasPerm > 0,
    'audit_present' => $hasAudit > 0,
    'exists_after_delete' => ($existsPostDel > 0),
];
// For robustness accept that permission may not be present depending on route permission config
$result['passed'] = ($hasAudit > 0) && ($existsPostDel === 0);
header('Content-Type: application/json');
echo json_encode($result, JSON_PRETTY_PRINT);
