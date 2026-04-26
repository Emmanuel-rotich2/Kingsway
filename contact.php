<?php
$appBase    = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '')),'/');
if ($appBase === '.') $appBase = '';
$pageTitle  = 'Contact Us';
$activePage = 'contact';
?>
<?php include __DIR__ . '/public/layout/header.php'; ?>

<div class="page-header">
  <div class="container position-relative" style="z-index:1">
    <nav aria-label="breadcrumb"><ol class="breadcrumb mb-2">
      <li class="breadcrumb-item"><a href="<?= $appBase ?>/index.php">Home</a></li>
      <li class="breadcrumb-item active">Contact Us</li>
    </ol></nav>
    <h1 class="page-title">Get In Touch</h1>
    <p class="mt-2" style="color:rgba(255,255,255,.7)">We'd love to hear from you. Our team is ready to help.</p>
  </div>
</div>

<!-- Contact Grid -->
<section class="section">
  <div class="container">
    <div class="row g-5">

      <!-- Contact Info Card -->
      <div class="col-lg-5">
        <div class="contact-info-card reveal reveal-left">
          <h3 class="fw-bold text-white mb-2">Contact Information</h3>
          <p style="color:rgba(255,255,255,.7);font-size:.9rem" class="mb-4">Visit us, call us, or send us a message. We respond within 24 hours on working days.</p>

          <div class="contact-info-item">
            <div class="ci-icon"><i class="bi bi-geo-alt-fill"></i></div>
            <div>
              <div class="ci-label">Physical Address</div>
              <div class="ci-value">Kingsway Preparatory School<br>Londiani – Kericho Road<br>Londiani Town, Kenya</div>
            </div>
          </div>
          <div class="contact-info-item">
            <div class="ci-icon"><i class="bi bi-envelope-fill"></i></div>
            <div>
              <div class="ci-label">Postal Address</div>
              <div class="ci-value">P.O BOX 203-20203<br>Londiani, Kericho County</div>
            </div>
          </div>
          <div class="contact-info-item">
            <div class="ci-icon"><i class="bi bi-telephone-fill"></i></div>
            <div>
              <div class="ci-label">Phone Numbers</div>
              <div class="ci-value">+254 720 113 030<br>+254 720 113 031</div>
            </div>
          </div>
          <div class="contact-info-item">
            <div class="ci-icon"><i class="bi bi-at"></i></div>
            <div>
              <div class="ci-label">Email</div>
              <div class="ci-value">info@kingswaypreparatoryschool.sc.ke</div>
            </div>
          </div>
          <div class="contact-info-item">
            <div class="ci-icon"><i class="bi bi-clock-fill"></i></div>
            <div>
              <div class="ci-label">Office Hours</div>
              <div class="ci-value">Monday – Friday: 7:30 AM – 5:00 PM<br>Saturday: 9:00 AM – 1:00 PM</div>
            </div>
          </div>

          <hr style="border-color:rgba(255,255,255,.2)" class="my-4">
          <div class="ci-label mb-3">Follow Us</div>
          <div class="social-links">
            <a href="#" aria-label="Facebook"><i class="bi bi-facebook"></i></a>
            <a href="#" aria-label="Twitter"><i class="bi bi-twitter-x"></i></a>
            <a href="#" aria-label="Instagram"><i class="bi bi-instagram"></i></a>
            <a href="#" aria-label="WhatsApp"><i class="bi bi-whatsapp"></i></a>
            <a href="#" aria-label="YouTube"><i class="bi bi-youtube"></i></a>
          </div>
        </div>
      </div>

      <!-- Contact Form -->
      <div class="col-lg-7">
        <div class="contact-form-wrap reveal reveal-right">
          <div class="section-label mb-2"><span>Send a Message</span></div>
          <h3 class="fw-bold mb-1">How Can We <span class="text-green">Help You?</span></h3>
          <p class="text-muted small mb-4">Fill in the form below and we'll get back to you as soon as possible.</p>

          <form id="contactForm">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label small fw-semibold">Full Name *</label>
                <input type="text" class="form-control-kw" placeholder="Your full name" required>
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-semibold">Phone Number</label>
                <input type="tel" class="form-control-kw" placeholder="+254 7XX XXX XXX">
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-semibold">Email Address *</label>
                <input type="email" class="form-control-kw" placeholder="your@email.com" required>
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-semibold">Subject</label>
                <select class="form-control-kw">
                  <option value="">Select a subject…</option>
                  <option>Admission Enquiry</option>
                  <option>Fee Structure</option>
                  <option>Academic Information</option>
                  <option>Boarding Facilities</option>
                  <option>Careers / Employment</option>
                  <option>General Enquiry</option>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label small fw-semibold">Your Message *</label>
                <textarea class="form-control-kw" rows="5" placeholder="Type your message here…" required></textarea>
              </div>
              <div class="col-12">
                <button type="submit" class="btn-kw-primary px-5 py-3">
                  <i class="bi bi-send-fill"></i>Send Message
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>

    </div>
  </div>
