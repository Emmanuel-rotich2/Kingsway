<?php if (!isset($appBase)) { $appBase = ''; } ?>
<div class="container-fluid py-4" id="classAttendanceHistoryPage">
  <style>
    #classAttendanceHistoryPage .register-table { min-width: 640px; }
    #classAttendanceHistoryPage .register-table .sticky-id { min-width: 118px; }
    #classAttendanceHistoryPage .register-table .sticky-name { min-width: 180px; }
    #classAttendanceHistoryPage .register-table th.sticky-id,
    #classAttendanceHistoryPage .register-table td.sticky-id,
    #classAttendanceHistoryPage .register-table th.sticky-name,
    #classAttendanceHistoryPage .register-table td.sticky-name {
      position: sticky; background-color: #fff; z-index: 2;
    }
    #classAttendanceHistoryPage .register-table th.sticky-id,
    #classAttendanceHistoryPage .register-table td.sticky-id { left: 0; border-right: 1px solid #dee2e6; }
    #classAttendanceHistoryPage .register-table th.sticky-name,
    #classAttendanceHistoryPage .register-table td.sticky-name { left: 118px; border-right: 1px solid #dee2e6; }
    #classAttendanceHistoryPage .register-table thead th { white-space: nowrap; font-size: .75rem; text-align: center; vertical-align: bottom; }
    #classAttendanceHistoryPage .register-table tbody td.date-cell { text-align: center; padding: .3rem .15rem; white-space: nowrap; }
    #classAttendanceHistoryPage .register-table tbody td.sticky-id, #classAttendanceHistoryPage .register-table tbody td.sticky-name { text-align: left; white-space: nowrap; }
    #classAttendanceHistoryPage .status-dot { display: inline-block; width: 22px; height: 22px; line-height: 22px; border-radius: 50%; font-size: .72rem; font-weight: 600; }
    #printRegisterHeader { display: none; }
    @media print {
      @page { size: A4 landscape; margin: 8mm; }
      body { background: #fff !important; }
      #historyFiltersCard, #registerActions, #pageTopbar, #pageFooter, .sidebar, .navbar, .no-print { display: none !important; }
      #printRegisterHeader { display: block !important; }
      #classAttendanceHistoryPage .card { border: none !important; box-shadow: none !important; }
      #classAttendanceHistoryPage .table-responsive { overflow: visible !important; }
      #classAttendanceHistoryPage .register-table th.sticky-id,
      #classAttendanceHistoryPage .register-table td.sticky-id,
      #classAttendanceHistoryPage .register-table th.sticky-name,
      #classAttendanceHistoryPage .register-table td.sticky-name { position: static !important; left: auto !important; }
      #classAttendanceHistoryPage .register-table thead { display: table-header-group; }
      #classAttendanceHistoryPage .register-table tr { break-inside: avoid; }
      #classAttendanceHistoryPage .register-table td, #classAttendanceHistoryPage .register-table th { font-size: 8pt; padding: 2px 4px; }
      #classAttendanceHistoryPage .print-meta td, #classAttendanceHistoryPage .status-dot { font-size: 7.5pt; }
    }
  </style>

  <div id="printRegisterHeader" class="mb-3">
    <div class="text-center mb-2">
      <h4 class="mb-0">KINGSWAY PREPARATORY SCHOOL</h4>
      <h5 class="mb-1">Class Attendance Register</h5>
      <div class="print-meta" id="printRegisterMeta"></div>
    </div>
  </div>

  <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2 no-print"><div><h3 class="mb-1"><i class="bi bi-clock-history me-2"></i>Class Attendance History</h3><p class="text-muted mb-0">View the attendance register for your class streams over any date range.</p></div><a class="btn btn-primary btn-sm" href="<?= $appBase ?>/home.php?route=class_mark_attendance"><i class="bi bi-clipboard-check me-1"></i>Mark Today&rsquo;s Attendance</a></div>

  <div class="card shadow-sm border-0 mb-3 no-print" id="historyFiltersCard"><div class="card-body">
    <div class="row g-3 align-items-end">
      <div class="col-md-3"><label class="form-label fw-semibold">My Class / Stream</label><select id="historyStream" class="form-select"><option value="">All My Classes</option></select></div>
      <div class="col-md-2"><label class="form-label fw-semibold">Date Range</label><select id="historyRangeType" class="form-select"><option value="custom">Custom dates</option><option value="week" selected>This week</option><option value="month">Month</option><option value="term">Term</option><option value="year">Year</option></select></div>
      <div class="col-md-3" id="filtersCustom"><label class="form-label fw-semibold">From / To</label><div class="d-flex gap-2"><input id="historyFrom" type="date" class="form-control"><input id="historyTo" type="date" class="form-control"></div></div>
      <div class="col-md-2 d-none" id="filtersWeek"><label class="form-label fw-semibold">Week Starting</label><input id="historyWeekStart" type="date" class="form-control"></div>
      <div class="col-md-2 d-none" id="filtersMonth"><label class="form-label fw-semibold">Month</label><input id="historyMonth" type="month" class="form-control"></div>
      <div class="col-md-3 d-none" id="filtersTerm"><label class="form-label fw-semibold">Term</label><select id="historyTerm" class="form-select"><option value="">Select term</option></select></div>
      <div class="col-md-2 d-none" id="filtersYear"><label class="form-label fw-semibold">Year</label><select id="historyYear" class="form-select"></select></div>
    </div>
    <div class="mt-3 small text-muted" id="historyRangeHint"><i class="bi bi-arrow-down-circle me-1"></i>Updates automatically as you change the filters.</div>
  </div></div>

  <div id="historyMessage" class="alert d-none no-print"></div>
  <div class="card shadow-sm border-0">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2 no-print">
      <div class="d-flex align-items-center gap-3 flex-wrap"><h5 class="mb-0" id="registerTitle">Attendance Register</h5><div id="registerLegend" class="d-flex gap-3 small text-muted"><span><span class="status-dot text-bg-success">P</span> Present</span><span><span class="status-dot text-bg-danger">A</span> Absent</span><span><span class="status-dot text-bg-warning text-dark">L</span> Late</span><span><span class="status-dot text-bg-info text-dark">Pm</span> Permission</span><span><span class="status-dot text-bg-light border">&ndash;</span> Not marked</span></div></div>
      <div id="registerActions" class="d-flex gap-2"><button class="btn btn-outline-secondary btn-sm" id="exportCsvBtn"><i class="bi bi-filetype-csv me-1"></i>Export CSV</button><button class="btn btn-outline-secondary btn-sm" id="printPdfBtn"><i class="bi bi-printer me-1"></i>Print / PDF</button></div>
    </div>
    <div class="table-responsive"><table class="table table-hover table-bordered mb-0 register-table"><thead id="historyHead"><tr><th class="sticky-id">Admission No.</th><th class="sticky-name">Learner</th></tr></thead><tbody id="historyBody"><tr><td colspan="2" class="text-center text-muted py-4">Loading your attendance register&hellip;</td></tr></tbody><tfoot id="historyFoot"></tfoot></table></div>
  </div>
</div>
<?php asset_script($appBase, 'js/pages/class_attendance_history.js'); ?>