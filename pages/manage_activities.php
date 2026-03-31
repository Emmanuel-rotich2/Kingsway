<?php
/**
 * Activities Management Page
 * API & JWT Mode — all data loaded client-side via manage_activities.js
 */
?>

<div class="container-fluid mt-3">

  <!-- Header -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h2 class="mb-0"><i class="bi bi-trophy-fill me-2 text-warning"></i>Activities Management</h2>
      <small class="text-muted">Manage school activities, participants, schedules and categories</small>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-outline-secondary btn-sm" onclick="activitiesController.loadAll()">
        <i class="bi bi-arrow-clockwise"></i> Refresh
      </button>
      <button class="btn btn-primary" onclick="activitiesController.showAddModal()">
        <i class="bi bi-plus-circle me-1"></i> Add Activity
      </button>
    </div>
  </div>

  <!-- Summary Cards -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-1 fw-bold text-primary" id="totalActivities">—</div>
          <div class="text-muted small">Total Activities</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-1 fw-bold text-success" id="activeActivities">—</div>
          <div class="text-muted small">Active</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-1 fw-bold text-warning" id="upcomingActivities">—</div>
          <div class="text-muted small">Upcoming</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-1 fw-bold text-info" id="totalParticipants">—</div>
          <div class="text-muted small">Participants</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Tabs -->
  <ul class="nav nav-tabs mb-3" id="activitiesTabs">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabActivities">Activities</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabCategories">Categories</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabParticipants">Participants</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabSchedules">Schedules</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabResources">Resources</button></li>
  </ul>

  <div class="tab-content">

    <!-- Activities Tab -->
    <div class="tab-pane fade show active" id="tabActivities">
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <!-- Filters -->
          <div class="row g-2 mb-3">
            <div class="col-md-3">
              <input type="text" class="form-control form-control-sm" id="searchActivity" placeholder="Search activities…">
            </div>
            <div class="col-md-2">
              <select class="form-select form-select-sm" id="filterCategory">
                <option value="">All Categories</option>
              </select>
            </div>
            <div class="col-md-2">
              <select class="form-select form-select-sm" id="filterStatus">
                <option value="">All Statuses</option>
                <option value="planning">Planning</option>
                <option value="scheduled">Scheduled</option>
                <option value="active">Active</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
              </select>
            </div>
            <div class="col-md-2">
              <button class="btn btn-outline-secondary btn-sm w-100" onclick="activitiesController.clearFilters()">Clear</button>
            </div>
          </div>
          <div id="activitiesTableContainer">
            <div class="text-center py-4"><div class="spinner-border text-primary"></div></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Categories Tab -->
    <div class="tab-pane fade" id="tabCategories">
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <div class="d-flex justify-content-between mb-3">
            <h6 class="mb-0">Activity Categories</h6>
            <button class="btn btn-sm btn-primary" onclick="activitiesController.showCategoryModal()">
              <i class="bi bi-plus"></i> Add Category
            </button>
          </div>
          <div id="categoriesContainer">
            <div class="text-center py-4"><div class="spinner-border text-primary"></div></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Participants Tab -->
    <div class="tab-pane fade" id="tabParticipants">
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <div class="row g-2 mb-3">
            <div class="col-md-4">
              <select class="form-select form-select-sm" id="filterParticipantActivity">
                <option value="">All Activities</option>
              </select>
            </div>
          </div>
          <div id="participantsContainer">
            <div class="text-center py-4"><div class="spinner-border text-primary"></div></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Schedules Tab -->
    <div class="tab-pane fade" id="tabSchedules">
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <div class="d-flex justify-content-between mb-3">
            <h6 class="mb-0">Activity Schedules</h6>
            <button class="btn btn-sm btn-primary" onclick="activitiesController.showScheduleModal()">
              <i class="bi bi-plus"></i> Add Schedule
            </button>
          </div>
          <div id="schedulesContainer">
            <div class="text-center py-4"><div class="spinner-border text-primary"></div></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Resources Tab -->
    <div class="tab-pane fade" id="tabResources">
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <div class="d-flex justify-content-between mb-3">
            <h6 class="mb-0">Activity Resources</h6>
            <button class="btn btn-sm btn-primary" onclick="activitiesController.showResourceModal()">
              <i class="bi bi-plus"></i> Add Resource
            </button>
          </div>
          <div id="resourcesContainer">
            <div class="text-center py-4"><div class="spinner-border text-primary"></div></div>
          </div>
        </div>
      </div>
    </div>

  </div><!-- /tab-content -->
