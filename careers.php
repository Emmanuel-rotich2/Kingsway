<?php
$appBase    = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '')),'/');
if ($appBase === '.') $appBase = '';
$pageTitle  = 'Careers';
$activePage = 'careers';
$jobs = [
  ['title'=>'Class Teacher — Grade 4','dept'=>'Teaching','type'=>'Full-Time','location'=>'Londiani Campus','deadline'=>date('Y-m-d',strtotime('+30 days')),'desc'=>'We are looking for a dedicated and passionate Grade 4 class teacher with strong CBC implementation skills. Must hold a P1 or B.Ed certificate.','requirements'=>['P1 or B.Ed (Primary Education)','TSC Registration (mandatory)','Minimum 2 years teaching experience','Strong CBC knowledge preferred'],'color'=>'#198754'],
  ['title'=>'Mathematics & Science Teacher (Grade 7–9)','dept'=>'Teaching','type'=>'Full-Time','location'=>'Londiani Campus','deadline'=>date('Y-m-d',strtotime('+25 days')),'desc'=>'Seeking an experienced JSS Mathematics and Integrated Science teacher to prepare Grade 9 students for KJSEA.','requirements'=>['B.Ed (Science/Mathematics)','TSC Registration','Experience with CBC Junior Secondary','Strong exam preparation track record'],'color'=>'#1976d2'],
  ['title'=>'School Nurse','dept'=>'Health','type'=>'Full-Time','location'=>'Londiani Campus','deadline'=>date('Y-m-d',strtotime('+20 days')),'desc'=>'Qualified nurse to manage the school sick bay, student health records, and first aid. Boarding school experience is an advantage.','requirements'=>['Diploma or Degree in Nursing','Kenya Nursing Council registration','First Aid certification','Experience in school health preferred'],'color'=>'#e91e63'],
  ['title'=>'ICT Technician','dept'=>'Technology','type'=>'Full-Time','location'=>'Londiani Campus','deadline'=>date('Y-m-d',strtotime('+35 days')),'desc'=>'Maintain the school computer lab, network infrastructure, and digital learning tools. Support teachers in integrating technology into lessons.','requirements'=>['Diploma/Degree in ICT or Computer Science','Networking and hardware skills','Experience with Windows/Linux environments','Ability to train staff and students'],'color'=>'#ff9800'],
  ['title'=>'Accounts Clerk','dept'=>'Finance','type'=>'Full-Time','location'=>'Londiani Campus','deadline'=>date('Y-m-d',strtotime('+28 days')),'desc'=>'Support the school bursar in fee collection, financial records, and day-to-day accounting tasks. Experience with school ERP systems is a plus.','requirements'=>['CPA Part 1 or Diploma in Accounting','Knowledge of school fee management','Attention to detail and integrity','Computer literacy (Excel, accounting software)'],'color'=>'#00695c'],
  ['title'=>'Games Teacher & Sports Coach','dept'=>'Co-Curricular','type'=>'Full-Time','location'=>'Londiani Campus','deadline'=>date('Y-m-d',strtotime('+15 days')),'desc'=>'Lead the school sports programs including football, athletics, and netball. Prepare teams for inter-schools competitions.','requirements'=>['Diploma or Degree in Physical Education','TSC Registration or coaching certificate','Experience in competitive school sports','Ability to motivate and discipline students'],'color'=>'#9c27b0'],
];
?>
<?php include __DIR__ . '/public/layout/header.php'; ?>

<div class="page-header">
  <div class="container position-relative" style="z-index:1">
    <nav aria-label="breadcrumb"><ol class="breadcrumb mb-2">
      <li class="breadcrumb-item"><a href="<?= $appBase ?>/index.php">Home</a></li>
      <li class="breadcrumb-item active">Careers</li>
    </ol></nav>
    <h1 class="page-title">Careers at Kingsway</h1>
    <p class="mt-2" style="color:rgba(255,255,255,.7)">Join our team of passionate educators and professionals</p>
  </div>
</div>

<!-- Why work here -->
<section class="section section-alt">
  <div class="container">
    <div class="row align-items-center g-5">
      <div class="col-lg-5 reveal reveal-left">
        <div class="section-label"><span>Why Kingsway</span></div>
        <h2 class="section-title">Why Work <span>With Us?</span></h2>
        <p class="section-subtitle mb-4">We believe great teachers make great schools. We invest in our staff and provide an environment where you can thrive professionally and personally.</p>
        <div class="d-flex flex-column gap-3">
          <?php foreach ([['bi-cash-coin','Competitive Salary','TSC-scale pay with timely disbursement and annual reviews.'],['bi-graph-up-arrow','Career Growth','Funded professional development, promotions, and CPD programs.'],['bi-house-fill','Staff Housing','On-campus accommodation available for teaching staff.'],['bi-heart-pulse','Medical Cover','Staff and dependants medical insurance scheme.'],['bi-calendar2-check','Work-Life Balance','Generous leave entitlement and a supportive management team.']] as $b): ?>
          <div class="d-flex align-items-start gap-3">
            <div class="bg-success bg-opacity-10 rounded-2 p-2 flex-shrink-0">
              <i class="bi <?= $b[0] ?> text-success fs-5"></i>
            </div>
            <div>
              <div class="fw-semibold small"><?= $b[1] ?></div>
              <div class="text-muted" style="font-size:.82rem"><?= $b[2] ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="col-lg-7 reveal reveal-right">
        <div class="row g-3 text-center">
          <?php foreach ([['80+','Qualified Staff'],['15+','Years Average Experience'],['98%','Staff Retention Rate'],['100%','CPD Participation']] as $s): ?>
          <div class="col-6">
            <div class="p-4 bg-white border rounded-4 h-100">
              <div class="stat-number text-success mb-1"><?= $s[0] ?></div>
              <div class="text-muted small"><?= $s[1] ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Job Listings -->
