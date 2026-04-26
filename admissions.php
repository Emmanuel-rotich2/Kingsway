<?php
$appBase    = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '')),'/');
if ($appBase === '.') $appBase = '';
$pageTitle  = 'Admissions';
$activePage = 'admissions';
?>
<?php include __DIR__ . '/public/layout/header.php'; ?>

<div class="page-header">
  <div class="container position-relative" style="z-index:1">
    <nav aria-label="breadcrumb"><ol class="breadcrumb mb-2">
      <li class="breadcrumb-item"><a href="<?= $appBase ?>/index.php">Home</a></li>
      <li class="breadcrumb-item active">Admissions</li>
    </ol></nav>
    <h1 class="page-title">Admissions</h1>
    <p class="mt-2" style="color:rgba(255,255,255,.7)">Join the Kingsway family — applications are open</p>
  </div>
</div>

<!-- Quick Info Bar -->
<div class="py-4" style="background:var(--gold)">
  <div class="container">
    <div class="row g-3 text-center">
      <?php $nextYear = (int)date('Y') + 1; foreach ([['bi-calendar-check','Applications Open','Term 1 '.$nextYear.' intake now open'],['bi-clock','Response Time','Within 5 working days'],['bi-person-plus','Age Range','4 – 15 years (PP1 – Grade 9)'],['bi-telephone','Enquiries','0720 113 030']] as $q): ?>
      <div class="col-6 col-md-3">
        <i class="bi <?= $q[0] ?> fs-4 text-dark d-block mb-1"></i>
        <div class="fw-bold small text-dark"><?= $q[1] ?></div>
        <div style="font-size:.78rem;color:var(--green-dark)"><?= $q[2] ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Process -->
<section class="section" id="process">
  <div class="container">
    <div class="text-center mb-5 reveal">
      <div class="section-label justify-content-center"><span>Step-by-Step</span></div>
      <h2 class="section-title">Admission <span>Process</span></h2>
      <p class="section-subtitle mx-auto">Our transparent, fair admission process ensures every child gets a proper evaluation.</p>
    </div>
    <div class="row g-4 justify-content-center">
      <?php
      $steps = [
        ['1','bi-file-earmark-plus-fill','#198754','Submit Application','Complete and submit the application form online or in person. Attach all required documents.'],
        ['2','bi-file-check-fill','#1976d2','Document Review','Our admissions team reviews the application and verifies all submitted documents.'],
        ['3','bi-chat-dots-fill','#f9c80e','Placement Assessment','The applicant sits a short placement test and interviews with the Head Teacher.'],
        ['4','bi-envelope-check-fill','#9c27b0','Offer Letter','Successful applicants receive an official offer letter within 5 working days.'],
        ['5','bi-cash-coin','#e65100','Fee Payment','A non-refundable admission fee secures the placement. Full term fees follow.'],
        ['6','bi-mortarboard-fill','#00695c','Orientation &amp; Enrolment','The student attends orientation before joining class on the agreed start date.'],
      ];
      foreach ($steps as $i => $s): ?>
      <div class="col-lg-4 col-md-6">
        <div class="text-center p-4 bg-white border rounded-4 h-100 reveal delay-<?= ($i%3)+1 ?>" style="transition-delay:<?= ($i%3)*.1 ?>s">
          <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" style="width:52px;height:52px;background:<?= $s[2] ?>;color:#fff;font-size:1.3rem;font-weight:900"><?= $s[0] ?></div>
          <div class="mb-2"><i class="bi <?= $s[1] ?> fs-2" style="color:<?= $s[2] ?>"></i></div>
          <h5 class="fw-bold mb-2"><?= $s[3] ?></h5>
          <p class="text-muted small mb-0"><?= $s[4] ?></p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Requirements -->
