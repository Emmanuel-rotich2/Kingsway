<?php
/**
 * Driver Dashboard — Route info, students, vehicle status (Role ID 23)
 */
?>
<div class="container-fluid py-3" id="driver-dashboard">

    <!-- Greeting Bar -->
    <div class="dash-greeting-bar mb-4">
        <div>
            <h5 id="driverGreeting">Good morning!</h5>
            <p>Your route, students, and daily schedule</p>
        </div>
        <div class="dash-meta">
            <button class="dash-refresh-btn" onclick="driverDashboardController.refresh()">
                <i class="bi bi-arrow-clockwise me-1"></i>Refresh
            </button>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="dash-stat dsc-blue">
                <div class="dash-stat-value small" id="routeNameCard">—</div>
                <div class="dash-stat-label">My Route</div>
                <i class="bi bi-signpost-split dash-stat-icon"></i>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="dash-stat dsc-indigo">
                <div class="dash-stat-value" id="totalStudents">0</div>
                <div class="dash-stat-label">Students</div>
                <i class="bi bi-people dash-stat-icon"></i>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="dash-stat dsc-teal">
                <div class="dash-stat-value" id="totalStops">0</div>
                <div class="dash-stat-label">Stops</div>
                <i class="bi bi-geo-alt-fill dash-stat-icon"></i>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="dash-stat dsc-green">
                <div class="dash-stat-value" id="presentToday">0</div>
                <div class="dash-stat-label">Present Today</div>
                <i class="bi bi-check-circle-fill dash-stat-icon"></i>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <!-- Route Schedule -->
        <div class="col-md-5">
            <div class="card dash-card">
                <div class="card-header"><h6 class="mb-0"><i class="bi bi-map me-2"></i>Route Schedule</h6></div>
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
            <div class="card dash-card mt-3">
                <div class="card-header"><h6 class="mb-0"><i class="bi bi-truck me-2"></i>My Vehicle</h6></div>
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
            <div class="card dash-card">
                <div class="card-header d-flex justify-content-between align-items-center">
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
