<?php
/**
 * HOD Talent Development Dashboard — Activities, Sports, Events (Role ID 21)
 */
?>
<div class="container-fluid py-3" id="hod-dashboard">

    <!-- Greeting Bar -->
    <div class="dash-greeting-bar mb-4">
        <div>
            <h5 id="hodGreeting">Good morning!</h5>
            <p>Manage school activities, sports, events, and talent programmes</p>
        </div>
        <div class="dash-meta">
            <button class="btn btn-sm btn-light" onclick="hodDashboardController.navigate('manage_activities')">
                <i class="bi bi-plus me-1"></i>New Activity
            </button>
            <button class="dash-refresh-btn" onclick="hodDashboardController.refresh()">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="dash-stat dsc-amber">
                <div class="dash-stat-value" id="activeActivities">0</div>
                <div class="dash-stat-label">Active Activities</div>
                <i class="bi bi-trophy-fill dash-stat-icon"></i>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="dash-stat dsc-blue">
                <div class="dash-stat-value" id="studentsEnrolled">0</div>
                <div class="dash-stat-label">Students Enrolled</div>
                <i class="bi bi-people-fill dash-stat-icon"></i>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="dash-stat dsc-green">
                <div class="dash-stat-value" id="upcomingEvents">0</div>
                <div class="dash-stat-label">Upcoming Events</div>
                <i class="bi bi-calendar-event-fill dash-stat-icon"></i>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="dash-stat dsc-red">
                <div class="dash-stat-value" id="awardsThisTerm">0</div>
                <div class="dash-stat-label">Awards This Term</div>
                <i class="bi bi-award-fill dash-stat-icon"></i>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <!-- Active Activities table -->
        <div class="col-md-7">
            <div class="card dash-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-list-check me-2"></i>Active Activities</h6>
                    <a href="#" onclick="hodDashboardController.navigate('manage_activities')" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr><th>Activity</th><th>Category</th><th>Participants</th><th>Coach</th><th>Status</th></tr>
                        </thead>
                        <tbody id="activitiesTableBody">
                            <tr><td colspan="5" class="text-center text-muted py-3">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Upcoming Events + Actions -->
        <div class="col-md-5">
            <div class="card dash-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-calendar-event me-2"></i>Upcoming Events</h6>
                    <a href="#" onclick="hodDashboardController.navigate('school_events')" class="btn btn-sm btn-outline-warning">View All</a>
                </div>
                <div class="list-group list-group-flush" id="upcomingEventsList">
                    <div class="text-center text-muted py-3">Loading events...</div>
                </div>
            </div>

            <div class="card dash-card mt-3">
                <div class="card-header"><h6 class="mb-0"><i class="bi bi-lightning me-2 text-warning"></i>Quick Actions</h6></div>
                <div class="card-body">
                    <a href="#" onclick="hodDashboardController.navigate('sports')" class="dash-quick-link">
                        <i class="bi bi-dribbble ql-icon bg-warning text-white"></i>
                        <span>Sports Management</span><i class="bi bi-chevron-right ql-arrow"></i>
                    </a>
                    <a href="#" onclick="hodDashboardController.navigate('clubs_societies')" class="dash-quick-link">
                        <i class="bi bi-people ql-icon bg-info text-white"></i>
                        <span>Clubs &amp; Societies</span><i class="bi bi-chevron-right ql-arrow"></i>
                    </a>
                    <a href="#" onclick="hodDashboardController.navigate('competitions')" class="dash-quick-link">
                        <i class="bi bi-award ql-icon bg-success text-white"></i>
                        <span>Competitions</span><i class="bi bi-chevron-right ql-arrow"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
