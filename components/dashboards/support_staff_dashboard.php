<?php
/**
 * Support Staff Dashboard — Kitchen (32), Security (33), Janitor (34)
 * Minimal read-only view: profile, today's schedule, announcements
 */
?>
<div class="container-fluid py-3" id="support-staff-dashboard">

    <!-- Greeting Bar -->
    <div class="dash-greeting-bar mb-4">
        <div>
            <h5 id="supportGreeting">Good morning!</h5>
            <p id="supportSubtitle">Your personal workspace</p>
        </div>
        <div class="dash-meta">
            <button class="dash-refresh-btn" onclick="supportStaffDashboardController.refresh()">
                <i class="bi bi-arrow-clockwise me-1"></i>Refresh
            </button>
        </div>
    </div>

    <div class="row g-3">
        <!-- Profile Card -->
        <div class="col-md-4">
            <div class="card dash-card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-person me-2"></i>My Profile</h6>
                </div>
                <div class="card-body text-center">
                    <div class="rounded-circle bg-secondary d-inline-flex align-items-center justify-content-center mb-3"
                         style="width:72px;height:72px">
                        <span class="text-white fs-2 fw-bold" id="staffInitials">—</span>
                    </div>
                    <h5 class="mb-1" id="staffFullName">Loading...</h5>
                    <p class="text-muted small mb-1" id="staffRoleName">—</p>
                    <p class="text-muted small mb-1" id="staffPhone"><i class="bi bi-telephone me-1"></i>—</p>
                    <p class="text-muted small mb-0" id="staffEmail"><i class="bi bi-envelope me-1"></i>—</p>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="card dash-card mt-3">
                <div class="card-header"><h6 class="mb-0"><i class="bi bi-link me-2"></i>Quick Links</h6></div>
                <div class="list-group list-group-flush">
                    <a href="#" onclick="supportStaffDashboardController.navigate('me')"
                       class="list-group-item list-group-item-action py-2">
                        <i class="bi bi-person-circle me-2"></i>My Profile
                    </a>
                    <a href="#" onclick="supportStaffDashboardController.navigate('announcements')"
                       class="list-group-item list-group-item-action py-2">
                        <i class="bi bi-megaphone me-2"></i>Announcements
                    </a>
                    <a href="#" onclick="supportStaffDashboardController.navigate('circulars')"
                       class="list-group-item list-group-item-action py-2">
                        <i class="bi bi-file-text me-2"></i>Circulars
                    </a>
                </div>
            </div>
        </div>

        <!-- Schedule & Announcements -->
        <div class="col-md-8">
            <div class="card dash-card mb-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-calendar-day me-2"></i>Today's Schedule</h6>
                </div>
                <div class="card-body" id="todaySchedule">
                    <div class="text-center text-muted py-3">Loading schedule...</div>
                </div>
            </div>

            <div class="card dash-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-megaphone me-2"></i>Recent Announcements</h6>
                    <a href="#" onclick="supportStaffDashboardController.navigate('announcements')"
                       class="btn btn-sm btn-outline-secondary">View All</a>
                </div>
                <div class="list-group list-group-flush" id="announcementsList">
                    <div class="text-center text-muted py-3">Loading announcements...</div>
                </div>
            </div>
        </div>
    </div>
</div>
