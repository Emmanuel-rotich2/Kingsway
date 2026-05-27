<?php
$appBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($appBase === '.') {
    $appBase = '';
}
$pageTitle = 'Forgot Password';
$activePage = 'login';
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
      radial-gradient(circle at 18% 18%, rgba(249,200,14,.32), transparent 30%),
      linear-gradient(135deg, rgba(13,79,42,.97), rgba(25,135,84,.86)),
      url('<?= $appBase ?>/images/school-hero.jpg') center/cover no-repeat;
  }
  .reset-hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background-image:
      linear-gradient(rgba(255,255,255,.06) 1px, transparent 1px),
      linear-gradient(90deg, rgba(255,255,255,.06) 1px, transparent 1px);
    background-size: 44px 44px;
    mask-image: linear-gradient(to bottom, rgba(0,0,0,.8), transparent 78%);
  }
  .reset-hero::after {
    content: '';
    position: absolute;
    width: 420px;
    height: 420px;
    right: -160px;
    top: 18%;
    border: 1px solid rgba(249,200,14,.3);
    border-radius: 50%;
    box-shadow: inset 0 0 0 34px rgba(255,255,255,.035);
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
  .reset-trust-list {
    display: grid;
    gap: 12px;
    margin-top: 30px;
    max-width: 520px;
  }
  .reset-trust-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    color: rgba(255,255,255,.82);
    font-size: .92rem;
  }
  .reset-trust-item i {
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
  .reset-input-wrap i {
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
    font-weight: 500;
    box-shadow: none;
  }
  .reset-input-wrap .form-control:focus {
    border-color: var(--green);
    box-shadow: 0 0 0 .2rem rgba(25,135,84,.12);
  }
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
  @media (max-width: 991px) {
    .reset-hero { padding-top: 112px; }
    .reset-card { margin-top: 30px; }
  }
</style>

<section class="reset-hero">
  <div class="container reset-shell">
    <div class="row align-items-center g-5">
      <div class="col-lg-6">
        <div class="reset-kicker"><i class="bi bi-shield-lock-fill"></i> Secure Account Recovery</div>
        <h1 class="reset-title">Get back into your <span>Kingsway portal</span>.</h1>
        <p class="reset-copy">
          Enter your staff username or email address. If it matches an account, we will send a private reset link with a short expiry window.
        </p>
        <div class="reset-trust-list">
          <div class="reset-trust-item"><i class="bi bi-eye-slash"></i><span>We show the same confirmation for every request to protect staff account privacy.</span></div>
          <div class="reset-trust-item"><i class="bi bi-clock-history"></i><span>Reset links are time-limited and become invalid after use.</span></div>
          <div class="reset-trust-item"><i class="bi bi-envelope-check"></i><span>Use the inbox connected to your Kingsway ERP account.</span></div>
        </div>
      </div>

      <div class="col-lg-5 offset-lg-1">
        <div class="reset-card reveal">
          <div class="reset-card-header">
            <div class="reset-card-icon"><i class="bi bi-key-fill"></i></div>
            <h2>Request reset link</h2>
            <p>We will email password reset instructions if the account is registered.</p>
          </div>
          <form class="reset-form" id="forgotPasswordForm" novalidate>
            <div class="mb-3">
              <label for="resetIdentifier" class="form-label">Email or username</label>
              <div class="reset-input-wrap">
                <i class="bi bi-person-badge"></i>
                <input type="text" class="form-control" id="resetIdentifier" name="email" placeholder="name@school.com or username" autocomplete="username" required>
              </div>
            </div>
            <button type="submit" class="btn-kw-primary w-100 justify-content-center py-3" id="forgotSubmitBtn">
              <span class="btn-label"><i class="bi bi-send me-2"></i>Send reset instructions</span>
              <span class="btn-loading d-none"><span class="spinner-border spinner-border-sm me-2"></span>Sending securely…</span>
            </button>
            <div id="forgotMessage" class="reset-state" role="status" aria-live="polite"></div>
            <div class="text-center mt-4">
              <a href="<?= $appBase ?>/index.php" class="reset-secondary-link"><i class="bi bi-arrow-left me-1"></i>Back to login</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</section>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('forgotPasswordForm');
    const input = document.getElementById('resetIdentifier');
    const message = document.getElementById('forgotMessage');
    const button = document.getElementById('forgotSubmitBtn');
    const label = button.querySelector('.btn-label');
    const loading = button.querySelector('.btn-loading');

    function setBusy(isBusy) {
      button.disabled = isBusy;
      label.classList.toggle('d-none', isBusy);
      loading.classList.toggle('d-none', !isBusy);
    }

    function showMessage(type, text) {
      message.className = 'reset-state show ' + type;
      message.replaceChildren();

      const icon = document.createElement('i');
      icon.className = 'bi ' + (type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill');

      const body = document.createElement('span');
      body.textContent = text;

      message.append(icon, body);
    }

    form.addEventListener('submit', async function (event) {
      event.preventDefault();
      const identifier = input.value.trim();

      if (!identifier) {
        showMessage('error', 'Enter your email address or username to continue.');
        input.focus();
        return;
      }

      setBusy(true);
      message.className = 'reset-state';
      message.textContent = '';

      try {
        const response = await window.API.resetPassword.request(identifier);
        showMessage('success', (response && response.message) || 'If an account exists for that email, password reset instructions have been sent.');
        form.reset();
      } catch (error) {
        showMessage('error', error.message || 'We could not start the reset process. Please try again.');
      } finally {
        setBusy(false);
      }
    });
  });
</script>

<?php include __DIR__ . '/public/layout/footer.php'; ?>
