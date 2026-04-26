<?php
$appBase    = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '')),'/');
if ($appBase === '.') $appBase = '';
$pageTitle  = 'About Us';
$activePage = 'about';
?>
<?php include __DIR__ . '/public/layout/header.php'; ?>

<div class="page-header">
  <div class="container position-relative" style="z-index:1">
    <nav aria-label="breadcrumb"><ol class="breadcrumb mb-2">
      <li class="breadcrumb-item"><a href="<?= $appBase ?>/index.php">Home</a></li>
      <li class="breadcrumb-item active">About Us</li>
    </ol></nav>
    <h1 class="page-title">About Kingsway</h1>
    <p class="mt-2" style="color:rgba(255,255,255,.7);font-size:1rem">Excellence, Character &amp; Leadership since 2005</p>
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
          <p class="mb-0">To provide a nurturing, inclusive, and academically rigorous environment that develops confident, virtuous, and globally-competitive learners through the Kenya Competency-Based Curriculum.</p>
        </div>
        <div class="p-4 rounded-4 mb-4" style="background:linear-gradient(135deg,#fff8e1,#fffde7);border-left:4px solid var(--gold)">
          <h5 class="fw-bold mb-2" style="color:var(--gold-dark)"><i class="bi bi-eye-fill me-2"></i>Vision</h5>
          <p class="mb-0">To be the most preferred school of excellence in the East African region, producing well-rounded, morally upright, and intellectually superior graduates.</p>
        </div>
        <div class="p-4 rounded-4" style="background:linear-gradient(135deg,#e8eaf6,#ede7f6);border-left:4px solid #7c4dff">
          <h5 class="fw-bold mb-2" style="color:#512da8"><i class="bi bi-gem me-2"></i>Motto</h5>
          <p class="mb-0 fst-italic fw-semibold fs-5">"In God We Soar"</p>
        </div>
      </div>
      <div class="col-lg-6 reveal reveal-right">
        <h4 class="fw-bold mb-4 text-success">Our Core Values</h4>
        <div class="row g-3">
          <?php foreach ([
            ['bi-heart-fill','#e91e63','Love','Compassion and empathy in every interaction'],
            ['bi-person-check-fill','#198754','Responsibility','Accountability for our actions and learning'],
            ['bi-hand-thumbs-up-fill','#1976d2','Respect','Honoring every person\'s dignity and worth'],
            ['bi-people-fill','#ff9800','Unity','Together we achieve more, divided we fall'],
            ['bi-dove','#9c27b0','Peace','Harmony in our diverse school community'],
            ['bi-flag-fill','#f44336','Patriotism','Pride in our Kenyan heritage and culture'],
          ] as $v): ?>
          <div class="col-6">
            <div class="d-flex align-items-start gap-3 p-3 bg-white border rounded-3">
              <i class="bi <?= $v[0] ?> fs-4 flex-shrink-0" style="color:<?= $v[1] ?>"></i>
              <div>
                <div class="fw-semibold small"><?= $v[2] ?></div>
                <div class="text-muted" style="font-size:.75rem"><?= $v[3] ?></div>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- History -->
