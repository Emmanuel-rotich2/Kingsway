<?php
declare(strict_types=1);
/* Account Settings — no per-page sidebar */
$parentPageTitle  = 'Account Settings';
$parentActive     = 'account';
$parentPageScript = 'parents/account';
require __DIR__ . '/_head.php';
?>
<div class="container pp-container">
  <div class="row g-4">
    <div class="col-lg-7">
      <div class="pp-card">
        <div class="pp-card-header"><h1><i class="bi bi-person-circle me-2 text-success"></i>Contact details</h1></div>
        <div class="pp-card-body">
          <dl class="row mb-0" id="ppAccountDetails">
            <dt class="col-sm-4">Name</dt><dd class="col-sm-8" id="ppAccName">—</dd>
            <dt class="col-sm-4">Email</dt><dd class="col-sm-8" id="ppAccEmail">—</dd>
            <dt class="col-sm-4">Phone</dt><dd class="col-sm-8" id="ppAccPhone">—</dd>
          </dl>
          <div class="alert alert-light border mt-3 mb-3 small">
            <i class="bi bi-shield-lock me-1 text-success"></i>
            For safeguarding, identity and contact changes are verified by the school office.
          </div>
        </div>
      </div>
    </div>
    <div class="col-lg-5">
      <div class="pp-card">
        <div class="pp-card-header"><h1><i class="bi bi-shield-check me-2 text-success"></i>Security</h1></div>
        <div class="pp-card-body">
          <p class="text-muted small">This portal uses a time-limited family session. Sign out on shared devices.</p>
          <button class="btn btn-outline-danger rounded-pill" type="button" id="btnLogoutInline"><i class="bi bi-box-arrow-right me-1"></i>Sign out securely</button>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/_foot.php'; ?>