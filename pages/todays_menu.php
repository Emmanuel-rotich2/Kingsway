<?php
/* Today's Menu — the day's planned meals across all meal types. */
$appBase = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($appBase === '.') $appBase = '';
?>

<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-calendar2-day text-success me-2"></i>Today's Menu</h4>
      <p class="text-muted small mb-0 mt-1" id="tmDate">Meals planned for today.</p>
    </div>
    <div class="d-flex gap-2 align-items-center">
      <input type="date" class="form-control form-control-sm" id="tmPicker" style="width: 170px;">
      <button class="btn btn-outline-secondary btn-sm" id="tmPrintBtn" type="button"><i class="bi bi-printer me-1"></i>Print / PDF</button>
    </div>
  </div>

  <div id="tmStats" class="row g-3 mb-4"></div>

  <div id="tmMeals"></div>

</div>
<style>@media print { #tmPicker, #tmPrintBtn { display:none !important; } }</style>

<?php asset_script($appBase, 'js/pages/todays_menu.js'); ?>
