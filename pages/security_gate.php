<?php
/**
 * Gate Desk — the security post at the school gate.
 * Sign visitors in and out (or turn them away), see who is on site now,
 * let learners out and back in against an approved exeat, and keep the
 * gate log (vehicles, deliveries, school buses, after-hours movement).
 * Data: /api/security/* (SecurityGateService). Roles: Security staff and
 * school leadership.
 */
?>
<link rel="stylesheet" href="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/css/pages/security.css?v=<?= asset_version('css/pages/security.css') ?>">
<div class="kw-gate container-fluid py-3" id="gateDesk">
  <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
    <div>
      <h1 class="gate-title">Gate desk</h1>
      <p class="gate-lede">Sign visitors in and out, let learners leave on an approved exeat, and log what passes the gate.</p>
    </div>
    <div class="d-flex gap-2 d-print-none">
      <a class="btn btn-outline-secondary" href="home.php?route=school_security_incidents"><i class="bi bi-shield-exclamation me-1" aria-hidden="true"></i>Security incidents</a>
      <button class="btn btn-outline-danger" type="button" id="gdReportIncident"><i class="bi bi-exclamation-octagon me-1" aria-hidden="true"></i>Report an incident</button>
    </div>
  </div>

  <div class="d-flex flex-wrap align-items-end gap-4 mb-3">
    <div class="gate-now" aria-live="polite"><span class="gate-now__n" id="gdOnSite">0</span><span class="gate-now__label">on site now</span></div>
    <ul class="gate-figures" id="gdFigures" aria-label="Today at the gate"></ul>
  </div>

  <div class="row g-3">
    <div class="col-lg-5">
      <section class="gate-panel" aria-labelledby="gdSignInTitle">
        <h2 id="gdSignInTitle">Sign in a visitor</h2>
        <form id="gdForm" novalidate>
          <div class="row g-2">
            <div class="col-12"><label class="form-label" for="gdName">Full name *</label><input class="form-control" id="gdName" maxlength="150" autocomplete="off" required></div>
            <div class="col-sm-6"><label class="form-label" for="gdIdNo">ID / passport no.</label><input class="form-control" id="gdIdNo" maxlength="40" autocomplete="off" inputmode="numeric"></div>
            <div class="col-sm-6"><label class="form-label" for="gdPhone">Phone</label><input class="form-control" id="gdPhone" maxlength="30" type="tel" autocomplete="off"></div>
            <div class="col-sm-6"><label class="form-label" for="gdPurpose">Reason for visit *</label>
              <select class="form-select" id="gdPurpose">
                <option value="parent_visit">Parent visiting</option>
                <option value="learner_pickup">Picking up a learner</option>
                <option value="delivery">Delivery</option>
                <option value="contractor">Contractor / repairs</option>
                <option value="official">Official (MoE, county, other)</option>
                <option value="interview">Interview / admissions</option>
                <option value="meeting">Meeting</option>
                <option value="other">Other</option>
              </select></div>
            <div class="col-sm-6"><label class="form-label" for="gdOrg">From (organisation)</label><input class="form-control" id="gdOrg" maxlength="150"></div>
            <div class="col-12" id="gdLearnerWrap">
              <label class="form-label" for="gdLearnerSearch">Learner</label>
              <input class="form-control" id="gdLearnerSearch" placeholder="Learner name or admission number" autocomplete="off">
              <div id="gdLearnerResults" class="list-group mt-1"></div>
              <div id="gdLearnerChosen" class="mt-2"></div>
            </div>
            <div class="col-12">
              <label class="form-label" for="gdHostSearch">Seeing (staff or office)</label>
              <input class="form-control" id="gdHostSearch" placeholder="Staff name, or type an office e.g. Admissions" autocomplete="off">
              <div id="gdHostResults" class="list-group mt-1"></div>
            </div>
            <div class="col-sm-4"><label class="form-label" for="gdBadge">Badge no.</label><input class="form-control" id="gdBadge" maxlength="30"></div>
            <div class="col-sm-4"><label class="form-label" for="gdVehicle">Vehicle reg.</label><input class="form-control text-uppercase" id="gdVehicle" maxlength="20"></div>
            <div class="col-sm-4"><label class="form-label" for="gdPeople">People</label><input class="form-control" id="gdPeople" type="number" min="1" max="50" value="1"></div>
            <div class="col-12"><label class="form-label" for="gdItems">Items declared</label><input class="form-control" id="gdItems" maxlength="255" placeholder="e.g. laptop, parcel for Grade 5"></div>
          </div>
          <div class="d-flex flex-wrap gap-2 mt-3">
            <button class="btn btn-gate flex-grow-1" type="submit" id="gdSignIn"><i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i>Sign in</button>
            <button class="btn btn-outline-danger" type="button" id="gdDeny">Turn away</button>
          </div>
        </form>
      </section>
    </div>

    <div class="col-lg-7">
      <section class="gate-panel mb-3" aria-labelledby="gdOnSiteTitle">
        <div class="d-flex justify-content-between align-items-center">
          <h2 id="gdOnSiteTitle">On site now</h2>
          <span class="small text-muted">Over 3 hours shows in amber</span>
        </div>
        <ul class="gate-list" id="gdOnSiteList"><li class="gate-empty">Loading…</li></ul>
      </section>

      <section class="gate-panel mb-3" aria-labelledby="gdExeatTitle">
        <h2 id="gdExeatTitle">Learners leaving today</h2>
        <p class="small text-muted mb-2">Only learners with an approved exeat or permission can be let out.</p>
        <ul class="gate-list" id="gdExeats"><li class="gate-empty">Loading…</li></ul>
      </section>

      <section class="gate-panel" aria-labelledby="gdLogTitle">
        <h2 id="gdLogTitle">Gate log</h2>
        <form class="row g-2 align-items-end mb-2" id="gdMoveForm" novalidate>
          <div class="col-6 col-md-2"><label class="form-label small" for="gdMoveDir">Direction</label><select class="form-select" id="gdMoveDir"><option value="in">In</option><option value="out">Out</option></select></div>
          <div class="col-6 col-md-3"><label class="form-label small" for="gdMoveType">What</label>
            <select class="form-select" id="gdMoveType"><option value="vehicle">Vehicle</option><option value="school_bus">School bus</option><option value="delivery">Delivery</option><option value="staff_after_hours">Staff after hours</option><option value="occurrence">Occurrence</option><option value="other">Other</option></select></div>
          <div class="col-6 col-md-2"><label class="form-label small" for="gdMoveReg">Vehicle reg.</label><input class="form-control text-uppercase" id="gdMoveReg" maxlength="20"></div>
          <div class="col-6 col-md-3"><label class="form-label small" for="gdMoveNote">Who / what</label><input class="form-control" id="gdMoveNote" maxlength="500"></div>
          <div class="col-12 col-md-2"><button class="btn btn-gate w-100" type="submit">Log it</button></div>
        </form>
        <div class="table-responsive" tabindex="0" role="region" aria-label="Today's gate log">
          <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Time</th><th>In / out</th><th>What</th><th>Vehicle</th><th>Who / what</th><th>Logged by</th></tr></thead>
            <tbody id="gdMoves"><tr><td colspan="6" class="text-muted">Loading…</td></tr></tbody>
          </table>
        </div>
      </section>
    </div>
  </div>
</div>

<div class="modal fade" id="gdDenyModal" tabindex="-1" aria-labelledby="gdDenyTitle">
  <div class="modal-dialog"><div class="modal-content kw-gate">
    <div class="modal-header"><h2 class="modal-title h5" id="gdDenyTitle">Turn visitor away</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body"><label class="form-label" for="gdDenyReason">Why *</label><textarea class="form-control" id="gdDenyReason" rows="3" maxlength="255" placeholder="e.g. no appointment, no ID, not on the pick-up list"></textarea></div>
    <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal" type="button">Cancel</button><button class="btn btn-danger" type="button" id="gdDenyConfirm">Record and turn away</button></div>
  </div></div>
</div>

<?php require __DIR__ . '/security/_incident_report_modal.php'; ?>
<?php asset_script($appBase, 'js/pages/security_common.js'); ?>
<?php asset_script($appBase, 'js/pages/security_gate.js'); ?>
