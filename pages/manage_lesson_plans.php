<?php
// filepath: /home/prof_angera/Projects/php_pages/Kingsway/pages/manage_lesson_plans.php
include __DIR__ . '/../components/tables/table.php';

// Example: Fetch lesson plans data
$lessonHeaders = ['No', 'Lesson Title', 'Subject', 'Class', 'Teacher', 'Date', 'Status'];
$lessonRows = [
  [1, 'Introduction to Algebra', 'Mathematics', 'Form 2', 'John Kamau', '2025-01-20', 'Approved'],
  [2, 'Photosynthesis Process', 'Science', 'Grade 6', 'Mary Wanjiru', '2025-01-22', 'Pending Review'],
  [3, 'Essay Writing', 'English', 'Form 4', 'Peter Ochieng', '2025-01-25', 'Approved'],
  [4, 'Kenya History', 'Social Studies', 'Grade 8', 'Jane Akinyi', '2025-01-28', 'Draft'],
];
$actionOptions = ['View', 'Edit', 'Approve', 'Duplicate'];
?>

<div class="container mt-1">
  <h2 class="mb-4 d-flex justify-content-between align-items-center">
    <span><i class="bi bi-file-text"></i> Lesson Plans Management</span>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addLessonPlanModal">
      <i class="bi bi-plus-circle"></i> Create Lesson Plan
    </button>
  </h2>
  
  <div class="row mb-4">
    <div class="col-md-3">
      <div class="card bg-primary text-white">
        <div class="card-body">
          <h6>Total Lesson Plans</h6>
          <h3>156</h3>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card bg-success text-white">
        <div class="card-body">
          <h6>Approved</h6>
          <h3>142</h3>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card bg-warning text-white">
        <div class="card-body">
          <h6>Pending Review</h6>
          <h3>8</h3>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card bg-info text-white">
        <div class="card-body">
          <h6>Drafts</h6>
          <h3>6</h3>
        </div>
      </div>
    </div>
  </div>

  <ul class="nav nav-tabs mb-4" role="tablist">
    <li class="nav-item">
      <a class="nav-link active" data-bs-toggle="tab" href="#allLessons">All Lesson Plans</a>
    </li>
    <li class="nav-item">
      <a class="nav-link" data-bs-toggle="tab" href="#myLessons">My Lessons</a>
    </li>
    <li class="nav-item">
      <a class="nav-link" data-bs-toggle="tab" href="#pending">Pending Review</a>
    </li>
    <li class="nav-item">
      <a class="nav-link" data-bs-toggle="tab" href="#approved">Approved</a>
    </li>
  </ul>

  <div class="tab-content">
    <div id="allLessons" class="tab-pane fade show active">
      <?php renderTable($lessonHeaders, $lessonRows, $actionOptions, 'lessonPlansTable'); ?>
    </div>
    <div id="myLessons" class="tab-pane fade">
      <p>Your lesson plans will appear here.</p>
    </div>
    <div id="pending" class="tab-pane fade">
      <p>Lesson plans pending review will appear here.</p>
    </div>
    <div id="approved" class="tab-pane fade">
      <p>Approved lesson plans will appear here.</p>
    </div>
  </div>
</div>
