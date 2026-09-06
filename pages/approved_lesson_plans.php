<?php
/* Approved Lesson Plans — standalone list of approved plans (Deputy/HT view). */
$appBase = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($appBase === '.') $appBase = '';
?>

<style>
.alp-table td,.alp-table th { vertical-align:middle;font-size:.85rem; }
</style>

<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-patch-check text-success me-2"></i>Approved Lesson Plans</h4>
      <p class="text-muted small mb-0 mt-1">All lesson plans that have been reviewed and approved.</p>
    </div>
  </div>

  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div class="d-flex gap-2 flex-wrap">
      <input type="text" id="alpSearch" class="form-control form-control-sm" placeholder="Search topic…" style="width:200px">
      <select id="alpSubject" class="form-select form-select-sm" style="width:180px">
        <option value="">All learning areas</option>
      </select>
      <select id="alpClass" class="form-select form-select-sm" style="width:180px">
        <option value="">All classes</option>
      </select>
    </div>
    <button class="btn btn-outline-success btn-sm" onclick="approvedLessonPlansController.loadData()">
      <i class="bi bi-arrow-clockwise"></i>
    </button>
  </div>

  <div class="bg-white border rounded-3 overflow-hidden">
    <table class="table table-hover alp-table mb-0">
      <thead class="table-light"><tr>
        <th scope="col">Topic</th><th scope="col">Learning Area</th><th scope="col">Class</th><th scope="col">Teacher</th><th scope="col">Date</th><th scope="col">Status</th><th class="text-end">Actions</th>
      </tr></thead>
      <tbody id="alpBody"><tr><td colspan="7" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
    </table>
  </div>

</div>

<!-- View Modal -->
<div class="modal fade" id="alpViewModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-scrollable modal-lg"><div class="modal-content">
    <div class="modal-header border-0 pb-0">
      <h5 class="modal-title fw-bold" id="alpViewTitle">Lesson Plan</h5>
      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body" id="alpViewBody"></div>
  </div></div>
</div>

<?php asset_script($appBase, 'js/pages/approved_lesson_plans.js'); ?>
