<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../api/modules/auth/AuthAPI.php';
require_once __DIR__ . '/../api/modules/users/UsersAPI.php';

use App\API\Modules\auth\AuthAPI;
use App\API\Modules\users\UsersAPI;

$usersApi = new UsersAPI();
$userRes = $usersApi->get(9); // test_accountant
if (empty($userRes['success']) || empty($userRes['data'])) {
    echo "User not found\n";
    exit(1);
}
$user = $userRes['data'];

$auth = new AuthAPI();
$ref = new ReflectionClass($auth);
$method = $ref->getMethod('buildLoginResponseFromDatabase');
$method->setAccessible(true);

$primaryRoleId = $user['roles'][0]['id'] ?? null;
$roleIds = array_map(function ($r) {
    return $r['id'] ?? $r; }, $user['roles'] ?? []);

$response = $method->invoke($auth, $user, $primaryRoleId, $roleIds, 'dummy-token');

echo json_encode($response, JSON_PRETTY_PRINT);