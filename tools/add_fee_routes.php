<?php
/**
 * Script to add manage_fee_structure route to database
 * Run: php tools/add_fee_routes.php
 */

require_once __DIR__ . '/../database/Database.php';

use App\Database\Database;

$db = Database::getInstance()->getConnection();

echo "=== Adding Fee Management Routes ===\n\n";

// Get the finance_view permission ID
$stmt = $db->prepare('SELECT id FROM permissions WHERE code = ?');
$stmt->execute(['finance_view']);
$perm = $stmt->fetch(\PDO::FETCH_ASSOC);

if (!$perm) {
    echo "✗ finance_view permission not found\n";
    exit(1);
}

$permId = $perm['id'];
echo "Found finance_view permission (ID: $permId)\n\n";

// Routes to add/verify
$routes = [
    'manage_fees' => 'Student Fees & Payments - Tracks student payments, balances, and payment status',
    'manage_fee_structure' => 'Fee Structure Management - Shows fee structures by level, year, term, and class'
];

foreach ($routes as $routeName => $description) {
    $stmt = $db->prepare('SELECT id FROM routes WHERE name = ?');
    $stmt->execute([$routeName]);
    $route = $stmt->fetch(\PDO::FETCH_ASSOC);

    if ($route) {
        echo "✓ Route '$routeName' exists (ID: " . $route['id'] . ")\n";
        $routeId = $route['id'];
    } else {
        // Insert new route
        $stmt = $db->prepare('INSERT INTO routes (name, url, domain, description, is_active, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
        $result = $stmt->execute([
            $routeName,
            "/pages/$routeName.php",
            'SCHOOL',
            $description,
            1
        ]);

        if ($result) {
            $routeId = $db->lastInsertId();
            echo "✓ Route '$routeName' created (ID: $routeId)\n";
        } else {
            echo "✗ Failed to create route '$routeName'\n";
            continue;
        }
    }

    // Check if permission already mapped
    $stmt = $db->prepare('SELECT id FROM route_permissions WHERE route_id = ? AND permission_id = ?');
    $stmt->execute([$routeId, $permId]);
    $exists = $stmt->fetch(\PDO::FETCH_ASSOC);

    if ($exists) {
        echo "  ✓ Permission mapping already exists\n";
    } else {
        $stmt = $db->prepare('INSERT INTO route_permissions (route_id, permission_id, created_at) VALUES (?, ?, NOW())');
        $result = $stmt->execute([$routeId, $permId]);

        if ($result) {
            echo "  ✓ Permission mapping created (finance_view)\n";
        } else {
            echo "  ✗ Failed to create permission mapping\n";
        }
    }
    echo "\n";
}

echo "✓ Database configuration complete!\n";
