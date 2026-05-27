<?php
$appBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($appBase === '.') {
    $appBase = '';
}
$pageTitle = 'Reset Password';
$activePage = 'login';
$token = trim($_GET['token'] ?? '');
require_once __DIR__ . '/public/layout/public_data.php';
?>
<?php include __DIR__ . '/public/layout/header.php'; ?>

<style>
  .reset-hero {
    min-height: 100vh;
    position: relative;
    display: flex;
    align-items: center;
    padding: 130px 0 80px;
    overflow: hidden;
    background:
      radial-gradient(circle at 78% 18%, rgba(249,200,14,.3), transparent 31%),
      linear-gradient(135deg, rgba(13,79,42,.98), rgba(18,107,63,.88)),
      url('<?= $appBase ?>/images/school-hero.jpg') center/cover no-repeat;
  }
  .reset-hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background-image:
      linear-gradient(rgba(255,255,255,.055) 1px, transparent 1px),
      linear-gradient(90deg, rgba(255,255,255,.055) 1px, transparent 1px);
    background-size: 44px 44px;
    mask-image: linear-gradient(to bottom, rgba(0,0,0,.82), transparent 80%);
  }
  .reset-hero::after {
    content: '';
    position: absolute;
    width: 380px;
    height: 380px;
    left: -130px;
    top: 24%;
    border: 1px solid rgba(249,200,14,.28);
    border-radius: 50%;
    box-shadow: inset 0 0 0 32px rgba(255,255,255,.035);
  }
  .reset-shell { position: relative; z-index: 2; }
  .reset-kicker {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 7px 16px;
    border: 1px solid rgba(249,200,14,.38);
    border-radius: 999px;
    color: var(--gold);
    background: rgba(249,200,14,.14);
    font-size: .78rem;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
  }
  .reset-title {
    color: #fff;
    font-size: clamp(2.35rem, 5vw, 4.4rem);
    font-weight: 900;
    letter-spacing: -.045em;
    line-height: 1.05;
    margin: 20px 0 18px;
  }
  .reset-title span { color: var(--gold); }
  .reset-copy {
    color: rgba(255,255,255,.78);
    font-size: 1.04rem;
    max-width: 560px;
  }
  .password-rules {
    display: grid;
    gap: 12px;
    margin-top: 30px;
    max-width: 520px;
  }
  .password-rule {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    color: rgba(255,255,255,.82);
    font-size: .92rem;
  }
  .password-rule i {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    flex: 0 0 30px;
    border-radius: 10px;
    background: rgba(249,200,14,.18);
    color: var(--gold);
  }
  .reset-card {
    background: rgba(255,255,255,.96);
    border: 1px solid rgba(255,255,255,.45);
    border-radius: 30px;
    box-shadow: 0 30px 80px rgba(0,0,0,.24);
    overflow: hidden;
  }
  .reset-card-header {
    padding: 28px 30px 18px;
    background:
      linear-gradient(135deg, rgba(13,79,42,.08), rgba(249,200,14,.13)),
      #fff;
    border-bottom: 1px solid rgba(13,79,42,.08);
  }
  .reset-card-icon {
    width: 58px;
    height: 58px;
    border-radius: 18px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, var(--green-dark), var(--green));
    color: var(--gold);
    font-size: 1.55rem;
    box-shadow: 0 12px 28px rgba(13,79,42,.28);
    margin-bottom: 18px;
  }
  .reset-card h2 {
    margin: 0 0 8px;
    font-size: 1.55rem;
    color: var(--green-dark);
  }
  .reset-card p { margin: 0; color: var(--gray-600); font-size: .94rem; }
  .reset-form { padding: 28px 30px 30px; }
  .reset-form .form-label {
    color: var(--gray-700);
    font-size: .8rem;
    font-weight: 700;
    letter-spacing: .04em;
    text-transform: uppercase;
  }
  .reset-input-wrap { position: relative; }
  .reset-input-wrap > i {
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--green);
    z-index: 3;
  }
  .reset-input-wrap .form-control {
    min-height: 54px;
    border-radius: 16px;
    border: 1px solid var(--gray-200);
    padding-left: 46px;
    padding-right: 52px;
    font-weight: 500;
    box-shadow: none;
  }
  .reset-input-wrap .form-control:focus {
    border-color: var(--green);
    box-shadow: 0 0 0 .2rem rgba(25,135,84,.12);
  }
  .password-toggle {
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
    border: 0;
    background: transparent;
    color: var(--gray-600);
    width: 40px;
    height: 40px;
    border-radius: 12px;
    z-index: 4;
  }
  .password-toggle:hover { background: var(--gray-100); color: var(--green-dark); }
  .strength-meter {
    height: 8px;
    border-radius: 999px;
    overflow: hidden;
    background: var(--gray-100);
    margin-top: 12px;
  }
  .strength-meter span {
    display: block;
    width: 0;
    height: 100%;
    background: #dc3545;
    transition: width .25s ease, background .25s ease;
  }
  .strength-copy { color: var(--gray-600); font-size: .8rem; margin-top: 8px; }
  .reset-state {
    display: none;
    border-radius: 16px;
    padding: 14px 16px;
    margin-top: 18px;
    font-size: .9rem;
  }
  .reset-state.show { display: flex; gap: 10px; align-items: flex-start; }
  .reset-state.success { background: rgba(25,135,84,.1); color: #11653d; border: 1px solid rgba(25,135,84,.22); }
  .reset-state.error { background: rgba(220,53,69,.1); color: #9f2230; border: 1px solid rgba(220,53,69,.22); }
  .reset-secondary-link {
    color: var(--green-dark);
    font-weight: 700;
  }
  .reset-secondary-link:hover { color: var(--green); }
  .token-panel {
    display: none;
    margin-bottom: 18px;
  }
  .token-panel.show { display: block; }
  @media (max-width: 991px) {
    .reset-hero { padding-top: 112px; }
    .reset-card { margin-top: 30px; }
  }
</style>

<section class="reset-hero">
  <div class="container reset-shell">
    <div class="row align-items-center g-5">
      <div class="col-lg-6">
        <div class="reset-kicker"><i class="bi bi-fingerprint"></i> Verified Reset Link</div>
        <h1 class="reset-title">Create a safer <span>new password</span>.</h1>
        <p class="reset-copy">
          Choose a fresh password for your Kingsway ERP account. The reset link is checked before the password form is enabled.
        </p>
        <div class="password-rules">
          <div class="password-rule"><i class="bi bi-shield-check"></i><span>Use a password you have not used before on this account.</span></div>
          <div class="password-rule"><i class="bi bi-key"></i><span>Mix letters, numbers, and symbols so the password is harder to guess.</span></div>
          <div class="password-rule"><i class="bi bi-person-lock"></i><span>After reset, sign in again from the public login modal.</span></div>
        </div>
      </div>

      <div class="col-lg-5 offset-lg-1">
        <div class="reset-card reveal">
          <div class="reset-card-header">
            <div class="reset-card-icon"><i class="bi bi-lock-fill"></i></div>
            <h2>Set new password</h2>
            <p id="tokenStatusText">Checking your reset link before enabling this form.</p>
          </div>
          <form class="reset-form" id="resetPasswordForm" novalidate>
            <input type="hidden" id="resetToken" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">

            <div id="missingTokenPanel" class="token-panel alert alert-warning mb-0">
              <i class="bi bi-exclamation-triangle me-1"></i>
              This reset link is missing a token. Request a new password reset link to continue.
            </div>

            <div class="mb-3">
              <label for="newPassword" class="form-label">New password</label>
              <div class="reset-input-wrap">
                <i class="bi bi-key-fill"></i>
                <input type="password" class="form-control" id="newPassword" name="new_password" placeholder="Enter new password" autocomplete="new-password" required disabled>
                <button class="password-toggle" type="button" data-target="newPassword" aria-label="Show new password"><i class="bi bi-eye"></i></button>
              </div>
              <div class="strength-meter" aria-hidden="true"><span id="strengthBar"></span></div>
              <div class="strength-copy" id="strengthCopy">Password strength will appear here.</div>
            </div>

            <div class="mb-3">
              <label for="confirmPassword" class="form-label">Confirm password</label>
              <div class="reset-input-wrap">
                <i class="bi bi-shield-lock-fill"></i>
                <input type="password" class="form-control" id="confirmPassword" name="confirm_password" placeholder="Repeat new password" autocomplete="new-password" required disabled>
                <button class="password-toggle" type="button" data-target="confirmPassword" aria-label="Show confirmation password"><i class="bi bi-eye"></i></button>
              </div>
            </div>

            <button type="submit" class="btn-kw-primary w-100 justify-content-center py-3" id="resetSubmitBtn" disabled>
              <span class="btn-label"><i class="bi bi-check2-circle me-2"></i>Update password</span>
              <span class="btn-loading d-none"><span class="spinner-border spinner-border-sm me-2"></span>Updating securely…</span>
            </button>
            <div id="resetMessage" class="reset-state" role="status" aria-live="polite"></div>
            <div class="text-center mt-4">
              <a href="<?= $appBase ?>/forgot_password.php" class="reset-secondary-link"><i class="bi bi-arrow-clockwise me-1"></i>Request a new link</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</section>

<script>
  document.addEventListener('DOMContentLoaded', async function () {
    const form = document.getElementById('resetPasswordForm');
    const token = document.getElementById('resetToken').value.trim();
    const newPassword = document.getElementById('newPassword');
    const confirmPassword = document.getElementById('confirmPassword');
    const button = document.getElementById('resetSubmitBtn');
    const label = button.querySelector('.btn-label');
    const loading = button.querySelector('.btn-loading');
    const message = document.getElementById('resetMessage');
    const tokenStatusText = document.getElementById('tokenStatusText');
    const missingTokenPanel = document.getElementById('missingTokenPanel');
    const strengthBar = document.getElementById('strengthBar');
    const strengthCopy = document.getElementById('strengthCopy');

    function setBusy(isBusy) {
      button.disabled = isBusy;
      label.classList.toggle('d-none', isBusy);
      loading.classList.toggle('d-none', !isBusy);
    }

    function showMessage(type, text) {
      message.className = 'reset-state show ' + type;
      message.innerHTML = '<i class="bi ' + (type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill') + '"></i><span>' + text + '</span>';
    }

    function enableForm() {
      newPassword.disabled = false;
      confirmPassword.disabled = false;
      button.disabled = false;
      tokenStatusText.textContent = 'Your reset link is valid. Enter and confirm your new password.';
      newPassword.focus();
    }

    function scorePassword(value) {
      let score = 0;
      if (value.length >= 8) score++;
      if (value.length >= 12) score++;
      if (/[A-Z]/.test(value) && /[a-z]/.test(value)) score++;
      if (/\d/.test(value)) score++;
      if (/[^A-Za-z0-9]/.test(value)) score++;
      return Math.min(score, 5);
    }

    function updateStrength() {
      const score = scorePassword(newPassword.value);
      const widths = ['0%', '22%', '42%', '62%', '82%', '100%'];
      const colors = ['#dc3545', '#dc3545', '#fd7e14', '#f9c80e', '#198754', '#0d4f2a'];
      const labels = [
        'Password strength will appear here.',
        'Very weak — add more characters.',
        'Weak — add numbers or symbols.',
        'Fair — make it longer if possible.',
        'Strong password.',
        'Excellent password.'
      ];
      strengthBar.style.width = widths[score];
      strengthBar.style.background = colors[score];
      strengthCopy.textContent = labels[score];
    }

    document.querySelectorAll('.password-toggle').forEach(function (toggle) {
      toggle.addEventListener('click', function () {
        const field = document.getElementById(toggle.dataset.target);
        const icon = toggle.querySelector('i');
        const isPassword = field.type === 'password';
        field.type = isPassword ? 'text' : 'password';
        icon.className = 'bi ' + (isPassword ? 'bi-eye-slash' : 'bi-eye');
      });
    });

    newPassword.addEventListener('input', updateStrength);

    if (!token) {
      tokenStatusText.textContent = 'This reset link cannot be verified because the token is missing.';
      missingTokenPanel.classList.add('show');
      return;
    }

    try {
      await window.API.resetPassword.verify(token);
      enableForm();
    } catch (error) {
      tokenStatusText.textContent = 'This reset link is invalid or expired.';
      showMessage('error', error.message || 'Invalid or expired reset link. Please request a new link.');
      return;
    }

    form.addEventListener('submit', async function (event) {
      event.preventDefault();
      const password = newPassword.value;
      const confirm = confirmPassword.value;

      if (!password || !confirm) {
        showMessage('error', 'Enter and confirm your new password.');
        return;
      }

      if (password !== confirm) {
        showMessage('error', 'The password confirmation does not match.');
        confirmPassword.focus();
        return;
      }

      setBusy(true);
      message.className = 'reset-state';
      message.textContent = '';

      try {
        const response = await window.API.resetPassword.complete(token, password);
        showMessage('success', (response && response.message) || 'Password has been reset successfully. You can now sign in.');
        form.reset();
        updateStrength();
        newPassword.disabled = true;
        confirmPassword.disabled = true;
        button.disabled = true;
        setTimeout(function () {
          window.location.href = (window.APP_BASE || '') + '/index.php';
        }, 2500);
      } catch (error) {
        showMessage('error', error.message || 'Password reset failed. Please try again.');
      } finally {
        if (!newPassword.disabled) {
          setBusy(false);
        }
      }
    });
  });
</script>

<?php include __DIR__ . '/public/layout/footer.php'; ?>
