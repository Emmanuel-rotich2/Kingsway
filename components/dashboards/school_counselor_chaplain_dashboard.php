<?php
/**
 * School Counselor / Chaplain Dashboard — Pastoral Care (Role ID 24)
 */
?>
<div class="container-fluid py-3" id="counselor-dashboard">

    <!-- Greeting Bar -->
    <div class="dash-greeting-bar mb-4">
        <div>
            <h5 id="counselorGreeting">Good morning!</h5>
            <p>Counseling, chapel services, and student welfare</p>
        </div>
        <div class="dash-meta">
            <button class="btn btn-sm btn-light" onclick="counselorDashboardController.showNewSessionModal()">
                <i class="bi bi-plus me-1"></i>Record Session
            </button>
            <button class="dash-refresh-btn" onclick="counselorDashboardController.refresh()">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="dash-stat dsc-blue">
                <div class="dash-stat-value" id="sessionsThisWeek">0</div>
                <div class="dash-stat-label">Sessions This Week</div>
                <i class="bi bi-chat-square-text-fill dash-stat-icon"></i>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="dash-stat dsc-green">
                <div class="dash-stat-value" id="studentsSeen">0</div>
                <div class="dash-stat-label">Students Seen</div>
                <i class="bi bi-person-check-fill dash-stat-icon"></i>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="dash-stat dsc-amber">
                <div class="dash-stat-value" id="pendingReferrals">0</div>
                <div class="dash-stat-label">Pending Referrals</div>
                <i class="bi bi-clipboard-fill dash-stat-icon"></i>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="dash-stat dsc-indigo">
                <div class="dash-stat-value" id="chapelServices">0</div>
                <div class="dash-stat-label">Chapel Services</div>
                <i class="bi bi-stars dash-stat-icon"></i>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <!-- Recent Sessions -->
        <div class="col-md-7">
            <div class="card dash-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-chat-square-text me-2"></i>Recent Counseling Sessions</h6>
                    <a href="#" onclick="counselorDashboardController.navigate('counseling_records')" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr><th>Student</th><th>Type</th><th>Date</th><th>Follow-up</th></tr>
                        </thead>
                        <tbody id="sessionsTableBody">
                            <tr><td colspan="4" class="text-center text-muted py-3">Loading sessions...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Chapel Schedule + Actions -->
        <div class="col-md-5">
            <div class="card dash-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-calendar-heart me-2"></i>Chapel Schedule</h6>
                    <a href="#" onclick="counselorDashboardController.navigate('chapel_services')" class="btn btn-sm btn-outline-danger">View All</a>
                </div>
                <div class="list-group list-group-flush" id="chapelScheduleList">
                    <div class="text-center text-muted py-3">Loading schedule...</div>
                </div>
            </div>

            <div class="card dash-card mt-3">
                <div class="card-header"><h6 class="mb-0"><i class="bi bi-lightning me-2 text-warning"></i>Quick Actions</h6></div>
                <div class="card-body">
                    <a href="#" onclick="counselorDashboardController.navigate('student_counseling')" class="dash-quick-link">
                        <i class="bi bi-person-check ql-icon bg-primary text-white"></i>
                        <span>Counseling Records</span><i class="bi bi-chevron-right ql-arrow"></i>
                    </a>
                    <a href="#" onclick="counselorDashboardController.navigate('parent_meetings')" class="dash-quick-link">
                        <i class="bi bi-people ql-icon bg-danger text-white"></i>
                        <span>Parent Meetings</span><i class="bi bi-chevron-right ql-arrow"></i>
                    </a>
                    <a href="#" onclick="counselorDashboardController.navigate('conduct_reports')" class="dash-quick-link">
                        <i class="bi bi-file-text ql-icon bg-secondary text-white"></i>
                        <span>Conduct Reports</span><i class="bi bi-chevron-right ql-arrow"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
