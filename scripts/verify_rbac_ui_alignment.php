<?php
/**
 * Verifies SystemConfigService authorization for every route that appears in the
 * sidebar tree built by MenuBuilderService (same path as login/API). If this
 * passes, DB RBAC + route_permissions + role_routes align with the UI shell.
 *
 * Requires: PHP pdo_mysql, config/config.php, DB reachable.
 * Usage: /opt/lampp/bin/php scripts/verify_rbac_ui_alignment.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../api/services/MenuBuilderService.php';
require_once __DIR__ . '/../api/services/SystemConfigService.php';

use App\API\Services\MenuBuilderService;
use App\API\Services\SystemConfigService;
use App\Database\Database;

$db = Database::getInstance();
$menu = MenuBuilderService::getInstance();
$sys = SystemConfigService::getInstance();

$sql = <<<'SQL'
SELECT u.id AS user_id, u.username, ur.role_id
FROM users u
JOIN user_roles ur ON ur.user_id = u.id
WHERE u.status = 'active'
  AND u.username LIKE 'test\_%' ESCAPE '\\'
ORDER BY u.id, ur.role_id
SQL;

$stmt = $db->query($sql);
$rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

if ($rows === []) {
    echo "No active users matching test_% — nothing to verify.\n";
    exit(0);
}

/**
 * @return list<string>
 */
function collectRouteNamesFromMenuTree(array $nodes): array
{
    $out = [];
    foreach ($nodes as $n) {
        $url = isset($n['url']) ? trim((string) $n['url']) : '';
        if ($url !== '' && $url !== '#' && strpos($url, '/') === false && stripos($url, 'home.php') === false) {
            $out[] = $url;
        }
        if (!empty($n['subitems']) && is_array($n['subitems'])) {
            $out = array_merge($out, collectRouteNamesFromMenuTree($n['subitems']));
        }
    }
    return $out;
}

$failures = [];
$checked = 0;

foreach ($rows as $row) {
    $userId = (int) $row['user_id'];
    $roleId = (int) $row['role_id'];
    $username = $row['username'];

    $pstmt = $db->query(
        'SELECT DISTINCT permission_code FROM v_user_permissions_effective WHERE user_id = ?',
        [$userId]
    );
    $perms = $pstmt->fetchAll(\PDO::FETCH_COLUMN) ?: [];

    $tree = $menu->buildSidebarForUser($userId, $roleId, $perms);
    $routes = array_unique(collectRouteNamesFromMenuTree($tree));

    foreach ($routes as $routeName) {
        $checked++;
        $res = $sys->isUserAuthorizedForRoute($userId, $roleId, $routeName);
        if (empty($res['authorized'])) {
            $failures[] = sprintf(
                '%s user_id=%d role_id=%d route=%s reason=%s',
                $username,
                $userId,
                $roleId,
                $routeName,
                $res['reason'] ?? 'unknown'
            );
        }
    }
}

echo "Checked {$checked} menu route authorizations (MenuBuilder output) for " . count($rows) . " user-role row(s).\n";

if ($failures !== []) {
    echo "FAILURES (" . count($failures) . "):\n";
    foreach (array_slice($failures, 0, 50) as $line) {
        echo $line . "\n";
    }
    if (count($failures) > 50) {
        echo '... and ' . (count($failures) - 50) . " more.\n";
    }
    exit(1);
}

echo "PASS: rendered sidebar routes are authorized for SystemConfigService.\n";
exit(0);
