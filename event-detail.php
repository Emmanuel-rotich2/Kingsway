<?php
$appBase    = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '')),'/');
if ($appBase === '.') $appBase = '';
require_once __DIR__ . '/public/layout/public_data.php';

/* ── Handle newsletter subscribe POST ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    if (!$email) { echo json_encode(['success'=>false,'message'=>'Please enter a valid email address.']); exit; }
    $r = kw_save_subscriber($email);
    $msg = $r === 'exists' ? 'You are already subscribed!' : 'Successfully subscribed to event alerts.';
    echo json_encode(['success'=>true,'message'=>$msg]);
    exit;
}

$id    = (int)($_GET['id'] ?? 0);
$event = $id ? kw_event_by_id($id) : null;
if (!$event) { header("Location: {$appBase}/events.php"); exit; }

$pageTitle  = htmlspecialchars($event['title']);
$activePage = 'events';
$related    = array_filter(kw_upcoming_events(6), fn($e) => $e['id'] != $id);
$related    = array_slice(array_values($related), 0, 3);

$typeColors = ['Academic'=>['#e3f2fd','#1565c0'],'Ceremony'=>['#fff8e1','#f57f17'],
               'Sports'=>['#e8f5e9','#2e7d32'],'Meeting'=>['#fce4ec','#b71c1c'],
               'Community'=>['#f3e5f5','#6a1b9a'],'Cultural'=>['#e0f2f1','#00695c']];
$tc      = $typeColors[$event['category'] ?? ''] ?? ['#f5f5f5','#333'];
$evDate  = new DateTime($event['event_date']);
$isPast  = $evDate < new DateTime('today');
$terms   = kw_academic_terms();
?>
<?php include __DIR__ . '/public/layout/header.php'; ?>

<div class="page-header">
  <div class="container position-relative" style="z-index:1">
    <nav aria-label="breadcrumb"><ol class="breadcrumb mb-2">
      <li class="breadcrumb-item"><a href="<?= $appBase ?>/index.php">Home</a></li>
      <li class="breadcrumb-item"><a href="<?= $appBase ?>/events.php">Events</a></li>
      <li class="breadcrumb-item active"><?= htmlspecialchars(mb_strimwidth($event['title'],0,40,'…')) ?></li>
    </ol></nav>
    <h1 class="page-title" style="font-size:clamp(1.4rem,3vw,2rem)"><?= htmlspecialchars($event['title']) ?></h1>
  </div>
</div>

<section class="section">
  <div class="container">
    <div class="row g-5">

      <!-- Event Detail -->
      <div class="col-lg-8">

        <!-- Date banner -->
        <div class="d-flex align-items-center gap-4 card-modern p-4 mb-4 reveal">
          <div class="d-flex flex-column align-items-center justify-content-center text-white rounded-3 flex-shrink-0"
               style="width:90px;height:90px;background:var(--green-dark)">
            <div style="font-size:2.2rem;font-weight:900;line-height:1"><?= $evDate->format('d') ?></div>
            <div style="font-size:.85rem;text-transform:uppercase"><?= $evDate->format('M') ?></div>
            <div style="font-size:.75rem;opacity:.8"><?= $evDate->format('Y') ?></div>
          </div>
          <div>
            <span class="event-type mb-2 d-inline-block" style="background:<?= $tc[0] ?>;color:<?= $tc[1] ?>">
              <?= htmlspecialchars($event['category']) ?>
            </span>
            <h2 class="fw-bold mb-2" style="font-size:1.4rem"><?= htmlspecialchars($event['title']) ?></h2>
            <div class="d-flex flex-wrap gap-3 text-muted small">
              <?php if (!empty($event['event_time'])): ?>
              <span><i class="bi bi-clock text-success me-1"></i><?= date('g:i A', strtotime($event['event_time'])) ?></span>
              <?php endif; ?>
              <?php if (!empty($event['end_date']) && $event['end_date'] !== $event['event_date']): ?>
              <span><i class="bi bi-calendar-range text-success me-1"></i>Until <?= date('d M Y',strtotime($event['end_date'])) ?></span>
              <?php endif; ?>
              <?php if (!empty($event['location'])): ?>
              <span><i class="bi bi-geo-alt text-success me-1"></i><?= htmlspecialchars($event['location']) ?></span>
              <?php endif; ?>
              <?php if ($isPast): ?>
              <span class="badge bg-secondary">Past Event</span>
              <?php else: ?>
              <span class="badge bg-success">Upcoming</span>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Description -->
        <?php if (!empty($event['description'])): ?>
        <div class="card-modern p-4 mb-4 reveal">
          <h5 class="fw-bold mb-3"><i class="bi bi-info-circle text-success me-2"></i>Event Details</h5>
          <div class="article-body">
            <p><?= nl2br(htmlspecialchars($event['description'])) ?></p>
          </div>
        </div>
        <?php endif; ?>

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
        <div class="d-flex align-items-center gap-3 flex-wrap mb-4">
          <span class="fw-semibold small">Share this event:</span>
          <a href="https://wa.me/?text=<?= urlencode($event['title'].' — '.date('d M Y',strtotime($event['event_date'])).(!empty($event['location'])?' at '.$event['location']:'')) ?>"
             target="_blank" class="btn btn-sm btn-outline-success rounded-pill px-3">
            <i class="bi bi-whatsapp me-1"></i>WhatsApp
          </a>
          <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode('https://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']) ?>"
             target="_blank" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
            <i class="bi bi-facebook me-1"></i>Facebook
          </a>
        </div>

        <a href="<?= $appBase ?>/events.php" class="btn-kw-outline">
          <i class="bi bi-arrow-left"></i>All Events
        </a>
      </div>

      <!-- Sidebar -->
      <div class="col-lg-4">

        <!-- Subscribe -->
        <?php if (!$isPast): ?>
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
        <?php endif; ?>

        <!-- Academic Calendar -->
        <div class="card-modern p-4 mb-4 reveal">
          <h6 class="fw-bold mb-3"><i class="bi bi-calendar2-week text-success me-2"></i>Academic Calendar</h6>
          <?php if (!empty($terms)): foreach ($terms as $t): ?>
          <div class="mb-3 pb-3 border-bottom">
            <div class="fw-semibold small"><?= htmlspecialchars($t['name'] ?? '') ?></div>
            <div class="text-muted" style="font-size:.8rem">
              <?= date('d M',strtotime($t['start_date'])) ?> — <?= date('d M Y',strtotime($t['end_date'])) ?>
            </div>
          </div>
          <?php endforeach; else: foreach ([['Term 1','Jan 20','Apr 4'],['Term 2','May 5','Aug 15'],['Term 3','Sep 1','Nov 28']] as $t): ?>
          <div class="mb-3 pb-3 border-bottom">
            <div class="fw-semibold small"><?= $t[0] ?></div>
            <div class="text-muted" style="font-size:.8rem"><?= $t[1] ?> — <?= $t[2] ?></div>
          </div>
          <?php endforeach; endif; ?>
        </div>

        <!-- Related Events -->
        <?php if (!empty($related)): ?>
        <div class="card-modern p-4 reveal">
          <h6 class="fw-bold mb-3"><i class="bi bi-calendar-event text-success me-2"></i>Upcoming Events</h6>
          <?php foreach ($related as $r):
            $rd = new DateTime($r['event_date']);
            $rtc = $typeColors[$r['category'] ?? ''] ?? ['#f5f5f5','#333'];
          ?>
          <a href="<?= $appBase ?>/event-detail.php?id=<?= $r['id'] ?>"
             class="d-flex align-items-center gap-3 mb-3 text-decoration-none text-dark">
            <div class="d-flex flex-column align-items-center justify-content-center text-white rounded-2 flex-shrink-0"
                 style="width:44px;height:44px;background:var(--green-dark);font-size:.75rem">
              <div class="fw-bold lh-1"><?= $rd->format('d') ?></div>
              <div><?= $rd->format('M') ?></div>
            </div>
            <div>
              <div class="small fw-semibold lh-sm"><?= htmlspecialchars(mb_strimwidth($r['title'],0,55,'…')) ?></div>
              <span class="d-inline-block mt-1" style="background:<?= $rtc[0] ?>;color:<?= $rtc[1] ?>;font-size:.7rem;font-weight:600;padding:1px 6px;border-radius:4px"><?= htmlspecialchars($r['category']) ?></span>
            </div>
          </a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

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
    const res = await fetch('', { method: 'POST', body: fd });
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
