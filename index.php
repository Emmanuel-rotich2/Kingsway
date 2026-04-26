<?php
$appBase    = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '')),'/');
if ($appBase === '.') $appBase = '';
$pageTitle  = 'Home';
$activePage = 'home';
require_once __DIR__ . '/public/layout/public_data.php';
$news   = kw_latest_news(3);
$events = kw_upcoming_events(4);
?>
<?php include __DIR__ . '/public/layout/header.php'; ?>

<!-- ═══ ANNOUNCEMENT TICKER ══════════════════════════════════════════════════ -->
<div class="ticker-bar d-flex align-items-center gap-3 px-3">
  <span class="ticker-label"><i class="bi bi-megaphone-fill me-1"></i>News</span>
  <div class="overflow-hidden flex-grow-1">
    <div class="ticker-track">
      <?php foreach ($news as $n): ?>
        <span><a href="<?= $appBase ?>/news.php"><?= htmlspecialchars($n['title']) ?></a></span>
      <?php endforeach; ?>
      <?php foreach ($news as $n): /* duplicate for seamless loop */ ?>
        <span><a href="<?= $appBase ?>/news.php"><?= htmlspecialchars($n['title']) ?></a></span>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- ═══ HERO ══════════════════════════════════════════════════════════════════ -->
<section class="hero">
  <div class="hero-bg-img"></div>
  <div class="hero-particles">
    <span></span><span></span><span></span><span></span><span></span>
    <span></span><span></span><span></span><span></span><span></span>
  </div>
  <div class="container hero-content">
    <div class="row align-items-center g-5">
      <div class="col-lg-7">
        <div class="hero-badge"><i class="bi bi-patch-check-fill"></i>CBC-Aligned Curriculum</div>
        <h1 class="hero-title">
          Where Every Child<br>
          <span class="highlight">Soars to Excellence</span>
        </h1>
        <p class="hero-subtitle">
          Kingsway Preparatory School provides world-class education combining the Kenya
          Competency-Based Curriculum with holistic character development, sports, and
          co-curricular excellence — in the heart of Londiani, Kenya.
        </p>
        <div class="hero-actions">
          <a href="<?= $appBase ?>/admissions.php" class="btn-kw-gold">
            <i class="bi bi-pencil-square"></i>Apply for Admission
          </a>
          <a href="<?= $appBase ?>/about.php" class="btn-kw-outline" style="color:#fff;border-color:rgba(255,255,255,.5);">
            <i class="bi bi-play-circle"></i>Discover Our School
          </a>
        </div>
      </div>
      <div class="col-lg-5 d-none d-lg-block">
        <div class="hero-card">
          <div class="hero-card-title">School at a Glance</div>
          <div class="hero-stat">
            <div class="hero-stat-icon"><i class="bi bi-people-fill"></i></div>
            <div class="hero-stat-text"><strong>1,200+</strong><span>Students Enrolled</span></div>
          </div>
          <div class="hero-stat">
            <div class="hero-stat-icon"><i class="bi bi-mortarboard-fill"></i></div>
            <div class="hero-stat-text"><strong>98%</strong><span>KJSEA / KCPE Pass Rate</span></div>
          </div>
          <div class="hero-stat">
            <div class="hero-stat-icon"><i class="bi bi-award-fill"></i></div>
            <div class="hero-stat-text"><strong>30+</strong><span>Regional Awards</span></div>
          </div>
          <div class="hero-stat">
            <div class="hero-stat-icon"><i class="bi bi-calendar2-check"></i></div>
            <div class="hero-stat-text"><strong>Est. 2005</strong><span>Years of Excellence</span></div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="scroll-indicator"><span></span>Scroll down</div>
</section>

