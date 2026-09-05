<?php
/**
 * School Counselor / Chaplain Dashboard — Cases, follow-ups, wellbeing and pastoral care.
 * Role: School Counselor / Chaplain
 */
$rootId = 'counselorDashboard';
$periods = [
    ['key' => 'today', 'label' => 'Today'],
    ['key' => 'week', 'label' => 'This Week'],
    ['key' => 'term', 'label' => 'This Term'],
];
$default = 'week';

$escape = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};
?>

<div class="container-fluid py-4 role-dashboard dashboard-surface dashboard-wellbeing" id="<?= $escape($rootId) ?>">
    <?php require __DIR__ . '/partials/period_selector.php'; ?>

    <!-- Meta Bar -->
    <div class="dash-meta-bar mb-4 d-flex align-items-center justify-content-end gap-3 flex-wrap">
        <span class="dash-badge bg-danger" id="<?= $escape($rootId) ?>RoleBadge">Counseling</span>
        <span class="dash-badge bg-info" id="<?= $escape($rootId) ?>TermBadge">—</span>
        <span class="small text-muted">Updated: <span id="<?= $escape($rootId) ?>LastUpdated">—</span></span>
        <button class="btn btn-sm btn-outline-success" id="<?= $escape($rootId) ?>Refresh">
            <i class="bi bi-arrow-clockwise me-1"></i>Refresh
        </button>
    </div>

    <!-- CHAPLAINCY / SPIRITUAL MINISTRY KPIs (SDA) -->
    <div class="row g-3 mb-2">
        <div class="col-12">
            <div class="dash-section-title d-flex align-items-center gap-2">
                <i class="bi bi-stars text-danger"></i>
                <h6 class="mb-0">Chaplaincy &amp; Spiritual Ministry</h6>
                <span class="dash-badge bg-danger">Sabbath (Saturday)</span>
                <button class="btn btn-sm btn-outline-primary ms-auto" data-route="chaplaincy_programs">
                    Spiritual Programs
                </button>
                <button class="btn btn-sm btn-outline-info" data-route="chaplaincy_pastoral">
                    Groups &amp; Pastoral
                </button>
            </div>
        </div>
    </div>
    <div class="row g-3 mb-4">
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-purple">
                <i class="bi bi-buildings dash-stat-icon"></i>
                <div class="dash-stat-value" id="chpSabbathWeek">—</div>
                <div class="dash-stat-label">Sabbath Services This Week</div>
                <div class="dash-stat-sub" id="chpSabbathWeekSub">scheduled Saturday worship</div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-indigo">
                <i class="bi bi-calendar-event dash-stat-icon"></i>
                <div class="dash-stat-value" id="chpSessionsWeek2">—</div>
                <div class="dash-stat-label">Program Sessions This Week</div>
                <div class="dash-stat-sub" id="chpSessionsWeek2Sub">all chaplaincy sessions</div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-teal">
                <i class="bi bi-clipboard-check dash-stat-icon"></i>
                <div class="dash-stat-value" id="chpAttendanceWeek">—</div>
                <div class="dash-stat-label">Attendance Recorded This Week</div>
                <div class="dash-stat-sub" id="chpAttendanceWeekSub">present marks</div>
            </div>
        </div>
    </div>
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="dash-stat dsc-green">
                <i class="bi bi-people dash-stat-icon"></i>
                <div class="dash-stat-value" id="chpActiveGroups">—</div>
                <div class="dash-stat-label">Active Spiritual Groups</div>
                <div class="dash-stat-sub" id="chpActiveGroupsSub">Pathfinders, AY, choirs…</div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="dash-stat dsc-blue">
                <i class="bi bi-person-check dash-stat-icon"></i>
                <div class="dash-stat-value" id="chpGroupMembers">—</div>
                <div class="dash-stat-label">Active Group Members</div>
                <div class="dash-stat-sub" id="chpGroupMembersSub">students &amp; staff</div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="dash-stat dsc-orange">
                <i class="bi bi-flag dash-stat-icon"></i>
                <div class="dash-stat-value" id="chpPastoralOverdue">—</div>
                <div class="dash-stat-label">Pastoral Follow-ups Overdue</div>
                <div class="dash-stat-sub" id="chpPastoralOverdueSub">open / follow-up visits</div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="dash-stat dsc-red">
                <i class="bi bi-crosshair dash-stat-icon"></i>
                <div class="dash-stat-value" id="chpBaptized">—</div>
                <div class="dash-stat-label">Baptized Learners</div>
                <div class="dash-stat-sub" id="chpBaptizedSub">spiritual profile</div>
            </div>
        </div>
    </div>

    <!-- ROW 1: CASE SUMMARY — 6 KPIs -->
    <div class="row g-3 mb-3">
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-blue">
                <i class="bi bi-folder2-open dash-stat-icon"></i>
                <div class="dash-stat-value" id="chpOpenCases">—</div>
                <div class="dash-stat-label">Open Cases</div>
                <div class="dash-stat-sub" id="chpOpenSub">active</div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-red">
                <i class="bi bi-exclamation-triangle dash-stat-icon"></i>
                <div class="dash-stat-value" id="chpUrgentCases">—</div>
                <div class="dash-stat-label">Urgent Cases</div>
                <div class="dash-stat-sub" id="chpUrgentSub">high priority</div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-orange">
                <i class="bi bi-calendar-check dash-stat-icon"></i>
                <div class="dash-stat-value" id="chpFollowUps">—</div>
                <div class="dash-stat-label">Follow-ups Due</div>
                <div class="dash-stat-sub" id="chpFollowUpsSub">this week</div>
            </div>
        </div>
    </div>
    <div class="row g-3 mb-4">
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-green">
                <i class="bi bi-chat-heart dash-stat-icon"></i>
                <div class="dash-stat-value" id="chpSessionsWeek">—</div>
                <div class="dash-stat-label">Sessions This Week</div>
                <div class="dash-stat-sub" id="chpSessionsSub">completed</div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-teal">
                <i class="bi bi-check-circle dash-stat-icon"></i>
                <div class="dash-stat-value" id="chpResolvedMonth">—</div>
                <div class="dash-stat-label">Resolved This Month</div>
                <div class="dash-stat-sub" id="chpResolvedSub">cases closed</div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-indigo">
                <i class="bi bi-arrow-left-circle dash-stat-icon"></i>
                <div class="dash-stat-value" id="chpReferrals">—</div>
                <div class="dash-stat-label">Referrals Received</div>
                <div class="dash-stat-sub" id="chpReferralsSub">this month</div>
            </div>
        </div>
    </div>

    <!-- ROW 2: CASE TIMELINE — full-width -->
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card dash-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-clock-history me-2 text-primary"></i>Recent Session Timeline</h6>
                    <button class="btn btn-sm btn-outline-primary" data-route="student_counseling">All Cases</button>
                </div>
                <div class="card-body">
                    <div class="dash-timeline" id="chpTimeline">
                        <div class="text-center text-muted py-3">
                            <div class="spinner-border spinner-border-sm me-2" role="status"></div>Loading session history...
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ROW 3: CASE ANALYTICS — 2 panels -->
    <div class="row g-3 mb-4">
        <div class="col-lg-5">
            <div class="card dash-card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-pie-chart me-2 text-danger"></i>Cases by Type</h6>
                </div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <div class="dash-chart-wrap-lg"><canvas id="chpTypeChart"></canvas></div>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card dash-card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-graph-up me-2 text-success"></i>Counseling Trend</h6>
                    <small class="text-muted">Cases opened vs resolved</small>
                </div>
                <div class="card-body">
                    <div class="dash-chart-wrap-lg"><canvas id="chpTrendChart"></canvas></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ROW 4: ACTIVE CASES — full-width table -->
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card dash-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-list-ul me-2 text-primary"></i>Active Cases</h6>
                    <button class="btn btn-sm btn-outline-success" data-route="student_counseling">Manage Cases</button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Case ID</th>
                                    <th scope="col">Student</th>
                                    <th scope="col">Type</th>
                                    <th scope="col">Priority</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Next Follow-up</th>
                                </tr>
                            </thead>
                            <tbody id="chpCasesBody">
                                <tr><td colspan="6" class="text-center text-muted py-4">
                                    <div class="spinner-border spinner-border-sm me-2" role="status"></div>Loading cases...
                                </td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ROW 5: WELLBEING INDICATORS — 2 panels -->
    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card dash-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-person-exclamation me-2 text-danger"></i>Wellbeing Flags</h6>
                    <small class="text-muted">Students flagged for concern</small>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Student</th>
                                    <th scope="col">Concern</th>
                                    <th scope="col">Severity</th>
                                    <th scope="col">Status</th>
                                </tr>
                            </thead>
                            <tbody id="chpWellbeingBody">
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
                    <h6 class="mb-0"><i class="bi bi-arrow-left-circle me-2 text-info"></i>Referral Sources</h6>
                </div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <div class="dash-chart-wrap-lg"><canvas id="chpReferralChart"></canvas></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Wellbeing by Grade -->
    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card dash-card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-bar-chart me-2 text-indigo"></i>Cases by Grade</h6>
                </div>
                <div class="card-body">
                    <div class="dash-chart-wrap-lg"><canvas id="chpGradeChart"></canvas></div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card dash-card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-clipboard-data me-2 text-success"></i>Session Outcomes Summary</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Outcome</th>
                                    <th scope="col" class="text-center">Count</th>
                                    <th scope="col" class="text-center">%</th>
                                </tr>
                            </thead>
                            <tbody id="chpOutcomeBody">
                                <tr><td colspan="3" class="text-center text-muted py-4">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Referral Tracking -->
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card dash-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-arrow-left-circle me-2 text-info"></i>Recent Referrals</h6>
                    <button class="btn btn-sm btn-outline-info" data-route="student_counseling">View All</button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Date</th>
                                    <th scope="col">Student</th>
                                    <th scope="col">Referred By</th>
                                    <th scope="col">Reason</th>
                                    <th scope="col">Status</th>
                                </tr>
                            </thead>
                            <tbody id="chpReferralsBody">
                                <tr><td colspan="5" class="text-center text-muted py-4">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Upcoming Follow-ups -->
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card dash-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-calendar2-week me-2 text-warning"></i>Upcoming Follow-ups</h6>
                    <button class="btn btn-sm btn-outline-warning" data-route="student_counseling">View All</button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Student</th>
                                    <th scope="col">Case</th>
                                    <th scope="col">Type</th>
                                    <th scope="col">Date</th>
                                    <th scope="col">Status</th>
                                </tr>
                            </thead>
                            <tbody id="chpFollowUpBody">
                                <tr><td colspan="5" class="text-center text-muted py-4">Loading...</td></tr>
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
                            <a href="#" data-route="student_counseling" class="dash-quick-link">
                                <i class="bi bi-heart-pulse ql-icon bg-primary text-white"></i>
                                <span>Counseling Cases</span><i class="bi bi-chevron-right ql-arrow"></i>
                            </a>
                        </div>
                        <div class="col-md-3 col-lg">
                            <a href="#" data-route="student_counseling" class="dash-quick-link">
                                <i class="bi bi-plus-circle ql-icon bg-success text-white"></i>
                                <span>New Session</span><i class="bi bi-chevron-right ql-arrow"></i>
                            </a>
                        </div>
                        <div class="col-md-3 col-lg">
                            <a href="#" data-route="student_welfare" class="dash-quick-link">
                                <i class="bi bi-person-hearts ql-icon bg-info text-white"></i>
                                <span>Student Welfare</span><i class="bi bi-chevron-right ql-arrow"></i>
                            </a>
                        </div>
                        <div class="col-md-3 col-lg">
                            <a href="#" data-route="manage_announcements" class="dash-quick-link">
                                <i class="bi bi-megaphone ql-icon bg-warning text-white"></i>
                                <span>Announcements</span><i class="bi bi-chevron-right ql-arrow"></i>
                            </a>
                        </div>
                        <div class="col-md-3 col-lg">
                            <a href="#" data-route="student_counseling" class="dash-quick-link">
                                <i class="bi bi-file-earmark-text ql-icon bg-danger text-white"></i>
                                <span>Case Reports</span><i class="bi bi-chevron-right ql-arrow"></i>
                            </a>
                        </div>
                        <div class="col-md-3 col-lg">
                            <a href="#" data-route="manage_communications" class="dash-quick-link">
                                <i class="bi bi-chat-dots ql-icon bg-secondary text-white"></i>
                                <span>Communicate</span><i class="bi bi-chevron-right ql-arrow"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<?php asset_script($appBase, 'js/dashboards/dashboard_base_controller.js'); ?>
<?php asset_script($appBase, 'js/dashboards/school_counselor_chaplain_dashboard.js'); ?>
