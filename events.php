<?php
$appBase    = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '')),'/');
if ($appBase === '.') $appBase = '';
$pageTitle  = 'Events & Calendar';
$activePage = 'events';
require_once __DIR__ . '/public/layout/public_data.php';
$events = kw_upcoming_events(10);
$terms  = kw_academic_terms();
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

<section class="section">
  <div class="container">
    <div class="row g-5">

      <!-- Upcoming Events list -->
      <div class="col-lg-8">
        <div class="section-label mb-2 reveal"><span>What's On</span></div>
        <h2 class="section-title mb-4 reveal">Upcoming <span>Events</span></h2>

        <?php
        $typeColors = ['Academic'=>['#e3f2fd','#1565c0'],'Ceremony'=>['#fff8e1','#f57f17'],'Sports'=>['#e8f5e9','#2e7d32'],'Meeting'=>['#fce4ec','#b71c1c'],'Community'=>['#f3e5f5','#6a1b9a'],'Cultural'=>['#e0f2f1','#00695c']];
        foreach ($events as $ev):
          $evDate = new DateTime($ev['event_date']);
          $tc = $typeColors[$ev['category'] ?? ''] ?? ['#f5f5f5','#333'];
          $isPast = $evDate < new DateTime('today');
        ?>
        <div class="card-modern mb-4 reveal <?= $isPast?'opacity-50':'' ?>">
          <div class="row g-0">
            <div class="col-auto d-flex">
              <div class="d-flex flex-column align-items-center justify-content-center px-4 text-white rounded-start-4" style="min-width:90px;background:var(--green-dark)">
                <div style="font-size:2rem;font-weight:900;line-height:1"><?= $evDate->format('d') ?></div>
                <div style="font-size:.8rem;text-transform:uppercase;letter-spacing:.5px;opacity:.9"><?= $evDate->format('M') ?></div>
                <div style="font-size:.75rem;opacity:.75"><?= $evDate->format('Y') ?></div>
              </div>
            </div>
            <div class="col p-4">
              <div class="d-flex align-items-start justify-content-between flex-wrap gap-2">
                <div>
                  <span class="event-type mb-2" style="background:<?= $tc[0] ?>;color:<?= $tc[1] ?>">
                    <?= htmlspecialchars($ev['category'] ?? 'Event') ?>
                  </span>
                  <h5 class="fw-bold mb-2"><?= htmlspecialchars($ev['title']) ?></h5>
                  <p class="text-muted small mb-2"><?= htmlspecialchars($ev['description'] ?? '') ?></p>
                  <div class="event-meta">
                    <?php if (!empty($ev['event_time'])): ?>
                    <span><i class="bi bi-clock text-success"></i><?= date('g:i A',strtotime($ev['event_time'])) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($ev['location'])): ?>
                    <span><i class="bi bi-geo-alt text-success"></i><?= htmlspecialchars($ev['location']) ?></span>
                    <?php endif; ?>
                  </div>
                </div>
                <?php if (!$isPast): ?>
                <span class="tag bg-success bg-opacity-10 text-success">Upcoming</span>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Sidebar: Academic Calendar + Subscribe -->
      <div class="col-lg-4">

        <!-- Academic Terms -->
        <div class="card-modern p-4 mb-4 reveal">
          <h5 class="fw-bold mb-3"><i class="bi bi-calendar2-week text-success me-2"></i>Academic Calendar <?= date('Y') ?></h5>
          <?php if (!empty($terms)): foreach ($terms as $t): ?>
          <div class="mb-3 pb-3 border-bottom">
            <div class="fw-semibold small"><?= htmlspecialchars($t['name'] ?? 'Term '.($t['term_number']??'')) ?></div>
            <div class="text-muted" style="font-size:.8rem">
              <?= date('d M', strtotime($t['start_date'])) ?> — <?= date('d M Y', strtotime($t['end_date'])) ?>
            </div>
          </div>
          <?php endforeach; else: ?>
          <?php foreach ([['Term 1','Jan 20','Apr 4'],['Term 2','May 5','Aug 15'],['Term 3','Sep 1','Nov 28']] as $t): ?>
          <div class="mb-3 pb-3 border-bottom">
            <div class="fw-semibold small"><?= $t[0] ?> <?= date('Y') ?></div>
            <div class="text-muted" style="font-size:.8rem"><?= $t[1] ?> — <?= $t[2] ?> <?= date('Y') ?></div>
          </div>
          <?php endforeach; endif; ?>
          <a href="<?= $appBase ?>/downloads.php" class="btn-kw-outline w-100 justify-content-center mt-2" style="font-size:.82rem;padding:8px">
            <i class="bi bi-download"></i>Download Calendar PDF
          </a>
        </div>

        <!-- Event Categories -->
        <div class="card-modern p-4 mb-4 reveal">
          <h5 class="fw-bold mb-3"><i class="bi bi-tags text-success me-2"></i>Event Categories</h5>
          <?php foreach ($typeColors as $cat => [$bg,$col]): ?>
          <div class="d-flex align-items-center justify-content-between py-2 border-bottom">
            <span class="d-flex align-items-center gap-2">
              <span class="rounded-2 px-2 py-1" style="background:<?= $bg ?>;color:<?= $col ?>;font-size:.75rem;font-weight:600"><?= $cat ?></span>
            </span>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- Newsletter subscribe -->
        <div class="rounded-4 p-4 reveal" style="background:linear-gradient(135deg,var(--green-dark),var(--green))">
          <h5 class="fw-bold text-white mb-2"><i class="bi bi-bell-fill text-warning me-2"></i>Get Event Alerts</h5>
          <p class="small mb-3" style="color:rgba(255,255,255,.8)">Subscribe to receive school event reminders via email.</p>
          <form onsubmit="event.preventDefault();this.innerHTML='<p class=\'text-white text-center\'>✅ Subscribed!</p>'">
            <input type="email" class="form-control-kw mb-2" placeholder="Your email address" required>
            <button type="submit" class="btn-kw-gold w-100 justify-content-center" style="font-size:.85rem">
              <i class="bi bi-bell"></i>Subscribe
            </button>
          </form>
        </div>

      </div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/public/layout/footer.php'; ?>