<!-- ═══ STATS ═════════════════════════════════════════════════════════════════ -->
<section class="section-sm bg-white">
  <div class="container">
    <div class="row g-4">
      <?php
      $stats = [
        ['icon'=>'bi-people-fill',      'target'=>1200, 'suffix'=>'+', 'label'=>'Students Enrolled',    'color'=>'#198754'],
        ['icon'=>'bi-person-workspace', 'target'=>80,   'suffix'=>'+', 'label'=>'Qualified Teachers',   'color'=>'#0d4f2a'],
        ['icon'=>'bi-trophy-fill',      'target'=>98,   'suffix'=>'%', 'label'=>'Exam Pass Rate',        'color'=>'#f9c80e'],
        ['icon'=>'bi-award-fill',       'target'=>30,   'suffix'=>'+', 'label'=>'Awards & Honours',      'color'=>'#198754'],
        ['icon'=>'bi-house-door-fill',  'target'=>20,   'suffix'=>'',  'label'=>'Years of Excellence',   'color'=>'#0d4f2a'],
        ['icon'=>'bi-heart-fill',       'target'=>100,  'suffix'=>'%', 'label'=>'Commitment to Learners','color'=>'#f9c80e'],
      ];
      foreach ($stats as $i => $s): ?>
      <div class="col-lg-2 col-md-4 col-6">
        <div class="stat-card reveal delay-<?= $i+1 ?>">
          <div class="stat-icon" style="background:linear-gradient(135deg,<?= $s['color'] ?>,<?= $s['color'] ?>cc);">
            <i class="bi <?= $s['icon'] ?>"></i>
          </div>
          <div class="stat-number" data-target="<?= $s['target'] ?>" data-suffix="<?= $s['suffix'] ?>">0</div>
          <div class="stat-label"><?= $s['label'] ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ═══ ABOUT SNIPPET ════════════════════════════════════════════════════════ -->
<section class="section section-alt">
  <div class="container">
    <div class="row align-items-center g-5">
      <div class="col-lg-6 reveal reveal-left">
        <div class="position-relative">
          <img src="<?= $appBase ?>/images/school-building.jpg"
               onerror="this.src='https://placehold.co/600x420/198754/ffffff?text=Kingsway+School'"
               alt="Kingsway School" class="img-fluid rounded-4 shadow-lg">
          <div class="position-absolute bottom-0 start-0 m-3 bg-white rounded-3 p-3 shadow-sm d-flex align-items-center gap-2">
            <div class="bg-success rounded-2 p-2 text-white"><i class="bi bi-shield-check fs-5"></i></div>
            <div><div class="fw-bold small text-dark">TSC Accredited</div><div class="text-muted" style="font-size:.75rem">Ministry of Education Kenya</div></div>
          </div>
        </div>
      </div>
      <div class="col-lg-6 reveal reveal-right">
        <div class="section-label"><span>About Kingsway</span></div>
        <h2 class="section-title">Building <span>Tomorrow's Leaders</span> Today</h2>
        <p class="section-subtitle mb-4">
          Founded with a vision to provide holistic education, Kingsway Preparatory School
          has grown into one of the leading schools in the Rift Valley region.
          We nurture academic excellence, strong values, and practical life skills.
        </p>
        <div class="row g-3 mb-4">
          <?php
          $pillars = [
            ['icon'=>'bi-book-fill','color'=>'#198754','text'=>'CBC Curriculum','sub'=>'Kenya-aligned learning'],
            ['icon'=>'bi-star-fill','color'=>'#f9c80e','text'=>'Values-Based','sub'=>'Character first approach'],
            ['icon'=>'bi-activity', 'color'=>'#0d6efd','text'=>'Co-Curricular','sub'=>'Sports, arts & clubs'],
            ['icon'=>'bi-house-fill','color'=>'#dc3545','text'=>'Full Boarding','sub'=>'Safe residential campus'],
          ];
          foreach ($pillars as $p): ?>
          <div class="col-6">
            <div class="d-flex align-items-start gap-3 p-3 bg-white rounded-3 border h-100">
              <div class="rounded-2 p-2 flex-shrink-0" style="background:<?= $p['color'] ?>22;">
                <i class="bi <?= $p['icon'] ?> fs-5" style="color:<?= $p['color'] ?>"></i>
              </div>
              <div>
                <div class="fw-semibold small"><?= $p['text'] ?></div>
                <div class="text-muted" style="font-size:.78rem"><?= $p['sub'] ?></div>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <a href="<?= $appBase ?>/about.php" class="btn-kw-primary">
          <i class="bi bi-arrow-right-circle"></i>Learn More About Us
        </a>
      </div>
    </div>
  </div>