</div><!-- /container-fluid -->

<!-- Add/Edit Activity Modal -->
<div class="modal fade" id="activityModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="activityModalTitle">Add Activity</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="activityId">
        <div class="row g-3">
          <div class="col-md-8">
            <label class="form-label fw-semibold">Activity Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="activityName" required>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Category</label>
            <select class="form-select" id="activityCategory">
              <option value="">Select Category</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Start Date</label>
            <input type="date" class="form-control" id="activityStartDate">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">End Date</label>
            <input type="date" class="form-control" id="activityEndDate">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Venue</label>
            <input type="text" class="form-control" id="activityVenue" placeholder="Location">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Status</label>
            <select class="form-select" id="activityStatus">
              <option value="planning">Planning</option>
              <option value="scheduled">Scheduled</option>
              <option value="active">Active</option>
              <option value="completed">Completed</option>
              <option value="cancelled">Cancelled</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Max Participants</label>
            <input type="number" class="form-control" id="activityMaxParticipants" min="1">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Teacher In Charge</label>
            <input type="text" class="form-control" id="activityTeacher" placeholder="Staff name or ID">
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Description</label>
            <textarea class="form-control" id="activityDescription" rows="3"></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" onclick="activitiesController.saveActivity()">Save Activity</button>
      </div>
    </div>
  </div>
</div>

<!-- Category Modal -->
<div class="modal fade" id="categoryModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="categoryModalTitle">Add Category</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="categoryId">
        <div class="mb-3">
          <label class="form-label fw-semibold">Name <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="categoryName" required>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Description</label>
          <textarea class="form-control" id="categoryDescription" rows="2"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" onclick="activitiesController.saveCategory()">Save Category</button>
      </div>
    </div>
  </div>
</div>

<!-- Participant Register Modal -->
<div class="modal fade" id="participantModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Register Participant</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label fw-semibold">Activity</label>
          <select class="form-select" id="participantActivity"></select>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Student ID</label>
          <input type="number" class="form-control" id="participantStudentId" placeholder="Student ID">
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Role</label>
          <input type="text" class="form-control" id="participantRole" placeholder="e.g. Player, Captain…">
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" onclick="activitiesController.saveParticipant()">Register</button>
      </div>
    </div>
  </div>
</div>

<!-- Schedule Modal -->
<div class="modal fade" id="scheduleModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="scheduleModalTitle">Add Schedule</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="scheduleId">
        <div class="mb-3">
          <label class="form-label fw-semibold">Activity</label>
          <select class="form-select" id="scheduleActivity"></select>
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label fw-semibold">Date</label>
            <input type="date" class="form-control" id="scheduleDate">
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold">Start Time</label>
            <input type="time" class="form-control" id="scheduleStartTime">
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold">End Time</label>
            <input type="time" class="form-control" id="scheduleEndTime">
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Venue</label>
            <input type="text" class="form-control" id="scheduleVenue">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" onclick="activitiesController.saveSchedule()">Save</button>
      </div>
    </div>
  </div>
</div>

<!-- Resource Modal -->
<div class="modal fade" id="resourceModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="resourceModalTitle">Add Resource</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="resourceId">
        <div class="mb-3">
          <label class="form-label fw-semibold">Activity</label>
          <select class="form-select" id="resourceActivity"></select>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Resource Name <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="resourceName" required>
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label fw-semibold">Type</label>
            <select class="form-select" id="resourceType">
              <option value="equipment">Equipment</option>
              <option value="facility">Facility</option>
              <option value="material">Material</option>
              <option value="personnel">Personnel</option>
              <option value="other">Other</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Quantity</label>
            <input type="number" class="form-control" id="resourceQuantity" min="1" value="1">
          </div>
        </div>
        <div class="mt-3">
          <label class="form-label fw-semibold">Notes</label>
          <textarea class="form-control" id="resourceNotes" rows="2"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" onclick="activitiesController.saveResource()">Save</button>
      </div>
    </div>
  </div>
</div>

<script src="<?= $appBase ?>/js/pages/manage_activities.js?v=<?= time() ?>"></script>
