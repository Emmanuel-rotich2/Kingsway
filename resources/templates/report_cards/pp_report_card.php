<?php
/**
 * CBC Pre-Primary (PP1 / PP2) Report Card — Body-only template
 *
 * Used via PrintService::printReportCard() with shared header/footer.
 * Variables (merged from buildTemplateVariables + data):
 *   - $student, $term, $scores, $competencies, $values, $attendance, $comments
 *   - $schoolName, $schoolMotto, $schoolLogo, $schoolAddress, $schoolPhone, $schoolEmail
 *   - $generatedBy, $generatedAt, $reportCode, $title, $subtitle
 */
declare(strict_types=1);
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__) { http_response_code(404); exit; } // template only: never a direct request

if (!function_exists('pe')) { function pe(mixed $v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }}
if (!function_exists('pv')) { function pv(mixed $v, string $f=''): string { return trim((string)($v ?? '')) !== '' ? pe($v) : $f; }}

$student     = $student ?? [];
$term        = $term ?? [];
$scores      = $scores ?? [];
$competencies = $competencies ?? [];
$values      = $values ?? [];
$attendance  = $attendance ?? [];
$ranking     = $ranking ?? [];
$comments    = $comments ?? [];
$studentName = trim(($student['first_name']??'') . ' ' . ($student['last_name']??'')) ?: 'Student';

