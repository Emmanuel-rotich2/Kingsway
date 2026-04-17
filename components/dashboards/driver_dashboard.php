<?php
/**
 * Driver Dashboard Component
 * Role: Driver (ID 23) — route info, student attendance, vehicle status
 */
?>
<div class="container-fluid py-3" id="driver-dashboard">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1"><i class="bi bi-truck me-2 text-primary"></i>Driver Dashboard</h4>
            <p class="text-muted mb-0">Your route, students, and daily schedule</p>
        </div>
        <button class="btn btn-outline-primary btn-sm" onclick="driverDashboardController.refresh()">
            <i class="bi bi-arrow-clockwise me-1"></i>Refresh
        </button>
    </div>

    <!-- Stat Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body py-3">
                    <div class="fs-2 mb-1">🛣️</div>
                    <div class="fw-semibold small" id="routeNameCard">—</div>
                    <small class="text-muted">My Route</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body py-3">
                    <div class="fs-2 mb-1">👨‍🎓</div>
                    <h4 class="mb-0 text-primary" id="totalStudents">0</h4>
                    <small class="text-muted">Students</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body py-3">
                    <div class="fs-2 mb-1">📍</div>
                    <h4 class="mb-0 text-success" id="totalStops">0</h4>
                    <small class="text-muted">Stops</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body py-3">
                    <div class="fs-2 mb-1">✅</div>
                    <h4 class="mb-0 text-success" id="presentToday">0</h4>
                    <small class="text-muted">Present Today</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <!-- Route Schedule -->
        <div class="col-md-5">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="bi bi-map me-2"></i>Route Schedule</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-6 text-center">
                            <small class="text-muted d-block">AM Pickup</small>
                            <strong id="amPickup" class="text-success fs-5">—</strong>
                        </div>
                        <div class="col-6 text-center">
                            <small class="text-muted d-block">PM Drop-off</small>
                            <strong id="pmDropoff" class="text-warning fs-5">—</strong>
                        </div>
                    </div>
                    <hr class="my-2">
                    <p class="small text-muted mb-2"><i class="bi bi-geo-alt me-1"></i>Stops</p>
                    <div id="stopsList"><div class="text-center text-muted py-3 small">Loading...</div></div>
                </div>
            </div>
            <!-- Vehicle -->
            <div class="card shadow-sm mt-3">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="bi bi-truck me-2"></i>My Vehicle</h6>
                </div>
                <div class="card-body">
                    <div class="row g-2 text-center">
                        <div class="col-6"><small class="text-muted d-block">Reg No.</small><strong id="vehicleReg">—</strong></div>
                        <div class="col-6"><small class="text-muted d-block">Model</small><strong id="vehicleModel">—</strong></div>
                        <div class="col-6"><small class="text-muted d-block">Capacity</small><strong id="vehicleCapacity">—</strong></div>
                        <div class="col-6"><small class="text-muted d-block">Status</small><span id="vehicleStatus" class="badge bg-secondary">—</span></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Student Attendance -->
        <div class="col-md-7">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-people me-2"></i>Today's Students</h6>
                    <button class="btn btn-sm btn-success" onclick="driverDashboardController.saveAttendance()">
                        <i class="bi bi-check2 me-1"></i>Save Attendance
                    </button>
                </div>
                <div class="card-body p-0" id="studentAttendanceList">
                    <div class="text-center text-muted py-4">Loading students...</div>
                </div>
            </div>
        </div>
    </div>
</div>
