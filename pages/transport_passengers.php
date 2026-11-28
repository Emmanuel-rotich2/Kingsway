<?php
/**
 * Today's Passengers — the driver's run sheet. Learners grouped by stop in
 * route order; one tap marks each learner on the bus / absent (morning) or
 * dropped off / not riding (evening). Problems with a passenger go to the
 * route's transport incidents.
 * Data: /api/transport/driver-manifest, POST /api/transport/attendance,
 * POST /api/transport/my-problem. Scoped to the driver's assigned routes.
 */
?>
<link rel="stylesheet" href="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/css/pages/support_ops.css?v=<?= asset_version('css/pages/support_ops.css') ?>">
<div class="kw-ops kw-ops--road container-fluid py-3" id="runSheetPage">
  <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
    <div>
      <h1 class="ops-title">Today's passengers</h1>
      <p class="ops-lede">Stop by stop. Tap as each learner gets on or off the bus.</p>
    </div>
    <div class="d-flex flex-wrap gap-2 d-print-none">
      <button class="btn btn-outline-danger" type="button" id="rsProblemBtn"><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>Report a problem</button>
      <button class="btn btn-outline-secondary" type="button" id="rsCsv"><i class="bi bi-download me-1" aria-hidden="true"></i>CSV</button>
      <button class="btn btn-outline-secondary" type="button" id="rsPrint"><i class="bi bi-printer me-1" aria-hidden="true"></i>Print</button>
    </div>
  </div>

  <div class="row g-2 mb-3 d-print-none">
    <div class="col-6 col-md-3"><label class="form-label small" for="rsTrip">Trip</label>
      <select class="form-select" id="rsTrip"><option value="morning_pickup">Morning pick-up</option><option value="evening_dropoff">Evening drop-off</option><option value="midday_trip">Midday trip</option><option value="special_trip">Special trip</option></select></div>
    <div class="col-6 col-md-2"><label class="form-label small" for="rsDate">Date</label><input class="form-control" type="date" id="rsDate"></div>
    <div class="col-6 col-md-3" id="rsRouteWrap" hidden><label class="form-label small" for="rsRoute">Route</label><select class="form-select" id="rsRoute"></select></div>
    <div class="col-6 col-md-4"><label class="form-label small" for="rsSearch">Find a learner</label><input class="form-control" type="search" id="rsSearch" placeholder="Name or admission number"></div>
  </div>

  <div class="ops-panel mb-3">
    <ul class="ops-facts" id="rsFacts" aria-live="polite" aria-label="Trip progress"></ul>
  </div>

  <div id="rsState" class="ops-panel text-muted" role="status">Loading today's passengers…</div>
  <div id="rsStops" class="d-grid gap-3"></div>

  <div class="modal fade" id="rsProblemModal" tabindex="-1" aria-labelledby="rsProblemTitle" aria-hidden="true">
    <div class="modal-dialog"><form class="modal-content" id="rsProblemForm" novalidate>
      <div class="modal-header"><h2 class="modal-title h5" id="rsProblemTitle">Report a problem</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body">
        <label class="form-label small" for="rsPrStudent">Learner (if it is about one)</label>
        <select class="form-select mb-2" id="rsPrStudent"></select>
        <label class="form-label small" for="rsPrType">What happened</label>
        <select class="form-select mb-2" id="rsPrType"><option value="behavior">Behaviour on the bus</option><option value="medical">Unwell or hurt</option><option value="wrong_stop">Wrong stop / not collected</option><option value="late_pickup">Late for pick-up</option><option value="late_dropoff">Late for drop-off</option><option value="accident">Accident</option><option value="vehicle_breakdown">Breakdown</option><option value="other">Something else</option></select>
        <label class="form-label small" for="rsPrDesc">Describe it</label>
        <textarea class="form-control mb-2" id="rsPrDesc" rows="3" maxlength="2000" required></textarea>
        <label class="form-label small" for="rsPrAction">What you have done so far</label>
        <textarea class="form-control mb-2" id="rsPrAction" rows="2" maxlength="1000"></textarea>
        <div class="form-check"><input class="form-check-input" type="checkbox" id="rsPrUrgent"><label class="form-check-label" for="rsPrUrgent">Urgent: the transport office must act now</label></div>
        <p class="small text-muted mt-2 mb-0">Unwell, hurt or an accident is always sent as urgent. Call the school as well.</p>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-danger">Report problem</button></div>
    </form></div>
  </div>
</div>
<?php asset_script($appBase, 'js/pages/support_ops_common.js'); ?>
<?php asset_script($appBase, 'js/pages/transport_passengers.js'); ?>
