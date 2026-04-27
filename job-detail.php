<?php
$appBase    = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '')),'/');
if ($appBase === '.') $appBase = '';
require_once __DIR__ . '/public/layout/public_data.php';

$id   = (int)($_GET['id'] ?? 0);
$job  = $id ? kw_job_by_id($id) : null;
if (!$job) { header("Location: {$appBase}/careers.php"); exit; }

$pageTitle  = htmlspecialchars($job['title']);
$activePage = 'careers';
$req = json_decode($job['requirements'] ?? '[]', true) ?: [];
$res = json_decode($job['responsibilities'] ?? '[]', true) ?: [];
$dl  = new DateTime($job['deadline']);
?>
<?php include __DIR__ . '/public/layout/header.php'; ?>

<div class="page-header">
  <div class="container position-relative" style="z-index:1">
    <nav aria-label="breadcrumb"><ol class="breadcrumb mb-2">
      <li class="breadcrumb-item"><a href="<?= $appBase ?>/index.php">Home</a></li>
      <li class="breadcrumb-item"><a href="<?= $appBase ?>/careers.php">Careers</a></li>
      <li class="breadcrumb-item active"><?= htmlspecialchars(mb_strimwidth($job['title'],0,40,'…')) ?></li>
    </ol></nav>
    <h1 class="page-title" style="font-size:clamp(1.4rem,3vw,2rem)"><?= htmlspecialchars($job['title']) ?></h1>
  </div>
</div>

