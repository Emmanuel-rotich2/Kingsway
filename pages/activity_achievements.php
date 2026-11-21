<?php
/* Activity Achievements — team standings and completed fixture results. */

?>

<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-trophy text-warning me-2"></i>Activity Achievements</h4>
      <p class="text-muted small mb-0 mt-1">Sports team standings and completed match results.</p>
    </div>
    <button class="btn btn-outline-warning btn-sm" data-kw-onclick="activityAchievementsController.load()"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
  </div>

  <div class="bg-white border rounded-3 overflow-hidden mb-4">
    <div class="p-3 border-bottom fw-semibold small text-uppercase text-muted">Team Standings</div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr>
          <th>Team</th><th>Sport</th><th>Season</th><th>Played</th><th>W</th><th>D</th><th>L</th><th>GF</th><th>GA</th><th>Points</th><th>Form</th>
        </tr></thead>
        <tbody id="aaStandings"><tr><td colspan="11" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
      </table>
    </div>
  </div>

  <div class="bg-white border rounded-3 overflow-hidden">
    <div class="p-3 border-bottom fw-semibold small text-uppercase text-muted">Completed Fixtures</div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr>
          <th>Team</th><th>Opponent</th><th>Date</th><th>Venue</th><th>Score</th><th>Result</th>
        </tr></thead>
        <tbody id="aaFixtures"><tr><td colspan="6" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
      </table>
    </div>
  </div>

</div>

<?php asset_script($appBase, 'js/pages/activity_achievements.js'); ?>
