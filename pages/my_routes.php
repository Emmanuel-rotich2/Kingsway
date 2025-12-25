<?php
/**
 * My Routes Page (Driver's assigned transport routes)
 * HTML structure only - logic will be in js/pages/my_routes.js
 * Embedded in app_layout.php
 */
?>

<div class="card shadow-sm">
    <div class="card-header bg-gradient bg-primary text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="fas fa-route"></i> My Routes</h4>
            <button class="btn btn-light btn-sm" id="startTripBtn">
                <i class="bi bi-play-circle"></i> Start Trip
            </button>
        </div>
    </div>

    <div class="card-body">
        <!-- Current Trip Status -->
        <div class="card bg-light mb-4" id="currentTripCard" style="display: none;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-1">Active Trip</h5>
                        <p class="mb-0"><strong>Route:</strong> <span id="activeTripRoute"></span></p>
                        <p class="mb-0"><strong>Started:</strong> <span id="tripStartTime"></span></p>
                    </div>
                    <div>
                        <button class="btn btn-success btn-sm" id="endTripBtn">
                            <i class="bi bi-stop-circle"></i> End Trip
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-primary">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Assigned Routes</h6>
                        <h3 class="text-primary mb-0" id="assignedRoutes">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Trips Today</h6>
                        <h3 class="text-success mb-0" id="tripsToday">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-info">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Students Assigned</h6>
                        <h3 class="text-info mb-0" id="studentsAssigned">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-warning">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">KM This Week</h6>
                        <h3 class="text-warning mb-0" id="kmThisWeek">0</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- My Routes Table -->
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Assigned Routes</h5>
                <div class="table-responsive">
                    <table class="table table-hover" id="routesTable">
                        <thead class="table-light">
                            <tr>
                                <th>Route Name</th>
                                <th>Type</th>
                                <th>Pickup Points</th>
                                <th>Students</th>
                                <th>Distance (KM)</th>
                                <th>Schedule</th>
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

        <!-- Today's Schedule -->
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Today's Schedule</h5>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Route</th>
                                <th>Type</th>
                                <th>Students</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="scheduleBody">
                            <!-- Dynamic content -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Route Details Modal -->
<div class="modal fade" id="routeDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title">Route Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p><strong>Route Name:</strong> <span id="routeName"></span></p>
                        <p><strong>Type:</strong> <span id="routeType"></span></p>
                        <p><strong>Distance:</strong> <span id="distance"></span> KM</p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Vehicle:</strong> <span id="vehicle"></span></p>
                        <p><strong>Total Students:</strong> <span id="totalStudents"></span></p>
                        <p><strong>Schedule:</strong> <span id="schedule"></span></p>
                    </div>
                </div>

                <!-- Pickup Points -->
                <h6>Pickup/Drop-off Points</h6>
                <div class="table-responsive mb-3">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Order</th>
                                <th>Location</th>
                                <th>Students</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody id="pickupPointsBody">
                            <!-- Dynamic content -->
                        </tbody>
                    </table>
                </div>

                <!-- Students List -->
                <h6>Students on This Route</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Class</th>
                                <th>Pickup Point</th>
                                <th>Contact</th>
                            </tr>
                        </thead>
                        <tbody id="studentsListBody">
                            <!-- Dynamic content -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="printRouteBtn">
                    <i class="bi bi-printer"></i> Print Route Sheet
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Start Trip Modal -->
<div class="modal fade" id="startTripModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Start Trip</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="startTripForm">
                    <div class="mb-3">
                        <label class="form-label">Select Route*</label>
                        <select class="form-select" id="tripRoute" required></select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Trip Type*</label>
                        <select class="form-select" id="tripType" required>
                            <option value="pickup">Pickup (Morning)</option>
                            <option value="dropoff">Drop-off (Evening)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Odometer Reading (KM)*</label>
                        <input type="number" class="form-control" id="odometerStart" required min="0" step="0.1">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Departure Time*</label>
                        <input type="time" class="form-control" id="departureTime" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" id="tripNotes" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmStartTripBtn">Start Trip</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // TODO: Implement myRoutesController in js/pages/my_routes.js
        console.log('My Routes page loaded');
    });
</script>