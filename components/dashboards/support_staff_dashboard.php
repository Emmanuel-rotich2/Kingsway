<?php
/**
 * Shared support-staff dashboard.
 *
 * Used by Kitchen Staff, Security Staff, Janitor and Generic Staff. The
 * backend snapshot is role- and department-aware; this page contains only
 * reusable Bootstrap presentation and self-service forms.
 */
?>

<div class="container-fluid py-4 role-dashboard" id="supportStaffDashboard">
    <div class="dash-greeting-bar">
        <div>
            <h5>
                <i class="bi bi-person-workspace me-2"></i>
                My Staff Workspace
            </h5>
            <p>Department updates, attendance, payroll and staff self-service.</p>
        </div>
        <div class="dash-meta">
            <span class="dash-badge" id="supportStaffRole">Staff</span>
            <span class="dash-badge" id="supportStaffDepartment">Department</span>
            <span class="small opacity-75">
                Updated <span id="supportStaffLastUpdated">—</span>
            </span>
            <button type="button" class="dash-refresh-btn" id="supportStaffRefresh">
                <i class="bi bi-arrow-clockwise me-1"></i>Refresh
            </button>
        </div>
    </div>

    <div class="dashboard-state alert alert-light border" id="supportStaffState" role="status">
        Loading staff workspace...
    </div>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="dash-stat dsc-green h-100">
                <i class="bi bi-person-check dash-stat-icon"></i>
                <div class="dash-stat-value" id="supportAttendanceToday">—</div>
                <div class="dash-stat-label">Attendance Today</div>
                <div class="dash-stat-sub" id="supportAttendanceTodaySub">No record yet</div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="dash-stat dsc-blue h-100">
                <i class="bi bi-calendar2-week dash-stat-icon"></i>
                <div class="dash-stat-value" id="supportLeaveBalance">0</div>
                <div class="dash-stat-label">Leave Days Available</div>
                <div class="dash-stat-sub" id="supportLeaveBalanceSub">Across active leave types</div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="dash-stat dsc-purple h-100">
                <i class="bi bi-envelope dash-stat-icon"></i>
                <div class="dash-stat-value" id="supportUnreadMessages">0</div>
                <div class="dash-stat-label">Current Notices</div>
                <div class="dash-stat-sub" id="supportUnreadMessagesSub">Staff announcements and notices</div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="dash-stat dsc-orange h-100">
                <i class="bi bi-briefcase dash-stat-icon"></i>
                <div class="dash-stat-value" id="supportOpenOpportunities">0</div>
                <div class="dash-stat-label">Internal Opportunities</div>
                <div class="dash-stat-sub" id="supportOpenOpportunitiesSub">Open for applications</div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-4">
            <div class="card dash-card">
                <div class="card-header">
                    <h6 class="dashboard-section-title">
                        <i class="bi bi-person-badge"></i>My Profile
                    </h6>
                </div>
                <div class="card-body">
                    <div class="dash-profile-card mb-3">
                        <div class="dash-profile-avatar" id="supportProfileAvatar">ST</div>
                        <div>
                            <h5 class="mb-1" id="supportProfileName">—</h5>
                            <div class="text-muted" id="supportProfilePosition">—</div>
                            <small class="text-muted" id="supportProfileStaffNo">—</small>
                        </div>
                    </div>
                    <dl class="row small mb-0">
                        <dt class="col-5 text-muted">Department</dt>
                        <dd class="col-7" id="supportProfileDepartment">—</dd>
                        <dt class="col-5 text-muted">Supervisor</dt>
                        <dd class="col-7" id="supportProfileSupervisor">—</dd>
                        <dt class="col-5 text-muted">Employment</dt>
                        <dd class="col-7" id="supportProfileEmployment">—</dd>
                        <dt class="col-5 text-muted">Contact</dt>
                        <dd class="col-7" id="supportProfileContact">—</dd>
                    </dl>
                    <button type="button" class="btn btn-outline-success btn-sm mt-3" data-route="complete_staff_profile">
                        <i class="bi bi-pencil-square me-1"></i>View Profile
                    </button>
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="card dash-card">
                <div class="card-header d-flex align-items-center justify-content-between gap-2">
                    <h6 class="dashboard-section-title">
                        <i class="bi bi-megaphone"></i>Staff Messages & Announcements
                    </h6>
                    <button type="button" class="btn btn-sm btn-outline-success" data-route="manage_communications">Open messages</button>
                </div>
                <div class="card-body p-0 dashboard-table-wrap">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr><th>Subject</th><th>Source</th><th>Priority</th><th>Date</th></tr>
                            </thead>
                            <tbody id="supportMessagesBody">
                                <tr><td colspan="4" class="text-center text-muted py-4">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-7">
            <div class="card dash-card">
                <div class="card-header">
                    <h6 class="dashboard-section-title">
                        <i class="bi bi-graph-up"></i>My Attendance — Last 14 Days
                    </h6>
                </div>
                <div class="card-body">
                    <div class="dash-chart-wrap"><canvas id="supportAttendanceChart"></canvas></div>
                </div>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="card dash-card">
                <div class="card-header">
                    <h6 class="dashboard-section-title">
                        <i class="bi bi-lightning-charge"></i>Self-service Actions
                    </h6>
                </div>
                <div class="card-body">
                    <div class="dashboard-action-grid">
                        <button type="button" class="dash-quick-link border-0 text-start" data-bs-toggle="modal" data-bs-target="#supportLeaveModal">
                            <i class="bi bi-calendar-plus ql-icon bg-success text-white"></i><span>Request Leave</span><i class="bi bi-chevron-right ql-arrow"></i>
                        </button>
                        <button type="button" class="dash-quick-link border-0 text-start" data-bs-toggle="modal" data-bs-target="#supportIncidentModal">
                            <i class="bi bi-exclamation-octagon ql-icon bg-danger text-white"></i><span>Report Incident</span><i class="bi bi-chevron-right ql-arrow"></i>
                        </button>
                        <button type="button" class="dash-quick-link border-0 text-start" data-route="detailed_payslip">
                            <i class="bi bi-file-earmark-text ql-icon bg-primary text-white"></i><span>Payslips & P9</span><i class="bi bi-chevron-right ql-arrow"></i>
                        </button>
                        <button type="button" class="dash-quick-link border-0 text-start" data-route="complete_staff_profile">
                            <i class="bi bi-person ql-icon bg-secondary text-white"></i><span>My Profile</span><i class="bi bi-chevron-right ql-arrow"></i>
                        </button>
                    </div>
                    <div class="border-top pt-3 mt-3" id="supportRoleSummary">
                        <small class="text-muted">Role-specific department summary will appear here.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-6">
            <div class="card dash-card">
                <div class="card-header d-flex align-items-center justify-content-between gap-2">
                    <h6 class="dashboard-section-title"><i class="bi bi-cash-coin"></i>Payslips & Tax Documents</h6>
                    <button type="button" class="btn btn-sm btn-outline-success" data-route="detailed_payslip">History</button>
                </div>
                <div class="card-body p-0 dashboard-table-wrap">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead><tr><th>Period</th><th>Net Pay</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                            <tbody id="supportPayslipsBody"><tr><td colspan="4" class="text-center text-muted py-4">Loading...</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card dash-card">
                <div class="card-header d-flex align-items-center justify-content-between gap-2">
                    <h6 class="dashboard-section-title"><i class="bi bi-calendar2-check"></i>My Leave Requests</h6>
                    <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#supportLeaveModal">New request</button>
                </div>
                <div class="card-body p-0 dashboard-table-wrap">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead><tr><th>Type</th><th>Dates</th><th>Days</th><th>Status</th></tr></thead>
                            <tbody id="supportLeaveBody"><tr><td colspan="4" class="text-center text-muted py-4">Loading...</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-7">
            <div class="card dash-card">
                <div class="card-header">
                    <h6 class="dashboard-section-title"><i class="bi bi-briefcase"></i>Internal Opportunities</h6>
                </div>
                <div class="card-body p-0 dashboard-table-wrap">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead><tr><th>Position</th><th>Department</th><th>Deadline</th><th>Status</th><th class="text-end">Action</th></tr></thead>
                            <tbody id="supportOpportunitiesBody"><tr><td colspan="5" class="text-center text-muted py-4">Loading...</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="card dash-card">
                <div class="card-header d-flex align-items-center justify-content-between gap-2">
                    <h6 class="dashboard-section-title"><i class="bi bi-exclamation-octagon"></i>My Incident Reports</h6>
                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#supportIncidentModal">Report</button>
                </div>
                <div class="card-body p-0 dashboard-table-wrap">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead><tr><th>Reference</th><th>Category</th><th>Severity</th><th>Status</th></tr></thead>
                            <tbody id="supportIncidentsBody"><tr><td colspan="4" class="text-center text-muted py-4">Loading...</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="supportLeaveModal" tabindex="-1" aria-labelledby="supportLeaveModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="supportLeaveForm">
            <div class="modal-header">
                <h5 class="modal-title" id="supportLeaveModalTitle">Request Leave</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="supportLeaveType">Leave type</label>
                    <select class="form-select" id="supportLeaveType" required><option value="">Select leave type</option></select>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-sm-6"><label class="form-label" for="supportLeaveStart">Start date</label><input type="date" class="form-control" id="supportLeaveStart" required></div>
                    <div class="col-sm-6"><label class="form-label" for="supportLeaveEnd">End date</label><input type="date" class="form-control" id="supportLeaveEnd" required></div>
                </div>
                <div><label class="form-label" for="supportLeaveReason">Reason</label><textarea class="form-control" id="supportLeaveReason" rows="4" required></textarea></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success" id="supportLeaveSubmit">Submit request</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="supportIncidentModal" tabindex="-1" aria-labelledby="supportIncidentModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form class="modal-content" id="supportIncidentForm">
            <div class="modal-header">
                <h5 class="modal-title" id="supportIncidentModalTitle">Report Workplace Incident</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-6"><label class="form-label" for="supportIncidentCategory">Category</label><select class="form-select" id="supportIncidentCategory" required><option value="">Select category</option><option value="safety_hazard">Safety hazard</option><option value="workplace_accident">Workplace accident</option><option value="property_damage">Damaged property</option><option value="security_concern">Security concern</option><option value="harassment">Harassment</option><option value="maintenance">Maintenance issue</option><option value="student_welfare">Student welfare</option><option value="transport">Transport issue</option><option value="kitchen">Kitchen issue</option><option value="other">Other</option></select></div>
                    <div class="col-md-6"><label class="form-label" for="supportIncidentSeverity">Severity</label><select class="form-select" id="supportIncidentSeverity" required><option value="low">Low</option><option value="medium" selected>Medium</option><option value="high">High</option><option value="critical">Critical</option></select></div>
                    <div class="col-md-6"><label class="form-label" for="supportIncidentOccurredAt">Date and time</label><input type="datetime-local" class="form-control" id="supportIncidentOccurredAt" required></div>
                    <div class="col-md-6"><label class="form-label" for="supportIncidentLocation">Location</label><input type="text" class="form-control" id="supportIncidentLocation" maxlength="255" required></div>
                </div>
                <div class="mb-3"><label class="form-label" for="supportIncidentDescription">Description</label><textarea class="form-control" id="supportIncidentDescription" rows="4" required></textarea></div>
                <div><label class="form-label" for="supportIncidentAction">Immediate action taken</label><textarea class="form-control" id="supportIncidentAction" rows="2"></textarea></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger" id="supportIncidentSubmit">Submit report</button>
            </div>
        </form>
    </div>
</div>

<script src="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/js/dashboards/dashboard_base_controller.js?v=<?= filemtime(__DIR__ . '/../../js/dashboards/dashboard_base_controller.js') ?>"></script>
<script src="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/js/dashboards/support_staff_dashboard.js?v=<?= filemtime(__DIR__ . '/../../js/dashboards/support_staff_dashboard.js') ?>"></script>
