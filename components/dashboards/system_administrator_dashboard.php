<?php
/**
 * System Administrator Dashboard
 * 
 * Shows system-level metrics for the platform:
 * - System Uptime
 * - Active Users
 * - Error Rate
 * - Queue Health
 * - Database Health
 * 
 * @package App\Components\Dashboards
 * @since 2025-01-01
 */

// Include required components
include_once __DIR__ . '/../charts/chart.php';
include_once __DIR__ . '/../tables/table.php';
include_once __DIR__ . '/../cards/card_component.php';
?>

<div class="container-fluid py-4">
    <!-- Dashboard Header -->
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h2 class="mb-1"><i class="fas fa-tachometer-alt me-2"></i>System Administrator Dashboard</h2>
                <p class="text-muted mb-0">Platform health and system metrics overview</p>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <small class="text-muted me-3">Last refreshed: <span id="lastRefreshTime">--:--:--</span></small>
                <button id="refreshDashboard" class="btn btn-sm btn-primary">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
                <button id="exportDashboard" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-download"></i> Export
                </button>
            </div>
        </div>
    </div>

    <!-- System Health Status Cards -->
    <div class="row g-3 mb-4">
        <!-- System Uptime -->
        <div class="col-6 col-md-4 col-lg-3 col-xl">
            <div class="card h-100 border-0 shadow-sm" id="card-uptime">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-muted fw-normal mb-1">System Uptime</h6>
                            <h3 class="mb-0 fw-bold" id="uptime-value">99.97%</h3>
                            <small class="text-success"><i class="bi bi-arrow-up"></i> +0.02% this week</small>
                        </div>
                        <div class="icon-circle bg-success bg-opacity-10 text-success">
                            <i class="fas fa-server fa-lg"></i>
                        </div>
                    </div>
                    <div class="progress mt-3" style="height: 4px;">
                        <div class="progress-bar bg-success" role="progressbar" style="width: 99.97%"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Active Users -->
        <div class="col-6 col-md-4 col-lg-3 col-xl">
            <div class="card h-100 border-0 shadow-sm" id="card-active-users">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-muted fw-normal mb-1">Active Users</h6>
                            <h3 class="mb-0 fw-bold" id="active-users-value">127</h3>
                            <small class="text-info"><i class="bi bi-person-fill"></i> Online now</small>
                        </div>
                        <div class="icon-circle bg-primary bg-opacity-10 text-primary">
                            <i class="fas fa-users fa-lg"></i>
                        </div>
                    </div>
                    <div class="mt-3 d-flex gap-1 flex-wrap">
                        <span class="badge bg-success">Admin: 3</span>
                        <span class="badge bg-info">Staff: 45</span>
                        <span class="badge bg-secondary">Others: 79</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Error Rate -->
        <div class="col-6 col-md-4 col-lg-3 col-xl">
            <div class="card h-100 border-0 shadow-sm" id="card-error-rate">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-muted fw-normal mb-1">Error Rate</h6>
                            <h3 class="mb-0 fw-bold" id="error-rate-value">0.12%</h3>
                            <small class="text-success"><i class="bi bi-arrow-down"></i> -0.05% from yesterday</small>
                        </div>
                        <div class="icon-circle bg-danger bg-opacity-10 text-danger">
                            <i class="fas fa-exclamation-triangle fa-lg"></i>
                        </div>
                    </div>
                    <div class="progress mt-3" style="height: 4px;">
                        <div class="progress-bar bg-danger" role="progressbar" style="width: 1%"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Queue Health -->
        <div class="col-6 col-md-4 col-lg-3 col-xl">
            <div class="card h-100 border-0 shadow-sm" id="card-queue-health">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-muted fw-normal mb-1">Queue Health</h6>
                            <h3 class="mb-0 fw-bold text-success" id="queue-health-value">Healthy</h3>
                            <small class="text-muted"><i class="bi bi-clock"></i> 0 pending jobs</small>
                        </div>
                        <div class="icon-circle bg-warning bg-opacity-10 text-warning">
                            <i class="fas fa-tasks fa-lg"></i>
                        </div>
                    </div>
                    <div class="mt-3 d-flex gap-1 flex-wrap">
                        <span class="badge bg-success">Processed: 1,234</span>
                        <span class="badge bg-warning text-dark">Failed: 2</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Database Health -->
        <div class="col-6 col-md-4 col-lg-3 col-xl">
            <div class="card h-100 border-0 shadow-sm" id="card-db-health">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-muted fw-normal mb-1">Database Health</h6>
                            <h3 class="mb-0 fw-bold text-success" id="db-health-value">Optimal</h3>
                            <small class="text-muted"><i class="bi bi-hdd"></i> 45ms avg response</small>
                        </div>
                        <div class="icon-circle bg-info bg-opacity-10 text-info">
                            <i class="fas fa-database fa-lg"></i>
                        </div>
                    </div>
                    <div class="progress mt-3" style="height: 4px;">
                        <div class="progress-bar bg-info" role="progressbar" style="width: 65%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Dashboard Content -->
    <div class="row g-4">
        <!-- Left Column -->
        <div class="col-lg-8">
            <!-- API Request Chart -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 py-3">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h5 class="mb-0"><i class="fas fa-chart-line me-2 text-primary"></i>API Requests (Last 24h)</h5>
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-outline-secondary active">Hourly</button>
                            <button type="button" class="btn btn-outline-secondary">Daily</button>
                            <button type="button" class="btn btn-outline-secondary">Weekly</button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <canvas id="apiRequestsChart" height="300"></canvas>
                </div>
            </div>

            <!-- Recent Activity Table -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h5 class="mb-0"><i class="fas fa-history me-2 text-info"></i>Recent System Activity</h5>
                        <a href="home.php?route=activity_audit_logs" class="btn btn-sm btn-outline-primary">
                            View All <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Time</th>
                                    <th>User</th>
                                    <th>Action</th>
                                    <th>Resource</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="activity-log-table">
                                <tr>
                                    <td><small class="text-muted">2 min ago</small></td>
                                    <td><span class="badge bg-primary">admin</span></td>
                                    <td>Updated role permissions</td>
                                    <td>Role: School Admin</td>
                                    <td><span class="badge bg-success">Success</span></td>
                                </tr>
                                <tr>
                                    <td><small class="text-muted">5 min ago</small></td>
                                    <td><span class="badge bg-info">system</span></td>
                                    <td>Scheduled backup completed</td>
                                    <td>Database: production</td>
                                    <td><span class="badge bg-success">Success</span></td>
                                </tr>
                                <tr>
                                    <td><small class="text-muted">12 min ago</small></td>
                                    <td><span class="badge bg-primary">admin</span></td>
                                    <td>Created new user account</td>
                                    <td>User: john.doe@school.ac.ke</td>
                                    <td><span class="badge bg-success">Success</span></td>
                                </tr>
                                <tr>
                                    <td><small class="text-muted">23 min ago</small></td>
                                    <td><span class="badge bg-warning text-dark">scheduler</span></td>
                                    <td>Email notification sent</td>
                                    <td>Template: fee_reminder</td>
                                    <td><span class="badge bg-success">Success</span></td>
                                </tr>
                                <tr>
                                    <td><small class="text-muted">45 min ago</small></td>
                                    <td><span class="badge bg-danger">security</span></td>
                                    <td>Failed login attempt blocked</td>
                                    <td>IP: 192.168.1.xxx</td>
                                    <td><span class="badge bg-warning text-dark">Blocked</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div class="col-lg-4">
            <!-- Quick Actions -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0"><i class="fas fa-bolt me-2 text-warning"></i>Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="home.php?route=manage_users" class="btn btn-outline-primary text-start">
                            <i class="fas fa-user-plus me-2"></i>Manage Users
                        </a>
                        <a href="home.php?route=role_definitions" class="btn btn-outline-secondary text-start">
                            <i class="fas fa-user-tag me-2"></i>Manage Roles
                        </a>
                        <a href="home.php?route=system_settings" class="btn btn-outline-secondary text-start">
                            <i class="fas fa-cog me-2"></i>System Settings
                        </a>
                        <a href="home.php?route=backups" class="btn btn-outline-info text-start">
                            <i class="fas fa-download me-2"></i>Backup Database
                        </a>
                        <a href="home.php?route=error_logs" class="btn btn-outline-danger text-start">
                            <i class="fas fa-exclamation-circle me-2"></i>View Error Logs
                        </a>
                    </div>
                </div>
            </div>

            <!-- System Status -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0"><i class="fas fa-heartbeat me-2 text-success"></i>System Status</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span>CPU Usage</span>
                            <span class="fw-bold">23%</span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-success" style="width: 23%"></div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span>Memory Usage</span>
                            <span class="fw-bold">58%</span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-info" style="width: 58%"></div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span>Disk Usage</span>
                            <span class="fw-bold">45%</span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-warning" style="width: 45%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span>Network I/O</span>
                            <span class="fw-bold">12 MB/s</span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-primary" style="width: 35%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Security Alerts -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-shield-alt me-2 text-danger"></i>Security Alerts</h5>
                        <span class="badge bg-success">All Clear</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="text-center py-3">
                        <i class="fas fa-check-circle text-success fa-3x mb-3"></i>
                        <p class="text-muted mb-0">No active security alerts</p>
                        <small class="text-muted">Last scan: 5 minutes ago</small>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between text-muted small">
                        <span><i class="fas fa-lock me-1"></i>Failed logins (24h): 3</span>
                        <span><i class="fas fa-ban me-1"></i>Blocked IPs: 0</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.icon-circle {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.spin {
    animation: spin 1s linear infinite;
}
</style>

<!-- Load the System Admin Dashboard helper -->
<script src="/Kingsway/js/dashboards/system_administrator_dashboard.js"></script>

