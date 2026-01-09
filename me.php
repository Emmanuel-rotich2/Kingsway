<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Kingsway Preparatory School</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="king.css">
  <style>
    .hero-bg {
      background: linear-gradient(135deg, #6f42c1 60%, #20c997 100%);
      color: #fff;
      min-height: 400px;
      position: relative;
      overflow: hidden;
    }

    .hero-graphic {
      position: absolute;
      right: 0;
      bottom: 0;
      width: 350px;
      opacity: 0.15;
      z-index: 0;
    }

    .hero-content {
      position: relative;
      z-index: 1;
    }

    .summary-card {
      min-width: 180px;
      border-radius: 1rem;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.07);
    }

    .school-logo {
      width: 60px;
      height: 60px;
      object-fit: contain;
      border-radius: 50%;
      background: #fff;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.07);
    }

    .school-info {
      margin-bottom: 1rem;
    }

    .school-info h5 {
      margin-bottom: 0.25rem;
    }

    .school-info small {
      font-size: 0.95rem;
      line-height: 1.5;
    }
  </style>
</head>

<body>
  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
    <div class="container">
      <a class="navbar-brand d-flex align-items-center" href="#">
        <img src="./images/download (16).jpg" alt="Kingsway Logo" class="school-logo me-2">
        Kingsway Preparatory School
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="mainNav">
        <ul class="navbar-nav ms-auto">
          <li class="nav-item"><a class="nav-link active" href="#">Home</a></li>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">About</a>
            <ul class="dropdown-menu">
              <li><a class="dropdown-item" href="#history">History</a></li>
              <li><a class="dropdown-item" href="#programs">Programs</a></li>
              <li><a class="dropdown-item" href="#performance">Performance</a></li>
            </ul>
          </li>
          <li class="nav-item"><a class="nav-link" href="#downloads">Downloads</a></li>
          <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>
          <li class="nav-item"><a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#loginModal"><i class="bi bi-box-arrow-in-right"></i> Login</a></li>
        </ul>
      </div>
    </div>
  </nav>

  <!-- Hero Section -->
  <section class="hero-bg d-flex align-items-center">
    <div class="container hero-content py-5">
      <!-- School Info Block -->
      <div class="school-info text-white text-center mb-4">
        <h5 class="fw-bold">KINGSWAY PREPARATORY SCHOOL</h5>
        <small>
          P.O BOX 203-20203, LONDIANI<br>
          PHONE: 0720113030 / 0720113031<br>
          Motto: <em>“In God We Soar”</em>
        </small>
      </div>

      <div class="row align-items-center">
        <div class="col-md-7">
          <h1 class="display-5 fw-bold">Welcome to Kingsway Preparatory School</h1>
          <p class="lead mb-4">Nurturing Excellence, Character & Leadership.<br>
            Discover our vibrant community, holistic programs, and outstanding results.</p>
          <button class="btn btn-light btn-lg shadow" data-bs-toggle="modal" data-bs-target="#loginModal">
            <i class="bi bi-box-arrow-in-right"></i> Admin/Staff Login
          </button>
        </div>
        <div class="col-md-5 d-none d-md-block">
          <svg class="hero-graphic" viewBox="0 0 400 300">
            <ellipse cx="200" cy="150" rx="180" ry="90" fill="#fff" />
            <circle cx="120" cy="120" r="40" fill="#20c997" />
            <circle cx="280" cy="180" r="60" fill="#6f42c1" />
            <rect x="170" y="60" width="60" height="120" rx="30" fill="#fd7e14" opacity="0.7" />
            <polygon points="200,30 220,70 180,70" fill="#0d6efd" opacity="0.8" />
          </svg>
        </div>
      </div>
    </div>
  </section>

  <!-- Summary Cards -->
  <section class="bg-light py-5">
    <div class="container text-center">
      <div class="row g-4 justify-content-center">
        <div class="col-md-3">
          <div class="summary-card bg-white p-4">
            <i class="bi bi-people display-6 text-primary mb-2"></i>
            <h5>800+ Students</h5>
            <p class="text-muted mb-0">From Playgroup to Class 8</p>
          </div>
        </div>
        <div class="col-md-3">
          <div class="summary-card bg-white p-4">
            <i class="bi bi-award display-6 text-success mb-2"></i>
            <h5>Top 5 in County</h5>
            <p class="text-muted mb-0">Consistently Excellent KCPE Results</p>
          </div>
        </div>
        <div class="col-md-3">
          <div class="summary-card bg-white p-4">
            <i class="bi bi-book display-6 text-warning mb-2"></i>
            <h5>Competency-Based</h5>
            <p class="text-muted mb-0">Modern CBC Curriculum</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- About Section -->
  <section class="py-5" id="history">
    <div class="container">
      <div class="row">
        <div class="col-lg-6 mb-4">
          <h2 class="fw-bold">Our History</h2>
          <p>Founded in 2001, Kingsway Preparatory School has grown to become one of the most reputable private primary schools in Kericho County. Our mission has always been to nurture learners who are academically excellent, morally upright, and confident to lead.</p>
        </div>
        <div class="col-lg-6 mb-4" id="programs">
          <h2 class="fw-bold">Academic Programs</h2>
          <p>We offer a wide range of programs including Early Childhood Education (ECD), CBC for grades 1–6, and the 8-4-4 system for class 7 and 8. Our programs emphasize creativity, innovation, and spiritual growth.</p>
        </div>
      </div>
      <div class="row" id="performance">
        <div class="col-lg-12">
          <h2 class="fw-bold">Performance</h2>
          <p>Our school regularly ranks among the top schools in KCPE with most students transitioning to national and extra-county schools. We emphasize not just test scores, but overall growth and discipline.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- Downloads Section -->
  <section class="bg-light py-5" id="downloads">
    <div class="container">
      <h2 class="fw-bold text-center mb-4">Downloads</h2>
      <div class="text-center">
        <a href="#" class="btn btn-outline-primary me-2"><i class="bi bi-download"></i> Prospectus</a>
        <a href="#" class="btn btn-outline-success"><i class="bi bi-download"></i> Fee Structure</a>
      </div>
    </div>
  </section>

  <!-- Contact Section -->


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
      
      // Add loading state to login form - handled via AJAX below
    });
  </script>

  <!-- Footer -->
 <section class="py-5" id="contact">
    <div class="container">
      <h2 class="fw-bold text-center mb-4">Get In Touch</h2>
      <div class="row justify-content-center">
        <div class="col-md-6">
          <p><i class="bi bi-geo-alt-fill"></i> P.O BOX 203-20203, Londiani</p>
          <p><i class="bi bi-telephone-fill"></i> 0720113030 / 0720113031</p>
          <p><i class="bi bi-envelope-fill"></i> info@kingswayschool.ac.ke</p>
          <form action="send_email.php" method="post">
            <div class="mb-3">
              <label for="name" class="form-label">Your Name</label>
              <input type="text" class="form-control" name="name" required>
            </div>
            <div class="mb-3">
              <label for="email" class="form-label">Your Email</label>
              <input type="email" class="form-control" name="email" required>
            </div>
            <div class="mb-3">
              <label for="message" class="form-label">Your Message</label>
              <textarea class="form-control" name="message" rows="5" required></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Send Message</button>
          </form>
        </div>
      </div>
    </div>
  </section>

  <!-- Footer -->
  <footer class="bg-primary text-white pt-4 pb-3">
    <div class="container text-center">
      <p class="mb-2">&copy; 2025 Kingsway Preparatory School. All Rights Reserved.</p>
      <div class="d-flex justify-content-center gap-3 mb-2">
        <a href="https://facebook.com/kingswayschool" target="_blank" class="text-white text-decoration-none fs-5">
          <i class="bi bi-facebook"></i>
        </a>
        <a href="https://twitter.com/kingswayschool" target="_blank" class="text-white text-decoration-none fs-5">
          <i class="bi bi-twitter"></i>
        </a>
        <a href="https://instagram.com/kingswayschool" target="_blank" class="text-white text-decoration-none fs-5">
          <i class="bi bi-instagram"></i>
        </a>
        <a href="https://youtube.com/@kingswayschool" target="_blank" class="text-white text-decoration-none fs-5">
          <i class="bi bi-youtube"></i>
        </a>
      </div>
      <small>Follow us on social media for news, events & updates.</small>
    </div>
  </footer>


  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="js/api.js?v=<?php echo time(); ?>"></script>
  
  <script>
    // Login form handler with AJAX
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
