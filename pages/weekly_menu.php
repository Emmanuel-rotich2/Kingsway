<?php
/* Weekly Menu — the school week's meals (Mon–Fri). */
$appBase = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($appBase === '.') $appBase = '';
?>

<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-calendar3-week text-success me-2"></i>Weekly Menu</h4>
      <p class="text-muted small mb-0 mt-1">Meals planned across the school week (Monday–Friday).</p>
    </div>
    <div class="d-flex gap-2 align-items-center">
      <input type="date" class="form-control form-control-sm" id="wmPicker" style="width: 170px;">
      <button class="btn btn-outline-secondary btn-sm" id="wmPrintBtn" type="button"><i class="bi bi-printer me-1"></i>Print / PDF</button>
    </div>
  </div>

  <div id="wmDays"></div>

</div>
<style>@media print { #wmPicker, #wmPrintBtn { display:none !important; } }</style>

<?php asset_script($appBase, 'js/pages/weekly_menu.js'); ?>
