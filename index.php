<!--Description: Main landing page for Kingsway Preparatory School-->
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Kingsway Preparatory School</title>

  <!-- Favicons -->
  <link rel="icon" type="image/png" href="images/favicon/favicon-96x96.png" sizes="96x96" />
  <link rel="icon" type="image/svg+xml" href="images/favicon/favicon.svg" />
  <link rel="shortcut icon" href="images/favicon/favicon.ico" />
  <link rel="apple-touch-icon" sizes="180x180" href="images/favicon/apple-touch-icon.png" />
  <meta name="apple-mobile-web-app-title" content="KingsWay Preparatory School Dashboard" />
  <link rel="manifest" href="images/favicon/site.webmanifest" />

  <!-- CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="king.css">

  <style>
    body {
      background-color: #fffdf5;
      padding-top: 90px; /* space for fixed navbar */
    }

    .navbar-custom {
      background-color: #198754;
    }

    .navbar {
      z-index: 1050;
    }

    .hero-bg {
      background: linear-gradient(135deg, #f9c80e 60%, #198754 100%);
      color: #fff;
      min-height: 350px;
      position: relative;
      overflow: hidden;
    }

    .hero-graphic {
      position: absolute;
      right: 0;
      bottom: 0;
      width: 350px;
      opacity: 0.15;
    }

    .hero-content {
      position: relative;
      z-index: 1;
    }

    .summary-card {
      min-width: 180px;
      border-radius: 1rem;
      background-color: #fff8e1;
      box-shadow: 0 2px 8px rgba(0,0,0,0.07);
      transition: transform .2s ease;
    }

    .summary-card:hover {
      transform: translateY(-5px);
    }

    .school-logo {
      width: 60px;
      height: 60px;
      object-fit: contain;
      border-radius: 50%;
      background: #fff;
      box-shadow: 0 2px 8px rgba(0,0,0,0.07);
    }

    footer {
      background-color: #198754;
    }
  </style>
</head>

<body>

<!-- Notification Modal -->
<div class="modal fade" id="notificationModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content notification-info">
      <div class="modal-body d-flex align-items-center">
        <i class="bi bi-info-circle fs-4 me-3"></i>
        <span class="notification-message"></span>
      </div>
    </div>
  </div>
</div>

<!-- NAVBAR (FIXED) -->
<nav class="navbar navbar-expand-lg navbar-dark navbar-custom shadow-sm fixed-top">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center" href="#">
      <img src="images/kings logo.png" alt="Kingsway Logo" class="school-logo me-2">
      Kingsway Prep School
    </a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item"><a class="nav-link active" href="#">Home</a></li>

        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown">About</a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="#history">History</a></li>
            <li><a class="dropdown-item" href="#programs">Programs</a></li>
            <li><a class="dropdown-item" href="#performance">Performance</a></li>
          </ul>
        </li>

        <li class="nav-item"><a class="nav-link" href="#downloads">Downloads</a></li>
        <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>

        <li class="nav-item">
          <a class="nav-link" data-bs-toggle="modal" data-bs-target="#loginModal">
            <i class="bi bi-box-arrow-in-right"></i> Login
          </a>
        </li>
      </ul>
    </div>
  </div>
</nav>

<!-- HERO -->
<section class="hero-bg d-flex align-items-center">
  <div class="container hero-content py-5">
    <div class="text-center mb-4">
      <h2 class="fw-bold">KINGSWAY PREPARATORY SCHOOL</h2>
      <p>P.O BOX 203-20203, LONDIANI - KENYA | 0720113030 / 0720113031</p>
      <em>Motto: "In God We Soar"</em>
    </div>

    <div class="row align-items-center">
      <div class="col-md-7">
        <h1 class="display-5 fw-bold">Welcome to Kingsway</h1>
        <p class="lead">Nurturing Excellence, Character & Leadership.</p>
        <button class="btn btn-light btn-lg" data-bs-toggle="modal" data-bs-target="#loginModal">
          <i class="bi bi-box-arrow-in-right"></i> Admin / Staff Login
        </button>
      </div>

      <div class="col-md-5 d-none d-md-block">
        <svg class="hero-graphic" viewBox="0 0 400 300">
          <ellipse cx="200" cy="150" rx="180" ry="90" fill="#fff" />
          <circle cx="120" cy="120" r="40" fill="#198754" />
          <circle cx="280" cy="180" r="60" fill="#f9c80e" />
        </svg>
      </div>
    </div>
  </div>
</section>

<!-- SUMMARY -->
<div class="container my-5">
  <div class="row g-4 justify-content-center text-center">
    <div class="col-md-3">
      <div class="summary-card p-4">
        <i class="bi bi-people-fill fs-1 text-success"></i>
        <h4>1200+</h4>
        <p class="text-muted">Students Enrolled</p>
      </div>
    </div>
    <div class="col-md-3">
      <div class="summary-card p-4">
        <i class="bi bi-mortarboard-fill fs-1 text-success"></i>
        <h4>98%</h4>
        <p class="text-muted">JSS Pass Rate</p>
      </div>
    </div>
    <div class="col-md-3">
      <div class="summary-card p-4">
        <i class="bi bi-award-fill fs-1 text-warning"></i>
        <h4>30+</h4>
        <p class="text-muted">Awards</p>
      </div>
    </div>
  </div>
</div>

<!-- ABOUT / DOWNLOADS -->
<div class="container my-5">
  <div class="row g-4">
    <div class="col-md-6" id="history">
      <h3>Our History</h3>
      <p>Kingsway Preparatory School was founded to provide quality education and holistic development.</p>
    </div>

    <div class="col-md-6" id="programs">
      <h3>Our Programs</h3>
      <ul>
        <li>Early Childhood Development (ECD)</li>
        <li>Primary & Junior Secondary</li>
        <li>STEM & ICT Integration</li>
        <li>Sports, Music & Drama</li>
        <li>Leadership Training</li>
      </ul>
    </div>

    <div class="col-md-6" id="performance">
      <h3>Performance</h3>
      <p>98% KCPE success rate and outstanding co-curricular achievements.</p>
    </div>

    <div class="col-md-6" id="downloads">
      <h3>Downloads</h3>
      <ul>
        <li><a href="downloads/admission_letter.pdf" target="_blank">Admission Letter</a></li>
        <li><a href="downloads/fee_structure.pdf" target="_blank">Fee Structure</a></li>
        <li><a href="downloads/calendar.pdf" target="_blank">School Calendar</a></li>
      </ul>
    </div>
  </div>
</div>

<!-- CONTACT -->
<div class="container my-5" id="contact">
  <h3>Contact Us</h3>
  <form class="bg-light p-4 rounded shadow-sm">
    <div class="row">
      <div class="col-md-6 mb-3">
        <input class="form-control" placeholder="Your Name" required>
      </div>
      <div class="col-md-6 mb-3">
        <input class="form-control" type="email" placeholder="Your Email" required>
      </div>
    </div>
    <textarea class="form-control mb-3" rows="4" placeholder="Your Message" required></textarea>
    <button class="btn btn-primary">Send Message</button>
  </form>
</div>

<!-- FOOTER -->
<footer class="text-white py-4 mt-5">
  <div class="container text-center">
    <small>&copy; 2025 Kingsway Preparatory School. All rights reserved.</small>
  </div>
</footer>

  <!-- Login Modal -->
  <div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <form class="modal-content border-0 shadow-lg" id="loginForm" style="border-radius: 1rem; overflow: hidden;">
        <!-- Header with gradient background -->
        <div class="modal-header border-0 text-white py-4" style="background: linear-gradient(135deg, #198754 0%, #0d6efd 100%);">
          <div class="w-100 text-center">
            <img src="./images/logo.jpg" alt="Kingsway Logo" class="mb-3" style="width: 80px; height: 80px; object-fit: contain; border-radius: 50%; background: #fff; padding: 5px; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
            <h4 class="modal-title fw-bold mb-1" id="loginModalLabel">Welcome Back!</h4>
            <p class="mb-0 opacity-75 small">Sign in to Kingsway Academy Portal</p>
          </div>
          <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        
        <div class="modal-body px-4 py-4">
          <!-- Username/Email Field -->
          <div class="mb-3">
            <label for="loginUsername" class="form-label fw-semibold text-muted small">
              <i class="bi bi-person me-1"></i>Username or Email
            </label>
            <div class="input-group">
              <span class="input-group-text bg-light border-end-0"><i class="bi bi-person-circle text-muted"></i></span>
              <input type="text"
                id="loginUsername"
                name="username"
                class="form-control border-start-0 ps-0"
                placeholder="Enter your username or email"
                autocomplete="username"
                required
                style="border-radius: 0 0.375rem 0.375rem 0;">
            </div>
          </div>
          
          <!-- Password Field with Show/Hide Toggle -->
          <div class="mb-3">
            <label for="loginPassword" class="form-label fw-semibold text-muted small">
              <i class="bi bi-lock me-1"></i>Password
            </label>
            <div class="input-group">
              <span class="input-group-text bg-light border-end-0"><i class="bi bi-key text-muted"></i></span>
              <input type="password"
                id="loginPassword"
                name="password"
                class="form-control border-start-0 border-end-0 ps-0"
                placeholder="Enter your password"
                autocomplete="current-password"
                required>
              <button type="button" class="btn btn-outline-secondary border-start-0 bg-light" id="togglePassword" tabindex="-1" title="Show/Hide Password">
                <i class="bi bi-eye" id="togglePasswordIcon"></i>
              </button>
            </div>
          </div>
          
          <!-- Remember Me & Forgot Password -->
          <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="form-check">
              <input type="checkbox" class="form-check-input" id="rememberMe" name="remember_me">
              <label class="form-check-label small text-muted" for="rememberMe">Remember me</label>
            </div>
            <a href="forgot_password.php" class="text-decoration-none small fw-semibold" style="color: #198754;">
              <i class="bi bi-question-circle me-1"></i>Forgot Password?
            </a>
          </div>
          
          <!-- Error Alert -->
          <div id="loginError" class="alert alert-danger d-none py-2 small">
            <i class="bi bi-exclamation-triangle me-1"></i>
            <span id="loginErrorText"></span>
          </div>
          
          <!-- Login Button -->
          <button type="submit" class="btn btn-success w-100 py-2 fw-semibold" id="loginSubmitBtn" style="border-radius: 0.5rem;">
            <span id="loginBtnText"><i class="bi bi-box-arrow-in-right me-2"></i>Sign In</span>
            <span id="loginSpinner" class="d-none">
              <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
              Signing in...
            </span>
          </button>
        </div>
        
        <!-- Footer -->
        <div class="modal-footer border-0 bg-light py-3 justify-content-center">
          <small class="text-muted">
            <i class="bi bi-shield-lock me-1"></i>
            Secure login protected by SSL encryption
          </small>
        </div>
      </form>
    </div>
  </div>

  <!-- Password Toggle Script -->
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const togglePassword = document.getElementById('togglePassword');
      const passwordInput = document.getElementById('loginPassword');
      const toggleIcon = document.getElementById('togglePasswordIcon');
      
      if (togglePassword && passwordInput && toggleIcon) {
        togglePassword.addEventListener('click', function() {
          const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
          passwordInput.setAttribute('type', type);
          toggleIcon.classList.toggle('bi-eye');
          toggleIcon.classList.toggle('bi-eye-slash');
        });
      }
      
      // Add loading state to login form
      const loginForm = document.getElementById('loginForm');
      if (loginForm) {
        loginForm.addEventListener('submit', function() {
          const btnText = document.getElementById('loginBtnText');
          const spinner = document.getElementById('loginSpinner');
          const submitBtn = document.getElementById('loginSubmitBtn');
          
          if (btnText && spinner && submitBtn) {
            btnText.classList.add('d-none');
            spinner.classList.remove('d-none');
            submitBtn.disabled = true;
          }
        });
      }
    });
  </script>

  <!-- Notification -->
  <div id="notification" class="alert d-none"></div>

  <!-- Footer -->
  <footer class="text-white py-4 mt-5">
    <div class="container">
      <div class="row">
        <div class="col-md-4">
          <h5>Contact Info</h5>
          <p>P.O BOX 203-20203, LONDIANI<br>
            Phone: 0720113030 / 0720113031<br>
            Email: info@kingsway.ac.ke</p>
        </div>
        <div class="col-md-4">
          <h5>Quick Links</h5>
          <ul class="list-unstyled">
            <li><a href="#" class="text-white">About Us</a></li>
            <li><a href="#" class="text-white">Admissions</a></li>
            <li><a href="#" class="text-white">News & Events</a></li>
            <li><a href="#" class="text-white">Contact Us</a></li>
          </ul>
        </div>
        <div class="col-md-4">
          <h5>Follow Us</h5>
          <div class="d-flex gap-3 fs-4">
            <a href="#" class="text-white"><i class="bi bi-facebook"></i></a>
            <a href="#" class="text-white"><i class="bi bi-twitter"></i></a>
            <a href="#" class="text-white"><i class="bi bi-instagram"></i></a>
            <a href="#" class="text-white"><i class="bi bi-youtube"></i></a>
          </div>
        </div>
      </div>
      <hr class="my-4">
      <div class="text-center">
        <small>&copy; 2025 Kingsway Preparatory School. All rights reserved.</small>
      </div>
    </div>
  </footer>

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="js/api.js?v=<?php echo time(); ?>"></script>
  <script src="js/sidebar.js?v=<?php echo time(); ?>"></script>
  
  <script>
    // Login form handler
    document.addEventListener('DOMContentLoaded', function() {
      const loginForm = document.getElementById('loginForm');
      const loginError = document.getElementById('loginError');
      const loginErrorText = document.getElementById('loginErrorText');
      const loginBtnText = document.getElementById('loginBtnText');
      const loginSpinner = document.getElementById('loginSpinner');
      const loginSubmitBtn = document.getElementById('loginSubmitBtn');
      
      // Function to reset button state
      function resetLoginButton() {
        if (loginBtnText && loginSpinner && loginSubmitBtn) {
          loginBtnText.classList.remove('d-none');
          loginSpinner.classList.add('d-none');
          loginSubmitBtn.disabled = false;
        }
      }
      
      // Function to show error
      function showLoginError(message) {
        if (loginErrorText) {
          loginErrorText.textContent = message;
        } else if (loginError) {
          loginError.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i>' + message;
        }
        if (loginError) {
          loginError.classList.remove('d-none');
        }
        resetLoginButton();
      }
      
      if (loginForm) {
        loginForm.addEventListener('submit', async function(e) {
          e.preventDefault();
          
          // Get form data
          const username = this.querySelector('input[name="username"]').value;
          const password = this.querySelector('input[name="password"]').value;
          
          // Hide previous errors and show loading state
          if (loginError) loginError.classList.add('d-none');
          if (loginBtnText) loginBtnText.classList.add('d-none');
          if (loginSpinner) loginSpinner.classList.remove('d-none');
          if (loginSubmitBtn) loginSubmitBtn.disabled = true;
          
          try {
            console.log('Attempting login for:', username);
            
            // Call the login API
            const response = await API.auth.login(username, password);
            
            console.log('Login response:', response);
            
            if (response && response.token) {
              console.log('Login successful, redirecting...');
              // The API.auth.login already handles the redirect
            } else {
              throw new Error(response?.message || 'Login failed');
            }
          } catch (error) {
            console.error('Login error:', error);
            showLoginError(error.message || 'Login failed. Please try again.');
          }
        });
      }
    });
  </script>
</body>
</html>
