<?php
/**
 * Transport Passengers Page
 * Driver's passenger list and route dashboard
 * Embedded in app_layout.php
 */

// Ensure $appBase is available for script loading
if (!isset($appBase)) {
    $appBase = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
    if ($appBase === '.' || $appBase === '/') {
        $appBase = '';
    }
}
?>

<div class="container-fluid py-4" id="transportPassengersPage">

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-primary text-white">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h4 class="mb-0">
                        <i class="fas fa-bus me-2"></i>
                        My Passengers
                    </h4>
                    <small id="scopeSubtitle">View assigned route passengers, pickup/drop-off points, and transport status</small>
                </div>
                <div class="btn-group">
                    <button class="btn btn-light btn-sm" id="refreshBtn">
                        <i class="bi bi-arrow-clockwise"></i> Refresh
                    </button>
                    <button class="btn btn-outline-light btn-sm" id="printListBtn">
                        <i class="bi bi-printer me-1"></i> Print List
                    </button>
                    <button class="btn btn-light btn-sm" id="exportSheetBtn">
                        <i class="bi bi-download me-1"></i> Export Sheet
                    </button>
                    <button class="btn btn-outline-light btn-sm" id="reportIncidentBtn">
                        <i class="bi bi-exclamation-triangle me-1"></i> Report Incident
                    </button>
                </div>
            </div>
        </div>

        <div class="card-body">

            <!-- Filters -->
            <div class="row g-3 mb-4">
                <div class="col-xl-2 col-md-4">
                    <label class="form-label fw-semibold">Date</label>
                    <input type="date" class="form-control" id="dateFilter">
                </div>

                <div class="col-xl-2 col-md-4">
                    <label class="form-label fw-semibold">Route</label>
                    <select class="form-select" id="routeFilter">
                        <option value="">All Routes</option>
                    </select>
                </div>

                <div class="col-xl-2 col-md-4">
                    <label class="form-label fw-semibold">Vehicle</label>
                    <select class="form-select" id="vehicleFilter">
                        <option value="">All Vehicles</option>
                    </select>
                </div>

                <div class="col-xl-2 col-md-4">
                    <label class="form-label fw-semibold">Trip Session</label>
                    <select class="form-select" id="tripSessionFilter">
                        <option value="">All Sessions</option>
                        <option value="morning_pickup">Morning Pickup</option>
                        <option value="evening_dropoff">Evening Drop-off</option>
                        <option value="midday_trip">Midday Trip</option>
                        <option value="special_trip">Special Trip</option>
                    </select>
                </div>

                <div class="col-xl-2 col-md-4">
                    <label class="form-label fw-semibold">Class</label>
                    <select class="form-select" id="classFilter">
                        <option value="">All Classes</option>
                    </select>
                </div>

                <div class="col-xl-2 col-md-4">
                    <label class="form-label fw-semibold">Stream</label>
                    <select class="form-select" id="streamFilter">
                        <option value="">All Streams</option>
                    </select>
                </div>

                <div class="col-xl-2 col-md-4">
                    <label class="form-label fw-semibold">Gender</label>
                    <select class="form-select" id="genderFilter">
                        <option value="">All</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                    </select>
                </div>

                <div class="col-xl-2 col-md-4">
                    <label class="form-label fw-semibold">Transport Status</label>
                    <select class="form-select" id="transportStatusFilter">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="suspended">Suspended</option>
                        <option value="not_riding">Not Riding</option>
                        <option value="transferred">Transferred</option>
                    </select>
                </div>

                <div class="col-xl-2 col-md-4">
                    <label class="form-label fw-semibold">Attendance Status</label>
                    <select class="form-select" id="attendanceStatusFilter">
                        <option value="">All</option>
                        <option value="pending">Pending</option>
                        <option value="picked_up">Picked Up</option>
                        <option value="dropped_off">Dropped Off</option>
                        <option value="absent">Absent</option>
                        <option value="excused">Excused</option>
                        <option value="not_riding">Not Riding</option>
                    </select>
                </div>

                <div class="col-xl-3 col-md-6">
                    <label class="form-label fw-semibold">Search</label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-search"></i>
                        </span>
                        <input type="text" class="form-control" id="searchBox"
                               placeholder="Search by name, admission number, pickup point, guardian phone">
                    </div>
                </div>

                <div class="col-xl-2 col-md-4 d-flex align-items-end">
                    <button class="btn btn-primary w-100" id="applyFiltersBtn">
                        <i class="fas fa-filter me-1"></i> Apply
                    </button>
                </div>

                <div class="col-xl-2 col-md-4 d-flex align-items-end">
                    <button class="btn btn-outline-secondary w-100" id="resetFiltersBtn">
                        <i class="fas fa-undo me-1"></i> Reset
                    </button>
                </div>
            </div>

            <!-- Summary cards -->
            <div class="row g-3 mb-4">
                <div class="col-xl-2 col-md-4">
                    <div class="card border-0 bg-light h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle bg-primary text-white p-3">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Total Passengers</small>
                                    <h4 class="mb-0" id="totalPassengers">0</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4">
                    <div class="card border-0 bg-light h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle bg-success text-white p-3">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Expected Today</small>
                                    <h4 class="mb-0" id="expectedToday">0</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4">
                    <div class="card border-0 bg-light h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle bg-info text-white p-3">
                                    <i class="fas fa-arrow-up"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Picked Up</small>
                                    <h4 class="mb-0" id="pickedUp">0</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4">
                    <div class="card border-0 bg-light h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle bg-warning text-dark p-3">
                                    <i class="fas fa-arrow-down"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Dropped Off</small>
                                    <h4 class="mb-0" id="droppedOff">0</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4">
                    <div class="card border-0 bg-light h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle bg-danger text-white p-3">
                                    <i class="fas fa-user-times"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Absent / Not Riding</small>
                                    <h4 class="mb-0" id="absentNotRiding">0</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4">
                    <div class="card border-0 bg-light h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle bg-secondary text-white p-3">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Pending Pickup</small>
                                    <h4 class="mb-0" id="pendingPickup">0</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4">
                    <div class="card border-0 bg-light h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle bg-danger text-white p-3">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Emergency Alerts</small>
                                    <h4 class="mb-0" id="emergencyAlerts">0</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4">
                    <div class="card border-0 bg-light h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle bg-dark text-white p-3">
                                    <i class="fas fa-route"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Route / Vehicle</small>
                                    <h4 class="mb-0" id="routeVehicle">-</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- States -->
            <div id="passengersLoading" class="alert alert-info d-none">
                <i class="fas fa-spinner fa-spin me-2"></i> Loading passengers...
            </div>

            <div id="passengersError" class="alert alert-danger d-none"></div>

            <div id="passengersForbidden" class="alert alert-warning d-none">
                <i class="fas fa-exclamation-triangle me-2"></i> You do not have permission to access transport data.
            </div>

            <div id="passengersEmpty" class="alert alert-warning d-none">
                <i class="fas fa-info-circle me-2"></i> No passengers found for the selected filters.
            </div>

            <!-- Main Table -->
            <div class="card border-0 shadow-sm" id="passengersCard">
                <div class="card-header bg-white">
                    <strong>
                        <i class="fas fa-list me-2 text-primary"></i>
                        Passengers
                    </strong>
                </div>

                <div class="card-body table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Adm No</th>
                                <th>Student Name</th>
                                <th>Class</th>
                                <th>Stream</th>
                                <th>Gender</th>
                                <th>Route</th>
                                <th>Vehicle</th>
                                <th>Pickup Point</th>
                                <th>Drop-off Point</th>
                                <th>Guardian Contact</th>
                                <th>Today Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="passengersTableBody">
                            <tr>
                                <td class="text-center text-muted">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Passenger Profile Modal -->