</section>

<!-- ═══ PROGRAMS ══════════════════════════════════════════════════════════════ -->
<section class="section">
  <div class="container">
    <div class="text-center mb-5 reveal">
      <div class="section-label justify-content-center"><span>Academic Excellence</span></div>
      <h2 class="section-title">Our <span>Programs & Curriculum</span></h2>
      <p class="section-subtitle mx-auto">Comprehensive CBC-aligned programs from Pre-Primary through Junior Secondary School.</p>
    </div>
    <div class="row g-4">
      <?php
      $programs = [
        ['icon'=>'bi-emoji-smile-fill','bg'=>'#e8f5e9','color'=>'#198754','title'=>'Pre-Primary (ECD)',  'desc'=>'PP1 & PP2 — Foundation learning through play-based activities, literacy, numeracy, and social skills.'],
        ['icon'=>'bi-book-open-fill',  'bg'=>'#e3f2fd','color'=>'#1976d2','title'=>'Lower Primary',      'desc'=>'Grade 1–3 — Core competencies in Literacy, Mathematical Activities, and Environmental Activities.'],
        ['icon'=>'bi-pencil-fill',     'bg'=>'#fff8e1','color'=>'#f9c80e','title'=>'Upper Primary',      'desc'=>'Grade 4–6 — Deepened learning across English, Kiswahili, Mathematics, Science and Social Studies.'],
        ['icon'=>'bi-mortarboard-fill','bg'=>'#fce4ec','color'=>'#e91e63','title'=>'Junior Secondary',   'desc'=>'Grade 7–9 — Pathway-focused learning. KJSEA preparation with STEM, Arts and Social Sciences tracks.'],
        ['icon'=>'bi-laptop-fill',     'bg'=>'#e8eaf6','color'=>'#3f51b5','title'=>'STEM & ICT',         'desc'=>'Integrated computer science, robotics, and digital literacy embedded across all grade levels.'],
        ['icon'=>'bi-trophy-fill',     'bg'=>'#f3e5f5','color'=>'#9c27b0','title'=>'Sports & Co-Curricular','desc'=>'Football, athletics, music, drama, clubs and leadership programs for all-round development.'],
      ];
      foreach ($programs as $i => $p): ?>
      <div class="col-lg-4 col-md-6">
        <div class="program-card reveal delay-<?= ($i%3)+1 ?>">
          <div class="program-icon" style="background:<?= $p['bg'] ?>">
            <i class="bi <?= $p['icon'] ?>" style="color:<?= $p['color'] ?>"></i>
          </div>
          <h5><?= $p['title'] ?></h5>
          <p><?= $p['desc'] ?></p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="text-center mt-5">
      <a href="<?= $appBase ?>/about.php#programs" class="btn-kw-outline">
        <i class="bi bi-grid-3x3-gap"></i>View All Programs
      </a>
    </div>
  </div>
</section>

