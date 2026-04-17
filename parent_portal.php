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
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link rel="stylesheet" href="<?= $appBase ?>/css/school-theme.css">
  <style>
    body { background: linear-gradient(135deg, #1a3a5c 0%, #2d6a9f 100%); min-height: 100vh; }
    .portal-card { max-width: 480px; margin: 0 auto; }
    .portal-logo { width: 72px; height: 72px; object-fit: contain; }
    .child-card { transition: box-shadow .2s; cursor: pointer; }
    .child-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,.15); }
    #portal-loading { display: none; }
    .view { display: none; }
    .view.active { display: block; }
  </style>
  <script>window.APP_BASE = <?php echo json_encode($appBase); ?>;</script>
</head>
<body class="py-4">

<!-- AUTH VIEW -->
<div id="view-auth" class="view active container">
  <div class="portal-card">
    <div class="card shadow-lg border-0 rounded-4">
      <div class="card-body p-4 p-md-5">
        <div class="text-center mb-4">
          <img src="<?= $appBase ?>/images/logo.png" alt="Kingsway Logo" class="portal-logo mb-3" onerror="this.style.display='none'">
          <h4 class="fw-bold text-dark">Parent Portal</h4>
          <p class="text-muted small">Kingsway Preparatory School</p>
        </div>

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
          <small class="text-muted">Having trouble? Contact the school office.</small>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- DASHBOARD VIEW -->
<div id="view-dashboard" class="view container">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h4 class="text-white fw-bold mb-0">Welcome, <span id="parentName"></span></h4>
      <small class="text-white-50">Parent Portal</small>
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
  <div class="d-flex align-items-center mb-4">
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= $appBase ?>/js/pages/parent_portal.js"></script>
</body>
</html>
