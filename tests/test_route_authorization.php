<?php
/**
 * ROUTE AUTHORIZATION TESTING SCRIPT
 * 
 * Tests the deny-by-default route authorization enforcement
 * Validates that System Admin cannot access school operations
 * and other roles cannot access system administration
 * 
 * Run from: php test_route_authorization.php
 */

require_once 'api/middleware/RouteAuthorization.php';
use App\API\Middleware\RouteAuthorization;

// Color codes for terminal output
$PASS = "\033[92m✓ PASS\033[0m";
$FAIL = "\033[91m✗ FAIL\033[0m";
$INFO = "\033[94mℹ\033[0m";
$WARN = "\033[93m⚠\033[0m";

echo "\n";
echo "════════════════════════════════════════════════════════════════\n";
echo "ROUTE AUTHORIZATION TEST SUITE\n";
echo "════════════════════════════════════════════════════════════════\n\n";

$tests_run = 0;
$tests_passed = 0;

// ============================================================================
// TEST 1: System Admin can access System Infrastructure routes
// ============================================================================
echo "TEST 1: System Admin (Role 2) - Access to System Infrastructure\n";
echo str_repeat("-", 60) . "\n";

$test_cases = [
    ['route' => 'system_administrator_dashboard', 'expected' => true],
    ['route' => 'system_health', 'expected' => true],
    ['route' => 'error_logs', 'expected' => true],
    ['route' => 'authentication_logs', 'expected' => true],
    ['route' => 'manage_users', 'expected' => true],
    ['route' => 'manage_roles', 'expected' => true],
];

foreach ($test_cases as $case) {
    $tests_run++;
    $authorized = RouteAuthorization::isAuthorized(2, $case['route']);
    $passed = ($authorized === $case['expected']);

    if ($passed) {
        $tests_passed++;
        echo "  {$PASS} System Admin can access '{$case['route']}'\n";
    } else {
        echo "  {$FAIL} System Admin CANNOT access '{$case['route']}' (expected: {$case['expected']}, got: {$authorized})\n";
    }
}

echo "\n";

// ============================================================================
// TEST 2: System Admin CANNOT access School Operations
// ============================================================================
echo "TEST 2: System Admin (Role 2) - Blocked from School Operations\n";
echo str_repeat("-", 60) . "\n";

$test_cases = [
    ['route' => 'manage_students', 'expected' => false],
    ['route' => 'manage_fees', 'expected' => false],
    ['route' => 'manage_payrolls', 'expected' => false],
    ['route' => 'manage_finance', 'expected' => false],
    ['route' => 'manage_academics', 'expected' => false],
    ['route' => 'manage_inventory', 'expected' => false],
    ['route' => 'manage_boarding', 'expected' => false],
    ['route' => 'manage_communications', 'expected' => false],
];

foreach ($test_cases as $case) {
    $tests_run++;
    $authorized = RouteAuthorization::isAuthorized(2, $case['route']);
    $passed = ($authorized === $case['expected']);

    if ($passed) {
        $tests_passed++;
        echo "  {$PASS} System Admin BLOCKED from '{$case['route']}'\n";
    } else {
        echo "  {$FAIL} System Admin CAN access '{$case['route']}' (expected: false, got: {$authorized})\n";
    }
}

echo "\n";

// ============================================================================
// TEST 3: Accountant (Role 10) can access Finance routes
// ============================================================================
echo "TEST 3: Accountant (Role 10) - Access to Finance Operations\n";
echo str_repeat("-", 60) . "\n";

$test_cases = [
    ['route' => 'school_accountant_dashboard', 'expected' => true],
    ['route' => 'manage_fees', 'expected' => true],
    ['route' => 'manage_payments', 'expected' => true],
    ['route' => 'manage_payrolls', 'expected' => true],
];

foreach ($test_cases as $case) {
    $tests_run++;
    $authorized = RouteAuthorization::isAuthorized(10, $case['route']);
    $passed = ($authorized === $case['expected']);

    if ($passed) {
        $tests_passed++;
        echo "  {$PASS} Accountant can access '{$case['route']}'\n";
    } else {
        echo "  {$FAIL} Accountant CANNOT access '{$case['route']}' (expected: true, got: {$authorized})\n";
    }
}

echo "\n";

// ============================================================================
// TEST 4: Accountant CANNOT access System Administration
// ============================================================================
echo "TEST 4: Accountant (Role 10) - Blocked from System Administration\n";
echo str_repeat("-", 60) . "\n";

$test_cases = [
    ['route' => 'system_health', 'expected' => false],
    ['route' => 'manage_users', 'expected' => false],
    ['route' => 'manage_roles', 'expected' => false],
    ['route' => 'error_logs', 'expected' => false],
];

foreach ($test_cases as $case) {
    $tests_run++;
    $authorized = RouteAuthorization::isAuthorized(10, $case['route']);
    $passed = ($authorized === $case['expected']);

    if ($passed) {
        $tests_passed++;
        echo "  {$PASS} Accountant BLOCKED from '{$case['route']}'\n";
    } else {
        echo "  {$FAIL} Accountant CAN access '{$case['route']}' (expected: false, got: {$authorized})\n";
    }
}

echo "\n";

// ============================================================================
// TEST 5: Teacher (Role 7) can access Academic routes
// ============================================================================
echo "TEST 5: Class Teacher (Role 7) - Access to Teaching Operations\n";
echo str_repeat("-", 60) . "\n";

$test_cases = [
    ['route' => 'class_teacher_dashboard', 'expected' => true],
    ['route' => 'myclasses', 'expected' => true],
    ['route' => 'submit_results', 'expected' => true],
];

