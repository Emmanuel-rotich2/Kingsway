<?php
/**
 * Test: MenuBuilderService deduplicates menu items across multiple roles
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
chdir(__DIR__ . '/..');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../database/Database.php';
require_once __DIR__ . '/../api/services/MenuBuilderService.php';

use App\Database\Database;
use App\API\Services\MenuBuilderService;

$service = MenuBuilderService::getInstance();
// User: test_deputy_acad (id 5), roles: deputy (6) and headteacher (5)
$userId = 5;
$roleIds = [6, 5];

$sidebar = $service->buildSidebarForMultipleRoles($userId, $roleIds);

function flattenItems($items)
{
    $out = [];
    foreach ($items as $item) {
        $out[] = $item;
        if (!empty($item['subitems'])) {
            $out = array_merge($out, flattenItems($item['subitems']));
        }
    }
    return $out;
}

$flat = flattenItems($sidebar);

// Look for entries with url 'all_classes' or label 'All Classes'
$matchesByUrl = array_filter($flat, fn($i) => isset($i['url']) && $i['url'] === 'all_classes');
$matchesByLabel = array_filter($flat, fn($i) => isset($i['label']) && stripos($i['label'], 'All Classes') !== false);

$results = [
    'test' => 'MenuBuilder dedupe for Deputy/Headteacher',
    'sidebar_count' => count($flat),
    'matches_by_url_count' => count($matchesByUrl),
    'matches_by_label_count' => count($matchesByLabel),
    'matches_by_url' => array_values(array_map(fn($i) => ['id' => $i['id'], 'label' => $i['label'], 'url' => $i['url']], $matchesByUrl)),
    'matches_by_label' => array_values(array_map(fn($i) => ['id' => $i['id'], 'label' => $i['label'], 'url' => $i['url'] ?? null], $matchesByLabel)),
];

$results['passed'] = ($results['matches_by_url_count'] <= 1) && ($results['matches_by_label_count'] <= 1);

header('Content-Type: application/json');
echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

?>