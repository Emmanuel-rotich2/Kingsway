<?php
/* PARTIAL — no DOCTYPE/html/head/body. Injected into app shell via app_layout.php */
?>
<div class="container-fluid py-4" id="attendanceReportsPage">

  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h3 class="mb-0"><i class="bi bi-clipboard2-data me-2 text-primary"></i>Attendance Reports</h3>
      <small class="text-muted">Attendance statistics by class, student, and date range</small>
    </div>
    <button class="btn btn-outline-success btn-sm" onclick="attendanceReportsController.exportCSV()">
      <i class="bi bi-download me-1"></i>Export CSV
    </button>
  </div>

  <!-- Filters -->
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
      <div class="row g-2 align-items-end">
        <div class="col-md-3">
          <label class="form-label small fw-semibold">Period</label>
          <select id="arPeriod" class="form-select form-select-sm" onchange="attendanceReportsController.onPeriodChange()">
            <option value="this_week">This Week</option>
            <option value="this_month" selected>This Month</option>
            <option value="this_term">This Term</option>
            <option value="custom">Custom Range</option>
          </select>
        </div>
        <div class="col-md-2 d-none" id="arDateFromWrap">
          <label class="form-label small fw-semibold">From</label>
          <input type="date" id="arDateFrom" class="form-control form-control-sm">
        </div>
        <div class="col-md-2 d-none" id="arDateToWrap">
          <label class="form-label small fw-semibold">To</label>
          <input type="date" id="arDateTo" class="form-control form-control-sm">
        </div>
        <div class="col-md-3">
          <label class="form-label small fw-semibold">Class</label>
          <select id="arClass" class="form-select form-select-sm">
            <option value="">All Classes</option>
          </select>
        </div>
        <div class="col-md-2">
          <button class="btn btn-primary btn-sm w-100" onclick="attendanceReportsController.load()">
            <i class="bi bi-search me-1"></i>Generate
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Summary Stats -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-primary" id="arStatTotal">—</div>
          <div class="text-muted small">Total Students</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-success" id="arStatRate">—</div>
          <div class="text-muted small">Average Attendance Rate</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-danger" id="arStatAbsent">—</div>
          <div class="text-muted small">Absent Today</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-warning" id="arStatChronic">—</div>
          <div class="text-muted small">Chronic Absentees (&lt;80%)</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Tabs -->
  <ul class="nav nav-tabs mb-0" id="arTabs">
    <li class="nav-item"><button class="nav-link active" data-tab="classes">By Class</button></li>
    <li class="nav-item"><button class="nav-link" data-tab="chronic">Chronic Absentees</button></li>
    <li class="nav-item"><button class="nav-link" data-tab="trends">Trends</button></li>
  </ul>

  <!-- By Class -->
  <div id="arTabClasses" class="card border-0 shadow-sm border-top-0 rounded-top-0">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th>Class</th>
              <th class="text-center">Enrolled</th>
              <th class="text-center">Present Today</th>
              <th class="text-center">Absent Today</th>
              <th class="text-center">Term Rate</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody id="arClassTableBody">
            <tr><td colspan="6" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div> Loading…</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Chronic Absentees -->
  <div id="arTabChronic" class="d-none card border-0 shadow-sm border-top-0 rounded-top-0">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th>Student</th>
              <th>Adm. No.</th>
              <th>Class</th>
              <th class="text-center">Days Present</th>
              <th class="text-center">Days Absent</th>
              <th class="text-center">Rate</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody id="arChronicTableBody">
            <tr><td colspan="7" class="text-center py-4 text-muted">Click Generate to load.</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Trends -->
  <div id="arTabTrends" class="d-none card border-0 shadow-sm border-top-0 rounded-top-0">
    <div class="card-body">
      <canvas id="arTrendsChart" height="110"></canvas>
    </div>
  </div>

</div>
<script src="<?= $appBase ?>js/pages/attendance_reports.js"></script>
