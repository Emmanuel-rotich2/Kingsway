<?php
/**
 * Fee Structure Comparison — Landscape A4
 *
 * Side-by-side comparison of Day, Boarding, Weekly fees.
 * Variables: $academicYear, $studentTypes (array of type configs), $feeItems, $terms, $schoolName, etc.
 */
declare(strict_types=1);
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__) { http_response_code(404); exit; } // template only: never a direct request
if (!function_exists('fe')) { function fe(mixed $v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }}
if (!function_exists('fm')) { function fm($v): string { return number_format((float)($v ?? 0), 2); }}

$academicYear = $academicYear ?? date('Y');
$studentTypes = $studentTypes ?? ['Day','Boarding','Weekly'];
$feeItems = $feeItems ?? [];
$terms = $terms ?? ['Term 1','Term 2','Term 3'];
$typeColors = ['#10b981', '#3b82f6', '#8b5cf6'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
@page { size: A4 landscape; margin: 10mm 14mm; }
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size:7.5pt; color:#1b2a23; }

.fsc-header { text-align:center; margin-bottom:4mm; }
.fsc-header .school-name { font-size:14pt; font-weight:800; color:#0f5b3b; }
.fsc-header .school-motto { font-size:8pt; color:#d3ad24; font-style:italic; }
.fsc-header .fsc-title { font-size:11pt; font-weight:700; color:#083f2b; margin-top:2mm; }

.fsc-table { width:100%; border-collapse:collapse; font-size:7pt; }
.fsc-table th { background:#0f5b3b; color:#fff; padding:1.5mm 2mm; text-align:left; font-weight:700; font-size:6.5pt; }
.fsc-table th.num { text-align:right; }
.fsc-table td { padding:1mm 2mm; border:0.4pt solid #e5e7eb; }
.fsc-table td.num { text-align:right; font-family:'DejaVu Sans Mono',monospace; }
.fsc-table tr:nth-child(even) { background:#f9fafb; }
.fsc-table tr.type-header td { font-weight:700; font-size:8pt; padding:2mm; }
.fsc-table tr.subtotal td { background:#e8f5e9; font-weight:700; }
.fsc-table tr.grand-total td { background:#0f5b3b; color:#fff; font-weight:800; font-size:8pt; }
</style>
</head>
<body>
<div class="fsc-header">
    <div class="school-name"><?= fe($schoolName ?? 'KINGSWAY PREPARATORY SCHOOL') ?></div>
    <div class="school-motto">"<?= fe($schoolMotto ?? 'In God We Soar') ?>"</div>
    <div class="fsc-title">FEE STRUCTURE COMPARISON — <?= fe($academicYear) ?></div>
</div>

<?php
$colsPerType = count($terms) + 1;
$totalCols = 1 + (count($studentTypes) * $colsPerType);
?>
<table class="fsc-table">
    <thead>
        <tr>
            <th rowspan="2" style="width:12%;">Fee Item</th>
            <?php foreach ($studentTypes as $ti => $type): ?>
            <th colspan="<?= $colsPerType ?>" style="text-align:center;background:<?= $typeColors[$ti % 3] ?>;">
                <?= fe($type) ?> Students
            </th>
            <?php endforeach; ?>
        </tr>
        <tr>
            <?php foreach ($studentTypes as $ti => $type): ?>
            <?php foreach ($terms as $t): ?>
            <th class="num"><?= fe($t) ?></th>
            <?php endforeach; ?>
            <th class="num">Annual</th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
    <?php if (empty($feeItems)): ?>
        <tr><td colspan="<?= $totalCols ?>" style="text-align:center;color:#9ca3af;padding:5mm;">No fee data available.</td></tr>
    <?php else: ?>
        <?php
        $typeGrandTotals = array_fill(0, count($studentTypes), 0);
        foreach ($feeItems as $item):
        ?>
        <tr>
            <td><strong><?= fe($item['name'] ?? '') ?></strong></td>
            <?php foreach ($studentTypes as $ti => $type):
                $typeData = $item['types'][$type] ?? [];
                $annual = 0;
            ?>
            <?php foreach ($terms as $t => $ti2):
                $amt = $typeData[$t] ?? 0;
                $annual += (float)$amt;
            ?>
            <td class="num"><?= fm($amt) ?></td>
            <?php endforeach; ?>
            <td class="num"><strong><?= fm($annual) ?></strong></td>
            <?php
                $typeGrandTotals[$ti] += $annual;
            endforeach; ?>
        </tr>
        <?php endforeach; ?>
        <tr class="subtotal">
            <td><strong>GRAND TOTAL</strong></td>
            <?php foreach ($studentTypes as $ti => $type): ?>
            <?php foreach ($terms as $t): ?>
            <td class="num"></td>
            <?php endforeach; ?>
            <td class="num"><strong><?= fm($typeGrandTotals[$ti]) ?></strong></td>
            <?php endforeach; ?>
        </tr>
    <?php endif; ?>
    </tbody>
</table>
</body>
</html>
