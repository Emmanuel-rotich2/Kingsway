<?php
/**
 * School Counselor/Chaplain Dashboard
 * Role: Chaplain (ID 24) — counseling sessions, chapel services, pastoral care
 */
?>
<div class="container-fluid py-3" id="counselor-dashboard">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1"><i class="bi bi-heart-pulse me-2 text-danger"></i>Pastoral Care Dashboard</h4>
            <p class="text-muted mb-0">Counseling, chapel services, and student welfare</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-danger btn-sm" onclick="counselorDashboardController.showNewSessionModal()">
                <i class="bi bi-plus me-1"></i>Record Session
            </button>
            <button class="btn btn-outline-secondary btn-sm" onclick="counselorDashboardController.refresh()">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
        </div>
    </div>

    <!-- Stat Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body py-3">
                    <div class="fs-2 mb-1">💬</div>
                    <h4 class="mb-0 text-primary" id="sessionsThisWeek">0</h4>
                    <small class="text-muted">Sessions This Week</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body py-3">
                    <div class="fs-2 mb-1">👤</div>
                    <h4 class="mb-0 text-success" id="studentsSeen">0</h4>
                    <small class="text-muted">Students Seen</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body py-3">
                    <div class="fs-2 mb-1">📋</div>
                    <h4 class="mb-0 text-warning" id="pendingReferrals">0</h4>
                    <small class="text-muted">Pending Referrals</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body py-3">
                    <div class="fs-2 mb-1">⛪</div>
                    <h4 class="mb-0 text-danger" id="chapelServices">0</h4>
                    <small class="text-muted">Chapel Services</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <!-- Recent Sessions -->
        <div class="col-md-7">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
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

        <!-- Upcoming Chapel & Actions -->
        <div class="col-md-5">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-calendar-heart me-2"></i>Chapel Schedule</h6>
                    <a href="#" onclick="counselorDashboardController.navigate('chapel_services')" class="btn btn-sm btn-outline-danger">View All</a>
                </div>
                <div class="list-group list-group-flush" id="chapelScheduleList">
                    <div class="text-center text-muted py-3">Loading schedule...</div>
                </div>
            </div>

            <div class="card shadow-sm mt-3">
                <div class="card-header bg-white"><h6 class="mb-0">Quick Actions</h6></div>
                <div class="card-body d-grid gap-2">
                    <button class="btn btn-outline-primary btn-sm" onclick="counselorDashboardController.navigate('student_counseling')">
                        <i class="bi bi-person-check me-1"></i>Student Counseling Records
                    </button>
                    <button class="btn btn-outline-danger btn-sm" onclick="counselorDashboardController.navigate('parent_meetings')">
                        <i class="bi bi-people me-1"></i>Parent Meetings
                    </button>
                    <button class="btn btn-outline-secondary btn-sm" onclick="counselorDashboardController.navigate('conduct_reports')">
                        <i class="bi bi-file-text me-1"></i>Conduct Reports
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
