<?php
$appBase    = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '')),'/');
if ($appBase === '.') $appBase = '';
$pageTitle  = 'Contact Us';
$activePage = 'contact';
require_once __DIR__ . '/public/layout/public_data.php';

/* ── Handle form POST ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $name    = trim($_POST['cf_name'] ?? '');
    $email   = filter_var(trim($_POST['cf_email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $phone   = trim($_POST['cf_phone'] ?? '');
    $subject = trim($_POST['cf_subject'] ?? '');
    $message = trim($_POST['cf_message'] ?? '');

    if (!$name || !$email || !$message) {
        echo json_encode(['success'=>false,'message'=>'Please fill in your name, email and message.']); exit;
    }

    $ok = kw_save_contact([
        'name'=>$name, 'email'=>$email, 'phone'=>$phone,
        'subject'=>$subject, 'message'=>$message,
        'ip'=>$_SERVER['REMOTE_ADDR'] ?? null
    ]);

    echo json_encode(['success'=>$ok, 'message'=>$ok
        ? 'Thank you for your message! We will respond within 24 hours on working days.'
        : 'Submission failed. Please try again or email us directly.']);
    exit;
}

$address    = kw_school_stat('school_address_physical', 'Londiani – Kericho Road, Londiani Town, Kenya');
$postal     = kw_school_stat('school_address_postal',   'P.O BOX 203-20203, Londiani, Kericho County');
$phoneMain  = kw_school_stat('school_phone_main',       '+254 720 113 030');
$phoneAlt   = kw_school_stat('school_phone_alt',        '+254 720 113 031');
$emailMain  = kw_school_stat('school_email_main',       'info@kingswaypreparatoryschool.sc.ke');
$hoursWkd   = kw_school_stat('office_hours_weekday',    'Monday – Friday: 7:30 AM – 5:00 PM');
$hoursSat   = kw_school_stat('office_hours_saturday',   'Saturday: 9:00 AM – 1:00 PM');
$fbUrl      = kw_school_stat('social_facebook',         'https://www.facebook.com/kingswayprepschool');
$twUrl      = kw_school_stat('social_twitter',          'https://twitter.com/kingswayprepschool');
$igUrl      = kw_school_stat('social_instagram',        'https://www.instagram.com/kingswayprepschool');
$waNum      = kw_school_stat('social_whatsapp',         '254720113030');
$ytUrl      = kw_school_stat('social_youtube',          'https://www.youtube.com/@kingswayprepschool');
$mapsUrl    = kw_school_stat('google_maps_url',         'https://www.google.com/maps/search/Kingsway+Preparatory+School+Londiani');
$departments = kw_departments();
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
              <div class="ci-value">Kingsway Preparatory School<br><?= nl2br(htmlspecialchars($address)) ?></div>
            </div>
          </div>
          <div class="contact-info-item">
            <div class="ci-icon"><i class="bi bi-envelope-fill"></i></div>
            <div>
              <div class="ci-label">Postal Address</div>
              <div class="ci-value"><?= nl2br(htmlspecialchars($postal)) ?></div>
            </div>
          </div>
          <div class="contact-info-item">
            <div class="ci-icon"><i class="bi bi-telephone-fill"></i></div>
            <div>
              <div class="ci-label">Phone Numbers</div>
              <div class="ci-value">
                <a href="tel:<?= preg_replace('/\s+/','',$phoneMain) ?>" class="ci-value"><?= htmlspecialchars($phoneMain) ?></a>
                <?php if ($phoneAlt): ?><br><a href="tel:<?= preg_replace('/\s+/','',$phoneAlt) ?>" class="ci-value"><?= htmlspecialchars($phoneAlt) ?></a><?php endif; ?>
              </div>
            </div>
          </div>
          <div class="contact-info-item">
            <div class="ci-icon"><i class="bi bi-at"></i></div>
            <div>
              <div class="ci-label">Email</div>
              <div class="ci-value"><a href="mailto:<?= htmlspecialchars($emailMain) ?>" class="ci-value"><?= htmlspecialchars($emailMain) ?></a></div>
            </div>
          </div>
          <div class="contact-info-item">
            <div class="ci-icon"><i class="bi bi-clock-fill"></i></div>
            <div>
              <div class="ci-label">Office Hours</div>
              <div class="ci-value"><?= htmlspecialchars($hoursWkd) ?><?php if ($hoursSat): ?><br><?= htmlspecialchars($hoursSat) ?><?php endif; ?></div>
            </div>
          </div>

          <hr style="border-color:rgba(255,255,255,.2)" class="my-4">
          <div class="ci-label mb-3">Follow Us</div>
          <div class="social-links">
            <?php if ($fbUrl): ?><a href="<?= htmlspecialchars($fbUrl) ?>" aria-label="Facebook" target="_blank"><i class="bi bi-facebook"></i></a><?php endif; ?>
            <?php if ($twUrl): ?><a href="<?= htmlspecialchars($twUrl) ?>" aria-label="Twitter" target="_blank"><i class="bi bi-twitter-x"></i></a><?php endif; ?>
            <?php if ($igUrl): ?><a href="<?= htmlspecialchars($igUrl) ?>" aria-label="Instagram" target="_blank"><i class="bi bi-instagram"></i></a><?php endif; ?>
            <?php if ($waNum): ?><a href="https://wa.me/<?= htmlspecialchars($waNum) ?>" aria-label="WhatsApp" target="_blank"><i class="bi bi-whatsapp"></i></a><?php endif; ?>
            <?php if ($ytUrl): ?><a href="<?= htmlspecialchars($ytUrl) ?>" aria-label="YouTube" target="_blank"><i class="bi bi-youtube"></i></a><?php endif; ?>
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
        <a href="<?= htmlspecialchars($mapsUrl) ?>" target="_blank" class="btn-kw-outline" style="font-size:.85rem">
          <i class="bi bi-map-fill"></i>Open in Google Maps
        </a>
      </div>
    </div>
    <div class="rounded-4 overflow-hidden shadow-sm reveal" style="height:360px;background:#e2e8f0;display:flex;align-items:center;justify-content:center">
      <div class="text-center text-muted">
        <i class="bi bi-map fs-1 d-block mb-3 text-success"></i>
        <p class="mb-2 fw-semibold"><?= htmlspecialchars(kw_school_stat('school_name','Kingsway Preparatory School')) ?></p>
        <p class="small"><?= htmlspecialchars($address) ?></p>
        <a href="<?= htmlspecialchars($mapsUrl) ?>" target="_blank" class="btn-kw-primary mt-2" style="padding:8px 20px;font-size:.85rem">
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
      <?php foreach ($departments as $dept): ?>
      <div class="col-lg-3 col-md-6">
        <div class="text-center card-modern p-4 h-100 reveal">
          <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" style="width:64px;height:64px;background:<?= htmlspecialchars($dept['color']) ?>22;">
            <i class="bi <?= htmlspecialchars($dept['icon']) ?> fs-3" style="color:<?= htmlspecialchars($dept['color']) ?>"></i>
          </div>
          <h6 class="fw-bold mb-1"><?= htmlspecialchars($dept['name']) ?></h6>
          <p class="text-muted small mb-3"><?= htmlspecialchars($dept['description']) ?></p>
          <?php if (!empty($dept['email'])): ?>
          <a href="mailto:<?= htmlspecialchars($dept['email']) ?>" class="d-block text-success small mb-1 text-truncate"><?= htmlspecialchars($dept['email']) ?></a>
          <?php endif; ?>
          <?php if (!empty($dept['phone'])): ?>
          <a href="tel:<?= preg_replace('/\s/','',$dept['phone']) ?>" class="d-block text-muted small"><?= htmlspecialchars($dept['phone']) ?></a>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<script>
document.getElementById('contactForm')?.addEventListener('submit', async function(e) {
  e.preventDefault();
  const btn = document.getElementById('cfSubmitBtn');
  const msg = document.getElementById('cfStatusMsg');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Sending…';
  const fd = new FormData(this);
  try {
    const res = await fetch('<?= $appBase ?>/contact.php', { method:'POST', body:fd });
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
    msg.textContent = 'Network error. Please email us at <?= htmlspecialchars($emailMain) ?>';
  }
  btn.disabled = false;
  btn.innerHTML = '<i class="bi bi-send-fill"></i>Send Message';
});
</script>

<?php include __DIR__ . '/public/layout/footer.php'; ?>