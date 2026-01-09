<?php
/**
 * Operator Admissions Template
 * For Teachers, Staff - Limited visibility into admissions
 * 
 * Features:
 * - View upcoming new students for their classes
 * - See basic admission information
 * - No workflow modification capabilities
 */

$pageTitle = "Incoming Students";
$pageIcon = "bi-person-plus";
$pageScripts = ['js/pages/admissions.js'];
$roleCategory = 'operator';
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
    <link href="../css/roles/operator-theme.css" rel="stylesheet">
</head>

<body>
    <?php include '../layouts/app_layout.php'; ?>

    <div class="main-content operator-layout">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1"><i class="bi bi-person-plus me-2"></i>Incoming Students</h2>
                <p class="text-muted mb-0">View new students joining your classes</p>
            </div>
            <button class="btn btn-outline-secondary" id="refreshBtn">
                <i class="bi bi-arrow-clockwise"></i> Refresh
            </button>
        </div>

        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card border-primary">
                    <div class="card-body text-center">
                        <i class="bi bi-people display-5 text-primary"></i>
                        <h3 id="incomingCount">0</h3>
                        <small class="text-muted">New Students Expected</small>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <i class="bi bi-calendar-check display-5 text-success"></i>
                        <h3 id="confirmedCount">0</h3>
                        <small class="text-muted">Confirmed Enrollment</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Class Filter for Teachers -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-4">
                        <label class="form-label mb-0">Filter by Class:</label>
                    </div>
                    <div class="col-md-6">
                        <select class="form-select" id="classFilter">
                            <option value="">My Assigned Classes</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-primary w-100" id="applyFilterBtn">
                            <i class="bi bi-funnel"></i> Filter
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Incoming Students List -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-list-ul me-2"></i>Upcoming Enrollments</h6>
            </div>
            <div class="card-body">
                <div class="row" id="incomingStudentsList">
                    <!-- Dynamic student cards will be loaded here -->
                    <div class="col-12 text-center text-muted py-5">
                        <i class="bi bi-inbox display-1"></i>
                        <p class="mt-3">No incoming students at this time</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Student Card Template (Hidden) -->
        <template id="studentCardTemplate">
            <div class="col-md-4 mb-3">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="avatar-circle bg-primary text-white me-3">
                                <span class="initials"></span>
                            </div>
                            <div>
                                <h6 class="mb-0 student-name"></h6>
                                <small class="text-muted student-grade"></small>
                            </div>
                        </div>
                        <div class="small">
                            <p class="mb-1"><i class="bi bi-calendar3 me-2"></i>Expected: <span
                                    class="expected-date"></span></p>
                            <p class="mb-1"><i class="bi bi-mortarboard me-2"></i>Previous: <span
                                    class="previous-school"></span></p>
                            <p class="mb-0">
                                <span class="badge status-badge"></span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <style>
        .avatar-circle {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .operator-layout {
            padding: 20px;
            margin-left: 60px;
        }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/api.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            console.log('Operator Admissions Dashboard loaded');
            // Initialize admissions controller
            if (typeof admissionsController !== 'undefined') {
                admissionsController.init('operator');
            }
        });
    </script>
</body>

</html>