#!/usr/bin/env php
<?php
/**
 * Test Token Size After Login
 * 
 * This script tests the JWT token generation to ensure tokens are compact
 * Expected: Token size should be ~500-1000 bytes (not 160KB!)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../api/includes/JWT.php';
require_once __DIR__ . '/../api/modules/users/UsersAPI.php';
require_once __DIR__ . '/../api/modules/users/RoleManager.php';
require_once __DIR__ . '/../api/modules/users/PermissionManager.php';
require_once __DIR__ . '/../api/modules/users/UserRoleManager.php';
require_once __DIR__ . '/../api/modules/users/UserPermissionManager.php';

echo "\n";
echo "=================================================================\n";
echo "JWT TOKEN SIZE TEST\n";
echo "=================================================================\n";
echo "\n";

// Test login with a known user
$username = 'test_system_administrator';
$password = 'Test@2024';

echo "Testing login for user: $username\n";
echo "-----------------------------------------------------------------\n";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $usersApi = new UsersAPI($db);
    
    $loginResult = $usersApi->login([
        'username' => $username,
        'password' => $password
    ]);
    
    if ($loginResult['success']) {
        echo "✓ Login successful\n\n";
        
        $token = $loginResult['data']['token'] ?? null;
        $user = $loginResult['data']['user'] ?? null;
        
        if ($token) {
            // Analyze token
            $tokenSize = strlen($token);
            $authHeaderSize = strlen("Bearer " . $token);
            
            echo "TOKEN ANALYSIS:\n";
            echo "-----------------------------------------------------------------\n";
            echo "Token size: " . number_format($tokenSize) . " bytes\n";
            echo "Authorization header size: " . number_format($authHeaderSize) . " bytes\n";
            
            // Decode token to see what's inside
            $parts = explode('.', $token);
            if (count($parts) === 3) {
                $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
                
                echo "\nTOKEN CONTENTS:\n";
                echo "-----------------------------------------------------------------\n";
                echo "User ID: " . ($payload['user_id'] ?? 'N/A') . "\n";
                echo "Username: " . ($payload['username'] ?? 'N/A') . "\n";
                echo "Email: " . ($payload['email'] ?? 'N/A') . "\n";
                
                // Check permissions format
                if (isset($payload['permissions']) && is_array($payload['permissions'])) {
                    $permCount = count($payload['permissions']);
                    $firstPerm = $payload['permissions'][0] ?? null;
                    
                    echo "Permissions count: " . $permCount . "\n";
                    echo "First permission type: " . gettype($firstPerm) . "\n";
                    
                    if (is_string($firstPerm)) {
                        echo "First permission value: " . $firstPerm . "\n";
                        echo "✓ Permissions are STRINGS (compact format) ✓\n";
                    } elseif (is_array($firstPerm)) {
                        echo "First permission: " . json_encode($firstPerm) . "\n";
                        echo "✗ Permissions are OBJECTS (bloated format) ✗\n";
                        echo "\nWARNING: Permissions should be permission codes (strings), not full objects!\n";
                    }
                    
                    // Calculate size used by permissions
                    $permissionsJson = json_encode($payload['permissions']);
                    $permissionsSize = strlen($permissionsJson);
                    $permissionsPercent = ($permissionsSize / $tokenSize) * 100;
                    
                    echo "\nPermissions size: " . number_format($permissionsSize) . " bytes (" . 
                         number_format($permissionsPercent, 1) . "% of token)\n";
                }
                
                // Check roles
                if (isset($payload['roles']) && is_array($payload['roles'])) {
                    echo "Roles count: " . count($payload['roles']) . "\n";
                }
                
                // Token expiry
                if (isset($payload['exp'])) {
                    $expiryTime = date('Y-m-d H:i:s', $payload['exp']);
                    echo "Expires at: " . $expiryTime . "\n";
                }
            }
            
            echo "\nRESULT:\n";
            echo "-----------------------------------------------------------------\n";
            if ($tokenSize < 2000) {
                echo "✓✓✓ PASS: Token is compact (< 2KB)\n";
                echo "This token will work with nginx header limits.\n";
            } elseif ($tokenSize < 5000) {
                echo "⚠ WARNING: Token is larger than expected (" . number_format($tokenSize) . " bytes)\n";
                echo "May cause issues with some nginx configurations.\n";
            } else {
                echo "✗✗✗ FAIL: Token is too large (" . number_format($tokenSize) . " bytes)\n";
                echo "This will cause '400 Request Header Too Large' errors!\n";
            }
            
        } else {
            echo "✗ No token in response\n";
        }
        
        // Check user data returned
        if ($user) {
            echo "\nUSER DATA IN RESPONSE:\n";
            echo "-----------------------------------------------------------------\n";
            echo "User ID: " . ($user['id'] ?? 'N/A') . "\n";
            echo "Username: " . ($user['username'] ?? 'N/A') . "\n";
            
            if (isset($user['permissions']) && is_array($user['permissions'])) {
                $permCount = count($user['permissions']);
                $firstPerm = $user['permissions'][0] ?? null;
                
                echo "Permissions count: " . $permCount . "\n";
                echo "First permission type: " . gettype($firstPerm) . "\n";
                
                if (is_string($firstPerm)) {
                    echo "First permission: " . $firstPerm . "\n";
                    echo "✓ Response permissions are CODES (strings)\n";
                } else {
                    echo "First permission: " . json_encode($firstPerm) . "\n";
                    echo "Response permissions format: " . (is_array($firstPerm) ? 'object' : 'unknown') . "\n";
                }
            }
        }
        
    } else {
        echo "✗ Login failed: " . ($loginResult['error'] ?? 'Unknown error') . "\n";
    }
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n";
echo "=================================================================\n";
echo "\n";