foreach ($test_cases as $case) {
    $tests_run++;
    $authorized = RouteAuthorization::isAuthorized(7, $case['route']);
    $passed = ($authorized === $case['expected']);

    if ($passed) {
        $tests_passed++;
        echo "  {$PASS} Teacher can access '{$case['route']}'\n";
    } else {
        echo "  {$FAIL} Teacher CANNOT access '{$case['route']}' (expected: true, got: {$authorized})\n";
    }
}

echo "\n";

// ============================================================================
// TEST 6: Teacher CANNOT access Finance or System routes
// ============================================================================
echo "TEST 6: Class Teacher (Role 7) - Blocked from Finance & System\n";
echo str_repeat("-", 60) . "\n";

$test_cases = [
    ['route' => 'manage_payrolls', 'expected' => false],
    ['route' => 'manage_finance', 'expected' => false],
    ['route' => 'manage_fees', 'expected' => false],
    ['route' => 'system_health', 'expected' => false],
    ['route' => 'manage_users', 'expected' => false],
];

foreach ($test_cases as $case) {
    $tests_run++;
    $authorized = RouteAuthorization::isAuthorized(7, $case['route']);
    $passed = ($authorized === $case['expected']);

    if ($passed) {
        $tests_passed++;
        echo "  {$PASS} Teacher BLOCKED from '{$case['route']}'\n";
    } else {
        echo "  {$FAIL} Teacher CAN access '{$case['route']}' (expected: false, got: {$authorized})\n";
    }
}

echo "\n";

// ============================================================================
// TEST 7: enforceAuthorization method returns correct HTTP codes
// ============================================================================
echo "TEST 7: enforceAuthorization() - HTTP Response Codes\n";
echo str_repeat("-", 60) . "\n";

$test_cases = [
    [
        'role_id' => 2,
        'route' => 'system_health',
        'expected_code' => 200,
        'description' => 'System Admin accessing allowed route'
    ],
    [
        'role_id' => 2,
        'route' => 'manage_students',
        'expected_code' => 403,
        'description' => 'System Admin accessing forbidden school ops route'
    ],
    [
        'role_id' => 10,
        'route' => 'manage_fees',
        'expected_code' => 200,
        'description' => 'Accountant accessing allowed route'
    ],
    [
        'role_id' => 10,
        'route' => 'system_health',
        'expected_code' => 403,
        'description' => 'Accountant accessing forbidden system route'
    ],
    [
        'role_id' => null,
        'route' => 'dashboard',
        'expected_code' => 401,
        'description' => 'Unauthenticated user (null role)'
    ],
];

foreach ($test_cases as $case) {
    $tests_run++;
    $result = RouteAuthorization::enforceAuthorization($case['role_id'], $case['route']);
    $passed = ($result['http_code'] === $case['expected_code']);

    if ($passed) {
        $tests_passed++;
        echo "  {$PASS} {$case['description']}\n";
        echo "         HTTP {$result['http_code']}: {$result['message']}\n";
    } else {
        echo "  {$FAIL} {$case['description']}\n";
        echo "         Expected: {$case['expected_code']}, Got: {$result['http_code']}\n";
        echo "         Message: {$result['message']}\n";
    }
}

echo "\n";

// ============================================================================
// TEST 8: Role-Route Matrix - Check all roles have definitions
// ============================================================================
echo "TEST 8: Role-Route Matrix - Completeness Check\n";
echo str_repeat("-", 60) . "\n";

$all_role_ids = [2, 3, 4, 5, 6, 7, 8, 9, 10, 14, 16, 18, 21, 23, 24, 63];
$undefined_roles = [];

foreach ($all_role_ids as $role_id) {
    $tests_run++;
    $routes = RouteAuthorization::getAllowedRoutesForRole($role_id);

    if (!empty($routes)) {
        $tests_passed++;
        echo "  {$PASS} Role {$role_id} has " . count($routes) . " allowed routes\n";
    } else {
        echo "  {$FAIL} Role {$role_id} has NO routes defined!\n";
        $undefined_roles[] = $role_id;
    }
}

echo "\n";

// ============================================================================
// TEST 9: System Routes vs School Operations Routes - No overlap
// ============================================================================
echo "TEST 9: Route Separation - System vs School Operations\n";
echo str_repeat("-", 60) . "\n";

$system_routes = RouteAuthorization::SYSTEM_ADMIN_ONLY_ROUTES;
$school_routes = RouteAuthorization::SCHOOL_OPERATIONS_ROUTES;
$overlap = array_intersect($system_routes, $school_routes);

$tests_run++;
if (empty($overlap)) {
    $tests_passed++;
    echo "  {$PASS} No overlap between System Admin and School Operations routes\n";
    echo "         System routes: " . count($system_routes) . "\n";
    echo "         School routes: " . count($school_routes) . "\n";
} else {
    echo "  {$FAIL} Found " . count($overlap) . " overlapping routes:\n";
    foreach ($overlap as $route) {
        echo "         - {$route}\n";
    }
}

echo "\n";

// ============================================================================
// SUMMARY
// ============================================================================
echo "════════════════════════════════════════════════════════════════\n";
echo "TEST SUMMARY\n";
echo "════════════════════════════════════════════════════════════════\n";
echo "Total Tests Run:     {$tests_run}\n";
echo "Tests Passed:        {$tests_passed}\n";
echo "Tests Failed:        " . ($tests_run - $tests_passed) . "\n";

$pass_rate = ($tests_run > 0) ? round(($tests_passed / $tests_run) * 100, 2) : 0;
echo "Pass Rate:           {$pass_rate}%\n";

if ($tests_passed === $tests_run) {
    echo "\n{$PASS} ALL TESTS PASSED - Route authorization working correctly!\n\n";
} else {
    echo "\n{$FAIL} SOME TESTS FAILED - Review configuration\n\n";
}

echo "════════════════════════════════════════════════════════════════\n\n";
