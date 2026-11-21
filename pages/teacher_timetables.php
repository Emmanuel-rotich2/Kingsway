<?php
/* Teacher Timetables — weekly schedule viewer for each teacher (Deputy view). */

?>

<style>
.tt-grid { display:grid; grid-template-columns:90px repeat(5,1fr); gap:6px; }
.tt-cell { border:1px solid #e2e8f0;border-radius:8px;background:#fff;min-height:64px;padding:6px;font-size:.75rem; }
.tt-cell-head { background:#198754;color:#fff;font-weight:600;text-align:center;border:none; }
.tt-period { background:#f8fafc;font-weight:600;display:flex;align-items:center;justify-content:center;border:none; }
.tt-entry { background:#e8f5e9;border:1px solid #c8e6c9;border-radius:6px;padding:4px 6px;margin-bottom:4px; }
</style>

<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-calendar2-week text-success me-2"></i>Teacher Timetables</h4>
      <p class="text-muted small mb-0 mt-1">View the weekly teaching timetable for any teacher.</p>
    </div>
  </div>

  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div class="d-flex gap-2 flex-wrap">
      <select id="academicYearFilter" class="form-select form-select-sm" style="width:200px" aria-label="Academic year"><option>Loading…</option></select>
      <select id="academicTermFilter" class="form-select form-select-sm" style="width:140px" aria-label="Term"><option>Loading…</option></select>
      <select id="teacherSelect" class="form-select form-select-sm" style="width:320px">
        <option value="">— Select teacher —</option>
      </select>
    </div>
    <button class="btn btn-outline-success btn-sm" data-kw-onclick="teacherTimetablesController.loadData()">
      <i class="bi bi-arrow-clockwise"></i>
    </button>
  </div>

  <div class="bg-white border rounded-3 p-3">
    <div class="tt-grid" id="timetableGrid">
      <div class="text-muted small p-3">Select a teacher to view their timetable.</div>
    </div>
  </div>

</div>

<?php asset_script($appBase, 'js/pages/teacher_timetables.js'); ?>
