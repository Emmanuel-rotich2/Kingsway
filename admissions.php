<?php
$appBase    = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '')),'/');
if ($appBase === '.') $appBase = '';
$pageTitle  = 'Admissions';
$activePage = 'admissions';
require_once __DIR__ . '/public/layout/public_data.php';

/* ── Handle full application POST ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $childName  = trim($_POST['child_name']  ?? '');
    $parentName = trim($_POST['parent_name'] ?? '');
    $phone      = trim($_POST['parent_phone'] ?? '');
    $grade      = trim($_POST['grade_applying'] ?? '');

    if (!$childName || !$parentName || !$phone || !$grade) {
        echo json_encode(['success'=>false,'message'=>'Please fill in all required fields.']); exit;
    }

    $ref = kw_save_admission_application([
        'child_name'       => $childName,
        'child_dob'        => trim($_POST['child_dob'] ?? ''),
        'child_gender'     => trim($_POST['child_gender'] ?? ''),
        'child_nationality'=> trim($_POST['child_nationality'] ?? 'Kenyan'),
        'child_prev_school'=> trim($_POST['child_prev_school'] ?? ''),
        'child_prev_grade' => trim($_POST['child_prev_grade'] ?? ''),
        'parent_name'      => $parentName,
        'parent_relationship' => trim($_POST['parent_relationship'] ?? ''),
        'parent_id'        => trim($_POST['parent_id'] ?? ''),
        'parent_phone'     => $phone,
        'parent_alt_phone' => trim($_POST['parent_alt_phone'] ?? ''),
        'parent_email'     => filter_var(trim($_POST['parent_email'] ?? ''), FILTER_VALIDATE_EMAIL) ?: '',
        'parent_address'   => trim($_POST['parent_address'] ?? ''),
        'grade'            => $grade,
        'boarding'         => trim($_POST['boarding_preference'] ?? 'day'),
        'start_term'       => trim($_POST['preferred_start'] ?? ''),
        'referral'         => trim($_POST['referral_source'] ?? ''),
        'special_needs'    => trim($_POST['special_needs'] ?? ''),
        'ip'               => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);

    if ($ref) {
        echo json_encode(['success'=>true,'ref'=>$ref,
            'message'=>"Application received! Your reference number is <strong>{$ref}</strong>. Our admissions team will contact you within 24 hours."]);
    } else {
        echo json_encode(['success'=>false,
            'message'=>'Submission failed. Please try calling us directly on '.kw_school_stat('school_phone','0720 113 030').'.']);
    }
    exit;
}

$terms        = kw_academic_terms();
$nextYear     = (int)date('Y') + 1;
$gradeSpaces  = kw_grade_spaces();
$schoolPhone  = kw_school_stat('school_phone_main', kw_school_stat('school_phone', '0720 113 030'));
$adSteps      = kw_admission_steps();
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
      <?php foreach ([
        ['bi-calendar-check','Applications Open','Term 1 '.$nextYear.' intake now open'],
        ['bi-clock','Response Time',kw_school_stat('admissions_response','Within 24 working hours')],
        ['bi-person-plus','Age Range',kw_school_stat('admissions_age_range','4 – 15 years (PP1 – Grade 9)')],
        ['bi-telephone','Enquiries',$schoolPhone],
      ] as $q): ?>
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
      <?php foreach ($adSteps as $i => $s): ?>
      <div class="col-lg-4 col-md-6">
        <div class="text-center p-4 bg-white border rounded-4 h-100 reveal delay-<?= ($i%3)+1 ?>" style="transition-delay:<?= ($i%3)*.1 ?>s">
          <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" style="width:52px;height:52px;background:<?= htmlspecialchars($s['color']) ?>;color:#fff;font-size:1.3rem;font-weight:900"><?= (int)$s['step_number'] ?></div>
          <div class="mb-2"><i class="bi <?= htmlspecialchars($s['icon']) ?> fs-2" style="color:<?= htmlspecialchars($s['color']) ?>"></i></div>
          <h5 class="fw-bold mb-2"><?= htmlspecialchars($s['title']) ?></h5>
          <p class="text-muted small mb-0"><?= htmlspecialchars($s['description']) ?></p>
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
            <i class="bi <?= $r[2] ?> fs-4 flex-shrink-0" style="color:<?= $r[3] ?>"></i>
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
                <th class="py-3">Spaces</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($gradeSpaces as $gradeName => [$ageRange, $spaces]):
                $spaceBg  = match($spaces) { 'Available'=>'#e8f5e9', 'Full'=>'#ffebee', 'Closed'=>'#fafafa', default=>'#fff8e1' };
                $spaceClr = match($spaces) { 'Available'=>'#2e7d32', 'Full'=>'#c62828',  'Closed'=>'#757575', default=>'#f57f17' };
              ?>
              <tr>
                <td class="fw-semibold"><?= htmlspecialchars($gradeName) ?></td>
                <td class="text-muted"><?= htmlspecialchars($ageRange) ?></td>
                <td>
                  <span class="tag" style="background:<?= $spaceBg ?>;color:<?= $spaceClr ?>;font-size:.72rem">
                    <?= htmlspecialchars($spaces) ?>
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

<!-- Apply Now — Full Application Form -->
<section class="section" id="apply" style="background:linear-gradient(135deg,var(--green-dark) 0%,var(--green) 60%,#1b5e20 100%)">
  <div class="container position-relative" style="z-index:1">
    <div class="text-center mb-5 reveal">
      <div class="section-label justify-content-center" style="color:var(--gold)"><span>Start Your Journey</span></div>
      <h2 class="section-title text-white">Apply for <span style="color:var(--gold)">Admission Today</span></h2>
      <p style="color:rgba(255,255,255,.75);max-width:560px;margin:0 auto">Complete the application form below. Our admissions team will contact you within 24 hours with the next steps.</p>
    </div>

    <div class="bg-white rounded-4 shadow-lg p-4 p-lg-5 reveal" id="admissionFormWrap">
      <form id="admissionForm">

        <!-- Step indicator -->
        <div class="d-flex align-items-center justify-content-center gap-2 mb-5 flex-wrap">
          <?php foreach (['Child Information','Parent / Guardian','Application Details','Declaration'] as $si => $sl): ?>
          <div class="d-flex align-items-center gap-2">
            <div class="step-circle <?= $si===0?'active':'' ?>" id="step-circle-<?= $si ?>"
                 style="width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;
                        font-size:.8rem;font-weight:700;border:2px solid var(--green);
                        background:<?= $si===0?'var(--green)':'transparent' ?>;
                        color:<?= $si===0?'#fff':'var(--green)' ?>">
              <?= $si+1 ?>
            </div>
            <span class="small fw-semibold d-none d-md-inline" style="color:<?= $si===0?'var(--green)':'#999' ?>"
                  id="step-label-<?= $si ?>"><?= $sl ?></span>
            <?php if ($si < 3): ?>
            <div style="width:30px;height:2px;background:#e2e8f0" class="d-none d-md-block"></div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- Section 1: Child Information -->
        <div class="form-section" id="section-0">
          <div class="d-flex align-items-center gap-2 mb-4">
            <div class="bg-success bg-opacity-10 rounded-2 p-2"><i class="bi bi-person-fill text-success fs-5"></i></div>
            <h5 class="fw-bold mb-0">Child / Student Information</h5>
          </div>
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label small fw-semibold">Child's Full Name <span class="text-danger">*</span></label>
              <input type="text" name="child_name" class="form-control-kw" placeholder="As on birth certificate" required>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold">Date of Birth</label>
              <input type="date" name="child_dob" class="form-control-kw">
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold">Gender <span class="text-danger">*</span></label>
              <select name="child_gender" class="form-control-kw" required>
                <option value="">Select gender</option>
                <option value="male">Male</option>
                <option value="female">Female</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold">Nationality</label>
              <input type="text" name="child_nationality" class="form-control-kw" value="Kenyan">
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold">Grade Applying For <span class="text-danger">*</span></label>
              <select name="grade_applying" class="form-control-kw" required>
                <option value="">Select grade</option>
                <?php foreach (['PP1','PP2','Grade 1','Grade 2','Grade 3','Grade 4','Grade 5','Grade 6','Grade 7','Grade 8','Grade 9'] as $g): ?>
                <option value="<?= $g ?>"><?= $g ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Current / Previous School</label>
              <input type="text" name="child_prev_school" class="form-control-kw" placeholder="If transferring from another school">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Current Grade / Class</label>
              <input type="text" name="child_prev_grade" class="form-control-kw" placeholder="e.g. Grade 3, Standard 4">
            </div>
          </div>
          <div class="d-flex justify-content-end mt-4">
            <button type="button" class="btn-kw-primary" onclick="adNext(0)">
              Next: Parent Details <i class="bi bi-arrow-right"></i>
            </button>
          </div>
        </div>

        <!-- Section 2: Parent / Guardian -->
        <div class="form-section" id="section-1" style="display:none">
          <div class="d-flex align-items-center gap-2 mb-4">
            <div class="bg-primary bg-opacity-10 rounded-2 p-2"><i class="bi bi-people-fill text-primary fs-5"></i></div>
            <h5 class="fw-bold mb-0">Parent / Guardian Information</h5>
          </div>
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label small fw-semibold">Full Name <span class="text-danger">*</span></label>
              <input type="text" name="parent_name" class="form-control-kw" placeholder="Parent or guardian's full name" required>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold">Relationship to Child <span class="text-danger">*</span></label>
              <select name="parent_relationship" class="form-control-kw" required>
                <option value="">Select</option>
                <option value="Mother">Mother</option>
                <option value="Father">Father</option>
                <option value="Guardian">Guardian</option>
                <option value="Sponsor">Sponsor</option>
                <option value="Other">Other</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">National ID / Passport No.</label>
              <input type="text" name="parent_id" class="form-control-kw" placeholder="ID number">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Phone Number <span class="text-danger">*</span></label>
              <input type="tel" name="parent_phone" class="form-control-kw" placeholder="e.g. 0720 113 030" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Alternative Phone</label>
              <input type="tel" name="parent_alt_phone" class="form-control-kw" placeholder="Second contact number">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Email Address</label>
              <input type="email" name="parent_email" class="form-control-kw" placeholder="parent@email.com">
            </div>
            <div class="col-12">
              <label class="form-label small fw-semibold">Residential Address</label>
              <input type="text" name="parent_address" class="form-control-kw" placeholder="Town, Sub-county, County">
            </div>
          </div>
          <div class="d-flex justify-content-between mt-4">
            <button type="button" class="btn-kw-outline" onclick="adPrev(1)">
              <i class="bi bi-arrow-left"></i> Back
            </button>
            <button type="button" class="btn-kw-primary" onclick="adNext(1)">
              Next: Application Details <i class="bi bi-arrow-right"></i>
            </button>
          </div>
        </div>

        <!-- Section 3: Application Preferences -->
        <div class="form-section" id="section-2" style="display:none">
          <div class="d-flex align-items-center gap-2 mb-4">
            <div class="bg-warning bg-opacity-10 rounded-2 p-2"><i class="bi bi-clipboard-check-fill text-warning fs-5"></i></div>
            <h5 class="fw-bold mb-0">Application Details</h5>
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Day Scholar or Boarding <span class="text-danger">*</span></label>
              <select name="boarding_preference" class="form-control-kw" required>
                <option value="day">Day Scholar</option>
                <option value="full_boarding">Full Boarding (Mon – Fri)</option>
                <option value="weekly_boarding">Weekly Boarding (Mon – Fri)</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Preferred Start Term</label>
              <select name="preferred_start" class="form-control-kw">
                <option value="">Select term</option>
                <option value="Term 1 <?= $nextYear ?>">Term 1 <?= $nextYear ?></option>
                <option value="Term 2 <?= $nextYear ?>">Term 2 <?= $nextYear ?></option>
                <option value="Term 3 <?= $nextYear ?>">Term 3 <?= $nextYear ?></option>
                <option value="Term 1 <?= $nextYear+1 ?>">Term 1 <?= $nextYear+1 ?></option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label small fw-semibold">How did you hear about Kingsway?</label>
              <select name="referral_source" class="form-control-kw">
                <option value="">Select source</option>
                <option value="Parent referral">Parent referral</option>
                <option value="Social media">Social media</option>
                <option value="Online search">Online search</option>
                <option value="Newspaper / print">Newspaper / print</option>
                <option value="School event">School event</option>
                <option value="Staff referral">Staff referral</option>
                <option value="Other">Other</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label small fw-semibold">Special Medical / Learning / Dietary Needs</label>
              <textarea name="special_needs" class="form-control-kw" rows="3"
                placeholder="Any information we should know about to support your child — allergies, learning support needs, medical conditions, dietary requirements. Leave blank if none."></textarea>
            </div>
          </div>
          <div class="d-flex justify-content-between mt-4">
            <button type="button" class="btn-kw-outline" onclick="adPrev(2)">
              <i class="bi bi-arrow-left"></i> Back
            </button>
            <button type="button" class="btn-kw-primary" onclick="adNext(2)">
              Next: Declaration <i class="bi bi-arrow-right"></i>
            </button>
          </div>
        </div>

        <!-- Section 4: Declaration & Submit -->
        <div class="form-section" id="section-3" style="display:none">
          <div class="d-flex align-items-center gap-2 mb-4">
            <div class="bg-success bg-opacity-10 rounded-2 p-2"><i class="bi bi-patch-check-fill text-success fs-5"></i></div>
            <h5 class="fw-bold mb-0">Declaration &amp; Submission</h5>
          </div>

          <!-- Summary box -->
          <div class="p-4 rounded-3 mb-4" style="background:#f8fffe;border:1px solid #c3e6cb">
            <h6 class="fw-semibold mb-3 text-success"><i class="bi bi-list-check me-2"></i>Application Summary</h6>
            <div class="row g-2 small text-muted" id="adSummary">
              <div class="col-6"><span class="fw-semibold text-dark">Child:</span> <span id="sum-child">—</span></div>
              <div class="col-6"><span class="fw-semibold text-dark">Grade Applying:</span> <span id="sum-grade">—</span></div>
              <div class="col-6"><span class="fw-semibold text-dark">Parent/Guardian:</span> <span id="sum-parent">—</span></div>
              <div class="col-6"><span class="fw-semibold text-dark">Phone:</span> <span id="sum-phone">—</span></div>
              <div class="col-6"><span class="fw-semibold text-dark">Boarding:</span> <span id="sum-boarding">—</span></div>
              <div class="col-6"><span class="fw-semibold text-dark">Start Term:</span> <span id="sum-term">—</span></div>
            </div>
          </div>

          <div class="p-3 rounded-3 mb-4" style="background:#e8f5e9;border-left:4px solid var(--green)">
            <div class="d-flex align-items-start gap-3">
              <input type="checkbox" id="declarationCheck" class="mt-1 flex-shrink-0" required style="width:18px;height:18px">
              <label for="declarationCheck" class="small text-muted">
                I confirm that all information provided in this application is accurate and complete.
                I understand that Kingsway Preparatory School will contact me within 24 hours to schedule
                a placement assessment. Submission of this form does not guarantee enrolment.
              </label>
            </div>
          </div>

          <div id="adStatusMsg" style="display:none" class="mb-3 p-3 rounded-3 small fw-semibold"></div>

          <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <button type="button" class="btn-kw-outline" onclick="adPrev(3)">
              <i class="bi bi-arrow-left"></i> Back
            </button>
            <button type="submit" id="adSubmitBtn" class="btn-kw-primary px-5">
              <i class="bi bi-send-fill"></i>Submit Application
            </button>
          </div>
        </div>

      </form>

      <!-- Success state (hidden until submitted) -->
      <div id="admissionSuccess" style="display:none" class="text-center py-5">
        <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-4" style="width:80px;height:80px">
          <i class="bi bi-check-circle-fill text-success" style="font-size:2.5rem"></i>
        </div>
        <h4 class="fw-bold text-success mb-2">Application Received!</h4>
        <p class="text-muted mb-1">Your application reference number is:</p>
        <div class="display-6 fw-bold text-success mb-3" id="adRefDisplay"></div>
        <p class="text-muted small">Save this reference number. Our admissions team will call you within 24 hours.</p>
        <a href="<?= $appBase ?>/contact.php" class="btn-kw-outline mt-3">
          <i class="bi bi-telephone"></i>Contact Admissions
        </a>
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

<script>
/* ── Multi-step form navigation ── */
function adShowSection(n) {
  document.querySelectorAll('.form-section').forEach((s,i) => {
    s.style.display = i === n ? '' : 'none';
  });
  // Update step indicators
  document.querySelectorAll('[id^="step-circle-"]').forEach((c,i) => {
    const done = i < n, active = i === n;
    c.style.background = (done || active) ? 'var(--green)' : 'transparent';
    c.style.color = (done || active) ? '#fff' : 'var(--green)';
    c.innerHTML = done ? '<i class="bi bi-check-lg" style="font-size:.75rem"></i>' : (i+1);
  });
  document.querySelectorAll('[id^="step-label-"]').forEach((l,i) => {
    l.style.color = i <= n ? 'var(--green)' : '#999';
    l.style.fontWeight = i === n ? '700' : '500';
  });
}

