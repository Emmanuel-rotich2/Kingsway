<?php
// filepath: /home/prof_angera/Projects/php_pages/Kingsway/pages/manage_lesson_plans.php
// Lesson Plans Management - data loaded dynamically via JS controller
?>

<div>
  <h2 class="mb-4 d-flex justify-content-between align-items-center">
    <span><i class="bi bi-file-text"></i> Lesson Plans Management</span>
    <button class="btn btn-primary" id="btnCreateLessonPlan" data-bs-toggle="modal" data-bs-target="#addLessonPlanModal">
      <i class="bi bi-plus-circle"></i> Create Lesson Plan
    </button>
  </h2>
  
  <!-- Filter bar -->
  <div class="row mb-3">
    <div class="col-md-3">
      <select id="lpFilterClass" class="form-select form-select-sm">
        <option value="">All Classes</option>
      </select>
    </div>
    <div class="col-md-3">
      <select id="lpFilterStatus" class="form-select form-select-sm">
        <option value="">All Statuses</option>
        <option value="draft">Draft</option>
        <option value="submitted">Submitted</option>
        <option value="approved">Approved</option>
        <option value="completed">Completed</option>
      </select>
    </div>
    <div class="col-md-3">
      <input type="date" id="lpFilterFrom" class="form-control form-control-sm" placeholder="From date">
    </div>
    <div class="col-md-3">
      <input type="date" id="lpFilterTo" class="form-control form-control-sm" placeholder="To date">
    </div>
  </div>

  <!-- Summary cards - populated by JS -->
  <div class="row mb-4">
    <div class="col-md-3">
      <div class="card bg-primary text-white">
        <div class="card-body">
          <h6>Total Lesson Plans</h6>
          <h3 id="lpTotalCount">0</h3>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card bg-success text-white">
        <div class="card-body">
          <h6>Approved</h6>
          <h3 id="lpApprovedCount">0</h3>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card bg-warning text-white">
        <div class="card-body">
          <h6>Pending Review</h6>
          <h3 id="lpPendingCount">0</h3>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card bg-info text-white">
        <div class="card-body">
          <h6>Drafts</h6>
          <h3 id="lpDraftCount">0</h3>
        </div>
      </div>
    </div>
  </div>

  <ul class="nav nav-tabs mb-4" role="tablist">
    <li class="nav-item">
      <a class="nav-link active" data-bs-toggle="tab" href="#allLessons" data-filter="">All Lesson Plans</a>
    </li>
    <li class="nav-item">
      <a class="nav-link" data-bs-toggle="tab" href="#myLessons" data-filter="mine">My Lessons</a>
    </li>
    <li class="nav-item">
      <a class="nav-link" data-bs-toggle="tab" href="#pending" data-filter="submitted">Pending Review</a>
    </li>
    <li class="nav-item">
      <a class="nav-link" data-bs-toggle="tab" href="#approved" data-filter="approved">Approved</a>
    </li>
  </ul>

  <div class="tab-content">
    <div id="allLessons" class="tab-pane fade show active">
      <div class="table-responsive">
        <table class="table table-striped table-hover" id="lessonPlansTable">
          <thead>
            <tr>
              <th>#</th>
              <th>Lesson Title</th>
              <th>Subject</th>
              <th>Class</th>
              <th>Teacher</th>
              <th>Date</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td colspan="8" class="text-center text-muted py-4">Loading lesson plans...</td>
            </tr>
          </tbody>
        </table>
      </div>
      <nav>
        <ul class="pagination justify-content-center" id="lpPagination"></ul>
      </nav>
    </div>
    <div id="myLessons" class="tab-pane fade">
      <div class="table-responsive">
        <table class="table table-striped table-hover" id="myLessonPlansTable">
          <thead>
            <tr>
              <th>#</th>
              <th>Lesson Title</th>
              <th>Subject</th>
              <th>Class</th>
              <th>Date</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td colspan="7" class="text-center text-muted py-4">Select tab to load your lessons</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
    <div id="pending" class="tab-pane fade">
      <div class="table-responsive">
        <table class="table table-striped table-hover" id="pendingLessonPlansTable">
          <thead>
            <tr>
              <th>#</th>
              <th>Lesson Title</th>
              <th>Subject</th>
              <th>Class</th>
              <th>Teacher</th>
              <th>Date</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td colspan="7" class="text-center text-muted py-4">Select tab to load pending plans</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
    <div id="approved" class="tab-pane fade">
      <div class="table-responsive">
        <table class="table table-striped table-hover" id="approvedLessonPlansTable">
          <thead>
            <tr>
              <th>#</th>
              <th>Lesson Title</th>
              <th>Subject</th>
              <th>Class</th>
              <th>Teacher</th>
              <th>Date</th>
              <th>Approved By</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td colspan="7" class="text-center text-muted py-4">Select tab to load approved plans</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script src="js/pages/manage_lesson_plans.js"></script>