<?php
$appBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($appBase === '.') {
    $appBase = '';
}
$pageTitle = 'Create Staff Password';
$activePage = 'login';
$token = htmlspecialchars($_GET['token'] ?? '', ENT_QUOTES, 'UTF-8');
require_once __DIR__ . '/public/layout/public_data.php';
?>
<?php include __DIR__ . '/public/layout/header.php'; ?>

<section class="py-5" style="min-height:70vh;background:#f6f8f7;">
  <div class="container py-5" style="max-width:620px">
    <div class="card border-0 shadow-sm">
      <div class="card-body p-4">
        <h3 class="mb-2">Create your private password</h3>
        <p class="text-muted">Use the setup link from your staff invitation email. Your default password must be replaced before dashboard access.</p>
        <div id="rdpState" class="alert alert-info">Enter and confirm a strong password.</div>
        <form id="rdpForm">
          <input type="hidden" id="rdpToken" value="<?= $token ?>">
          <div class="mb-3">
            <label class="form-label">New password</label>
            <input id="rdpPassword" type="password" class="form-control" minlength="10" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Confirm password</label>
            <input id="rdpConfirm" type="password" class="form-control" minlength="10" required>
          </div>
          <button class="btn btn-primary w-100" type="submit">Update password</button>
        </form>
      </div>
    </div>
  </div>
</section>

<script src="<?= $appBase ?>/js/pages/reset_default_password.js?v=<?= filemtime(__DIR__ . '/js/pages/reset_default_password.js') ?>"></script>
<?php include __DIR__ . '/public/layout/footer.php'; ?>
