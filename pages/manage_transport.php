<?php
/**
 * Manage Transport Page - Role-Based Router
 * Routes to appropriate template based on user role category
 */

require_once __DIR__ . '/../config/permissions.php';

session_start();
$roleCategory = getRoleCategory($_SESSION['role'] ?? 'Student');

// Route to role-specific template
$templateMap = [
    'admin' => 'transport/admin_transport.php',
    'manager' => 'transport/manager_transport.php',
    'operator' => 'transport/operator_transport.php',
    'viewer' => 'transport/viewer_transport.php'
];

$template = $templateMap[$roleCategory] ?? $templateMap['viewer'];
include __DIR__ . '/' . $template;
exit;
?>

<!-- Legacy fallback (should not reach here) -->
<div class="card shadow-sm">
    <div class="card-header bg-gradient bg-warning text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="bi bi-bus-front-fill"></i> Transport Management</h4>
            <div class="btn-group">
                <button class="btn btn-light btn-sm" onclick="transportController.showVehicleModal()"
                    data-permission="transport_create">
                    <i class="bi bi-plus-circle"></i> Add Vehicle
                </button>
                <button class="btn btn-outline-light btn-sm" onclick="transportController.showRouteModal()"
                    data-permission="transport_create">
                    <i class="bi bi-signpost"></i> Add Route
                </button>
                <button class="btn btn-outline-light btn-sm" onclick="transportController.exportData()">
                    <i class="bi bi-download"></i> Export
                </button>
            </div>
        </div>
    </div>

    <div class="card-body">
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-warning">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Total Vehicles</h6>
                        <h3 class="text-warning mb-0" id="totalVehiclesCount">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Active Routes</h6>
                        <h3 class="text-success mb-0" id="activeRoutesCount">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-info">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Drivers</h6>
                        <h3 class="text-info mb-0" id="driversCount">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-danger">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Maintenance</h6>
                        <h3 class="text-danger mb-0" id="maintenanceCount">0</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab Navigation -->
        <ul class="nav nav-tabs mb-3" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="tab" href="#vehiclesTab"
                    onclick="transportController.loadVehicles()">
                    <i class="bi bi-truck"></i> Vehicles
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#routesTab" onclick="transportController.loadRoutes()">
                    <i class="bi bi-signpost-2"></i> Routes
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#driversTab" onclick="transportController.loadDrivers()">
                    <i class="bi bi-person-badge"></i> Drivers
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#assignmentsTab"
                    onclick="transportController.loadAssignments()">
                    <i class="bi bi-list-check"></i> Student Assignments
                </a>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content">
            <!-- Vehicles Tab -->
            <div class="tab-pane fade show active" id="vehiclesTab">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" id="searchVehicles" class="form-control" placeholder="Search vehicles..."
                                onkeyup="transportController.searchVehicles(this.value)">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select id="vehicleStatusFilter" class="form-select"
                            onchange="transportController.filterVehiclesByStatus(this.value)">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select id="vehicleTypeFilter" class="form-select"
                            onchange="transportController.filterVehiclesByType(this.value)">
                            <option value="">All Types</option>
                            <option value="bus">Bus</option>
                            <option value="van">Van</option>
                            <option value="car">Car</option>
                        </select>
                    </div>
                </div>
                <div class="table-responsive" id="vehiclesTableContainer">
                    <table class="table table-hover table-striped">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Vehicle Name</th>
                                <th>Registration</th>
                                <th>Type</th>
                                <th>Capacity</th>
                                <th>Route</th>
                                <th>Driver</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="vehiclesTableBody">
                            <tr>
                                <td colspan="9" class="text-center py-4">
                                    <div class="spinner-border text-warning" role="status"></div>
                                    <p class="text-muted mt-2">Loading vehicles...</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Routes Tab -->
            <div class="tab-pane fade" id="routesTab">
                <div id="routesContainer">
                    <p class="text-muted">Loading routes...</p>
                </div>
            </div>

            <!-- Drivers Tab -->
            <div class="tab-pane fade" id="driversTab">
                <div id="driversContainer">
                    <p class="text-muted">Loading drivers...</p>
                </div>
            </div>

            <!-- Assignments Tab -->
            <div class="tab-pane fade" id="assignmentsTab">
                <div id="assignmentsContainer">
                    <p class="text-muted">Loading student assignments...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Vehicle Modal -->
<div class="modal fade" id="vehicleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-white">
                <h5 class="modal-title" id="vehicleModalLabel">Add Vehicle</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="vehicleForm" onsubmit="transportController.saveVehicle(event)">
                <div class="modal-body">
                    <input type="hidden" id="vehicleId">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Vehicle Name <span class="text-danger">*</span></label>
                            <input type="text" id="vehicleName" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Registration Number <span class="text-danger">*</span></label>
                            <input type="text" id="vehicleRegistration" class="form-control" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Type <span class="text-danger">*</span></label>
                            <select id="vehicleType" class="form-select" required>
                                <option value="">Select Type</option>
                                <option value="bus">Bus</option>
                                <option value="van">Van</option>
                                <option value="car">Car</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Capacity <span class="text-danger">*</span></label>
                            <input type="number" id="vehicleCapacity" class="form-control" required min="1">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Status <span class="text-danger">*</span></label>
                            <select id="vehicleStatus" class="form-select" required>
                                <option value="active">Active</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Route</label>
                            <select id="vehicleRoute" class="form-select">
                                <option value="">Select Route</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Driver</label>
                            <select id="vehicleDriver" class="form-select">
                                <option value="">Select Driver</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-save"></i> Save Vehicle
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Route Modal -->
<div class="modal fade" id="routeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Add Route</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="routeForm" onsubmit="transportController.saveRoute(event)">
                <div class="modal-body">
                    <input type="hidden" id="routeId">
                    <div class="mb-3">
                        <label class="form-label">Route Name <span class="text-danger">*</span></label>
                        <input type="text" id="routeName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea id="routeDescription" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status <span class="text-danger">*</span></label>
                        <select id="routeStatus" class="form-select" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-save"></i> Save Route
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Link Controller Script -->
<script src="/Kingsway/js/pages/transport.js"></script>