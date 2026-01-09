<?php
/**
 * Activities Management - Role-Based Router
 * 
 * Routes to the appropriate template based on user's role category:
 * - admin: Full admin layout with all features
 * - manager: Compact layout with standard features
 * - operator: Minimal layout with essential features
 * - viewer: Read-only simple list view
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/permissions.php';

// Get current user's role
$userRole = $_SESSION['role'] ?? '';
$userId = $_SESSION['user_id'] ?? null;

// Determine role category using the helper function
$roleCategory = getRoleCategory($userRole);

// Map role category to template file
$templateMap = [
  'admin' => 'activities/admin_activities.php',
  'manager' => 'activities/manager_activities.php',
  'operator' => 'activities/operator_activities.php',
  'viewer' => 'activities/viewer_activities.php'
];

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
    <div class="container mt-3">
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
          <div class="card shadow-sm bg-info text-white">
            <div class="card-body text-center">
              <h6>Total Participants</h6>
              <h3 id="totalParticipants">0</h3>
            </div>
          </div>
        </div>
      </div>

      <!-- Tabs -->
      <ul class="nav nav-tabs mb-3">
        <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#allActivities">All Activities</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#sports">Sports</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#arts">Arts & Culture</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#clubs">Clubs</a></li>
      </ul>

      <!-- Tab Contents -->
      <div class="tab-content">
        <div id="allActivities" class="tab-pane fade show active">
          <div class="d-flex justify-content-between mb-2">
            <input type="text" class="form-control w-25" id="searchActivities" placeholder="Search activities...">
            <?php if (can($userRole, 'activities', 'export')): ?>
            <button class="btn btn-outline-secondary" id="exportActivities"><i class="bi bi-download"></i> Export</button>
            <?php endif; ?>
          </div>
          <div class="table-responsive">
            <table class="table table-hover" id="activitiesTable">
              <thead class="table-light">
                <tr>
                  <th>ID</th>
                  <th>Activity Name</th>
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
          <div id="sports" class="tab-pane fade">
            <p>Sports activities here...</p>
          </div>
          <div id="arts" class="tab-pane fade">
            <p>Arts & culture activities here...</p>
          </div>
          <div id="clubs" class="tab-pane fade">
            <p>Club activities here...</p>
          </div>
          </div>
          </div>

    <!-- Add/Edit Activity Modal -->
    <div class="modal fade" id="addActivityModal" tabindex="-1">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header bg-primary text-white">
            <h5 class="modal-title">Add/Edit Activity</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <form id="activityForm">
              <input type="hidden" id="activityId">
              <div class="row">
                <div class="col-md-6 mb-3">
                  <label class="form-label">Activity Name*</label>
                  <input type="text" class="form-control" id="activityName" required>
                </div>
                <div class="col-md-6 mb-3">
                  <label class="form-label">Category*</label>
                  <select class="form-select" id="activityCategory" required>
                    <option value="Sports">Sports</option>
                    <option value="Arts">Arts</option>
                    <option value="Academic">Academic</option>
                    <option value="Clubs">Clubs</option>
                  </select>
                </div>
              </div>
              <div class="row">
                <div class="col-md-6 mb-3">
                  <label class="form-label">Date*</label>
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
    <script src="/js/pages/manage_activities.js"></script>
    <?php
}

$content = ob_get_clean();
echo $content;