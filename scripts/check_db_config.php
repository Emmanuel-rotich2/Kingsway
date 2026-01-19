<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/DashboardRouter.php';
require_once __DIR__ . '/../database/Database.php';

use App\Database\Database;

$db = Database::getInstance()->getConnection();
$tables = ['sidebar_menu_items', 'role_sidebar_menus', 'role_dashboards', 'dashboards', 'routes'];

$result = [];
foreach ($tables as $t) {
    try {
        $stmt = $db->query("SHOW TABLES LIKE '$t'");
        $exists = $stmt->rowCount() > 0;
        $count = null;
        if ($exists) {
            $cstmt = $db->query("SELECT COUNT(*) AS cnt FROM $t");
            $c = $cstmt->fetch(PDO::FETCH_ASSOC);
            $count = $c['cnt'] ?? 0;
        }
        $result[$t] = ['exists' => $exists, 'count' => $count];
    } catch (Exception $e) {
        $result[$t] = ['exists' => false, 'count' => null, 'error' => $e->getMessage()];
    }
}

echo json_encode($result, JSON_PRETTY_PRINT);
