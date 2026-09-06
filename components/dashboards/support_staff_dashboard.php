<?php
/**
 * Support Staff Dashboard — Profile, department duties, leave, payslips and communications.
 * Role: Kitchen Staff, Security Staff, Janitor, Generic Staff
 */
$rootId = 'supportStaffDashboard';
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

<div class="container-fluid py-4 role-dashboard dashboard-surface dashboard-service" id="<?= $escape($rootId) ?>">
    <?php require __DIR__ . '/partials/period_selector.php'; ?>

    <!-- Meta Bar -->
    <div class="dash-meta-bar mb-4 d-flex align-items-center justify-content-end gap-3 flex-wrap">
        <span class="dash-badge bg-success" id="<?= $escape($rootId) ?>RoleBadge">Staff</span>
        <span class="dash-badge bg-info" id="<?= $escape($rootId) ?>DeptBadge">Department</span>
        <span class="small text-muted">Updated: <span id="<?= $escape($rootId) ?>LastUpdated">—</span></span>
        <button class="btn btn-sm btn-outline-success" id="<?= $escape($rootId) ?>Refresh">
            <i class="bi bi-arrow-clockwise me-1"></i>Refresh
        </button>
    </div>

    <!-- ROW 1: PROFILE HERO — full-width -->
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card dash-card border-start border-4 border-success">
                <div class="card-body">
                    <div class="row align-items-center g-3">
                        <div class="col-md-2 text-center">
                            <div class="dash-profile-avatar mx-auto" id="supportAvatar">ST</div>
                            <div class="mt-2">
                                <span class="badge bg-success" id="supportStatusBadge">Active</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <h4 class="mb-1" id="supportStaffName">—</h4>
                            <div class="text-muted" id="supportStaffRole">—</div>
                            <small class="text-muted" id="supportStaffNo">Staff #: —</small>
                            <div class="mt-2">
                                <dl class="row small mb-0">
                                    <dt class="col-5 text-muted">Department</dt>
                                    <dd class="col-7" id="supportDepartment">—</dd>
                                    <dt class="col-5 text-muted">Supervisor</dt>
                                    <dd class="col-7" id="supportSupervisor">—</dd>
                                    <dt class="col-5 text-muted">Shift</dt>
                                    <dd class="col-7" id="supportShift">—</dd>
                                </dl>
                            </div>
                        </div>
                        <div class="col-md-3 text-center">
                            <div class="mb-2"><strong>Shift Hours</strong></div>
                            <div class="fs-5 fw-bold text-primary" id="supportShiftTime">—</div>
                            <div class="small text-muted" id="supportShiftLabel">Current shift</div>
                        </div>
                        <div class="col-md-3 text-end">
                            <a href="#" data-route="complete_staff_profile" class="btn btn-outline-success btn-sm me-1">
                                <i class="bi bi-person me-1"></i>Profile
                            </a>
                            <a href="#" data-route="detailed_payslip" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-file-earmark-text me-1"></i>Payslips
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ROW 2: DEPARTMENT ALERT — full-width -->
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card dash-card border-start border-4 border-warning" id="supportDeptAlert">
                <div class="card-body">
                    <div class="d-flex align-items-start gap-3">
                        <i class="bi bi-megaphone fs-3 text-warning mt-1"></i>
                        <div>
                            <h6 class="mb-1" id="supportDeptAlertTitle">Department Daily Duties</h6>
                            <p class="mb-0 small" id="supportDeptAlertBody">
                                Loading role-specific daily duties and announcements...
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ROW 3: KPIs — 4 -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="dash-stat dsc-green">
                <i class="bi bi-person-check dash-stat-icon"></i>
                <div class="dash-stat-value" id="supportAttendance">—</div>
                <div class="dash-stat-label">Attendance Today</div>
                <div class="dash-stat-sub" id="supportAttendanceSub">Checked in / out</div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="dash-stat dsc-blue">
                <i class="bi bi-calendar2-week dash-stat-icon"></i>
                <div class="dash-stat-value" id="supportLeaveBalance">—</div>
                <div class="dash-stat-label">Leave Days Available</div>
                <div class="dash-stat-sub" id="supportLeaveSub">across leave types</div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="dash-stat dsc-purple">
                <i class="bi bi-list-task dash-stat-icon"></i>
                <div class="dash-stat-value" id="supportPendingTasks">—</div>
                <div class="dash-stat-label">Pending Tasks</div>
                <div class="dash-stat-sub" id="supportTasksSub">assigned to me</div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="dash-stat dsc-cyan">
                <i class="bi bi-mortarboard dash-stat-icon"></i>
                <div class="dash-stat-value" id="supportTraining">—</div>
                <div class="dash-stat-label">Training Progress</div>
                <div class="dash-stat-sub" id="supportTrainingSub">% completed</div>
            </div>
        </div>
    </div>

    <!-- ROW 4: MY FINANCES & LEAVE — 2 panels -->
    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card dash-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-cash-coin me-2 text-success"></i>Recent Payslips</h6>
                    <button class="btn btn-sm btn-outline-success" data-route="detailed_payslip">History</button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Period</th>
                                    <th scope="col">Gross</th>
                                    <th scope="col">Deductions</th>
                                    <th scope="col">Net Pay</th>
                                    <th scope="col">Status</th>
                                </tr>
                            </thead>
                            <tbody id="supportPayslipsBody">
                                <tr><td colspan="5" class="text-center text-muted py-4">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card dash-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-calendar2-check me-2 text-info"></i>Leave Requests</h6>
                    <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#supportLeaveModal">New Request</button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Type</th>
                                    <th scope="col">Dates</th>
                                    <th scope="col">Days</th>
                                    <th scope="col">Status</th>
                                </tr>
                            </thead>
                            <tbody id="supportLeaveBody">
                                <tr><td colspan="4" class="text-center text-muted py-4">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ROW 5: COMMUNICATIONS — 2 panels -->
    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card dash-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-megaphone me-2 text-primary"></i>Staff Notices</h6>
                    <button class="btn btn-sm btn-outline-primary" data-route="communications/messages_inbox">Inbox</button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Subject</th>
                                    <th scope="col">Source</th>
                                    <th scope="col">Priority</th>
                                    <th scope="col">Date</th>
                                </tr>
                            </thead>
                            <tbody id="supportNoticesBody">
                                <tr><td colspan="4" class="text-center text-muted py-4">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card dash-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-briefcase me-2 text-warning"></i>Internal Opportunities</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Position</th>
                                    <th scope="col">Department</th>
                                    <th scope="col">Deadline</th>
                                    <th scope="col">Status</th>
                                </tr>
                            </thead>
                            <tbody id="supportOpportunitiesBody">
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
                            <button type="button" class="dash-quick-link border-0 text-start w-100" data-bs-toggle="modal" data-bs-target="#supportLeaveModal">
                                <i class="bi bi-calendar-plus ql-icon bg-success text-white"></i>
                                <span>Request Leave</span><i class="bi bi-chevron-right ql-arrow"></i>
                            </button>
                        </div>
                        <div class="col-md-3 col-lg">
                            <button type="button" class="dash-quick-link border-0 text-start w-100" data-bs-toggle="modal" data-bs-target="#supportIncidentModal">
                                <i class="bi bi-exclamation-octagon ql-icon bg-danger text-white"></i>
                                <span>Report Incident</span><i class="bi bi-chevron-right ql-arrow"></i>
                            </button>
                        </div>
                        <div class="col-md-3 col-lg">
                            <a href="#" data-route="detailed_payslip" class="dash-quick-link">
                                <i class="bi bi-file-earmark-text ql-icon bg-primary text-white"></i>
                                <span>Payslips</span><i class="bi bi-chevron-right ql-arrow"></i>
                            </a>
                        </div>
                        <div class="col-md-3 col-lg">
                            <a href="#" data-route="complete_staff_profile" class="dash-quick-link">
                                <i class="bi bi-person ql-icon bg-secondary text-white"></i>
                                <span>Profile</span><i class="bi bi-chevron-right ql-arrow"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Attendance Trend -->
    <div class="row g-3 mb-4">
        <div class="col-xl-7">
            <div class="card dash-card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-graph-up me-2 text-primary"></i>My Attendance — Last 14 Days</h6>
                </div>
                <div class="card-body">
                    <div class="dash-chart-wrap"><canvas id="supportAttendanceChart"></canvas></div>
                </div>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="card dash-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-exclamation-octagon me-2 text-danger"></i>My Incident Reports</h6>
                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#supportIncidentModal">Report</button>
                </div>
                <div class="card-body p-0 dashboard-table-wrap">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead><tr><th scope="col">Reference</th><th scope="col">Category</th><th scope="col">Severity</th><th scope="col">Status</th></tr></thead>
                            <tbody id="supportIncidentsBody"><tr><td colspan="4" class="text-center text-muted py-4">Loading...</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Leave Request Modal -->
