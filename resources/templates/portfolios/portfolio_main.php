<?php
/**
* Kingsway Preparatory School
* CBC Student Portfolio — Body-only template
*
* Used by PrintService::printPortfolio() via buildReportDocument().
*
* Structure:
*   1. Cover Page (no header/footer, no page number)
*   2. Table of Contents
*   3. Content Pages (running header + footer)
*
* Expected variables:
*   - $student           ['first_name','last_name','admission_no','class_name','stream_name','gender','photo_url']
*   - $portfolio         ['title','academic_year','status','description','theme']
*   - $allArtifacts      [array of artifacts grouped by competency → year]
*   - $competencySummary [array]
*   - $valuesSummary     [array]
*   - $teacherFeedback   string (aggregated)
*   - $yearRange         string
*   - $totalArtifacts    int
*   - $schoolName, $schoolMotto, $schoolLogo, $schoolAddress, $schoolPhone, $schoolEmail, $schoolWebsite
*   - $reportCode, $generatedAt
 */
declare(strict_types=1);
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__) { http_response_code(404); exit; } // template only: never a direct request

if (!function_exists('pe')) { function pe(mixed $v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); } }
if (!function_exists('pv')) { function pv(mixed $v, string $f=''): string { return trim((string)($v ?? '')) !== '' ? pe($v) : $f; } }

$schoolName   = pv($schoolName ?? null, 'Kingsway Preparatory School');
$schoolMotto  = pv($schoolMotto ?? null, 'In God We Soar');
$schoolLogo   = $schoolLogo ?? '';
$schoolAddress = pv($schoolAddress ?? null);
$schoolPhone  = pv($schoolPhone ?? null);
$schoolEmail  = pv($schoolEmail ?? null);
$schoolWebsite = pv($schoolWebsite ?? null);
$student      = $student ?? [];
$portfolio    = $portfolio ?? [];
$allArtifacts = $allArtifacts ?? [];
$compSummary  = $competencySummary ?? [];
$valsSummary  = $valuesSummary ?? [];
$teacherFB    = (string)($teacherFeedback ?? '');
$yrRange      = pv($yearRange ?? null, date('Y'));
$totalArts    = (int)($totalArtifacts ?? 0);
$studentName  = $studentName ?? trim(($student['first_name']??'') . ' ' . ($student['last_name']??'')) ?: 'Student';
$studentAdm   = pv($student['admission_no'] ?? null, '-');
$studentClass = pv($student['class_name'] ?? null, '-') . ($student['stream_name'] ?? '' ? ' (' . pe($student['stream_name']) . ')' : '');
$studentGender = pv($student['gender'] ?? null, '-');

/* ----- Group artifacts by competency → year for TOC and content ---- */
$grouped = [];
foreach ($allArtifacts as $a) {
    $comp = $a['competency_name'] ?? 'Uncategorized';
    $year = $a['academic_year'] ?? date('Y');
    $grouped[$comp][$year][] = $a;
}
$compOrder = array_keys($grouped);
if (isset($grouped['Uncategorized'])) {
    unset($grouped['Uncategorized']);
    $grouped['Uncategorized'] = [];
}

