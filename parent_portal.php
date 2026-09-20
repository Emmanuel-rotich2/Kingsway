<?php
// Parent Portal — standalone login entry. After authentication the parent is
// sent to the multi-page cpanel (parents/dashboard.php). This page only
// authenticates; page-level JS redirects already-signed-in parents to the
// dashboard.
declare(strict_types=1);
$familyStaffMode = $familyStaffMode ?? false;
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$appBase = $appBaseOverride ?? rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
if ($appBase === '.') $appBase = '';

// Parent/guardian portal front controller. Sections are reached as
// parent_portal.php?route=<key>; direct *.php access is denied by .htaccess.
$route = trim((string)($_GET['route'] ?? ''));
if ($route !== '' && $route !== 'login') {
    $facadeRoutes = require __DIR__ . '/public/layout/facade_routes.php';
    $routeTarget  = $facadeRoutes['parents'][$route] ?? null;
    unset($facadeRoutes);
    if ($routeTarget !== null) {
        $appBaseOverride = $appBase;
        require __DIR__ . '/' . $routeTarget;
        exit;
    }
    http_response_code(404);
    exit('Page not found.');
}
unset($route);

require_once __DIR__ . '/public/layout/public_data.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <?php asset_script($appBase, 'js/core/console_logger.js'); ?>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Parent Sign In — Kingsway Parent Portal</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= $appBase ?>/css/school-theme.css?v=<?= asset_version('css/school-theme.css') ?>">
  <link rel="stylesheet" href="<?= $appBase ?>/css/app-common.css?v=<?= asset_version('css/app-common.css') ?>">
  <link rel="stylesheet" href="<?= $appBase ?>/css/parent-cpanel.css?v=<?= asset_version('css/parent-cpanel.css') ?>">
  <script>
    window.APP_BASE = <?= json_encode($appBase) ?>;
    window.KINGSWAY_PUBLIC_PAGE = true;
    window.FAMILY_STAFF_MODE = <?= $familyStaffMode ? 'true' : 'false' ?>;
  </script>
</head>
<body class="pp-auth-page">

<div class="pp-login-card">
  <div class="card border-0 rounded-4 shadow-lg">
    <div class="card-body p-0">
      <div class="kw-auth-header">
        <img src="<?= $appBase ?>/uploads/school_assets/official_school_logo.png" alt="Kingsway Logo" class="kw-auth-logo mb-2" onerror="this.onerror=null;this.src='<?= $appBase ?>/images/official_school_logo.png';">
        <div class="h5 mb-0"><a class="kw-auth-name" href="<?= htmlspecialchars($appBase) ?>/index.php">KINGSWAY PREPARATORY SCHOOL</a></div>
        <div class="kw-auth-motto">"In God We Soar"</div>
        <div class="text-white-50 small mt-2">Parent Portal</div>
      </div>
      <div class="p-4 p-md-5">

        <div class="text-center mb-4"><span class="badge rounded-pill text-bg-success"><i class="bi bi-shield-lock me-1"></i>Password + email verification</span></div>

        <!-- Email Login Form -->
        <div id="tab-email">
          <div class="mb-3">
            <label class="form-label fw-semibold">Email or Phone Number</label>
            <input type="text" id="loginEmail" class="form-control" placeholder="Email or +2547XXXXXXXX" autocomplete="username" autocapitalize="none">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Password</label>
            <div class="input-group">
              <input type="password" id="loginPassword" class="form-control" placeholder="••••••••" autocomplete="current-password">
              <button class="btn btn-outline-secondary" type="button" id="togglePwd" aria-label="Show password"><i class="bi bi-eye"></i></button>
            </div>
          </div>
          <div class="text-end mb-3">
            <a class="small" href="<?= htmlspecialchars($appBase) ?>/index.php?route=r8f1bc67a3eb2">Forgot password?</a>
          </div>
          <div id="loginError" class="alert alert-danger d-none"></div>
          <button class="btn btn-primary w-100 py-2 fw-semibold" type="button" id="btnEmailLogin">
            <span class="spinner-border spinner-border-sm me-2 d-none" id="loginSpinner"></span>
            Sign In
          </button>
        </div>

        <!-- Email OTP Form -->
        <div id="tab-otp" style="display:none">
          <div id="otp-step-1" style="display:none">
            <div class="mb-3">
              <label class="form-label fw-semibold">Email Address</label>
              <input type="email" id="otpEmail" class="form-control" autocomplete="email" placeholder="parent@example.com">
            </div>
            <div id="otpRequestError" class="alert alert-danger d-none"></div>
            <button class="btn btn-primary w-100 py-2 fw-semibold" type="button" id="btnRequestOtp">Send OTP</button>
          </div>
          <div id="otp-step-2" style="display:none">
            <p class="text-muted small">Enter the 6-digit code sent to your email.</p>
            <div class="mb-3">
              <label class="form-label fw-semibold">OTP Code</label>
              <input type="text" id="otpCode" class="form-control text-center fw-bold fs-4" maxlength="6" placeholder="------" inputmode="numeric">
            </div>
            <div id="otpVerifyError" class="alert alert-danger d-none"></div>
            <button class="btn btn-success w-100 py-2 fw-semibold" type="button" id="btnVerifyOtp">Verify &amp; Sign In</button>
            <button class="btn btn-link w-100 mt-2 text-muted" type="button" id="btnResendOtp">Restart secure sign-in</button>
          </div>
        </div>

        <div class="text-center mt-4 pt-3 border-top">
          <small class="text-muted">Having trouble? Contact the school office.</small><br>
          <small class="text-muted">
            <i class="bi bi-telephone me-1"></i>+254 720 113 030 &nbsp;·&nbsp;
            <i class="bi bi-envelope me-1"></i>info@kingswaypreparatoryschool.sc.ke
          </small>
          <div class="mt-3">
            <span class="text-muted">Staff? </span><a class="fw-semibold" href="<?= htmlspecialchars($appBase) ?>/index.php?route=r6d394ab20b0b"><i class="bi bi-box-arrow-in-right me-1"></i>Sign in to the school workspace</a>
          </div>
          <div class="small mt-2 text-muted">
            Parent Portal maintained by
            <a href="https://www.angisoft.co.ke" target="_blank" rel="noopener">AngiSoft Technologies</a>
          </div>
        </div>
      </div><!-- /p-4 p-md-5 -->
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<?php asset_script($appBase, 'js/api.js'); ?>
<?php asset_script($appBase, 'js/core/frontend_logger.js'); ?>
<?php asset_script($appBase, 'js/core/parent_common.js'); ?>
<?php asset_script($appBase, 'js/pages/parents/login.js'); ?>
<script>window.AppLogger?.init?.();</script>
</body>
</html>