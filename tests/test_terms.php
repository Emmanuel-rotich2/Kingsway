<?php
/**
 * Quick test to verify AcademicYearManager creates terms
 */
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_connection.php';
require_once __DIR__ . '/../api/modules/academic/AcademicYearManager.php';

use App\Config\Database;
use App\API\Modules\Academic\AcademicYearManager;

try {
    $db = Database::getInstance()->getConnection();
    $yearManager = new AcademicYearManager($db);

    echo "Testing term creation for existing year...\n";

    // Get current year
    $currentYear = $yearManager->getCurrentAcademicYear();
    echo "Current year: {$currentYear['year_code']}\n";

    // Get terms
    $terms = $yearManager->getTermsForYear($currentYear['id']);
    echo "Terms found: " . count($terms) . "\n";

    if (count($terms) === 0) {
        echo "\nNo terms exist. The migration created the year but terms creation might have failed.\n";
        echo "This is expected if the year was inserted via SQL migration rather than via AcademicYearManager.\n";
        echo "\nTo fix: Run createTermsForYear() manually or recreate the year via the manager.\n";
    } else {
        echo "\n✓ Terms exist!\n";
        foreach ($terms as $term) {
            echo "  - {$term['name']}: {$term['start_date']} to {$term['end_date']}\n";
        }
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
