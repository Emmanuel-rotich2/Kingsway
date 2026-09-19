<?php
declare(strict_types=1);
/* School Updates — announcements & events (no child selector) */
$parentPageTitle  = 'School Updates';
$parentActive     = 'updates';
$parentPageScript = 'parents/updates';
$parentSidebar    = false;
require __DIR__ . '/_head.php';
?>
<div class="container pp-container">
  <div class="pp-card">
    <div class="pp-card-header">
      <h1><i class="bi bi-megaphone-fill me-2 text-success"></i>School Updates</h1>
      <div class="d-flex gap-2">
        <button class="btn btn-outline-success btn-sm" type="button" id="btnUpdatesCsv"><i class="bi bi-filetype-csv me-1"></i>CSV</button>
        <button class="btn btn-outline-success btn-sm" type="button" id="btnUpdatesPrint"><i class="bi bi-printer me-1"></i>Print</button>
      </div>
    </div>
    <div class="pp-card-body">
      <ul class="nav nav-tabs mb-3" id="ppUpdatesTabs" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-pp-tab="announcements" type="button">Announcements</button></li>
        <li class="nav-item"><button class="nav-link" data-pp-tab="events" type="button">Events &amp; dates</button></li>
      </ul>
      <div id="ppUpdatesContent"></div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/_foot.php'; ?>