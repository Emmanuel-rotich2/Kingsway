<?php
/**
 * Boarding Master / Matron Dashboard
 * Role: Matron/Housemother (Role ID 18)
 *
 * Features:
 * - 4 KPI cards: Total Boarders, Tonight's Roll Call, Pending Exeats, Discipline Cases
 * - Dormitory Occupancy bar chart
 * - Pending Exeat Requests table (with inline approve)
 * - Tonight's Roll Call table
 * - Quick Actions panel
 *
 * Auto-Refresh: 30 minutes
 * Data: fetched client-side via window.API.boarding.*
 */
?>

<style>
    .boarding-dashboard .stat-card {
        border-radius: 12px;
        border: none;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        transition: transform 0.2s, box-shadow 0.2s;
        overflow: hidden;
        position: relative;
        padding: 1.25rem;
        color: white;
    }
    .boarding-dashboard .stat-card::after {
        content: '';
        position: absolute;
        top: -50%; right: -50%;
        width: 100%; height: 200%;
        background: rgba(255,255,255,0.08);
        transform: rotate(30deg);
        pointer-events: none;
    }
    .boarding-dashboard .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 24px rgba(0,0,0,0.14);
    }
    .boarding-dashboard .stat-card .stat-icon {
        font-size: 2.4rem;
        opacity: 0.28;
        position: absolute;
        right: 1rem; top: 50%;
        transform: translateY(-50%);
    }
    .boarding-dashboard .stat-card .stat-value {
        font-size: 2rem;
        font-weight: 700;
        line-height: 1;
    }
    .boarding-dashboard .stat-card .stat-label {
        font-size: 0.85rem;
        opacity: 0.9;
        margin-top: 0.25rem;
    }
    .boarding-dashboard .stat-card .stat-sub {
        font-size: 0.72rem;
        opacity: 0.72;
        margin-top: 0.4rem;
    }
    .bg-boarders  { background: linear-gradient(135deg,#9c27b0 0%,#673ab7 100%); }
    .bg-rollcall  { background: linear-gradient(135deg,#11998e 0%,#38ef7d 100%); }
    .bg-exeats    { background: linear-gradient(135deg,#f7971e 0%,#ffd200 100%); }
    .bg-discipline{ background: linear-gradient(135deg,#eb3349 0%,#f45c43 100%); }

    .boarding-dashboard .dashboard-card {
        border-radius: 12px;
        border: none;
        box-shadow: 0 2px 8px rgba(0,0,0,0.07);
        transition: box-shadow 0.2s;
    }
    .boarding-dashboard .dashboard-card:hover {
        box-shadow: 0 6px 20px rgba(0,0,0,0.11);
    }
    .boarding-dashboard .chart-container { position: relative; height: 260px; }

    .boarding-dashboard .quick-link {
        display: flex; align-items: center;
        padding: 0.7rem 1rem;
        border-radius: 8px;
        background: #f8f9fa;
        margin-bottom: 0.5rem;
        text-decoration: none;
        color: #333;
        transition: background 0.15s, transform 0.15s;
    }
    .boarding-dashboard .quick-link:hover {
        background: #e9ecef;
        transform: translateX(4px);
    }
    .boarding-dashboard .quick-link i.ql-icon {
        width: 32px; height: 32px;
        display: flex; align-items: center; justify-content: center;
        border-radius: 8px;
        margin-right: 0.75rem;
        font-size: 0.9rem;
    }
    .boarding-dashboard .refresh-indicator {
        font-size: 0.75rem;
        color: #6c757d;
    }
</style>

<div class="boarding-dashboard container-fluid py-4">

    <!-- Dashboard Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h3 class="mb-1"><i class="bi bi-house-heart me-2"></i>Boarding Dashboard</h3>
                    <p class="text-muted mb-0">Dormitory oversight &amp; welfare management</p>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <span class="refresh-indicator">
                        <i class="bi bi-clock me-1"></i>Last updated: <span id="lastUpdated">--</span>
                    </span>
                    <button class="btn btn-outline-secondary btn-sm" id="refreshDashboard">
                        <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-lg-4 col-md-6">
            <div class="stat-card bg-boarders">
                <div class="stat-value" id="totalBoarders">--</div>
                <div class="stat-label">Total Boarders</div>
                <div class="stat-sub" id="boardersCapacity">of -- capacity</div>
                <i class="bi bi-people-fill stat-icon"></i>
            </div>
        </div>
        <div class="col-xl-3 col-lg-4 col-md-6">
            <div class="stat-card bg-rollcall">
                <div class="stat-value" id="rollCallRate">--</div>
                <div class="stat-label">Tonight's Roll Call</div>
                <div class="stat-sub" id="rollCallSub">Present / Total</div>
                <i class="bi bi-check2-circle stat-icon"></i>
            </div>
        </div>
        <div class="col-xl-3 col-lg-4 col-md-6">
            <div class="stat-card bg-exeats">
                <div class="stat-value" id="pendingExeats">--</div>
                <div class="stat-label">Pending Exeats</div>
                <div class="stat-sub">Awaiting approval</div>
                <i class="bi bi-door-open-fill stat-icon"></i>
            </div>
        </div>
        <div class="col-xl-3 col-lg-4 col-md-6">
            <div class="stat-card bg-discipline">
                <div class="stat-value" id="disciplineCases">--</div>
                <div class="stat-label">Discipline Cases</div>
                <div class="stat-sub">This week</div>
                <i class="bi bi-exclamation-triangle-fill stat-icon"></i>
            </div>
        </div>
    </div>

    <!-- Charts & Exeats -->
    <div class="row g-4 mb-4">
        <!-- Dormitory Occupancy Chart -->
        <div class="col-lg-6">
            <div class="card dashboard-card h-100">
                <div class="card-header bg-transparent border-0 pb-0">
                    <h5 class="mb-0"><i class="bi bi-bar-chart me-2 text-purple"></i>Dormitory Occupancy</h5>
                    <small class="text-muted">Occupied vs. total capacity per dormitory</small>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="occupancyChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending Exeat Requests -->
        <div class="col-lg-6">
            <div class="card dashboard-card h-100">
                <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0"><i class="bi bi-door-open me-2 text-warning"></i>Pending Exeat Requests</h5>
                        <small class="text-muted">Students requesting leave</small>
                    </div>
                    <a href="home.php?route=permissions_exeats" class="btn btn-sm btn-outline-warning">
                        View All <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
                <div class="card-body p-0">
                    <div id="exeatsContainer">
                        <div class="text-center py-5 text-muted">
                            <div class="spinner-border spinner-border-sm me-2" role="status"></div>Loading...
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Roll Call & Quick Actions -->
    <div class="row g-4">
        <!-- Tonight's Roll Call -->
        <div class="col-lg-8">
            <div class="card dashboard-card">
                <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0"><i class="bi bi-clipboard-check me-2 text-success"></i>Tonight's Roll Call</h5>
                        <small class="text-muted">Current night attendance status</small>
                    </div>
                    <a href="home.php?route=boarding_roll_call" class="btn btn-sm btn-outline-success">
                        Mark Roll Call <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
                <div class="card-body p-0">
                    <div id="rollCallContainer">
                        <div class="text-center py-5 text-muted">
                            <div class="spinner-border spinner-border-sm me-2" role="status"></div>Loading...
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="col-lg-4">
            <div class="card dashboard-card h-100">
                <div class="card-header bg-transparent border-0">
                    <h5 class="mb-0"><i class="bi bi-lightning me-2 text-warning"></i>Quick Actions</h5>
                </div>
                <div class="card-body">
                    <a href="home.php?route=boarding_roll_call" class="quick-link">
                        <i class="bi bi-clipboard-check ql-icon bg-success text-white"></i>
                        <span>Mark Roll Call</span>
                    </a>
                    <a href="home.php?route=permissions_exeats" class="quick-link">
                        <i class="bi bi-door-open ql-icon bg-warning text-white"></i>
                        <span>Manage Exeats</span>
                    </a>
                    <a href="home.php?route=dormitory_management" class="quick-link">
                        <i class="bi bi-building ql-icon bg-primary text-white"></i>
                        <span>Dormitories</span>
                    </a>
                    <a href="home.php?route=student_discipline" class="quick-link">
                        <i class="bi bi-exclamation-triangle ql-icon bg-danger text-white"></i>
                        <span>Discipline Cases</span>
                    </a>
                    <a href="home.php?route=boarding_menus" class="quick-link">
                        <i class="bi bi-cup-hot ql-icon bg-info text-white"></i>
                        <span>Meal Menus</span>
                    </a>
                    <a href="home.php?route=boarding_chapel" class="quick-link">
                        <i class="bi bi-moon-stars ql-icon bg-secondary text-white"></i>
                        <span>Chapel Services</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

</div>

<script src="js/dashboards/matron_housemother_dashboard.js"></script>
