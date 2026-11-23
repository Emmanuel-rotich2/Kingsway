<?php
/**
 * CBC Junior Secondary (Grade 7–9) Report Card — Body-only template
 *
 * Uses 8-point KJSEA scale. Used via PrintService::printReportCard().
 */
declare(strict_types=1);
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__) { http_response_code(404); exit; } // template only: never a direct request

if (!function_exists('pe')) { function pe(mixed $v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }}
if (!function_exists('pv')) { function pv(mixed $v, string $f=''): string { return trim((string)($v ?? '')) !== '' ? pe($v) : $f; }}

$student      = $student ?? [];
$term         = $term ?? [];
$scores       = $scores ?? [];
$competencies = $competencies ?? [];
$values       = $values ?? [];
$attendance   = $attendance ?? [];
$ranking      = $ranking ?? [];
$comments     = $comments ?? [];
$kjseaSummary = $kjseaSummary ?? [];
$studentName  = trim(($student['first_name']??'') . ' ' . ($student['last_name']??'')) ?: 'Student';

$kjsea8Labels = ['EE1'=>'Exceptional','EE2'=>'Very Good','ME1'=>'Good','ME2'=>'Fair','AE1'=>'Needs Improvement','AE2'=>'Below Average','BE1'=>'Well Below Avg','BE2'=>'Minimal'];
$kjsea8Colors = ['EE1'=>'#065f46','EE2'=>'#047857','ME1'=>'#2563eb','ME2'=>'#3b82f6','AE1'=>'#d97706','AE2'=>'#f59e0b','BE1'=>'#dc2626','BE2'=>'#ef4444'];
$gradeLabels  = ['BE'=>'Below Expectation','AE'=>'Approaching Expectation','ME'=>'Meeting Expectation','EE'=>'Exceeding Expectations'];
$gradeColors  = ['BE'=>'#ef4444','AE'=>'#f59e0b','ME'=>'#3b82f6','EE'=>'#10b981'];
?>
<style>
.info-table { width:100%; border-collapse:collapse; margin-bottom:3mm; font-size:8pt; }
.info-table td { padding:0.8mm 2mm; border:1px solid #e5e7eb; }
.info-table td:first-child { font-weight:700; background:#f9fafb; width:15%; }

.attendance-box { background:#f0fdf4; border:1px solid #bbf7d0; border-radius:1.5mm; padding:1.5mm 3mm; margin-bottom:3mm; }
.attendance-box table { width:100%; border-collapse:collapse; }
.attendance-box td { padding:0.8mm 2mm; text-align:center; font-size:8pt; }
.attendance-box .label { font-weight:700; color:#0f5b3b; }
.attendance-box .value { font-size:10pt; font-weight:800; }

.section-title { background:#e8f5e9; color:#0f5b3b; padding:1.2mm 3mm; font-size:8.5pt; font-weight:700; border-left:2.5mm solid #0f5b3b; margin:2.5mm 0 2mm; }

.scale-ref { font-size:6.5pt; color:#6b7280; margin-bottom:2mm; line-height:1.7; }
.scale-ref .kjsea-block { display:inline-block; margin-right:1.5mm; padding:0.2mm 1mm; border-radius:0.5mm; font-weight:700; font-size:6pt; color:#fff; }

.subject-table { width:100%; border-collapse:collapse; margin-bottom:2mm; font-size:7.5pt; }
.subject-table th { background:#0f5b3b; color:#fff; padding:1.2mm 1.2mm; text-align:left; font-weight:700; font-size:6.5pt; }
.subject-table td { padding:1mm 1.2mm; border:1px solid #e5e7eb; }
.subject-table tr:nth-child(even) { background:#f9fafb; }
.grade-badge { display:inline-block; padding:0.3mm 1.8mm; border-radius:0.8mm; font-weight:700; font-size:6.5pt; color:#fff; }
.grade-badge-8 { display:inline-block; padding:0.3mm 1.5mm; border-radius:0.6mm; font-weight:700; font-size:6pt; color:#fff; }

.competency-table { width:100%; border-collapse:collapse; margin-bottom:2mm; font-size:7.5pt; }
.competency-table th { background:#f3f4f6; padding:1mm 1.5mm; text-align:left; font-weight:700; border:1px solid #e5e7eb; }
.competency-table td { padding:1mm 1.5mm; border:1px solid #e5e7eb; }

.values-box { margin-bottom:2mm; }
.value-item { display:inline-block; background:#fff3cd; padding:0.6mm 2mm; border-radius:0.8mm; margin:0.3mm; font-size:7.5pt; }
.value-item .rating { font-weight:700; color:#92400e; }

.comment-block { background:#f9fafb; border:1px solid #e5e7eb; border-radius:1.5mm; padding:2mm; margin-bottom:2mm; }
.comment-block strong { color:#0f5b3b; }
.comment-block p { margin-top:0.5mm; font-size:8pt; color:#374151; }

.kjsea-summary { background:#f8fafc; border:1.5px solid #cbd5e1; border-radius:1.5mm; padding:2mm; margin-bottom:2.5mm; }
.kjsea-summary table { width:100%; border-collapse:collapse; font-size:7.5pt; }
.kjsea-summary th { background:#e2e8f0; padding:0.8mm 1.5mm; text-align:left; font-weight:700; }
.kjsea-summary td { padding:0.8mm 1.5mm; border:1px solid #e2e8f0; }

.pathway-box { background:#f0f9ff; border:1px solid #bae6fd; border-radius:1.5mm; padding:1.5mm 3mm; margin-bottom:2.5mm; font-size:7.5pt; color:#0369a1; }
</style>

<table class="info-table">
    <tr>
        <td>Learner Name</td>
        <td><strong><?= pe($studentName) ?></strong></td>
        <td>Admission No</td>
        <td><?= pv($student['admission_no'] ?? null, '—') ?></td>
        <td>Gender</td>
        <td><?= pv($student['gender'] ?? null, '—') ?></td>
    </tr>
    <tr>
        <td>Grade / Stream</td>
        <td><?= pv($student['class_name'] ?? null, '—') ?> <?= $student['stream_name'] ?? '' ? '(' . pe($student['stream_name']) . ')' : '' ?></td>
        <td>Term</td>
        <td><?= pv($term['name'] ?? '') ?: 'Term ' . ($term['term_number']??'1') . ' ' . ($term['year']??date('Y')) ?></td>
        <td>Report Date</td>
        <td><?= date('d F Y') ?></td>
    </tr>
    <?php if (!empty($ranking['class_position'])): ?>
    <tr><td>Class Position</td><td><?= (int)$ranking['class_position'] ?> / <?= (int)($ranking['class_population']??0) ?></td><td>Cohort Position</td><td><?= (int)($ranking['cohort_position']??0) ?> / <?= (int)($ranking['cohort_population']??0) ?></td><td>Overall</td><td><?= pv($ranking['overall_percentage'] ?? null, '—') ?>%</td></tr>
    <?php endif; ?>
</table>

<?php if (!empty($attendance)): ?>
<div class="attendance-box">
    <table>
        <tr>
            <td><span class="label">School Days</span><br><span class="value"><?= (int)($attendance['total_days']??0) ?></span></td>
            <td><span class="label">Present</span><br><span class="value" style="color:#10b981;"><?= (int)($attendance['days_present']??0) ?></span></td>
            <td><span class="label">Absent</span><br><span class="value" style="color:#ef4444;"><?= (int)($attendance['days_absent']??0) ?></span></td>
            <td><span class="label">Late</span><br><span class="value" style="color:#f59e0b;"><?= (int)($attendance['days_late']??0) ?></span></td>
        </tr>
    </table>
</div>
<?php endif; ?>

<div class="scale-ref">
    <strong>KJSEA 8-Point Scale:</strong>
    <?php foreach ($kjsea8Labels as $code => $label): ?>
    <span class="kjsea-block" style="background:<?= $kjsea8Colors[$code] ?? '#6b7280' ?>;"><?= pe($code) ?></span><?= pe($label) ?>
    <?php endforeach; ?>
</div>

<?php if (!empty($comments['pathway_suggestion'])): ?>
<div class="pathway-box">
    <strong>Senior School Pathway Recommendation:</strong> <?= pe($comments['pathway_suggestion']) ?>
    — Based on performance in STEM / Social Sciences / Arts &amp; Sports Science clusters.
</div>
<?php endif; ?>

<div class="section-title">Learning Areas — KJSEA-Aligned Performance</div>
<table class="subject-table">
    <thead><tr><th style="width:20%">Learning Area</th><th style="width:8%">Form.</th><th style="width:8%">Summ.</th><th style="width:12%">Grade</th><th style="width:6%">Pts</th><th>Comment</th></tr></thead>
    <tbody>
    <?php if (empty($scores)): ?>
        <tr><td colspan="6" style="text-align:center;color:#9ca3af;">No assessment data available.</td></tr>
    <?php else: ?>
        <?php foreach ($scores as $s): ?>
        <?php
            $g = strtoupper((string)($s['overall_grade']??''));
            $pts = (string)($s['overall_points'] ?? '');
            $isKjsea = isset($kjsea8Labels[$g]);
        ?>
        <tr>
            <td><strong><?= pe($s['subject_name'] ?? '') ?></strong></td>
            <td><?= pv($s['formative_total'] ?? null, '—') ?></td>
            <td><?= pv($s['summative_total'] ?? null, '—') ?></td>
            <td>
                <?php if ($isKjsea): ?>
                <span class="grade-badge-8" style="background:<?= $kjsea8Colors[$g] ?? '#6b7280' ?>;"><?= pe($g) ?></span>
                <span style="font-size:6pt;color:#6b7280;display:block;"><?= pe($kjsea8Labels[$g] ?? '') ?></span>
                <?php elseif (isset($gradeLabels[$g])): ?>
                <span class="grade-badge" style="background:<?= $gradeColors[$g] ?? '#6b7280' ?>;"><?= pe($g) ?></span>
                <?php else: ?>—<?php endif; ?>
            </td>
            <td><?= pe($pts) ?></td>
            <td style="font-size:7pt;color:#4b5563;"><?= pv($s['teacher_comment'] ?? null, '') ?></td>
        </tr>
        <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
</table>

<div class="section-title">Core Competencies</div>
<?php if (!empty($competencies)): ?>
<table class="competency-table">
    <thead><tr><th style="width:55%">Core Competency</th><th style="width:25%">Level</th><th style="width:20%">Points</th></tr></thead>
    <tbody>
    <?php foreach ($competencies as $c): ?>
    <?php $cg = strtoupper((string)($c['performance_level']??'')); ?>
    <?php $isK = isset($kjsea8Labels[$cg]); ?>
    <tr>
        <td><?= pe($c['competency_name'] ?? '') ?></td>
        <td><?php if ($isK): ?><span class="grade-badge-8" style="background:<?= $kjsea8Colors[$cg] ?? '#6b7280' ?>;"><?= pe($cg) ?></span>
        <?php elseif (isset($gradeLabels[$cg])): ?><span class="grade-badge" style="background:<?= $gradeColors[$cg] ?? '#6b7280' ?>;"><?= pe($cg) ?></span>
        <?php else: ?><?= pv($c['performance_level'] ?? null, '—') ?><?php endif; ?></td>
        <td><?= pv($c['points'] ?? null, '—') ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php else: ?>
<p style="text-align:center;color:#9ca3af;padding:2mm;">No competency data recorded.</p>
<?php endif; ?>

<?php if (!empty($values)): ?>
<div class="section-title">Core Values</div>
<div class="values-box">
    <?php foreach ($values as $v): ?>
    <span class="value-item"><?= pe($v['value_name'] ?? '') ?>: <span class="rating"><?= pe($v['rating'] ?? '') ?></span></span>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!empty($kjseaSummary)): ?>
<div class="section-title">KJSEA Placement Score Breakdown</div>
<div class="kjsea-summary">
    <table>
        <thead><tr><th>Component</th><th>Score</th><th>Weight</th><th>Contribution</th></tr></thead>
        <tbody>
            <tr><td>SBA (Grades 7–8)</td><td><?= pe((string)($kjseaSummary['sba_score']??'—')) ?></td><td>20%</td><td><?= pe((string)($kjseaSummary['sba_contribution']??'—')) ?></td></tr>
            <tr><td>KJSEA National Exam</td><td><?= pe((string)($kjseaSummary['exam_score']??'—')) ?></td><td>60%</td><td><?= pe((string)($kjseaSummary['exam_contribution']??'—')) ?></td></tr>
            <tr><td>KPSEA Carry-Over</td><td><?= pe((string)($kjseaSummary['kpsea_carry']??'—')) ?></td><td>20%</td><td><?= pe((string)($kjseaSummary['kpsea_contribution']??'—')) ?></td></tr>
            <tr style="background:#f0fdf4;font-weight:700;"><td>Final Placement Score</td><td colspan="3"><?= pe((string)($kjseaSummary['final_score']??'—')) ?></td></tr>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if (!empty($comments['next_term_focus'])): ?>
<div class="pathway-box" style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:1.5mm;padding:1.5mm 3mm;margin-bottom:2.5mm;font-size:7.5pt;color:#0369a1;">
    <strong>Next Term Focus Areas:</strong><br>
    <?php
    $focus = $comments['next_term_focus'];
    if (is_array($focus)) {
        echo implode('<br>', array_map(fn($f) => '• ' . pe($f), $focus));
    } else {
        echo nl2br(pe($focus));
    }
    ?>
</div>
<?php endif; ?>

<div class="section-title">Teacher's Comments</div>
<div class="comment-block">
    <strong>Class Teacher:</strong>
    <p><?= nl2br(pe($comments['teacher'] ?? 'A satisfactory report. Keep up the good work.')) ?></p>
</div>
<div class="comment-block">
    <strong>Headteacher:</strong>
    <p><?= nl2br(pe($comments['headteacher'] ?? '')) ?: 'Well done. Prepare diligently for the next level.' ?></p>
</div>

<div class="section-title">Signatures</div>
<table class="info-table" style="margin-bottom:2mm;">
    <tr>
        <td style="width:25%;text-align:center;">
            <div style="height:10mm;"></div>
            <div style="border-top:0.5pt solid #000;width:70%;margin:0 auto;"></div>
            <div style="font-size:7pt;font-weight:700;">Class Teacher</div>
            <div style="font-size:6.5pt;">Date: _______________</div>
        </td>
        <td style="width:25%;text-align:center;">
            <div style="height:10mm;"></div>
            <div style="border-top:0.5pt solid #000;width:70%;margin:0 auto;"></div>
            <div style="font-size:7pt;font-weight:700;">Headteacher</div>
            <div style="font-size:6.5pt;">Date: _______________</div>
        </td>
        <td style="width:25%;text-align:center;">
            <div style="height:10mm;"></div>
            <div style="border-top:0.5pt solid #000;width:70%;margin:0 auto;"></div>
            <div style="font-size:7pt;font-weight:700;">Dean of Studies</div>
            <div style="font-size:6.5pt;">Date: _______________</div>
        </td>
        <td style="width:25%;text-align:center;">
            <div style="height:10mm;"></div>
            <div style="border-top:0.5pt solid #000;width:70%;margin:0 auto;"></div>
            <div style="font-size:7pt;font-weight:700;">Parent / Guardian</div>
            <div style="font-size:6.5pt;">Date: _______________</div>
        </td>
    </tr>
</table>
