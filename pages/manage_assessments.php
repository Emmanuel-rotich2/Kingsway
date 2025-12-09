<?php
// filepath: /home/prof_angera/Projects/php_pages/Kingsway/pages/manage_assessments.php
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
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAssessmentModal">
      <i class="bi bi-plus-circle"></i> Create Assessment
    </button>
  </h2>
  
  <div class="row mb-4">
    <div class="col-md-3">
      <div class="card bg-primary text-white">
        <div class="card-body">
          <h6>Total Assessments</h6>
          <h3>24</h3>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card bg-success text-white">
        <div class="card-body">
          <h6>Completed</h6>
          <h3>18</h3>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card bg-warning text-white">
        <div class="card-body">
          <h6>In Progress</h6>
          <h3>4</h3>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card bg-info text-white">
        <div class="card-body">
          <h6>Scheduled</h6>
          <h3>2</h3>
        </div>
      </div>
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
