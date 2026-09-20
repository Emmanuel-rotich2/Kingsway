<?php
$appBase    = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '')),'/');
if ($appBase === '.') $appBase = '';
$pageTitle  = 'About Us';
$activePage = 'about';
$pageScript = 'about';
// Dynamic sections are rendered by js/pages/public/about.js via /api/website/*.
require_once __DIR__ . '/public/layout/public_data.php';
?>
<?php include __DIR__ . '/public/layout/header.php'; ?>

<div class="page-header">
  <div class="container position-relative" style="z-index:1">
    <nav aria-label="breadcrumb"><ol class="breadcrumb mb-2">
      <li class="breadcrumb-item"><a href="<?= $appBase ?>/index.php">Home</a></li>
      <li class="breadcrumb-item active">About Us</li>
    </ol></nav>
    <h1 class="page-title">About Kingsway</h1>
    <p class="mt-2" style="color:rgba(255,255,255,.7);font-size:1rem">Excellence, Character &amp; Leadership since <span id="about-founded">2008</span></p>
  </div>
</div>

<!-- Mission & Vision -->
<section class="section" id="mission">
  <div class="container">
    <div class="row g-5 align-items-center">
      <div class="col-lg-6 reveal reveal-left">
        <div class="section-label"><span>Who We Are</span></div>
        <h2 class="section-title">Our <span>Mission &amp; Vision</span></h2>
        <div class="p-4 rounded-4 mb-4" style="background:linear-gradient(135deg,#e8f5e9,#f1f8f4);border-left:4px solid var(--green)">
          <h5 class="text-success fw-bold mb-2"><i class="bi bi-bullseye me-2"></i>Mission</h5>
          <p class="mb-0" id="about-mission"></p>
        </div>
        <div class="p-4 rounded-4 mb-4" style="background:linear-gradient(135deg,#fff8e1,#fffde7);border-left:4px solid var(--gold)">
          <h5 class="fw-bold mb-2" style="color:var(--gold-dark)"><i class="bi bi-eye-fill me-2"></i>Vision</h5>
          <p class="mb-0" id="about-vision"></p>
        </div>
        <div class="p-4 rounded-4" style="background:linear-gradient(135deg,#e8eaf6,#ede7f6);border-left:4px solid #7c4dff">
          <h5 class="fw-bold mb-2" style="color:#512da8"><i class="bi bi-gem me-2"></i>Motto</h5>
          <p class="mb-0 fst-italic fw-semibold fs-5">"<span id="about-motto"></span>"</p>
        </div>
      </div>
      <div class="col-lg-6 reveal reveal-right">
        <h4 class="fw-bold mb-4 text-success">Our Core Values</h4>
        <div class="row g-3" id="about-values">
        </div>
      </div>
    </div>
  </div>
</section>

<!-- History Timeline -->
<section class="section section-alt" id="history">
  <div class="container">
    <div class="text-center mb-5 reveal">
      <div class="section-label justify-content-center"><span>Our Story</span></div>
      <h2 class="section-title">A Journey of <span>Excellence</span></h2>
    </div>
    <div class="row justify-content-center">
      <div class="col-lg-10">
        <div class="position-relative" style="border-left:3px solid var(--green);padding-left:32px">
          <div id="about-history"></div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Leadership -->
<section class="section" id="leadership">
  <div class="container">
    <div class="text-center mb-5 reveal">
      <div class="section-label justify-content-center"><span>Meet the Team</span></div>
      <h2 class="section-title">Our <span>Leadership Team</span></h2>
      <p class="section-subtitle mx-auto">Experienced educators committed to excellence at every level.</p>
    </div>
    <div class="row g-4 justify-content-center" id="about-leadership">
    </div>
  </div>
</section>

<!-- Programs -->
<section class="section section-alt" id="programs">
  <div class="container">
    <div class="text-center mb-5 reveal">
      <div class="section-label justify-content-center"><span>What We Offer</span></div>
      <h2 class="section-title">Academic <span>Programs</span></h2>
    </div>
    <div class="row g-4" id="about-programs">
    </div>
  </div>
</section>

<!-- Facilities -->
<section class="section" id="facilities">
  <div class="container">
    <div class="text-center mb-5 reveal">
      <div class="section-label justify-content-center"><span>Infrastructure</span></div>
      <h2 class="section-title">World-Class <span>Facilities</span></h2>
    </div>
    <div class="row g-3" id="about-facilities">
    </div>
  </div>
</section>

<!-- CTA -->
<section class="cta-banner">
  <div class="container position-relative" style="z-index:1">
    <div class="row align-items-center g-4">
      <div class="col-lg-8 reveal">
        <h2 class="section-title text-white">Ready to Be Part of the <span style="color:var(--gold)">Kingsway Family?</span></h2>
        <p style="color:rgba(255,255,255,.8)">Applications are open for all grade levels. Spaces fill fast — apply today.</p>
      </div>
      <div class="col-lg-4 text-lg-end reveal">
        <a href="<?= $appBase ?>/index.php?route=rdb9314b5fdb2#apply" class="btn-kw-gold">
          <i class="bi bi-pencil-square"></i>Apply for Admission
        </a>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/public/layout/footer.php'; ?>
