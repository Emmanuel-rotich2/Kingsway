#!/usr/bin/env php
<?php
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../database/Database.php';
require __DIR__ . '/../api/services/DelegationService.php';

use App\API\Services\DelegationService;
use App\API\Modules\users\UserPermissionManager;

$argv = $_SERVER['argv'];
$cmd = $argv[1] ?? 'help';
$db = (new App\Database\Database())->getConnection();
$service = new DelegationService();
$permMgr = new UserPermissionManager($db);

function usage()
{
    echo "Usage:\n";
    echo "  manage_delegations.php list\n";
    echo "  manage_delegations.php create <delegator_user_id> <delegate_user_id> <menu_item_id> [expires_at]\n";
    echo "  manage_delegations.php delete <delegation_id>\n";
    echo "  manage_delegations.php revoke <delegation_id>\n";
}

if ($cmd === 'list') {
    $stmt = $db->query('SELECT udi.*, du.username as delegator, dv.username as delegate, mi.label as menu_label FROM user_delegations_items udi LEFT JOIN users du ON du.id = udi.delegator_user_id LEFT JOIN users dv ON dv.id = udi.delegate_user_id LEFT JOIN sidebar_menu_items mi ON mi.id = udi.menu_item_id ORDER BY id DESC');
    $rows = $stmt->fetchAll();
    foreach ($rows as $r) {
        echo sprintf("%4d | %s(%d) -> %s(%d) | %s | active=%s expires=%s\n", $r['id'], $r['delegator'], $r['delegator_user_id'], $r['delegate'], $r['delegate_user_id'], $r['menu_label'] ?? $r['menu_item_id'], $r['active'], $r['expires_at']);
    }
    exit(0);
}
if ($cmd === 'create') {
    $delegator = (int) ($argv[2] ?? 0);
    $delegate = (int) ($argv[3] ?? 0);
    $menuItem = (int) ($argv[4] ?? 0);
    $expires = $argv[5] ?? null;
    if (!$delegator || !$delegate || !$menuItem) {
        usage();
        exit(1);
    }
    $granted = $service->delegateMenuItemToUser($delegator, $delegate, $menuItem, true, $expires);
    echo "Delegation created. granted_permissions: " . json_encode($granted) . "\n";
    exit(0);
}
if ($cmd === 'delete') {
    $id = (int) ($argv[2] ?? 0);
    if (!$id) {
        usage();
        exit(1);
    }
    // Delete and revoke
    // Reusing logic from controller: revoke via best-effort, then delete
    $stmt = $db->prepare('SELECT udi.*, da.granted_permissions FROM user_delegations_items udi LEFT JOIN delegation_audit da ON da.delegator_user_id = udi.delegator_user_id AND da.delegate_user_id = udi.delegate_user_id AND da.menu_item_id = udi.menu_item_id WHERE udi.id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        echo "Delegation not found\n";
        exit(1);
    }
    $delegateUserId = $row['delegate_user_id'];
    $granted = $row['granted_permissions'] ?? null;
    $permIds = [];
    if ($granted && $granted !== '[]')
        $permIds = json_decode($granted, true);
    else {
        $stmt2 = $db->prepare('SELECT rp.permission_id FROM sidebar_menu_items mi JOIN routes r ON r.id = mi.route_id JOIN route_permissions rp ON rp.route_id = r.id AND rp.is_required = 1 WHERE mi.id = ?');
        $stmt2->execute([$row['menu_item_id']]);
        $permIds = array_map(fn($r) => $r['permission_id'], $stmt2->fetchAll());
    }
    foreach ($permIds as $pid) {
        $checkStmt = $db->prepare('SELECT COUNT(*) FROM user_delegations_items udi JOIN sidebar_menu_items mi ON mi.id = udi.menu_item_id JOIN routes r ON r.id = mi.route_id JOIN route_permissions rp ON rp.route_id = r.id AND rp.is_required = 1 WHERE udi.delegate_user_id = ? AND rp.permission_id = ? AND udi.active = 1 AND udi.id != ?');
        $checkStmt->execute([$delegateUserId, $pid, $id]);
        $cnt = (int) $checkStmt->fetchColumn();
        if ($cnt === 0) {
            $permMgr->revokePermission($delegateUserId, $pid);
            echo "Revoked permission {$pid} from user {$delegateUserId}\n";
        }
    }
    $del = $db->prepare('DELETE FROM user_delegations_items WHERE id = ?');
    $del->execute([$id]);
    echo "Deleted delegation {$id}\n";
    exit(0);
}

usage();
exit(0);
