<?php
/**
 * Mark Attendance Page
 * Driver version - Mark passenger attendance for transport trips
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

<div class="container-fluid py-4" id="markAttendancePage">

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-primary text-white">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h4 class="mb-0">
                        <i class="fas fa-clipboard-check me-2"></i>
                        Passenger Attendance
                    </h4>
                    <small id="scopeSubtitle">Mark pickup, drop-off, absence, and not-riding status for transport passengers</small>
                </div>
                <div class="btn-group">
                    <button class="btn btn-light btn-sm" id="refreshBtn">
                        <i class="bi bi-arrow-clockwise"></i> Refresh
                    </button>
                    <button class="btn btn-outline-light btn-sm" id="saveAttendanceBtn">
                        <i class="bi bi-check-circle me-1"></i> Save Attendance
                    </button>
                    <button class="btn btn-outline-light btn-sm" id="submitTripReportBtn">
                        <i class="bi bi-file-text me-1"></i> Submit Trip Report
                    </button>
                    <button class="btn btn-light btn-sm" id="printSheetBtn">
                        <i class="bi bi-printer me-1"></i> Print Sheet
                    </button>
                </div>
            </div>
        </div>

        <div class="card-body">

            <!-- Trip Setup Card -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="fas fa-cog me-2 text-primary"></i>
                        Trip Setup
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-semibold">Date</label>
                            <input type="date" class="form-control" id="attendanceDate" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-semibold">Route</label>
                            <select class="form-select" id="routeSelect" required>
                                <option value="">Select Route</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-semibold">Vehicle</label>
                            <select class="form-select" id="vehicleSelect" required>
                                <option value="">Select Vehicle</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-semibold">Trip Session</label>
                            <select class="form-select" id="tripSession" required>
                                <option value="">Select Session</option>
                                <option value="morning_pickup">Morning Pickup</option>
                                <option value="evening_dropoff">Evening Drop-off</option>
                                <option value="midday_trip">Midday Trip</option>
                                <option value="special_trip">Special Trip</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Driver</label>
                            <input type="text" class="form-control" id="driverName" readonly>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Notes</label>
                            <input type="text" class="form-control" id="tripNotes" placeholder="Trip notes (optional)">
                        </div>
                        <div class="col-md-12 mb-3">
                            <button class="btn btn-primary" id="loadPassengersBtn">
                                <i class="bi bi-people me-1"></i> Load Passengers
                            </button>
                        </div>
                    </div>
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
                                    <small class="text-muted">Total Expected</small>
                                    <h4 class="mb-0" id="totalExpected">0</h4>
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
                                    <i class="fas fa-check"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Marked Present</small>
                                    <h4 class="mb-0" id="markedPresent">0</h4>
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
                                    <small class="text-muted">Absent</small>
                                    <h4 class="mb-0" id="absent">0</h4>
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
                                    <i class="fas fa-ban"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Not Riding</small>
                                    <h4 class="mb-0" id="notRiding">0</h4>
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
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Pending</small>
                                    <h4 class="mb-0" id="pending">0</h4>
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
                                    <small class="text-muted">Incidents</small>
                                    <h4 class="mb-0" id="incidents">0</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- States -->
            <div id="attendanceLoading" class="alert alert-info d-none">
                <i class="fas fa-spinner fa-spin me-2"></i> Loading passengers...
            </div>

            <div id="attendanceError" class="alert alert-danger d-none"></div>

            <div id="attendanceForbidden" class="alert alert-warning d-none">
                <i class="fas fa-exclamation-triangle me-2"></i> You do not have permission to mark transport attendance.
            </div>

            <div id="attendanceEmpty" class="alert alert-warning d-none">
                <i class="fas fa-info-circle me-2"></i> Select trip details and load passengers to mark attendance.
            </div>

            <!-- Passenger Attendance Table -->
            <div class="card border-0 shadow-sm" id="attendanceCard" style="display: none;">
                <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h5 class="mb-0">Passenger Attendance</h5>
                        <small class="text-muted" id="attendanceInfo">-</small>
                    </div>
                    <div class="btn-group">
                        <button type="button" class="btn btn-outline-success btn-sm" id="markSelectedPickedUp">
                            <i class="bi bi-arrow-up"></i> Picked Up
                        </button>
                        <button type="button" class="btn btn-outline-info btn-sm" id="markSelectedDroppedOff">
                            <i class="bi bi-arrow-down"></i> Dropped Off
                        </button>
                        <button type="button" class="btn btn-outline-danger btn-sm" id="markSelectedAbsent">
                            <i class="bi bi-x-circle"></i> Absent
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="markSelectedNotRiding">
                            <i class="bi bi-ban"></i> Not Riding
                        </button>
                        <button type="button" class="btn btn-outline-dark btn-sm" id="clearSelected">
                            <i class="bi bi-eraser"></i> Clear
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="passengersTable">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 50px;"><input type="checkbox" id="selectAll"></th>
                                    <th style="width: 120px;">Adm No</th>
                                    <th>Student Name</th>
                                    <th style="width: 100px;">Class</th>
                                    <th style="width: 100px;">Stream</th>
                                    <th style="width: 150px;">Pickup Point</th>
                                    <th style="width: 150px;">Drop-off Point</th>
                                    <th style="width: 100px;">Expected</th>
                                    <th style="width: 200px;">Status</th>
                                    <th style="width: 100px;">Time</th>
                                    <th style="width: 200px;">Notes</th>
                                    <th style="width: 80px;">Action</th>
                                </tr>
                            </thead>
                            <tbody id="passengersTableBody">
                                <!-- Passengers will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-white">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div id="attendanceSummary">
                            <span class="badge bg-success me-2" id="pickedUpCount">Picked Up: 0</span>
                            <span class="badge bg-info me-2" id="droppedOffCount">Dropped Off: 0</span>
                            <span class="badge bg-danger me-2" id="absentCount">Absent: 0</span>
                            <span class="badge bg-secondary me-2" id="notRidingCount">Not Riding: 0</span>
                            <span class="badge bg-warning text-dark" id="pendingCount">Pending: 0</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Save Confirmation Modal -->
<div class="modal fade" id="saveConfirmationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title mb-0">Confirm Attendance</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Please confirm the following attendance summary:</p>
                <ul>
                    <li><strong>Picked Up:</strong> <span id="confirmPickedUp">0</span></li>
                    <li><strong>Dropped Off:</strong> <span id="confirmDroppedOff">0</span></li>
                    <li><strong>Absent:</strong> <span id="confirmAbsent">0</span></li>
                    <li><strong>Not Riding:</strong> <span id="confirmNotRiding">0</span></li>
                    <li><strong>Pending:</strong> <span id="confirmPending">0</span></li>
                </ul>
                <p class="text-muted">Are you sure you want to save this attendance?</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" id="confirmSaveBtn">
                    <i class="bi bi-check-circle me-1"></i> Confirm Save
                </button>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo $appBase; ?>/js/pages/mark_attendance.js?v=<?php echo time(); ?>"></script>
