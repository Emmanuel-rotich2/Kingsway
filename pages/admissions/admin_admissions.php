<?php
/**
 * Admin Admissions Template
 * Full 7-stage workflow access for Director, Headteacher, System Admin
 * 
 * Features:
 * - Complete workflow management
 * - All statistics visible
 * - Bulk operations
 * - Application approval/rejection
 * - Reports and analytics
 */

$pageTitle = "Admissions Management";
$pageIcon = "bi-person-plus";
$pageScripts = ['js/pages/admissions.js'];
$roleCategory = 'admin';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - Kingsway Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../css/school-theme.css" rel="stylesheet">
    <link href="../css/roles/admin-theme.css" rel="stylesheet">
</head>

<body>
    <?php include '../layouts/app_layout.php'; ?>

    <div class="main-content" style="margin-left: 280px; padding: 20px;">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1"><i class="bi bi-person-plus me-2"></i>Admissions Management</h2>
                <p class="text-muted mb-0">Full 7-stage admission workflow control</p>
            </div>
            <div class="btn-group">
                <button class="btn btn-primary" id="newApplicationBtn">
                    <i class="bi bi-plus-lg me-1"></i> New Application
                </button>
                <button class="btn btn-success" id="bulkApproveBtn">
                    <i class="bi bi-check-all me-1"></i> Bulk Approve
                </button>
                <button class="btn btn-outline-secondary" id="exportReportBtn">
                    <i class="bi bi-download me-1"></i> Export
                </button>
                <button class="btn btn-outline-secondary" id="refreshBtn">
                    <i class="bi bi-arrow-clockwise"></i>
                </button>
            </div>
        </div>

        <!-- 7-Stage Workflow Overview -->
        <div class="card mb-4">
            <div class="card-header bg-light">
                <h6 class="mb-0"><i class="bi bi-diagram-3 me-2"></i>Admission Workflow Pipeline</h6>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col">
                        <div class="workflow-stage active">
                            <i class="bi bi-file-earmark-plus display-6 text-primary"></i>
                            <h4 id="stage1Count">0</h4>
                            <small>Application</small>
                        </div>
                    </div>
                    <div class="col">
                        <div class="workflow-stage">
                            <i class="bi bi-file-earmark-check display-6 text-warning"></i>
                            <h4 id="stage2Count">0</h4>
                            <small>Documents</small>
                        </div>
                    </div>
                    <div class="col">
                        <div class="workflow-stage">
                            <i class="bi bi-calendar-event display-6 text-info"></i>
                            <h4 id="stage3Count">0</h4>
                            <small>Interview</small>
                        </div>
                    </div>
                    <div class="col">
                        <div class="workflow-stage">
                            <i class="bi bi-clipboard-check display-6 text-purple"></i>
                            <h4 id="stage4Count">0</h4>
                            <small>Assessment</small>
                        </div>
                    </div>
                    <div class="col">
                        <div class="workflow-stage">
                            <i class="bi bi-envelope-check display-6 text-success"></i>
                            <h4 id="stage5Count">0</h4>
                            <small>Placement</small>
                        </div>
                    </div>
                    <div class="col">
                        <div class="workflow-stage">
                            <i class="bi bi-cash-stack display-6 text-warning"></i>
                            <h4 id="stage6Count">0</h4>
                            <small>Payment</small>
                        </div>
                    </div>
                    <div class="col">
                        <div class="workflow-stage">
                            <i class="bi bi-person-check display-6 text-dark"></i>
                            <h4 id="stage7Count">0</h4>
                            <small>Enrolled</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <i class="bi bi-people display-6"></i>
                        <h3 id="totalApplications">0</h3>
                        <small>Total Applications</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-dark">
                    <div class="card-body text-center">
                        <i class="bi bi-hourglass-split display-6"></i>
                        <h3 id="pendingApplications">0</h3>
                        <small>Pending Review</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <i class="bi bi-check-circle display-6"></i>
                        <h3 id="approvedApplications">0</h3>
                        <small>Approved</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-danger text-white">
                    <div class="card-body text-center">
                        <i class="bi bi-x-circle display-6"></i>
                        <h3 id="rejectedApplications">0</h3>
                        <small>Rejected</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Applications by Grade</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="applicationsByGradeChart" height="200"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Monthly Admission Trends</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="admissionTrendsChart" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <input type="text" class="form-control" id="searchApplication"
                            placeholder="Search by name or application ID...">
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" id="stageFilter">
                            <option value="">All Stages</option>
                            <option value="1">Application</option>
                            <option value="2">Documents</option>
                            <option value="3">Interview</option>
                            <option value="4">Assessment</option>
                            <option value="5">Placement</option>
                            <option value="6">Payment</option>
                            <option value="7">Enrolled</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" id="gradeFilter">
                            <option value="">All Grades</option>
                            <option value="ECD">ECD</option>
                            <option value="PP1">PP1</option>
                            <option value="PP2">PP2</option>
                            <option value="Grade 1">Grade 1</option>
                            <option value="Grade 2">Grade 2</option>
                            <option value="Grade 3">Grade 3</option>
                            <option value="Grade 4">Grade 4</option>
                            <option value="Grade 5">Grade 5</option>
                            <option value="Grade 6">Grade 6</option>
                            <option value="Grade 7">Grade 7</option>
                            <option value="Grade 8">Grade 8</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" id="statusFilter">
                            <option value="">All Status</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                            <option value="withdrawn">Withdrawn</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" id="yearFilter">
                            <option value="">All Years</option>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <button class="btn btn-outline-primary w-100" id="applyFiltersBtn">
                            <i class="bi bi-funnel"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Applications Table -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">All Applications</h6>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="selectAllApps">
                    <label class="form-check-label" for="selectAllApps">Select All</label>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="applicationsTable">
                        <thead class="table-light">
                            <tr>
                                <th width="40"><input type="checkbox" class="form-check-input"></th>
                                <th>App ID</th>
                                <th>Student Name</th>
                                <th>DOB</th>
                                <th>Grade Applied</th>
                                <th>Parent/Guardian</th>
                                <th>Contact</th>
                                <th>Stage</th>
                                <th>Status</th>
                                <th>Applied Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Dynamic content -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <nav class="mt-3">
            <ul class="pagination justify-content-center" id="applicationsPagination">
            </ul>
        </nav>
    </div>

    <!-- New Application Modal -->
    <div class="modal fade" id="newApplicationModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>New Admission Application</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="applicationForm">
                        <!-- Student Information -->
                        <h6 class="border-bottom pb-2 mb-3">Student Information</h6>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">First Name *</label>
                                <input type="text" class="form-control" name="first_name" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Middle Name</label>
                                <input type="text" class="form-control" name="middle_name">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Last Name *</label>
                                <input type="text" class="form-control" name="last_name" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Date of Birth *</label>
                                <input type="date" class="form-control" name="dob" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Gender *</label>
                                <select class="form-select" name="gender" required>
                                    <option value="">Select</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Grade Applying For *</label>
                                <select class="form-select" name="grade_applied" required>
                                    <option value="">Select Grade</option>
                                    <option value="ECD">ECD</option>
                                    <option value="PP1">PP1</option>
                                    <option value="PP2">PP2</option>
                                    <option value="Grade 1">Grade 1</option>
                                    <option value="Grade 2">Grade 2</option>
                                    <option value="Grade 3">Grade 3</option>
                                    <option value="Grade 4">Grade 4</option>
                                    <option value="Grade 5">Grade 5</option>
                                    <option value="Grade 6">Grade 6</option>
                                    <option value="Grade 7">Grade 7</option>
                                    <option value="Grade 8">Grade 8</option>
                                </select>
                            </div>
                        </div>

                        <!-- Parent/Guardian Information -->
                        <h6 class="border-bottom pb-2 mb-3 mt-4">Parent/Guardian Information</h6>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Parent/Guardian Name *</label>
                                <input type="text" class="form-control" name="guardian_name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Relationship *</label>
                                <select class="form-select" name="relationship" required>
                                    <option value="">Select</option>
                                    <option value="Father">Father</option>
                                    <option value="Mother">Mother</option>
                                    <option value="Guardian">Guardian</option>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Phone Number *</label>
                                <input type="tel" class="form-control" name="phone" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Occupation</label>
                                <input type="text" class="form-control" name="occupation">
                            </div>
                        </div>

                        <!-- Additional Information -->
                        <h6 class="border-bottom pb-2 mb-3 mt-4">Additional Information</h6>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Previous School</label>
                                <input type="text" class="form-control" name="previous_school">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Boarding Status *</label>
                                <select class="form-select" name="boarding_status" required>
                                    <option value="day">Day Scholar</option>
                                    <option value="boarding">Boarder</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Special Requirements / Medical Conditions</label>
                            <textarea class="form-control" name="special_requirements" rows="2"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveApplicationBtn">
                        <i class="bi bi-save me-1"></i> Submit Application
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Application Details Modal -->
    <div class="modal fade" id="applicationDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Application Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="applicationDetailsContent">
                    <!-- Dynamic content -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" id="approveAppBtn">
                        <i class="bi bi-check-lg me-1"></i> Approve
                    </button>
                    <button type="button" class="btn btn-danger" id="rejectAppBtn">
                        <i class="bi bi-x-lg me-1"></i> Reject
                    </button>
                    <button type="button" class="btn btn-primary" id="advanceStageBtn">
                        <i class="bi bi-arrow-right me-1"></i> Advance Stage
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../js/api.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            console.log('Admin Admissions Dashboard loaded');
            // Initialize admissions controller
            if (typeof admissionsController !== 'undefined') {
                admissionsController.init('admin');
            }
        });
    </script>
</body>

</html>