</section>

<!-- Map & Quick Info -->
<section class="section-sm section-alt">
  <div class="container">
    <div class="row g-4 align-items-center mb-4">
      <div class="col-lg-8">
        <h4 class="fw-bold mb-1">Find Us on the Map</h4>
        <p class="text-muted small mb-0">Kingsway Preparatory School is located along the Londiani–Kericho Road in Londiani Town, Kericho County.</p>
      </div>
      <div class="col-lg-4 text-lg-end">
        <a href="https://www.google.com/maps/search/Kingsway+Preparatory+School+Londiani" target="_blank" class="btn-kw-outline" style="font-size:.85rem">
          <i class="bi bi-map-fill"></i>Open in Google Maps
        </a>
      </div>
    </div>
    <div class="rounded-4 overflow-hidden shadow-sm reveal" style="height:360px;background:#e2e8f0;display:flex;align-items:center;justify-content:center">
      <div class="text-center text-muted">
        <i class="bi bi-map fs-1 d-block mb-3 text-success"></i>
        <p class="mb-2 fw-semibold">Kingsway Preparatory School</p>
        <p class="small">Londiani–Kericho Road, Londiani, Kenya</p>
        <a href="https://www.google.com/maps/search/Kingsway+Preparatory+School+Londiani" target="_blank" class="btn-kw-primary mt-2" style="padding:8px 20px;font-size:.85rem">
          <i class="bi bi-box-arrow-up-right"></i>View on Google Maps
        </a>
      </div>
    </div>
  </div>
</section>

<!-- Departmental Contacts -->
<section class="section">
  <div class="container">
    <div class="text-center mb-5 reveal">
      <div class="section-label justify-content-center"><span>Departments</span></div>
      <h2 class="section-title">Direct <span>Department Contacts</span></h2>
    </div>
    <div class="row g-4">
      <?php foreach ([
        ['bi-person-check-fill','#198754','Admissions Office','New applications, transfers, placement tests','admissions@kingswaypreparatoryschool.sc.ke','0720 113 030'],
        ['bi-cash-coin','#1976d2','Finance &amp; Fees','Fee structure, payments, balances, receipts','finance@kingswaypreparatoryschool.sc.ke','0720 113 031'],
        ['bi-book-fill','#9c27b0','Academic Office','Results, report cards, curriculum, timetables','academic@kingswaypreparatoryschool.sc.ke','0720 113 030'],
        ['bi-house-fill','#e65100','Boarding Office','Dormitory, exeats, welfare, health matters','boarding@kingswaypreparatoryschool.sc.ke','0720 113 031'],
      ] as $dept): ?>
      <div class="col-lg-3 col-md-6">
        <div class="text-center card-modern p-4 h-100 reveal">
          <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" style="width:64px;height:64px;background:<?= $dept[1] ?>22;">
            <i class="bi <?= $dept[0] ?> fs-3" style="color:<?= $dept[1] ?>"></i>
          </div>
          <h6 class="fw-bold mb-1"><?= $dept[2] ?></h6>
          <p class="text-muted small mb-3"><?= $dept[3] ?></p>
          <a href="mailto:<?= $dept[4] ?>" class="d-block text-success small mb-1 text-truncate"><?= $dept[4] ?></a>
          <a href="tel:<?= preg_replace('/\s/','',$dept[5]) ?>" class="d-block text-muted small"><?= $dept[5] ?></a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php include __DIR__ . '/public/layout/footer.php'; ?>
