<?php
/**
 * Fully Dynamic A4 Fee Structure Print Engine
 * Layout Matrix:
 * - Multiple Classes + Both Types  => 50/50 Split (Default Layout)
 * - Single Class + Both Types       => Stacked 100% Full Width Tables
 * - Any Scope + Single Type (D/B)   => 100% Full Width Table
 */
$tplDir = __DIR__;
/*
 * CSS is always supplied by PrintService ($printStyles), which resolves the
 * stylesheet from the canonical project root. Templates must NOT resolve
 * filesystem paths themselves (dirname(__DIR__, N) breaks in production where
 * UPLOAD_PATH lives outside the project root).
 */
$cssText = trim((string) ($printStyles ?? ''));
if (!function_exists('fsMoney')) { 
    function fsMoney($amount) { return number_format((float) $amount); } 
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($schoolName ?? 'KINGSWAY PREPARATORY SCHOOL') ?> — Fee Structure</title>
    <style><?= $cssText ?></style>
</head>
<body>
<div class="fs-document">
    <div class="fs-inner-border">
        <?php include $tplDir . '/fee_structure_watermark.php'; ?>
        
        <div class="fs-content-wrap">
            <?php include $tplDir . '/fee_structure_header.php'; ?>

            <main class="fs-content">
                <?php foreach (($sections ?? []) as $section):
                    $variant = $section['variant'] ?? 'custom';
                    $dayRows = $section['dayRows'] ?? [];
                    $boarderRows = $section['boarderRows'] ?? [];
                    $hasDay = !empty($dayRows);
                    $hasBoarder = !empty($boarderRows);
                    
                    // Count total rows to determine if single class
                    $totalDayCount = count($dayRows);
                    $totalBoarderCount = count($boarderRows);
                    $isSingleClass = ($totalDayCount <= 1 && $totalBoarderCount <= 1);
                ?>
                <section class="fs-fee-section">
                    <div class="fs-section-header"><?= htmlspecialchars($section['title'] ?? '') ?></div>
                    
                    <?php if ($variant === 'primary'): ?>
                    <div class="fs-primary-panel">
                        
                        <?php if ($hasDay && $hasBoarder && !$isSingleClass): ?>
                            <!-- CASE 1: MULTIPLE CLASSES + BOTH TYPES -> 50/50 SPLIT (DEFAULT) -->
                            <table class="fs-two-col-table">
                                <tr>
                                    <!-- DAY SCHOLARS -->
                                    <td class="fs-col-cell fs-col-cell--left">
                                        <div class="fs-category-title">&#127979; DAY SCHOLARS</div>
                                        <table class="fs-table">
                                            <colgroup>
                                                <col style="width:24%;">
                                                <col style="width:19%;">
                                                <col style="width:19%;">
                                                <col style="width:19%;">
                                                <col style="width:19%;">
                                            </colgroup>
                                            <thead>
                                                <tr>
                                                    <th>GRADE</th>
                                                    <th>TERM I<br><small>(KSh)</small></th>
                                                    <th>TERM II<br><small>(KSh)</small></th>
                                                    <th>TERM III<br><small>(KSh)</small></th>
                                                    <th>TOTAL<br><small>(KSh)</small></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            <?php foreach ($dayRows as $row): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($row['label'] ?? '') ?></td>
                                                    <td><?= fsMoney($row['term1'] ?? 0) ?></td>
                                                    <td><?= fsMoney($row['term2'] ?? 0) ?></td>
                                                    <td><?= fsMoney($row['term3'] ?? 0) ?></td>
                                                    <td class="fs-total"><?= fsMoney($row['total'] ?? 0) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </td>

                                    <!-- BOARDERS -->
                                    <td class="fs-col-cell fs-col-cell--right fs-col-boarder">
                                        <div class="fs-category-title fs-category-title--boarder">&#128716; BOARDERS</div>
                                        <table class="fs-table">
                                            <colgroup>
                                                <col style="width:24%;">
                                                <col style="width:19%;">
                                                <col style="width:19%;">
                                                <col style="width:19%;">
                                                <col style="width:19%;">
                                            </colgroup>
                                            <thead>
                                                <tr>
                                                    <th>GRADE</th>
                                                    <th>TERM I<br><small>(KSh)</small></th>
                                                    <th>TERM II<br><small>(KSh)</small></th>
                                                    <th>TERM III<br><small>(KSh)</small></th>
                                                    <th>TOTAL<br><small>(KSh)</small></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            <?php foreach ($boarderRows as $row): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($row['label'] ?? '') ?></td>
                                                    <td><?= fsMoney($row['term1'] ?? 0) ?></td>
                                                    <td><?= fsMoney($row['term2'] ?? 0) ?></td>
                                                    <td><?= fsMoney($row['term3'] ?? 0) ?></td>
                                                    <td class="fs-total"><?= fsMoney($row['total'] ?? 0) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                        <?php elseif ($hasDay && $hasBoarder && $isSingleClass): ?>
                            <!-- CASE 2: SINGLE CLASS + BOTH TYPES -> STACKED FULL-WIDTH TABLES -->
                            <div class="fs-category-title">&#127979; DAY SCHOLARS</div>
                            <table class="fs-table fs-table--fullwidth" style="margin-bottom: 2mm;">
                                <colgroup>
                                    <col style="width:40%;">
                                    <col style="width:15%;">
                                    <col style="width:15%;">
                                    <col style="width:15%;">
                                    <col style="width:15%;">
                                </colgroup>
                                <thead>
                                    <tr>
                                        <th>GRADE</th>
                                        <th>TERM I <small>(KSh)</small></th>
                                        <th>TERM II <small>(KSh)</small></th>
                                        <th>TERM III <small>(KSh)</small></th>
                                        <th>TOTAL <small>(KSh)</small></th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($dayRows as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['label'] ?? '') ?></td>
                                        <td><?= fsMoney($row['term1'] ?? 0) ?></td>
                                        <td><?= fsMoney($row['term2'] ?? 0) ?></td>
                                        <td><?= fsMoney($row['term3'] ?? 0) ?></td>
                                        <td class="fs-total"><?= fsMoney($row['total'] ?? 0) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>

                            <div class="fs-category-title fs-category-title--boarder">&#128716; BOARDERS</div>
                            <table class="fs-table fs-table--fullwidth fs-col-boarder">
                                <colgroup>
                                    <col style="width:40%;">
                                    <col style="width:15%;">
                                    <col style="width:15%;">
                                    <col style="width:15%;">
                                    <col style="width:15%;">
                                </colgroup>
                                <thead>
                                    <tr>
                                        <th>GRADE</th>
                                        <th>TERM I <small>(KSh)</small></th>
                                        <th>TERM II <small>(KSh)</small></th>
                                        <th>TERM III <small>(KSh)</small></th>
                                        <th>TOTAL <small>(KSh)</small></th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($boarderRows as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['label'] ?? '') ?></td>
                                        <td><?= fsMoney($row['term1'] ?? 0) ?></td>
                                        <td><?= fsMoney($row['term2'] ?? 0) ?></td>
                                        <td><?= fsMoney($row['term3'] ?? 0) ?></td>
                                        <td class="fs-total"><?= fsMoney($row['total'] ?? 0) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>

                        <?php elseif ($hasDay): ?>
                            <!-- CASE 3: DAY SCHOLAR ONLY -> 100% FULL-WIDTH TABLE -->
                            <div class="fs-category-title">&#127979; DAY SCHOLARS</div>
                            <table class="fs-table fs-table--fullwidth">
                                <colgroup>
                                    <col style="width:40%;">
                                    <col style="width:15%;">
                                    <col style="width:15%;">
                                    <col style="width:15%;">
                                    <col style="width:15%;">
                                </colgroup>
                                <thead>
                                    <tr>
                                        <th>GRADE</th>
                                        <th>TERM I <small>(KSh)</small></th>
                                        <th>TERM II <small>(KSh)</small></th>
                                        <th>TERM III <small>(KSh)</small></th>
                                        <th>TOTAL <small>(KSh)</small></th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($dayRows as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['label'] ?? '') ?></td>
                                        <td><?= fsMoney($row['term1'] ?? 0) ?></td>
                                        <td><?= fsMoney($row['term2'] ?? 0) ?></td>
                                        <td><?= fsMoney($row['term3'] ?? 0) ?></td>
                                        <td class="fs-total"><?= fsMoney($row['total'] ?? 0) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>

                        <?php elseif ($hasBoarder): ?>
                            <!-- CASE 4: BOARDER ONLY -> 100% FULL-WIDTH TABLE -->
                            <div class="fs-category-title fs-category-title--boarder">&#128716; BOARDERS</div>
                            <table class="fs-table fs-table--fullwidth fs-col-boarder">
                                <colgroup>
                                    <col style="width:40%;">
                                    <col style="width:15%;">
                                    <col style="width:15%;">
                                    <col style="width:15%;">
                                    <col style="width:15%;">
                                </colgroup>
                                <thead>
                                    <tr>
                                        <th>GRADE</th>
                                        <th>TERM I <small>(KSh)</small></th>
                                        <th>TERM II <small>(KSh)</small></th>
                                        <th>TERM III <small>(KSh)</small></th>
                                        <th>TOTAL <small>(KSh)</small></th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($boarderRows as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['label'] ?? '') ?></td>
                                        <td><?= fsMoney($row['term1'] ?? 0) ?></td>
                                        <td><?= fsMoney($row['term2'] ?? 0) ?></td>
                                        <td><?= fsMoney($row['term3'] ?? 0) ?></td>
                                        <td class="fs-total"><?= fsMoney($row['total'] ?? 0) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>

                    <?php else: $rows = $section['rows'] ?? []; ?>
                    <!-- JUNIOR SCHOOL / CUSTOM SINGLE FULL-WIDTH TABLE -->
                    <div class="fs-junior-panel">
                        <table class="fs-table fs-table--junior">
                            <colgroup>
                                <col style="width:40%;">
                                <col style="width:15%;">
                                <col style="width:15%;">
                                <col style="width:15%;">
                                <col style="width:15%;">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th><?= htmlspecialchars($section['firstCol'] ?? 'CATEGORY') ?></th>
                                    <th>TERM I <small>(KSh)</small></th>
                                    <th>TERM II <small>(KSh)</small></th>
                                    <th>TERM III <small>(KSh)</small></th>
                                    <th>TOTAL <small>(KSh)</small></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['category'] ?? $row['label'] ?? '') ?></td>
                                    <td><?= fsMoney($row['term1'] ?? 0) ?></td>
                                    <td><?= fsMoney($row['term2'] ?? 0) ?></td>
                                    <td><?= fsMoney($row['term3'] ?? 0) ?></td>
                                    <td class="fs-total"><?= fsMoney($row['total'] ?? 0) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </section>
                <?php endforeach; ?>

                <?php include $tplDir . '/fee_structure_body_lower.php'; ?>
            </main>
        </div>
    </div>

    <!-- ROOT LEVEL FOOTER -->
    <?php include $tplDir . '/fee_structure_page_footer.php'; ?>
</div>
</body>
</html>