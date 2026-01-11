<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../database/Database.php';
require_once __DIR__ . '/../api/modules/users/UsersAPI.php';

use App\Database\Database;
use App\API\Modules\users\UsersAPI;

$username = $argv[1] ?? 'test_headteacher';
$password = $argv[2] ?? 'Pass123!@';

$uapi = new UsersAPI();
$result = $uapi->login(['username' => $username, 'password' => $password]);
print_r($result);
