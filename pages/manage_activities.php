<?php
/**
 * Activities Management - API & JWT Mode
 * Fully cleaned version
 * No PHP template includes, modal embedded, ready for JS/API
 */

require_once __DIR__ . '/../config/config.php';
?>

<div class="container-fluid mt-3">

  <!-- Page Header -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-trophy"></i> Activities Management</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addActivityModal">
      <i class="bi bi-plus-circle"></i> Add Activity
    </button>
  </div>

<<<<<<< HEAD
  <!-- Summary Cards -->
  <div class="row mb-4">
    <div class="col-md-3 mb-3">
      <div class="card shadow-sm bg-primary text-white text-center">
        <div class="card-body">
          <h6>Total Activities</h6>
          <h3 id="totalActivities">0</h3>
=======

// Get the appropriate template
$templateFile = $templateMap[$roleCategory] ?? $templateMap['viewer'];

// Include the base layout
include __DIR__ . '/../layouts/app_layout.php';

// Start content buffer for layout
ob_start();

// Include the role-specific template
if (file_exists(__DIR__ . '/' . $templateFile)) {
  include __DIR__ . '/' . $templateFile;
} else {
  // Fallback to legacy template if role-specific doesn't exist yet
  ?>
  <!-- Legacy Activities Page (Fallback) -->
  <?php include __DIR__ . '/../components/tables/table.php'; ?>
  <div>
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2><i class="bi bi-trophy"></i> Activities Management</h2>
      <?php if (can($userRole, 'activities', 'create')): ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addActivityModal">
          <i class="bi bi-plus-circle"></i> Add Activity
        </button>
      <?php endif; ?>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
      <div class="col-md-3 mb-3">
        <div class="card shadow-sm bg-primary text-white">
          <div class="card-body text-center">
            <h6>Total Activities</h6>
            <h3 id="totalActivities">0</h3>
          </div>
        </div>
      </div>
      <div class="col-md-3 mb-3">
        <div class="card shadow-sm bg-success text-white">
          <div class="card-body text-center">
            <h6>Active</h6>
            <h3 id="activeActivities">0</h3>
          </div>
        </div>
      </div>
      <div class="col-md-3 mb-3">
        <div class="card shadow-sm bg-warning text-white">
          <div class="card-body text-center">
            <h6>Upcoming</h6>
            <h3 id="upcomingActivities">0</h3>
          </div>
        </div>
      </div>
      <div class="col-md-3 mb-3">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
          <h2><i class="bi bi-trophy"></i> Activities Management</h2>
          <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addActivityModal">
            <i class="bi bi-plus-circle"></i> Add Activity
          </button>
        </div>

        <!-- Summary Cards -->
        <div class="row mb-4">
          <div class="col-md-3 mb-3">
            <div class="card shadow-sm bg-primary text-white">
              <div class="card-body text-center">
                <h6>Total Activities</h6>
                <h3 id="totalActivities">0</h3>
              </div>
            </div>
          </div>
          <div class="col-md-3 mb-3">
            <div class="card shadow-sm bg-success text-white">
              <div class="card-body text-center">
                <h6>Active</h6>
                <h3 id="activeActivities">0</h3>
              </div>
            </div>
          </div>
          <div class="col-md-3 mb-3">
            <div class="card shadow-sm bg-warning text-white">
              <div class="card-body text-center">
                <h6>Upcoming</h6>
                <h3 id="upcomingActivities">0</h3>
              </div>
            </div>
          </div>
          <div class="col-md-3 mb-3">
            <div class="card shadow-sm bg-info text-white">
              <div class="card-body text-center">
                <h6>Total Participants</h6>
                <h3 id="totalParticipants">0</h3>
              </div>
            </div>
          </div>
        </div>
              <input type="date" class="form-control" id="activityDate" required>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Participants*</label>
              <input type="number" class="form-control" id="activityParticipants" required>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Status*</label>
            <select class="form-select" id="activityStatus" required>
              <option value="Planning">Planning</option>
              <option value="Scheduled">Scheduled</option>
              <option value="In Progress">In Progress</option>
              <option value="Completed">Completed</option>
              <option value="Cancelled">Cancelled</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea class="form-control" id="activityDescription" rows="3"></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" id="saveActivityBtn">Save Activity</button>
      </div>
    </div>
  </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="/js/pages/manage_activities.js"></script>
