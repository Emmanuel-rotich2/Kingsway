<?php
/**
 * Personal Timetable — Portrait A4
 *
 * Weekly timetable for a specific class/stream or teacher.
 * Variables:
 *   $title, $subtitle, $className, $streamName, $teacherName, $term, $year,
 *   $periods (array: Mon-Fri, each with time slots),
 *   $days ('Mon','Tue','Wed','Thu','Fri'),
 *   $timeSlots (array of {start, end}),
 *   $grid (2D: grid[day][slot] = {subject, room, teacher}),
 *   $schoolName, $schoolMotto
 */
declare(strict_types=1);
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__) { http_response_code(404); exit; } // template only: never a direct request
if (!function_exists('te')) { function te(mixed $v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }}

$days = $days ?? ['Monday','Tuesday','Wednesday','Thursday','Friday'];
$dayAbbr = ['Monday'=>'Mon','Tuesday'=>'Tue','Wednesday'=>'Wed','Thursday'=>'Thu','Friday'=>'Fri'];
$timeSlots = $timeSlots ?? [];
$grid = $grid ?? [];
$subjectColors = [
    'Mathematics'=>'#dbeafe','English'=>'#fce7f3','Kiswahili'=>'#fef3c7',
    'Science'=>'#d1fae5','Social Studies'=>'#e0e7ff','Religious Education'=>'#f3e8ff',
    'Creative Arts'=>'#fef9c3','Physical Education'=>'#ccfbf1','Home Science'=>'#ffe4e6',
    'Pre-Technical'=>'#e0f2fe','Health Education'=>'#ecfdf5',
];
$defaultColor = '#f3f4f6';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
@page { size: A4 portrait; margin: 12mm 14mm; }
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size:8pt; color:#1b2a23; }

.tt-header { text-align:center; margin-bottom:4mm; }
.tt-header .school-name { font-size:14pt; font-weight:800; color:#0f5b3b; }
.tt-header .school-motto { font-size:8pt; color:#d3ad24; font-style:italic; }
.tt-header .tt-title { font-size:11pt; font-weight:700; color:#083f2b; margin-top:2mm; }
.tt-header .tt-subtitle { font-size:8.5pt; color:#5e6e65; }

.tt-table { width:100%; border-collapse:collapse; font-size:7.5pt; border:1pt solid #0f5b3b; }
.tt-table th { background:#0f5b3b; color:#fff; padding:2mm 1.5mm; text-align:center; font-weight:700; font-size:7pt; }
.tt-table th.time-col { background:#083f2b; width:14%; }
.tt-table td { padding:1.5mm 1mm; border:0.5pt solid #c7d3cc; text-align:center; vertical-align:middle; min-height:8mm; }
.tt-table td.time-cell { background:#f3f4f6; font-weight:700; text-align:center; font-size:6.5pt; color:#083f2b; }

.tt-subject { font-weight:700; font-size:7.5pt; }
.tt-room { font-size:6pt; color:#5e6e65; }
.tt-teacher { font-size:6pt; color:#6b7280; font-style:italic; }

.tt-break td { background:#fef3c7 !important; font-weight:700; color:#92400e; font-size:7pt; }
.tt-lunch td { background:#fee2e2 !important; font-weight:700; color:#991b1b; font-size:7pt; }

.tt-legend { margin-top:3mm; font-size:6.5pt; display:flex; flex-wrap:wrap; gap:1.5mm; }
.tt-legend-item { display:flex; align-items:center; gap:1mm; padding:0.5mm 1.5mm; border-radius:0.5mm; }
.tt-legend-swatch { width:3mm; height:3mm; border-radius:0.5mm; display:inline-block; }
</style>
</head>
<body>
<div class="tt-header">
    <div class="school-name"><?= te($schoolName ?? 'KINGSWAY PREPARATORY SCHOOL') ?></div>
    <div class="school-motto">"<?= te($schoolMotto ?? 'In God We Soar') ?>"</div>
    <div class="tt-title"><?= te($title ?? 'CLASS TIMETABLE') ?></div>
    <div class="tt-subtitle">
        <?php if (!empty($className)): ?>Class: <?= te($className) ?><?php endif; ?>
        <?php if (!empty($streamName)): ?> — <?= te($streamName) ?><?php endif; ?>
        <?php if (!empty($term)): ?> | <?= te($term) ?><?php endif; ?>
        <?php if (!empty($year)): ?> <?= te($year) ?><?php endif; ?>
    </div>
</div>

<?php if (!empty($timeSlots) && !empty($grid)): ?>
<table class="tt-table">
    <thead>
        <tr>
            <th class="time-col">Time</th>
            <?php foreach ($days as $day): ?>
            <th><?= te($dayAbbr[$day] ?? substr($day,0,3)) ?></th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($timeSlots as $si => $slot):
        $isBreak = strtolower($slot['type'] ?? '') === 'break';
        $isLunch = strtolower($slot['type'] ?? '') === 'lunch';
        $rowClass = $isBreak ? 'tt-break' : ($isLunch ? 'tt-lunch' : '');
    ?>
        <tr class="<?= $rowClass ?>">
            <td class="time-cell"><?= te($slot['start'] ?? '') ?><br><?= te($slot['end'] ?? '') ?></td>
            <?php foreach ($days as $day):
                $cell = $grid[$day][$si] ?? null;
            ?>
                <?php if ($isBreak || $isLunch): ?>
                    <td><?= $isBreak ? 'BREAK' : 'LUNCH' ?></td>
                <?php elseif ($cell): ?>
                    <?php
                    $bg = $subjectColors[$cell['subject'] ?? ''] ?? $defaultColor;
                    ?>
                    <td style="background:<?= $bg ?>;">
                        <div class="tt-subject"><?= te($cell['subject'] ?? '') ?></div>
                        <?php if (!empty($cell['room'])): ?>
                        <div class="tt-room">Rm: <?= te($cell['room']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($cell['teacher'])): ?>
                        <div class="tt-teacher"><?= te($cell['teacher']) ?></div>
                        <?php endif; ?>
                    </td>
                <?php else: ?>
                    <td style="background:#f9fafb;"></td>
                <?php endif; ?>
            <?php endforeach; ?>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<div class="tt-legend">
    <strong style="width:100%;font-size:7pt;color:#0f5b3b;">Subject Colour Key:</strong>
    <?php foreach ($subjectColors as $subj => $color): ?>
    <div class="tt-legend-item"><span class="tt-legend-swatch" style="background:<?= $color ?>;"></span> <?= te($subj) ?></div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<p style="text-align:center;color:#9ca3af;padding:10mm;">No timetable data available.</p>
<?php endif; ?>
</body>
</html>
