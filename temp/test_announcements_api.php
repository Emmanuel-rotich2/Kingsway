<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

use App\API\Services\DirectorAnalyticsService;

$service = new DirectorAnalyticsService();
$result = $service->getLatestAnnouncements();

echo "=== Announcements API Test ===\n\n";
echo "Total announcements: " . count($result['announcements']) . "\n";
echo "Expiring notices: " . count($result['expiring_notices']) . "\n\n";

echo "--- ANNOUNCEMENTS ---\n";
foreach ($result['announcements'] as $ann) {
    echo "• [{$ann['priority']}] {$ann['title']} ({$ann['announcement_type']})\n";
}

echo "\n--- EXPIRING NOTICES ---\n";
foreach ($result['expiring_notices'] as $notice) {
    echo "• {$notice['title']} - expires in {$notice['days_remaining']} days ({$notice['expires_at']})\n";
}

echo "\n=== JSON OUTPUT ===\n";
echo json_encode($result, JSON_PRETTY_PRINT);
