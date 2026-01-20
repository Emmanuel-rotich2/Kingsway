<?php
/**
 * Operator Boarding Template
 * For Teachers, Nurse, Staff - Limited view
 * 
 * Features:
 * - View student boarding status
 * - See health alerts
 * - Basic dormitory information
 * - Emergency contacts
 */

$pageTitle = "Boarding Information";
$pageIcon = "bi-house";
$pageScripts = ['js/pages/boarding.js'];
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
                <h2 class="mb-1"><i class="bi bi-house me-2"></i>Boarding Information</h2>
                <p class="text-muted mb-0">View student boarding status</p>
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
                        <h3 id="totalBoarders">0</h3>
                        <small class="text-muted">Total Boarders</small>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <i class="bi bi-check-circle display-5 text-success"></i>
                        <h3 id="presentToday">0</h3>
                        <small class="text-muted">Present Today</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search Boarder -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-4">
                        <label class="form-label mb-0">Search Boarder:</label>
                    </div>
                    <div class="col-md-6">
                        <input type="text" class="form-control" id="searchBoarder" 
                               placeholder="Enter student name or admission number...">
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-primary w-100" id="searchBtn">
                            <i class="bi bi-search"></i> Search
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dormitories Overview -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-building me-2"></i>Dormitories</h6>
            </div>
            <div class="card-body">
                <div class="row" id="dormitoriesList">
                    <!-- Dynamic dormitory cards -->
                    <div class="col-md-6 mb-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">Boys Dormitory A</h6>
                                        <small class="text-muted">Patron: Mr. Smith</small>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-success">45/50</span>
                                        <br><small class="text-muted">Occupied</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">Girls Dormitory A</h6>
                                        <small class="text-muted">Matron: Mrs. Johnson</small>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-warning text-dark">48/50</span>
                                        <br><small class="text-muted">Near Full</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Emergency Contacts -->
        <div class="card">
            <div class="card-header bg-danger text-white">
                <h6 class="mb-0"><i class="bi bi-telephone-fill me-2"></i>Emergency Contacts</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="d-flex align-items-center">
                            <div class="bg-light rounded-circle p-3 me-3">
                                <i class="bi bi-person-badge text-primary fs-4"></i>
                            </div>
                            <div>
                                <h6 class="mb-0">Boarding Master</h6>
                                <a href="tel:+254700000001">+254 700 000 001</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="d-flex align-items-center">
                            <div class="bg-light rounded-circle p-3 me-3">
                                <i class="bi bi-heart-pulse text-danger fs-4"></i>
                            </div>
                            <div>
                                <h6 class="mb-0">School Nurse</h6>
                                <a href="tel:+254700000002">+254 700 000 002</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="d-flex align-items-center">
                            <div class="bg-light rounded-circle p-3 me-3">
                                <i class="bi bi-shield text-success fs-4"></i>
                            </div>
                            <div>
                                <h6 class="mb-0">Security</h6>
                                <a href="tel:+254700000003">+254 700 000 003</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .operator-layout {
            padding: 20px;
            margin-left: 60px;
        }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/api.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Operator Boarding Dashboard loaded');
            // Initialize boarding controller
            if (typeof boardingController !== 'undefined') {
                boardingController.init('operator');
            }
        });
    </script>
</body>
</html>
