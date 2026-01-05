<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';
// No helpers.php available in scripts; load DB wrapper if needed via Database class
require_once __DIR__ . '/../api/includes/BaseAPI.php';

use App\API\Modules\users\UsersAPI;

$usersApi = new UsersAPI();
$payload = [
    'username' => 'cli_test_user',
    'email' => 'cli_test_user+seed@example.com',
    'password' => 'Password123!',
    'first_name' => 'Cli',
    'last_name' => 'Test',
    'role_id' => 65,
    'tsc_no' => 'TSC000'
];

try {
    $result = $usersApi->create($payload);
    var_dump($result);
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString();
}