<!-- ═══ NEWS + EVENTS ════════════════════════════════════════════════════════ -->
<section class="section section-alt">
  <div class="container">
    <div class="row g-5">

      <!-- Latest News -->
      <div class="col-lg-7">
        <div class="d-flex align-items-center justify-content-between mb-4 reveal">
          <div>
            <div class="section-label"><span>Latest Updates</span></div>
            <h2 class="section-title mb-0">News &amp; <span>Blog</span></h2>
          </div>
          <a href="<?= $appBase ?>/news.php" class="btn-kw-outline" style="white-space:nowrap;padding:8px 18px;font-size:.82rem;">
            All News <i class="bi bi-arrow-right"></i>
          </a>
        </div>
        <div class="row g-4">
          <?php foreach (array_slice($news,0,3) as $i => $n):
            $cats = ['Sports'=>['#198754','bi-lightning-fill'],'Academic'=>['#1976d2','bi-book-fill'],'Infrastructure'=>['#e91e63','bi-buildings-fill'],'Announcement'=>['#f9c80e','bi-megaphone-fill'],'Arts'=>['#9c27b0','bi-music-note-beamed']];
            $cat  = $cats[$n['category']] ?? ['#198754','bi-circle-fill'];
            $date = date('d M Y', strtotime($n['created_at']));
            $excerpt = mb_strimwidth(strip_tags($n['content']),0,120,'…');
          ?>
          <div class="col-md-<?= $i===0?'12':'6' ?>">
            <div class="card-modern reveal delay-<?= $i+1 ?>">
              <div class="card-img-wrap">
                <img src="https://placehold.co/600x380/<?= ltrim($cat[0],'#') ?>/ffffff?text=<?= urlencode($n['category'] ?? 'News') ?>" alt="<?= htmlspecialchars($n['title']) ?>">
              </div>
              <div class="p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                  <span class="card-category" style="background:<?= $cat[0] ?>">
                    <i class="bi <?= $cat[1] ?>"></i><?= htmlspecialchars($n['category'] ?? 'News') ?>
                  </span>
                  <span class="card-date"><i class="bi bi-calendar3"></i><?= $date ?></span>
                </div>
                <div class="card-title"><a href="<?= $appBase ?>/news.php"><?= htmlspecialchars($n['title']) ?></a></div>
                <div class="card-excerpt"><?= htmlspecialchars($excerpt) ?></div>
                <a href="<?= $appBase ?>/news.php" class="read-more">Read More <i class="bi bi-arrow-right"></i></a>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Upcoming Events -->
      <div class="col-lg-5">
        <div class="d-flex align-items-center justify-content-between mb-4 reveal">
          <div>
            <div class="section-label"><span>What's Coming</span></div>
            <h2 class="section-title mb-0">Upcoming <span>Events</span></h2>
          </div>
          <a href="<?= $appBase ?>/events.php" class="btn-kw-outline" style="white-space:nowrap;padding:8px 18px;font-size:.82rem;">
            Calendar <i class="bi bi-arrow-right"></i>
          </a>
        </div>
        <div class="bg-white rounded-4 border p-4 reveal">
          <?php
          $typeColors = ['Academic'=>['#e3f2fd','#1976d2'],'Ceremony'=>['#fff8e1','#f9a825'],'Sports'=>['#e8f5e9','#2e7d32'],'Meeting'=>['#fce4ec','#c62828']];
          foreach ($events as $ev):
            $evDate = new DateTime($ev['event_date']);
            $tc = $typeColors[$ev['category']] ?? ['#f3e5f5','#7b1fa2'];
          ?>
          <div class="event-item">
            <div class="event-date-box">
              <div class="day"><?= $evDate->format('d') ?></div>
              <div class="month"><?= $evDate->format('M') ?></div>
            </div>
            <div>
              <div class="event-type" style="background:<?= $tc[0] ?>;color:<?= $tc[1] ?>">
                <?= htmlspecialchars($ev['category'] ?? 'Event') ?>
              </div>
              <div class="event-title"><?= htmlspecialchars($ev['title']) ?></div>
              <div class="event-meta">
                <?php if (!empty($ev['event_time'])): ?>
                <span><i class="bi bi-clock text-success"></i><?= date('g:i A', strtotime($ev['event_time'])) ?></span>
                <?php endif; ?>
                <?php if (!empty($ev['location'])): ?>
                <span><i class="bi bi-geo-alt text-success"></i><?= htmlspecialchars($ev['location']) ?></span>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
          <div class="text-center pt-2">
            <a href="<?= $appBase ?>/events.php" class="read-more justify-content-center">
              View Full Calendar <i class="bi bi-arrow-right"></i>
            </a>
          </div>
        </div>
      </div>

    </div>
  </div>
