<?php
$appBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($appBase === '.') {
    $appBase = '';
}
$pageTitle = 'Create Staff Password';
$activePage = 'login';
$bodyClass = 'auth-page auth-recovery-page account-setup-page';
$rawToken = (string)($_GET['token'] ?? '');
$token = htmlspecialchars($rawToken, ENT_QUOTES, 'UTF-8');
require_once __DIR__ . '/public/layout/public_data.php';
$isParentInvitation = false;
$resumeStaffInvitationOtp = false;
if ($rawToken !== '' && function_exists('kw_db') && ($setupDb = kw_db())) {
    try {
        $setupStmt = $setupDb->prepare("SELECT ui.staff_id,ui.status,ui.expires_at,u.status AS account_status,u.force_password_change,u.password_changed_at,u.profile_completed_at
            FROM user_invitations ui JOIN users u ON u.id=ui.user_id
            WHERE ui.token_hash=? AND (
                (ui.status='pending' AND ui.expires_at>NOW() AND u.status='active' AND u.force_password_change=1 AND u.password_changed_at IS NULL AND u.profile_completed_at IS NULL)
                OR (ui.status='accepted' AND u.status='active' AND u.force_password_change=0 AND u.password_changed_at IS NOT NULL AND u.profile_completed_at IS NULL)
            ) ORDER BY ui.id DESC LIMIT 1");
        $setupStmt->execute([hash('sha256', $rawToken)]);
        $setupInvitation = $setupStmt->fetch(PDO::FETCH_ASSOC);
        $isParentInvitation = is_array($setupInvitation) && empty($setupInvitation['staff_id']);
        $resumeStaffInvitationOtp = is_array($setupInvitation)
            && !empty($setupInvitation['staff_id'])
            && $setupInvitation['status'] === 'accepted';
    } catch (Throwable $ignored) {
        $isParentInvitation = false;
    }
}
?>
<?php include __DIR__ . '/public/layout/header.php'; ?>
<link rel="stylesheet" href="<?= $appBase ?>/css/pages/sign-in.css?v=<?= asset_version('css/pages/sign-in.css') ?>">

<style>
.account-setup-page .site-nav{background:#075b35;box-shadow:0 5px 18px rgba(4,61,35,.22);border-bottom:3px solid #d5a928;padding:10px 0}
.account-setup-page .site-nav .navbar-brand,.account-setup-page .site-nav .nav-link{color:#fffdf7!important}
.account-setup-page .site-nav .nav-link:hover,.account-setup-page .site-nav .nav-link.active{color:#f4cf55!important;background:rgba(255,255,255,.09)}
.account-setup-page .site-nav .nav-link.btn-login{color:#075b35!important;background:#d5a928}
.account-setup-page .site-nav .navbar-toggler{color:#fff}.account-setup-page .site-nav .navbar-toggler i{color:#fff!important}
.setup-page{min-height:76vh;background:linear-gradient(135deg,#fdf9ec 0%,#eef7f0 55%,#fffdf7 100%);padding:124px 0 64px}
.setup-shell{max-width:980px;margin:auto;background:#fffdf8;border:1px solid #eadcae;border-radius:22px;overflow:hidden;box-shadow:0 24px 60px rgba(7,91,53,.13)}
.setup-aside{background:linear-gradient(155deg,#06482b,#087344);color:#fff;padding:44px;position:relative;overflow:hidden}
.setup-aside:after{content:"";position:absolute;width:230px;height:230px;border:45px solid rgba(213,169,40,.18);border-radius:50%;right:-100px;bottom:-110px}
.setup-step{display:flex;gap:12px;align-items:center;margin:20px 0;position:relative;z-index:1}.setup-step span{width:32px;height:32px;border-radius:50%;display:grid;place-items:center;background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.35);font-weight:700}.setup-step.active span{background:#d5a928;color:#173c28;border-color:#f1d574}
.setup-form{padding:44px}.setup-form .form-control{min-height:50px;border-radius:10px}.setup-form .form-control:focus{border-color:#087344;box-shadow:0 0 0 .2rem rgba(8,115,68,.12)}
.password-rule{font-size:.8rem;color:#7a7f79}.password-rule.ok{color:#087344;font-weight:600}.btn-school{background:#087344;color:#fff;border:0;min-height:50px;border-radius:10px;font-weight:700}.btn-school:hover{background:#06482b;color:#fff}
@media(max-width:767px){.setup-page{padding:104px 12px 34px}.setup-aside,.setup-form{padding:28px}.account-setup-page .site-nav .navbar-collapse{background:#075b35;padding:12px;border-radius:0 0 12px 12px}}
</style>
<section class="setup-page">
  <a class="auth-brand auth-recovery-brand" href="<?= htmlspecialchars($appBase) ?>/index.php"><img src="<?= htmlspecialchars($appBase) ?>/uploads/school_assets/official_school_logo.png" alt=""><span><strong>Kingsway Preparatory School</strong><small>In God We Soar</small></span></a>
  <div class="container">
    <div class="setup-shell row g-0">
      <aside class="setup-aside col-md-5">
        <span class="badge rounded-pill mb-3" style="background:#d5a928;color:#173c28"><?= $isParentInvitation ? 'PARENT PORTAL SETUP' : 'STAFF ONBOARDING' ?></span>
        <h2 class="fw-bold"><?= $isParentInvitation ? 'Welcome to the Kingsway parent community' : 'Welcome to the Kingsway team' ?></h2>
        <p style="color:#dcefe3"><?= $isParentInvitation ? 'Secure your account, sign in to the Parent Portal, and access information for your linked child.' : 'Secure your account, complete your staff information, and then begin from your role dashboard.' ?></p>
        <div class="setup-step active"><span>1</span><div><strong>Create password</strong><small class="d-block" style="color:#cbe2d3">Replace the temporary credential</small></div></div>
        <div class="setup-step"><span>2</span><div><strong><?= $isParentInvitation ? 'Sign in to the portal' : 'Verify your email' ?></strong><small class="d-block" style="color:#cbe2d3"><?= $isParentInvitation ? 'Use your registered parent email' : 'Enter the six-digit code we send you' ?></small></div></div>
        <div class="setup-step"><span>3</span><div><strong><?= $isParentInvitation ? 'View your child' : 'Complete profile' ?></strong><small class="d-block" style="color:#cbe2d3"><?= $isParentInvitation ? 'Access fees, attendance, results and notices' : 'Confirm your personal and contact details' ?></small></div></div>
        <div class="mt-4 pt-3 border-top border-light border-opacity-25 small" style="color:#f4e4aa"><i class="bi bi-shield-lock me-2"></i>This secure invitation is personal and expires after 72 hours.</div>
      </aside>
      <div class="setup-form col-md-7">
        <div class="d-flex align-items-center gap-2 text-success fw-semibold small mb-2"><i class="bi bi-patch-check-fill"></i> Secure account setup</div>
        <h3 class="fw-bold mb-2" style="color:#153d2a">Create your private password</h3>
        <p class="text-muted mb-4">Choose a password only you know. <?= $isParentInvitation ? 'You will use it with your registered email on the Parent Portal.' : 'The temporary password from your invitation will stop working.' ?></p>
        <div id="rdpState" class="alert <?= $resumeStaffInvitationOtp ? 'alert-info' : 'alert-light border' ?>"><?= $resumeStaffInvitationOtp ? 'Your password is already saved. Verify your email to continue to your staff profile.' : 'Enter and confirm a strong password.' ?></div>
        <form id="rdpForm" class="<?= $resumeStaffInvitationOtp ? 'd-none' : '' ?>">
          <input type="hidden" id="rdpToken" value="<?= $token ?>">
          <div class="mb-3">
            <label class="form-label fw-semibold">New password</label>
            <div class="input-group"><input id="rdpPassword" type="password" class="form-control" minlength="10" autocomplete="new-password" required><button class="btn btn-outline-secondary" type="button" id="rdpToggle"><i class="bi bi-eye"></i></button></div>
            <div class="row g-1 mt-2" id="rdpRules"><div class="col-6 password-rule" data-rule="length"><i class="bi bi-circle me-1"></i>10+ characters</div><div class="col-6 password-rule" data-rule="upper"><i class="bi bi-circle me-1"></i>Uppercase letter</div><div class="col-6 password-rule" data-rule="lower"><i class="bi bi-circle me-1"></i>Lowercase letter</div><div class="col-6 password-rule" data-rule="number"><i class="bi bi-circle me-1"></i>Number</div><div class="col-6 password-rule" data-rule="symbol"><i class="bi bi-circle me-1"></i>Symbol</div></div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Confirm password</label>
            <input id="rdpConfirm" type="password" class="form-control" minlength="10" autocomplete="new-password" required>
          </div>
          <button class="btn btn-school w-100" type="submit"><i class="bi bi-arrow-right-circle me-2"></i>Save password and continue</button>
        </form>
        <form id="rdpOtpForm" class="<?= $resumeStaffInvitationOtp ? '' : 'd-none' ?>" autocomplete="one-time-code">
          <p class="text-muted">We sent a six-digit verification code to your invitation email. Enter it below to continue to your staff profile.</p>
          <label class="form-label fw-semibold" for="rdpOtpCode">Email verification code</label>
          <input id="rdpOtpCode" class="form-control mb-3" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required>
          <button id="rdpOtpSubmit" class="btn btn-school w-100" type="submit"><i class="bi bi-shield-check me-2"></i>Verify and continue</button>
          <button id="rdpOtpResend" class="btn btn-link w-100 mt-2" type="button">Send a new code</button>
        </form>
      </div>
    </div>
  </div>
</section>

<script>
window.KINGSWAY_PUBLIC_PAGE = true;
window.KINGSWAY_SETUP_ACCOUNT_TYPE = <?= json_encode($isParentInvitation ? 'parent' : 'staff') ?>;
window.KINGSWAY_SETUP_RESUME_OTP = <?= $resumeStaffInvitationOtp ? 'true' : 'false' ?>;
</script>
<?php asset_script($appBase, 'js/pages/reset_default_password.js'); ?>
<?php include __DIR__ . '/public/layout/footer.php'; ?>
