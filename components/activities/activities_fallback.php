<div class="container-fluid mt-3">

  <div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="bi bi-trophy"></i> Activities Management</h3>

    <?php if (can($userRole, 'activities', 'create')): ?>
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addActivityModal">
        <i class="bi bi-plus-circle"></i> Add Activity
      </button>
    <?php endif; ?>
  </div>

  <!-- Summary Cards -->
  <div class="row mb-4">
    <?php
      $cards = [
        ['Total Activities','primary','totalActivities'],
        ['Active','success','activeActivities'],
        ['Upcoming','warning','upcomingActivities'],
        ['Participants','info','totalParticipants']
      ];
      foreach ($cards as [$title,$color,$id]):
    ?>
    <div class="col-md-3 mb-3">
      <div class="card bg-<?=$color?> text-white shadow-sm">
        <div class="card-body text-center">
          <small><?=$title?></small>
          <h3 id="<?=$id?>">0</h3>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Activities Table -->
  <div class="card shadow-sm">
    <div class="card-body">
      <div class="d-flex justify-content-between mb-3">
        <input class="form-control w-25" id="searchActivities" placeholder="Search activities...">
        <?php if (can($userRole, 'activities', 'export')): ?>
          <button class="btn btn-outline-secondary">
            <i class="bi bi-download"></i> Export
          </button>
        <?php endif; ?>
      </div>

      <div class="table-responsive">
        <table class="table table-hover" id="activitiesTable">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th>Activity</th>
              <th>Category</th>
              <th>Date</th>
              <th>Participants</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script src="/js/pages/manage_activities.js"></script>
