<?php
declare(strict_types=1);
/* PTA & Community — no per-page sidebar */
$parentPageTitle  = 'PTA & Community';
$parentActive     = 'community';
$parentPageScript = 'parents/community';
require __DIR__ . '/_head.php';
?>
<div class="container pp-container">
  <div class="pp-card">
    <div class="pp-card-header">
      <h1><i class="bi bi-person-hearts me-2 text-success"></i>PTA & parent community</h1>
      <span class="badge bg-success-subtle text-success rounded-pill" id="ppMeetingCount"></span>
    </div>
    <div class="pp-card-body">
      <div class="alert alert-light border mb-4">
        <i class="bi bi-info-circle me-2 text-success"></i>
        School meetings, parent forums and PTA representative information appear here.
      </div>
      <div class="row" id="ppCommunityContent"></div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/_foot.php'; ?>