function adNext(fromSection) {
  const section = document.getElementById('section-' + fromSection);
  const required = section.querySelectorAll('[required]');
  for (const el of required) {
    if (!el.value.trim()) {
      el.focus();
      el.style.borderColor = '#dc3545';
      el.addEventListener('input', () => el.style.borderColor = '', {once:true});
      return;
    }
  }
  if (fromSection === 2) adUpdateSummary();
  adShowSection(fromSection + 1);
  window.scrollTo({top: document.getElementById('apply').offsetTop - 80, behavior:'smooth'});
}

function adPrev(fromSection) {
  adShowSection(fromSection - 1);
  window.scrollTo({top: document.getElementById('apply').offsetTop - 80, behavior:'smooth'});
}

function adUpdateSummary() {
  const f = document.getElementById('admissionForm');
  const get = n => (f.elements[n]?.value || '—');
  document.getElementById('sum-child').textContent    = get('child_name');
  document.getElementById('sum-grade').textContent    = get('grade_applying');
  document.getElementById('sum-parent').textContent   = get('parent_name');
  document.getElementById('sum-phone').textContent    = get('parent_phone');
  const boardMap = {day:'Day Scholar', full_boarding:'Full Boarding', weekly_boarding:'Weekly Boarding'};
  document.getElementById('sum-boarding').textContent = boardMap[get('boarding_preference')] || 'Day Scholar';
  document.getElementById('sum-term').textContent     = get('preferred_start') || 'Not specified';
}

