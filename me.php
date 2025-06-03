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
  <div class="modal fade" id="loginModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content border-0 shadow">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title">Admin/Staff Login</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <form action="login.php" method="post">
            <div class="mb-3">
              <label for="username" class="form-label">Username</label>
              <input type="text" name="username" class="form-control" required>
            </div>
            <div class="mb-3">
              <label for="password" class="form-label">Password</label>
              <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Login</button>
          </form>
        </div>
      </div>
    </div>
  </div>

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
</body>

</html>
