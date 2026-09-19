<?php
/** @var string $governedReportCode */
/** @var string $governedReportTitle */
$governedReportCode = $governedReportCode ?? '';
$governedReportTitle = $governedReportTitle ?? 'Report';
$governedReportLayout = $governedReportLayout ?? 'overview';
$governedPrimaryVisual = $governedPrimaryVisual ?? 'bar';
?>
<link rel="stylesheet" href="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/css/reports/report-components.css">
<style>
  .gr-hero{background:linear-gradient(135deg,#123c2d,#1f7a55);color:#fff;border-radius:14px;padding:1.4rem;box-shadow:0 8px 24px rgba(18,60,45,.18)}
  .gr-panel{background:#fff;border:1px solid #e2ebe6;border-radius:12px;box-shadow:0 2px 10px rgba(18,60,45,.06)}
  .analytics-meta{color:#63746b;font-size:.82rem}.analytics-metric{background:#f4faf7;border-left:3px solid #2e8b62;border-radius:8px;padding:.75rem}
  .analytics-summary-card{background:#f7faf8;border:1px solid #e1ebe5;border-radius:9px;padding:.75rem}.analytics-result-table{font-size:.84rem}
  .analytics-result-table thead th{background:#174f39;color:#fff;white-space:nowrap}.analytics-empty{border:1px dashed #bdcec5;border-radius:12px;color:#66766e;padding:3rem 1rem;text-align:center}
</style>
<div class="container-fluid px-0 kw-report kw-report-layout--<?= htmlspecialchars($governedReportLayout, ENT_QUOTES, 'UTF-8') ?>" id="governedReportPage" data-report-code="<?= htmlspecialchars($governedReportCode, ENT_QUOTES, 'UTF-8') ?>" data-primary-visual="<?= htmlspecialchars($governedPrimaryVisual, ENT_QUOTES, 'UTF-8') ?>">
  <section class="gr-hero mb-3">
    <div class="small text-uppercase opacity-75 fw-semibold" id="analyticsReportDomain">Reports &amp; Analytics</div>
    <h1 class="h3 mb-1" id="analyticsReportModalTitle"><?= htmlspecialchars($governedReportTitle, ENT_QUOTES, 'UTF-8') ?></h1>
    <p class="mb-0 opacity-75" id="analyticsDecisionPurpose">Loading report definition…</p>
    <div class="small opacity-75 mt-2" id="analyticsDefinitionMeta"></div>
  </section>
  <div id="analyticsCatalogueStatus" class="visually-hidden" role="status" aria-live="polite"></div>
  <section class="gr-panel p-3 mb-3"><div class="row g-2" id="analyticsMetricPanel"></div></section>
  <form id="analyticsFilterForm" class="gr-panel p-3 mb-3" novalidate>
    <div class="d-flex justify-content-between align-items-center mb-2"><h2 class="h6 mb-0">Report filters</h2><span class="analytics-meta">Your authorized scope is enforced by the server.</span></div>
    <div class="row g-2" id="analyticsFilterFields"></div>
  </form>
  <section class="gr-panel p-3" id="analyticsResultRegion" hidden>
    <div class="d-flex flex-column flex-md-row justify-content-between gap-2 align-items-md-center mb-2">
      <div><h2 class="h6 mb-0">Report results</h2><div class="analytics-meta" id="analyticsRunMeta"></div></div>
      <div class="btn-group btn-group-sm" id="analyticsExportButtons"></div>
    </div>
    <div class="row g-2 mb-3" id="analyticsSummary"></div><div class="alert alert-warning py-2" id="analyticsWarnings" hidden></div>
    <div class="card border-success-subtle bg-light mb-3"><div class="card-body py-2"><div class="d-flex justify-content-between align-items-center gap-2"><div><strong><i class="bi bi-stars text-success me-1"></i>AI report explanation</strong><div class="small text-muted">Explains governed results and suggests follow-up questions. It does not calculate or change report data.</div></div><button type="button" class="btn btn-outline-success btn-sm" id="queueAiKpiBrief"><i class="bi bi-stars me-1"></i>Explain this report</button></div><div id="aiKpiBriefs" class="row g-2 mt-2"></div></div></div>
    <div class="row g-3 mb-3" id="analyticsVisualRegion">
      <div class="col-xl-7"><div class="kw-report-panel h-100"><div class="kw-report-panel__head"><div><div class="kw-report-eyebrow">Visual analysis</div><h3 class="h6 mb-0" id="analyticsPrimaryVisualTitle">Report pattern</h3></div></div><div class="kw-report-panel__body kw-chart" id="analyticsPrimaryVisual"><canvas id="analyticsPrimaryChart"></canvas></div></div></div>
      <div class="col-xl-5"><div class="kw-report-panel h-100"><div class="kw-report-panel__head"><div><div class="kw-report-eyebrow">Cross-tabulation</div><h3 class="h6 mb-0">Pivot summary</h3></div></div><div class="kw-report-panel__body" id="analyticsPivot"></div></div></div>
    </div>
    <div class="table-responsive border rounded"><table class="table table-hover table-striped mb-0 analytics-result-table" id="analyticsResultTable"><thead></thead><tbody></tbody></table></div>
    <div class="analytics-empty" id="analyticsNoRows" hidden>No records matched the selected filters.</div>
  </section>
</div>
<?php asset_script($appBase, 'js/reports/report_components.js'); ?>
<?php asset_script($appBase, 'js/pages/analytics_catalogue.js'); ?>
<?php asset_script($appBase, 'js/pages/ai_kpi_brief.js'); ?>
