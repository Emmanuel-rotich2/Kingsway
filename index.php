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
  <link rel="icon" type="image/png" href="images/favicon/favicon-96x96.png" sizes="96x96" />
  <link rel="icon" type="image/svg+xml" href="images/favicon/favicon.svg" />
  <link rel="shortcut icon" href="images/favicon/favicon.ico" />
  <link rel="apple-touch-icon" sizes="180x180" href="images/favicon/apple-touch-icon.png" />
  <meta name="apple-mobile-web-app-title" content="KingsWay Preparatory School Dashboard" />
  <link rel="manifest" href="images/favicon/site.webmanifest" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="king.css">
  <style>
    body {
      background-color: #fffdf5;
    }

    .navbar-custom {
      background-color: #198754;
      /* Bootstrap green */
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
      background-color: #fff8e1;
    }

    .school-logo {
      width: 60px;
      height: 60px;
      object-fit: contain;
      border-radius: 50%;
      background: #fff;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.07);
    }

    footer {
      background-color: #198754;
    }
  </style>
</head>

<body>

  <div class="modal fade" id="notificationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content notification-info">
        <div class="modal-body d-flex align-items-center">
          <span class="notification-icon me-3"><i class="bi bi-info-circle"></i></span>
          <span class="notification-message"></span>
        </div>
      </div>
    </div>
  </div>
  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg navbar-dark navbar-custom shadow-sm">
    <div class="container">
      <a class="navbar-brand d-flex align-items-center" href="#">
        <img src="./images/logo.jpg" alt="Kingsway Logo" class="school-logo me-2">
        Kingsway Prep School
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
      <div class="text-center mb-4">
        <h2 class="fw-bold">KINGSWAY PREPARATORY SCHOOL</h2>
        <p class="mb-0">P.O BOX 203-20203, LONDIANI | PHONE: 0720113030 / 0720113031</p>
        <em>Motto: "In God We Soar"</em>
      </div>
      <div class="row align-items-center">
        <div class="col-md-7">
          <h1 class="display-5 fw-bold">Welcome to Kingsway</h1>
          <p class="lead">Nurturing Excellence, Character & Leadership.</p>
          <button class="btn btn-light btn-lg" data-bs-toggle="modal" data-bs-target="#loginModal">
            <i class="bi bi-box-arrow-in-right"></i> Admin/Staff Login
          </button>
        </div>
        <div class="col-md-5 d-none d-md-block">
          <svg class="hero-graphic" viewBox="0 0 400 300">
            <ellipse cx="200" cy="150" rx="180" ry="90" fill="#fff" />
            <circle cx="120" cy="120" r="40" fill="#198754" />
            <circle cx="280" cy="180" r="60" fill="#f9c80e" />
            <rect x="170" y="60" width="60" height="120" rx="30" fill="#fd7e14" opacity="0.7" />
            <polygon points="200,30 220,70 180,70" fill="#0d6efd" opacity="0.8" />
          </svg>
        </div>
      </div>
    </div>
  </section>

  <!-- Summary Info Cards -->
  <div class="container my-5">
    <div class="row g-4 justify-content-center">
      <div class="col-md-3">
        <div class="summary-card p-4 text-center">
          <div class="mb-2"><i class="bi bi-people-fill fs-1 text-success"></i></div>
          <h4>1200+</h4>
          <p class="mb-0 text-muted">Students Enrolled</p>
        </div>
      </div>
      <div class="col-md-3">
        <div class="summary-card p-4 text-center">
          <div class="mb-2"><i class="bi bi-mortarboard-fill fs-1 text-success"></i></div>
          <h4>98%</h4>
          <p class="mb-0 text-muted">JSS Pass Rate</p>
        </div>
      </div>
      <div class="col-md-3">
        <div class="summary-card p-4 text-center">
          <div class="mb-2"><i class="bi bi-award-fill fs-1 text-warning"></i></div>
          <h4>30+</h4>
          <p class="mb-0 text-muted">Co-curricular Awards</p>
        </div>
      </div>
    </div>
  </div>

  <!-- About and Downloads -->
  <div class="container my-5" id="about">
    <div class="row g-4">
      <div class="col-md-6" id="history">
        <h3>Our History</h3>
        <p>Kingsway Preparatory School was founded to provide quality education and holistic development. We have grown into a leading institution.</p>
      </div>
      <div class="col-md-6" id="programs">
        <h3>Our Programs</h3>
        <ul>
          <li>Early Childhood Development (ECD)</li>
          <li>Primary & Junior Secondary</li>
          <li>STEM & ICT Integration</li>
          <li>Sports, Music & Drama</li>
          <li>Clubs & Leadership Training</li>
        </ul>
      </div>
      <div class="col-md-6" id="performance">
        <h3>Performance</h3>
        <p>Kingsway has a 98% KCPE pass rate and many awards in academics and co-curricular activities.</p>
      </div>
      <div class="col-md-6" id="downloads">
        <h3>Downloads</h3>
        <ul>
          <li><a href="downloads/admission_letter.pdf" target="_blank"><i class="bi bi-file-earmark-arrow-down"></i> Admission Letter</a></li>
          <li><a href="downloads/fee_structure.pdf" target="_blank"><i class="bi bi-file-earmark-arrow-down"></i> Fee Structure</a></li>
          <li><a href="downloads/calendar.pdf" target="_blank"><i class="bi bi-file-earmark-arrow-down"></i> School Calendar</a></li>
        </ul>
      </div>
    </div>
  </div>


  <!-- Contact Section -->
  <div class="container my-5" id="contact">
    <div class="row justify-content-center">
      <div class="col-md-8">
        <h3>Contact Us</h3>
        <form id="contact-form" class="bg-light p-4 rounded shadow-sm">
          <div class="row">
            <div class="col-md-6 mb-3">
              <input type="text" name="name" class="form-control" placeholder="Your Name" required>
            </div>
            <div class="col-md-6 mb-3">
              <input type="email" name="email" class="form-control" placeholder="Your Email" required>
            </div>
          </div>
          <div class="mb-3">
            <input type="text" name="subject" class="form-control" placeholder="Subject" required>
          </div>
          <div class="mb-3">
            <textarea name="message" class="form-control" rows="4" placeholder="Your Message" required></textarea>
          </div>
          <div id="contact-success" class="text-success mb-2"></div>
          <button class="btn btn-primary" type="submit">Send Message</button>
        </form>
      </div>
    </div>
  </div>

  <!-- Login Modal -->
  <div class="modal fade" id="loginModal" tabindex="-1">
    <div class="modal-dialog">
      <form class="modal-content" id="loginForm">
        <div class="modal-header">
          <h5 class="modal-title">Admin/Staff Login</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <input type="text"
              name="username"
              class="form-control"
              placeholder="Username or Email"
              autocomplete="username"
              required>
          </div>
          <div class="mb-3">
            <input type="password"
              name="password"
              class="form-control"
              placeholder="Password"
              autocomplete="current-password"
              required>
          </div>
          <div id="loginError" class="alert alert-danger d-none"></div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Login</button>
        </div>
      </form>
    </div>
  </div>

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
      
      if (loginForm) {
        loginForm.addEventListener('submit', async function(e) {
          e.preventDefault();
          
          // Get form data
          const username = this.querySelector('input[name="username"]').value;
          const password = this.querySelector('input[name="password"]').value;
          
          // Hide previous errors
          loginError.classList.add('d-none');
          
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
            loginError.textContent = error.message || 'Login failed. Please try again.';
            loginError.classList.remove('d-none');
          }
        });
      }
    });
  </script>
</body>

</html>