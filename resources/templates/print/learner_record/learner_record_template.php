<?php
/**
 * Learner academic record — Dompdf printable template.
 * Data: $record from App\API\Services\LearnerAcademicRecordService::forStudent().
 * One section per academic year in that year's class; each learning area
 * with Terms 1-3; approved results only.
 */
declare(strict_types=1);
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__) { http_response_code(404); exit; } // template only: never a direct request

if (!function_exists('lr_e')) {
    function lr_e(mixed $v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
}
if (!function_exists('lr_pct')) {
    function lr_pct($v): string { return $v === null ? '—' : rtrim(rtrim(number_format((float)$v, 1), '0'), '.') . '%'; }
}
if (!function_exists('lr_cell')) {
    function lr_cell(array $term): string {
        if (!empty($term['term_result'])) {
            return lr_pct($term['term_result']['overall_percentage']) . (!empty($term['term_result']['grade']) ? ' ' . lr_e($term['term_result']['grade']) : '');
        }
        $scored = array_values(array_filter(array_map(static fn ($s) => $s['percentage'], $term['summative'] ?? []), static fn ($v) => $v !== null));
        if ($scored) return 'Exam ' . lr_pct(array_sum($scored) / count($scored));
        if (!empty($term['formative_strands'])) return 'Formative only';
        if (($term['status'] ?? '') === 'awaiting_approval') return 'Awaiting approval';
        return 'Not assessed';
    }
}
$record = $record ?? ['student' => [], 'years' => []];
$student = $record['student'] ?? [];
$useSharedReportShell = $useSharedReportShell ?? false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
@page { size: A4 portrait; margin: 12mm 15mm; }
body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 8pt; color: #17202a; }
.lr-info { width: 100%; border-collapse: collapse; margin-bottom: 3mm; }
.lr-info td { padding: 1.2mm 2.5mm; border: 0.5pt solid #cbd5e1; }
.lr-info td.lbl { font-weight: 700; background: #f8fafc; width: 18%; color: #062653; }
.lr-year { font-size: 9pt; font-weight: 800; color: #062653; margin: 3mm 0 1.2mm; text-transform: uppercase; }
.lr-table { width: 100%; border-collapse: collapse; margin-bottom: 2mm; page-break-inside: auto; }
.lr-table th { background: #062653; color: #fff; padding: 1.3mm 2mm; text-align: left; font-size: 7.5pt; }
.lr-table td { padding: 1.1mm 2mm; border: 0.5pt solid #cbd5e1; vertical-align: top; }
.lr-table td.c, .lr-table th.c { text-align: center; }
.lr-detail { font-size: 7pt; color: #334155; }
.lr-note { font-size: 7.5pt; color: #475569; margin-top: 2mm; }
</style>
</head>
<body>
<table class="lr-info">
    <tr><td class="lbl">Learner</td><td><strong><?= lr_e($student['name'] ?? '') ?></strong></td><td class="lbl">Admission No</td><td><?= lr_e($student['admission_no'] ?? '') ?></td></tr>
    <tr><td class="lbl">Joined</td><td><?= !empty($student['joined']) ? lr_e($student['joined']['academic_year'] . ', ' . ($student['joined']['class_name'] ?? '')) : '—' ?></td><td class="lbl">Status</td><td><?= lr_e(ucfirst((string)($student['status'] ?? ''))) ?></td></tr>
</table>

<?php foreach (($record['years'] ?? []) as $year): ?>
    <div class="lr-year"><?= lr_e($year['academic_year']) ?> · <?= lr_e($year['class_name'] ?? '') ?> <?= lr_e($year['stream_name'] ?? '') ?> (<?= lr_e($year['enrollment_status'] ?? '') ?>)</div>
    <?php if (!empty($year['learning_areas'])): ?>
    <table class="lr-table">
        <thead><tr><th>Learning area</th><?php foreach ($year['terms'] as $t): ?><th class="c"><?= lr_e($t['term']) ?></th><?php endforeach; ?></tr></thead>
        <tbody>
        <?php foreach ($year['learning_areas'] as $area): ?>
            <tr>
                <td><strong><?= lr_e($area['name']) ?></strong>
                <?php
                $details = [];
                foreach ($area['terms'] as $t) {
                    foreach ($t['summative'] as $s) $details[] = lr_e($t['term'] . ': ' . $s['title'] . ' ' . ($s['score'] ?? '—') . '/' . ($s['out_of'] ?? '—'));
                    foreach ($t['formative_strands'] as $st) foreach ($st['sub_strands'] as $sub) foreach ($sub['evidence'] as $e)
                        $details[] = lr_e($t['term'] . ': ' . $st['strand'] . ' › ' . $sub['sub_strand'] . ' ' . lr_pct($e['percentage']));
                }
                if ($details): ?><div class="lr-detail"><?= implode('<br>', $details) ?></div><?php endif; ?>
                </td>
                <?php foreach ($area['terms'] as $t): ?><td class="c"><?= lr_cell($t) ?></td><?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
        <p class="lr-note">No learning areas were set for this class in this year.</p>
    <?php endif; ?>
    <?php if (!empty($year['results_awaiting_approval'])): ?>
        <p class="lr-note"><?= (int)$year['results_awaiting_approval'] ?> mark(s) awaiting approval are not shown.</p>
    <?php endif; ?>
<?php endforeach; ?>

<p class="lr-note">Results are shown in the class and year they were earned. Only approved results appear on this record.</p>
</body>
</html>
