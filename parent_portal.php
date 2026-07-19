<?php
// Parent Portal — standalone entry point (not inside admin app shell)
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$appBase = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
if ($appBase === '.') $appBase = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Kingsway Prep School — Parent Portal</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link rel="stylesheet" href="<?= $appBase ?>/css/school-theme.css">
  <style>
    /* ===== Kingsway brand palette ===== */
    :root {
      --kw-green:        #0d4f2a;
      --kw-green-light:  #198754;
      --kw-gold:         #f9c80e;
      --kw-cream:        #f5f5dc;
      --kw-green-grad:   linear-gradient(135deg, #0d4f2a 0%, #198754 100%);
    }
    /* Recolor Bootstrap primary (used by JS-injected badges/cards) to Kingsway green */
    .btn-primary, .bg-primary, .alert-primary { background-color: var(--kw-green-light) !important; border-color: var(--kw-green-light) !important; }
    .text-primary { color: var(--kw-green) !important; }
    .btn-primary:hover { background-color: var(--kw-green) !important; border-color: var(--kw-green) !important; }

    body {
      background: linear-gradient(135deg, #0d4f2a 0%, #198754 100%);
      min-height: 100vh;
      font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
    }
    .portal-card { max-width: 480px; margin: 0 auto; }
    .portal-logo { width: 72px; height: 72px; object-fit: contain; }
    .child-card { transition: box-shadow .2s, transform .2s; cursor: pointer; border-top: 4px solid var(--kw-green-light) !important; }
    .child-card:hover { box-shadow: 0 8px 24px rgba(13,79,42,.25); transform: translateY(-2px); }
    #portal-loading { display: none; }
    .view { display: none; }
    .view.active { display: block; }

    /* Branded auth header */
    .kw-auth-header {
      background: var(--kw-green-grad);
      border-radius: 1rem 1rem 0 0;
      color: #fff;
      text-align: center;
      padding: 2rem 1rem 1.5rem;
      position: relative;
      overflow: hidden;
    }
    .kw-auth-header::after {
      content: ""; position: absolute; left: 0; right: 0; bottom: 0; height: 4px;
      background: linear-gradient(90deg, var(--kw-gold), #ffe27a);
    }
    .kw-auth-name { color: var(--kw-gold); font-weight: 800; letter-spacing: .5px; }
    .kw-auth-motto { color: rgba(255,255,255,.85); font-style: italic; font-size: .85rem; }
    .kw-auth-logo { width: 64px; height: 64px; object-fit: contain; filter: drop-shadow(0 2px 6px rgba(0,0,0,.3)); }

    /* Branded top bar for dashboard/student views */
    .kw-topbar {
      background: var(--kw-green-grad);
      border: 1px solid rgba(255,255,255,.1);
      border-radius: 1rem;
      padding: 1.25rem 1.5rem;
      box-shadow: 0 6px 20px rgba(13,79,42,.25);
    }

    /* Branded gradient cards used by balance summary */
    .kw-balance-card { border-top: 4px solid var(--kw-gold) !important; }

    /* Statement/popup print header */
    .kw-print-head { font-family: inherit; }

    /* Tabs active color */
    .nav-tabs .nav-link.active { color: var(--kw-green) !important; border-color: #dee2e6 #dee2e6 #fff; font-weight: 600; }
  </style>
  <script>window.APP_BASE = <?php echo json_encode($appBase); ?>;</script>
</head>
<body class="py-4">

<!-- AUTH VIEW -->
<div id="view-auth" class="view active container">
  <div class="portal-card">
    <div class="card shadow-lg border-0 rounded-4">
      <div class="card-body p-0">
        <div class="kw-auth-header">
          <img src="<?= $appBase ?>/uploads/school_assets/official_school_logo.png" alt="Kingsway Logo" class="kw-auth-logo mb-2" onerror="this.onerror=null;this.src='<?= $appBase ?>/images/official_school_logo.png';">
          <div class="kw-auth-name h5 mb-0">KINGSWAY PREPARATORY SCHOOL</div>
          <div class="kw-auth-motto">"In God We Soar"</div>
          <div class="text-white-50 small mt-2">Parent Portal</div>
        </div>
        <div class="p-4 p-md-5">

        <ul class="nav nav-pills nav-fill mb-4" id="loginTabs">
          <li class="nav-item"><button class="nav-link active" data-tab="email">Email Login</button></li>
          <li class="nav-item"><button class="nav-link" data-tab="otp">Phone OTP</button></li>
        </ul>

        <!-- Email Login Form -->
        <div id="tab-email">
          <div class="mb-3">
            <label class="form-label fw-semibold">Email Address</label>
            <input type="email" id="loginEmail" class="form-control" placeholder="parent@email.com">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Password</label>
            <div class="input-group">
              <input type="password" id="loginPassword" class="form-control" placeholder="••••••••">
              <button class="btn btn-outline-secondary" type="button" id="togglePwd"><i class="bi bi-eye"></i></button>
            </div>
          </div>
          <div id="loginError" class="alert alert-danger d-none"></div>
          <button class="btn btn-primary w-100 py-2 fw-semibold" id="btnEmailLogin">
            <span class="spinner-border spinner-border-sm me-2 d-none" id="loginSpinner"></span>
            Sign In
          </button>
        </div>

        <!-- Phone OTP Form -->
        <div id="tab-otp" style="display:none">
          <div id="otp-step-1">
            <div class="mb-3">
              <label class="form-label fw-semibold">Phone Number</label>
              <input type="tel" id="otpPhone" class="form-control" placeholder="07XXXXXXXX">
            </div>
            <div id="otpRequestError" class="alert alert-danger d-none"></div>
            <button class="btn btn-primary w-100 py-2 fw-semibold" id="btnRequestOtp">Send OTP</button>
          </div>
          <div id="otp-step-2" style="display:none">
            <p class="text-muted small">Enter the 6-digit code sent to your phone.</p>
            <div class="mb-3">
              <label class="form-label fw-semibold">OTP Code</label>
              <input type="text" id="otpCode" class="form-control text-center fw-bold fs-4" maxlength="6" placeholder="------">
            </div>
            <div id="otpVerifyError" class="alert alert-danger d-none"></div>
            <button class="btn btn-success w-100 py-2 fw-semibold" id="btnVerifyOtp">Verify &amp; Sign In</button>
            <button class="btn btn-link w-100 mt-2 text-muted" id="btnResendOtp">Resend OTP</button>
          </div>
        </div>

        <div class="text-center mt-3">
          <small class="text-muted">Having trouble? Contact the school office.</small><br>
          <small class="text-muted">
            <i class="bi bi-telephone me-1"></i>+254 720 113 030 &nbsp;·&nbsp;
            <i class="bi bi-envelope me-1"></i>info@kingswaypreparatoryschool.sc.ke
          </small>
        </div>
        </div><!-- /p-4 p-md-5 -->
      </div>
    </div>
  </div>
</div>

<!-- DASHBOARD VIEW -->
<div id="view-dashboard" class="view container">
  <div class="kw-topbar d-flex justify-content-between align-items-center mb-4">
    <div>
      <h4 class="text-white fw-bold mb-0">Welcome, <span id="parentName"></span></h4>
      <small class="text-white-50">Kingsway Preparatory School · Parent Portal</small>
    </div>
    <button class="btn btn-outline-light btn-sm" id="btnLogout"><i class="bi bi-box-arrow-right me-1"></i>Logout</button>
  </div>
  <div id="portal-loading" class="text-center py-5">
    <div class="spinner-border text-light"></div>
    <p class="text-white mt-2">Loading...</p>
  </div>
  <div class="row" id="childrenCards"></div>
</div>

<!-- STUDENT DETAIL VIEW -->
<div id="view-student" class="view container">
  <div class="kw-topbar d-flex align-items-center mb-4">
    <button class="btn btn-outline-light btn-sm me-3" id="btnBackToDashboard"><i class="bi bi-arrow-left me-1"></i>Back</button>
    <div>
      <h5 class="text-white fw-bold mb-0" id="studentDetailName"></h5>
      <small class="text-white-50" id="studentDetailClass"></small>
    </div>
  </div>
  <div class="row g-3 mb-4" id="balanceSummaryCards"></div>
  <div class="card border-0 shadow-sm rounded-4">
    <div class="card-header bg-white border-0 pb-0">
      <ul class="nav nav-tabs" id="studentDetailTabs">
        <li class="nav-item"><button class="nav-link active" data-tab="fees">Fee History</button></li>
        <li class="nav-item"><button class="nav-link" data-tab="payments">Payments</button></li>
        <li class="nav-item"><button class="nav-link" data-tab="statement">Statement</button></li>
      </ul>
    </div>
    <div class="card-body" id="studentDetailContent">
      <div class="text-center py-4"><div class="spinner-border text-primary"></div></div>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<script src="<?= $appBase ?>/js/pages/parent_portal.js"></script>
</body>
</html>
