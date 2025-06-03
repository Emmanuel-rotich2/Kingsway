<!-- Description: Main landing page for Kingsway Preparatory School -->
<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Kingsway Preparatory School</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body {
      background-color: #fffdf5;
    }

    .navbar-custom {
      background-color: #198754; /* Bootstrap green */
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
  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg navbar-dark navbar-custom shadow-sm">
    <div class="container">
      <a class="navbar-brand d-flex align-items-center" href="#">
        <img src="./images/download (16).jpg" alt="Kingsway Logo" class="school-logo me-2">
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
        <em>Motto: “In God We Soar”</em>
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

  <!-- Contact -->
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
          <button class="btn btn-success" type="submit">Send Message</button>
        </form>
      </div>
    </div>
  </div>

  <!-- Login Modal -->
  <div class="modal fade" id="loginModal" tabindex="-1">
    <div class="modal-dialog">
      <form class="modal-content" id="login-form">
        <div class="modal-header">
          <h5 class="modal-title">Admin/Staff Login</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input name="username" class="form-control mb-2" placeholder="Username" required>
          <input name="password" type="password" class="form-control mb-2" placeholder="Password" required>
          <div id="login-error" class="text-danger small"></div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-success" type="submit">Login</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Footer -->
  <footer class="text-white pt-4 pb-3">
    <div class="container text-center">
      <p class="mb-2">&copy; 2025 Kingsway Preparatory School. All Rights Reserved.</p>
      <div class="d-flex justify-content-center gap-3 mb-2">
        <a href="#" class="text-white fs-5"><i class="bi bi-facebook"></i></a>
        <a href="#" class="text-white fs-5"><i class="bi bi-twitter"></i></a>
        <a href="#" class="text-white fs-5"><i class="bi bi-instagram"></i></a>
        <a href="#" class="text-white fs-5"><i class="bi bi-youtube"></i></a>
      </div>
      <small>Follow us on social media for news, events & updates.</small>
    </div>
  </footer>

  <script>
    document.getElementById('login-form').addEventListener('submit', function(e) {
      e.preventDefault();
      const formData = new FormData(this);
      fetch('api/login.php', {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          window.location = 'home.php';
        } else {
          document.getElementById('login-error').textContent = data.message || 'Invalid credentials';
        }
      });
    });

    document.getElementById('contact-form').addEventListener('submit', function(e) {
      e.preventDefault();
      const formData = new FormData(this);
      fetch('api/contact.php', {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          document.getElementById('contact-success').textContent = "Message sent successfully!";
          this.reset();
        } else {
          document.getElementById('contact-success').textContent = "Failed to send message. Try again.";
        }
      });
    });
  </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
