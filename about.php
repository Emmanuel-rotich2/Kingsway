<?php
$appBase    = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '')),'/');
if ($appBase === '.') $appBase = '';
$pageTitle  = 'About Us';
$activePage = 'about';
require_once __DIR__ . '/public/layout/public_data.php';

$mission    = kw_content('mission',  'To provide a nurturing, inclusive, and academically rigorous environment that develops confident, virtuous, and globally-competitive learners through the Kenya Competency-Based Curriculum.');
$vision     = kw_content('vision',   'To be the most preferred school of excellence in the East African region, producing well-rounded, morally upright, and intellectually superior graduates.');
$motto      = kw_school_stat('school_motto', 'In God We Soar');
$founded    = kw_school_stat('school_founded_year', '2005');
$values     = kw_school_values();
$history    = kw_school_history();
$leadership = kw_leadership();
$programs   = kw_programs();
$facilities = kw_facilities();
?>
<?php include __DIR__ . '/public/layout/header.php'; ?>

<div class="page-header">
  <div class="container position-relative" style="z-index:1">
    <nav aria-label="breadcrumb"><ol class="breadcrumb mb-2">
      <li class="breadcrumb-item"><a href="<?= $appBase ?>/index.php">Home</a></li>
      <li class="breadcrumb-item active">About Us</li>
    </ol></nav>
    <h1 class="page-title">About Kingsway</h1>
    <p class="mt-2" style="color:rgba(255,255,255,.7);font-size:1rem">Excellence, Character &amp; Leadership since <?= htmlspecialchars($founded) ?></p>
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
          <p class="mb-0"><?= htmlspecialchars($mission) ?></p>
        </div>
        <div class="p-4 rounded-4 mb-4" style="background:linear-gradient(135deg,#fff8e1,#fffde7);border-left:4px solid var(--gold)">
          <h5 class="fw-bold mb-2" style="color:var(--gold-dark)"><i class="bi bi-eye-fill me-2"></i>Vision</h5>
          <p class="mb-0"><?= htmlspecialchars($vision) ?></p>
        </div>
        <div class="p-4 rounded-4" style="background:linear-gradient(135deg,#e8eaf6,#ede7f6);border-left:4px solid #7c4dff">
          <h5 class="fw-bold mb-2" style="color:#512da8"><i class="bi bi-gem me-2"></i>Motto</h5>
          <p class="mb-0 fst-italic fw-semibold fs-5">"<?= htmlspecialchars($motto) ?>"</p>
        </div>
      </div>
      <div class="col-lg-6 reveal reveal-right">
        <h4 class="fw-bold mb-4 text-success">Our Core Values</h4>
        <div class="row g-3">
          <?php foreach ($values as $v): ?>
          <div class="col-6">
            <div class="d-flex align-items-start gap-3 p-3 bg-white border rounded-3">
              <i class="bi <?= htmlspecialchars($v['icon']) ?> fs-4 flex-shrink-0" style="color:<?= htmlspecialchars($v['color']) ?>"></i>
              <div>
                <div class="fw-semibold small"><?= htmlspecialchars($v['name']) ?></div>
                <div class="text-muted" style="font-size:.75rem"><?= htmlspecialchars($v['description']) ?></div>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
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
          <?php foreach ($history as $h): ?>
          <div class="mb-5 position-relative reveal">
            <div class="position-absolute" style="width:14px;height:14px;background:var(--green);border-radius:50%;left:-40px;top:5px;border:3px solid #fff;box-shadow:0 0 0 3px var(--green)"></div>
            <span class="badge bg-success mb-2"><?= htmlspecialchars($h['year']) ?></span>
            <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars($h['event_title']) ?></h5>
            <p class="text-muted mb-0"><?= htmlspecialchars($h['description']) ?></p>
          </div>
          <?php endforeach; ?>
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
    <div class="row g-4 justify-content-center">
      <?php foreach ($leadership as $l):
        $initials = strtoupper(substr(explode(' ', $l['name'])[count(explode(' ', $l['name']))-1] ?? $l['name'], 0, 1));
        $avatarBg = htmlspecialchars($l['avatar_color'] ?? '#198754');
      ?>
      <div class="col-lg-2 col-md-4 col-6">
        <div class="text-center card-modern p-3 h-100 reveal">
          <?php if (!empty($l['avatar_url'])): ?>
          <img src="<?= htmlspecialchars($l['avatar_url']) ?>" alt="<?= htmlspecialchars($l['name']) ?>"
               class="rounded-circle mx-auto d-block mb-3 object-fit-cover" style="width:72px;height:72px;">
          <?php else: ?>
          <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3 text-white fs-3 fw-bold" style="width:72px;height:72px;background:<?= $avatarBg ?>">
            <?= $initials ?>
          </div>
          <?php endif; ?>
          <div class="fw-bold small"><?= htmlspecialchars($l['name']) ?></div>
          <div class="text-success" style="font-size:.75rem;font-weight:600"><?= htmlspecialchars($l['title']) ?></div>
          <?php if (!empty($l['bio'])): ?>
          <p class="text-muted mt-2 mb-0" style="font-size:.75rem"><?= htmlspecialchars($l['bio']) ?></p>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
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
    <div class="row g-4">
      <?php foreach ($programs as $p): ?>
      <div class="col-lg-4 col-md-6 reveal">
        <div class="program-card h-100">
          <div class="program-icon" style="background:<?= htmlspecialchars($p['color']) ?>22">
            <i class="bi <?= htmlspecialchars($p['icon']) ?> fs-2" style="color:<?= htmlspecialchars($p['color']) ?>"></i>
          </div>
          <h5><?= htmlspecialchars($p['name']) ?></h5>
          <?php if (!empty($p['level_range'])): ?>
          <div class="tag mb-2" style="background:<?= htmlspecialchars($p['color']) ?>22;color:<?= htmlspecialchars($p['color']) ?>"><?= htmlspecialchars($p['level_range']) ?></div>
          <?php endif; ?>
          <p><?= htmlspecialchars($p['description']) ?></p>
        </div>
      </div>
      <?php endforeach; ?>
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
    <div class="row g-3">
      <?php foreach ($facilities as $f): ?>
      <div class="col-lg-4 col-md-6 reveal">
        <div class="d-flex align-items-start gap-3 p-4 bg-white border rounded-3 h-100">
          <div class="bg-success bg-opacity-10 rounded-2 p-2 flex-shrink-0">
            <i class="bi <?= htmlspecialchars($f['icon']) ?> text-success fs-4"></i>
          </div>
          <div>
            <div class="fw-bold small mb-1"><?= htmlspecialchars($f['name']) ?></div>
            <div class="text-muted" style="font-size:.82rem"><?= htmlspecialchars($f['description']) ?></div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
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
        <a href="<?= $appBase ?>/admissions.php#apply" class="btn-kw-gold">
          <i class="bi bi-pencil-square"></i>Apply for Admission
        </a>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/public/layout/footer.php'; ?>