</section>

<!-- ═══ GALLERY ═══════════════════════════════════════════════════════════════ -->
<section class="section">
  <div class="container">
    <div class="row align-items-center g-5">
      <div class="col-lg-5 reveal reveal-left">
        <div class="section-label"><span>School Life</span></div>
        <h2 class="section-title">Life at <span>Kingsway</span></h2>
        <p class="section-subtitle mb-4">
          From morning assemblies to afternoon sports, from science labs to music
          festivals — every day at Kingsway is filled with purpose, joy, and growth.
        </p>
        <div class="d-flex flex-column gap-3 mb-4">
          <?php foreach ([['bi-house-heart-fill','Boarding & Hostel','Modern dormitories with trained houseparents'],['bi-cup-hot-fill','Catering','Nutritious meals planned by qualified cooks'],['bi-wifi','Digital Learning','Smart classrooms and computer lab']] as $f): ?>
          <div class="d-flex align-items-center gap-3">
            <div class="bg-success bg-opacity-10 rounded-2 p-2 flex-shrink-0">
              <i class="bi <?= $f[0] ?> text-success fs-5"></i>
            </div>
            <div>
              <div class="fw-semibold small"><?= $f[1] ?></div>
              <div class="text-muted" style="font-size:.78rem"><?= $f[2] ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="col-lg-7 reveal reveal-right">
        <div class="gallery-grid">
          <?php
          $imgs = ['students','sports','classroom','library','assembly','lab'];
          $colors = ['198754','0d4f2a','f9c80e','1976d2','9c27b0','e91e63'];
          foreach ($imgs as $gi => $g): ?>
          <div class="gallery-item">
            <img src="<?= $appBase ?>/images/gallery/<?= $g ?>.jpg"
                 onerror="this.src='https://placehold.co/400x300/<?= $colors[$gi] ?>/ffffff?text=<?= ucfirst($g) ?>'"
                 alt="<?= ucfirst($g) ?>">
            <div class="overlay"><i class="bi bi-zoom-in"></i></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ═══ ADMISSIONS CTA ════════════════════════════════════════════════════════ -->
<section class="cta-banner">
  <div class="container position-relative" style="z-index:1">
    <div class="row align-items-center g-4">
      <div class="col-lg-8 reveal reveal-left">
        <div class="section-label" style="color:var(--gold)"><span>Enrol Today</span></div>
        <h2 class="section-title">Ready to Join the <span style="color:var(--gold)">Kingsway Family?</span></h2>
        <p class="section-subtitle" style="color:rgba(255,255,255,.8);max-width:540px">
          Applications for Term 1 <?= date('Y')+1 ?> are now open. Limited spaces available across all grade levels.
          Begin your child's journey to excellence today.
        </p>
      </div>
      <div class="col-lg-4 text-lg-end reveal reveal-right">
        <div class="d-flex flex-column flex-sm-row flex-lg-column gap-3 justify-content-lg-end">
          <a href="<?= $appBase ?>/admissions.php" class="btn-kw-gold">
            <i class="bi bi-pencil-square"></i>Start Application
          </a>
          <a href="<?= $appBase ?>/downloads.php" class="btn-kw-outline" style="color:#fff;border-color:rgba(255,255,255,.4)">
            <i class="bi bi-download"></i>Download Prospectus
          </a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ═══ TESTIMONIALS ══════════════════════════════════════════════════════════ -->
