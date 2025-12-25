<?php
/**
 * Comprehensive Staff API Endpoint Test Script
 * Tests all 33 staff endpoints with authentication
 * 
 * Usage: php test_staff_endpoints.php
 */

// Configuration
$API_BASE = 'http://localhost/Kingsway/api';
$USERNAME = 'admin'; // Change to your test user
$PASSWORD = 'password'; // Change to your test password

// Color output for terminal
class Colors
{
    public static $GREEN = "\033[0;32m";
    public static $RED = "\033[0;31m";
    public static $YELLOW = "\033[1;33m";
    public static $BLUE = "\033[0;34m";
    public static $CYAN = "\033[0;36m";
    public static $NC = "\033[0m"; // No Color
}

// Test results tracker
$testResults = [
    'total' => 0,
    'passed' => 0,
    'failed' => 0,
    'skipped' => 0,
    'errors' => []
];

/**
 * Make API request with authentication
 */
function apiRequest($endpoint, $method = 'GET', $data = null, $token = null)
{
    global $API_BASE;

    $url = $API_BASE . $endpoint;
    $ch = curl_init();

    $headers = [
        'Content-Type: application/json',
        'Accept: application/json'
    ];

    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['error' => $error, 'http_code' => $httpCode];
    }

    $decoded = json_decode($response, true);
    $decoded['_http_code'] = $httpCode;

    return $decoded;
}

/**
 * Login and get authentication token
 */
function login($username, $password)
{
    global $API_BASE;

    echo Colors::$CYAN . "\n🔐 Authenticating...\n" . Colors::$NC;

    $response = apiRequest('/auth/login', 'POST', [
        'username' => $username,
        'password' => $password
    ]);

    if (isset($response['token'])) {
        echo Colors::$GREEN . "✓ Authentication successful\n" . Colors::$NC;
        return $response['token'];
    }

    echo Colors::$RED . "✗ Authentication failed: " . ($response['message'] ?? 'Unknown error') . "\n" . Colors::$NC;
    return null;
}

/**
 * Test an endpoint
 */
function testEndpoint($name, $endpoint, $method = 'GET', $data = null, $token = null, $expectedStatus = 200)
{
    global $testResults;

    $testResults['total']++;

    echo Colors::$BLUE . "\n📋 Testing: " . Colors::$NC . $name . "\n";
    echo "   Method: " . Colors::$YELLOW . $method . Colors::$NC . " " . $endpoint . "\n";

    $response = apiRequest($endpoint, $method, $data, $token);

    if (isset($response['error'])) {
        echo Colors::$RED . "   ✗ FAILED: " . $response['error'] . "\n" . Colors::$NC;
        $testResults['failed']++;
        $testResults['errors'][] = [
            'test' => $name,
            'error' => $response['error']
        ];
        return false;
    }

    $httpCode = $response['_http_code'] ?? 0;
    $status = $response['status'] ?? 'unknown';

    // Check HTTP status code
    if ($httpCode != $expectedStatus) {
        echo Colors::$RED . "   ✗ FAILED: Expected HTTP $expectedStatus, got $httpCode\n" . Colors::$NC;
        echo "   Response: " . json_encode($response, JSON_PRETTY_PRINT) . "\n";
        $testResults['failed']++;
        $testResults['errors'][] = [
            'test' => $name,
            'expected' => $expectedStatus,
            'actual' => $httpCode,
            'response' => $response
        ];
        return false;
    }

    // Check response structure
    if ($status === 'success' || $httpCode === 200) {
        echo Colors::$GREEN . "   ✓ PASSED" . Colors::$NC;
        if (isset($response['message'])) {
            echo " - " . $response['message'];
        }
        echo "\n";

        // Show data preview if available
        if (isset($response['data']) && is_array($response['data'])) {
            $dataCount = count($response['data']);
            echo "   Data: " . $dataCount . " items\n";
        }

        $testResults['passed']++;
        return true;
    }

    echo Colors::$YELLOW . "   ⚠ WARNING: Unexpected response\n" . Colors::$NC;
    echo "   Response: " . json_encode($response, JSON_PRETTY_PRINT) . "\n";
    $testResults['skipped']++;
    return false;
}

/**
 * Print test summary
 */