/* ── Form submission ── */
document.getElementById('admissionForm')?.addEventListener('submit', async function(e) {
  e.preventDefault();
  const check = document.getElementById('declarationCheck');
  if (!check.checked) {
    check.focus();
    return;
  }
  const btn = document.getElementById('adSubmitBtn');
  const msg = document.getElementById('adStatusMsg');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting…';
  const fd = new FormData(this);
  try {
    const res  = await fetch('<?= $appBase ?>/admissions.php', { method:'POST', body:fd });
    const json = await res.json();
    if (json.success) {
      document.getElementById('admissionFormWrap').querySelector('form').style.display = 'none';
      document.getElementById('admissionSuccess').style.display = '';
      document.getElementById('adRefDisplay').textContent = json.ref || '';
      window.scrollTo({top: document.getElementById('apply').offsetTop - 60, behavior:'smooth'});
    } else {
      msg.style.display = 'block';
      msg.style.background = '#fff3f3';
      msg.style.color = '#dc3545';
      msg.innerHTML = json.message || 'Submission failed. Please try again.';
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-send-fill"></i>Submit Application';
    }
  } catch {
    msg.style.display = 'block';
    msg.style.background = '#fff3f3';
    msg.style.color = '#dc3545';
    msg.textContent = 'Network error. Please try again or call <?= kw_school_stat('school_phone','0720 113 030') ?>.';
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-send-fill"></i>Submit Application';
  }
});
</script>

<?php include __DIR__ . '/public/layout/footer.php'; ?>