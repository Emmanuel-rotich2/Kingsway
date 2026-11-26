<?php
$appBase    = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '')),'/');

$pageTitle  = 'Contact Us';
$activePage = 'contact';
$pageScript = 'contact';
// Contact info, social links, map URL and department cards are rendered by
// js/pages/public/contact.js via /api/website/{settings,departments}.
require_once __DIR__ . '/public/layout/public_data.php';
?>
<?php include __DIR__ . '/public/layout/header.php'; ?>

<div class="page-header">
  <div class="container position-relative" style="z-index:1">
    <nav aria-label="breadcrumb"><ol class="breadcrumb mb-2">
      <li class="breadcrumb-item"><a href="<?= $appBase ?>/">Home</a></li>
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
              <div class="ci-value">Kingsway Preparatory School<br id="ci-address"></div>
            </div>
          </div>
          <div class="contact-info-item">
            <div class="ci-icon"><i class="bi bi-envelope-fill"></i></div>
            <div>
              <div class="ci-label">Postal Address</div>
              <div class="ci-value" id="ci-postal"></div>
            </div>
          </div>
          <div class="contact-info-item">
            <div class="ci-icon"><i class="bi bi-telephone-fill"></i></div>
            <div>
              <div class="ci-label">Phone Numbers</div>
              <div class="ci-value" id="ci-phone"></div>
            </div>
          </div>
          <div class="contact-info-item">
            <div class="ci-icon"><i class="bi bi-at"></i></div>
            <div>
              <div class="ci-label">Email</div>
              <div class="ci-value" id="ci-email"></div>
            </div>
          </div>
          <div class="contact-info-item">
            <div class="ci-icon"><i class="bi bi-clock-fill"></i></div>
            <div>
              <div class="ci-label">Office Hours</div>
              <div class="ci-value" id="ci-hours"></div>
            </div>
          </div>

          <hr style="border-color:rgba(255,255,255,.2)" class="my-4">
          <div class="ci-label mb-3">Follow Us</div>
          <div class="social-links" id="ci-social"></div>
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
                <input type="text" name="cf_name" class="form-control-kw" placeholder="Your full name" required>
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-semibold">Phone Number</label>
                <input type="tel" name="cf_phone" class="form-control-kw" placeholder="+254 7XX XXX XXX">
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-semibold">Email Address *</label>
                <input type="email" name="cf_email" class="form-control-kw" placeholder="your@email.com" required>
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-semibold">Subject</label>
                <select name="cf_subject" class="form-control-kw">
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
                <textarea name="cf_message" class="form-control-kw" rows="5" placeholder="Type your message here…" required></textarea>
              </div>
              <div class="col-12" id="cfStatusMsg" style="display:none" class="small fw-semibold"></div>
              <div class="col-12">
                <button type="submit" id="cfSubmitBtn" class="btn-kw-primary px-5 py-3">
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
        <a href="https://www.google.com/maps" id="ci-maps-open" target="_blank" class="btn-kw-outline" style="font-size:.85rem">
          <i class="bi bi-map-fill"></i>Open in Google Maps
        </a>
      </div>
    </div>
    <div class="rounded-4 overflow-hidden shadow-sm reveal" style="height:360px;background:#e2e8f0;display:flex;align-items:center;justify-content:center">
      <div class="text-center text-muted">
        <i class="bi bi-map fs-1 d-block mb-3 text-success"></i>
        <p class="mb-2 fw-semibold" id="ci-school-name">Kingsway Preparatory School</p>
        <p class="small" id="ci-map-address"></p>
        <a href="https://www.google.com/maps" id="ci-maps-view" target="_blank" class="btn-kw-primary mt-2" style="padding:8px 20px;font-size:.85rem">
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
    <div class="row g-4" id="contact-departments">
    </div>
  </div>
</section>

<script nonce="<?= csp_nonce() ?>">
document.getElementById('contactForm')?.addEventListener('submit', async function(e) {
  e.preventDefault();
  const btn = document.getElementById('cfSubmitBtn');
  const msg = document.getElementById('cfStatusMsg');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Sending…';
  const fd = new FormData(this);
  try {
    const res = await fetch('<?= $appBase ?>/api/public/inquiries', { method:'POST', body:fd });
    const json = await res.json();
    if (json.success) {
      msg.style.display = 'block';
      msg.style.color = '#198754';
      msg.textContent = 'Message sent! We will respond within 24 hours.';
      this.reset();
    } else {
      msg.style.display = 'block';
      msg.style.color = '#dc3545';
      msg.textContent = json.message || 'Failed. Please try again.';
    }
  } catch {
    msg.style.display = 'block';
    msg.style.color = '#dc3545';
    msg.textContent = 'Network error. Please email us at info@kingswaypreparatoryschool.sc.ke';
  }
  btn.disabled = false;
  btn.innerHTML = '<i class="bi bi-send-fill"></i>Send Message';
});
</script>

<?php include __DIR__ . '/public/layout/footer.php'; ?>