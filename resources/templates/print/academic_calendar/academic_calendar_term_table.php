<?php
/**
 * Academic Calendar — Body Only (portrait A4)
 *
 * Part of the standard report pipeline:
 *   report_header.php → THIS BODY → report_footer.php
 *   assembled by PrintService::buildReportDocument()
 *
 * Expected variables:
 *   $academicYearLabel — e.g. "2026/2027"
 *   $terms — array of [ title, subtitle, dateLabel, rows[] ]
 *     each row = [ date, activity, bold, note, rowType, venue, timeRange ]
 *   $publicHolidays — array of [ date, name ]
 *   $calendarNote — string
 */
declare(strict_types=1);
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__) { http_response_code(404); exit; } // template only: never a direct request

if (!function_exists('ce')) {
    function ce(mixed $v): string {
        return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
if (!function_exists('calDate')) {
    function calDate(string $dateStr): string {
        if (!$dateStr) return '';
        $d = \DateTime::createFromFormat('Y-m-d', $dateStr);
        return $d ? $d->format('j M Y') : $dateStr;
    }
}
if (!function_exists('calDateShort')) {
    function calDateShort(string $dateStr): string {
        if (!$dateStr) return '';
        $d = \DateTime::createFromFormat('Y-m-d', $dateStr);
        return $d ? $d->format('j M') : $dateStr;
    }
}
?>

<style>
.acal-term-block { margin-bottom:4mm; page-break-inside:avoid; }
.acal-term-header {
    background:#0f5b3b; color:#fff;
    padding:2mm 3mm; font-size:11pt; font-weight:700;
    border-radius:1mm 1mm 0 0;
    display:flex; justify-content:space-between; align-items:baseline;
}
.acal-term-header .acal-term-dates { font-size:8pt; font-weight:400; opacity:.9; }
.acal-term-subtitle {
    background:#e8f5e9; padding:1.5mm 3mm;
    font-size:8pt; color:#2e7d32;
    border-left:3pt solid #0f5b3b; border-bottom:.5pt solid #c8e6c9;
}
.acal-table { width:100%; border-collapse:collapse; font-size:8.5pt; }
.acal-table th {
    background:#f1f8f4; color:#0f5b3b; font-weight:700; text-align:left;
    padding:1.5mm 2mm; border-bottom:1pt solid #0f5b3b;
    font-size:7.5pt; text-transform:uppercase; letter-spacing:.3pt;
}
.acal-table th.col-date  { width:18%; }
.acal-table th.col-event { width:40%; }
.acal-table th.col-time  { width:15%; }
.acal-table th.col-venue { width:27%; }
.acal-table td {
    padding:1.5mm 2mm; border-bottom:.3pt solid #e0e8e3; vertical-align:top;
}
.acal-table tr:nth-child(even) td { background:#f9fbfa; }
.acal-table tr.row-opening td,
.acal-table tr.row-closing td  { background:#d4edda; font-weight:700; }
.acal-table tr.row-halfterm td { background:#fff8e1; }
.acal-table tr.row-holiday td  { background:#fce4ec; }
.acal-table tr.row-exam td     { background:#e3f2fd; }
.acal-table td.col-date  { white-space:nowrap; color:#37474f; font-weight:500; }
.acal-table td.col-event { color:#212121; }
.acal-table td.col-time  { color:#546e7a; white-space:nowrap; }
.acal-table td.col-venue { color:#6d4c41; font-style:italic; }
.acal-table td .target-badge {
    display:inline-block; font-size:6.5pt;
    background:#0f5b3b; color:#fff;
    padding:.2mm 1.5mm; border-radius:.5mm;
    margin-left:1.5mm; font-weight:600;
    text-transform:uppercase; vertical-align:middle;
}
.acal-table td .note {
    display:block; font-size:7.5pt; color:#757575;
    font-style:italic; margin-top:.3mm;
}
.acal-holidays {
    margin-top:3mm; padding:2mm 3mm;
    background:#fce4ec; border-radius:1mm;
    page-break-inside:avoid;
}
.acal-holidays h3 { font-size:9pt; color:#c62828; margin-bottom:1.5mm; }
.acal-holiday-table { width:100%; border-collapse:collapse; font-size:8.5pt; }
.acal-holiday-table td { padding:1mm 2mm; border-bottom:.3pt solid #f8bbd0; }
.acal-holiday-table td.col-date  { width:18%; font-weight:500; color:#37474f; }
.acal-holiday-table td.col-event { color:#b71c1c; font-weight:600; }
.acal-note {
    margin-top:4mm; padding:2mm 3mm;
    font-size:8pt; color:#5e6e65; font-style:italic;
    border-top:1pt solid #c8e6c9; line-height:1.5;
}
</style>

<?php foreach (($terms ?? []) as $term): ?>
<div class="acal-term-block">
    <div class="acal-term-header">
        <span><?= ce($term['title'] ?? 'TERM') ?></span>
        <span class="acal-term-dates"><?= ce($term['dateLabel'] ?? '') ?></span>
    </div>
    <?php if (!empty($term['subtitle'])): ?>
    <div class="acal-term-subtitle"><?= ce($term['subtitle']) ?></div>
    <?php endif; ?>
    <table class="acal-table">
        <thead>
            <tr>
                <th class="col-date">DATE</th>
                <th class="col-event">SCHOOL ACTIVITY</th>
                <th class="col-time">TIME</th>
                <th class="col-venue">VENUE</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($term['rows'])): ?>
            <tr><td colspan="4" style="text-align:center;color:#9e9e9e;padding:3mm;">No events scheduled.</td></tr>
        <?php else: ?>
            <?php foreach ($term['rows'] as $row): ?>
            <?php $rowClass = !empty($row['rowType']) ? 'row-' . $row['rowType'] : ''; ?>
            <tr class="<?= ce($rowClass) ?>">
                <td class="col-date"><?= ce($row['date'] ?? '') ?></td>
                <td class="col-event">
                    <?= ($row['bold'] ?? false) ? '<strong>' : '' ?>
                    <?= ce($row['activity'] ?? '') ?>
                    <?= ($row['bold'] ?? false) ? '</strong>' : '' ?>
                    <?php if (!empty($row['target'])): ?>
                        <span class="target-badge"><?= ce($row['target']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($row['note'])): ?>
                        <span class="note"><?= ce($row['note']) ?></span>
                    <?php endif; ?>
                </td>
                <td class="col-time"><?= ce($row['timeRange'] ?? '') ?></td>
                <td class="col-venue"><?= ce($row['venue'] ?? '') ?></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php endforeach; ?>

<?php if (!empty($publicHolidays)): ?>
<div class="acal-holidays">
    <h3>PUBLIC HOLIDAYS DURING THE ACADEMIC YEAR</h3>
    <table class="acal-holiday-table">
        <thead>
            <tr>
                <td class="col-date" style="font-weight:700">DATE</td>
                <td class="col-event" style="font-weight:700">HOLIDAY</td>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($publicHolidays as $h): ?>
            <tr>
                <td class="col-date"><?= ce(calDate($h['date'] ?? '')) ?></td>
                <td class="col-event"><?= ce($h['name'] ?? '') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<div class="acal-note">
    <?= ce($calendarNote ?? 'This academic calendar applies to all learners from Playgroup to Grade 9. Where an activity is intended for a particular class or group, the relevant class is indicated alongside the activity.') ?>
</div>
