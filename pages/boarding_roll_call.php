<?php
/**
 * Boarding Roll Call — the dormitory register. Pick the dormitory and the
 * roll call (night, morning, weekend), tap each learner in, absent or in
 * the sick bay, and save. Learners on an approved exeat are shown as away.
 * Below: today's count for every dormitory you may see.
 * morning_roll_call and evening_roll_call open this page on their session.
 * Data: /api/attendance/dormitories | sessions | dormitory-students,
 * POST /api/attendance/mark-boarding (today only, dormitory-scoped),
 * /api/boarding/roll-call (per-dormitory counts).
 */
$rollCallSession = $rollCallSession ?? '';
?>
<link rel="stylesheet" href="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/css/pages/support_ops.css?v=<?= asset_version('css/pages/support_ops.css') ?>">
<div class="kw-ops kw-ops--dorm container-fluid py-3" id="rollCallPage" data-session-code="<?= htmlspecialchars($rollCallSession, ENT_QUOTES, 'UTF-8') ?>">
  <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
    <div>
      <h1 class="ops-title" id="rcTitle">Roll call</h1>
      <p class="ops-lede">Tap each learner as you count them. Learners away on an approved exeat are already marked.</p>
    </div>
    <div class="d-flex flex-wrap gap-2 d-print-none">
      <button class="btn btn-outline-secondary" type="button" id="rcCsv"><i class="bi bi-download me-1" aria-hidden="true"></i>CSV</button>
      <button class="btn btn-outline-secondary" type="button" id="rcPrint"><i class="bi bi-printer me-1" aria-hidden="true"></i>Print</button>
    </div>
  </div>

  <div class="row g-2 mb-3 d-print-none">
    <div class="col-12 col-md-4"><label class="form-label small" for="rcDorm">Dormitory</label><select class="form-select" id="rcDorm"></select></div>
    <div class="col-7 col-md-4"><label class="form-label small" for="rcSession">Roll call</label><select class="form-select" id="rcSession"></select></div>
    <div class="col-5 col-md-2"><label class="form-label small" for="rcDate">Date</label><input class="form-control" type="date" id="rcDate"></div>
    <div class="col-12 col-md-2"><label class="form-label small" for="rcSearch">Find</label><input class="form-control" type="search" id="rcSearch" placeholder="Name or bed"></div>
  </div>

  <div id="rcState" class="ops-panel text-muted mb-3" role="status">Loading your dormitories…</div>

  <div id="rcBody" hidden>
    <div class="ops-panel mb-3">
      <div class="d-flex flex-wrap justify-content-between align-items-end gap-3">
        <div class="ops-count" aria-live="polite">
          <span class="ops-count__n" id="rcIn">0</span><span class="ops-count__of" id="rcOf">of 0 in</span>
        </div>
        <ul class="ops-facts" id="rcFacts" aria-label="Roll call counts"></ul>
      </div>
      <div class="d-flex flex-wrap gap-2 mt-3 d-print-none" id="rcBulk">
        <button class="btn btn-outline-success" type="button" id="rcAllIn">Mark everyone not yet counted as in</button>
        <button class="btn btn-ops ms-md-auto" type="button" id="rcSave">Save roll call</button>
      </div>
      <p class="small text-muted mt-2 mb-0" id="rcNote"></p>
    </div>
    <section class="ops-panel" aria-labelledby="rcListTitle">
      <h2 id="rcListTitle">Learners</h2>
      <ul class="list-unstyled mb-0" id="rcList"></ul>
    </section>
  </div>

  <section class="ops-panel mt-3" aria-labelledby="rcAllTitle">
    <h2 id="rcAllTitle">All dormitories</h2>
    <div class="table-responsive" tabindex="0" role="region" aria-label="All dormitories">
      <table class="table align-middle mb-0">
        <thead><tr><th scope="col">Dormitory</th><th scope="col">Roll call</th><th scope="col">Learners</th><th scope="col">In</th><th scope="col">Absent</th><th scope="col">Away on exeat</th><th scope="col">Sick bay</th></tr></thead>
        <tbody id="rcAllBody"></tbody>
      </table>
    </div>
  </section>
</div>
<?php asset_script($appBase, 'js/pages/support_ops_common.js'); ?>
<?php asset_script($appBase, 'js/pages/boarding_roll_call.js'); ?>
