<?php
/**
 * Test DashboardManager database-driven functionality
 */

require_once dirname(__DIR__) . '/api/includes/DashboardManager.php';

$dm = new DashboardManager();

echo "=== Testing DashboardManager - Sidebar Menu Items ===\n\n";

// Test for School Admin (Role 4)
echo "Sidebar for School Admin (Role 4):\n";
$sidebar = $dm->getSidebarForRole(4);
foreach ($sidebar as $item) {
    echo "  - " . $item['label'] . " [" . ($item['url'] ?: 'parent') . "]\n";
    if (!empty($item['subitems'])) {
        foreach ($item['subitems'] as $child) {
            echo "      - " . $child['label'] . " [" . ($child['url'] ?: '') . "]\n";
        }
    }
}

echo "\nSidebar for System Admin (Role 2):\n";
$sidebar = $dm->getSidebarForRole(2);
foreach ($sidebar as $item) {
    echo "  - " . $item['label'] . " [" . ($item['url'] ?: 'parent') . "]\n";
    if (!empty($item['subitems'])) {
        foreach ($item['subitems'] as $child) {
            echo "      - " . $child['label'] . " [" . ($child['url'] ?: '') . "]\n";
        }
    }
}

echo "\nSidebar for Class Teacher (Role 7):\n";
$sidebar = $dm->getSidebarForRole(7);
foreach ($sidebar as $item) {
    echo "  - " . $item['label'] . " [" . ($item['url'] ?: 'parent') . "]\n";
    if (!empty($item['subitems'])) {
        foreach ($item['subitems'] as $child) {
            echo "      - " . $child['label'] . " [" . ($child['url'] ?: '') . "]\n";
        }
    }
}

echo "\n=== Testing Dashboard Routes ===\n";
echo "System Admin (Role 2): " . $dm->getDashboardRouteForRole(2) . "\n";
echo "School Admin (Role 4): " . $dm->getDashboardRouteForRole(4) . "\n";
echo "Class Teacher (Role 7): " . $dm->getDashboardRouteForRole(7) . "\n";
echo "Accountant (Role 10): " . $dm->getDashboardRouteForRole(10) . "\n";

echo "\nTests complete!\n";