<section class="section section-alt">
  <div class="container">
    <div class="text-center mb-5 reveal">
      <div class="section-label justify-content-center"><span>Parent &amp; Alumni Voices</span></div>
      <h2 class="section-title">What Our <span>Community Says</span></h2>
    </div>
    <div class="row g-4">
      <?php
      $testi = [
        ['text'=>"Kingsway has transformed my daughter completely. The teachers genuinely care, the CBC teaching is excellent, and she has grown so much in confidence and character.",'name'=>'Mrs. Akinyi Otieno','role'=>'Parent, Grade 6','stars'=>5],
        ['text'=>"As an alumni who went through KCPE here, I can say the foundation Kingsway gave me opened doors to the best secondary schools and beyond. The values still guide me.",'name'=>'Brian Kiprotich','role'=>'Alumni, Class of 2019','stars'=>5],
        ['text'=>"The boarding facilities and pastoral care are exceptional. My son feels at home here. The staff treats every child as their own. We are extremely satisfied.",'name'=>'Mr. Samuel Cheruiyot','role'=>'Parent, Grade 8','stars'=>5],
      ];
      foreach ($testi as $i => $t): ?>
      <div class="col-lg-4 col-md-6">
        <div class="testimonial-card reveal delay-<?= $i+1 ?>">
          <div class="stars"><?= str_repeat('★',$t['stars']) ?></div>
          <p class="testimonial-text"><?= $t['text'] ?></p>
          <div class="testimonial-author">
            <div class="testimonial-avatar d-flex align-items-center justify-content-center bg-success text-white rounded-circle" style="width:46px;height:46px;font-size:1.1rem;font-weight:700;flex-shrink:0;">
              <?= strtoupper(substr($t['name'],0,1)) ?>
            </div>
            <div>
              <div class="testimonial-name"><?= $t['name'] ?></div>
              <div class="testimonial-role"><?= $t['role'] ?></div>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ═══ CAREERS TEASER ════════════════════════════════════════════════════════ -->
<section class="section">
  <div class="container">
    <div class="row align-items-center g-4">
      <div class="col-lg-6 reveal reveal-left">
        <div class="section-label"><span>Work With Us</span></div>
        <h2 class="section-title">Build Your Career at <span>Kingsway</span></h2>
        <p class="section-subtitle mb-4">
          We are always looking for passionate educators and support staff who share our
          vision of excellence and child-centred education. Join our team and make a difference.
        </p>
        <a href="<?= $appBase ?>/careers.php" class="btn-kw-primary">
          <i class="bi bi-briefcase-fill"></i>View Open Positions
        </a>
      </div>
      <div class="col-lg-6 reveal reveal-right">
        <div class="row g-3">
          <?php foreach ([['bi-person-check-fill','Competitive Salary','TSC-aligned pay scales and benefits'],['bi-people-fill','Supportive Team','Collaborative, growth-oriented environment'],['bi-patch-check-fill','CPD Programs','Continuous professional development funded'],['bi-geo-alt-fill','Londiani, Kenya','Beautiful, serene school campus']] as $b): ?>
          <div class="col-6">
            <div class="p-3 bg-white border rounded-3 text-center h-100">
              <i class="bi <?= $b[0] ?> text-success fs-3 mb-2 d-block"></i>
              <div class="fw-semibold small mb-1"><?= $b[1] ?></div>
              <div class="text-muted" style="font-size:.78rem"><?= $b[2] ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ═══ CONTACT STRIP ═════════════════════════════════════════════════════════ -->
<section class="section-sm" style="background:var(--green-dark)">
  <div class="container">
    <div class="row g-4 text-white">
      <?php foreach ([['bi-geo-alt-fill','Location','P.O BOX 203-20203, Londiani, Kenya'],['bi-telephone-fill','Call Us','+254 720 113 030 / 031'],['bi-envelope-fill','Email','info@kingswaypreparatoryschool.sc.ke'],['bi-clock-fill','Office Hours','Mon – Fri: 7:30 AM – 5:00 PM']] as $c): ?>
      <div class="col-md-3 col-6 text-center">
        <div class="mb-2 opacity-75"><i class="bi <?= $c[0] ?> fs-2" style="color:var(--gold)"></i></div>
        <div class="small text-uppercase fw-semibold opacity-75 mb-1"><?= $c[1] ?></div>
        <div class="small opacity-90"><?= $c[2] ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php include __DIR__ . '/public/layout/footer.php'; ?>
