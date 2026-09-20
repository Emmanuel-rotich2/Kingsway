<?php
$appBase    = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '')),'/');
if ($appBase === '.') $appBase = '';
$pageTitle  = 'Events & Calendar';
$activePage = 'events';
$pageScript = 'events';
// Upcoming events, the academic-terms sidebar and the event-type legend are
// rendered by js/pages/public/events.js via /api/website/{events,terms}.
require_once __DIR__ . '/public/layout/public_data.php';
?>
<?php include __DIR__ . '/public/layout/header.php'; ?>

<div class="page-header">
  <div class="container position-relative" style="z-index:1">
    <nav aria-label="breadcrumb"><ol class="breadcrumb mb-2">
      <li class="breadcrumb-item"><a href="<?= $appBase ?>/index.php">Home</a></li>
      <li class="breadcrumb-item active">Events &amp; Calendar</li>
    </ol></nav>
    <h1 class="page-title">Events &amp; School Calendar</h1>
    <p class="mt-2" style="color:rgba(255,255,255,.7)">Stay up to date with everything happening at Kingsway</p>
  </div>
</div>

<section class="section" style="background:#f5f7fa">
  <div class="container">
    <div class="row g-5">

      <!-- Upcoming Events list -->
      <div class="col-lg-8">
        <div class="section-label mb-2"><span>Events & Calendar</span></div>
        <h2 class="section-title mb-4">School <span>Events</span></h2>

        <div id="events-list"></div>
      </div>

      <!-- Sidebar: Academic Calendar + Subscribe -->
      <div class="col-lg-4">

        <!-- Academic Terms -->
        <div class="card-modern p-4 mb-5">
          <h5 class="fw-bold mb-3"><i class="bi bi-calendar2-week text-success me-2"></i>Academic Calendar <?= date('Y') ?></h5>
          <div id="events-terms"></div>
          <a href="<?= $appBase ?>/index.php?route=r0157d23dd5ef" class="btn-kw-outline w-100 justify-content-center mt-2" style="font-size:.82rem;padding:8px" download>
            <i class="bi bi-download"></i>Download Academic Calendar (PDF)
          </a>
        </div>

        <!-- Event Categories -->
        <div class="card-modern p-4 mb-5">
          <h5 class="fw-bold mb-3"><i class="bi bi-tags text-success me-2"></i>Event Categories</h5>
          <div id="events-categories"></div>
        </div>

        <!-- Newsletter subscribe -->
        <div class="rounded-4 p-4" style="background:linear-gradient(135deg,var(--green-dark),var(--green))">
          <h5 class="fw-bold text-white mb-2"><i class="bi bi-bell-fill text-warning me-2"></i>Get Event Alerts</h5>
          <p class="small mb-3" style="color:rgba(255,255,255,.8)">Subscribe to receive school event reminders via email.</p>
          <form id="subscribeForm">
            <input type="email" name="email" class="form-control-kw mb-2" placeholder="Your email address" required>
            <div id="subMsg" class="small fw-semibold mb-2" style="display:none"></div>
            <button type="submit" id="subBtn" class="btn-kw-gold w-100 justify-content-center" style="font-size:.85rem">
              <i class="bi bi-bell"></i>Subscribe
            </button>
          </form>
        </div>

      </div>
    </div>
  </div>
</section>

<script>
document.getElementById('subscribeForm')?.addEventListener('submit', async function(e) {
  e.preventDefault();
  const btn = document.getElementById('subBtn');
  const msg = document.getElementById('subMsg');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Subscribing…';
  const fd = new FormData(this);
  try {
    const res = await fetch('<?= $appBase ?>/api/public/subscribers', { method:'POST', body:fd });
    const json = await res.json();
    if (json.success) {
      this.style.display = 'none';
      msg.style.display = 'block';
      msg.style.color = '#fff';
      msg.textContent = 'Subscribed!';
    } else {
      msg.style.display = 'block';
      msg.style.color = '#ffe082';
      msg.textContent = json.message;
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-bell"></i>Subscribe';
    }
  } catch {
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-bell"></i>Subscribe';
  }
});
</script>

<?php include __DIR__ . '/public/layout/footer.php'; ?>