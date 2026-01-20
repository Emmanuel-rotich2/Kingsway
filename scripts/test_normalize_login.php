<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../api/modules/auth/AuthAPI.php';

use App\API\Modules\auth\AuthAPI;

$sample = json_decode(<<<'JSON'
{
  "status": "success",
  "message": "Login successful",
  "data": {
    "token": "tok",
    "refresh_token": "rtok",
    "token_expires_in": 3600,
    "user": {
      "id": 9,
      "username": "test_accountant",
      "email": "jenifer@gmail.com",
      "first_name": "Jennifer",
      "last_name": "Accountant",
      "role_id": 10,
      "status": "active",
      "roles": [
        { "id": 10, "name": "Accountant" }
      ],
      "permissions": ["communications_inbound_view"]
    },
    "sidebar_items": [
      {"label":"Income","url":"home.php?route=manage_payments","subitems":[]},
      {"label":"Accounts","url":"/pages/bank_accounts.php","subitems":[]}
    ],
    "dashboard": {"key": "system_administrator_dashboard","url":"system_administrator_dashboard"},
    "config_source": "file"
  }
}
JSON
    ,
    true
);

$auth = new AuthAPI();

// Use reflection to call private methods
$ref = new ReflectionClass($auth);

$normalizeSidebar = $ref->getMethod('normalizeSidebarItems');
$normalizeSidebar->setAccessible(true);

$normalizePerms = $ref->getMethod('normalizeUserPermissions');
$normalizePerms->setAccessible(true);

$getDefaultPerms = $ref->getMethod('getDefaultRolePermissions');
$getDefaultPerms->setAccessible(true);

$sidebar = $sample['data']['sidebar_items'];
$normalizedSidebar = $normalizeSidebar->invoke($auth, $sidebar);

$user = $sample['data']['user'];
$user = $normalizePerms->invoke($auth, $user);

// Merge default perms for Accountant (role 10)
$defaults = $getDefaultPerms->invoke($auth, 10);
$user['permissions'] = array_values(array_unique(array_merge($user['permissions'], $defaults)));

echo "Normalized sidebar:\n";
print_r($normalizedSidebar);

echo "\nNormalized user permissions:\n";
print_r($user['permissions']);