/* Collect teacher feedback from individual artifacts */
$fbLines = [];
foreach ($allArtifacts as $a) {
    if (!empty($a['teacher_feedback'])) {
        $fbLines[] = $a['teacher_feedback'];
    }
}
$allFeedback = $teacherFB ?: implode("\n---\n", $fbLines);
$competencyCount = count($compOrder);
$valuesCount = count($valsSummary);
$yearCount = count(array_unique(array_column($allArtifacts, 'academic_year')));
?>
<!-- ═══════════════════════════════════════════════════════════════════════
COVER PAGE (no page number, no running header/footer)
═══════════════════════════════════════════════════════════════════════ -->
<div class="cover-page">
    <?php if ($schoolLogo): ?>
    <img src="<?= pe($schoolLogo) ?>" alt="School Logo" class="school-logo">
    <?php endif; ?>

    <h1><?= pe($schoolName) ?></h1>
    <p class="subtitle">"<?= pe($schoolMotto) ?>"</p>

    <div class="cover-divider">
        <?= pe($schoolAddress) ?><br>
        <?= pe($schoolPhone) ?> &mdash; <?= pe($schoolEmail) ?>
    </div>

    <h2 class="cover-title">CBC STUDENT PORTFOLIO</h2>
    <p class="cover-desc">Competency-Based Curriculum &mdash; Evidence of Learning</p>

    <div class="student-name"><?= pe($studentName) ?></div>

    <table class="meta-table">
        <tr><td>Admission Number</td><td><?= pe($studentAdm) ?></td></tr>
        <tr><td>Class / Stream</td><td><?= pe($studentClass) ?></td></tr>
        <tr><td>Gender</td><td><?= pe($studentGender) ?></td></tr>
        <tr><td>Portfolio Theme</td><td><?= pv($portfolio['title'] ?? null, 'My Learning Journey') ?></td></tr>
        <tr><td>Status</td><td><?= pv($portfolio['status'] ?? null, '-') ?></td></tr>
        <tr><td>Total Artifacts</td><td><?= $totalArts ?></td></tr>
    </table>

    <p class="year-range">Academic Years: <?= pe($yrRange) ?></p>

    <div class="gold-strip"></div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     TABLE OF CONTENTS
═══════════════════════════════════════════════════════════════════════════ -->
<div class="toc-page">
  <h2 class="toc-title">Table of Contents</h2>
  <table class="toc-table">
      <tr><td class="toc-number">1</td><td class="toc-title-cell">Competency Coverage Summary</td><td class="toc-page-num">3</td></tr>
      <tr><td class="toc-number">2</td><td class="toc-title-cell">Learning Evidence by Competency</td><td class="toc-page-num">4</td></tr>
      <?php foreach ($compOrder as $index => $compName): ?>
      <tr><td class="toc-number">2.<?= $index +1 ?></td><td class="toc-title-cell" style="padding-left:6mm;"><?= pe(($compName)) ?></td><td class="toc-page">5</td></tr>
      <?php endforeach; ?>
      <tr><td class="toc-number">3</td><td class="toc-title">Core Values Demonstrated</td><td class="toc-page">-</td></tr>
      <tr><td class="toc-number">4</td><td class="toc-title">Teacher’s Overall Assessment</td><td class="toc-page">-</td></tr>
      <tr><td class="toc-number">5</td><td class="toc-title">Signatures &amp; Validation</td><td class="toc-page">-</td></tr>
  </table>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     SECTIONS (body content with running header/footer on each page)
═══════════════════════════════════════════════════════════════════════════ -->

<!-- ── section 1: KPI Strip ── -->
<div class="kpi-strip">
    <div class="kpi-box">
        <span class="kpi-value"><?= $totalArts ?></span>
        <span class="kpi-label">Total Artifacts</span>
    </div>
    <div class="kpi-box">
        <span class="kpi-value"><?= $competencyCount ?></span>
        <span class="kpi-label">Competencies Covered</span>
    </div>
    <div class="kpi-box">
        <span class="kpi-value"><?= $valuesCount ?></span>
        <span class="kpi-label">Core Values</span>
    </div>
    <div class="kpi-box">
        <span class="kpi-value"><?= $yearCount ?></span>
        <span class="kpi-label">Academic Years</span>
    </div>
</div>

