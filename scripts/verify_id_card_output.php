<?php
// Throwaway verification: render student + staff ID cards via the real generator.
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/DashboardRouter.php';
require_once __DIR__ . '/../database/Database.php';

use App\API\Modules\students\StudentIDCardGenerator;
use App\API\Modules\staff\StaffIDCardGenerator;

function summarize(string $label, array $resp): void
{
    $ok = $resp['success'] ?? false;
    echo "\n=== $label ===\n";
    echo "success: " . var_export($ok, true) . "\n";
    if (!$ok) {
        echo "error: " . ($resp['message'] ?? '?') . "\n";
        return;
    }
    if (isset($resp['data']['html'])) {
        $html = $resp['data']['html'];
        echo "html length: " . strlen($html) . "\n";
        $fronts = substr_count($html, 'id-card-front');
        $backs = substr_count($html, 'id-card-back');
        $qr = substr_count($html, 'data:image/png;base64,');
        $rows = substr_count($html, 'person-card-row');
        $broken = substr_count($html, 'alt="QR Code"') + substr_count($html, '<img');
        echo "id-card-front: $fronts | id-card-back: $backs | person-card-row: $rows\n";
        echo "QR data-uri (base64 png): $qr | total <img> tags: $broken\n";
        echo "has 85.60mm width: " . (str_contains($html, '85.60mm') ? 'YES' : 'NO') . "\n";
    } else {
        echo "pdf_url: " . ($resp['data']['pdf_url'] ?? 'none') . "\n";
        echo "student_count: " . ($resp['data']['student_count'] ?? '?') . "\n";
        echo "card_sides: " . ($resp['data']['card_sides'] ?? '?') . "\n";
        if (!empty($resp['data']['pdf_url'])) {
            $local = ltrim(parse_url($resp['data']['pdf_url'], PHP_URL_PATH), '/');
            $bytes = file_exists($local) ? filesize($local) : 0;
            echo "pdf file bytes: $bytes\n";
        }
    }
}

// SINGLE student printable HTML (direct_card)
$gen = new StudentIDCardGenerator();
$single = $gen->generatePrintableSingle(1, 'both', 'direct_card');
summarize('STUDENT single direct_card', $single);

// BULK student PDF (a4_sheet) - 3 ids if available
$bulk = $gen->generateBulkIDCardsPDF([1, 2, 3], 'a4_sheet', true, true);
summarize('STUDENT bulk a4_sheet [1,2,3]', $bulk);

// BULK student PDF (25 ids) - check pagination/no blanks
$bigIds = range(1, 25);
$bulkBig = $gen->generateBulkIDCardsPDF($bigIds, 'a4_sheet', true, true);
summarize('STUDENT bulk a4_sheet [1..25]', $bulkBig);

// STAFF single + bulk
$sgen = new StaffIDCardGenerator();
$staffSingle = $sgen->generatePrintableSingle(1, 'both', 'direct_card');
summarize('STAFF single direct_card', $staffSingle);
$staffBulk = $sgen->generateBulkIDCardsPDF([1, 2, 3], 'a4_sheet', true, true);
summarize('STAFF bulk a4_sheet [1,2,3]', $staffBulk);
