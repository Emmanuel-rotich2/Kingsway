<?php
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__) { http_response_code(404); exit; } // template only: never a direct request
$tplDir = __DIR__;
$feeItems = is_array($feeItems ?? null) ? $feeItems : [];
$terms = is_array($terms ?? null) ? $terms : [];
$rows = [];
foreach ($feeItems as $item) {
    $itemTerms = is_array($item['terms'] ?? null) ? $item['terms'] : [];
    $term1 = $itemTerms[0] ?? ($item['amount'] ?? 0);
    $term2 = $itemTerms[1] ?? 0;
    $term3 = $itemTerms[2] ?? 0;
    $rows[] = ['category' => $item['name'] ?? $item['fee_item_name'] ?? '', 'term1' => $term1, 'term2' => $term2, 'term3' => $term3, 'total' => (float)$term1 + (float)$term2 + (float)$term3];
}
$structureName = trim((string) ($structureName ?? 'FEE STRUCTURE'));
$sections = [['title' => $structureName, 'variant' => 'custom', 'rows' => $rows, 'firstCol' => 'FEE ITEM']];
$documentTitle = strtoupper($structureName) . ' — ' . ($academicYear ?? date('Y'));
$documentSubtitle = strtoupper((string) ($studentType ?? 'STUDENT')) . ' FEES';
include $tplDir . '/fee_structure_simple.php';
