<?php
/**
 * Deputy Headteacher - Discipline Dashboard
 * Focus: discipline cases, attendance patterns, communications, events.
 */
?>

<style>
    .dashboard-card {
        transition: all 0.3s ease;
        border-radius: 12px;
        border: none;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    }

    .stat-card {
        padding: 1.25rem;
        border-radius: 12px;
        color: white;
        position: relative;
        overflow: hidden;
    }

    .stat-card .value {
        font-size: 1.8rem;
        font-weight: 700;
        line-height: 1;
    }

    .stat-card .label {
        font-size: 0.9rem;
        opacity: 0.9;
        margin-top: 0.35rem;
    }

    .stat-card .secondary {
        font-size: 0.8rem;
        opacity: 0.75;
        margin-top: 0.35rem;
    }

    .chart-container {
        position: relative;
        height: 280px;
    }

    .quick-link {
        display: flex;
        align-items: center;
        padding: 0.75rem 1rem;
        border-radius: 10px;
        background: #f8f9fa;
        text-decoration: none;
        color: #2d2d2d;
        transition: all 0.2s;
    }

    .quick-link:hover {
        background: #e9ecef;
        transform: translateX(4px);
    }

    .quick-link i {
        width: 34px;
        height: 34px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        margin-right: 0.75rem;
        font-size: 1rem;
    }

    .refresh-indicator {
        font-size: 0.8rem;
        color: #6c757d;
    }
</style>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h3 class="mb-1"><i class="bi bi-shield-exclamation me-2"></i>Deputy Headteacher - Discipline
                        Dashboard</h3>
                    <p class="text-muted mb-0">Discipline oversight: cases, attendance, parent engagement.</p>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <span class="refresh-indicator"><i class="bi bi-clock me-1"></i>Last updated: <span
                            id="lastUpdated">--</span></span>
                    <button class="btn btn-outline-primary btn-sm" id="refreshDashboard"><i
                            class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-lg-4 col-md-6">
            <div class="stat-card bg-danger">
                <div class="value" id="disciplineCasesValue">--</div>
                <div class="label">Open Discipline Cases</div>
                <div class="secondary" id="disciplineDetail">Active investigations</div>
            </div>
        </div>
        <div class="col-xl-3 col-lg-4 col-md-6">
            <div class="stat-card bg-primary">
                <div class="value" id="attendanceValue">--%</div>
                <div class="label">Attendance Today</div>
                <div class="secondary" id="attendanceDetail">Present vs absent</div>
            </div>
        </div>
        <div class="col-xl-3 col-lg-4 col-md-6">
            <div class="stat-card bg-secondary">
                <div class="value" id="communicationsValue">--</div>
                <div class="label">Parent Communications</div>
                <div class="secondary">Messages this week</div>
            </div>
        </div>
        <div class="col-xl-3 col-lg-4 col-md-6">
            <div class="stat-card bg-info">
                <div class="value" id="eventsValue">--</div>
                <div class="label">Upcoming Events</div>
                <div class="secondary">Discipline-related</div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card dashboard-card h-100">
                <div class="card-header bg-transparent border-0 pb-0">
                    <h5 class="mb-0"><i class="bi bi-activity me-2 text-danger"></i>Discipline Trend</h5>
                    <small class="text-muted">Cases over time</small>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="disciplineTrendChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card dashboard-card h-100">
                <div class="card-header bg-transparent border-0 pb-0">
                    <h5 class="mb-0"><i class="bi bi-graph-up me-2 text-primary"></i>Attendance Trend</h5>
                    <small class="text-muted">Weekly attendance</small>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="disciplineAttendanceChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card dashboard-card table-card h-100">
                <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0"><i class="bi bi-exclamation-triangle me-2 text-danger"></i>Open Discipline
                            Cases</h5>
                        <small class="text-muted">Active cases requiring attention</small>
                    </div>
                    <a href="home.php?route=discipline_cases" class="btn btn-sm btn-outline-danger">View All</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="disciplineTable">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Class</th>
                                    <th>Issue</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="disciplineTableBody">
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">
                                        <div class="spinner-border spinner-border-sm" role="status"></div> Loading...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card dashboard-card h-100">
                <div class="card-header bg-transparent border-0">
                    <h5 class="mb-0"><i class="bi bi-calendar-event me-2 text-info"></i>Upcoming Events</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush" id="eventsList">
                        <li class="list-group-item text-center text-muted py-4">
                            <div class="spinner-border spinner-border-sm" role="status"></div> Loading events...
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card dashboard-card">
                <div class="card-header bg-transparent border-0">
                    <h5 class="mb-0"><i class="bi bi-lightning me-2 text-warning"></i>Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-md-6"><a class="quick-link" href="home.php?route=discipline_cases"><i
                                    class="bi bi-shield-exclamation bg-danger text-white"></i><span>Manage
                                    Cases</span></a></div>
                        <div class="col-md-6"><a class="quick-link" href="home.php?route=attendance_trends"><i
                                    class="bi bi-graph-up bg-primary text-white"></i><span>Attendance Trends</span></a>
                        </div>
                        <div class="col-md-6"><a class="quick-link" href="home.php?route=manage_communications"><i
                                    class="bi bi-chat-dots bg-secondary text-white"></i><span>Parent
                                    Communications</span></a></div>
                        <div class="col-md-6"><a class="quick-link" href="home.php?route=conduct_reports"><i
                                    class="bi bi-journal-text bg-info text-white"></i><span>Conduct Reports</span></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card dashboard-card">
                <div class="card-header bg-transparent border-0">
                    <h5 class="mb-0"><i class="bi bi-clipboard-check me-2 text-primary"></i>Discipline Notes</h5>
                </div>
                <div class="card-body" id="disciplineNotes">
                    <p class="text-muted mb-0">Surface escalations, follow-ups, and action items.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="js/dashboards/deputy_head_discipline_dashboard.js"></script>