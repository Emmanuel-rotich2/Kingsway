<?php
$token = htmlspecialchars($_GET['token'] ?? '', ENT_QUOTES, 'UTF-8');
?>
<div class="container py-5" style="max-width:620px">
  <div class="card border-0 shadow-sm"><div class="card-body p-4">
    <h3 class="mb-2">Create your private password</h3><p class="text-muted">Your temporary password cannot be used to access the dashboard until it is replaced.</p>
    <div id="rdpState" class="alert alert-info">Enter and confirm a strong password.</div>
    <form id="rdpForm"><input type="hidden" id="rdpToken" value="<?= $token ?>"><div class="mb-3"><label class="form-label">New password</label><input id="rdpPassword" type="password" class="form-control" minlength="10" required></div><div class="mb-3"><label class="form-label">Confirm password</label><input id="rdpConfirm" type="password" class="form-control" minlength="10" required></div><button class="btn btn-primary w-100">Update password</button></form>
  </div></div>
</div>
<script src="<?= $appBase ?? '' ?>/js/pages/reset_default_password.js"></script>