$gradeLabels = ['BE'=>'Below Expectation','AE'=>'Approaching Expectation','ME'=>'Meeting Expectation','EE'=>'Exceeding Expectations'];
$gradeColors = ['BE'=>'#ef4444','AE'=>'#f59e0b','ME'=>'#3b82f6','EE'=>'#10b981'];
?>
<style>
.info-table { width:100%; border-collapse:collapse; margin-bottom:3mm; font-size:9pt; }
.info-table td { padding:1.2mm 3mm; border:1px solid #e5e7eb; }
.info-table td:first-child { font-weight:700; background:#f9fafb; width:20%; }

.attendance-box { background:#f0fdf4; border:1px solid #bbf7d0; border-radius:1.5mm; padding:2mm 4mm; margin-bottom:3mm; }
.attendance-box table { width:100%; border-collapse:collapse; }
.attendance-box td { padding:1mm 3mm; text-align:center; font-size:9pt; }
.attendance-box .label { font-weight:700; color:#0f5b3b; }
.attendance-box .value { font-size:11pt; font-weight:800; }

.section-title { background:#e8f5e9; color:#0f5b3b; padding:2mm 4mm; font-size:10pt; font-weight:700; border-left:3mm solid #0f5b3b; margin:4mm 0 3mm; }

.subject-table { width:100%; border-collapse:collapse; margin-bottom:3mm; font-size:8.5pt; }
.subject-table th { background:#0f5b3b; color:#fff; padding:1.8mm 2mm; text-align:left; font-weight:700; font-size:8pt; }
.subject-table td { padding:1.5mm 2mm; border:1px solid #e5e7eb; }
.subject-table tr:nth-child(even) { background:#f9fafb; }
.grade-badge { display:inline-block; padding:0.5mm 2.5mm; border-radius:1mm; font-weight:700; font-size:8pt; color:#fff; }

.competency-table { width:100%; border-collapse:collapse; margin-bottom:3mm; font-size:8.5pt; }
.competency-table th { background:#f3f4f6; padding:1.5mm 2mm; text-align:left; font-weight:700; border:1px solid #e5e7eb; }
.competency-table td { padding:1.5mm 2mm; border:1px solid #e5e7eb; }

.values-box { margin-bottom:3mm; }
.value-item { display:inline-block; background:#fff3cd; padding:1mm 3mm; border-radius:1mm; margin:0.5mm; font-size:8.5pt; }
.value-item .rating { font-weight:700; color:#92400e; }

.comment-block { background:#f9fafb; border:1px solid #e5e7eb; border-radius:1.5mm; padding:3mm; margin-bottom:3mm; }
.comment-block strong { color:#0f5b3b; }
.comment-block p { margin-top:1mm; font-size:9pt; color:#374151; }

.note-box { background:#f0f9ff; border:1px solid #bae6fd; border-radius:1.5mm; padding:2mm 4mm; margin-bottom:3mm; font-size:8pt; color:#0369a1; }
</style>

<table class="info-table">
    <tr>
        <td>Learner Name</td>
        <td><strong><?= pe($studentName) ?></strong></td>
        <td>Admission No</td>
        <td><?= pv($student['admission_no'] ?? null, '—') ?></td>
    </tr>
    <tr>
        <td>Class / Stream</td>
        <td><?= pv($student['class_name'] ?? null, '—') ?> <?= $student['stream_name'] ?? '' ? '(' . pe($student['stream_name']) . ')' : '' ?></td>
        <td>Gender</td>
        <td><?= pv($student['gender'] ?? null, '—') ?></td>
    </tr>
    <tr>
        <td>Term</td>
        <td><?= pv($term['name'] ?? '') ?: 'Term ' . ($term['term_number']??'1') . ' ' . ($term['year']??date('Y')) ?></td>
        <td>Report Date</td>
        <td><?= date('d F Y') ?></td>
    </tr>
    <?php if (!empty($ranking['class_position'])): ?>
    <tr><td>Class Position</td><td><?= (int)$ranking['class_position'] ?> of <?= (int)($ranking['class_population']??0) ?></td><td>Overall</td><td><?= pv($ranking['overall_percentage'] ?? null, '—') ?>%</td></tr>
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

<div class="note-box">
    <strong>Assessment Guide:</strong> Pre-Primary uses activity-based learning with no formal examinations.
    Ratings: <strong>EE</strong> (Exceeding), <strong>ME</strong> (Meeting),
    <strong>AE</strong> (Approaching), <strong>BE</strong> (Below).
</div>

<div class="section-title">Learning Areas — Strand-Level Assessment</div>
<table class="subject-table">
    <thead><tr><th style="width:26%">Learning Area</th><th>Formative</th><th>Summative</th><th style="width:18%">Grade</th><th style="width:26%">Remarks</th></tr></thead>
    <tbody>
    <?php if (empty($scores)): ?>
        <tr><td colspan="5" style="text-align:center;color:#9ca3af;">No assessment data available.</td></tr>
    <?php else: ?>
        <?php foreach ($scores as $s): ?>
        <?php $g = strtoupper((string)($s['overall_grade']??'')); $gb = preg_replace('/[0-9]+$/', '', $g); ?>
        <tr>
            <td><strong><?= pe($s['subject_name'] ?? '') ?></strong></td>
            <td><?= pv($s['formative_total'] ?? null, '—') ?>/<?= pv($s['formative_max'] ?? null, '—') ?></td>
            <td><?= pv($s['summative_total'] ?? null, '—') ?>/<?= pv($s['summative_max'] ?? null, '—') ?></td>
            <td><?php if (isset($gradeLabels[$gb])): ?><span class="grade-badge" style="background:<?= $gradeColors[$gb] ?? '#6b7280' ?>;"><?= pe($g) ?></span><?php else: ?>—<?php endif; ?></td>
            <td><?= pv($gradeLabels[$gb] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
</table>

<div class="section-title">Core Competencies</div>
<?php if (!empty($competencies)): ?>
<table class="competency-table">
    <thead><tr><th style="width:50%">Core Competency</th><th style="width:25%">Level</th><th style="width:25%">Points</th></tr></thead>
    <tbody>
    <?php foreach ($competencies as $c): ?>
    <?php $cg = strtoupper((string)($c['performance_level']??'')); ?>
    <tr>
        <td><?= pe($c['competency_name'] ?? '') ?></td>
        <td><?php if (isset($gradeLabels[$cg])): ?><span class="grade-badge" style="background:<?= $gradeColors[$cg] ?? '#6b7280' ?>;"><?= pe($cg) ?></span><?php else: ?><?= pv($c['performance_level'] ?? null, '—') ?><?php endif; ?></td>
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

<?php if (!empty($comments['next_term_focus'])): ?>
<div class="note-box">
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
    <p><?= nl2br(pe($comments['teacher'] ?? 'No comment provided.')) ?></p>
</div>
<div class="comment-block">
    <strong>Headteacher:</strong>
    <p><?= nl2br(pe($comments['headteacher'] ?? '')) ?: 'A good report. Keep up the effort.' ?></p>
</div>

<div class="section-title">Signatures</div>
<table class="info-table" style="margin-bottom:2mm;">
    <tr>
        <td style="width:33%;text-align:center;">
            <div style="height:12mm;"></div>
            <div style="border-top:0.5pt solid #000;width:70%;margin:0 auto;"></div>
            <div style="font-size:7.5pt;font-weight:700;">Class Teacher</div>
            <div style="font-size:7pt;">Date: _______________</div>
        </td>
        <td style="width:33%;text-align:center;">
            <div style="height:12mm;"></div>
            <div style="border-top:0.5pt solid #000;width:70%;margin:0 auto;"></div>
            <div style="font-size:7.5pt;font-weight:700;">Headteacher</div>
            <div style="font-size:7pt;">Date: _______________</div>
        </td>
        <td style="width:34%;text-align:center;">
            <div style="height:12mm;"></div>
            <div style="border-top:0.5pt solid #000;width:70%;margin:0 auto;"></div>
            <div style="font-size:7.5pt;font-weight:700;">Parent / Guardian</div>
            <div style="font-size:7pt;">Date: _______________</div>
        </td>
    </tr>
</table>
