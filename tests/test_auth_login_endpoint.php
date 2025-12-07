<?php
/**
 * Test /auth/login Endpoint
 * Tests the actual HTTP endpoint to see what data is returned
 */

$apiUrl = 'http://localhost/Kingsway/api/auth/login';

$postData = [
    'username' => 'test_system_administrator',
    'password' => 'testpass'
];

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);

echo "Testing: POST $apiUrl\n";
echo "Credentials: test_system_administrator / testpass\n\n";

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "cURL Error: $error\n";
    exit(1);
}

echo "HTTP Status: $httpCode\n\n";
echo "Raw Response:\n";
echo $response;
echo "\n\n";
echo "Decoded Response:\n";
echo json_encode(json_decode($response), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
echo "\n\n";

// Analyze response structure
$data = json_decode($response, true);
if ($data) {
    echo "Analysis:\n";
    echo "- Status: " . ($data['status'] ?? 'N/A') . "\n";
    echo "- Has 'data' key: " . (isset($data['data']) ? 'YES' : 'NO') . "\n";

    if (isset($data['data'])) {
        echo "- data.token: " . (isset($data['data']['token']) ? 'EXISTS' : 'MISSING') . "\n";
        echo "- data.user: " . (isset($data['data']['user']) ? 'EXISTS' : 'MISSING') . "\n";
        echo "- data.sidebar_items: " . (isset($data['data']['sidebar_items']) ? 'EXISTS (' . count($data['data']['sidebar_items']) . ' items)' : 'MISSING') . "\n";
        echo "- data.dashboard: " . (isset($data['data']['dashboard']) ? 'EXISTS' : 'MISSING') . "\n";

        if (isset($data['data']['dashboard'])) {
            echo "  - dashboard.key: " . ($data['data']['dashboard']['key'] ?? 'N/A') . "\n";
            echo "  - dashboard.url: " . ($data['data']['dashboard']['url'] ?? 'N/A') . "\n";
        }
    }
}
?>