<?php
/**
 * HOD Talent Development Dashboard
 * Role: HOD Talent Development (ID 21) — activities, events, sports
 */
?>
<div class="container-fluid py-3" id="hod-dashboard">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1"><i class="bi bi-trophy me-2 text-warning"></i>Talent Development Dashboard</h4>
            <p class="text-muted mb-0">Manage school activities, sports, events, and talent programmes</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-warning btn-sm" onclick="hodDashboardController.navigate('manage_activities')">
                <i class="bi bi-plus me-1"></i>New Activity
            </button>
            <button class="btn btn-outline-secondary btn-sm" onclick="hodDashboardController.refresh()">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
        </div>
    </div>

    <!-- Stat Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body py-3">
                    <div class="fs-2 mb-1">🏆</div>
                    <h4 class="mb-0 text-warning" id="activeActivities">0</h4>
                    <small class="text-muted">Active Activities</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body py-3">
                    <div class="fs-2 mb-1">👥</div>
                    <h4 class="mb-0 text-primary" id="studentsEnrolled">0</h4>
                    <small class="text-muted">Students Enrolled</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body py-3">
                    <div class="fs-2 mb-1">📅</div>
                    <h4 class="mb-0 text-success" id="upcomingEvents">0</h4>
                    <small class="text-muted">Upcoming Events</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body py-3">
                    <div class="fs-2 mb-1">🥇</div>
                    <h4 class="mb-0 text-danger" id="awardsThisTerm">0</h4>
                    <small class="text-muted">Awards This Term</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <!-- Recent Activities -->
        <div class="col-md-7">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
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

        <!-- Upcoming Events -->
        <div class="col-md-5">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-calendar-event me-2"></i>Upcoming Events</h6>
                    <a href="#" onclick="hodDashboardController.navigate('school_events')" class="btn btn-sm btn-outline-warning">View All</a>
                </div>
                <div class="list-group list-group-flush" id="upcomingEventsList">
                    <div class="text-center text-muted py-3">Loading events...</div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card shadow-sm mt-3">
                <div class="card-header bg-white"><h6 class="mb-0">Quick Actions</h6></div>
                <div class="card-body d-grid gap-2">
                    <button class="btn btn-outline-warning btn-sm" onclick="hodDashboardController.navigate('sports')">
                        <i class="bi bi-dribbble me-1"></i>Sports Management
                    </button>
                    <button class="btn btn-outline-info btn-sm" onclick="hodDashboardController.navigate('clubs_societies')">
                        <i class="bi bi-people me-1"></i>Clubs & Societies
                    </button>
                    <button class="btn btn-outline-success btn-sm" onclick="hodDashboardController.navigate('competitions')">
                        <i class="bi bi-award me-1"></i>Competitions
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
