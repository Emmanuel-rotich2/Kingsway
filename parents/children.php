<?php
declare(strict_types=1);
$parentPageTitle  = 'My Children';
$parentActive     = 'children';
$parentPageScript = 'parents/children';
require __DIR__ . '/_head.php';
?>
<div class="container pp-container">
  <div class="pp-card mb-4">
    <div class="pp-card-header">
      <h1><i class="bi bi-people-fill me-2 text-success"></i>My children</h1>
      <span class="badge bg-success-subtle text-success rounded-pill" id="ppChildCount"></span>
    </div>
    <div class="pp-card-body">
      <p class="text-muted mb-4">Every child linked to your family account. Select a child to explore fees, results, attendance, messages, documents and transport.</p>
      <div class="row" id="ppChildrenCards"></div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/_foot.php'; ?>