<?php
// filepath: /home/prof_angera/Projects/php_pages/Kingsway/pages/manage_workflows.php
include __DIR__ . '/../components/tables/table.php';

// Example: Fetch workflows data
$workflowHeaders = ['No', 'Workflow Name', 'Type', 'Status', 'Last Modified'];
$workflowRows = [
  [1, 'Student Admission Approval', 'Admission', 'Active', '2025-01-01'],
  [2, 'Staff Leave Request', 'HR', 'Active', '2025-01-05'],
  [3, 'Fee Payment Approval', 'Finance', 'Active', '2025-01-10'],
  [4, 'Exam Results Publication', 'Academic', 'Active', '2025-01-15'],
];
$actionOptions = ['View', 'Edit', 'Deactivate', 'Duplicate'];
?>

<div class="container mt-1">
  <h2 class="mb-4 d-flex justify-content-between align-items-center">
    <span><i class="bi bi-diagram-3"></i> Workflows Management</span>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addWorkflowModal">
      <i class="bi bi-plus-circle"></i> Create Workflow
    </button>
  </h2>
  
  <div class="row mb-4">
    <div class="col-md-3">
      <div class="card bg-primary text-white">
        <div class="card-body">
          <h6>Total Workflows</h6>
          <h3>4</h3>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card bg-success text-white">
        <div class="card-body">
          <h6>Active</h6>
          <h3>4</h3>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card bg-warning text-white">
        <div class="card-body">
          <h6>Pending Approvals</h6>
          <h3>12</h3>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card bg-info text-white">
        <div class="card-body">
          <h6>Completed Today</h6>
          <h3>8</h3>
        </div>
      </div>
    </div>
  </div>

  <ul class="nav nav-tabs mb-4" role="tablist">
    <li class="nav-item">
      <a class="nav-link active" data-bs-toggle="tab" href="#allWorkflows">All Workflows</a>
    </li>
    <li class="nav-item">
      <a class="nav-link" data-bs-toggle="tab" href="#pending">Pending</a>
    </li>
    <li class="nav-item">
      <a class="nav-link" data-bs-toggle="tab" href="#completed">Completed</a>
    </li>
  </ul>

  <div class="tab-content">
    <div id="allWorkflows" class="tab-pane fade show active">
      <?php renderTable($workflowHeaders, $workflowRows, $actionOptions, 'workflowsTable'); ?>
    </div>
    <div id="pending" class="tab-pane fade">
      <p>Pending workflow approvals will appear here.</p>
    </div>
    <div id="completed" class="tab-pane fade">
      <p>Completed workflows will appear here.</p>
    </div>
  </div>
</div>
