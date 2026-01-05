<?php
/**
 * Test API response for getSidebarItems
 * This script tests what the API is returning for the Director user
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set correct header
header('Content-Type: application/json');

// Include database and necessary files
require_once __DIR__ . '/database/Database.php';
require_once __DIR__ . '/api/modules/users/UsersAPI.php';

try {
    // Get database instance
    $db = \App\Database\Database::getInstance();

    // Get the Director user (john@yahoo.com, ID 2)
    $stmt = $db->prepare('SELECT id, username, email FROM users WHERE id = 2');
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['error' => 'User not found']);
        exit;
    }

    // Get the user's roles
    $stmt = $db->prepare('SELECT role_id FROM user_roles WHERE user_id = ?');
    $stmt->execute([$user['id']]);
    $roles = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Call the getSidebarItems method directly
    $usersAPI = new \Users\UsersAPI();

    // Use reflection to call protected method
    $method = new ReflectionMethod('\Users\UsersAPI', 'getSidebarItems');
    $method->setAccessible(true);
    $result = $method->invoke($usersAPI, $user['id']);

    // Return the result
    echo json_encode([
        'user' => $user,
        'roles' => $roles,
        'sidebar_items' => $result
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>