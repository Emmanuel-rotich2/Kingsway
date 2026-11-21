<?php
$appBase    = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '')),'/');

$pageTitle  = 'Careers';
$activePage = 'careers';
$pageScript = 'careers';
// Jobs, benefits, staff headcount and the careers stat cards are rendered by
// js/pages/public/careers.js via /api/website/{jobs,benefits,departments,stats,settings}.
require_once __DIR__ . '/public/layout/public_data.php';
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
        <div class="d-flex flex-column gap-3" id="careers-benefits">
        </div>
      </div>
      <div class="col-lg-7 reveal reveal-right">
        <div class="row g-3 text-center" id="careers-stats">
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
      <span class="badge bg-danger fs-6 px-3 py-2" id="careers-count">0 Open</span>
    </div>

    <div class="row g-4" id="careers-jobs">
    </div>

    <!-- General Application -->
    <div class="cta-banner rounded-4 mt-5 p-5 reveal">
      <div class="row align-items-center g-4 position-relative" style="z-index:1">
        <div class="col-lg-8">
          <h4 class="text-white fw-bold mb-2">Don't see your role listed?</h4>
          <p style="color:rgba(255,255,255,.8)" class="mb-0">We welcome speculative applications from talented individuals. Send us your CV and a cover letter and we'll keep you in our talent pool.</p>
        </div>
        <div class="col-lg-4 text-lg-end">
          <button class="btn-kw-gold" data-kw-onclick="openApplyModal(0,'General Application')">
            <i class="bi bi-envelope-fill"></i>Send Speculative CV
          </button>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Apply Modal -->
<div id="applyModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:9999;align-items:center;justify-content:center;padding:20px" data-kw-onclick="if(event.target===this)this.style.display='none'">
  <div class="bg-white rounded-4 p-4 shadow-lg" style="max-width:520px;width:100%;max-height:90vh;overflow-y:auto">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h4 class="fw-bold mb-0">Apply for <span id="applyJobTitleDisplay">Position</span></h4>
      <button class="btn-close" data-kw-onclick="document.getElementById('applyModal').style.display='none'"></button>
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

<script nonce="<?= csp_nonce() ?>">
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
    const res = await fetch('<?= $appBase ?>/api/public/job-applications', { method: 'POST', body: fd });
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