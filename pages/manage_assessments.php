<?php
/**
 * Manage Assessments Page
 * 
 * Role-based access:
 * - Subject Teacher: Create/manage assessments for own subjects, enter marks
 * - Class Teacher: View class assessments, enter marks for own class
 * - HOD/Deputy Head Academic: Approve assessments, view all, generate reports
 * - Headteacher: View all, approve final results, publish
 * - Admin: Full access
 */
include __DIR__ . '/../components/tables/table.php';

// Example: Fetch assessments data
$assessmentHeaders = ['No', 'Assessment Name', 'Class', 'Subject', 'Date', 'Status'];
$assessmentRows = [
  [1, 'End Term 1 Exam 2025', 'Form 4', 'Mathematics', '2025-03-20', 'Scheduled'],
  [2, 'Mid Term Test', 'Grade 6', 'English', '2025-02-15', 'Completed'],
  [3, 'CAT 1', 'Form 2', 'Science', '2025-01-30', 'In Progress'],
  [4, 'Weekly Quiz', 'Grade 8', 'Kiswahili', '2025-01-25', 'Completed'],
];
$actionOptions = ['View', 'Edit', 'Enter Marks', 'Publish Results'];
?>

<div class="container mt-1">
  <h2 class="mb-4 d-flex justify-content-between align-items-center">
    <span><i class="bi bi-clipboard-check"></i> Assessments Management</span>
    <div class="btn-group">
      <!-- Create Assessment - Teachers and above -->
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAssessmentModal"
              data-permission="assessments_create"
              data-role="subject_teacher,class_teacher,deputy_head_academic,headteacher,admin">
        <i class="bi bi-plus-circle"></i> Create Assessment
      </button>
      <!-- Bulk Enter Marks - Teachers -->
      <button class="btn btn-outline-primary" onclick="assessmentsController.showBulkMarksModal()"
              data-permission="assessments_marks"
              data-role="subject_teacher,class_teacher,deputy_head_academic,admin">
        <i class="bi bi-pencil-square"></i> Enter Marks
      </button>
      <!-- Publish Results - Leadership only -->
      <button class="btn btn-outline-success" onclick="assessmentsController.showPublishModal()"
              data-permission="assessments_publish"
              data-role="deputy_head_academic,headteacher,admin">
        <i class="bi bi-check2-circle"></i> Publish Results
      </button>
      <!-- Export/Reports - Academic leadership -->
      <button class="btn btn-outline-secondary" onclick="assessmentsController.exportResults()"
              data-permission="assessments_export"
              data-role="deputy_head_academic,headteacher,director,admin">
        <i class="bi bi-download"></i> Export
      </button>
    </div>
  </h2>
  
  <div class="row mb-4">
    <div class="col-md-3">
      <div class="card bg-primary text-white">
        <div class="card-body">
          <h6>Total Assessments</h6>
          <h3 id="totalAssessments">24</h3>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card bg-success text-white">
        <div class="card-body">
          <h6>Completed</h6>
          <h3 id="completedAssessments">18</h3>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card bg-warning text-white">
        <div class="card-body">
          <h6>In Progress</h6>
          <h3 id="inProgressAssessments">4</h3>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card bg-info text-white">
        <div class="card-body">
          <h6>Scheduled</h6>
          <h3 id="scheduledAssessments">2</h3>
        </div>
      </div>
    </div>
  </div>

  <!-- Academic Leadership Stats - Deputy Head, Headteacher, Admin -->
  <div class="row mb-4" data-role="deputy_head_academic,headteacher,director,admin">
    <div class="col-md-3">
      <div class="card border-danger">
        <div class="card-body text-center">
          <h6 class="text-muted mb-2">Pending Approval</h6>
          <h3 class="text-danger mb-0" id="pendingApproval">0</h3>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card border-warning">
        <div class="card-body text-center">
          <h6 class="text-muted mb-2">Marks Not Entered</h6>
          <h3 class="text-warning mb-0" id="marksNotEntered">0</h3>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card border-success">
        <div class="card-body text-center">
          <h6 class="text-muted mb-2">Ready to Publish</h6>
          <h3 class="text-success mb-0" id="readyToPublish">0</h3>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card border-primary">
        <div class="card-body text-center">
          <h6 class="text-muted mb-2">Published This Term</h6>
          <h3 class="text-primary mb-0" id="publishedThisTerm">0</h3>
        </div>
      </div>
    </div>
  </div>

  <!-- Filter row - Subject teachers see subject filter, class teachers see class filter -->
  <div class="row mb-3">
    <div class="col-md-3">
      <input type="text" id="searchAssessment" class="form-control" placeholder="Search assessments...">
    </div>
    <!-- Class filter - Hidden for subject teachers teaching specific subjects -->
    <div class="col-md-2" id="classFilterContainer">
      <select id="classFilter" class="form-select">
        <option value="">All Classes</option>
      </select>
    </div>
    <!-- Subject filter - Hidden for class teachers -->
    <div class="col-md-2" data-role-exclude="class_teacher">
      <select id="subjectFilter" class="form-select">
        <option value="">All Subjects</option>
      </select>
    </div>
    <div class="col-md-2">
      <select id="statusFilter" class="form-select">
        <option value="">All Status</option>
        <option value="scheduled">Scheduled</option>
        <option value="in_progress">In Progress</option>
        <option value="completed">Completed</option>
        <option value="published">Published</option>
      </select>
    </div>
    <div class="col-md-2">
      <select id="termFilter" class="form-select">
        <option value="">All Terms</option>
        <option value="1">Term 1</option>
        <option value="2">Term 2</option>
        <option value="3">Term 3</option>
      </select>
    </div>
  </div>

  <ul class="nav nav-tabs mb-4" role="tablist">
    <li class="nav-item">
      <a class="nav-link active" data-bs-toggle="tab" href="#allAssessments">All Assessments</a>
    </li>
    <li class="nav-item">
      <a class="nav-link" data-bs-toggle="tab" href="#exams">Exams</a>
    </li>
    <li class="nav-item">
      <a class="nav-link" data-bs-toggle="tab" href="#tests">Tests</a>
    </li>
    <li class="nav-item">
      <a class="nav-link" data-bs-toggle="tab" href="#assignments">Assignments</a>
    </li>
    <!-- My Assessments Tab - Teachers only -->
    <li class="nav-item" data-role="subject_teacher,class_teacher,intern">
      <a class="nav-link" data-bs-toggle="tab" href="#myAssessments">My Assessments</a>
    </li>
    <!-- Pending Approval Tab - Academic leadership -->
    <li class="nav-item" data-role="deputy_head_academic,headteacher,admin">
      <a class="nav-link" data-bs-toggle="tab" href="#pendingApprovalTab">
        <i class="bi bi-hourglass-split"></i> Pending Approval
      </a>
    </li>
  </ul>

  <div class="tab-content">
    <div id="allAssessments" class="tab-pane fade show active">
      <?php renderTable($assessmentHeaders, $assessmentRows, $actionOptions, 'assessmentsTable'); ?>
    </div>
    <div id="exams" class="tab-pane fade">
      <p>Exams will appear here.</p>
    </div>
    <div id="tests" class="tab-pane fade">
      <p>Tests will appear here.</p>
    </div>
    <div id="assignments" class="tab-pane fade">
      <p>Assignments will appear here.</p>
    </div>
  </div>
</div>
