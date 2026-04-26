<?php
/**
 * Boarding Master / Matron Dashboard — Role ID 18
 */
?>

<div class="container-fluid py-4" id="matron-dashboard">

    <!-- Greeting Bar -->
    <div class="dash-greeting-bar mb-4">
        <div>
            <h5 id="matronGreeting">Good evening!</h5>
            <p>Dormitory oversight &amp; welfare management</p>
        </div>
        <div class="dash-meta">
            <span class="dash-badge"><i class="bi bi-house-heart me-1"></i>Boarding</span>
            <span class="text-white-50 small">Updated: <span id="lastUpdated">—</span></span>
            <button class="dash-refresh-btn" id="refreshDashboard">
                <i class="bi bi-arrow-clockwise me-1"></i>Refresh
            </button>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="dash-stat dsc-purple">
                <div class="dash-stat-value" id="totalBoarders">—</div>
                <div class="dash-stat-label">Total Boarders</div>
                <div class="dash-stat-sub" id="boardersCapacity">of — capacity</div>
                <i class="bi bi-people-fill dash-stat-icon"></i>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="dash-stat dsc-green">
                <div class="dash-stat-value" id="rollCallRate">—</div>
                <div class="dash-stat-label">Tonight's Roll Call</div>
                <div class="dash-stat-sub" id="rollCallSub">Present / Total</div>
                <i class="bi bi-check2-circle dash-stat-icon"></i>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="dash-stat dsc-amber">
                <div class="dash-stat-value" id="pendingExeats">—</div>
                <div class="dash-stat-label">Pending Exeats</div>
                <div class="dash-stat-sub">Awaiting approval</div>
                <i class="bi bi-door-open-fill dash-stat-icon"></i>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="dash-stat dsc-red">
                <div class="dash-stat-value" id="disciplineCases">—</div>
                <div class="dash-stat-label">Discipline Cases</div>
                <div class="dash-stat-sub">This week</div>
                <i class="bi bi-exclamation-triangle-fill dash-stat-icon"></i>
            </div>
        </div>
    </div>

    <!-- Charts & Exeats -->
    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card dash-card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-bar-chart me-2 text-primary"></i>Dormitory Occupancy</h6>
                    <small class="text-muted">Occupied vs. capacity per dormitory</small>
                </div>
                <div class="card-body">
                    <div class="dash-chart-wrap"><canvas id="occupancyChart"></canvas></div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card dash-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-0"><i class="bi bi-door-open me-2 text-warning"></i>Pending Exeat Requests</h6>
                        <small class="text-muted">Students requesting leave</small>
                    </div>
                    <a href="home.php?route=permissions_exeats" class="btn btn-sm btn-outline-warning">View All</a>
                </div>
                <div class="card-body p-0" id="exeatsContainer">
                    <div class="text-center py-5 text-muted">
                        <div class="spinner-border spinner-border-sm me-2" role="status"></div>Loading...
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Roll Call & Quick Actions -->
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card dash-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-0"><i class="bi bi-clipboard-check me-2 text-success"></i>Tonight's Roll Call</h6>
                        <small class="text-muted">Current night attendance status</small>
                    </div>
                    <a href="home.php?route=boarding_roll_call" class="btn btn-sm btn-outline-success">Mark Roll Call</a>
                </div>
                <div class="card-body p-0" id="rollCallContainer">
                    <div class="text-center py-5 text-muted">
                        <div class="spinner-border spinner-border-sm me-2" role="status"></div>Loading...
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card dash-card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-lightning me-2 text-warning"></i>Quick Actions</h6>
                </div>
                <div class="card-body">
                    <a href="home.php?route=boarding_roll_call" class="dash-quick-link">
                        <i class="bi bi-clipboard-check ql-icon bg-success text-white"></i>
                        <span>Mark Roll Call</span><i class="bi bi-chevron-right ql-arrow"></i>
                    </a>
                    <a href="home.php?route=permissions_exeats" class="dash-quick-link">
                        <i class="bi bi-door-open ql-icon bg-warning text-white"></i>
                        <span>Manage Exeats</span><i class="bi bi-chevron-right ql-arrow"></i>
                    </a>
                    <a href="home.php?route=dormitory_management" class="dash-quick-link">
                        <i class="bi bi-building ql-icon bg-primary text-white"></i>
                        <span>Dormitories</span><i class="bi bi-chevron-right ql-arrow"></i>
                    </a>
                    <a href="home.php?route=student_discipline" class="dash-quick-link">
                        <i class="bi bi-exclamation-triangle ql-icon bg-danger text-white"></i>
                        <span>Discipline Cases</span><i class="bi bi-chevron-right ql-arrow"></i>
                    </a>
                    <a href="home.php?route=manage_menus" class="dash-quick-link">
                        <i class="bi bi-cup-hot ql-icon bg-info text-white"></i>
                        <span>Meal Menus</span><i class="bi bi-chevron-right ql-arrow"></i>
                    </a>
                    <a href="home.php?route=chapel_services" class="dash-quick-link">
                        <i class="bi bi-moon-stars ql-icon bg-secondary text-white"></i>
                        <span>Chapel Services</span><i class="bi bi-chevron-right ql-arrow"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

</div>

<script src="<?= $appBase ?>js/dashboards/matron_housemother_dashboard.js"></script>
<script>
    (function () {
        const user = (typeof AuthContext !== 'undefined') ? AuthContext.getUser() : null;
        if (user) {
            const hr = new Date().getHours();
            const greet = hr < 12 ? 'Good morning' : hr < 17 ? 'Good afternoon' : 'Good evening';
            const name = user.first_name || user.name || '';
            document.getElementById('matronGreeting').textContent = greet + (name ? ', ' + name : '') + '!';
        }
    })();
</script>
