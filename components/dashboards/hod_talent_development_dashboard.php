<?php
/**
 * HOD Talent Development Dashboard — Activities, events, participation and budget.
 * Role: HOD Talent Development
 */
$rootId = 'talentDevDashboard';
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

<div class="container-fluid py-4 role-dashboard dashboard-surface dashboard-talent" id="<?= $escape($rootId) ?>">
    <?php require __DIR__ . '/partials/period_selector.php'; ?>

    <!-- Meta Bar -->
    <div class="dash-meta-bar mb-4 d-flex align-items-center justify-content-end gap-3 flex-wrap">
        <span class="dash-badge bg-purple" id="<?= $escape($rootId) ?>DeptBadge">Talent Development</span>
        <span class="small text-muted">Updated: <span id="<?= $escape($rootId) ?>LastUpdated">—</span></span>
        <button class="btn btn-sm btn-outline-success" id="<?= $escape($rootId) ?>Refresh">
            <i class="bi bi-arrow-clockwise me-1"></i>Refresh
        </button>
    </div>

    <!-- ROW 1: ACTIVITY SUMMARY — 6 KPIs -->
    <div class="row g-3 mb-3">
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-blue">
                <i class="bi bi-trophy dash-stat-icon"></i>
                <div class="dash-stat-value" id="talActiveActivities">—</div>
                <div class="dash-stat-label">Active Activities</div>
                <div class="dash-stat-sub" id="talActiveSub">running this term</div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-indigo">
                <i class="bi bi-people dash-stat-icon"></i>
                <div class="dash-stat-value" id="talParticipants">—</div>
                <div class="dash-stat-label">Student Participants</div>
                <div class="dash-stat-sub" id="talParticipantsSub">enrolled</div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-cyan">
                <i class="bi bi-calendar-event dash-stat-icon"></i>
                <div class="dash-stat-value" id="talUpcomingEvents">—</div>
                <div class="dash-stat-label">Upcoming Events</div>
                <div class="dash-stat-sub" id="talUpcomingSub">this month</div>
            </div>
        </div>
    </div>
    <div class="row g-3 mb-4">
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-green">
                <i class="bi bi-calendar-check dash-stat-icon"></i>
                <div class="dash-stat-value" id="talEventsThisMonth">—</div>
                <div class="dash-stat-label">Events This Month</div>
                <div class="dash-stat-sub" id="talEventsSub">completed + scheduled</div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-amber">
                <i class="bi bi-piggy-bank dash-stat-icon"></i>
                <div class="dash-stat-value" id="talBudgetUtilized">—</div>
                <div class="dash-stat-label">Budget Utilized</div>
                <div class="dash-stat-sub" id="talBudgetSub">% of allocation</div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-teal">
                <i class="bi bi-graph-up dash-stat-icon"></i>
                <div class="dash-stat-value" id="talParticipationRate">—</div>
                <div class="dash-stat-label">Participation Rate</div>
                <div class="dash-stat-sub" id="talParticipationSub">% of student body</div>
            </div>
        </div>
    </div>

    <!-- ROW 2: WEEKLY CALENDAR — full-width -->
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card dash-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-calendar-week me-2 text-primary"></i>This Week's Activities</h6>
                    <button class="btn btn-sm btn-outline-primary" data-route="school_events">Full Calendar</button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Day</th>
                                    <th scope="col">Time</th>
                                    <th scope="col">Activity</th>
                                    <th scope="col">Venue</th>
                                    <th scope="col">Category</th>
                                </tr>
                            </thead>
                            <tbody id="talWeeklyBody">
                                <tr><td colspan="5" class="text-center text-muted py-4">
                                    <div class="spinner-border spinner-border-sm me-2" role="status"></div>Loading weekly activities...
                                </td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ROW 3: ACTIVITY ANALYTICS — 2 panels -->
    <div class="row g-3 mb-4">
        <div class="col-lg-5">
            <div class="card dash-card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-pie-chart me-2 text-info"></i>Activities by Category</h6>
                </div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <div class="dash-chart-wrap-lg"><canvas id="talCategoryChart"></canvas></div>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card dash-card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-bar-chart me-2 text-success"></i>Participation Trend</h6>
                </div>
                <div class="card-body">
                    <div class="dash-chart-wrap-lg"><canvas id="talParticipationChart"></canvas></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ROW 4: CURRENT ACTIVITIES — full-width table -->
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card dash-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-list-check me-2 text-purple"></i>Current Activities</h6>
                    <button class="btn btn-sm btn-outline-success" data-route="manage_activities">Manage All</button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Activity</th>
                                    <th scope="col">Category</th>
                                    <th scope="col" class="text-center">Students</th>
                                    <th scope="col">Start Date</th>
                                    <th scope="col">End Date</th>
                                    <th scope="col">Status</th>
                                </tr>
                            </thead>
                            <tbody id="talCurrentActivitiesBody">
                                <tr><td colspan="6" class="text-center text-muted py-4">Loading activities...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ROW 5: EVENT PIPELINE — 2 panels -->
    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card dash-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-calendar-event me-2 text-primary"></i>Upcoming Events</h6>
                    <button class="btn btn-sm btn-outline-primary" data-route="school_events">View All</button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Event</th>
                                    <th scope="col">Date</th>
                                    <th scope="col">Expected</th>
                                    <th scope="col">Status</th>
                                </tr>
                            </thead>
                            <tbody id="talUpcomingBody">
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
                    <h6 class="mb-0"><i class="bi bi-calendar-check me-2 text-success"></i>Past Events</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Event</th>
                                    <th scope="col">Date</th>
                                    <th scope="col">Attendance</th>
                                    <th scope="col">Rating</th>
                                </tr>
                            </thead>
                            <tbody id="talPastEventsBody">
                                <tr><td colspan="4" class="text-center text-muted py-4">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Budget & Participation Summary -->
    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card dash-card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-wallet2 me-2 text-amber"></i>Budget Summary</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Category</th>
                                    <th scope="col">Allocated</th>
                                    <th scope="col">Spent</th>
                                    <th scope="col">Remaining</th>
                                </tr>
                            </thead>
                            <tbody id="talBudgetBody">
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
                    <h6 class="mb-0"><i class="bi bi-people me-2 text-info"></i>Top Activities by Participation</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Activity</th>
                                    <th scope="col">Category</th>
                                    <th scope="col" class="text-center">Students</th>
                                    <th scope="col">Growth</th>
                                </tr>
                            </thead>
                            <tbody id="talTopActivitiesBody">
                                <tr><td colspan="4" class="text-center text-muted py-4">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Staff Coaches -->
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card dash-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-person-badge me-2 text-teal"></i>Activity Coaches / Supervisors</h6>
                    <small class="text-muted">Staff assigned to activities</small>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Staff Name</th>
                                    <th scope="col">Activity</th>
                                    <th scope="col">Category</th>
                                    <th scope="col">Sessions/Week</th>
                                    <th scope="col">Status</th>
                                </tr>
                            </thead>
                            <tbody id="talStaffBody">
                                <tr><td colspan="5" class="text-center text-muted py-4">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Resource & Equipment Status -->
    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card dash-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-box-seam me-2 text-amber"></i>Equipment &amp; Resources</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Item</th>
                                    <th scope="col">Category</th>
                                    <th scope="col">Quantity</th>
                                    <th scope="col">Condition</th>
                                </tr>
                            </thead>
                            <tbody id="talResourcesBody">
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
                    <h6 class="mb-0"><i class="bi bi-calendar-check me-2 text-success"></i>Recent Achievements</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Activity</th>
                                    <th scope="col">Achievement</th>
                                    <th scope="col">Level</th>
                                    <th scope="col">Date</th>
                                </tr>
                            </thead>
                            <tbody id="talAchievementsBody">
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
                            <a href="#" data-route="manage_activities" class="dash-quick-link">
                                <i class="bi bi-trophy ql-icon bg-primary text-white"></i>
                                <span>Manage Activities</span><i class="bi bi-chevron-right ql-arrow"></i>
                            </a>
                        </div>
                        <div class="col-md-3 col-lg">
                            <a href="#" data-route="school_events" class="dash-quick-link">
                                <i class="bi bi-calendar-event ql-icon bg-success text-white"></i>
                                <span>School Events</span><i class="bi bi-chevron-right ql-arrow"></i>
                            </a>
                        </div>
                        <div class="col-md-3 col-lg">
                            <a href="#" data-route="manage_activities" class="dash-quick-link">
                                <i class="bi bi-people ql-icon bg-info text-white"></i>
                                <span>Participants</span><i class="bi bi-chevron-right ql-arrow"></i>
                            </a>
                        </div>
                        <div class="col-md-3 col-lg">
                            <a href="#" data-route="budgets" class="dash-quick-link">
                                <i class="bi bi-wallet2 ql-icon bg-warning text-white"></i>
                                <span>Budget</span><i class="bi bi-chevron-right ql-arrow"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<?php asset_script($appBase, 'js/dashboards/dashboard_base_controller.js'); ?>
<?php asset_script($appBase, 'js/dashboards/hod_talent_development_dashboard.js'); ?>