<!-- ── 2: Competency Summary ── -->
<div class="section-title">1. Competency Coverage Summary</div>
<table class="summary-table">
    <thead><tr><th>Core Competency</th><th>Artifacts</th><th>Avg Rating</th><th>Highest</th></tr></thead>
    <tbody>
    <?php foreach ($compSummary as $c): ?>
    <tr>
        <td><?= pe($c['competency_name'] ?? '') ?></td>
        <td><?= (int)($c['artifact_count']??0) ?></td>
        <td><?= ($c['avg_rating'] ?? '') !== '' ? number_format((float)$c['avg_rating'], 1) . ' / 5' : '—' ?></td>
        <td><?= pv($c['highest_rating'] ?? null, '—') ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($compSummary)): ?>
    <tr><td colspan="4" style="text-align:center;color:#9ca3af;">No competency data recorded.</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<!-- ── 3: Artifacts grouped by Competency → Year ── -->
<div class="section-title">2. Learning Evidence by Competency</div>
<?php if (empty($grouped)): ?>
<p style="text-align:center;color:#9ca3af;padding:6mm;">No artifacts have been recorded.</p>
<?php else: ?>
<?php foreach ($grouped as $compName => $years): ?>
<div class="competency-group">
    <h3><?= pe($compName) ?></h3>
    <?php foreach ($years as $year => $artifacts): ?>
    <div class="year-label">
        Academic Year <?= pe($year) ?>
        (<?= count($artifacts) ?> artifact<?= count($artifacts) !== 1 ? 's' : '' ?>)
    </div>
    <?php foreach ($artifacts as $a): ?>
    <div class="artifact-item">
        <h4>
            <?= pe($a['artifact_title'] ?? 'Untitled') ?>
            <?php if (($asset=$a['rating'] ?? '') !== ''): ?>
            <span class="rating"><?= pe($a['rating']) ?>/5</span>
            <?php endif; ?>
        </h4>
        <div class="meta">
            <span><?= pe($a['artifact_type'] ?? '') ?></span>
            <?php if ($a['upload_date'] ?? ''): ?><span><?= pe($a['upload_date']) ?></span><?php endif; ?>
            <?php if ($a['value_name'] ?? ''): ?><span>Value: <?= pe($a['value_name']) ?></span><?php endif; ?>
        </div>
        <?php if ($a['description'] ?? ''): ?>
        <div class="desc"><?= pe($a['description']) ?></div>
        <?php endif; ?>
        <?php if ($a['learner_reflection'] ?? ''): ?>
        <div class="reflection"><strong>Learner Reflection:</strong> <?= pe($a['learner_reflection']) ?></div>
        <?php endif; ?>
        <?php if ($a['teacher_feedback'] ?? ''): ?>
        <div class="feedback"><strong>Teacher Feedback:</strong> <?= pe($a['teacher_feedback']) ?></div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php endforeach; ?>
</div>
<?php endforeach; ?>
<?php endif; ?>

<!-- ── 4: Core Values ── -->
<?php if (!empty($valuesSummary)): ?>
<div class="section-title">3. Core Values Demonstrated</div>
<table class="summary-table">
    <thead><tr><th>Core Value</th><th>Evidence Count</th></tr></thead>
    <tbody>
    <?php foreach ($valsSummary as $v): ?>
    <tr><td><?= pe($v['value_name'] ?? '') ?></td><td><?= (int)($v['artifact_count']??0) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<!-- ── 5: Teacher’s Overall Assessment ── -->
<?php if ($teacherFB !== ''): ?>
<div class="section-title">4. Teacher’s Overall Assessment</div>
<div class="teacher-block"><?= nl2br(pe($teacherFB)) ?></div>
<?php endif; ?>

<!-- ── 6: Signatures ── -->
<div class="section-title">5. Signatures &amp; Validation</div>
<div class="signatures">
    <table><tr>
        <td><div style="height:8mm;"></div><div class="line">Class Teacher</div><p>Date: _______________</p></td>
        <td><div style="height:8mm;"></div><div class="line">Headteacher</div><p>Date: _______________</p></td>
    </tr>
    <tr>
        <td colspan="2" style="padding-top:5mm;">
            <div style="height:8mm;"></div>
            <div class="line">Parent / Guardian</div>
            <p>Date: _______________</p>
        </td>
    </tr>
    </table>
</div>