<?php
/**
 * Manager Admissions Template
 * For Registrar, Deputy Head, Accountant - Department-specific workflow access
 * 
 * Features:
 * - Document verification (Registrar)
 * - Interview scheduling (Deputy Head)
 * - Payment recording (Accountant)
 * - Limited stage transitions
 */

$pageTitle = "Admissions Processing";
$pageIcon = "bi-person-plus";
$pageScripts = ['js/pages/admissions.js'];
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
                <h2 class="mb-1"><i class="bi bi-person-plus me-2"></i>Admissions Processing</h2>
                <p class="text-muted mb-0">Process applications in your responsibility area</p>
            </div>
            <div class="btn-group">
                <button class="btn btn-primary" id="newApplicationBtn">
                    <i class="bi bi-plus-lg me-1"></i> New Application
                </button>
                <button class="btn btn-outline-secondary" id="refreshBtn">
                    <i class="bi bi-arrow-clockwise"></i>
                </button>
            </div>
        </div>

        <!-- Role-Specific Stats -->
        <div class="row mb-4">
            <!-- Documents Pending - For Registrar -->
            <div class="col-md-4" id="documentsCard">
                <div class="card border-warning">
                    <div class="card-body text-center">
                        <i class="bi bi-file-earmark-text display-5 text-warning"></i>
                        <h3 id="documentsPending">0</h3>
                        <small class="text-muted">Documents Pending Verification</small>
                        <button class="btn btn-sm btn-outline-warning mt-2 w-100" id="viewDocumentsBtn">
                            View Documents Queue
                        </button>
                    </div>
                </div>
            </div>
            <!-- Interview Pending - For Deputy Head -->
            <div class="col-md-4" id="interviewsCard">
                <div class="card border-info">
                    <div class="card-body text-center">
                        <i class="bi bi-calendar-event display-5 text-info"></i>
                        <h3 id="interviewsPending">0</h3>
                        <small class="text-muted">Interviews to Schedule</small>
                        <button class="btn btn-sm btn-outline-info mt-2 w-100" id="viewInterviewsBtn">
                            View Interview Queue
                        </button>
                    </div>
                </div>
            </div>
            <!-- Payments Pending - For Accountant -->
            <div class="col-md-4" id="paymentsCard">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <i class="bi bi-cash-stack display-5 text-success"></i>
                        <h3 id="paymentsPending">0</h3>
                        <small class="text-muted">Payments Pending</small>
                        <button class="btn btn-sm btn-outline-success mt-2 w-100" id="viewPaymentsBtn">
                            View Payment Queue
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- My Assigned Applications -->
        <div class="card mb-4">
            <div class="card-header bg-light">
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-inbox me-2"></i>My Processing Queue</h6>
                    <div class="input-group" style="width: 300px;">
                        <input type="text" class="form-control form-control-sm" id="searchApplication"
                            placeholder="Search applications...">
                        <button class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="myApplicationsTable">
                        <thead class="table-light">
                            <tr>
                                <th>App ID</th>
                                <th>Student Name</th>
                                <th>Grade</th>
                                <th>Current Stage</th>
                                <th>Assigned Date</th>
                                <th>Priority</th>
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

        <!-- Recent Processed -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-clock-history me-2"></i>Recently Processed</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0" id="recentProcessedTable">
                        <thead class="table-light">
                            <tr>
                                <th>App ID</th>
                                <th>Student Name</th>
                                <th>Action Taken</th>
                                <th>Date</th>
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

    <!-- Document Verification Modal -->
    <div class="modal fade" id="documentVerificationModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title"><i class="bi bi-file-earmark-check me-2"></i>Document Verification</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="documentsList">
                        <!-- Dynamic document list -->
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Verification Notes</label>
                        <textarea class="form-control" id="verificationNotes" rows="3"
                            placeholder="Add any notes about the documents..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" id="rejectDocumentsBtn">
                        <i class="bi bi-x-lg me-1"></i> Request Re-upload
                    </button>
                    <button type="button" class="btn btn-success" id="approveDocumentsBtn">
                        <i class="bi bi-check-lg me-1"></i> Verify & Advance
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Interview Scheduling Modal -->
    <div class="modal fade" id="interviewScheduleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title"><i class="bi bi-calendar-plus me-2"></i>Schedule Interview</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Interview Date *</label>
                        <input type="date" class="form-control" id="interviewDate" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Interview Time *</label>
                        <input type="time" class="form-control" id="interviewTime" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Interview Type</label>
                        <select class="form-select" id="interviewType">
                            <option value="in-person">In Person</option>
                            <option value="virtual">Virtual (Zoom/Meet)</option>
                            <option value="phone">Phone Call</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Interviewer</label>
                        <select class="form-select" id="interviewer">
                            <option value="">Select Interviewer</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" id="interviewNotes" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-info text-white" id="scheduleInterviewBtn">
                        <i class="bi bi-calendar-check me-1"></i> Schedule
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Recording Modal -->
    <div class="modal fade" id="paymentRecordModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="bi bi-cash-coin me-2"></i>Record Admission Payment</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <strong>Student:</strong> <span id="paymentStudentName"></span><br>
                        <strong>Required Amount:</strong> KES <span id="requiredAmount"></span>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Amount Paid (KES) *</label>
                        <input type="number" class="form-control" id="amountPaid" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payment Method *</label>
                        <select class="form-select" id="paymentMethod" required>
                            <option value="">Select</option>
                            <option value="cash">Cash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="mpesa">M-Pesa</option>
                            <option value="cheque">Cheque</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reference Number</label>
                        <input type="text" class="form-control" id="paymentReference">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payment Date *</label>
                        <input type="date" class="form-control" id="paymentDate" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="recordPaymentBtn">
                        <i class="bi bi-check-lg me-1"></i> Record Payment
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/api.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            console.log('Manager Admissions Dashboard loaded');
            // Initialize admissions controller
            if (typeof admissionsController !== 'undefined') {
                admissionsController.init('manager');
            }
        });
    </script>
</body>

</html>