<?php
/**
 * Viewer Boarding Template
 * For Parents, Students - View own child's boarding status
 * 
 * Features:
 * - View child's dormitory assignment
 * - Check attendance status
 * - Submit leave requests
 * - View boarding schedule
 * - Contact dormitory staff
 */

$pageTitle = "Boarding Status";
$pageIcon = "bi-house";
$pageScripts = ['js/pages/boarding.js'];
$roleCategory = 'viewer';
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
    <link href="../css/roles/viewer-theme.css" rel="stylesheet">
</head>
<body>
    <?php include '../layouts/app_layout.php'; ?>

    <div class="main-content viewer-layout">
        <div class="container py-4">
            <!-- Page Header -->
            <div class="text-center mb-4">
                <i class="bi bi-house display-3 text-primary"></i>
                <h2 class="mt-2">Boarding Status</h2>
                <p class="text-muted">View your child's boarding information</p>
            </div>

            <!-- Child Selector (for parents with multiple children) -->
            <div class="row justify-content-center mb-4">
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <label class="form-label">Select Child:</label>
                            <select class="form-select" id="childSelector">
                                <option value="">-- Select Child --</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Boarding Information Card -->
            <div class="row justify-content-center mb-4">
                <div class="col-md-8">
                    <div class="card shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="bi bi-house me-2"></i>Dormitory Assignment</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <p class="mb-1"><strong>Student Name:</strong></p>
                                    <p class="text-muted" id="studentName">-</p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <p class="mb-1"><strong>Class:</strong></p>
                                    <p class="text-muted" id="studentClass">-</p>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <p class="mb-1"><strong>Dormitory:</strong></p>
                                    <p class="text-muted" id="dormitoryName">-</p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <p class="mb-1"><strong>Bed Number:</strong></p>
                                    <p class="text-muted" id="bedNumber">-</p>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <p class="mb-1"><strong>Matron/Patron:</strong></p>
                                    <p class="text-muted" id="patronName">-</p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <p class="mb-1"><strong>Contact:</strong></p>
                                    <p class="text-muted" id="patronContact">-</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Current Status Card -->
            <div class="row justify-content-center mb-4">
                <div class="col-md-8">
                    <div class="card shadow-sm">
                        <div class="card-header bg-success text-white">
                            <h6 class="mb-0"><i class="bi bi-check-circle me-2"></i>Current Status</h6>
                        </div>
                        <div class="card-body text-center">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="p-3">
                                        <i class="bi bi-calendar-check display-5 text-success"></i>
                                        <h5 class="mt-2" id="attendanceStatus">Present</h5>
                                        <small class="text-muted">Today's Status</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-3">
                                        <i class="bi bi-heart-pulse display-5 text-info"></i>
                                        <h5 class="mt-2" id="healthStatus">Good</h5>
                                        <small class="text-muted">Health</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-3">
                                        <i class="bi bi-clock display-5 text-primary"></i>
                                        <h5 class="mt-2" id="lastRollCall">-</h5>
                                        <small class="text-muted">Last Roll Call</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Leave Request Section -->
            <div class="row justify-content-center mb-4">
                <div class="col-md-8">
                    <div class="card shadow-sm">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><i class="bi bi-calendar-x me-2"></i>Leave Requests</h6>
                            <button class="btn btn-sm btn-primary" id="requestLeaveBtn">
                                <i class="bi bi-plus me-1"></i> Request Leave
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm" id="leaveHistoryTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th>From</th>
                                            <th>To</th>
                                            <th>Reason</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted">No leave requests</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Boarding Schedule -->
            <div class="row justify-content-center mb-4">
                <div class="col-md-8">
                    <div class="card shadow-sm">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-clock-history me-2"></i>Daily Schedule</h6>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                <div class="list-group-item d-flex justify-content-between">
                                    <span><i class="bi bi-sunrise text-warning me-2"></i>Wake Up</span>
                                    <span class="badge bg-secondary">5:30 AM</span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between">
                                    <span><i class="bi bi-clipboard-check text-success me-2"></i>Morning Roll Call</span>
                                    <span class="badge bg-secondary">6:00 AM</span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between">
                                    <span><i class="bi bi-cup-hot text-info me-2"></i>Breakfast</span>
                                    <span class="badge bg-secondary">6:30 AM</span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between">
                                    <span><i class="bi bi-book text-primary me-2"></i>Prep Time</span>
                                    <span class="badge bg-secondary">7:00 PM - 9:00 PM</span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between">
                                    <span><i class="bi bi-clipboard-check text-success me-2"></i>Evening Roll Call</span>
                                    <span class="badge bg-secondary">9:00 PM</span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between">
                                    <span><i class="bi bi-moon text-dark me-2"></i>Lights Out</span>
                                    <span class="badge bg-secondary">10:00 PM</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contact Information -->
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card bg-light">
                        <div class="card-body text-center">
                            <h6>Need to Contact Boarding Staff?</h6>
                            <p class="text-muted mb-2">For urgent matters, please contact:</p>
                            <p class="mb-0">
                                <i class="bi bi-telephone me-1"></i> Boarding Master: +254 XXX XXX XXX
                                <span class="mx-3">|</span>
                                <i class="bi bi-envelope me-1"></i> boarding@kingsway.ac.ke
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Request Leave Modal -->
    <div class="modal fade" id="requestLeaveModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-calendar-plus me-2"></i>Request Leave</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="leaveRequestForm">
                        <div class="mb-3">
                            <label class="form-label">Leave Type *</label>
                            <select class="form-select" name="leave_type" required>
                                <option value="">Select Type</option>
                                <option value="weekend">Weekend Exeat</option>
                                <option value="holiday">Holiday</option>
                                <option value="medical">Medical Leave</option>
                                <option value="family">Family Emergency</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">From Date *</label>
                                <input type="date" class="form-control" name="from_date" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">To Date *</label>
                                <input type="date" class="form-control" name="to_date" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Reason *</label>
                            <textarea class="form-control" name="reason" rows="3" required 
                                      placeholder="Please provide a detailed reason..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Who Will Pick Up the Student? *</label>
                            <input type="text" class="form-control" name="pickup_person" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Contact Number *</label>
                            <input type="tel" class="form-control" name="pickup_contact" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="submitLeaveRequestBtn">
                        <i class="bi bi-send me-1"></i> Submit Request
                    </button>
                </div>
            </div>
        </div>
    </div>

    <style>
        .viewer-layout {
            padding: 0;
            min-height: 100vh;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/api.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Viewer Boarding Dashboard loaded');
            // Initialize boarding controller
            if (typeof boardingController !== 'undefined') {
                boardingController.init('viewer');
            }
        });
    </script>
</body>
</html>