<div class="modal fade" id="supportLeaveModal" tabindex="-1" aria-labelledby="supportLeaveModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-dialog-centered">
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

<!-- Incident Report Modal -->
<div class="modal fade" id="supportIncidentModal" tabindex="-1" aria-labelledby="supportIncidentModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-dialog-centered modal-lg">
        <form class="modal-content" id="supportIncidentForm">
            <div class="modal-header">
                <h5 class="modal-title" id="supportIncidentModalTitle">Report Workplace Incident</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-6"><label class="form-label" for="supportIncidentCategory">Category</label><select class="form-select" id="supportIncidentCategory" required><option value="">Select category</option><option value="safety_hazard">Safety hazard</option><option value="workplace_accident">Workplace accident</option><option value="property_damage">Damaged property</option><option value="security_concern">Security concern</option><option value="maintenance">Maintenance issue</option><option value="student_welfare">Student welfare</option><option value="transport">Transport issue</option><option value="kitchen">Kitchen issue</option><option value="other">Other</option></select></div>
                    <div class="col-md-6"><label class="form-label" for="supportIncidentSeverity">Severity</label><select class="form-select" id="supportIncidentSeverity" required><option value="low">Low</option><option value="medium" selected>Medium</option><option value="high">High</option><option value="critical">Critical</option></select></div>
                    <div class="col-md-6"><label class="form-label" for="supportIncidentOccurredAt">Date &amp; time</label><input type="datetime-local" class="form-control" id="supportIncidentOccurredAt" required></div>
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

<?php asset_script($appBase, 'js/dashboards/dashboard_base_controller.js'); ?>
<?php asset_script($appBase, 'js/dashboards/support_staff_dashboard.js'); ?>