<section class="section section-alt" id="requirements">
  <div class="container">
    <div class="row g-5">
      <div class="col-lg-6 reveal reveal-left">
        <div class="section-label"><span>What You Need</span></div>
        <h2 class="section-title">Admission <span>Requirements</span></h2>
        <p class="section-subtitle mb-4">Please ensure all documents are ready before submitting your application.</p>
        <div class="d-flex flex-column gap-3">
          <?php foreach ([
            ['Completed Application Form','Download from our website or collect at the office','bi-file-earmark-text-fill','#198754'],
            ['Birth Certificate (certified copy)','Original or notarized photocopy required','bi-file-person-fill','#1976d2'],
            ['Previous School Report / Transfer Letter','Last 2 years of academic performance','bi-file-earmark-bar-graph-fill','#9c27b0'],
            ['Passport Photo (2 copies)','Recent, passport-size, white background','bi-person-bounding-box','#e65100'],
            ['Medical/Vaccination Card','Immunization records for health file','bi-heart-pulse-fill','#e91e63'],
            ['Parent/Guardian National ID (copy)','For contact and emergency records','bi-credit-card-fill','#00695c'],
          ] as $r): ?>
          <div class="d-flex align-items-start gap-3 p-3 bg-white border rounded-3">
            <i class="bi <?= $r[3] ?> fs-4 flex-shrink-0" style="color:<?= $r[2] ?>"></i>
            <div>
              <div class="fw-semibold small"><?= $r[0] ?></div>
              <div class="text-muted" style="font-size:.78rem"><?= $r[1] ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="col-lg-6 reveal reveal-right">
        <div class="section-label"><span>Grade Levels</span></div>
        <h2 class="section-title">Age & <span>Grade Entry</span></h2>
        <div class="table-responsive">
          <table class="table table-bordered rounded-4 overflow-hidden" style="font-size:.9rem">
            <thead style="background:var(--green);color:#fff">
              <tr>
                <th class="py-3">Grade / Level</th>
                <th class="py-3">Age Range</th>
                <th class="py-3">Spaces Available</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ([
                ['PP1 (Pre-Primary 1)','4 – 5 years','Limited'],
                ['PP2 (Pre-Primary 2)','5 – 6 years','Available'],
                ['Grade 1','6 – 7 years','Available'],
                ['Grade 2 – 3','7 – 9 years','Available'],
                ['Grade 4 – 6','10 – 12 years','Limited'],
                ['Grade 7 – 9 (JSS)','12 – 15 years','Limited'],
              ] as $g): ?>
              <tr>
                <td class="fw-semibold"><?= $g[0] ?></td>
                <td class="text-muted"><?= $g[1] ?></td>
                <td>
                  <span class="tag" style="background:<?= $g[2]==='Available'?'#e8f5e9':'#fff8e1' ?>;color:<?= $g[2]==='Available'?'#2e7d32':'#f57f17' ?>;font-size:.72rem">
                    <?= $g[2] ?>
                  </span>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="p-3 rounded-3 mt-3" style="background:#e8f5e9;border-left:3px solid var(--green)">
          <p class="small text-muted mb-0"><i class="bi bi-info-circle text-success me-2"></i>
          Placement is subject to availability and assessment results. Early applications are encouraged as places fill quickly, especially for PP1 and JSS.
          </p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Fee Overview -->
<section class="section" id="fees">
  <div class="container">
    <div class="text-center mb-5 reveal">
      <div class="section-label justify-content-center"><span>Transparent Pricing</span></div>
      <h2 class="section-title">Fee <span>Structure Overview</span></h2>
      <p class="section-subtitle mx-auto">Comprehensive education at affordable rates. Detailed fee structure available on request.</p>
    </div>
    <div class="row g-4 justify-content-center">
      <?php foreach ([
        ['Day Scholar','PP1 – Grade 9','#198754',['Tuition fees','Activity fees','Examination fees','Lunch meal','All learning materials'],'From KSh 18,000 / term'],
        ['Full Boarding','Grade 1 – Grade 9','#1976d2',['All day scholar inclusions','Accommodation &amp; bedding','Three meals daily','Evening preps','Pastoral care 24/7'],'From KSh 42,000 / term'],
      ] as $fp): ?>
      <div class="col-lg-5">
        <div class="card-modern p-5 text-center h-100 reveal" style="border-top:4px solid <?= $fp[2] ?>">
          <h4 class="fw-bold mb-1"><?= $fp[0] ?></h4>
          <div class="text-muted small mb-3"><?= $fp[1] ?></div>
          <div class="fs-4 fw-bold mb-4" style="color:<?= $fp[2] ?>"><?= $fp[4] ?></div>
          <ul class="list-unstyled text-start mb-4">
            <?php foreach ($fp[3] as $f): ?>
            <li class="d-flex align-items-center gap-2 mb-2 small">
              <i class="bi bi-check-circle-fill flex-shrink-0" style="color:<?= $fp[2] ?>"></i><?= $f ?>
            </li>
            <?php endforeach; ?>
          </ul>
          <a href="<?= $appBase ?>/downloads.php" class="btn-kw-outline" style="color:<?= $fp[2] ?>;border-color:<?= $fp[2] ?>">
            <i class="bi bi-download"></i>Download Full Fee Structure
          </a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <p class="text-center text-muted small mt-4">* Fees are reviewed annually. Payment plans available. Bursaries offered to deserving students — <a href="<?= $appBase ?>/downloads.php" class="text-success">download bursary form</a>.</p>
  </div>
