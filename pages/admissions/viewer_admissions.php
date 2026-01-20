<?php
/**
 * Viewer Admissions Template
 * For Parents, Students - Application status check only
 * 
 * Features:
 * - View own application status
 * - Track admission progress
 * - See required next steps
 * - Download admission documents
 */

$pageTitle = "Application Status";
$pageIcon = "bi-person-plus";
$pageScripts = ['js/pages/admissions.js'];
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
                <i class="bi bi-mortarboard display-3 text-primary"></i>
                <h2 class="mt-2">Admission Application Status</h2>
                <p class="text-muted">Track your child's admission progress</p>
            </div>

            <!-- Application Status Card -->
            <div class="row justify-content-center mb-4">
                <div class="col-md-8">
                    <div class="card shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="bi bi-file-earmark-text me-2"></i>Application Details</h5>
                                <span class="badge bg-light text-dark" id="applicationId">Loading...</span>
                            </div>
                        </div>
                        <div class="card-body">
                            <!-- Student Info -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Student Name:</strong></p>
                                    <p class="text-muted" id="studentName">-</p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Grade Applied:</strong></p>
                                    <p class="text-muted" id="gradeApplied">-</p>
                                </div>
                            </div>
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Application Date:</strong></p>
                                    <p class="text-muted" id="applicationDate">-</p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Academic Year:</strong></p>
                                    <p class="text-muted" id="academicYear">-</p>
                                </div>
                            </div>

                            <!-- Current Status -->
                            <div class="alert alert-info d-flex align-items-center" id="currentStatusAlert">
                                <i class="bi bi-info-circle-fill me-3 fs-4"></i>
                                <div>
                                    <strong>Current Status:</strong>
                                    <span id="currentStatus">Loading...</span>
                                </div>
                            </div>

                            <!-- Progress Tracker -->
                            <h6 class="border-bottom pb-2 mb-3">Application Progress</h6>
                            <div class="progress-tracker">
                                <div class="step completed" data-step="1">
                                    <div class="step-icon">
                                        <i class="bi bi-check-lg"></i>
                                    </div>
                                    <div class="step-label">Application</div>
                                </div>
                                <div class="step" data-step="2">
                                    <div class="step-icon">
                                        <i class="bi bi-file-earmark"></i>
                                    </div>
                                    <div class="step-label">Documents</div>
                                </div>
                                <div class="step" data-step="3">
                                    <div class="step-icon">
                                        <i class="bi bi-calendar"></i>
                                    </div>
                                    <div class="step-label">Interview</div>
                                </div>
                                <div class="step" data-step="4">
                                    <div class="step-icon">
                                        <i class="bi bi-clipboard"></i>
                                    </div>
                                    <div class="step-label">Assessment</div>
                                </div>
                                <div class="step" data-step="5">
                                    <div class="step-icon">
                                        <i class="bi bi-envelope"></i>
                                    </div>
                                    <div class="step-label">Offer</div>
                                </div>
                                <div class="step" data-step="6">
                                    <div class="step-icon">
                                        <i class="bi bi-cash"></i>
                                    </div>
                                    <div class="step-label">Payment</div>
                                </div>
                                <div class="step" data-step="7">
                                    <div class="step-icon">
                                        <i class="bi bi-person-check"></i>
                                    </div>
                                    <div class="step-label">Enrolled</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Required Actions Card -->
            <div class="row justify-content-center mb-4">
                <div class="col-md-8">
                    <div class="card shadow-sm" id="requiredActionsCard">
                        <div class="card-header bg-warning text-dark">
                            <h6 class="mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Required Actions</h6>
                        </div>
                        <div class="card-body">
                            <ul class="list-group list-group-flush" id="requiredActionsList">
                                <!-- Dynamic content -->
                                <li class="list-group-item text-muted">No pending actions at this time</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Documents Section -->
            <div class="row justify-content-center mb-4">
                <div class="col-md-8">
                    <div class="card shadow-sm">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="mb-0"><i class="bi bi-folder me-2"></i>Documents</h6>
                                <button class="btn btn-sm btn-outline-primary" id="uploadDocumentBtn">
                                    <i class="bi bi-upload me-1"></i> Upload
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm" id="documentsTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Document</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>Birth Certificate</td>
                                            <td><span class="badge bg-success">Uploaded</span></td>
                                            <td><button class="btn btn-sm btn-link">View</button></td>
                                        </tr>
                                        <tr>
                                            <td>Previous School Records</td>
                                            <td><span class="badge bg-warning text-dark">Pending</span></td>
                                            <td><button class="btn btn-sm btn-primary">Upload</button></td>
                                        </tr>
                                        <tr>
                                            <td>Passport Photos</td>
                                            <td><span class="badge bg-success">Uploaded</span></td>
                                            <td><button class="btn btn-sm btn-link">View</button></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Interview Details (if scheduled) -->
            <div class="row justify-content-center mb-4" id="interviewSection" style="display: none;">
                <div class="col-md-8">
                    <div class="card shadow-sm border-info">
                        <div class="card-header bg-info text-white">
                            <h6 class="mb-0"><i class="bi bi-calendar-event me-2"></i>Interview Scheduled</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Date:</strong></p>
                                    <p class="text-muted" id="interviewDate">-</p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Time:</strong></p>
                                    <p class="text-muted" id="interviewTime">-</p>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Type:</strong></p>
                                    <p class="text-muted" id="interviewType">-</p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Location/Link:</strong></p>
                                    <p class="text-muted" id="interviewLocation">-</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contact Support -->
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card bg-light">
                        <div class="card-body text-center">
                            <h6>Need Help?</h6>
                            <p class="text-muted mb-2">Contact our admissions office for assistance</p>
                            <p class="mb-0">
                                <i class="bi bi-telephone me-1"></i> +254 XXX XXX XXX
                                <span class="mx-3">|</span>
                                <i class="bi bi-envelope me-1"></i> admissions@kingsway.ac.ke
                            </p>
                        </div>
                    </div>
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

        .progress-tracker {
            display: flex;
            justify-content: space-between;
            position: relative;
            padding: 20px 0;
        }

        .progress-tracker::before {
            content: '';
            position: absolute;
            top: 35px;
            left: 5%;
            right: 5%;
            height: 2px;
            background: #e9ecef;
        }

        .step {
            text-align: center;
            position: relative;
            z-index: 1;
            flex: 1;
        }

        .step-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            color: #6c757d;
            border: 2px solid #e9ecef;
        }

        .step.completed .step-icon {
            background: #198754;
            color: white;
            border-color: #198754;
        }

        .step.active .step-icon {
            background: #0d6efd;
            color: white;
            border-color: #0d6efd;
            animation: pulse 1.5s infinite;
        }

        .step-label {
            font-size: 12px;
            color: #6c757d;
        }

        .step.completed .step-label,
        .step.active .step-label {
            font-weight: 600;
            color: #212529;
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(13, 110, 253, 0.7);
            }

            70% {
                box-shadow: 0 0 0 10px rgba(13, 110, 253, 0);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(13, 110, 253, 0);
            }
        }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/api.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            console.log('Viewer Admissions Dashboard loaded');
            // Initialize admissions controller
            if (typeof admissionsController !== 'undefined') {
                admissionsController.init('viewer');
            }
        });
    </script>
</body>

</html>