<?php
/**
 * Staff Discipline — staff disciplinary handling workspace.
 *
 * Used by Deputy Heads, Headteacher, School Administrator and Director.
 * Cases escalate to Deputy Head - Discipline; the senior roles see and act on
 * every case. Business logic lives in the JS controller.
 */

// Ensure $appBase is available for script loading
$appBase = $appBase ?? '';
?>
<div class="container-fluid py-4" id="staffDisciplinePage">

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-dark text-white">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h4 class="mb-0">
                        <i class="bi bi-person-badge me-2"></i>
                        Staff Disciplinary Cases
                    </h4>
                    <small id="staffDisciplineScope">Misconduct, negligence and attendance cases against staff</small>
                </div>
                <div class="btn-group">
                    <button class="btn btn-light btn-sm" id="staffDisciplineExportBtn">
                        <i class="bi bi-download"></i> Export CSV
                    </button>
                    <button class="btn btn-outline-light btn-sm" id="staffDisciplinePrintBtn">
                        <i class="bi bi-printer"></i> Print / PDF
                    </button>
                    <button class="btn btn-warning btn-sm" id="addStaffDisciplineBtn" data-permission="staff_discipline_create">
                        <i class="bi bi-plus-circle"></i> Log Case
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body">

            <!-- Summary cards -->
            <div class="row mb-4" id="staffDisciplineSummary">
                <div class="col-md-3">
                    <div class="card border-secondary h-100"><div class="card-body text-center">
                        <h6 class="text-muted mb-2">Total Cases</h6>
                        <h3 class="mb-0" id="sdTotal">0</h3>
                    </div></div>
                </div>
                <div class="col-md-2">
                    <div class="card border-warning h-100"><div class="card-body text-center">
                        <h6 class="text-muted mb-2">Open</h6>
                        <h3 class="text-warning mb-0" id="sdOpen">0</h3>
                    </div></div>
                </div>
                <div class="col-md-2">
                    <div class="card border-primary h-100"><div class="card-body text-center">
                        <h6 class="text-muted mb-2">In Review</h6>
                        <h3 class="text-primary mb-0" id="sdInReview">0</h3>
                    </div></div>
                </div>
                <div class="col-md-2">
                    <div class="card border-danger h-100"><div class="card-body text-center">
                        <h6 class="text-muted mb-2">Escalated</h6>
                        <h3 class="text-danger mb-0" id="sdEscalated">0</h3>
                    </div></div>
                </div>
                <div class="col-md-3">
                    <div class="card border-success h-100"><div class="card-body text-center">
                        <h6 class="text-muted mb-2">Resolved / Dismissed</h6>
                        <h3 class="text-success mb-0" id="sdResolved">0</h3>
                    </div></div>
                </div>
            </div>

            <!-- Filters: auto-load on every change -->
            <div class="row g-2 mb-3">
                <div class="col-md-3">
                    <input type="text" class="form-control" id="sdSearch" placeholder="Search case no, staff, description…">
                </div>
                <div class="col-md-2">
                    <select class="form-select" id="sdStatusFilter">
                        <option value="">All statuses</option>
                        <option value="open">Open</option>
                        <option value="in_review">In Review</option>
                        <option value="escalated">Escalated</option>
                        <option value="resolved">Resolved</option>
                        <option value="dismissed">Dismissed</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" id="sdSeverityFilter">
                        <option value="">All severities</option>
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                        <option value="critical">Critical</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" id="sdDepartmentFilter">
                        <option value="">All departments</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <div class="input-group">
                        <input type="date" class="form-control" id="sdFromFilter" aria-label="From date">
                        <input type="date" class="form-control" id="sdToFilter" aria-label="To date">
                    </div>
                </div>
            </div>

            <!-- Cases table -->
            <div class="table-responsive">
                <table class="table table-hover align-middle" id="staffDisciplineTable" aria-label="Staff disciplinary cases">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Case No</th>
                            <th scope="col">Staff</th>
                            <th scope="col">Department</th>
                            <th scope="col">Category</th>
                            <th scope="col">Severity</th>
                            <th scope="col">Incident Date</th>
                            <th scope="col">Status</th>
                            <th scope="col">Assigned To</th>
                            <th scope="col" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="staffDisciplineBody"></tbody>
                </table>
            </div>
            <div class="text-center text-muted py-4 d-none" id="staffDisciplineEmpty">
                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                No staff disciplinary cases found for the selected filters.
            </div>
        </div>
    </div>
</div>

<!-- Log case modal -->
<div class="modal fade" id="staffDisciplineModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="staffDisciplineForm">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Log Staff Disciplinary Case</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="sdStaffSelect">Staff member <span class="text-danger">*</span></label>
                            <select class="form-select" id="sdStaffSelect" required></select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="sdCategory">Category</label>
                            <select class="form-select" id="sdCategory">
                                <option value="misconduct">Misconduct</option>
                                <option value="negligence">Negligence</option>
                                <option value="absenteeism">Absenteeism</option>
                                <option value="lateness">Lateness</option>
                                <option value="professional_misconduct">Professional misconduct</option>
                                <option value="other" selected>Other</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="sdSeverity">Severity</label>
                            <select class="form-select" id="sdSeverity">
                                <option value="low" selected>Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="sdIncidentDate">Incident date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="sdIncidentDate" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="sdDepartmentSelect">Department</label>
                            <select class="form-select" id="sdDepartmentSelect">
                                <option value="">—</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="sdDescription">Description <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="sdDescription" rows="3" required
                                placeholder="What happened? Facts only — the case is reviewed before any action."></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="sdActionTaken">Immediate action taken (optional)</label>
                            <textarea class="form-control" id="sdActionTaken" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="alert alert-info mt-3 mb-0 py-2 small">
                        <i class="bi bi-shield-check me-1"></i>
                        High and critical cases escalate straight to Deputy Head - Discipline.
                        Discipline decisions affecting a staff member are reviewed before action.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Log Case</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?php echo $appBase; ?>/js/pages/staff_discipline.js?v=<?php echo asset_version('js/pages/staff_discipline.js'); ?>"></script>
