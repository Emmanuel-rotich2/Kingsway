<?php
require_once __DIR__ . '/vendor/autoload.php';
$appBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($appBase === '.') $appBase = '';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
?>
<!doctype html>
<html lang="en">
<head>
    <?php asset_script($appBase, 'js/core/console_logger.js'); ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="#0b5d3b">
    <title>Secure sign in · Kingsway Preparatory School</title>
    <link rel="icon" href="<?= htmlspecialchars($appBase) ?>/images/favicon/favicon-96x96.png">
    <link rel="stylesheet" href="<?= htmlspecialchars($appBase) ?>/public/vendor/bootstrap/css/bootstrap.min.css?v=<?= asset_version('public/vendor/bootstrap/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($appBase) ?>/css/school-theme.css?v=<?= asset_version('css/school-theme.css') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars($appBase) ?>/css/pages/sign-in.css?v=<?= asset_version('css/pages/sign-in.css') ?>">
    <script>window.APP_BASE=<?= json_encode($appBase) ?>;</script>
</head>
<body class="auth-page">
<main class="auth-layout">
    <section class="auth-story" aria-label="Kingsway Preparatory School">
        <a class="auth-brand" href="<?= htmlspecialchars($appBase) ?>/index.php"><img src="<?= htmlspecialchars($appBase) ?>/uploads/school_assets/official_school_logo.png" alt=""><span><strong>Kingsway Preparatory School</strong><small>In God We Soar</small></span></a>
        <p class="auth-story-foot">Secure access · Kingsway Preparatory School</p>
    </section>
    <section class="auth-workspace">
        <a class="auth-back" href="<?= htmlspecialchars($appBase) ?>/index.php"><i class="bi bi-arrow-left"></i> Back to school website</a>
        <div class="auth-card">
            <div id="signInStep">
                <span class="auth-step-label">Secure school workspace</span><h2>Welcome back</h2><p class="auth-intro">Sign in with your Kingsway username or email.</p>
                <div class="alert alert-danger d-none" id="authError" role="alert"></div>
                <form id="authLoginForm">
                    <label class="form-label" for="authIdentifier">Username or email</label><div class="input-group mb-3"><span class="input-group-text"><i class="bi bi-person"></i></span><input class="form-control" id="authIdentifier" placeholder="Enter username or email" required autocomplete="username"></div>
                    <div class="d-flex justify-content-between"><label class="form-label" for="authPassword">Password</label><a href="<?= htmlspecialchars($appBase) ?>/forgot_password.php">Forgot password?</a></div><div class="input-group mb-3"><span class="input-group-text"><i class="bi bi-key"></i></span><input type="password" class="form-control" id="authPassword" placeholder="Enter your password" required autocomplete="current-password"><button class="btn auth-password-toggle" type="button" id="authTogglePassword" aria-label="Show password"><i class="bi bi-eye"></i></button></div>
                    <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="authRemember"><label class="form-check-label" for="authRemember">Keep me signed in on this device</label></div>
                    <button class="btn btn-warning w-100 auth-submit" id="authSubmit" type="submit"><span>Sign in</span><span class="spinner-border spinner-border-sm d-none"></span></button>
                </form>
                <div class="auth-divider"><span>or</span></div><button class="btn btn-outline-dark w-100" id="authPasswordless" type="button"><i class="bi bi-fingerprint me-2"></i>Sign in with a passkey</button>
                <p class="text-center mt-3 mb-0"><a href="<?= htmlspecialchars($appBase) ?>/parent_portal.php"><i class="bi bi-people me-1"></i>Login as parent or guardian</a></p>
            </div>
            <div class="d-none" id="verificationStep">
                <span class="auth-step-label">Identity verification</span><h2 id="verificationTitle">Two-factor authentication 🔑</h2><p class="auth-intro" id="verificationDescription">Use your device to get the code.</p>
                <div class="alert alert-danger d-none" id="verifyError" role="alert"></div><div class="alert alert-success d-none" id="verifySuccess" role="status"></div>
                <form id="verificationForm"><label class="form-label" id="verificationCodeLabel">Enter the six-digit code</label><div class="auth-otp-grid" id="authOtpGrid" role="group" aria-labelledby="verificationCodeLabel"><input class="auth-otp-cell" inputmode="numeric" autocomplete="one-time-code" maxlength="1" aria-label="Digit 1"><input class="auth-otp-cell" inputmode="numeric" maxlength="1" aria-label="Digit 2"><input class="auth-otp-cell" inputmode="numeric" maxlength="1" aria-label="Digit 3"><input class="auth-otp-cell" inputmode="numeric" maxlength="1" aria-label="Digit 4"><input class="auth-otp-cell" inputmode="numeric" maxlength="1" aria-label="Digit 5"><input class="auth-otp-cell" inputmode="numeric" maxlength="1" aria-label="Digit 6"></div><input class="form-control form-control-lg auth-code d-none" id="verificationCode" autocomplete="one-time-code" maxlength="32"><p class="auth-auto-status" id="authAutoStatus"><i class="bi bi-lightning-charge-fill"></i> Verification starts automatically when all six digits are entered.</p><button class="btn btn-warning w-100 mt-3 auth-submit d-none" id="verifySubmit" type="submit">Verify recovery code</button></form>
                <button class="btn btn-success w-100 d-none" id="authVerifyPasskey" type="button"><i class="bi bi-fingerprint me-2"></i>Verify with this device</button>
                <div class="d-flex justify-content-between mt-4"><button class="btn btn-link p-0" id="authBack" type="button"><i class="bi bi-arrow-left"></i> Back to login</button><button class="btn btn-link p-0" id="authResend" type="button">Send a new code</button><button class="btn btn-link p-0" id="authUseRecovery" type="button">I don't have my 2FA</button></div>
            </div>
        </div>
        <p class="auth-help"><i class="bi bi-shield-lock"></i> Kingsway will never ask you to share a verification code.</p>
        <p class="auth-soft">Maintained by <a href="https://www.angisoft.co.ke" target="_blank" rel="noopener">AngiSoft Technologies</a></p>
    </section>
</main>
<script src="<?= htmlspecialchars($appBase) ?>/public/vendor/bootstrap/js/bootstrap.bundle.min.js?v=<?= asset_version('public/vendor/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= htmlspecialchars($appBase) ?>/js/api.js?v=<?= asset_version('js/api.js') ?>"></script>
<script src="<?= htmlspecialchars($appBase) ?>/js/pages/sign_in.js?v=<?= asset_version('js/pages/sign_in.js') ?>"></script>
</body></html>
