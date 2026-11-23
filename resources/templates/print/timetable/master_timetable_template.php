<?php
/**
 * Master Timetable — Landscape A4
 *
 * Full-school timetable showing all classes across all time slots.
 * Variables:
 *   $title, $academicYear, $termName,
 *   $classes (array of class names),
 *   $timeSlots (array of {start, end, type}),
 *   $days (array: Mon-Fri),
 *   $grid (3D: grid[day][slot][className] = {subject, room, teacher}),
 *   $schoolName, $schoolMotto
 */
declare(strict_types=1);
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__) { http_response_code(404); exit; } // template only: never a direct request
if (!function_exists('me')) { function me(mixed $v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }}

$days = $days ?? ['Monday','Tuesday','Wednesday','Thursday','Friday'];
$dayAbbr = ['Mon','Tue','Wed','Thu','Fri'];
$classes = $classes ?? [];
$timeSlots = $timeSlots ?? [];
$grid = $grid ?? [];
$subjectColors = [
    'Maths'=>'#dbeafe','English'=>'#fce7f3','Kiswahili'=>'#fef3c7',
    'Science'=>'#d1fae5','SST'=>'#e0e7ff','CRE'=>'#f3e8ff',
    'Art'=>'#fef9c3','PE'=>'#ccfbf1','Home Sci'=>'#ffe4e6',
    'Pre-Tech'=>'#e0f2fe','Health'=>'#ecfdf5',
];
$defaultColor = '#f9fafb';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
@page { size: A4 landscape; margin: 8mm 10mm; }
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size:6pt; color:#1b2a23; }

.mt-header { text-align:center; margin-bottom:3mm; }
.mt-header .school-name { font-size:12pt; font-weight:800; color:#0f5b3b; }
.mt-header .mt-title { font-size:10pt; font-weight:700; color:#083f2b; margin-top:1mm; }

.mt-table { width:100%; border-collapse:collapse; font-size:5.5pt; border:0.5pt solid #0f5b3b; table-layout:fixed; }
.mt-table th { background:#0f5b3b; color:#fff; padding:1mm 0.5mm; text-align:center; font-weight:700; font-size:5.5pt; }
.mt-table th.time-col { background:#083f2b; width:5%; font-size:5pt; }
.mt-table th.day-col { width:19%; }
.mt-table th.class-col { font-size:5pt; font-weight:700; padding:0.5mm; }
.mt-table td { padding:0.8mm 0.5mm; border:0.3pt solid #d1d5db; text-align:center; vertical-align:middle; }
.mt-table td.time-cell { background:#f3f4f6; font-weight:700; text-align:center; font-size:5pt; color:#083f2b; }
.mt-table td.break-cell { background:#fef3c7 !important; font-size:5pt; font-weight:700; color:#92400e; }
.mt-table td.lunch-cell { background:#fee2e2 !important; font-size:5pt; font-weight:700; color:#991b1b; }
.mt-table td.class-header { background:#e8f5e9; font-weight:700; font-size:5.5pt; color:#0f5b3b; }

.mt-cell { font-weight:700; font-size:5.5pt; }
.mt-room { font-size:4.5pt; color:#6b7280; }
</style>
</head>
<body>
<div class="mt-header">
    <div class="school-name"><?= me($schoolName ?? 'KINGSWAY PREPARATORY SCHOOL') ?></div>
    <div class="mt-title">MASTER TIMETABLE — <?= me($academicYear ?? date('Y')) ?> <?= me($termName ?? '') ?></div>
</div>

<?php if (!empty($timeSlots) && !empty($classes)): ?>
<table class="mt-table">
    <thead>
        <tr>
            <th class="time-col">Time</th>
            <?php foreach ($days as $day): ?>
            <th class="day-col" colspan="<?= max(1, count($classes)) ?>"><?= me($dayAbbr[array_search($day, $days)] ?? substr($day,0,3)) ?></th>
            <?php endforeach; ?>
        </tr>
        <tr>
            <th></th>
            <?php foreach ($days as $day): ?>
            <?php foreach ($classes as $cls): ?>
            <th class="class-col"><?= me($cls) ?></th>
            <?php endforeach; ?>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($timeSlots as $si => $slot):
        $isBreak = strtolower($slot['type'] ?? '') === 'break';
        $isLunch = strtolower($slot['type'] ?? '') === 'lunch';
        $rowClass = $isBreak ? 'break-cell' : ($isLunch ? 'lunch-cell' : '');
    ?>
        <tr>
            <td class="time-cell"><?= me($slot['start'] ?? '') ?></td>
            <?php foreach ($days as $day): ?>
            <?php foreach ($classes as $cls):
                $cell = $grid[$day][$si][$cls] ?? null;
            ?>
                <?php if ($isBreak || $isLunch): ?>
                <td class="<?= $rowClass ?>"><?= $isBreak ? 'BREAK' : 'LUNCH' ?></td>
                <?php elseif ($cell): ?>
                <?php $bg = $subjectColors[$cell['subject'] ?? ''] ?? $defaultColor; ?>
                <td style="background:<?= $bg ?>;">
                    <div class="mt-cell"><?= me($cell['subject'] ?? '') ?></div>
                    <?php if (!empty($cell['room'])): ?>
                    <div class="mt-room"><?= me($cell['room']) ?></div>
                    <?php endif; ?>
                </td>
                <?php else: ?>
                <td></td>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php endforeach; ?>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php else: ?>
<p style="text-align:center;color:#9ca3af;padding:10mm;">No timetable data available.</p>
<?php endif; ?>
</body>
</html>
