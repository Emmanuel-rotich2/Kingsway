<?php
/**
 * Admin Boarding Template
 * Full boarding management for Director, Headteacher, System Admin
 * 
 * Features:
 * - Complete dormitory management
 * - All statistics visible
 * - Room assignments and capacity
 * - Leave approval workflow
 * - Health and welfare monitoring
 * - Reports and analytics
 */

$pageTitle = "Boarding Management";
$pageIcon = "bi-house";
$pageScripts = ['js/pages/boarding.js'];
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
                <h2 class="mb-1"><i class="bi bi-house me-2"></i>Boarding Management</h2>
                <p class="text-muted mb-0">Complete boarding facility administration</p>
            </div>
            <div class="btn-group">
                <button class="btn btn-primary" id="addDormitoryBtn">
                    <i class="bi bi-plus-lg me-1"></i> Add Dormitory
                </button>
                <a href="boarding_roll_call.php" class="btn btn-success">
                    <i class="bi bi-clipboard-check me-1"></i> Roll Call
                </a>
                <button class="btn btn-warning" id="leaveRequestsBtn">
                    <i class="bi bi-calendar-x me-1"></i> Leave Requests
                    <span class="badge bg-danger" id="pendingLeaveCount">0</span>
                </button>
                <button class="btn btn-outline-secondary" id="exportReportBtn">
                    <i class="bi bi-download me-1"></i> Export
                </button>
            </div>
        </div>

        <!-- Summary Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <i class="bi bi-building display-6"></i>
                        <h3 id="totalCapacity">0</h3>
                        <small>Total Capacity</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <i class="bi bi-person-check display-6"></i>
                        <h3 id="occupiedBeds">0</h3>
                        <small>Occupied</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-dark">
                    <div class="card-body text-center">
                        <i class="bi bi-door-open display-6"></i>
                        <h3 id="availableBeds">0</h3>
                        <small>Available</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body text-center">
                        <i class="bi bi-percent display-6"></i>
                        <h3 id="occupancyRate">0%</h3>
                        <small>Occupancy Rate</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Health & Welfare Stats -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-danger">
                    <div class="card-body text-center">
                        <i class="bi bi-heart-pulse text-danger display-6"></i>
                        <h3 class="text-danger" id="healthIssues">0</h3>
                        <small class="text-muted">Health Issues</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-warning">
                    <div class="card-body text-center">
                        <i class="bi bi-calendar-x text-warning display-6"></i>
                        <h3 class="text-warning" id="onLeave">0</h3>
                        <small class="text-muted">On Leave</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-info">
                    <div class="card-body text-center">
                        <i class="bi bi-exclamation-triangle text-info display-6"></i>
                        <h3 class="text-info" id="disciplinaryCases">0</h3>
                        <small class="text-muted">Disciplinary Cases</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <i class="bi bi-check-circle text-success display-6"></i>
                        <h3 class="text-success" id="rollCallComplete">0%</h3>
                        <small class="text-muted">Today's Attendance</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Occupancy by Dormitory</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="occupancyChart" height="200"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Weekly Attendance Trend</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="attendanceTrendChart" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dormitories Table -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Dormitories</h6>
                <div class="input-group" style="width: 300px;">
                    <input type="text" class="form-control form-control-sm" id="searchDormitory" 
                           placeholder="Search dormitory...">
                    <button class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="dormitoriesTable">
                        <thead class="table-light">
                            <tr>
                                <th>Dormitory Name</th>
                                <th>Gender</th>
                                <th>Capacity</th>
                                <th>Occupied</th>
                                <th>Available</th>
                                <th>Matron/Patron</th>
                                <th>Status</th>
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

        <!-- Recent Activity -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-clock-history me-2"></i>Recent Activity</h6>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush" id="recentActivity">
                    <!-- Dynamic content -->
                </div>
            </div>
        </div>
    </div>

    <!-- Add Dormitory Modal -->
    <div class="modal fade" id="addDormitoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-house-add me-2"></i>Add Dormitory</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="dormitoryForm">
                        <div class="mb-3">
                            <label class="form-label">Dormitory Name *</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Gender *</label>
                                <select class="form-select" name="gender" required>
                                    <option value="">Select</option>
                                    <option value="male">Boys</option>
                                    <option value="female">Girls</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Capacity *</label>
                                <input type="number" class="form-control" name="capacity" min="1" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Matron/Patron</label>
                            <select class="form-select" name="patron_id">
                                <option value="">Select Staff</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Location/Block</label>
                            <input type="text" class="form-control" name="location">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="2"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveDormitoryBtn">
                        <i class="bi bi-save me-1"></i> Save
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Leave Requests Modal -->
    <div class="modal fade" id="leaveRequestsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title"><i class="bi bi-calendar-x me-2"></i>Pending Leave Requests</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-sm" id="leaveRequestsTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Student</th>
                                    <th>Dormitory</th>
                                    <th>Leave Type</th>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../js/api.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Admin Boarding Dashboard loaded');
            // Initialize boarding controller
            if (typeof boardingController !== 'undefined') {
                boardingController.init('admin');
            }
        });
    </script>
</body>
</html>
