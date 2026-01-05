<?php
/**
 * Uniform Sales API Test Script
 * Tests all uniform-related API endpoints
 */

// Base URL for API
$baseUrl = "http://localhost/kingsway/api/?route=inventory";

// Test function
function testEndpoint($method, $route, $data = null)
{
    global $baseUrl;

    $url = $baseUrl . "&action=" . $route;

    echo "\n" . str_repeat("=", 70) . "\n";
    echo "TEST: $method - $route\n";
    echo "URL: $url\n";
    echo str_repeat("=", 70) . "\n";

    $options = [
        "http" => [
            "method" => $method,
            "header" => "Content-Type: application/json\r\n",
        ]
    ];

    if ($data && ($method === "POST" || $method === "PUT")) {
        $options["http"]["content"] = json_encode($data);
    }

    $context = stream_context_create($options);

    try {
        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            echo "ERROR: Failed to connect to API endpoint\n";
            return null;
        }

        $decoded = json_decode($response, true);
        echo "RESPONSE:\n";
        echo json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        return $decoded;
    } catch (Exception $e) {
        echo "EXCEPTION: " . $e->getMessage() . "\n";
        return null;
    }
}

echo "\n";
echo "╔═══════════════════════════════════════════════════════════════════╗\n";
echo "║         UNIFORM SALES API ENDPOINT TESTS                          ║\n";
echo "╚═══════════════════════════════════════════════════════════════════╝\n";

// Test 1: Get all uniform items
echo "\n[TEST 1/7] List all uniform items\n";
$items = testEndpoint("GET", "getUniformItems");

// Test 2: Get sizes for first uniform item (Sweater - ID 11)
echo "\n[TEST 2/7] Get sizes for uniform item (ID: 11 - Sweater)\n";
$sizes = testEndpoint("GET", "getUniformSizes&item_id=11");

// Test 3: Get uniform sales dashboard
echo "\n[TEST 3/7] Get uniform sales dashboard\n";
$dashboard = testEndpoint("GET", "getUniformDashboard");

// Test 4: Get uniform payment summary
echo "\n[TEST 4/7] Get payment summary\n";
$payments = testEndpoint("GET", "getUniformPaymentSummary");

// Test 5: Register a uniform sale (requires student ID)
// First check if we have students
echo "\n[TEST 5/7] Register new uniform sale\n";
$saleData = [
    "student_id" => 1,  // Assuming first student exists
    "item_id" => 11,    // School Sweater
    "size" => "M",
    "quantity" => 1,
    "unit_price" => 1200,
    "sold_by" => 1
];
$sale = testEndpoint("POST", "postUniformSales", $saleData);

// Test 6: Get student uniform sales history (after sale)
echo "\n[TEST 6/7] Get student uniform sales history (Student ID: 1)\n";
$studentSales = testEndpoint("GET", "getUniformSalesByStudent&student_id=1");

// Test 7: Get/Set student uniform profile
echo "\n[TEST 7/7] Get student uniform profile (Student ID: 1)\n";
$profile = testEndpoint("GET", "getUniformStudentProfile&student_id=1");

echo "\n";
echo "╔═══════════════════════════════════════════════════════════════════╗\n";
echo "║                    TESTS COMPLETED                                 ║\n";
echo "╚═══════════════════════════════════════════════════════════════════╝\n";
echo "\n";
?>