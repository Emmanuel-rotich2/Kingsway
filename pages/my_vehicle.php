<?php
/**
 * My Vehicle — the driver's logbook for the vehicle assigned to them:
 * service and insurance dates, the pre-trip check, services and repairs,
 * fuel bought, and problems reported to the transport office.
 * Data: /api/transport/my-logbook and my-service | my-fuel | my-check | my-problem
 * (DriverLogbookService, scoped to the driver's own vehicle).
 */
?>
<link rel="stylesheet" href="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/css/pages/support_ops.css?v=<?= asset_version('css/pages/support_ops.css') ?>">
<div class="kw-ops kw-ops--road container-fluid py-3" id="myVehiclePage">
  <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
    <div>
      <h1 class="ops-title">My vehicle</h1>
      <p class="ops-lede">Check the vehicle before each trip, and keep its services, fuel and problems on record.</p>
    </div>
    <div class="d-flex flex-wrap gap-2 ops-actions d-print-none" id="mvActions" hidden>
      <button class="btn btn-outline-secondary" type="button" id="mvFuelBtn"><i class="bi bi-fuel-pump me-1" aria-hidden="true"></i>Log fuel</button>
      <button class="btn btn-outline-secondary" type="button" id="mvServiceBtn"><i class="bi bi-wrench me-1" aria-hidden="true"></i>Log a service</button>
      <button class="btn btn-outline-danger" type="button" id="mvProblemBtn"><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>Report a problem</button>
    </div>
  </div>

  <div id="mvState" class="ops-panel text-muted" role="status">Loading your vehicle…</div>

  <div id="mvBody" hidden>
    <div class="ops-panel mb-3">
      <div class="d-flex flex-wrap align-items-center gap-4">
        <span class="ops-plate" id="mvPlate" aria-label="Registration number"></span>
        <div>
          <div class="fw-bold" id="mvModel"></div>
          <div class="text-muted small" id="mvMeta"></div>
        </div>
      </div>
      <ul class="ops-facts mt-3" id="mvFacts" aria-label="Vehicle dates and readings"></ul>
    </div>

    <section class="ops-panel mb-3 d-print-none" aria-labelledby="mvCheckTitle">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
        <h2 class="mb-0" id="mvCheckTitle">Pre-trip check</h2>
        <div id="mvCheckToday" class="d-flex flex-wrap gap-2"></div>
      </div>
      <form id="mvCheckForm" novalidate>
        <div class="row g-2 mb-2">
          <div class="col-sm-4">
            <label class="form-label small" for="mvCheckTrip">Trip</label>
            <select class="form-select" id="mvCheckTrip">
              <option value="morning_pickup">Morning pick-up</option>
              <option value="evening_dropoff">Evening drop-off</option>
              <option value="midday_trip">Midday trip</option>
              <option value="special_trip">Special trip</option>
            </select>
          </div>
          <div class="col-sm-4">
            <label class="form-label small" for="mvCheckOdo">Odometer (km)</label>
            <input class="form-control" type="number" min="0" inputmode="numeric" id="mvCheckOdo">
          </div>
        </div>
        <ul class="ops-check" id="mvCheckItems"></ul>
        <div id="mvDefectBox" class="mt-3" hidden>
          <label class="form-label small" for="mvCheckDefects">What is wrong?</label>
          <textarea class="form-control mb-2" id="mvCheckDefects" rows="2" maxlength="1000"></textarea>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="mvCheckFit">
            <label class="form-check-label" for="mvCheckFit">The vehicle is still safe to drive</label>
          </div>
          <p class="small text-muted mb-0">If it is not safe, the transport office is told straight away.</p>
        </div>
        <button class="btn btn-ops mt-3" type="submit" id="mvCheckSave">Save check</button>
      </form>
    </section>

    <section class="ops-panel" aria-label="Vehicle records">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="ops-tabs" role="tablist" aria-label="Vehicle records" id="mvTabs">
          <button type="button" role="tab" id="mvTabServices" aria-controls="mvServices" aria-selected="true">Services &amp; repairs</button>
          <button type="button" role="tab" id="mvTabFuel" aria-controls="mvFuel" aria-selected="false" tabindex="-1">Fuel</button>
          <button type="button" role="tab" id="mvTabChecks" aria-controls="mvChecks" aria-selected="false" tabindex="-1">Checks</button>
          <button type="button" role="tab" id="mvTabProblems" aria-controls="mvProblems" aria-selected="false" tabindex="-1">Problems</button>
        </div>
        <div class="d-flex gap-2 d-print-none">
          <button class="btn btn-outline-secondary btn-sm" type="button" id="mvCsv"><i class="bi bi-download me-1" aria-hidden="true"></i>CSV</button>
          <button class="btn btn-outline-secondary btn-sm" type="button" id="mvPrint"><i class="bi bi-printer me-1" aria-hidden="true"></i>Print</button>
        </div>
      </div>
      <div role="tabpanel" id="mvServices" aria-labelledby="mvTabServices"></div>
      <div role="tabpanel" id="mvFuel" aria-labelledby="mvTabFuel" hidden></div>
      <div role="tabpanel" id="mvChecks" aria-labelledby="mvTabChecks" hidden></div>
      <div role="tabpanel" id="mvProblems" aria-labelledby="mvTabProblems" hidden></div>
    </section>
  </div>

  <!-- Log a service -->
  <div class="modal fade" id="mvServiceModal" tabindex="-1" aria-labelledby="mvServiceTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable"><form class="modal-content" id="mvServiceForm" novalidate>
      <div class="modal-header"><h2 class="modal-title h5" id="mvServiceTitle">Log a service or repair</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body row g-2">
        <div class="col-6"><label class="form-label small" for="mvSvType">Work done</label>
          <select class="form-select" id="mvSvType" required><option value="routine">Routine service</option><option value="repair">Repair</option><option value="inspection">Inspection</option><option value="emergency">Emergency repair</option></select></div>
        <div class="col-6"><label class="form-label small" for="mvSvDate">Date</label><input class="form-control" type="date" id="mvSvDate" required></div>
        <div class="col-12"><label class="form-label small" for="mvSvDesc">What was done</label><textarea class="form-control" id="mvSvDesc" rows="2" maxlength="2000" required></textarea></div>
        <div class="col-6"><label class="form-label small" for="mvSvOdo">Odometer (km)</label><input class="form-control" type="number" min="0" inputmode="numeric" id="mvSvOdo"></div>
        <div class="col-6"><label class="form-label small" for="mvSvCost">Cost (KES)</label><input class="form-control" type="number" min="0" step="0.01" inputmode="decimal" id="mvSvCost"></div>
        <div class="col-12"><label class="form-label small" for="mvSvParts">Parts replaced</label><input class="form-control" id="mvSvParts" maxlength="1000"></div>
        <div class="col-12"><label class="form-label small" for="mvSvGarage">Garage or mechanic</label><input class="form-control" id="mvSvGarage" maxlength="500"></div>
        <div class="col-6"><label class="form-label small" for="mvSvNext">Next service date</label><input class="form-control" type="date" id="mvSvNext"></div>
        <div class="col-6"><label class="form-label small" for="mvSvNextKm">Next service at (km)</label><input class="form-control" type="number" min="0" inputmode="numeric" id="mvSvNextKm"></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-ops">Save service</button></div>
    </form></div>
  </div>

  <!-- Log fuel -->
  <div class="modal fade" id="mvFuelModal" tabindex="-1" aria-labelledby="mvFuelTitle" aria-hidden="true">
    <div class="modal-dialog"><form class="modal-content" id="mvFuelForm" novalidate>
      <div class="modal-header"><h2 class="modal-title h5" id="mvFuelTitle">Log fuel</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body row g-2">
        <div class="col-6"><label class="form-label small" for="mvFuDate">Date</label><input class="form-control" type="date" id="mvFuDate" required></div>
        <div class="col-6"><label class="form-label small" for="mvFuOdo">Odometer (km)</label><input class="form-control" type="number" min="0" inputmode="numeric" id="mvFuOdo"></div>
        <div class="col-6"><label class="form-label small" for="mvFuLitres">Litres</label><input class="form-control" type="number" min="0.1" step="0.01" inputmode="decimal" id="mvFuLitres" required></div>
        <div class="col-6"><label class="form-label small" for="mvFuAmount">Amount paid (KES)</label><input class="form-control" type="number" min="1" step="0.01" inputmode="decimal" id="mvFuAmount" required></div>
        <div class="col-6"><label class="form-label small" for="mvFuStation">Station</label><input class="form-control" id="mvFuStation" maxlength="120"></div>
        <div class="col-6"><label class="form-label small" for="mvFuReceipt">Receipt number</label><input class="form-control" id="mvFuReceipt" maxlength="60"></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-ops">Save fuel</button></div>
    </form></div>
  </div>

  <!-- Report a problem -->
  <div class="modal fade" id="mvProblemModal" tabindex="-1" aria-labelledby="mvProblemTitle" aria-hidden="true">
    <div class="modal-dialog"><form class="modal-content" id="mvProblemForm" novalidate>
      <div class="modal-header"><h2 class="modal-title h5" id="mvProblemTitle">Report a problem</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body">
        <label class="form-label small" for="mvPrType">What happened</label>
        <select class="form-select mb-2" id="mvPrType"><option value="vehicle_breakdown">Breakdown or fault</option><option value="accident">Accident</option><option value="late_pickup">Late for pick-up</option><option value="late_dropoff">Late for drop-off</option><option value="other">Something else</option></select>
        <label class="form-label small" for="mvPrDesc">Describe it</label>
        <textarea class="form-control mb-2" id="mvPrDesc" rows="3" maxlength="2000" required></textarea>
        <label class="form-label small" for="mvPrAction">What you have done so far</label>
        <textarea class="form-control mb-2" id="mvPrAction" rows="2" maxlength="1000"></textarea>
        <div class="form-check"><input class="form-check-input" type="checkbox" id="mvPrUrgent"><label class="form-check-label" for="mvPrUrgent">Urgent: the transport office must act now</label></div>
        <p class="small text-muted mt-2 mb-0">A learner hurt or unwell? Also call the school straight away.</p>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-danger">Report problem</button></div>
    </form></div>
  </div>
</div>
<?php asset_script($appBase, 'js/pages/support_ops_common.js'); ?>
<?php asset_script($appBase, 'js/pages/my_vehicle.js'); ?>
