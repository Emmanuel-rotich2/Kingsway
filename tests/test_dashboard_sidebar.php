<?php
/**
 * Test Dashboard & Sidebar Population
 * 
 * Verifies that:
 * 1. Login response includes correct dashboard key
 * 2. Sidebar items are populated correctly
 * 3. Menu items have correct field names (url, icon, label)
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set JSON header for API testing
header('Content-Type: application/json');

// Change to project root
chdir(__DIR__ . '/..');
require_once __DIR__ . '/../config/DashboardRouter.php';
require_once __DIR__ . '/../api/includes/DashboardManager.php';

// Test data
$testUser = [
    'id' => 1,
    'username' => 'test_system_administrator',
    'email' => 'admin@test.com',
    'first_name' => 'Test',
    'last_name' => 'Admin',
    'roles' => [
        ['id' => 1, 'name' => 'System Administrator']
    ],
    'permissions' => [
        ['permission_code' => 'dashboard_system_administrator_access'],
        ['permission_code' => 'academics_all_permissions'],
        ['permission_code' => 'attendance_all_permissions'],
        ['permission_code' => 'staff_all_permissions'],
    ]
];

$results = [
    'test_name' => 'Dashboard & Sidebar Population Tests',
    'timestamp' => date('Y-m-d H:i:s'),
    'tests' => []
];

try {
    // Test 1: DashboardRouter mapping
    $test1 = [
        'name' => 'DashboardRouter: Maps roles to dashboard keys',
        'passed' => false,
        'details' => []
    ];

    $primaryRole = 'System Administrator';
    $dashboardKey = \DashboardRouter::getDashboardForRole($primaryRole);

    $test1['details']['input_role'] = $primaryRole;
    $test1['details']['output_dashboard_key'] = $dashboardKey;
    $test1['details']['expected'] = 'system_administrator_dashboard';
    $test1['passed'] = ($dashboardKey === 'system_administrator_dashboard');

    $results['tests'][] = $test1;

    // Test 2: DashboardManager initialization
    $test2 = [
        'name' => 'DashboardManager: Initializes and loads config',
        'passed' => false,
        'details' => []
    ];

    $dashboardManager = new \DashboardManager();
    $dashboardManager->setUser($testUser);

    $allDashboards = $dashboardManager->getAllDashboards();
    $test2['details']['config_loaded'] = !empty($allDashboards);
    $test2['details']['total_dashboards'] = count($allDashboards);
    $test2['details']['dashboard_keys'] = array_keys($allDashboards);
    $test2['passed'] = (count($allDashboards) > 0);

    $results['tests'][] = $test2;

    // Test 3: Get menu items with correct key
    $test3 = [
        'name' => 'DashboardManager: Gets menu items for system_administrator',
        'passed' => false,
        'details' => []
    ];

    $normalizedRole = 'system_administrator';
    $menuItems = $dashboardManager->getMenuItems($normalizedRole);

    $test3['details']['role_key'] = $normalizedRole;
    $test3['details']['menu_items_found'] = count($menuItems);
    $test3['passed'] = (count($menuItems) > 0);

    if (count($menuItems) > 0) {
        // Check first item structure
        $firstItem = $menuItems[0];
        $test3['details']['first_item'] = [
            'label' => $firstItem['label'] ?? null,
            'url' => $firstItem['url'] ?? null,  // Should be 'url' not 'route'
            'icon' => $firstItem['icon'] ?? null,
            'has_permissions' => isset($firstItem['permissions'])
        ];

        // Verify field names
        $test3['details']['has_url_field'] = isset($firstItem['url']);
        $test3['details']['has_route_field'] = isset($firstItem['route']);
        $test3['passed'] = $test3['passed'] && isset($firstItem['url']) && !isset($firstItem['route']);
    }

    $results['tests'][] = $test3;

    // Test 4: Dashboard config has correct structure
    $test4 = [
        'name' => 'Dashboards.php: Has correct menu item structure',
        'passed' => false,
        'details' => []
    ];

    $dashboard = $dashboardManager->getDashboard('system_administrator');
    $test4['details']['dashboard_found'] = ($dashboard !== null);

    if ($dashboard) {
        $test4['details']['dashboard_label'] = $dashboard['label'] ?? null;
        $test4['details']['menu_items_count'] = count($dashboard['menu_items'] ?? []);

        if (!empty($dashboard['menu_items'])) {
            $firstMenuItem = $dashboard['menu_items'][0];
            $test4['details']['first_menu_item_fields'] = [
                'label' => $firstMenuItem['label'] ?? null,
                'url' => $firstMenuItem['url'] ?? null,
                'icon' => $firstMenuItem['icon'] ?? null,
            ];

            $test4['details']['field_check'] = [
                'has_url_field' => isset($firstMenuItem['url']),
                'has_route_field' => isset($firstMenuItem['route']),
                'has_icon_field' => isset($firstMenuItem['icon']),
            ];

            $test4['passed'] = isset($firstMenuItem['url']) && !isset($firstMenuItem['route']);
        }
    }

    $results['tests'][] = $test4;

    // Test 5: Simulate login response
    $test5 = [
        'name' => 'AuthAPI: Generates proper login response',
        'passed' => false,
        'details' => []
    ];

    $dashboardManager = new \DashboardManager();
    $dashboardManager->setUser($testUser);

    $normalizedRole = 'system_administrator';
    $sidebarItems = $dashboardManager->getMenuItems($normalizedRole);
    $defaultDashboard = $dashboardManager->getDashboard($normalizedRole);

    $loginResponse = [
        'key' => \DashboardRouter::getDashboardForRole('System Administrator'),
        'url' => '?route=' . \DashboardRouter::getDashboardForRole('System Administrator'),
        'label' => $defaultDashboard['label'] ?? 'System Administrator Dashboard'
    ];

    $test5['details']['dashboard_key'] = $loginResponse['key'];
    $test5['details']['dashboard_url'] = $loginResponse['url'];
    $test5['details']['sidebar_items_count'] = count($sidebarItems);
    $test5['details']['first_sidebar_item'] = !empty($sidebarItems) ? [
        'label' => $sidebarItems[0]['label'] ?? null,
        'url' => $sidebarItems[0]['url'] ?? null,
    ] : null;

    $test5['passed'] = (
        $loginResponse['key'] === 'system_administrator_dashboard' &&
        count($sidebarItems) > 0 &&
        !empty($sidebarItems[0]['url'])
    );

    $results['tests'][] = $test5;

    // Test 6: Class Teacher should NOT have 'My Classes' (menu id 400) in sidebar
    $test6 = [
        'name' => "Class Teacher: 'My Classes' not present in sidebar",
        'passed' => false,
        'details' => []
    ];

    $ctUser = [
        'id' => 2,
        'username' => 'test_class_teacher',
        'roles' => [ ['id' => 7, 'name' => 'Class Teacher'] ],
        'permissions' => []
    ];

    $dm = new \DashboardManager();
    $dm->setUser($ctUser);
    $ctMenu = $dm->getMenuItems();

    $has400 = false;
    $stack = $ctMenu;
    while (!$has400 && !empty($stack)) {
        $item = array_shift($stack);
        if (isset($item['id']) && (int)$item['id'] === 400) {
            $has400 = true; break;
        }
        if (!empty($item['subitems'])) { $stack = array_merge($stack, $item['subitems']); }
    }

    $test6['details']['has_400'] = $has400;
    $test6['passed'] = (!$has400);
    $results['tests'][] = $test6;

    // Summary
    $results['summary'] = [
        'total_tests' => count($results['tests']),
        'passed' => count(array_filter($results['tests'], fn($t) => $t['passed'])),
        'failed' => count(array_filter($results['tests'], fn($t) => !$t['passed']))
    ];

    $results['all_passed'] = ($results['summary']['failed'] === 0);

} catch (Exception $e) {
    $results['error'] = [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ];
    $results['all_passed'] = false;
}

// Output results
echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>