<?php
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__) { http_response_code(404); exit; } // template only: never a direct request
/**
 * School Leaving Certificate (portrait A4), CBC edition of the Kenya Primary
 * School Leaving Certificate: Grades instead of Standards, the learner's
 * NEMIS UPI and assessment numbers, KPSEA/KJSEA details, signatures of the
 * head teacher and the director, and the official school stamp.
 *
 * Variables (PrintService::printLearnerExitDocument):
 *   $school, $coatOfArms, $colors, $headteacher, $director, $learner, $facts, $document
 *
 * Coat of arms image: Ashiriz, Wikimedia Commons, CC BY-SA 3.0
 * (images/kenya_coat_of_arms.ATTRIBUTION.md).
 */
$e = static fn ($v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$d = static function ($date): string {
    if (empty($date)) return '';
    $t = strtotime((string) $date);
    return $t ? date('j F Y', $t) : (string) $date;
};
$green = $colors['primary'] ?? '#0d4f2a';
$gold = $colors['accent'] ?? '#f9c80e';
$soft = $colors['soft'] ?? '#e8f3ec';
$outcome = (string) ($learner['outcome_key'] ?? '');
$completed = in_array($outcome, ['graduated', 'graduating'], true);
$entered = $facts['admission_date'] ? $d($facts['admission_date']) : ($facts['admission_year'] ?? '');
$leavingGrade = trim((string) ($facts['leaving_class'] ?? ''));
$gradeOnly = static fn (string $class): string => trim(preg_replace('/^grade\s*/i', '', $class));
$assessments = $facts['national_assessments'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>School Leaving Certificate</title>
<style>
@page { size: A4 portrait; margin: 9mm; }
* { box-sizing: border-box; }
body { margin: 0; font-family: "DejaVu Sans", Arial, sans-serif; color: #1f2a24; font-size: 9.6pt; }
/* Explicit content-box height (not height:100%) so Dompdf, WeasyPrint and
   browsers all size the frame the same and it stays on one A4 page. */
.frame { border: 2.4mm double <?= $green ?>; padding: 1.4mm; }
.inner { box-sizing: content-box; border: 0.6mm solid <?= $gold ?>; height: 254mm; padding: 5mm 8mm 4mm; position: relative; }
.center { text-align: center; }
.arms { height: 20mm; }
.republic { font-weight: 700; font-size: 11pt; letter-spacing: .6pt; margin-top: 1mm; }
.rule { width: 26mm; border-top: 0.6mm solid #1f2a24; margin: 1.2mm auto; }
.ministry { font-weight: 700; font-size: 10.2pt; letter-spacing: .4pt; }
.school { width: 100%; border-collapse: collapse; margin-top: 3mm; background: <?= $soft ?>; border-top: 0.8mm solid <?= $green ?>; border-bottom: 0.8mm solid <?= $gold ?>; }
.school td { padding: 1.5mm 3mm; vertical-align: middle; }
.school .logo { width: 20mm; }
.school .logo img { width: 17mm; }
.school-name { color: <?= $green ?>; font-size: 15pt; font-weight: 800; letter-spacing: .5pt; text-transform: uppercase; }
.school-meta { font-size: 8pt; color: #3c4a42; margin-top: .6mm; }
.motto { font-style: italic; color: <?= $green ?>; font-size: 8.4pt; }
.title { margin: 3mm 0 1mm; text-align: center; }
.title span { display: inline-block; background: <?= $green ?>; color: #fff; font-weight: 800; font-size: 14pt; letter-spacing: 1.4pt; padding: 1.6mm 7mm; border-bottom: 0.9mm solid <?= $gold ?>; }
.serial { width: 100%; border-collapse: collapse; font-size: 8.4pt; margin-bottom: 2mm; }
.serial td { padding: .5mm 0; }
.fields { width: 100%; border-collapse: collapse; margin-top: 1mm; }
.fields td { padding: 1.1mm 0 0; vertical-align: bottom; white-space: nowrap; font-weight: 700; font-size: 9pt; }
.fields td.v { border-bottom: 0.3mm dotted #445; font-weight: 700; color: <?= $green ?>; white-space: normal; font-family: "DejaVu Serif", Georgia, serif; font-size: 10pt; padding-left: 1.5mm; }
.block { margin-top: 2mm; }
.block .label { font-weight: 700; font-size: 9pt; }
.block .text { border-bottom: 0.3mm dotted #445; min-height: 7mm; padding: .5mm 1mm; font-family: "DejaVu Serif", Georgia, serif; color: <?= $green ?>; font-size: 9.6pt; line-height: 1.45; }
.assess { width: 100%; border-collapse: collapse; margin-top: 2mm; font-size: 8.6pt; }
.assess th { background: <?= $soft ?>; color: <?= $green ?>; text-align: left; padding: 1mm 1.5mm; border-bottom: 0.4mm solid <?= $green ?>; }
.assess td { padding: 1mm 1.5mm; border-bottom: 0.2mm solid #cfdcd3; }
.sign { width: 100%; border-collapse: collapse; margin-top: 1mm; }
.sign td { vertical-align: bottom; padding: 0 2mm; font-size: 8pt; white-space: nowrap; }
.sign td.ls { width: 22%; }
.sign td.hs { width: 31%; }
.sign td.ds { width: 29%; }
.sign td.st { width: 18%; padding: 0; }
.line { border-bottom: 0.3mm solid #1f2a24; height: 8mm; }
.cap { font-weight: 700; margin-top: .8mm; }
.name { color: #3c4a42; }
.issue { margin-top: 1.5mm; font-size: 8.2pt; }
.stamp { border: 0.4mm dashed <?= $green ?>; border-radius: 50%; width: 26mm; height: 26mm; margin-left: auto; text-align: center; color: #6b7a71; font-size: 6.6pt; padding-top: 9mm; }
/* Faint school logo behind the certificate, centred in the inner frame. */
.watermark { position: absolute; top: 72mm; left: 31mm; width: 120mm; text-align: center; z-index: 0; }
.watermark img { width: 120mm; opacity: .08; }
.foot { position: absolute; left: 8mm; right: 8mm; bottom: 3mm; text-align: center; font-size: 7.6pt; font-weight: 700; letter-spacing: .3pt; color: #3c4a42; border-top: 0.3mm solid <?= $gold ?>; padding-top: 1.2mm; }
</style>
</head>
<body>
<div class="frame"><div class="inner">
  <?php if (!empty($school['logo'])): ?><div class="watermark"><img src="<?= $e($school['logo']) ?>" alt=""></div><?php endif; ?>
  <div class="center">
    <?php if (!empty($coatOfArms)): ?><img class="arms" src="<?= $e($coatOfArms) ?>" alt="Coat of arms of Kenya"><?php endif; ?>
    <div class="republic">REPUBLIC OF KENYA</div>
    <div class="rule"></div>
    <div class="ministry">MINISTRY OF EDUCATION</div>
    <div class="rule"></div>
  </div>

  <table class="school"><tr>
    <td class="logo"><?php if (!empty($school['logo'])): ?><img src="<?= $e($school['logo']) ?>" alt=""><?php endif; ?></td>
    <td>
      <div class="school-name"><?= $e($school['name']) ?></div>
      <?php if (!empty($school['motto'])): ?><div class="motto">&ldquo;<?= $e($school['motto']) ?>&rdquo;</div><?php endif; ?>
      <div class="school-meta"><?= $e($school['address']) ?><?= !empty($school['phone']) ? ' &middot; Tel: ' . $e($school['phone']) : '' ?></div>
      <div class="school-meta"><?= !empty($school['email']) ? $e($school['email']) : '' ?><?= !empty($school['code']) ? ' &middot; School code: ' . $e($school['code']) : '' ?></div>
    </td>
  </tr></table>

  <div class="title"><span>SCHOOL LEAVING CERTIFICATE</span></div>
  <table class="serial"><tr>
    <td>Serial No: <strong><?= $e($document['serial_no'] ?? '') ?></strong></td>
    <td style="text-align:right;">Academic year: <strong><?= $e($facts['academic_year'] ?? '') ?></strong></td>
  </tr></table>

  <table class="fields">
    <tr><td>THIS IS TO CERTIFY THAT</td><td class="v"><?= $e(mb_strtoupper((string) $facts['full_name'])) ?></td><td>&nbsp;ADM. NO</td><td class="v" style="width:24mm;"><?= $e($facts['admission_no']) ?></td></tr>
  </table>
  <table class="fields">
    <tr><td>UPI (NEMIS) NO.</td><td class="v"><?= $e($facts['upi'] ?: '—') ?></td><td>&nbsp;ASSESSMENT NO.</td><td class="v"><?= $e($facts['assessment_number'] ?: '—') ?></td><td>&nbsp;GENDER</td><td class="v" style="width:18mm;"><?= $e($facts['gender']) ?></td></tr>
  </table>
  <table class="fields">
    <tr><td>DATE OF BIRTH</td><td class="v" style="width:42mm;"><?= $e($d($facts['dob'])) ?></td><td>&nbsp;ENTERED</td><td class="v"><?= $e(mb_strtoupper((string) $school['name'])) ?></td></tr>
  </table>
  <table class="fields">
    <tr><td>SCHOOL AND WAS ENROLLED ON</td><td class="v"><?= $e($entered) ?></td><td>&nbsp;IN GRADE</td><td class="v" style="width:26mm;"><?= $e($gradeOnly((string) $facts['admission_class']) ?: $facts['admission_class']) ?></td></tr>
  </table>
  <table class="fields">
    <?php if ($completed): ?>
    <tr><td>AND LEFT ON</td><td class="v"><?= $e($d($document['leaving_date'] ?? '')) ?></td><td>&nbsp;HAVING SATISFACTORILY COMPLETED THE</td></tr>
    <?php else: ?>
    <tr><td>AND LEFT ON</td><td class="v"><?= $e($d($document['leaving_date'] ?? '')) ?></td><td>&nbsp;WHILE IN GRADE</td><td class="v" style="width:26mm;"><?= $e($gradeOnly($leavingGrade) ?: $leavingGrade) ?></td></tr>
    <?php endif; ?>
  </table>
  <?php if ($completed): ?>
  <table class="fields">
    <tr><td>APPROVED COMPETENCY BASED CURRICULUM COURSE FOR GRADE</td><td class="v"><?= $e($gradeOnly($leavingGrade) ?: $leavingGrade) ?> (JUNIOR SCHOOL)</td></tr>
  </table>
  <?php endif; ?>

  <?php if ($assessments): ?>
  <table class="assess">
    <tr><th>National assessment</th><th>Year</th><th>Assessment no.</th><th>Overall level</th><th>Points</th></tr>
    <?php foreach ($assessments as $a): ?>
    <tr><td><?= $e($a['exam_type']) ?></td><td><?= $e($a['exam_year']) ?></td><td><?= $e($a['assessment_number']) ?></td><td><?= $e($a['overall_level']) ?></td><td><?= $e($a['total_points']) ?></td></tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>

  <div class="block"><div class="label">HEAD TEACHER&rsquo;S REPORT ON THE LEARNER&rsquo;S ACADEMIC ABILITY</div><div class="text"><?= nl2br($e($document['academic_report'] ?? '')) ?></div></div>
  <div class="block"><div class="label">LEARNER&rsquo;S CONDUCT</div><div class="text" style="min-height:8mm;"><?= nl2br($e($document['conduct'] ?? '')) ?></div></div>
  <div class="block"><div class="label">LEARNER&rsquo;S PARTICIPATION IN CO-CURRICULAR ACTIVITIES</div><div class="text" style="min-height:8mm;"><?= nl2br($e($document['co_curricular'] ?? '')) ?></div></div>

  <table class="sign"><tr>
    <td class="ls"><div class="line"></div><div class="cap">LEARNER&rsquo;S SIGNATURE</div></td>
    <td class="hs"><div class="line"></div><div class="cap"><?= $e(mb_strtoupper((string) ($headteacher['title'] ?? 'Head teacher'))) ?>&rsquo;S SIGNATURE</div><div class="name"><?= $e($headteacher['name'] ?: 'Name: ............................................') ?></div></td>
    <td class="ds"><div class="line"></div><div class="cap">DIRECTOR&rsquo;S SIGNATURE</div><div class="name"><?= $e($director['name'] ?: 'Name: ............................................') ?></div></td>
    <td class="st"><div class="stamp">OFFICIAL SCHOOL<br>RUBBER STAMP</div></td>
  </tr></table>

  <div class="issue">DATE OF ISSUE: <strong><?= $e($d($document['issued_at'] ?? date('Y-m-d'))) ?></strong>
    <span style="font-size:7.6pt;color:#3c4a42;"> &middot; Verify with the school quoting serial no. <?= $e($document['serial_no'] ?? '') ?>.</span></div>

  <div class="foot">THIS CERTIFICATE WAS ISSUED WITHOUT ANY ERASURE OR ALTERATION WHATSOEVER</div>
</div></div>
</body>
</html>
