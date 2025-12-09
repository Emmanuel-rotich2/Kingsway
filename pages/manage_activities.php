<?php
// filepath: /home/prof_angera/Projects/php_pages/Kingsway/pages/manage_activities.php
include __DIR__ . '/../components/tables/table.php';

// Example: Fetch activities data
$activitiesHeaders = ['No', 'Activity Name', 'Category', 'Date', 'Participants', 'Status'];
$activitiesRows = [
  [1, 'Inter-House Football Match', 'Sports', '2025-01-30', 42, 'Scheduled'],
  [2, 'Drama Club Performance', 'Arts', '2025-02-05', 28, 'In Progress'],
  [3, 'Science Fair', 'Academic', '2025-02-15', 65, 'Planning'],
  [4, 'Music Concert', 'Arts', '2025-01-28', 35, 'Completed'],
];
$actionOptions = ['View Details', 'Edit', 'Manage Participants', 'Cancel'];
?>

<div class="container mt-1">
  <h2 class="mb-4 d-flex justify-content-between align-items-center">
    <span><i class="bi bi-trophy"></i> Activities Management</span>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addActivityModal">
      <i class="bi bi-plus-circle"></i> Add Activity
    </button>
  </h2>
  
  <div class="row mb-4">
    <div class="col-md-3">
      <div class="card bg-primary text-white">
        <div class="card-body">
          <h6>Total Activities</h6>
          <h3>24</h3>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card bg-success text-white">
        <div class="card-body">
          <h6>Active</h6>
          <h3>8</h3>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card bg-warning text-white">
        <div class="card-body">
          <h6>Upcoming</h6>
          <h3>6</h3>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card bg-info text-white">
        <div class="card-body">
          <h6>Total Participants</h6>
          <h3>342</h3>
        </div>
      </div>
    </div>
  </div>

  <ul class="nav nav-tabs mb-4" role="tablist">
    <li class="nav-item">
      <a class="nav-link active" data-bs-toggle="tab" href="#allActivities">All Activities</a>
    </li>
    <li class="nav-item">
      <a class="nav-link" data-bs-toggle="tab" href="#sports">Sports</a>
    </li>
    <li class="nav-item">
      <a class="nav-link" data-bs-toggle="tab" href="#arts">Arts & Culture</a>
    </li>
    <li class="nav-item">
      <a class="nav-link" data-bs-toggle="tab" href="#clubs">Clubs</a>
    </li>
  </ul>

  <div class="tab-content">
    <div id="allActivities" class="tab-pane fade show active">
      <?php renderTable($activitiesHeaders, $activitiesRows, $actionOptions, 'activitiesTable'); ?>
    </div>
    <div id="sports" class="tab-pane fade">
      <p>Sports activities will appear here.</p>
    </div>
    <div id="arts" class="tab-pane fade">
      <p>Arts and culture activities will appear here.</p>
    </div>
    <div id="clubs" class="tab-pane fade">
      <p>Club activities will appear here.</p>
    </div>
  </div>
</div>
