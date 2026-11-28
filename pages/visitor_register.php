<?php
/**
 * Visitor Register — every visit recorded at the gate, searchable by date,
 * reason and name/ID/vehicle, with CSV and print. Data: /api/security/visits.
 */
?>
<link rel="stylesheet" href="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/css/pages/security.css?v=<?= asset_version('css/pages/security.css') ?>">
<div class="kw-gate container-fluid py-3" id="visitorRegisterPage">
  <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
    <div>
      <h1 class="gate-title">Visitor register</h1>
      <p class="gate-lede">Who came to school, why, who they saw and when they left.</p>
    </div>
    <div class="d-flex gap-2 d-print-none">
      <button class="btn btn-outline-secondary" type="button" id="vrCsv"><i class="bi bi-download me-1" aria-hidden="true"></i>CSV</button>
      <button class="btn btn-outline-secondary" type="button" id="vrPrint"><i class="bi bi-printer me-1" aria-hidden="true"></i>Print</button>
    </div>
  </div>
  <div class="row g-2 mb-3 d-print-none">
    <div class="col-6 col-md-2"><label class="form-label small" for="vrFrom">From</label><input class="form-control" type="date" id="vrFrom"></div>
    <div class="col-6 col-md-2"><label class="form-label small" for="vrTo">To</label><input class="form-control" type="date" id="vrTo"></div>
    <div class="col-6 col-md-2"><label class="form-label small" for="vrStatus">Status</label>
      <select class="form-select" id="vrStatus"><option value="">All</option><option value="on_site">On site</option><option value="left">Left</option><option value="denied">Turned away</option></select></div>
    <div class="col-6 col-md-2"><label class="form-label small" for="vrPurpose">Reason</label>
      <select class="form-select" id="vrPurpose"><option value="">All</option><option value="parent_visit">Parent visiting</option><option value="learner_pickup">Picking up a learner</option><option value="delivery">Delivery</option><option value="contractor">Contractor</option><option value="official">Official</option><option value="interview">Interview / admissions</option><option value="meeting">Meeting</option><option value="other">Other</option></select></div>
    <div class="col-12 col-md-4"><label class="form-label small" for="vrQ">Search</label><input class="form-control" type="search" id="vrQ" placeholder="Name, ID number, phone, vehicle or badge"></div>
  </div>
  <p class="small text-muted mb-2" id="vrCount"></p>
  <div class="gate-panel p-0">
    <div class="table-responsive" tabindex="0" role="region" aria-label="Visitor register">
      <table class="table table-hover align-middle mb-0">
        <thead><tr><th>In</th><th>Out</th><th>Visitor</th><th>ID no.</th><th>Reason</th><th>Seeing</th><th>Badge / vehicle</th><th>Status</th></tr></thead>
        <tbody id="vrBody"><tr><td colspan="8" class="text-muted p-3">Loading…</td></tr></tbody>
      </table>
    </div>
  </div>
</div>
<?php asset_script($appBase, 'js/pages/security_common.js'); ?>
<?php asset_script($appBase, 'js/pages/visitor_register.js'); ?>
