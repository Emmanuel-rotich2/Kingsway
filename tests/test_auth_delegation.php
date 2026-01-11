<?php
/**
 * Test: AuthAPI delegation behavior
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
chdir(__DIR__ . '/..');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../database/Database.php';
require_once __DIR__ . '/../api/includes/BaseAPI.php';
require_once __DIR__ . '/../api/modules/auth/AuthAPI.php';

use App\API\Modules\auth\AuthAPI;
use App\Database\Database;

$db = Database::getInstance()->getConnection();
$auth = new AuthAPI();

// Helper to call private method via reflection
$ref = new ReflectionClass($auth);
$method = $ref->getMethod('buildLoginResponseFromDatabase');
$method->setAccessible(true);

// Prepare userData for deputy (role 6)
$userData = [
    'id' => 9999,
    'username' => 'test_deputy_sim',
    'email' => 'sim@example.com',
    'first_name' => 'Sim',
    'last_name' => 'Deputy',
    'roles' => [['id' => 6, 'name' => 'Deputy Head - Academic']],
    'permissions' => []
];

$primaryRoleId = 6;
$roleIds = [6];
$token = 'dummy';

// Ensure delegation table exists (migration may not have been run yet)
$db->exec("CREATE TABLE IF NOT EXISTS role_delegations (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  delegator_role_id INT UNSIGNED NOT NULL,
  delegate_role_id INT UNSIGNED NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY ux_delegation_pair (delegator_role_id, delegate_role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

// Clean up any previous rows
$db->exec("DELETE FROM role_delegations WHERE delegator_role_id = 5 AND delegate_role_id = 6");

// 1) Without delegation: expect headteacher menu NOT included
$res1 = $method->invoke($auth, $userData, $primaryRoleId, $roleIds, $token);
$sidebar1 = $res1['data']['sidebar_items'] ?? [];

function flatten($items)
{
    $out = [];
    foreach ($items as $i) {
        $out[] = $i;
        if (!empty($i['subitems']))
            $out = array_merge($out, flatten($i['subitems']));
    }
    return $out;
}

$flat1 = flatten($sidebar1);
$has_headteacher_item_1 = count(array_filter($flat1, fn($i) => (isset($i['url']) && $i['url'] === 'headteacher_dashboard') || (isset($i['route_name']) && $i['route_name'] === 'headteacher_dashboard'))) > 0;

// 2) Add delegation row and re-run
$stmt = $db->prepare("INSERT INTO role_delegations (delegator_role_id, delegate_role_id, active) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE active = VALUES(active)");
$stmt->execute([5, 6, 1]);

$res2 = $method->invoke($auth, $userData, $primaryRoleId, $roleIds, $token);
$sidebar2 = $res2['data']['sidebar_items'] ?? [];
$flat2 = flatten($sidebar2);
$has_headteacher_item_2 = count(array_filter($flat2, fn($i) => (isset($i['url']) && $i['url'] === 'headteacher_dashboard') || (isset($i['route_name']) && $i['route_name'] === 'headteacher_dashboard'))) > 0;

$result = [
    'test' => 'Auth Delegation Behavior',
    'without_delegation_has_headteacher_menu' => $has_headteacher_item_1,
    'with_delegation_has_headteacher_menu' => $has_headteacher_item_2
];

$result['passed'] = ($has_headteacher_item_1 === false) && ($has_headteacher_item_2 === true);

header('Content-Type: application/json');
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

?>