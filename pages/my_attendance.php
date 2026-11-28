<?php /** My HR — My Attendance: own attendance history, punctuality insights, CSV/PDF. */ ?>
<style>@media print { #myAttendancePage .d-print-none { display: none !important; } }</style>
<div class="container-fluid py-4" id="myAttendancePage" data-staff-page>
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div><h3 class="mb-1"><i class="bi bi-calendar-check me-2"></i>My Attendance</h3><p class="text-muted mb-0">Your attendance, punctuality and leave for the period you choose.</p></div>
    <div class="d-flex gap-2 d-print-none">
      <button id="myAttendanceCsv" class="btn btn-outline-primary btn-sm"><i class="bi bi-download me-1"></i>CSV</button>
      <button id="myAttendancePrint" class="btn btn-outline-secondary btn-sm"><i class="bi bi-printer me-1"></i>Print / PDF</button>
      <a href="home.php?route=staff_leave" class="btn btn-outline-primary btn-sm">My Leave</a>
    </div>
  </div>
  <div class="card border-0 shadow-sm mb-3 d-print-none"><div class="card-body"><div class="row g-2">
    <div class="col-md-3"><label class="form-label small" for="myAttendancePeriod">Period</label>
      <select id="myAttendancePeriod" class="form-select"><option value="month">This month</option><option value="last_month">Last month</option><option value="term">Last 3 months</option><option value="year">This year</option><option value="custom">Custom</option></select></div>
    <div class="col-md-3"><label class="form-label small" for="myAttendanceFrom">From</label><input id="myAttendanceFrom" type="date" class="form-control"></div>
    <div class="col-md-3"><label class="form-label small" for="myAttendanceTo">To</label><input id="myAttendanceTo" type="date" class="form-control"></div>
  </div></div></div>
  <div class="row g-3 mb-3">
    <div class="col-6 col-md-2"><div class="card border-0 shadow-sm h-100"><div class="card-body"><small class="text-muted">Attendance rate</small><h3 id="myRate">—</h3></div></div></div>
    <div class="col-6 col-md-2"><div class="card border-0 shadow-sm h-100"><div class="card-body"><small class="text-muted">Present</small><h3 id="myPresent">0</h3></div></div></div>
    <div class="col-6 col-md-2"><div class="card border-0 shadow-sm h-100"><div class="card-body"><small class="text-muted">Late</small><h3 id="myLate">0</h3></div></div></div>
    <div class="col-6 col-md-2"><div class="card border-0 shadow-sm h-100"><div class="card-body"><small class="text-muted">Absent</small><h3 id="myAbsent">0</h3></div></div></div>
    <div class="col-6 col-md-2"><div class="card border-0 shadow-sm h-100"><div class="card-body"><small class="text-muted">On-time rate</small><h3 id="myOnTime">—</h3></div></div></div>
    <div class="col-6 col-md-2"><div class="card border-0 shadow-sm h-100"><div class="card-body"><small class="text-muted">Average arrival</small><h3 id="myAvgIn">—</h3></div></div></div>
  </div>
  <div class="row g-3 mb-3">
    <div class="col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white"><strong>Insights</strong></div><div class="card-body small" id="myInsights">—</div></div></div>
    <div class="col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white"><strong>By month</strong></div><div class="card-body p-0"><table class="table table-sm mb-0"><thead class="table-light"><tr><th>Month</th><th>Days</th><th>Present</th><th>Late</th><th>Absent</th><th>Rate</th></tr></thead><tbody id="myMonthly"></tbody></table></div></div></div>
  </div>
  <div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-hover mb-0"><thead class="table-light"><tr><th scope="col">Date</th><th scope="col">Shift</th><th scope="col">Status</th><th scope="col">Check In</th><th scope="col">Check Out</th><th scope="col">Reason / Notes</th></tr></thead><tbody id="myAttendanceBody"><tr><td colspan="6" class="text-center py-4">Loading…</td></tr></tbody></table></div></div>
</div>
<?php asset_script($appBase, 'js/pages/staff_access.js'); ?><?php asset_script($appBase, 'js/pages/my_attendance.js'); ?>
