<?php
/* Shared public header — included by every public page.
 * Expects: $pageTitle (string), $activePage (string), $appBase (string) */
$pageTitle  = $pageTitle  ?? 'Kingsway Preparatory School';
$activePage = $activePage ?? 'home';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> | Kingsway Preparatory School</title>
  <meta name="description" content="Kingsway Preparatory School — Nurturing Excellence, Character &amp; Leadership. Located in Londiani, Kenya.">

  <!-- Favicons -->
  <link rel="icon" type="image/png" href="<?= $appBase ?>/images/favicon/favicon-96x96.png" sizes="96x96">
  <link rel="icon" type="image/svg+xml" href="<?= $appBase ?>/images/favicon/favicon.svg">
  <link rel="shortcut icon" href="<?= $appBase ?>/images/favicon/favicon.ico">
  <link rel="apple-touch-icon" sizes="180x180" href="<?= $appBase ?>/images/favicon/apple-touch-icon.png">

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

  <!-- CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="<?= $appBase ?>/public/css/public.css">
</head>
<body>

<!-- ═══ NAVBAR ═══════════════════════════════════════════════════════════════ -->
<nav class="site-nav navbar navbar-expand-lg">
  <div class="container">

    <a class="navbar-brand" href="<?= $appBase ?>/index.php">
      <img src="<?= $appBase ?>/images/kings logo.png" alt="Kingsway Logo" onerror="this.style.display='none'">
      <span>Kingsway Prep</span>
    </a>

    <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#siteNav" aria-controls="siteNav" aria-expanded="false">
      <i class="bi bi-list text-white fs-3"></i>
    </button>

    <div class="collapse navbar-collapse" id="siteNav">
      <ul class="navbar-nav ms-auto align-items-lg-center gap-1">

        <li class="nav-item">
          <a class="nav-link <?= $activePage==='home'?'active':'' ?>" href="<?= $appBase ?>/index.php">Home</a>
        </li>

        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle <?= $activePage==='about'?'active':'' ?>" href="#" data-bs-toggle="dropdown">About Us</a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="<?= $appBase ?>/about.php#mission"><i class="bi bi-bullseye me-2 text-success"></i>Mission &amp; Vision</a></li>
            <li><a class="dropdown-item" href="<?= $appBase ?>/about.php#history"><i class="bi bi-book me-2 text-success"></i>Our History</a></li>
            <li><a class="dropdown-item" href="<?= $appBase ?>/about.php#leadership"><i class="bi bi-person-badge me-2 text-success"></i>Leadership Team</a></li>
            <li><a class="dropdown-item" href="<?= $appBase ?>/about.php#facilities"><i class="bi bi-buildings me-2 text-success"></i>Facilities</a></li>
          </ul>
        </li>

        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle <?= $activePage==='admissions'?'active':'' ?>" href="#" data-bs-toggle="dropdown">Admissions</a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="<?= $appBase ?>/admissions.php#process"><i class="bi bi-list-check me-2 text-success"></i>Admission Process</a></li>
            <li><a class="dropdown-item" href="<?= $appBase ?>/admissions.php#requirements"><i class="bi bi-file-earmark-text me-2 text-success"></i>Requirements</a></li>
            <li><a class="dropdown-item" href="<?= $appBase ?>/admissions.php#fees"><i class="bi bi-cash me-2 text-success"></i>Fee Structure</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item fw-semibold text-success" href="<?= $appBase ?>/admissions.php#apply"><i class="bi bi-send me-2"></i>Apply Now</a></li>
          </ul>
        </li>

        <li class="nav-item">
          <a class="nav-link <?= $activePage==='news'?'active':'' ?>" href="<?= $appBase ?>/news.php">
            <i class="bi bi-newspaper me-1"></i>News
          </a>
        </li>

        <li class="nav-item">
          <a class="nav-link <?= $activePage==='events'?'active':'' ?>" href="<?= $appBase ?>/events.php">
            <i class="bi bi-calendar-event me-1"></i>Events
          </a>
        </li>

        <li class="nav-item">
          <a class="nav-link <?= $activePage==='careers'?'active':'' ?>" href="<?= $appBase ?>/careers.php">Careers</a>
        </li>

        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">More</a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="<?= $appBase ?>/downloads.php"><i class="bi bi-download me-2 text-success"></i>Downloads</a></li>
            <li><a class="dropdown-item" href="<?= $appBase ?>/contact.php"><i class="bi bi-envelope me-2 text-success"></i>Contact Us</a></li>
            <li><a class="dropdown-item" href="<?= $appBase ?>/parent_portal.php"><i class="bi bi-people me-2 text-success"></i>Parent Portal</a></li>
          </ul>
        </li>

        <li class="nav-item">
          <a class="nav-link btn-login" href="#" data-bs-toggle="modal" data-bs-target="#loginModal">
            <i class="bi bi-box-arrow-in-right me-1"></i>Login
          </a>
        </li>

      </ul>
    </div>
  </div>
</nav>

<!-- ═══ LOGIN MODAL ══════════════════════════════════════════════════════════ -->
<div class="modal fade modal-login" id="loginModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:420px">
    <form class="modal-content" id="loginForm">
      <div class="modal-login-header">
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        <img src="<?= $appBase ?>/images/logo.jpg" alt="Logo" class="logo" onerror="this.style.display='none'">
        <h5>Welcome Back</h5>
        <p>Sign in to Kingsway Academy Portal</p>
      </div>
      <div class="p-4">
        <div class="mb-3">
          <label class="form-label small fw-semibold text-muted">Username or Email</label>
          <div class="input-group">
            <span class="input-group-text bg-light"><i class="bi bi-person-circle text-muted"></i></span>
            <input type="text" name="username" id="loginUsername" class="form-control" placeholder="Enter username or email" required autocomplete="username">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label small fw-semibold text-muted">Password</label>
          <div class="input-group">
            <span class="input-group-text bg-light"><i class="bi bi-key text-muted"></i></span>
            <input type="password" name="password" id="loginPassword" class="form-control" placeholder="Enter password" required autocomplete="current-password">
            <button type="button" class="btn btn-outline-secondary bg-light" id="togglePassword">
              <i class="bi bi-eye" id="togglePasswordIcon"></i>
            </button>
          </div>
        </div>
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div class="form-check mb-0">
            <input class="form-check-input" type="checkbox" id="rememberMe">
            <label class="form-check-label small text-muted" for="rememberMe">Remember me</label>
          </div>
          <a href="<?= $appBase ?>/forgot_password.php" class="small fw-semibold text-success">Forgot password?</a>
        </div>
        <div id="loginError" class="alert alert-danger d-none py-2 small mb-3">
          <i class="bi bi-exclamation-triangle me-1"></i><span id="loginErrorText"></span>
        </div>
        <button type="submit" class="btn-kw-primary w-100 justify-content-center py-2" id="loginSubmitBtn">
          <span id="loginBtnText"><i class="bi bi-box-arrow-in-right me-2"></i>Sign In</span>
          <span id="loginSpinner" class="d-none"><span class="spinner-border spinner-border-sm me-2"></span>Signing in…</span>
        </button>
      </div>
      <div class="bg-light text-center py-3 px-4 border-top rounded-bottom">
        <small class="text-muted"><i class="bi bi-shield-lock me-1 text-success"></i>Secured with SSL encryption</small>
      </div>
    </form>
  </div>
</div>
