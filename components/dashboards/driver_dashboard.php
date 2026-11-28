<?php
/**
 * Driver Dashboard — Vehicle, route, passengers and safety metrics.
 * Role: Driver
 */
$rootId = 'driverDashboard';
$periods = [
    ['key' => 'today', 'label' => 'Today'],
    ['key' => 'week', 'label' => 'This Week'],
    ['key' => 'term', 'label' => 'This Term'],
];
$default = 'today';

$escape = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};
?>

<div class="container-fluid py-4 role-dashboard dashboard-surface dashboard-transport" id="<?= $escape($rootId) ?>">
    <?php require __DIR__ . '/partials/period_selector.php'; ?>

    <!-- Meta Bar -->
    <div class="dash-meta-bar mb-4 d-flex align-items-center justify-content-end gap-3 flex-wrap">
        <span class="dash-badge bg-info" id="<?= $escape($rootId) ?>VehicleBadge">—</span>
        <span class="small text-muted">Updated: <span id="<?= $escape($rootId) ?>LastUpdated">—</span></span>
        <button class="btn btn-sm btn-outline-success" id="<?= $escape($rootId) ?>Refresh">
            <i class="bi bi-arrow-clockwise me-1"></i>Refresh
        </button>
    </div>

    <!-- ROW 1: VEHICLE STATUS — full-width hero -->
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card dash-card border-start border-4 border-primary">
                <div class="card-body">
                    <div class="row align-items-center g-3">
                        <div class="col-md-2 text-center">
                            <i class="bi bi-bus-front display-3 text-primary"></i>
                            <div class="mt-2 fw-bold" id="drvVehiclePlate">—</div>
                            <small class="text-muted" id="drvVehicleModel">—</small>
                        </div>
                        <div class="col-md-3">
                            <h6 class="text-muted mb-1">Vehicle Details</h6>
                            <div class="small">
                                <div><i class="bi bi-rulers me-1"></i>Capacity: <strong id="drvVehicleCapacity">—</strong> seats</div>
                                <div><i class="bi bi-clipboard-check me-1"></i>Last Inspection: <strong id="drvLastInspection">—</strong></div>
                                <div><i class="bi bi-shield-check me-1"></i>Service Status: <span class="badge" id="drvServiceStatus">—</span></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <h6 class="text-muted mb-1">Fuel Level</h6>
                            <div class="progress" style="height: 24px;">
                                <div class="progress-bar" id="drvFuelBar" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">—</div>
                            </div>
                            <small class="text-muted mt-1" id="drvFuelLabel">Loading...</small>
                        </div>
                        <div class="col-md-4 text-center">
                            <h6 class="text-muted mb-2">Today's Summary</h6>
                            <div class="d-flex justify-content-center gap-4">
                                <div>
                                    <div class="fs-3 fw-bold text-success" id="drvTripsToday">0</div>
                                    <small class="text-muted">Trips Today</small>
                                </div>
                                <div>
                                    <div class="fs-3 fw-bold text-info" id="drvPassengersNow">0</div>
                                    <small class="text-muted">Passengers Now</small>
                                </div>
                                <div>
                                    <div class="fs-3 fw-bold text-warning" id="drvKmToday">0</div>
                                    <small class="text-muted">km Today</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ROW 2: ROUTE STATUS — full-width -->
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card dash-card border-start border-4 border-info">
                <div class="card-body">
                    <div class="row align-items-center g-3">
                        <div class="col-md-3">
                            <h6 class="text-muted mb-1">Current Route</h6>
                            <div class="fs-5 fw-bold" id="drvRouteName">—</div>
                            <small class="text-muted" id="drvRouteDescription">—</small>
                        </div>
                        <div class="col-md-2">
                            <h6 class="text-muted mb-1">Departure</h6>
                            <div class="fs-5 fw-bold" id="drvDepartureTime">—</div>
                            <small class="text-muted">Scheduled</small>
                        </div>
                        <div class="col-md-2">
                            <h6 class="text-muted mb-1">Pickup Points</h6>
                            <div class="fs-5 fw-bold" id="drvPickupPoints">—</div>
                            <small class="text-muted">stops</small>
                        </div>
                        <div class="col-md-2">
                            <h6 class="text-muted mb-1">Students</h6>
                            <div class="fs-5 fw-bold" id="drvRouteStudents">—</div>
                            <small class="text-muted">boarded / expected</small>
                        </div>
                        <div class="col-md-3 text-end">
                            <h6 class="text-muted mb-1">ETA</h6>
                            <div class="fs-5 fw-bold text-success" id="drvETA">—</div>
                            <small class="text-muted" id="drvETALabel">to school</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ROW 3: TODAY'S METRICS — 6 KPIs -->
    <div class="row g-3 mb-3">
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-blue">
                <i class="bi bi-people dash-stat-icon"></i>
                <div class="dash-stat-value" id="drvPassengersToday">—</div>
                <div class="dash-stat-label">Passengers Today</div>
                <div class="dash-stat-sub" id="drvPassengersSub">pickup + dropoff</div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-green">
                <i class="bi bi-clock-history dash-stat-icon"></i>
                <div class="dash-stat-value" id="drvOnTimeRate">—</div>
                <div class="dash-stat-label">On-Time Rate</div>
                <div class="dash-stat-sub" id="drvOnTimeSub">trips on schedule</div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-red">
                <i class="bi bi-exclamation-triangle dash-stat-icon"></i>
                <div class="dash-stat-value" id="drvIncidents">—</div>
                <div class="dash-stat-label">Incidents</div>
                <div class="dash-stat-sub">this month</div>
            </div>
        </div>
    </div>
    <div class="row g-3 mb-4">
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-cyan">
                <i class="bi bi-signpost-split dash-stat-icon"></i>
                <div class="dash-stat-value" id="drvTripsCompleted">—</div>
                <div class="dash-stat-label">Trips Completed</div>
                <div class="dash-stat-sub">this week</div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-amber">
                <i class="bi bi-fuel-pump dash-stat-icon"></i>
                <div class="dash-stat-value" id="drvFuelEconomy">—</div>
                <div class="dash-stat-label">Fuel Economy</div>
                <div class="dash-stat-sub">km / litre</div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-orange">
                <i class="bi bi-tools dash-stat-icon"></i>
                <div class="dash-stat-value" id="drvMaintenanceDue">—</div>
                <div class="dash-stat-label">Maintenance Due</div>
                <div class="dash-stat-sub">upcoming service</div>
            </div>
        </div>
    </div>

    <!-- ROW 4: PASSENGER MANAGEMENT — 2 panels -->
    <div class="row g-3 mb-4">
        <div class="col-lg-7">
            <div class="card dash-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-clipboard-check me-2 text-primary"></i>Passenger Manifest</h6>
                    <button class="btn btn-sm btn-outline-primary" data-route="transport_passengers">Full Manifest</button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Student</th>
                                    <th scope="col">Class</th>
                                    <th scope="col">Pickup Point</th>
                                    <th scope="col" class="text-center">Check-in</th>
                                </tr>
                            </thead>
                            <tbody id="drvManifestBody">
                                <tr><td colspan="4" class="text-center text-muted py-4">Loading manifest...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card dash-card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-geo-alt me-2 text-success"></i>Pickup Point Status</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Point</th>
                                    <th scope="col">Students</th>
                                    <th scope="col">Collected</th>
                                    <th scope="col">Status</th>
                                </tr>
                            </thead>
                            <tbody id="drvPickupBody">
                                <tr><td colspan="4" class="text-center text-muted py-4">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ROW 5: WEEKLY PERFORMANCE — 2 panels -->
    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card dash-card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-bar-chart me-2 text-info"></i>Trips: Completed vs Planned</h6>
                </div>
                <div class="card-body">
                    <div class="dash-chart-wrap-lg"><canvas id="drvTripsChart"></canvas></div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card dash-card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-clipboard2-check me-2 text-warning"></i>Daily Safety Checklist</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Check Item</th>
                                    <th scope="col">Last Done</th>
                                    <th scope="col" class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody id="drvChecklistBody">
                                <tr><td colspan="3" class="text-center text-muted py-4">Loading checklist...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Incident History -->
    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card dash-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-exclamation-octagon me-2 text-danger"></i>Recent Incidents</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Date</th>
                                    <th scope="col">Type</th>
                                    <th scope="col">Description</th>
                                    <th scope="col">Status</th>
                                </tr>
                            </thead>
                            <tbody id="drvIncidentsBody">
                                <tr><td colspan="4" class="text-center text-muted py-4">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card dash-card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-fuel-pump me-2 text-amber"></i>Fuel Log</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Date</th>
                                    <th scope="col">Litres</th>
                                    <th scope="col">Cost (KES)</th>
                                    <th scope="col">Odometer</th>
                                </tr>
                            </thead>
                            <tbody id="drvFuelLogBody">
                                <tr><td colspan="4" class="text-center text-muted py-4">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ROW 6: QUICK ACTIONS -->
    <div class="row g-3 mb-3">
        <div class="col-12">
            <div class="card dash-card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-lightning-charge me-2 text-warning"></i>Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-md-3 col-lg">
                            <a href="#" data-route="my_routes" class="dash-quick-link">
                                <i class="bi bi-signpost-split ql-icon bg-primary text-white"></i>
                                <span>My Route</span><i class="bi bi-chevron-right ql-arrow"></i>
                            </a>
                        </div>
                        <div class="col-md-3 col-lg">
                            <a href="#" data-route="transport_passengers" class="dash-quick-link">
                                <i class="bi bi-people ql-icon bg-success text-white"></i>
                                <span>Today's Passengers</span><i class="bi bi-chevron-right ql-arrow"></i>
                            </a>
                        </div>
                        <div class="col-md-3 col-lg">
                            <a href="#" data-route="my_vehicle" class="dash-quick-link">
                                <i class="bi bi-clipboard-check ql-icon bg-info text-white"></i>
                                <span>Pre-trip Check</span><i class="bi bi-chevron-right ql-arrow"></i>
                            </a>
                        </div>
                        <div class="col-md-3 col-lg">
                            <a href="#" data-route="my_vehicle" class="dash-quick-link">
                                <i class="bi bi-exclamation-octagon ql-icon bg-danger text-white"></i>
                                <span>Report a Problem</span><i class="bi bi-chevron-right ql-arrow"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<?php asset_script($appBase, 'js/dashboards/dashboard_base_controller.js'); ?>
<?php asset_script($appBase, 'js/dashboards/driver_dashboard.js'); ?>
