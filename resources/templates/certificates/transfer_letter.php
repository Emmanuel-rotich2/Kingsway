<?php
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__) { http_response_code(404); exit; } // template only: never a direct request
/**
 * Learner transfer letter (portrait A4), addressed to the head teacher of the
 * receiving school. Carries what the Ministry of Education transfer process
 * needs: the learner's NEMIS UPI and assessment numbers, fee and discipline
 * clearance, the releasing head teacher's certification, and a box for the
 * Sub-County Director of Education's endorsement; the receiving school then
 * requests the learner on NEMIS.
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
$dest = $learner['destination'] ?? [];
$name = (string) ($facts['full_name'] ?? '');
$cleared = array_values(array_filter($learner['checks'] ?? [], static fn ($c) => ($c['status'] ?? '') === 'cleared'));
$entered = $facts['admission_date'] ? $d($facts['admission_date']) : ($facts['admission_year'] ?? '');
$assessments = $facts['national_assessments'] ?? [];
$financeNote = '';
foreach ($learner['checks'] ?? [] as $c) {
    if (($c['type'] ?? '') === 'finance') { $financeNote = trim((string) ($c['note'] ?? '')); break; }
}
$placeLine =implode(', ', array_filter([$dest['sub_county'] ?? '', !empty($dest['county']) ? $dest['county'] . ' County' : '']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Transfer Letter</title>
<style>
@page { size: A4 portrait; margin: 9mm 13mm; }
* { box-sizing: border-box; }
body { margin: 0; font-family: "DejaVu Sans", Arial, sans-serif; color: #1f2a24; font-size: 9pt; line-height: 1.25; }
p { margin: 1.6mm 0; }
.gov { width: 100%; border-collapse: collapse; }
.gov td { vertical-align: middle; }
.gov .arms img { height: 15mm; }
.gov .gtext { text-align: center; font-weight: 700; font-size: 9pt; letter-spacing: .5pt; }
.head { width: 100%; border-collapse: collapse; margin-top: 2mm; background: <?= $soft ?>; border-top: 1mm solid <?= $green ?>; border-bottom: 0.8mm solid <?= $gold ?>; }
.head td { padding: 1.5mm 3mm; vertical-align: middle; }
.head .logo { width: 20mm; }
.head .logo img { width: 17mm; }
.school-name { color: <?= $green ?>; font-size: 15pt; font-weight: 800; text-transform: uppercase; letter-spacing: .4pt; }
.meta { font-size: 8pt; color: #3c4a42; }
.motto { font-style: italic; color: <?= $green ?>; font-size: 8.2pt; }
.refs { width: 100%; border-collapse: collapse; margin-top: 2.5mm; font-size: 9pt; }
.to { margin-top: 2mm; }
.re { margin: 2mm 0 1.5mm; font-weight: 800; text-decoration: underline; color: <?= $green ?>; }
.details { width: 100%; border-collapse: collapse; margin: 1.5mm 0; font-size: 8.6pt; }
.details td { border: 0.25mm solid #b9cdbf; padding: .7mm 1.6mm; vertical-align: top; width: 30%; }
.details td.k { background: <?= $soft ?>; font-weight: 700; width: 20%; color: <?= $green ?>; }
.section { font-weight: 800; color: <?= $green ?>; margin-top: 2mm; font-size: 9.2pt; border-bottom: 0.3mm solid <?= $gold ?>; padding-bottom: .4mm; }
.report td { padding: .5mm 0; vertical-align: top; }
.report td.k { width: 30%; font-weight: 700; }
.chips span { display: inline-block; border: 0.25mm solid <?= $green ?>; color: <?= $green ?>; padding: 0 1.6mm; margin: 0 1mm 0 0; font-size: 8pt; }
.sign { width: 100%; border-collapse: collapse; margin-top: 0; }
.sign td { vertical-align: bottom; padding-right: 4mm; font-size: 8.6pt; }
.line { border-bottom: 0.3mm solid #1f2a24; height: 12mm; }
.cap { font-weight: 700; margin-top: .6mm; }
.stamp { border: 0.4mm dashed <?= $green ?>; border-radius: 50%; width: 25mm; height: 25mm; text-align: center; color: #6b7a71; font-size: 6.6pt; padding-top: 8.5mm; margin-left: auto; }
.official { margin-top: 2mm; page-break-inside: avoid; border: 0.4mm solid <?= $green ?>; }
.official .t { background: <?= $green ?>; color: #fff; font-weight: 800; font-size: 8.4pt; padding: 1mm 2mm; letter-spacing: .4pt; border-bottom: 0.7mm solid <?= $gold ?>; }
.official table { width: 100%; border-collapse: collapse; font-size: 8.4pt; }
.official td { padding: 1.3mm 2mm; vertical-align: bottom; }
.official .dots { border-bottom: 0.3mm dotted #445; }
/* Faint school logo behind the letter, centred on every page. */
.watermark { position: fixed; top: 80mm; left: 32mm; width: 120mm; text-align: center; z-index: 0; }
.watermark img { width: 120mm; opacity: .08; }
.foot { margin-top: 2.5mm; font-size: 7.4pt; color: #556; text-align: center; }
</style>
</head>
<body>
  <?php if (!empty($school['logo'])): ?><div class="watermark"><img src="<?= $e($school['logo']) ?>" alt=""></div><?php endif; ?>
  <table class="gov"><tr>
    <td style="width:20%;"></td>
    <td class="gtext"><?php if (!empty($coatOfArms)): ?><div class="arms"><img src="<?= $e($coatOfArms) ?>" alt="Coat of arms of Kenya"></div><?php endif; ?>REPUBLIC OF KENYA<br>MINISTRY OF EDUCATION</td>
    <td style="width:20%;"></td>
  </tr></table>

  <table class="head"><tr>
    <td class="logo"><?php if (!empty($school['logo'])): ?><img src="<?= $e($school['logo']) ?>" alt=""><?php endif; ?></td>
    <td>
      <div class="school-name"><?= $e($school['name']) ?></div>
      <?php if (!empty($school['motto'])): ?><div class="motto">&ldquo;<?= $e($school['motto']) ?>&rdquo;</div><?php endif; ?>
      <div class="meta"><?= $e($school['address']) ?><?= !empty($school['phone']) ? ' &middot; Tel: ' . $e($school['phone']) : '' ?></div>
      <div class="meta"><?= $e($school['email']) ?><?= !empty($school['code']) ? ' &middot; School code: ' . $e($school['code']) : '' ?></div>
    </td>
  </tr></table>

  <table class="refs"><tr>
    <td>Our Ref: <strong><?= $e($document['serial_no'] ?? '') ?></strong></td>
    <td style="text-align:right;">Date: <strong><?= $e($d($document['issued_at'] ?? date('Y-m-d'))) ?></strong></td>
  </tr></table>

  <div class="to">
    The Head Teacher,<br>
    <strong><?= $e($dest['school'] ?? '') ?></strong><?= !empty($dest['school_code']) ? ' (Code: ' . $e($dest['school_code']) . ')' : '' ?><br>
    <?= $e($placeLine) ?>
  </div>

  <p>Dear Sir/Madam,</p>
  <div class="re">RE: TRANSFER OF <?= $e(mb_strtoupper($name)) ?> &mdash; UPI <?= $e($facts['upi'] ?: 'N/A') ?></div>

  <p>This is to confirm that the above-named learner has been a bona fide learner of <?= $e($school['name']) ?>
  and has been released, at the request of the parent/guardian, to join your school. The learner has been cleared
  by every department of this school. The learner&rsquo;s particulars are as follows:</p>

  <table class="details">
    <tr><td class="k">Full name</td><td><?= $e($name) ?></td><td class="k">Admission no.</td><td><?= $e($facts['admission_no']) ?></td></tr>
    <tr><td class="k">UPI (NEMIS) no.</td><td><?= $e($facts['upi'] ?: '—') ?></td><td class="k">Assessment no.</td><td><?= $e($facts['assessment_number'] ?: '—') ?></td></tr>
    <tr><td class="k">Date of birth</td><td><?= $e($d($facts['dob'])) ?></td><td class="k">Gender</td><td><?= $e($facts['gender']) ?></td></tr>
    <tr><td class="k">Date of admission</td><td><?= $e($entered) ?><?= !empty($facts['admission_class']) ? ' (' . $e($facts['admission_class']) . ')' : '' ?></td><td class="k">Grade at leaving</td><td><?= $e(trim(($facts['leaving_class'] ?? '') . ' ' . ($facts['leaving_stream'] ?? ''))) ?></td></tr>
    <tr><td class="k">Date of leaving</td><td><?= $e($d($document['leaving_date'] ?? '')) ?></td><td class="k">Academic year</td><td><?= $e($facts['academic_year'] ?? '') ?></td></tr>
    <tr><td class="k">Reason</td><td colspan="3"><?= $e($dest['reason'] ?? '') ?></td></tr>
    <?php foreach ($assessments as $a): ?>
    <tr><td class="k"><?= $e($a['exam_type']) ?> (<?= $e($a['exam_year']) ?>)</td><td colspan="3">Assessment no. <?= $e($a['assessment_number']) ?><?= !empty($a['overall_level']) ? ' &middot; Overall level: ' . $e($a['overall_level']) : '' ?><?= $a['total_points'] !== null && $a['total_points'] !== '' ? ' &middot; Points: ' . $e($a['total_points']) : '' ?></td></tr>
    <?php endforeach; ?>
    <tr><td class="k">Clearance</td><td colspan="3"><span class="chips"><?php foreach ($cleared as $c): ?><span>&#10003; <?= $e($c['label']) ?></span><?php endforeach; ?></span>
      <div style="margin-top:.6mm;">Fees: <?= $e(($learner['balance'] ?? 0) == 0 ? 'fully settled' : ($financeNote !== '' ? $financeNote : 'settled')) ?>. No school property or library books are held by the learner.</div></td></tr>
  </table>

  <div class="section">HEAD TEACHER&rsquo;S REPORT</div>
  <table class="report" style="width:100%;border-collapse:collapse;">
    <tr><td class="k">Academic ability</td><td><?= nl2br($e($document['academic_report'] ?? '')) ?></td></tr>
    <tr><td class="k">Conduct</td><td><?= nl2br($e($document['conduct'] ?? '')) ?></td></tr>
    <tr><td class="k">Co-curricular activities</td><td><?= nl2br($e($document['co_curricular'] ?? '')) ?></td></tr>
  </table>

  <p style="margin-top:2mm;"><strong>NEMIS:</strong>
  <?php if (!empty($learner['nemis_released_at'])): ?>
    The learner was released from this school&rsquo;s NEMIS account on <?= $e($d($learner['nemis_released_at'])) ?>. Kindly admit the learner on NEMIS using the UPI above.
  <?php else: ?>
    Kindly request the learner on NEMIS using the UPI above; this school will release the learner on receipt of your request.
  <?php endif; ?>
  Any learning records you may need will be provided on request.</p>

  <table class="sign">
    <tr>
      <td colspan="2" style="vertical-align:top;"><p>We wish the learner success in your school.</p><p>Yours faithfully,</p></td>
      <td rowspan="2" style="width:28%;vertical-align:bottom;"><div class="stamp">OFFICIAL SCHOOL<br>STAMP</div></td>
    </tr>
    <tr>
      <td style="width:36%;"><div class="line"></div><div class="cap"><?= $e($headteacher['name'] ?: 'Name: ..........................................') ?></div><div><?= $e($headteacher['title'] ?? 'Head teacher') ?></div></td>
      <td style="width:36%;"><div class="line"></div><div class="cap"><?= $e($director['name'] ?: 'Name: ..........................................') ?></div><div>Director</div></td>
    </tr>
  </table>

  <div class="official">
    <div class="t">FOR OFFICIAL USE &mdash; SUB-COUNTY DIRECTOR OF EDUCATION</div>
    <table><tr>
      <td style="width:10%;">Name:</td><td class="dots" style="width:40%;"></td>
      <td style="width:12%;">Signature:</td><td class="dots"></td>
    </tr><tr>
      <td>Date:</td><td class="dots"></td>
      <td>Stamp:</td><td class="dots"></td>
    </tr></table>
  </div>

  <div class="foot">This letter is valid only with the school stamp and the signatures above. Quote reference <?= $e($document['serial_no'] ?? '') ?> in any correspondence.</div>
</body>
</html>
