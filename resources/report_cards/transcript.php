<?php
/**
 * Kingsway learner progress report (report book), all levels.
 *
 * Rendered by PrintService::printReportCard() into a PDF with Dompdf, so the
 * layout uses tables and inline SVG only (no flexbox, no icon fonts).
 * Variables: $student, $term, $scores (each with strands[]), $competencies,
 * $values, $attendance, $ranking, $result_policy, $comments, $grading_bands,
 * $level, $verification {code,url,qr}, $cba (optional), school variables.
 */
// Template only: never answer a direct web request.
if (!defined('KINGSWAY_TRANSCRIPT_RENDER')) {
    http_response_code(404);
    exit;
}
$e = static fn ($v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
$pct = static fn ($v): string => ($v === null || $v === '') ? '—' : rtrim(rtrim(number_format((float) $v, 1), '0'), '.') . '%';
$icon = static function (string $name, string $color = '#155C3B', int $size = 14): string {
    $paths = [
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
        'award' => '<circle cx="12" cy="9" r="6"/><path d="M8.5 14.5 7 22l5-3 5 3-1.5-7.5"/>',
        'chart' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'check' => '<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>',
        'shield' => '<path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6z"/><path d="m9 12 2 2 4-4"/>',
        'people' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.8-3.5 3.4-5 6.5-5s5.7 1.5 6.5 5"/><circle cx="17" cy="9" r="2.5"/><path d="M16 14.5c2.6.2 4.6 1.6 5.3 4.5"/>',
        'quote' => '<path d="M7 7h4v4c0 3-2 5-4 6M15 7h4v4c0 3-2 5-4 6"/>',
    ];
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="' . $color . '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . ($paths[$name] ?? '') . '</svg>';
    return '<img alt="" style="width:' . $size . 'px;height:' . $size . 'px;vertical-align:-2px" src="data:image/svg+xml;base64,' . base64_encode($svg) . '">';
};

$student = is_array($student ?? null) ? $student : [];
$term = is_array($term ?? null) ? $term : [];
$scores = is_array($scores ?? null) ? $scores : [];
$ranking = is_array($ranking ?? null) ? $ranking : [];
$attendance = is_array($attendance ?? null) ? $attendance : [];
$comments = is_array($comments ?? null) ? $comments : [];
$policy = is_array($result_policy ?? null) ? $result_policy : [];
$bands = is_array($grading_bands ?? null) ? $grading_bands : [];
$verification = is_array($verification ?? null) ? $verification : [];

$learnerName = trim(implode(' ', array_filter([$student['first_name'] ?? '', $student['middle_name'] ?? '', $student['last_name'] ?? ''])));
$classLabel = trim(($student['class_name'] ?? '') . ' ' . ($student['stream_name'] ?? ''));
$termLabel = trim(($term['name'] ?? '') . ' ' . ($term['year_code'] ?? ''));
$assessed = array_values(array_filter($scores, static fn ($s) => isset($s['overall_percentage']) && $s['overall_percentage'] !== null));
$average = $ranking['overall_percentage'] ?? (count($assessed) ? array_sum(array_map(static fn ($s) => (float) $s['overall_percentage'], $assessed)) / count($assessed) : null);
$overallBand = null;
if ($average !== null) {
    foreach ($bands as $band) {
        if ((float) $average >= (float) $band['min_percentage'] && (float) $average <= (float) $band['max_percentage']) { $overallBand = $band; break; }
    }
}
$showRank = (int) ($policy['publish_rank_to_guardians'] ?? 1) === 1 && !empty($ranking['class_position']);
$daysTotal = (int) ($attendance['total_days'] ?? 0);
$daysPresent = (int) ($attendance['days_present'] ?? 0) + (int) ($attendance['days_late'] ?? 0);
$photo = (string) ($student['photo_data_uri'] ?? '');
$initials = strtoupper(mb_substr((string) ($student['first_name'] ?? ''), 0, 1) . mb_substr((string) ($student['last_name'] ?? ''), 0, 1));
$levelTitle = [
    'PP' => 'Pre-Primary', 'LowerPrimary' => 'Lower Primary', 'UpperPrimary' => 'Upper Primary', 'JuniorSecondary' => 'Junior School',
][$level ?? ''] ?? 'Primary';
$fw = (float) ($policy['formative_weight'] ?? 40); $sw = (float) ($policy['summative_weight'] ?? 60);
$fwPct = ($fw + $sw) > 0 ? round($fw * 100 / ($fw + $sw)) : 40; $swPct = 100 - $fwPct;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= $e($learnerName) ?> — <?= $e($termLabel) ?> report</title>
<style>
  @page { size: A4 portrait; margin: 14mm 12mm 16mm 12mm; }
  * { box-sizing: border-box; }
  body { font-family: "DejaVu Sans", sans-serif; font-size: 9.2pt; color: #1B2620; line-height: 1.35; margin: 0; }
  h1, h2, h3 { font-family: "DejaVu Serif", serif; margin: 0; color: #12402B; }
  table { border-collapse: collapse; width: 100%; }
  .muted { color: #5E6B64; }
  .rule { height: 3px; background: #155C3B; margin: 6px 0 0; }
  .rule-gold { height: 2px; background: #C99A2E; margin: 1px 0 10px; }
  .head td { vertical-align: middle; }
  .school-name { font-size: 18pt; letter-spacing: .3px; }
  .doc-title { font-size: 11.5pt; color: #12402B; white-space: nowrap; }
  .panel { border: 1px solid #D5DED8; border-radius: 6px; }
  .learner td { padding: 3px 6px; vertical-align: top; }
  .label { font-size: 7.6pt; color: #5E6B64; text-transform: none; display: block; }
  .value { font-size: 9.6pt; font-weight: bold; }
  .photo { width: 78px; height: 92px; border-radius: 6px; border: 1px solid #D5DED8; object-fit: cover; }
  .photo-ph { width: 78px; height: 92px; border-radius: 6px; background: #E7F2EC; color: #155C3B; font-size: 22pt; font-family: "DejaVu Serif", serif; text-align: center; line-height: 92px; }
  .summary td { width: 25%; padding: 8px 10px; border-right: 1px solid #D5DED8; vertical-align: top; }
  .summary td:last-child { border-right: 0; }
  .big { font-size: 15pt; font-weight: bold; color: #12402B; font-family: "DejaVu Serif", serif; }
  .section { margin: 9px 0 4px; font-size: 10.5pt; }
  .results th { background: #12402B; color: #fff; font-size: 7.8pt; font-weight: bold; padding: 5px 6px; text-align: left; }
  .results th.num, .results td.num { text-align: center; }
  .results td { padding: 4px 6px; border-bottom: 1px solid #E3E9E4; vertical-align: top; }
  .results tr.area td { border-top: 1px solid #C9D6CE; }
  .area-name { font-weight: bold; }
  .strands { font-size: 7.6pt; color: #3F4D45; padding-top: 2px; }
  .chip { display: inline-block; border: 1px solid #C9D6CE; border-radius: 3px; padding: 0 4px; margin: 1px 3px 1px 0; white-space: nowrap; }
  .chip b { color: #12402B; }
  .level { display: inline-block; min-width: 26px; text-align: center; border-radius: 3px; padding: 1px 4px; font-weight: bold; font-size: 8.4pt; background: #E7F2EC; color: #12402B; }
  .na { color: #8A968F; font-style: italic; }
  .two td { width: 50%; vertical-align: top; padding-right: 8px; }
  .list td { padding: 3px 6px; border-bottom: 1px solid #EEF2EF; font-size: 8.6pt; }
  .comment { padding: 7px 9px; min-height: 34px; font-size: 8.8pt; }
  .key td { font-size: 7.4pt; padding: 2px 4px; border: 1px solid #E3E9E4; text-align: center; }
  .sign td { width: 33.3%; padding: 14px 10px 0; text-align: center; font-size: 8pt; color: #5E6B64; }
  .sign .line { border-top: 1px solid #1B2620; padding-top: 3px; color: #1B2620; font-weight: bold; }
  .verify td { vertical-align: middle; font-size: 7.6pt; color: #5E6B64; }
  .footer { position: fixed; bottom: -10mm; left: 0; right: 0; font-size: 7pt; color: #8A968F; }
  .avoid { page-break-inside: avoid; }
</style>
</head>
<body>

<div class="footer">
  <table><tr>
    <td><?= $e($schoolName ?? 'Kingsway Preparatory School') ?> · Learner progress report · <?= $e($termLabel) ?></td>
    <td style="text-align:right">Verification code <?= $e($verification['code'] ?? '—') ?></td>
  </tr></table>
</div>

<!-- Header -->
<table class="head">
  <tr>
    <td style="width:70px"><?php if (!empty($schoolLogo)): ?><img src="<?= $e($schoolLogo) ?>" alt="" style="width:62px;height:62px"><?php endif; ?></td>
    <td>
      <h1 class="school-name"><?= $e($schoolName ?? 'Kingsway Preparatory School') ?></h1>
      <?php if (!empty($schoolMotto)): ?><div style="color:#8A6A1F;font-style:italic;font-size:8.6pt">“<?= $e($schoolMotto) ?>”</div><?php endif; ?>
      <div class="muted" style="font-size:7.8pt"><?= $e($schoolAddress ?? '') ?><?= !empty($schoolPhone) ? ' · ' . $e($schoolPhone) : '' ?><?= !empty($schoolEmail) ? ' · ' . $e($schoolEmail) : '' ?></div>
    </td>
    <td style="width:215px;text-align:right">
      <div class="doc-title">Learner Progress Report</div>
      <div class="muted" style="font-size:8.2pt"><?= $e($levelTitle) ?> · CBC</div>
      <div style="font-size:9pt;font-weight:bold;margin-top:2px"><?= $e($termLabel) ?></div>
    </td>
  </tr>
</table>
<div class="rule"></div><div class="rule-gold"></div>

<!-- Learner -->
<table class="panel learner" style="margin-bottom:8px">
  <tr>
    <td style="width:92px;padding:8px">
      <?php if ($photo !== ''): ?><img class="photo" src="<?= $e($photo) ?>" alt=""><?php else: ?><div class="photo-ph"><?= $e($initials ?: '–') ?></div><?php endif; ?>
    </td>
    <td style="padding:8px 4px">
      <table>
        <tr>
          <td colspan="3"><span class="label">Learner</span><span class="value" style="font-size:12pt"><?= $e($learnerName) ?></span></td>
        </tr>
        <tr>
          <td><span class="label">Admission number</span><span class="value"><?= $e($student['admission_no'] ?? '—') ?></span></td>
          <td><span class="label">Assessment number (KNEC)</span><span class="value"><?= $e(($student['assessment_number'] ?? '') ?: '—') ?></span></td>
          <td><span class="label">Grade and stream</span><span class="value"><?= $e($classLabel ?: '—') ?></span></td>
        </tr>
        <tr>
          <td><span class="label">Gender</span><span class="value"><?= $e(ucfirst((string) ($student['gender'] ?? '')) ?: '—') ?></span></td>
          <td><span class="label">Term dates</span><span class="value"><?= !empty($term['opening_date']) ? $e(date('j M', strtotime((string) $term['opening_date']))) . ' – ' . $e(date('j M Y', strtotime((string) $term['closing_date']))) : '—' ?></span></td>
          <td><span class="label">UPI (NEMIS)</span><span class="value"><?= $e(($student['nemis_number'] ?? '') ?: '—') ?></span></td>
        </tr>
      </table>
    </td>
  </tr>
</table>

<!-- Term summary -->
<table class="panel summary avoid">
  <tr>
    <td><?= $icon('chart') ?> <span class="label" style="display:inline">Term average</span><div class="big"><?= $pct($average) ?></div>
      <div class="muted" style="font-size:7.4pt"><?= count($assessed) ?> of <?= count($scores) ?> learning areas assessed</div></td>
    <td><?= $icon('award') ?> <span class="label" style="display:inline">Overall level</span>
      <div class="big"><?= $e($overallBand['band_code'] ?? '—') ?></div>
      <div class="muted" style="font-size:7.4pt"><?= $e($overallBand['band_name'] ?? 'Not yet assessed') ?></div></td>
    <td><?= $icon('people') ?> <span class="label" style="display:inline">Position in stream</span>
      <div class="big"><?= $showRank ? $e($ranking['class_position']) : '—' ?></div>
      <div class="muted" style="font-size:7.4pt"><?= $showRank ? 'out of ' . $e($ranking['class_population'] ?? '—') . (!empty($ranking['cohort_position']) ? ' · grade ' . $e($ranking['cohort_position']) . ' of ' . $e($ranking['cohort_population']) : '') : 'Shown once all learning areas are assessed' ?></div></td>
    <td><?= $icon('calendar') ?> <span class="label" style="display:inline">Attendance</span>
      <div class="big"><?= $daysTotal ? $e($daysPresent) . '/' . $e($daysTotal) : '—' ?></div>
      <div class="muted" style="font-size:7.4pt"><?= $daysTotal ? 'days present' . ((int) ($attendance['days_absent'] ?? 0) ? ' · ' . (int) $attendance['days_absent'] . ' absent' : '') : 'No registers recorded' ?></div></td>
  </tr>
</table>

<!-- Learning areas -->
<h2 class="section"><?= $icon('check') ?> Learning areas</h2>
<table class="results">
  <thead>
    <tr>
      <th style="width:34%">Learning area and strands</th>
      <th class="num">Formative<br><span style="font-weight:normal">classroom · <?= $fwPct ?>%</span></th>
      <th class="num">Summative<br><span style="font-weight:normal">exams · <?= $swPct ?>%</span></th>
      <th class="num">Term score</th>
      <th class="num">Level</th>
      <th class="num">Points</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($scores as $s): $hasScore = isset($s['overall_percentage']) && $s['overall_percentage'] !== null; ?>
    <tr class="area avoid">
      <td>
        <div class="area-name"><?= $e($s['subject_name'] ?? '') ?></div>
        <?php if (!empty($s['strands'])): ?>
          <div class="strands"><?php foreach ($s['strands'] as $st): ?><span class="chip"><?= $e($st['strand_name'] ?: 'Strand') ?> <b><?= $e($st['grade_code'] ?? '—') ?></b></span><?php endforeach; ?></div>
        <?php endif; ?>
      </td>
      <td class="num"><?= $pct($s['formative_percentage'] ?? null) ?><?= !empty($s['formative_grade']) ? '<div class="muted" style="font-size:7.4pt">' . $e($s['formative_grade']) . '</div>' : '' ?></td>
      <td class="num"><?= $pct($s['summative_percentage'] ?? null) ?><?= !empty($s['summative_grade']) ? '<div class="muted" style="font-size:7.4pt">' . $e($s['summative_grade']) . '</div>' : '' ?></td>
      <td class="num"><?= $hasScore ? '<b>' . $pct($s['overall_percentage']) . '</b>' : '<span class="na">Not assessed</span>' ?></td>
      <td class="num"><?= !empty($s['overall_grade']) ? '<span class="level">' . $e($s['overall_grade']) . '</span>' : '—' ?></td>
      <td class="num"><?= isset($s['overall_points']) && $s['overall_points'] !== null ? $e(rtrim(rtrim(number_format((float) $s['overall_points'], 1), '0'), '.')) : '—' ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$scores): ?>
    <tr><td colspan="6" class="na" style="text-align:center;padding:12px">No learning areas are timetabled for this stream yet.</td></tr>
  <?php endif; ?>
  </tbody>
</table>

<?php if (!empty($cba) && is_array($cba)): ?>
<h2 class="section"><?= $icon('shield') ?> KNEC assessment (<?= $e($cba['code'] ?? 'CBA') ?>)</h2>
<table class="panel list avoid">
  <?php foreach (($cba['components'] ?? []) as $c): ?>
    <tr><td><?= $e($c['label'] ?? '') ?></td><td style="text-align:right"><?= $pct($c['percentage'] ?? null) ?> <span class="muted">weight <?= $e($c['weight'] ?? '') ?>%</span></td></tr>
  <?php endforeach; ?>
  <tr><td><b>Composite</b></td><td style="text-align:right"><b><?= $pct($cba['composite'] ?? null) ?></b> <?= !empty($cba['status']) && $cba['status'] !== 'complete' ? '<span class="na">(awaiting ' . $e($cba['status']) . ')</span>' : '' ?></td></tr>
</table>
<?php endif; ?>

<!-- Competencies and values -->
<table class="two" style="margin-top:10px">
  <tr>
    <td>
      <h2 class="section"><?= $icon('award') ?> Core competencies</h2>
      <table class="panel list">
        <?php foreach (($competencies ?? []) as $c): ?>
          <tr><td><?= $e($c['competency_name'] ?? '') ?></td><td style="text-align:right"><span class="level"><?= $e($c['level_code'] ?? '—') ?></span></td></tr>
        <?php endforeach; ?>
        <?php if (empty($competencies)): ?><tr><td class="na">Not recorded this term.</td></tr><?php endif; ?>
      </table>
    </td>
    <td>
      <h2 class="section"><?= $icon('shield') ?> Values</h2>
      <table class="panel list">
        <?php foreach (($values ?? []) as $v): ?>
          <tr><td><?= $e($v['value_name'] ?? '') ?></td><td style="text-align:right;font-size:7.6pt" class="muted"><?= $e(mb_substr((string) ($v['evidence'] ?? ''), 0, 60)) ?></td></tr>
        <?php endforeach; ?>
        <?php if (empty($values)): ?><tr><td class="na">Not recorded this term.</td></tr><?php endif; ?>
      </table>
    </td>
  </tr>
</table>

<!-- Comments -->
<div>
<h2 class="section"><?= $icon('quote') ?> Comments</h2>
<table class="two">
  <tr>
    <td><div class="panel comment"><span class="label">Class teacher</span><?= $e($comments['class_teacher'] ?? $comments['teacher'] ?? '') ?></div></td>
    <td><div class="panel comment"><span class="label">Headteacher</span><?= $e($comments['headteacher'] ?? $comments['principal'] ?? '') ?></div></td>
  </tr>
</table>
</div>

<!-- Grading key: the 8 levels, grouped into the 4 CBC levels they belong to -->
<?php if ($bands):
  // Whole-number ranges as published (75–89, not 75–89.99 shown as 75–90).
  $lo = static fn ($v): string => (string) (int) ceil((float) $v);
  $hi = static fn ($v): string => (string) (int) floor((float) $v);
  $groups = [];
  foreach ($bands as $b) {
    $g = \App\API\Services\GradingScopeService::level4($b['band_code'] ?? '') ?? (string) ($b['band_code'] ?? '');
    $last = array_key_last($groups);
    if ($last !== null && $groups[$last]['code'] === $g) { $groups[$last]['span']++; }
    else { $groups[] = ['code' => $g, 'span' => 1]; }
  }
  $grouped = count($groups) < count($bands);
?>
<?php if (!empty($grading_system_name) || !empty($grading_note)): ?>
<div class="muted" style="margin-top:10px;font-size:7.6pt">
  <?php if (!empty($grading_system_name)): ?>Levels: <b><?= $e($grading_system_name) ?></b>.<?php endif; ?>
  <?php if (!empty($grading_note)): ?> <?= $e($grading_note) ?><?php endif; ?>
</div>
<?php endif; ?>
<table class="key avoid" style="margin-top:4px">
  <?php if ($grouped): ?>
  <tr>
    <td style="text-align:left;border:0;padding-left:0" class="muted">4 levels</td>
    <?php foreach ($groups as $g): ?>
      <td colspan="<?= (int) $g['span'] ?>"><b><?= $e($g['code']) ?></b> <?= $e(\App\API\Services\GradingScopeService::LEVEL4_NAMES[$g['code']] ?? '') ?></td>
    <?php endforeach; ?>
  </tr>
  <?php endif; ?>
  <tr>
    <td style="text-align:left;border:0;padding-left:0" class="muted"><?= $grouped ? '8 levels' : 'Levels' ?></td>
    <?php foreach ($bands as $b): ?>
      <td><b><?= $e($b['band_code']) ?></b><?= $grouped ? '' : ' ' . $e($b['band_name']) ?><br><span class="muted"><?= $e($lo($b['min_percentage'])) ?>–<?= $e($hi($b['max_percentage'])) ?>%</span></td>
    <?php endforeach; ?>
  </tr>
</table>
<?php endif; ?>

<!-- Signatures and verification -->
<table class="sign avoid">
  <tr>
    <td><div class="line">Class teacher</div>Signature and date</td>
    <td><div class="line">Headteacher</div>Signature, date and school stamp</td>
    <td><div class="line">Parent or guardian</div>Signature and date</td>
  </tr>
</table>

<table class="verify avoid" style="margin-top:12px;border-top:1px solid #E3E9E4">
  <tr>
    <td style="width:76px;padding-top:6px"><?php if (!empty($verification['qr'])): ?><img src="<?= $e($verification['qr']) ?>" alt="" style="width:68px;height:68px"><?php endif; ?></td>
    <td style="padding-top:6px">
      <b style="color:#12402B">Check that this report is genuine.</b> Scan the code, or visit
      <?= $e(preg_replace('#\?code=.*$#', '', (string) ($verification['url'] ?? ''))) ?> and enter
      <b style="color:#1B2620;letter-spacing:1px"><?= $e($verification['code'] ?? '—') ?></b>.<br>
      Formative scores come from classroom assessment of each strand and sub-strand; summative scores from school examinations.
      The term score combines them <?= $fwPct ?>% / <?= $swPct ?>%. Issued <?= $e(date('j F Y')) ?>.
    </td>
  </tr>
</table>

</body>
</html>
