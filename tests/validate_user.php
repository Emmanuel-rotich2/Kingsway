<?php
require_once __DIR__ . '/../api/includes/ValidationHelper.php';
require_once __DIR__ . '/../database/Database.php';
use App\Database\Database;
$db = Database::getInstance()->getConnection();
$data = [
    'username' => 'test_classteacher_psychosocial_emotional_development',
    'email' => 'test_classteacher_psychosocial_emotional_development@example.com',
    'password' => 'Pass123!@',
    'first_name' => 'Test',
    'last_name' => 'Psychosocial Emotional Development CT',
    'role_ids' => [7],
    'department_id' => 1,
    'position' => 'Class Teacher',
    'employment_date' => '2026-01-11',
    'staff_info' => [
        'department_id' => 1,
        'staff_category_id' => 6,
        'position' => 'Class Teacher',
        'employment_date' => '2026-01-11',
        'date_of_birth' => '1990-01-01',
        'nssf_no' => 'N/A',
        'kra_pin' => 'A000',
        'nhif_no' => 'N/A',
        'bank_account' => '0000',
        'salary' => 0.00,
        'staff_no' => 'KWPS034'
    ]
];
$res = \App\API\Includes\ValidationHelper::validateUserData($data, $db, false);
echo json_encode($res, JSON_PRETTY_PRINT);
