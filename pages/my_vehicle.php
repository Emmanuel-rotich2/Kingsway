<?php
/**
 * My Vehicle Page (Driver's vehicle management)
 * HTML structure only - logic will be in js/pages/my_vehicle.js
 * Embedded in app_layout.php
 */
?>

<div class="card shadow-sm">
    <div class="card-header bg-gradient bg-dark text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="fas fa-bus"></i> My Vehicle</h4>
            <div class="btn-group">
                <button class="btn btn-light btn-sm" id="logMaintenanceBtn">
                    <i class="bi bi-wrench"></i> Log Maintenance
                </button>
                <button class="btn btn-outline-light btn-sm" id="reportIssueBtn">
                    <i class="bi bi-exclamation-triangle"></i> Report Issue
                </button>
            </div>
        </div>
    </div>

    <div class="card-body">
        <!-- Vehicle Info Card -->
        <div class="card bg-light mb-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <h4 id="vehicleReg">Loading...</h4>
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <p class="mb-2"><strong>Make & Model:</strong> <span id="vehicleModel"></span></p>
                                <p class="mb-2"><strong>Capacity:</strong> <span id="capacity"></span> passengers</p>
                                <p class="mb-2"><strong>Type:</strong> <span id="vehicleType"></span></p>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-2"><strong>Year:</strong> <span id="vehicleYear"></span></p>
                                <p class="mb-2"><strong>Current Mileage:</strong> <span id="currentMileage"></span> KM
                                </p>
                                <p class="mb-2"><strong>Status:</strong> <span id="vehicleStatus" class="badge"></span>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 text-center">
                        <img id="vehicleImage" src="/images/default-vehicle.png" class="img-fluid rounded"
                            style="max-height: 150px;" alt="Vehicle">
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-primary">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Trips This Month</h6>
                        <h3 class="text-primary mb-0" id="tripsMonth">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">KM This Month</h6>
                        <h3 class="text-success mb-0" id="kmMonth">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-warning">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Fuel Cost (KES)</h6>
                        <h3 class="text-warning mb-0" id="fuelCost">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-info">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Next Service</h6>
                        <h3 class="text-info mb-0" id="nextService">-</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <ul class="nav nav-tabs" id="vehicleTabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" id="maintenance-tab" data-bs-toggle="tab" data-bs-target="#maintenance"
                    type="button">
                    Maintenance History
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="issues-tab" data-bs-toggle="tab" data-bs-target="#issues" type="button">
                    Reported Issues
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="fuel-tab" data-bs-toggle="tab" data-bs-target="#fuel" type="button">
                    Fuel Logs
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="documents-tab" data-bs-toggle="tab" data-bs-target="#documents"
                    type="button">
                    Documents
                </button>
            </li>
        </ul>

        <div class="tab-content" id="vehicleTabContent">
            <!-- Maintenance Tab -->
            <div class="tab-pane fade show active" id="maintenance" role="tabpanel">
                <div class="table-responsive mt-3">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Description</th>
                                <th>Mileage (KM)</th>
                                <th>Cost (KES)</th>
                                <th>Garage</th>
                            </tr>
                        </thead>
                        <tbody id="maintenanceBody">
                            <!-- Dynamic content -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Issues Tab -->
            <div class="tab-pane fade" id="issues" role="tabpanel">
                <div class="table-responsive mt-3">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Issue</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Resolved By</th>
                            </tr>
                        </thead>
                        <tbody id="issuesBody">
                            <!-- Dynamic content -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Fuel Tab -->
            <div class="tab-pane fade" id="fuel" role="tabpanel">
                <div class="table-responsive mt-3">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Liters</th>
                                <th>Cost (KES)</th>
                                <th>Mileage (KM)</th>
                                <th>Station</th>
                            </tr>
                        </thead>
                        <tbody id="fuelBody">
                            <!-- Dynamic content -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Documents Tab -->
            <div class="tab-pane fade" id="documents" role="tabpanel">
                <div class="mt-3">
                    <div class="list-group">
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">Insurance Certificate</h6>
                                    <small class="text-muted">Expires: <span id="insuranceExpiry"></span></small>
                                </div>
                                <button class="btn btn-sm btn-outline-primary">View</button>
                            </div>
                        </div>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">PSV License</h6>
                                    <small class="text-muted">Expires: <span id="psvExpiry"></span></small>
                                </div>
                                <button class="btn btn-sm btn-outline-primary">View</button>
                            </div>
                        </div>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">Road Worthiness Certificate</h6>
                                    <small class="text-muted">Expires: <span id="roadworthinessExpiry"></span></small>
                                </div>
                                <button class="btn btn-sm btn-outline-primary">View</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Log Maintenance Modal -->
<div class="modal fade" id="maintenanceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Log Maintenance</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="maintenanceForm">
                    <div class="mb-3">
                        <label class="form-label">Maintenance Type*</label>
                        <select class="form-select" id="maintenanceType" required>
                            <option value="service">Regular Service</option>
                            <option value="oil_change">Oil Change</option>
                            <option value="tire">Tire Replacement</option>
                            <option value="brake">Brake Service</option>
                            <option value="repair">Repair</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description*</label>
                        <textarea class="form-control" id="maintenanceDescription" rows="3" required></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Date*</label>
                            <input type="date" class="form-control" id="maintenanceDate" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Mileage (KM)*</label>
                            <input type="number" class="form-control" id="maintenanceMileage" required min="0">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Cost (KES)*</label>
                        <input type="number" class="form-control" id="maintenanceCost" required min="0" step="0.01">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Garage/Service Center</label>
                        <input type="text" class="form-control" id="garage">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveMaintenanceBtn">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Report Issue Modal -->
<div class="modal fade" id="issueModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">Report Vehicle Issue</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="issueForm">
                    <div class="mb-3">
                        <label class="form-label">Issue Description*</label>
                        <textarea class="form-control" id="issueDescription" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Priority*</label>
                        <select class="form-select" id="issuePriority" required>
                            <option value="low">Low - Can wait</option>
                            <option value="medium">Medium - Needs attention</option>
                            <option value="high">High - Urgent</option>
                            <option value="critical">Critical - Vehicle not operational</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <select class="form-select" id="issueCategory">
                            <option value="mechanical">Mechanical</option>
                            <option value="electrical">Electrical</option>
                            <option value="body">Body/Exterior</option>
                            <option value="safety">Safety</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" id="saveIssueBtn">Report Issue</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // TODO: Implement myVehicleController in js/pages/my_vehicle.js
        console.log('My Vehicle page loaded');
    });
</script>