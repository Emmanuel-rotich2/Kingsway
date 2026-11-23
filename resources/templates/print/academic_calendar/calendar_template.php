<?php
/**
 * Academic Year Calendar — Landscape A4
 *
 * Full-page 12-month grid showing term dates, half-terms, holidays, exams.
 * Variables:
 *   $academicYear (string), $months (array), $terms (array),
 *   $holidays (array), $halfTerms (array), $examPeriods (array),
 *   $schoolName, $schoolLogo, $schoolMotto, $schoolAddress
 */
declare(strict_types=1);
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__) { http_response_code(404); exit; } // template only: never a direct request

if (!function_exists('ce')) { function ce(mixed $v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }}

$academicYear = $academicYear ?? date('Y');
$months = $months ?? [];
$terms = $terms ?? [];
$holidays = $holidays ?? [];
$halfTerms = $halfTerms ?? [];
$examPeriods = $examPeriods ?? [];

$termColors = ['#10b981', '#3b82f6', '#8b5cf6'];
$monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
$dayNames = ['Mon','Tue','Wed','Thu','Fri'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
@page { size: A4 landscape; margin: 10mm 12mm; }
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size:7pt; color:#1b2a23; }

.cal-header { text-align:center; margin-bottom:4mm; }
.cal-header .school-name { font-size:14pt; font-weight:800; color:#0f5b3b; }
.cal-header .school-motto { font-size:8pt; color:#d3ad24; font-style:italic; }
.cal-header .cal-title { font-size:11pt; font-weight:700; color:#083f2b; margin-top:1mm; }
.cal-header .school-addr { font-size:6.5pt; color:#5e6e65; }

.cal-grid { display:table; width:100%; border-collapse:collapse; table-layout:fixed; }
.cal-row { display:table-row; }
.cal-cell { display:table-cell; border:0.5pt solid #c7d3cc; vertical-align:top; padding:1.5mm; width:25%; }
.cal-cell-header { background:#0f5b3b; color:#fff; font-weight:700; text-align:center; font-size:8pt; padding:1.5mm; }
.cal-cell-month-name { font-size:8pt; font-weight:700; color:#0f5b3b; text-align:center; padding:1mm 0; border-bottom:0.5pt solid #c7d3cc; margin-bottom:1mm; }

.cal-day-grid { width:100%; border-collapse:collapse; font-size:6pt; }
.cal-day-grid th { background:#f3f4f6; padding:0.5mm; text-align:center; font-weight:700; border:0.3pt solid #e5e7eb; }
.cal-day-grid td { padding:0.4mm; text-align:center; border:0.3pt solid #e5e7eb; }
.cal-day-grid td.term { background:#d1fae5; }
.cal-day-grid td.half-term { background:#fef3c7; }
.cal-day-grid td.holiday { background:#fee2e2; }
.cal-day-grid td.exam { background:#dbeafe; }
.cal-day-grid td.empty { background:#f9fafb; }
.cal-day-grid td.key-date { font-weight:700; border:0.5pt solid #0f5b3b; }

.cal-legend { margin-top:3mm; display:flex; gap:5mm; font-size:6.5pt; }
.cal-legend-item { display:flex; align-items:center; gap:1mm; }
.cal-legend-swatch { width:3mm; height:3mm; border-radius:0.5mm; display:inline-block; }

.cal-summary { margin-top:3mm; }
.cal-summary table { width:100%; border-collapse:collapse; font-size:7pt; }
.cal-summary th { background:#0f5b3b; color:#fff; padding:1.5mm 2mm; text-align:left; }
.cal-summary td { padding:1mm 2mm; border:0.5pt solid #c7d3cc; }
.cal-summary tr:nth-child(even) { background:#f9fafb; }

.cal-holidays { margin-top:2mm; font-size:6.5pt; }
.cal-holidays strong { color:#0f5b3b; }
</style>
</head>
<body>
<div class="cal-header">
    <div class="school-name"><?= ce($schoolName ?? 'KINGSWAY PREPARATORY SCHOOL') ?></div>
    <div class="school-motto">"<?= ce($schoolMotto ?? 'In God We Soar') ?>"</div>
    <div class="cal-addr"><?= ce($schoolAddress ?? '') ?></div>
    <div class="cal-title">ACADEMIC YEAR CALENDAR <?= ce($academicYear) ?></div>
</div>

<div class="cal-grid">
<?php
$rowCount = 0;
foreach ($months as $monthIndex => $monthData):
    $monthName = $monthNames[$monthIndex] ?? 'Month';
    $year = $monthData['year'] ?? date('Y');
    $daysInMonth = $monthData['days_in_month'] ?? (int)date('t', mktime(0,0,0,$monthIndex+1,1,$year));
    $startDay = $monthData['start_day'] ?? (int)date('w', mktime(0,0,0,$monthIndex+1,1,$year));
    $startDay = $startDay === 0 ? 6 : $startDay - 1;
    $termDays = $monthData['term_days'] ?? [];
    $halfTermDays = $monthData['half_term_days'] ?? [];
    $holidayDays = $monthData['holiday_days'] ?? [];
    $examDays = $monthData['exam_days'] ?? [];
    $keyDates = $monthData['key_dates'] ?? [];

    if ($rowCount % 4 === 0 && $rowCount > 0):
?>
</div>
<div class="cal-grid" style="margin-top:2mm;">
<?php
    endif;
    $rowCount++;
?>
<div class="cal-cell">
    <div class="cal-cell-month-name"><?= ce($monthName) ?> <?= ce((string)$year) ?></div>
    <table class="cal-day-grid">
        <tr>
            <?php foreach ($dayNames as $dn): ?>
            <th><?= $dn ?></th>
            <?php endforeach; ?>
        </tr>
        <?php
        $day = 1;
        for ($week = 0; $week < 6; $week++):
            if ($day > $daysInMonth) break;
        ?>
        <tr>
            <?php for ($dow = 0; $dow < 5; $dow++):
                if ($week === 0 && $dow < $startDay || $day > $daysInMonth):
            ?>
            <td class="empty"></td>
            <?php else:
                $cls = '';
                if (in_array($day, $termDays)) $cls = 'term';
                if (in_array($day, $halfTermDays)) $cls = 'half-term';
                if (in_array($day, $holidayDays)) $cls = 'holiday';
                if (in_array($day, $examDays)) $cls = 'exam';
                if (isset($keyDates[$day])) $cls .= ' key-date';
            ?>
            <td class="<?= $cls ?>" title="<?= ce($keyDates[$day] ?? '') ?>"><?= $day ?></td>
            <?php
                $day++;
                endif;
            endfor;
            ?>
        </tr>
        <?php endfor; ?>
    </table>
</div>
<?php endforeach; ?>
</div>

<div class="cal-legend">
    <div class="cal-legend-item"><span class="cal-legend-swatch" style="background:#d1fae5;"></span> Term Time</div>
    <div class="cal-legend-item"><span class="cal-legend-swatch" style="background:#fef3c7;"></span> Half-Term Break</div>
    <div class="cal-legend-item"><span class="cal-legend-swatch" style="background:#fee2e2;"></span> Holiday / Public Holiday</div>
    <div class="cal-legend-item"><span class="cal-legend-swatch" style="background:#dbeafe;"></span> Examination Period</div>
    <div class="cal-legend-item"><span class="cal-legend-swatch" style="border:0.5pt solid #0f5b3b;"></span> Key Date (see labels)</div>
</div>

<?php if (!empty($terms)): ?>
<div class="cal-summary">
    <table>
        <thead>
            <tr><th>Term</th><th>Opening Date</th><th>Half-Term Start</th><th>Half-Term End</th><th>Closing Date</th><th>Weeks</th></tr>
        </thead>
        <tbody>
        <?php foreach ($terms as $i => $t): ?>
        <tr>
            <td><strong style="color:<?= $termColors[$i % 3] ?? '#0f5b3b' ?>;">Term <?= ($i+1) ?></strong></td>
            <td><?= ce($t['opening_date'] ?? '—') ?></td>
            <td><?= ce($t['half_term_start'] ?? '—') ?></td>
            <td><?= ce($t['half_term_end'] ?? '—') ?></td>
            <td><?= ce($t['closing_date'] ?? '—') ?></td>
            <td><?= ce((string)($t['weeks'] ?? '—')) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if (!empty($holidays)): ?>
<div class="cal-holidays">
    <strong>Public Holidays:</strong>
    <?php foreach ($holidays as $h): ?>
    <span style="display:inline-block;margin:0.5mm 2mm;padding:0.3mm 1.5mm;background:#fee2e2;border-radius:0.5mm;">
        <?= ce($h['date'] ?? '') ?> — <?= ce($h['name'] ?? '') ?>
    </span>
    <?php endforeach; ?>
</div>
<?php endif; ?>
</body>
</html>
