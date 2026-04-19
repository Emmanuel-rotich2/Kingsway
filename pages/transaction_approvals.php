<?php
/* PARTIAL — no DOCTYPE/html/head/body. Injected into app shell via app_layout.php */
?>
<div class="container-fluid py-4" id="page-transaction_approvals">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h3 class="mb-0"><i class="bi bi-check2-square me-2 text-primary"></i>Transaction Approvals</h3>
      <small class="text-muted">Pending financial transactions awaiting approval</small>
    </div>
  </div>
  <div id="transaction_approvals-content">
    <div class="text-center py-5">
      <div class="spinner-border text-primary mb-3" role="status"></div>
      <p class="text-muted">Loading...</p>
    </div>
  </div>
</div>
