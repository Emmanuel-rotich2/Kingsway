<?php
/**
 * Security Incidents — report and respond.
 * The security team and school leadership see every incident and record each
 * response step; leadership resolves, closes and reopens. Other staff see
 * the incidents they reported. Data: /api/security/incidents*.
 */
?>
<link rel="stylesheet" href="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/css/pages/security.css?v=<?= asset_version('css/pages/security.css') ?>">
<div class="kw-gate container-fluid py-3" id="secIncidentsPage">
  <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
    <div>
      <h1 class="gate-title">Security incidents</h1>
      <p class="gate-lede">Everything that threatened the safety of learners, staff or property, and what was done about it.</p>
    </div>
    <div class="d-flex gap-2 d-print-none">
      <button class="btn btn-outline-secondary" type="button" id="siCsv"><i class="bi bi-download me-1" aria-hidden="true"></i>CSV</button>
      <button class="btn btn-outline-secondary" type="button" id="siPrint"><i class="bi bi-printer me-1" aria-hidden="true"></i>Print</button>
      <button class="btn btn-danger" type="button" id="siReport"><i class="bi bi-exclamation-octagon me-1" aria-hidden="true"></i>Report an incident</button>
    </div>
  </div>
  <div class="row g-2 mb-3 d-print-none">
    <div class="col-6 col-md-2"><label class="form-label small" for="siStatus">Status</label>
      <select class="form-select" id="siStatus"><option value="open" selected>Open</option><option value="">All</option><option value="reported">Reported</option><option value="responding">Responding</option><option value="contained">Contained</option><option value="escalated">Escalated</option><option value="resolved">Resolved</option><option value="closed">Closed</option></select></div>
    <div class="col-6 col-md-2"><label class="form-label small" for="siSeverity">Seriousness</label>
      <select class="form-select" id="siSeverity"><option value="">All</option><option value="critical">Critical</option><option value="high">High</option><option value="medium">Medium</option><option value="low">Low</option></select></div>
    <div class="col-6 col-md-2"><label class="form-label small" for="siFrom">From</label><input class="form-control" type="date" id="siFrom"></div>
    <div class="col-6 col-md-2"><label class="form-label small" for="siTo">To</label><input class="form-control" type="date" id="siTo"></div>
  </div>
  <div class="gate-panel p-0">
    <div class="table-responsive" tabindex="0" role="region" aria-label="Security incidents">
      <table class="table table-hover align-middle mb-0">
        <thead><tr><th>Reference</th><th>What</th><th>Where</th><th>When</th><th>Seriousness</th><th>Status</th><th>Handled by</th></tr></thead>
        <tbody id="siBody"><tr><td colspan="7" class="text-muted p-3">Loading…</td></tr></tbody>
      </table>
    </div>
  </div>
</div>

<div class="modal fade" id="siDetail" tabindex="-1" aria-labelledby="siDetailTitle">
  <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content kw-gate">
    <div class="modal-header"><h2 class="modal-title h5" id="siDetailTitle">Incident</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body" id="siDetailBody"></div>
    <div class="modal-footer d-block" id="siActions"></div>
  </div></div>
</div>

<?php require __DIR__ . '/security/_incident_report_modal.php'; ?>
<?php asset_script($appBase, 'js/pages/security_common.js'); ?>
<?php asset_script($appBase, 'js/pages/school_security_incidents.js'); ?>
