<?php
/**
 * Boarding Roll Call Page
 * 
 * Purpose: Allow House Parents/Boarding Masters to mark dormitory attendance
 * - Morning roll call (6:00 AM)
 * - Night roll call (9:30 PM)
 * - Weekend roll calls
 * - Evening prep attendance
 * 
 * Role: House Parent, Boarding Master, Admin
 * 
 * This is an embedded component - loaded via home.php?route=boarding_roll_call
 */
?>

<style>
    .roll-call-card {
        transition: all 0.2s;
    }

    .roll-call-card:hover {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .status-present {
        background-color: #d4edda !important;
    }

    .status-absent {
        background-color: #f8d7da !important;
    }

    .status-permission {
        background-color: #fff3cd !important;
    }

    .status-sick-bay {
        background-color: #cce5ff !important;
    }

    .dormitory-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }

    .session-badge {
        font-size: 0.9rem;
        padding: 0.5rem 1rem;
    }

    .student-row {
        border-left: 4px solid transparent;
        transition: all 0.2s;
    }

    .student-row.has-permission {
        border-left-color: #ffc107;
    }

    .quick-stats {
        background: #f8f9fa;
        border-radius: 10px;
        padding: 1rem;
    }

    .stat-item {
        text-align: center;
    }

    .stat-value {
        font-size: 2rem;
        font-weight: bold;
    }
</style>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h3 class="mb-1"><i class="bi bi-house-door me-2"></i>Boarding Roll Call</h3>
                    <p class="text-muted mb-0">Mark dormitory attendance for morning and evening sessions</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="/Kingsway/home.php?route=view_attendance&type=boarding" class="btn btn-outline-secondary">
                        <i class="bi bi-clock-history me-1"></i> View History
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Selection Controls -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Dormitory</label>
                    <select id="dormitorySelect" class="form-select">
                        <option value="">-- Select Dormitory --</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Session</label>
                    <select id="sessionSelect" class="form-select">
                        <option value="">-- Select Session --</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Date</label>
                    <input type="date" id="rollCallDate" class="form-control">
                </div>
                <div class="col-md-2">
                    <button id="loadStudentsBtn" class="btn btn-primary w-100">
                        <i class="bi bi-search me-1"></i> Load Students
                    </button>
                </div>
                <div class="col-md-2">
                    <button id="refreshBtn" class="btn btn-outline-secondary w-100">
                        <i class="bi bi-arrow-clockwise me-1"></i> Refresh
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="quick-stats mb-4" id="quickStats" style="display: none;">
        <div class="row">
            <div class="col">
                <div class="stat-item">
                    <div class="stat-value text-primary" id="statTotal">0</div>
                    <div class="text-muted">Total</div>
                </div>
            </div>
            <div class="col">
                <div class="stat-item">
                    <div class="stat-value text-success" id="statPresent">0</div>
                    <div class="text-muted">Present</div>
                </div>
            </div>
            <div class="col">
                <div class="stat-item">
                    <div class="stat-value text-danger" id="statAbsent">0</div>
                    <div class="text-muted">Absent</div>
                </div>
            </div>
            <div class="col">
                <div class="stat-item">
                    <div class="stat-value text-warning" id="statPermission">0</div>
                    <div class="text-muted">On Permission</div>
                </div>
            </div>
            <div class="col">
                <div class="stat-item">
                    <div class="stat-value text-info" id="statSickBay">0</div>
                    <div class="text-muted">Sick Bay</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading State -->
    <div id="loadingState" class="text-center py-5" style="display: none;">
        <div class="spinner-border text-primary" role="status"></div>
        <p class="mt-2 text-muted">Loading students...</p>
    </div>

    <!-- Empty State -->
    <div id="emptyState" class="text-center py-5">
        <i class="bi bi-house-door display-1 text-muted"></i>
        <h5 class="mt-3">Select a Dormitory and Session</h5>
        <p class="text-muted">Choose a dormitory and roll call session to begin marking attendance</p>
    </div>

    <!-- Roll Call Card -->
    <div id="rollCallCard" class="card shadow-sm" style="display: none;">
        <div class="card-header dormitory-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-1" id="dormitoryTitle">Eagles House</h5>
                    <span class="badge bg-light text-dark session-badge" id="sessionBadge">Morning Roll Call</span>
                </div>
                <div>
                    <span class="badge bg-light text-dark" id="dateDisplay">7 January 2026</span>
                </div>
            </div>
        </div>

        <!-- Bulk Actions -->
        <div class="card-body border-bottom bg-light">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="btn-group">
                    <button id="markAllPresent" class="btn btn-success btn-sm">
                        <i class="bi bi-check-all me-1"></i> All Present
                    </button>
                    <button id="markAllAbsent" class="btn btn-danger btn-sm">
                        <i class="bi bi-x-circle me-1"></i> All Absent
                    </button>
                </div>
                <div>
                    <button id="submitRollCall" class="btn btn-primary">
                        <i class="bi bi-check2-circle me-2"></i>Submit Roll Call
                    </button>
                </div>
            </div>
        </div>

        <!-- Students Table -->
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th width="5%">#</th>
                        <th width="12%">Adm No</th>
                        <th width="25%">Student Name</th>
                        <th width="12%">Class</th>
                        <th width="10%">Bed</th>
                        <th width="15%">Permission</th>
                        <th width="21%">Status</th>
                    </tr>
                </thead>
                <tbody id="studentsTableBody">
                    <!-- Students rendered here -->
                </tbody>
            </table>
        </div>
    </div>

    <!-- Today's Summary -->
    <div class="card shadow-sm mt-4" id="summaryCard" style="display: none;">
        <div class="card-header bg-white">
            <h5 class="mb-0"><i class="bi bi-bar-chart me-2"></i>Today's Boarding Summary</h5>
        </div>
        <div class="card-body">
            <div id="summaryContent">
                <!-- Summary will be loaded here -->
            </div>
        </div>
    </div>
</div>

<script src="/Kingsway/js/pages/boarding_roll_call.js?v=<?php echo time(); ?>"></script>