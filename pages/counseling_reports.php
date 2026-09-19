<?php
/* Counseling Reports — counseling analytics and session trends. */
$appBase = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($appBase === '.') $appBase = '';
?>

<div class="container-fluid px-4 py-4">
  <div class="card border-success-subtle bg-light mb-3" id="aiCounselingPanel"><div class="card-body py-2"><div class="d-flex justify-content-between align-items-center gap-2"><div><strong><i class="bi bi-stars text-success me-1"></i>Counselling welfare assistant</strong><div class="small text-muted">Aggregate follow-up guidance only; confidential notes, identities, diagnoses, and safeguarding decisions stay with authorized professionals.</div></div><button type="button" class="btn btn-outline-success btn-sm" id="queueAiCounselingReview"><i class="bi bi-stars me-1"></i>Review welfare signals</button></div><div id="aiCounselingReviews" class="row g-2 mt-2"></div></div></div>

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-graph-up text-primary me-2"></i>Counseling Reports</h4>
      <p class="text-muted small mb-0 mt-1">Counseling analytics and session trends.</p>
    </div>
    <button class="btn btn-outline-primary btn-sm" onclick="counselingReportsController.load()"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
  </div>

  <div class="row g-3 mb-4" id="crKpis"></div>

  <div class="row g-4">
    <div class="col-lg-6">
      <div class="bg-white border rounded-3 overflow-hidden">
        <div class="p-3 border-bottom fw-semibold small text-uppercase text-muted">Cases by Type</div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Case Type</th><th>Count</th></tr></thead>
            <tbody id="crByType"><tr><td colspan="2" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm"></div></td></tr></tbody>
          </table>
        </div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="bg-white border rounded-3 overflow-hidden">
        <div class="p-3 border-bottom fw-semibold small text-uppercase text-muted">Session Trend (Last 5 Months)</div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Month</th><th>Sessions</th><th>Bar</th></tr></thead>
            <tbody id="crTrend"><tr><td colspan="3" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm"></div></td></tr></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="bg-white border rounded-3 overflow-hidden mt-4">
    <div class="p-3 border-bottom fw-semibold small text-uppercase text-muted">Follow-ups Due (Next 14 Days)</div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Case</th><th>Counselee</th><th>Type</th><th>Priority</th><th>Follow-up</th></tr></thead>
        <tbody id="crFollow"><tr><td colspan="5" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm"></div></td></tr></tbody>
      </table>
    </div>
  </div>

</div>

<?php asset_script($appBase, 'js/pages/counseling_reports.js'); ?>
<?php asset_script($appBase, 'js/pages/ai_counseling_review.js'); ?>
