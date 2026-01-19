<?php
/**
 * Discipline Cases - Stateless JWT-based Router
 *
 * Uses JavaScript to determine user role from JWT token and load appropriate template
 */

// Default template (will be overridden by JavaScript)
$template = 'discipline/manager_discipline.php'; // Default fallback

// Include the template (JavaScript will replace content based on role)
include __DIR__ . '/' . $template;
exit;
?>

// Include the appropriate template
include __DIR__ . '/' . $template;
exit;

// Legacy template below (kept for reference, not executed)
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1"><i class="fas fa-gavel me-2"></i>Discipline Cases</h4>
                    <p class="text-muted mb-0">Manage student discipline incidents and resolutions</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newCaseModal">
                    <i class="fas fa-plus me-1"></i> New Case
                </button>
            </div>
        </div>
    </div>

    <!-- Stats -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-left-warning">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="text-warning" id="openCases">0</h5>
                            <p class="text-muted mb-0">Open Cases</p>
                        </div>
                        <i class="fas fa-exclamation-triangle fa-2x text-warning"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-left-info">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="text-info" id="underReview">0</h5>
                            <p class="text-muted mb-0">Under Review</p>
                        </div>
                        <i class="fas fa-search fa-2x text-info"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-left-success">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="text-success" id="resolvedCases">0</h5>
                            <p class="text-muted mb-0">Resolved</p>
                        </div>
                        <i class="fas fa-check-circle fa-2x text-success"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-left-danger">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="text-danger" id="escalatedCases">0</h5>
                            <p class="text-muted mb-0">Escalated</p>
                        </div>
                        <i class="fas fa-arrow-up fa-2x text-danger"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Cases Table -->
    <div class="card">
        <div class="card-header">
            <ul class="nav nav-tabs card-header-tabs">
                <li class="nav-item">
                    <a class="nav-link active" data-bs-toggle="tab" href="#allCases">All Cases</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#openTab">Open</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#resolvedTab">Resolved</a>
                </li>
            </ul>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="casesTable">
                    <thead>
                        <tr>
                            <th>Case #</th>
                            <th>Date</th>
                            <th>Student</th>
                            <th>Class</th>
                            <th>Type</th>
                            <th>Severity</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="8" class="text-center">Loading cases...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- New Case Modal -->
<div class="modal fade" id="newCaseModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">New Discipline Case</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="newCaseForm">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Student</label>
                            <select class="form-select" name="student_id" required>
                                <option value="">Select Student</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Incident Date</label>
                            <input type="date" class="form-control" name="incident_date" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Type</label>
                            <select class="form-select" name="type" required>
                                <option value="">Select Type</option>
                                <option value="minor">Minor Offense</option>
                                <option value="major">Major Offense</option>
                                <option value="bullying">Bullying</option>
                                <option value="truancy">Truancy</option>
                                <option value="property_damage">Property Damage</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Severity</label>
                            <select class="form-select" name="severity" required>
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="4" required></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Action Taken</label>
                            <textarea class="form-control" name="action_taken" rows="2"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="newCaseForm" class="btn btn-primary">Create Case</button>
            </div>
        </div>
    </div>
</div>

<script src="js/pages/discipline_cases.js"></script>