</section>

<!-- Apply Now -->
<section class="cta-banner" id="apply">
  <div class="container position-relative" style="z-index:1">
    <div class="row align-items-center g-5">
      <div class="col-lg-6 reveal reveal-left">
        <div class="section-label" style="color:var(--gold)"><span>Start Your Journey</span></div>
        <h2 class="section-title text-white">Apply for <span style="color:var(--gold)">Admission Today</span></h2>
        <p style="color:rgba(255,255,255,.8)" class="mb-4">Complete the short enquiry form and our admissions team will contact you within 24 hours with the next steps.</p>
      </div>
      <div class="col-lg-6 reveal reveal-right">
        <div class="bg-white rounded-4 p-4 shadow-lg">
          <h5 class="fw-bold mb-3 text-dark">Admission Enquiry</h5>
          <form onsubmit="event.preventDefault();this.innerHTML='<div class=\'text-center py-3\'><i class=\'bi bi-check-circle-fill text-success fs-1\'></i><p class=\'mt-3 fw-semibold\'>Thank you! We\'ll be in touch shortly.</p></div>'">
            <div class="row g-3">
              <div class="col-6">
                <input type="text" class="form-control-kw" placeholder="Parent Name *" required>
              </div>
              <div class="col-6">
                <input type="tel" class="form-control-kw" placeholder="Phone Number *" required>
              </div>
              <div class="col-12">
                <input type="email" class="form-control-kw" placeholder="Email Address *" required>
              </div>
              <div class="col-6">
                <input type="text" class="form-control-kw" placeholder="Child's Name *" required>
              </div>
              <div class="col-6">
                <select class="form-control-kw" required>
                  <option value="">Grade Applying *</option>
                  <?php foreach (['PP1','PP2','Grade 1','Grade 2','Grade 3','Grade 4','Grade 5','Grade 6','Grade 7','Grade 8','Grade 9'] as $g): ?>
                  <option><?= $g ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-12">
                <button type="submit" class="btn-kw-primary w-100 justify-content-center py-3">
                  <i class="bi bi-send-fill"></i>Submit Enquiry
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- FAQ -->
<section class="section section-alt">
  <div class="container">
    <div class="text-center mb-5 reveal">
      <div class="section-label justify-content-center"><span>Common Questions</span></div>
      <h2 class="section-title">Frequently Asked <span>Questions</span></h2>
    </div>
    <div class="row justify-content-center">
      <div class="col-lg-8">
        <div class="accordion" id="faqAccordion">
          <?php foreach ([
            ['When is the application deadline?','Applications are accepted on a rolling basis throughout the year. However, we recommend applying early as spaces, especially for PP1, Grade 1, and Grade 7, fill up quickly. Applications for Term 1 intake should be submitted by November of the preceding year.'],
            ['Is there an entrance exam?','Yes, applicants for Grade 2 and above sit a short placement assessment in English, Mathematics, and General Knowledge. This helps us place each child in the right class. There is no pass or fail — it is purely for placement purposes.'],
            ['Do you offer boarding for all grades?','Full boarding is available for Grade 1 through Grade 9 (ages 6–15). PP1 and PP2 pupils are day scholars only. Half-day boarding options can be discussed with the admissions office.'],
            ['What is the payment schedule?','Fees are due at the beginning of each term (three terms per year). We accept M-Pesa, bank transfer, and cash at the office. Payment plans are available upon request for families facing financial difficulty.'],
            ['Do you offer bursaries or scholarships?','Yes, we have a limited number of bursaries available for academically deserving but financially needy students. Applications are reviewed each term. Download the bursary application form from our downloads page.'],
            ['Can my child join mid-term?','Mid-term admissions are possible depending on space availability. The child will sit the placement assessment, and if space is available, they can join immediately. Contact the admissions office to check availability.'],
          ] as $qi => $faq): ?>
          <div class="accordion-item border rounded-3 mb-3 overflow-hidden reveal">
            <h2 class="accordion-header">
              <button class="accordion-button <?= $qi?'collapsed':'' ?> fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#faq<?= $qi ?>">
                <?= $faq[0] ?>
              </button>
            </h2>
            <div id="faq<?= $qi ?>" class="accordion-collapse collapse <?= $qi===0?'show':'' ?>" data-bs-parent="#faqAccordion">
              <div class="accordion-body text-muted"><?= $faq[1] ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <div class="text-center mt-4">
          <p class="text-muted small">Still have questions? <a href="<?= $appBase ?>/contact.php" class="text-success fw-semibold">Contact our admissions team</a></p>
        </div>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/public/layout/footer.php'; ?>
