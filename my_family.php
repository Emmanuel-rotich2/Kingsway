<?php
/* Staff private family workspace. Reuses the parent cpanel shell but
   authenticates every request with the staff JWT through /api/family/*.
   The dashboard controller renders the family overview via
   js/pages/parents/dashboard.js (staff mode). */
declare(strict_types=1);
$familyStaffMode = true;
$parentPageTitle  = 'Family';
$parentActive     = 'dashboard';
$parentPageScript = 'parents/dashboard';
$parentBodyClass  = 'pp-staff-family-mode';
require __DIR__ . '/parents/_head.php';
?>
<div class="container pp-container">
  <div class="pp-welcome p-4 p-md-5 mb-4">
    <div class="row align-items-center g-3">
      <div class="col-lg-8">
        <div class="small opacity-75 mb-1">Staff family workspace</div>
        <h2>Your registered family,</h2>
        <p>Quick overview of the children linked to the family account you manage.</p>
      </div>
      <div class="col-lg-4 text-lg-end">
        <span class="badge bg-warning text-dark rounded-pill px-3 py-2"><i class="bi bi-person-badge me-1"></i>Staff mode</span>
      </div>
    </div>
  </div>
  <div class="row g-3 mb-4" id="ppKpis"></div>
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
      <h3 class="h5 fw-bold mb-1">Linked children</h3>
      <p class="text-muted small mb-0">Select a child to open the full parent views (open in the parent centre).</p>
    </div>
    <a href="<?= $appBase ?>/index.php?route=r39d07ccbcf8a" class="btn btn-outline-success btn-sm rounded-pill" target="_blank" rel="noopener"><i class="bi bi-bag me-1"></i>Visit store</a>
  </div>
  <div class="row" id="ppChildrenCards"></div>
</div>
<?php require __DIR__ . '/parents/_foot.php'; ?>