<?php
declare(strict_types=1);
$parentPageTitle  = 'Dashboard';
$parentActive     = 'dashboard';
$parentPageScript = 'parents/dashboard';
require __DIR__ . '/_head.php';
?>
<div class="container pp-container">
  <div class="pp-welcome p-4 p-md-5 mb-4" id="ppWelcome">
    <div class="row align-items-center g-3">
      <div class="col-lg-8">
        <div class="small opacity-75 mb-1">Welcome back</div>
        <h2>Your children, all in one place.</h2>
        <p>Stay connected to learning, wellbeing, payments and the Kingsway community.</p>
      </div>
      <div class="col-lg-4 text-lg-end">
        <span class="badge bg-warning text-dark rounded-pill px-3 py-2"><i class="bi bi-shield-check me-1"></i>Secure family account</span>
      </div>
    </div>
  </div>
  <div class="row g-3 mb-4" id="ppKpis"></div>
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
      <h3 class="h5 fw-bold mb-1" id="ppChildrenHeading">My children</h3>
      <p class="text-muted small mb-0">Select a child to view their complete school journey.</p>
    </div>
    <a href="<?= $appBase ?>/index.php?route=uniform-catalog" class="btn btn-outline-success btn-sm rounded-pill" target="_blank" rel="noopener"><i class="bi bi-bag me-1"></i>Visit store</a>
  </div>
  <div class="row" id="ppChildrenCards"></div>
</div>
<?php require __DIR__ . '/_foot.php'; ?>