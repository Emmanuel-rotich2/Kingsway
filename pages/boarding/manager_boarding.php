<?php
/**
 * Manager Boarding Template
 * For Boarding Master, Matron, Deputy Head - Operational management
 * 
 * Features:
 * - Room assignments
 * - Roll call management
 * - Leave request processing
 * - Health issue tracking
 * - Student welfare monitoring
 */

$pageTitle = "Boarding Operations";
$pageIcon = "bi-house";
$pageScripts = ['js/pages/boarding.js'];
$roleCategory = 'manager';
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
    <link href="../css/roles/manager-theme.css" rel="stylesheet">
</head>
<body>
    <?php include '../layouts/app_layout.php'; ?>

    <div class="main-content manager-layout">
        <!-- Collapsible Sidebar Toggle -->
        <button class="btn btn-sm btn-outline-primary sidebar-toggle" id="sidebarToggle">
            <i class="bi bi-list"></i>
        </button>

        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1"><i class="bi bi-house me-2"></i>Boarding Operations</h2>
                <p class="text-muted mb-0">Manage daily boarding activities</p>
            </div>
            <div class="btn-group">
                <a href="boarding_roll_call.php" class="btn btn-success">
                    <i class="bi bi-clipboard-check me-1"></i> Take Roll Call
                </a>
                <button class="btn btn-primary" id="assignRoomBtn">
                    <i class="bi bi-door-open me-1"></i> Assign Room
                </button>
                <button class="btn btn-outline-secondary" id="refreshBtn">
                    <i class="bi bi-arrow-clockwise"></i>
                </button>
            </div>
        </div>

        <!-- Quick Actions Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-success h-100" role="button" id="rollCallCard">
                    <div class="card-body text-center">
                        <i class="bi bi-clipboard-check display-5 text-success"></i>
                        <h5 class="mt-2">Roll Call</h5>
                        <small class="text-muted">Today's Status</small>
                        <div class="mt-2">
                            <span class="badge bg-success" id="presentCount">0</span>
                            <span class="badge bg-danger" id="absentCount">0</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-warning h-100" role="button" id="leaveCard">
                    <div class="card-body text-center">
                        <i class="bi bi-calendar-x display-5 text-warning"></i>
                        <h5 class="mt-2">Leave Requests</h5>
                        <h3 class="text-warning" id="pendingLeaves">0</h3>
                        <small class="text-muted">Pending Approval</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-danger h-100" role="button" id="healthCard">
                    <div class="card-body text-center">
                        <i class="bi bi-heart-pulse display-5 text-danger"></i>
                        <h5 class="mt-2">Health Issues</h5>
                        <h3 class="text-danger" id="healthIssues">0</h3>
                        <small class="text-muted">Active Cases</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-info h-100" role="button" id="roomsCard">
                    <div class="card-body text-center">
                        <i class="bi bi-door-open display-5 text-info"></i>
                        <h5 class="mt-2">Room Status</h5>
                        <h3 class="text-info" id="availableRooms">0</h3>
                        <small class="text-muted">Available Beds</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- My Dormitory Section -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-house me-2"></i>My Dormitory: <span id="myDormitoryName">-</span></h6>
                    <div class="input-group" style="width: 300px;">
                        <input type="text" class="form-control form-control-sm" id="searchStudent" 
                               placeholder="Search student...">
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0" id="dormitoryStudentsTable">
                        <thead class="table-light">
                            <tr>
                                <th>Bed No</th>
                                <th>Student</th>
                                <th>Class</th>
                                <th>Status</th>
                                <th>Health</th>
                                <th>Last Roll Call</th>
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

        <!-- Today's Events -->
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-bell me-2"></i>Alerts & Notifications</h6>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush" id="alertsList">
                            <div class="list-group-item text-muted text-center">No alerts</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-calendar-event me-2"></i>Today's Schedule</h6>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush" id="scheduleList">
                            <div class="list-group-item d-flex justify-content-between">
                                <span>Morning Roll Call</span>
                                <span class="badge bg-secondary">6:00 AM</span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between">
                                <span>Evening Roll Call</span>
                                <span class="badge bg-secondary">9:00 PM</span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between">
                                <span>Lights Out</span>
                                <span class="badge bg-secondary">10:00 PM</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Assign Room Modal -->
    <div class="modal fade" id="assignRoomModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-door-open me-2"></i>Assign Room</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="assignRoomForm">
                        <div class="mb-3">
                            <label class="form-label">Select Student *</label>
                            <select class="form-select" name="student_id" required>
                                <option value="">Select Student</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Bed Number *</label>
                            <select class="form-select" name="bed_number" required>
                                <option value="">Select Available Bed</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" rows="2"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirmAssignBtn">
                        <i class="bi bi-check-lg me-1"></i> Assign
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Leave Request Modal -->
    <div class="modal fade" id="leaveRequestModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title"><i class="bi bi-calendar-x me-2"></i>Leave Requests</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-sm" id="leaveRequestsTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Student</th>
                                    <th>Type</th>
                                    <th>From</th>
                                    <th>To</th>
                                    <th>Reason</th>
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
        </div>
    </div>

    <!-- Health Issue Modal -->
    <div class="modal fade" id="healthIssueModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-heart-pulse me-2"></i>Report Health Issue</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="healthIssueForm">
                        <div class="mb-3">
                            <label class="form-label">Student *</label>
                            <select class="form-select" name="student_id" required>
                                <option value="">Select Student</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Issue Type *</label>
                            <select class="form-select" name="issue_type" required>
                                <option value="">Select Type</option>
                                <option value="illness">Illness</option>
                                <option value="injury">Injury</option>
                                <option value="allergy">Allergic Reaction</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Severity *</label>
                            <select class="form-select" name="severity" required>
                                <option value="low">Low - Minor</option>
                                <option value="medium">Medium - Requires Attention</option>
                                <option value="high">High - Urgent</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description *</label>
                            <textarea class="form-control" name="description" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Action Taken</label>
                            <textarea class="form-control" name="action_taken" rows="2"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="reportHealthBtn">
                        <i class="bi bi-send me-1"></i> Report
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/api.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Manager Boarding Dashboard loaded');
            // Initialize boarding controller
            if (typeof boardingController !== 'undefined') {
                boardingController.init('manager');
            }
        });
    </script>
</body>
</html>