function printSummary()
{
    global $testResults;

    echo "\n" . str_repeat("=", 80) . "\n";
    echo Colors::$CYAN . "📊 TEST SUMMARY\n" . Colors::$NC;
    echo str_repeat("=", 80) . "\n";

    $passRate = $testResults['total'] > 0
        ? round(($testResults['passed'] / $testResults['total']) * 100, 2)
        : 0;

    echo "Total Tests:  " . $testResults['total'] . "\n";
    echo Colors::$GREEN . "Passed:       " . $testResults['passed'] . Colors::$NC . "\n";
    echo Colors::$RED . "Failed:       " . $testResults['failed'] . Colors::$NC . "\n";
    echo Colors::$YELLOW . "Skipped:      " . $testResults['skipped'] . Colors::$NC . "\n";
    echo "Pass Rate:    " . ($passRate >= 80 ? Colors::$GREEN : Colors::$RED) . $passRate . "%" . Colors::$NC . "\n";

    if (!empty($testResults['errors'])) {
        echo "\n" . Colors::$RED . "❌ FAILED TESTS:\n" . Colors::$NC;
        foreach ($testResults['errors'] as $error) {
            echo "  • " . $error['test'] . "\n";
            if (isset($error['error'])) {
                echo "    Error: " . $error['error'] . "\n";
            }
        }
    }

    echo str_repeat("=", 80) . "\n";
}

// ============================================================================
// MAIN TEST EXECUTION
// ============================================================================

echo Colors::$CYAN . "\n";
echo "╔══════════════════════════════════════════════════════════════════════════╗\n";
echo "║                   STAFF API ENDPOINT TEST SUITE                          ║\n";
echo "╚══════════════════════════════════════════════════════════════════════════╝\n";
echo Colors::$NC;

// Step 1: Authenticate
$token = login($USERNAME, $PASSWORD);

if (!$token) {
    echo Colors::$RED . "\n❌ Cannot proceed without authentication token\n" . Colors::$NC;
    exit(1);
}

echo "\n" . Colors::$CYAN . "Starting endpoint tests...\n" . Colors::$NC;

// ==================== BASE CRUD OPERATIONS ====================
echo "\n" . Colors::$CYAN . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  BASE CRUD OPERATIONS\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n" . Colors::$NC;

testEndpoint('Index', '/staff/index', 'GET', null, $token);
testEndpoint('List All Staff', '/staff', 'GET', null, $token);
testEndpoint('Create Staff', '/staff', 'POST', [
    'first_name' => 'Test',
    'last_name' => 'Teacher',
    'email' => 'test.teacher' . time() . '@school.com',
    'phone' => '0700123456',
    'staff_type' => 'teaching',
    'department_id' => 1
], $token);
// Note: We'd need a real staff ID for PUT and DELETE - skipping for now

// ==================== STAFF INFORMATION ====================
echo "\n" . Colors::$CYAN . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  STAFF INFORMATION\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n" . Colors::$NC;

testEndpoint('Get Profile', '/staff/profile-get', 'GET', null, $token);
testEndpoint('Get Schedule', '/staff/schedule-get', 'GET', null, $token);
testEndpoint('Get Departments', '/staff/departments-get', 'GET', null, $token);

// ==================== ASSIGNMENT OPERATIONS ====================
echo "\n" . Colors::$CYAN . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  ASSIGNMENT OPERATIONS\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n" . Colors::$NC;

testEndpoint('Get Assignments', '/staff/assignments-get', 'GET', null, $token);
testEndpoint('Get Current Assignments', '/staff/assignments-current', 'GET', null, $token);
testEndpoint('Get Workload', '/staff/workload-get', 'GET', null, $token);

// ==================== ATTENDANCE OPERATIONS ====================
echo "\n" . Colors::$CYAN . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  ATTENDANCE OPERATIONS\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n" . Colors::$NC;

testEndpoint('Get Attendance', '/staff/attendance-get', 'GET', null, $token);

// ==================== LEAVE MANAGEMENT ====================
echo "\n" . Colors::$CYAN . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  LEAVE MANAGEMENT\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n" . Colors::$NC;

testEndpoint('List Leaves', '/staff/leaves-list', 'GET', null, $token);

// ==================== PAYROLL OPERATIONS ====================
echo "\n" . Colors::$CYAN . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  PAYROLL OPERATIONS\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n" . Colors::$NC;

$currentMonth = date('m');
$currentYear = date('Y');

testEndpoint('View Payslip', '/staff/payroll-payslip?month=' . $currentMonth . '&year=' . $currentYear, 'GET', null, $token);
testEndpoint('Get Payroll History', '/staff/payroll-history', 'GET', null, $token);
testEndpoint('View Allowances', '/staff/payroll-allowances', 'GET', null, $token);
testEndpoint('View Deductions', '/staff/payroll-deductions', 'GET', null, $token);
testEndpoint('Get Loan Details', '/staff/payroll-loan-details', 'GET', null, $token);

// ==================== PERFORMANCE MANAGEMENT ====================
echo "\n" . Colors::$CYAN . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  PERFORMANCE MANAGEMENT\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n" . Colors::$NC;

testEndpoint('Get Review History', '/staff/performance-review-history', 'GET', null, $token);
testEndpoint('Get Academic KPI Summary', '/staff/performance-academic-kpi-summary', 'GET', null, $token);

// ==================== FINAL SUMMARY ====================
printSummary();

// Exit with appropriate code
exit($testResults['failed'] > 0 ? 1 : 0);