<section class="section">
  <div class="container">
    <div class="d-flex align-items-center justify-content-between mb-5 reveal">
      <div>
        <div class="section-label"><span>Open Positions</span></div>
        <h2 class="section-title mb-0">Current <span>Vacancies</span></h2>
      </div>
      <span class="badge bg-danger fs-6 px-3 py-2"><?= count($jobs) ?> Open</span>
    </div>

    <div class="row g-4">
      <?php foreach ($jobs as $i => $job): $dl = new DateTime($job['deadline']); ?>
      <div class="col-lg-6">
        <div class="card-modern p-4 h-100 reveal delay-<?= ($i%3)+1 ?>">
          <div class="d-flex align-items-start gap-3 mb-3">
            <div class="job-icon" style="background:<?= $job['color'] ?>">
              <i class="bi bi-briefcase-fill"></i>
            </div>
            <div class="flex-grow-1">
              <div class="d-flex flex-wrap gap-2 mb-1">
                <span class="job-type" style="background:<?= $job['color'] ?>22;color:<?= $job['color'] ?>"><?= $job['type'] ?></span>
                <span class="job-type" style="background:#f1f5f9;color:#64748b"><?= $job['dept'] ?></span>
              </div>
              <h5 class="job-title"><?= htmlspecialchars($job['title']) ?></h5>
              <div class="job-meta">
                <span><i class="bi bi-geo-alt text-success"></i><?= $job['location'] ?></span>
                <span><i class="bi bi-calendar-x text-danger"></i>Closes <?= $dl->format('d M Y') ?></span>
              </div>
            </div>
          </div>
          <p class="text-muted small mb-3"><?= htmlspecialchars($job['desc']) ?></p>
          <div class="mb-3">
            <div class="small fw-semibold mb-2 text-dark">Requirements:</div>
            <ul class="list-unstyled mb-0">
              <?php foreach ($job['requirements'] as $req): ?>
              <li class="d-flex align-items-start gap-2 small text-muted mb-1">
                <i class="bi bi-check-circle-fill text-success mt-1 flex-shrink-0"></i><?= $req ?>
              </li>
              <?php endforeach; ?>
            </ul>
          </div>
          <div class="mt-auto pt-3 border-top d-flex gap-2">
            <button class="btn-kw-primary flex-grow-1 justify-content-center py-2" style="font-size:.85rem"
              onclick="document.getElementById('applyModal').style.display='flex';document.getElementById('applyJobTitle').value='<?= htmlspecialchars($job['title']) ?>'">
              <i class="bi bi-send-fill"></i>Apply Now
            </button>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- General Application -->
    <div class="cta-banner rounded-4 mt-5 p-5 reveal">
      <div class="row align-items-center g-4 position-relative" style="z-index:1">
        <div class="col-lg-8">
          <h4 class="text-white fw-bold mb-2">Don't see your role listed?</h4>
          <p style="color:rgba(255,255,255,.8)" class="mb-0">We welcome speculative applications from talented individuals. Send us your CV and a cover letter and we'll keep you in our talent pool.</p>
        </div>
        <div class="col-lg-4 text-lg-end">
          <a href="mailto:info@kingswaypreparatoryschool.sc.ke?subject=Speculative Application" class="btn-kw-gold">
            <i class="bi bi-envelope-fill"></i>Send Speculative CV
          </a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Apply Modal -->
<div id="applyModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:9999;align-items:center;justify-content:center;padding:20px" onclick="if(event.target===this)this.style.display='none'">
  <div class="bg-white rounded-4 p-5 shadow-lg" style="max-width:520px;width:100%;max-height:90vh;overflow-y:auto">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h4 class="fw-bold mb-0">Apply for Position</h4>
      <button class="btn-close" onclick="document.getElementById('applyModal').style.display='none'"></button>
    </div>
    <form onsubmit="event.preventDefault();document.getElementById('applyModal').style.display='none';alert('Application submitted! We will be in touch.')">
      <input type="hidden" id="applyJobTitle" name="job_title">
      <div class="row g-3">
        <div class="col-6">
          <label class="form-label small fw-semibold">First Name *</label>
          <input type="text" class="form-control-kw" required>
        </div>
        <div class="col-6">
          <label class="form-label small fw-semibold">Last Name *</label>
          <input type="text" class="form-control-kw" required>
        </div>
        <div class="col-12">
          <label class="form-label small fw-semibold">Email Address *</label>
          <input type="email" class="form-control-kw" required>
        </div>
        <div class="col-12">
          <label class="form-label small fw-semibold">Phone Number *</label>
          <input type="tel" class="form-control-kw" required>
        </div>
        <div class="col-12">
          <label class="form-label small fw-semibold">TSC Number (if applicable)</label>
          <input type="text" class="form-control-kw">
        </div>
        <div class="col-12">
          <label class="form-label small fw-semibold">Upload CV (PDF/DOC) *</label>
          <input type="file" class="form-control-kw" accept=".pdf,.doc,.docx" required style="padding:10px">
        </div>
        <div class="col-12">
          <label class="form-label small fw-semibold">Cover Letter</label>
          <textarea class="form-control-kw" rows="4" placeholder="Tell us why you're the right fit…"></textarea>
        </div>
        <div class="col-12">
          <button type="submit" class="btn-kw-primary w-100 justify-content-center py-3">
            <i class="bi bi-send-fill"></i>Submit Application
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/public/layout/footer.php'; ?>
