<?php
/**
 * My Routes / Routes Overview — the route board.
 * Drivers see the route they drive: stops in order with times, the vehicle,
 * coming schedules and recent incidents. The Director and School
 * Administrator pick any route. Learner names are on My Passengers.
 * Data: /api/transport/my-route (driver), /api/transport/all-routes and
 * /api/transport/route-board/{id} (overview).
 */
?>
<link rel="stylesheet" href="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/css/pages/support_ops.css?v=<?= asset_version('css/pages/support_ops.css') ?>">
<div class="kw-ops kw-ops--road container-fluid py-3" id="myRoutesPage">
  <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
    <div>
      <h1 class="ops-title" id="mrTitle">My route</h1>
      <p class="ops-lede" id="mrLede">Your stops in order, with pick-up times, the vehicle and the coming schedule.</p>
    </div>
    <div class="d-flex flex-wrap gap-2 d-print-none">
      <div id="mrPickWrap" hidden>
        <label class="visually-hidden" for="mrPick">Route</label>
        <select class="form-select" id="mrPick"></select>
      </div>
      <a class="btn btn-ops" href="home.php?route=transport_passengers" id="mrPassengers"><i class="bi bi-people me-1" aria-hidden="true"></i>Today's passengers</a>
      <button class="btn btn-outline-secondary" type="button" id="mrPrint"><i class="bi bi-printer me-1" aria-hidden="true"></i>Print</button>
    </div>
  </div>

  <div id="mrState" class="ops-panel text-muted" role="status">Loading the route…</div>

  <div id="mrBody" hidden>
    <div class="ops-blind mb-3" id="mrBlind" aria-label="Route"></div>
    <ul class="ops-facts mb-3" id="mrFacts" aria-label="Route details"></ul>
    <div class="row g-3">
      <div class="col-lg-7">
        <section class="ops-panel h-100" aria-labelledby="mrStopsTitle">
          <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
            <h2 class="mb-0" id="mrStopsTitle">Stops</h2>
            <div class="btn-group btn-group-sm d-print-none" role="group" aria-label="Show times for">
              <input type="radio" class="btn-check" name="mrDir" id="mrDirAm" value="am" checked>
              <label class="btn btn-outline-secondary" for="mrDirAm">Morning</label>
              <input type="radio" class="btn-check" name="mrDir" id="mrDirPm" value="pm">
              <label class="btn btn-outline-secondary" for="mrDirPm">Afternoon</label>
            </div>
          </div>
          <ol class="ops-line" id="mrStops"></ol>
          <button class="btn btn-outline-secondary btn-sm mt-2 d-print-none" type="button" id="mrStopsCsv"><i class="bi bi-download me-1" aria-hidden="true"></i>Stops CSV</button>
        </section>
      </div>
      <div class="col-lg-5 d-grid gap-3 align-content-start">
        <section class="ops-panel" aria-labelledby="mrVehTitle">
          <h2 id="mrVehTitle">Vehicle</h2>
          <div id="mrVehicles"></div>
        </section>
        <section class="ops-panel" aria-labelledby="mrSchedTitle">
          <h2 id="mrSchedTitle">Coming trips</h2>
          <div id="mrSchedules"></div>
        </section>
        <section class="ops-panel" aria-labelledby="mrIncTitle">
          <h2 id="mrIncTitle">Recent incidents on this route</h2>
          <div id="mrIncidents"></div>
        </section>
      </div>
    </div>
  </div>
</div>
<?php asset_script($appBase, 'js/pages/support_ops_common.js'); ?>
<?php asset_script($appBase, 'js/pages/my_routes.js'); ?>