<section class="section section-alt" id="history">
  <div class="container">
    <div class="text-center mb-5 reveal">
      <div class="section-label justify-content-center"><span>Our Story</span></div>
      <h2 class="section-title">A Journey of <span>Excellence</span></h2>
    </div>
    <div class="row justify-content-center">
      <div class="col-lg-10">
        <div class="position-relative" style="border-left:3px solid var(--green);padding-left:32px">
          <?php foreach ([
            ['2005','Foundation','Kingsway Preparatory School was founded by a group of visionary educators committed to quality education in Londiani. The school started with 3 streams and 120 pupils.'],
            ['2010','Growth & Recognition','Enrollment surpassed 400 students. The school received its first regional award for academic excellence. New classrooms and a modern library were constructed.'],
            ['2015','Boarding Programme','Introduction of the full boarding programme, enabling students from across the region to benefit from Kingsway\'s quality education. Dormitory facilities expanded.'],
            ['2019','CBC Transition','Seamless transition to Kenya\'s Competency-Based Curriculum. Teacher training and infrastructure upgrades positioned Kingsway as a model CBC school.'],
            ['2022','Digital Transformation','Launch of the new computer lab with 40 workstations. Introduction of smart classrooms and the school management ERP system for modern operations.'],
            [date('Y'),'Today','Over 1,200 students enrolled, 80+ qualified staff, and a track record of 98% KJSEA pass rates. Kingsway continues to grow in excellence.'],
          ] as $h): ?>
          <div class="mb-5 position-relative reveal">
            <div class="position-absolute" style="width:14px;height:14px;background:var(--green);border-radius:50%;left:-40px;top:5px;border:3px solid #fff;box-shadow:0 0 0 3px var(--green)"></div>
            <span class="badge bg-success mb-2"><?= $h[0] ?></span>
            <h5 class="fw-bold text-dark mb-1"><?= $h[1] ?></h5>
            <p class="text-muted mb-0"><?= $h[2] ?></p>
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
      <?php foreach ([
        ['The Director','School Founder &amp; Director','20+ years in education leadership. Holds a Masters in Educational Management.','#0d4f2a'],
        ['The Head Teacher','Head Teacher','B.Ed (Hons), experienced in CBC implementation and school administration.','#198754'],
        ['Deputy (Academic)','Deputy Head — Academic','Oversees curriculum, lesson plans, timetabling, and academic performance.','#1976d2'],
        ['Deputy (Discipline)','Deputy Head — Discipline','Manages student conduct, welfare, and community relations.','#7b1fa2'],
        ['The Bursar','School Bursar / Accountant','CPA-K certified. Manages school finances, fee collection, and budgets.','#e65100'],
        ['Admissions Officer','Admissions Officer','Handles student intake, records, and parent liaison.','#00695c'],
      ] as $l): ?>
      <div class="col-lg-2 col-md-4 col-6">
        <div class="text-center card-modern p-3 h-100 reveal">
          <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3 text-white fs-3 fw-bold" style="width:72px;height:72px;background:<?= $l[3] ?>;">
            <?= strtoupper(substr(explode(' ',$l[0])[1] ?? $l[0], 0, 1)) ?>
          </div>
          <div class="fw-bold small"><?= $l[0] ?></div>
          <div class="text-success" style="font-size:.75rem;font-weight:600"><?= $l[1] ?></div>
          <p class="text-muted mt-2 mb-0" style="font-size:.75rem"><?= $l[2] ?></p>
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
      <?php foreach ([
        ['Pre-Primary (ECD)','PP1 – PP2 (Ages 4–5)','bi-emoji-smile-fill','#198754','Play-based learning, phonics, number recognition, social skills, and spiritual development.'],
        ['Lower Primary','Grade 1–3 (Ages 6–8)','bi-book-open-fill','#1976d2','Literacy, Mathematical Activities, Environmental Activities, Creative Arts, and PE.'],
        ['Upper Primary','Grade 4–6 (Ages 9–11)','bi-pencil-fill','#f9c80e','English, Kiswahili, Mathematics, Science & Technology, Social Studies, CRE, Agriculture.'],
        ['Junior Secondary','Grade 7–9 (Ages 12–14)','bi-mortarboard-fill','#e91e63','Integrated Science, Health Education, Pre-Technical, Business Studies, KJSEA preparation.'],
        ['Boarding','All Grades','bi-house-heart-fill','#9c27b0','Full boarding with trained houseparents, nutritious meals, evening preps, and pastoral care.'],
        ['Co-Curricular','All Grades','bi-trophy-fill','#ff9800','Football, Athletics, Music, Drama, Scouts, Debate, Environmental Club, and much more.'],
      ] as $p): ?>
      <div class="col-lg-4 col-md-6 reveal">
        <div class="program-card h-100">
          <div class="program-icon" style="background:<?= $p[3] ?>22"><i class="bi <?= $p[2] ?> fs-2" style="color:<?= $p[3] ?>"></i></div>
          <h5><?= $p[0] ?></h5>
          <div class="tag mb-2" style="background:<?= $p[3] ?>22;color:<?= $p[3] ?>"><?= $p[1] ?></div>
          <p><?= $p[4] ?></p>
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
      <?php foreach ([
        ['bi-building','Modern Classrooms','32 well-ventilated, furnished classrooms equipped for CBC learning.'],
        ['bi-laptop','Computer Lab','40-station computer lab with high-speed internet access.'],
        ['bi-book','Library','Over 10,000 books including CBC-aligned reference materials.'],
        ['bi-heartbeat','Sick Bay','Fully equipped sick bay managed by a qualified nurse.'],
        ['bi-house-door','Dormitories','Separate boys and girls dormitories with houseparents on duty 24/7.'],
        ['bi-cup-hot','Dining Hall','Spacious dining hall serving three balanced meals daily.'],
        ['bi-flag','Sports Ground','Full-size football pitch, basketball, netball, and athletics track.'],
        ['bi-music-note','Music Room','Dedicated music room with instruments for lessons and choir practice.'],
        ['bi-flask','Science Lab','Equipped laboratory for Grade 7–9 integrated science experiments.'],
      ] as $f): ?>
      <div class="col-lg-4 col-md-6 reveal">
        <div class="d-flex align-items-start gap-3 p-4 bg-white border rounded-3 h-100">
          <div class="bg-success bg-opacity-10 rounded-2 p-2 flex-shrink-0">
            <i class="bi <?= $f[0] ?> text-success fs-4"></i>
          </div>
          <div>
            <div class="fw-bold small mb-1"><?= $f[1] ?></div>
            <div class="text-muted" style="font-size:.82rem"><?= $f[2] ?></div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php include __DIR__ . '/public/layout/footer.php'; ?>