<section class="section">
  <div class="container">
    <div class="row g-5">

      <!-- Main content -->
      <div class="col-lg-8">

        <!-- Job meta card -->
        <div class="card-modern p-4 mb-4 reveal">
          <div class="d-flex flex-wrap gap-3 mb-3">
            <span class="tag text-white" style="background:<?= htmlspecialchars($job['color'] ?? '#198754') ?>"><?= htmlspecialchars($job['job_type']) ?></span>
            <span class="tag bg-white border text-dark"><?= htmlspecialchars($job['department']) ?></span>
            <span class="tag bg-white border text-dark"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($job['location']) ?></span>
          </div>
          <div class="d-flex flex-wrap gap-4 text-muted small">
            <span><i class="bi bi-calendar-x text-danger me-1"></i>Closes: <?= $dl->format('d M Y') ?></span>
            <span><i class="bi bi-clock me-1"></i><?= htmlspecialchars($job['job_type']) ?></span>
          </div>
        </div>

        <!-- Description -->
        <div class="card-modern p-4 mb-4 reveal">
          <h5 class="fw-bold mb-3"><i class="bi bi-file-text text-success me-2"></i>About This Role</h5>
          <div class="article-body"><?= nl2br(htmlspecialchars($job['description'])) ?></div>
        </div>

        <!-- Responsibilities -->
        <?php if (!empty($res)): ?>
        <div class="card-modern p-4 mb-4 reveal">
          <h5 class="fw-bold mb-3"><i class="bi bi-list-check text-success me-2"></i>Responsibilities</h5>
          <ul class="list-unstyled">
            <?php foreach ($res as $r): ?>
            <li class="d-flex align-items-start gap-2 mb-2">
              <i class="bi bi-check-circle-fill text-success mt-1 flex-shrink-0"></i>
              <span><?= htmlspecialchars($r) ?></span>
            </li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>

        <!-- Requirements -->
        <?php if (!empty($req)): ?>
        <div class="card-modern p-4 mb-4 reveal">
          <h5 class="fw-bold mb-3"><i class="bi bi-person-check text-success me-2"></i>Requirements</h5>
          <ul class="list-unstyled">
            <?php foreach ($req as $r): ?>
            <li class="d-flex align-items-start gap-2 mb-2">
              <i class="bi bi-check-circle-fill text-success mt-1 flex-shrink-0"></i>
              <span><?= htmlspecialchars($r) ?></span>
            </li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>

        <!-- Apply CTA -->
        <div class="d-flex flex-wrap gap-3 mt-4">
          <a href="<?= $appBase ?>/careers.php" class="btn-kw-outline">
            <i class="bi bi-arrow-left"></i>All Vacancies
          </a>
          <button class="btn-kw-primary" onclick="openApplyModal(<?= (int)$job['id'] ?>, '<?= htmlspecialchars(addslashes($job['title'])) ?>')">
            <i class="bi bi-send-fill"></i>Apply for This Position
          </button>
        </div>
      </div>

      <!-- Sidebar -->
      <div class="col-lg-4">

        <!-- Quick apply card -->
        <div class="card-modern p-4 mb-4 reveal">
          <h6 class="fw-bold mb-3 text-success"><i class="bi bi-send me-2"></i>Quick Apply</h6>
          <form id="quickApplyForm">
            <input type="hidden" name="apply_job_id" value="<?= (int)$job['id'] ?>">
            <div class="mb-2">
              <input type="text" name="apply_first_name" class="form-control-kw" placeholder="First Name *" required>
            </div>
            <div class="mb-2">
              <input type="text" name="apply_last_name" class="form-control-kw" placeholder="Last Name *" required>
            </div>
            <div class="mb-2">
              <input type="email" name="apply_email" class="form-control-kw" placeholder="Email *" required>
            </div>
            <div class="mb-2">
              <input type="tel" name="apply_phone" class="form-control-kw" placeholder="Phone *" required>
            </div>
            <div class="mb-2">
              <input type="text" name="apply_tsc" class="form-control-kw" placeholder="TSC Number">
            </div>
            <div class="mb-3">
              <label class="form-label small fw-semibold">Upload CV (PDF/DOC) *</label>
              <input type="file" name="apply_cv" class="form-control-kw" accept=".pdf,.doc,.docx" required style="padding:8px">
            </div>
            <div class="mb-3">
              <textarea name="apply_cover" class="form-control-kw" rows="3" placeholder="Brief cover note (optional)"></textarea>
            </div>
            <div id="qaStatusMsg" style="display:none" class="small fw-semibold mb-2"></div>
            <button type="submit" id="qaSubmitBtn" class="btn-kw-primary w-100 justify-content-center py-2">
              <i class="bi bi-send-fill"></i>Submit Application
            </button>
          </form>
        </div>

        <!-- Why work here -->
        <div class="card-modern p-4 reveal">
          <h6 class="fw-bold mb-3"><i class="bi bi-heart text-success me-2"></i>Why Kingsway?</h6>
          <?php foreach ([['bi-cash-coin','Competitive Salary','TSC-scale with annual reviews'],['bi-house-fill','Staff Housing','On-campus accommodation'],['bi-heart-pulse','Medical Cover','Staff and dependants'],['bi-calendar2-check','Work-Life Balance','Generous leave and support']] as $b): ?>
          <div class="d-flex align-items-start gap-2 mb-3">
            <i class="bi <?= $b[0] ?> text-success flex-shrink-0 mt-1"></i>
            <div>
              <div class="small fw-semibold"><?= $b[1] ?></div>
              <div class="text-muted" style="font-size:.78rem"><?= $b[2] ?></div>
            </div>
          </div>
          <?php endforeach; ?>
          <a href="<?= $appBase ?>/careers.php" class="btn-kw-outline w-100 justify-content-center mt-2" style="font-size:.85rem">
            Learn More About Benefits
          </a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Apply Modal (shared) -->
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
          <textarea name="apply_cover" class="form-control-kw" rows="3" placeholder="Tell us why you're the right fit..."></textarea>
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

async function submitApplication(formId, statusId, submitBtnId) {
  const form = document.getElementById(formId);
  const btn  = document.getElementById(submitBtnId);
  const msg  = document.getElementById(statusId);
  if (!form) return;
  form.addEventListener('submit', async function(e) {
    e.preventDefault();
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting…';
    const fd = new FormData(form);
    try {
      const res = await fetch('<?= $appBase ?>/careers.php', { method:'POST', body:fd });
      const json = await res.json();
      msg.style.display = 'block';
      if (json.success) {
        msg.style.color = '#198754';
        msg.textContent = 'Application submitted! We will be in touch.';
        setTimeout(() => {
          document.getElementById('applyModal').style.display = 'none';
        }, 2000);
      } else {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-send-fill"></i>Submit Application';
        msg.style.color = '#dc3545';
        msg.textContent = json.message || 'Submission failed.';
      }
    } catch {
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-send-fill"></i>Submit Application';
      msg.style.display = 'block';
      msg.style.color = '#dc3545';
      msg.textContent = 'Network error. Please try again.';
    }
  });
}
submitApplication('applyForm', 'applyStatusMsg', 'applySubmitBtn');
submitApplication('quickApplyForm', 'qaStatusMsg', 'qaSubmitBtn');
</script>

<?php include __DIR__ . '/public/layout/footer.php'; ?>