<?php
$appBase    = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '')),'/');
if ($appBase === '.') $appBase = '';
$pageTitle  = 'Event Details';
$activePage = 'events';
$pageScript = 'event-detail';
// Event detail, related events, share links and the academic-terms sidebar are
// rendered by js/pages/public/event-detail.js via GET /api/website/events/<id>
// (plus the events list + terms). A missing/invalid id is redirected to
// events.php client-side.
require_once __DIR__ . '/public/layout/public_data.php';
?>
<?php include __DIR__ . '/public/layout/header.php'; ?>

<div class="page-header">
  <div class="container position-relative" style="z-index:1">
    <nav aria-label="breadcrumb"><ol class="breadcrumb mb-2">
      <li class="breadcrumb-item"><a href="<?= $appBase ?>/index.php">Home</a></li>
      <li class="breadcrumb-item"><a href="<?= $appBase ?>/index.php?route=r78213fd42e2d">Events</a></li>
      <li class="breadcrumb-item active" id="ed-crumb">Event</li>
    </ol></nav>
    <h1 class="page-title" style="font-size:clamp(1.4rem,3vw,2rem)" id="ed-title">Loading&hellip;</h1>
  </div>
</div>

<section class="section">
  <div class="container">
    <div class="row g-5">

      <!-- Event Detail -->
      <div class="col-lg-8">

        <!-- Date banner -->
        <div class="d-flex align-items-center gap-4 card-modern p-4 mb-4 reveal" id="ed-banner">
        </div>

        <!-- Description -->
        <div class="card-modern p-4 mb-4 reveal" id="ed-description-card">
          <h5 class="fw-bold mb-3"><i class="bi bi-info-circle text-success me-2"></i>Event Details</h5>
          <div class="article-body" id="ed-description"></div>
        </div>

        <!-- What to bring / notes -->
        <div class="card-modern p-4 mb-4 reveal" style="background:linear-gradient(135deg,#e8f5e9,#f1f8f4)">
          <h5 class="fw-bold mb-3 text-success"><i class="bi bi-check2-circle me-2"></i>Important Notes</h5>
          <ul class="list-unstyled mb-0">
            <?php $notes = [
              ['bi-clock-fill','Arrive at least 10 minutes early to find your seat.'],
              ['bi-telephone-fill','For enquiries, call us on 0720 113 030 during office hours.'],
              ['bi-envelope-fill','Email any questions to info@kingswaypreparatoryschool.sc.ke'],
              ['bi-people-fill','All parents and guardians are welcome. Please bring your school ID card.'],
            ]; foreach ($notes as $n): ?>
            <li class="d-flex align-items-start gap-2 mb-2 small">
              <i class="bi <?= $n[0] ?> text-success mt-1 flex-shrink-0"></i>
              <span><?= $n[1] ?></span>
            </li>
            <?php endforeach; ?>
          </ul>
        </div>

        <!-- Share -->
        <div class="d-flex align-items-center gap-3 flex-wrap mb-4" id="ed-share">
        </div>

        <a href="<?= $appBase ?>/index.php?route=r78213fd42e2d" class="btn-kw-outline">
          <i class="bi bi-arrow-left"></i>All Events
        </a>
      </div>

      <!-- Sidebar -->
      <div class="col-lg-4">

        <!-- Subscribe -->
        <div id="ed-subscribe-box">
        <div class="rounded-4 p-4 mb-4 reveal" style="background:linear-gradient(135deg,var(--green-dark),var(--green))">
          <h6 class="fw-bold text-white mb-1"><i class="bi bi-bell-fill text-warning me-2"></i>Get Event Alerts</h6>
          <p class="small mb-3" style="color:rgba(255,255,255,.8)">Subscribe to receive event reminders and school updates via email.</p>
          <form id="subscribeForm">
            <input type="email" name="email" class="form-control-kw mb-2" placeholder="Your email address" required>
            <button type="submit" class="btn-kw-gold w-100 justify-content-center" style="font-size:.85rem" id="subscribeBtn">
              <i class="bi bi-bell"></i>Subscribe
            </button>
          </form>
          <div id="subscribeMsg" class="mt-2 small fw-semibold text-center" style="display:none"></div>
        </div>
        </div>

        <!-- Academic Calendar -->
        <div class="card-modern p-4 mb-4 reveal">
          <h6 class="fw-bold mb-3"><i class="bi bi-calendar2-week text-success me-2"></i>Academic Calendar</h6>
          <div id="ed-terms"></div>
        </div>

        <!-- Related Events -->
        <div class="card-modern p-4 reveal">
          <h6 class="fw-bold mb-3"><i class="bi bi-calendar-event text-success me-2"></i>Upcoming Events</h6>
          <div id="ed-related"></div>
        </div>

      </div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/public/layout/footer.php'; ?>
<script>
document.getElementById('subscribeForm')?.addEventListener('submit', async function(e) {
  e.preventDefault();
  const btn = document.getElementById('subscribeBtn');
  const msg = document.getElementById('subscribeMsg');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Subscribing…';
  try {
    const fd = new FormData(this);
    const res = await fetch('<?= $appBase ?>/api/public/subscribers', { method: 'POST', body: fd });
    const json = await res.json();
    if (json.success) {
      this.style.display = 'none';
      msg.style.display = 'block';
      msg.style.color = '#fff';
      msg.textContent = '✅ ' + json.message;
    } else {
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-bell"></i>Subscribe';
      msg.style.display = 'block';
      msg.style.color = '#ffe082';
      msg.textContent = json.message;
    }
  } catch { btn.disabled = false; btn.innerHTML = '<i class="bi bi-bell"></i>Subscribe'; }
});
</script>