<div class="modal fade" id="passengerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content border-0 shadow">

            <div class="modal-header bg-primary text-white">
                <div>
                    <h5 class="modal-title mb-0">
                        <i class="fas fa-user me-2"></i>
                        Passenger Profile
                    </h5>
                    <small id="modalSubtitle">Transport passenger details</small>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div id="modalLoading" class="alert alert-info d-none">
                    <i class="fas fa-spinner fa-spin me-2"></i> Loading passenger profile...
                </div>

                <div id="modalError" class="alert alert-danger d-none"></div>

                <div id="modalPassengerContent">
                    <!-- Passenger profile details will be rendered here -->
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <button class="btn btn-success" id="markPickedUpBtn">
                    <i class="bi bi-arrow-up me-1"></i> Mark Picked Up
                </button>
                <button class="btn btn-warning" id="markDroppedOffBtn">
                    <i class="bi bi-arrow-down me-1"></i> Mark Dropped Off
                </button>
                <button class="btn btn-danger" id="markAbsentBtn">
                    <i class="bi bi-x-circle me-1"></i> Mark Absent
                </button>
                <button class="btn btn-secondary" id="addTransportNoteBtn">
                    <i class="bi bi-plus-circle me-1"></i> Add Note
                </button>
            </div>

        </div>
    </div>
</div>

<!-- Incident Modal -->
<div class="modal fade" id="incidentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">

            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title mb-0">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Report Transport Incident
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <form id="incidentForm">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Student</label>
                        <select class="form-select" id="incidentStudent">
                            <option value="">Select Student (Optional)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Date/Time</label>
                        <input type="datetime-local" class="form-control" id="incidentDateTime" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Route</label>
                        <select class="form-select" id="incidentRoute">
                            <option value="">Select Route</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Vehicle</label>
                        <select class="form-select" id="incidentVehicle">
                            <option value="">Select Vehicle</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Incident Type</label>
                        <select class="form-select" id="incidentType" required>
                            <option value="">Select Type</option>
                            <option value="accident">Accident</option>
                            <option value="late_pickup">Late Pickup</option>
                            <option value="late_dropoff">Late Drop-off</option>
                            <option value="wrong_stop">Wrong Stop</option>
                            <option value="behavior">Behavior Issue</option>
                            <option value="medical">Medical Emergency</option>
                            <option value="vehicle_breakdown">Vehicle Breakdown</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea class="form-control" id="incidentDescription" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Action Taken</label>
                        <textarea class="form-control" id="incidentActionTaken" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Escalate to Admin</label>
                        <select class="form-select" id="incidentEscalate">
                            <option value="0">No</option>
                            <option value="1">Yes</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Notes</label>
                        <textarea class="form-control" id="incidentNotes" rows="2"></textarea>
                    </div>
                </form>
            </div>

            <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-danger" id="saveIncidentBtn">
                    <i class="bi bi-exclamation-triangle me-1"></i> Submit Report
                </button>
            </div>

        </div>
    </div>
</div>

<script src="<?php echo $appBase; ?>/js/pages/transport_passengers.js?v=<?php echo time(); ?>"></script>
