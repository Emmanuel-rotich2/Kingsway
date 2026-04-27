<?php
$appBase    = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '')),'/');
if ($appBase === '.') $appBase = '';
$pageTitle  = 'Careers';
$activePage = 'careers';
require_once __DIR__ . '/public/layout/public_data.php';
$jobs     = kw_open_jobs();
$benefits = kw_careers_benefits();
$staffStats = [
    [kw_school_stat('careers_stat_staff','80+'),      'Qualified Staff'],
    [kw_school_stat('careers_stat_experience','15+'), 'Years Avg Experience'],
    [kw_school_stat('careers_stat_retention','98%'),  'Staff Retention Rate'],
    [kw_school_stat('careers_stat_cpd','100%'),       'CPD Participation'],
];

/* ── Handle application POST ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['apply_first_name'])) {
    header('Content-Type: application/json');
    $first = trim($_POST['apply_first_name'] ?? '');
    $last  = trim($_POST['apply_last_name']  ?? '');
    $email = filter_var($_POST['apply_email'] ?? '', FILTER_VALIDATE_EMAIL);
    $phone = trim($_POST['apply_phone'] ?? '');
    $tsc   = trim($_POST['apply_tsc'] ?? '');
    $jobId = (int)($_POST['apply_job_id'] ?? 0);

    // Handle CV upload
    $cvFilename = null;
    if (!empty($_FILES['apply_cv']['name']) && $_FILES['apply_cv']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['apply_cv']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['pdf','doc','docx'])) {
            $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', "$first-$last-CV.".date('Ymd'));
            $dest = __DIR__ . '/uploads/cvs/' . $safeName;
            if (move_uploaded_file($_FILES['apply_cv']['tmp_name'], $dest)) {
                $cvFilename = $safeName;
            }
        }
    }

    $job = kw_job_by_id($jobId);
    kw_save_job_application([
        'job_id'=>$jobId, 'job_title'=>$job['title'] ?? 'General Application',
        'first_name'=>$first, 'last_name'=>$last, 'email'=>$email,
        'phone'=>$phone, 'tsc_number'=>$tsc, 'cv_filename'=>$cvFilename,
        'cover_letter'=>trim($_POST['apply_cover'] ?? ''),
        'ip'=>$_SERVER['REMOTE_ADDR'] ?? null
    ]);
    echo json_encode(['success'=>true,'message'=>'Application submitted! We will be in touch.']);
    exit;
}
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
          <?php foreach ($benefits as $b): ?>
          <div class="d-flex align-items-start gap-3">
            <div class="bg-success bg-opacity-10 rounded-2 p-2 flex-shrink-0">
              <i class="bi <?= htmlspecialchars($b['icon']) ?> text-success fs-5"></i>
            </div>
            <div>
              <div class="fw-semibold small"><?= htmlspecialchars($b['title']) ?></div>
              <div class="text-muted" style="font-size:.82rem"><?= htmlspecialchars($b['description']) ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="col-lg-7 reveal reveal-right">
        <div class="row g-3 text-center">
          <?php foreach ($staffStats as $s): ?>
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
      <?php foreach ($jobs as $i => $job):
        $dl = new DateTime($job['deadline']);
        $req = json_decode($job['requirements'] ?? '[]', true) ?: [];
      ?>
      <div class="col-lg-6">
        <div class="card-modern p-4 h-100 reveal delay-<?= ($i%3)+1 ?>">
          <div class="d-flex align-items-start gap-3 mb-3">
            <div class="job-icon" style="background:<?= htmlspecialchars($job['color'] ?? '#198754') ?>">
              <i class="bi bi-briefcase-fill"></i>
            </div>
            <div class="flex-grow-1">
              <div class="d-flex flex-wrap gap-2 mb-1">
                <span class="job-type" style="background:<?= htmlspecialchars($job['color'] ?? '#198754') ?>22;color:<?= htmlspecialchars($job['color'] ?? '#198754') ?>"><?= htmlspecialchars($job['job_type']) ?></span>
                <span class="job-type" style="background:#f1f5f9;color:#64748b"><?= htmlspecialchars($job['department']) ?></span>
              </div>
              <h5 class="job-title"><?= htmlspecialchars($job['title']) ?></h5>
              <div class="job-meta">
                <span><i class="bi bi-geo-alt text-success"></i><?= htmlspecialchars($job['location']) ?></span>
                <span><i class="bi bi-calendar-x text-danger"></i>Closes <?= $dl->format('d M Y') ?></span>
              </div>
            </div>
          </div>
          <p class="text-muted small mb-3"><?= htmlspecialchars(mb_strimwidth(strip_tags($job['description']),0,150,'…')) ?></p>
          <?php if (!empty($req)): ?>
          <div class="mb-3">
            <div class="small fw-semibold mb-2 text-dark">Key Requirements:</div>
            <ul class="list-unstyled mb-0">
              <?php foreach (array_slice($req, 0, 3) as $r): ?>
              <li class="d-flex align-items-start gap-2 small text-muted mb-1">
                <i class="bi bi-check-circle-fill text-success mt-1 flex-shrink-0"></i><?= htmlspecialchars($r) ?>
              </li>
              <?php endforeach; ?>
              <?php if (count($req) > 3): ?>
              <li class="small text-muted ms-4">+<?= count($req)-3 ?> more</li>
              <?php endif; ?>
            </ul>
          </div>
          <?php endif; ?>
          <div class="mt-auto pt-3 border-top d-flex gap-2">
            <a href="<?= $appBase ?>/job-detail.php?id=<?= (int)$job['id'] ?>" class="btn-kw-outline flex-grow-1 justify-content-center py-2" style="font-size:.85rem">
              <i class="bi bi-info-circle"></i>View Details
            </a>
            <button class="btn-kw-primary justify-content-center py-2" style="font-size:.85rem"
              onclick="openApplyModal(<?= (int)$job['id'] ?>, '<?= htmlspecialchars(addslashes($job['title'])) ?>')">
              <i class="bi bi-send-fill"></i>Apply
            </button>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <?php if (empty($jobs)): ?>
    <div class="text-center py-5">
      <i class="bi bi-briefcase fs-1 text-muted d-block mb-3"></i>
      <p class="text-muted">No open vacancies at the moment. Check back soon!</p>
    </div>
    <?php endif; ?>

    <!-- General Application -->
    <div class="cta-banner rounded-4 mt-5 p-5 reveal">
      <div class="row align-items-center g-4 position-relative" style="z-index:1">
        <div class="col-lg-8">
          <h4 class="text-white fw-bold mb-2">Don't see your role listed?</h4>
          <p style="color:rgba(255,255,255,.8)" class="mb-0">We welcome speculative applications from talented individuals. Send us your CV and a cover letter and we'll keep you in our talent pool.</p>
        </div>
        <div class="col-lg-4 text-lg-end">
          <button class="btn-kw-gold" onclick="openApplyModal(0,'General Application')">
            <i class="bi bi-envelope-fill"></i>Send Speculative CV
          </button>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Apply Modal -->
<div id="applyModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:9999;align-items:center;justify-content:center;padding:20px" onclick="if(event.target===this)this.style.display='none'">
  <div class="bg-white rounded-4 p-4 shadow-lg" style="max-width:520px;width:100%;max-height:90vh;overflow-y:auto">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h4 class="fw-bold mb-0">Apply for <span id="applyJobTitleDisplay">Position</span></h4>
      <button class="btn-close" onclick="document.getElementById('applyModal').style.display='none'"></button>
    </div>
    <form id="applyForm" enctype="multipart/form-data">
      <input type="hidden" id="applyJobId" name="apply_job_id" value="0">
      <div class="row g-3">
        <div class="col-6">
          <label class="form-label small fw-semibold">First Name *</label>
          <input type="text" name="apply_first_name" class="form-control-kw" required>
        </div>
        <div class="col-6">
          <label class="form-label small fw-semibold">Last Name *</label>
          <input type="text" name="apply_last_name" class="form-control-kw" required>
        </div>
        <div class="col-12">
          <label class="form-label small fw-semibold">Email Address *</label>
          <input type="email" name="apply_email" class="form-control-kw" required>
        </div>
        <div class="col-12">
          <label class="form-label small fw-semibold">Phone Number *</label>
          <input type="tel" name="apply_phone" class="form-control-kw" required>
        </div>
        <div class="col-12">
          <label class="form-label small fw-semibold">TSC Number (if applicable)</label>
          <input type="text" name="apply_tsc" class="form-control-kw">
        </div>
        <div class="col-12">
          <label class="form-label small fw-semibold">Upload CV (PDF/DOC) *</label>
          <input type="file" name="apply_cv" class="form-control-kw" accept=".pdf,.doc,.docx" required style="padding:10px">
        </div>
        <div class="col-12">
          <label class="form-label small fw-semibold">Cover Letter</label>
          <textarea name="apply_cover" class="form-control-kw" rows="3" placeholder="Tell us why you're the right fit…"></textarea>
        </div>
        <div class="col-12" id="applyStatusMsg" style="display:none" class="small"></div>
        <div class="col-12">
          <button type="submit" id="applySubmitBtn" class="btn-kw-primary w-100 justify-content-center py-3">
            <i class="bi bi-send-fill"></i>Submit Application
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
function openApplyModal(jobId, jobTitle) {
  document.getElementById('applyJobId').value = jobId;
  document.getElementById('applyJobTitleDisplay').textContent = jobTitle;
  document.getElementById('applyStatusMsg').style.display = 'none';
  document.getElementById('applySubmitBtn').disabled = false;
  document.getElementById('applySubmitBtn').innerHTML = '<i class="bi bi-send-fill"></i>Submit Application';
  document.getElementById('applyModal').style.display = 'flex';
}

document.getElementById('applyForm')?.addEventListener('submit', async function(e) {
  e.preventDefault();
  const btn = document.getElementById('applySubmitBtn');
  const msg = document.getElementById('applyStatusMsg');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting…';
  const fd = new FormData(this);
  try {
    const res = await fetch('<?= $appBase ?>/careers.php', { method: 'POST', body: fd });
    const json = await res.json();
    if (json.success) {
      msg.style.display = 'block';
      msg.style.color = '#198754';
      msg.textContent = 'Application submitted! We will be in touch.';
      setTimeout(() => { document.getElementById('applyModal').style.display = 'none'; }, 2000);
    } else {
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-send-fill"></i>Submit Application';
      msg.style.display = 'block';
      msg.style.color = '#dc3545';
      msg.textContent = json.message || 'Submission failed. Please try again.';
    }
  } catch {
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-send-fill"></i>Submit Application';
    msg.style.display = 'block';
    msg.style.color = '#dc3545';
    msg.textContent = 'Network error. Please try again.';
  }
});
</script>

<?php include __DIR__ . '/public/layout/footer.php'; ?>