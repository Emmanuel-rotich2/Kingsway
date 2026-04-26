<?php
$appBase    = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '')),'/');
if ($appBase === '.') $appBase = '';
$pageTitle  = 'Downloads';
$activePage = 'downloads';
$categories = [
  'Admissions' => [
    ['Admission Application Form','PDF','245 KB','bi-file-earmark-pdf','#e91e63','downloads/admission_form.pdf'],
    ['School Prospectus '.date('Y'),'PDF','2.4 MB','bi-file-earmark-pdf','#e91e63','downloads/prospectus.pdf'],
    ['Admission Requirements','PDF','120 KB','bi-file-earmark-pdf','#e91e63','downloads/admission_requirements.pdf'],
    ['Transfer Request Form','PDF','85 KB','bi-file-earmark-pdf','#e91e63','downloads/transfer_form.pdf'],
  ],
  'Academic' => [
    ['School Calendar '.date('Y'),'PDF','310 KB','bi-file-earmark-pdf','#1976d2','downloads/calendar.pdf'],
    ['Term Dates '.date('Y'),'PDF','95 KB','bi-file-earmark-pdf','#1976d2','downloads/term_dates.pdf'],
    ['CBC Curriculum Guide','PDF','890 KB','bi-file-earmark-pdf','#1976d2','downloads/cbc_guide.pdf'],
    ['Exam Timetable Template','DOCX','45 KB','bi-file-earmark-word','#1976d2','downloads/exam_timetable.docx'],
  ],
  'Finance' => [
    ['Fee Structure '.date('Y'),'PDF','180 KB','bi-file-earmark-pdf','#198754','downloads/fee_structure.pdf'],
    ['Fee Payment Guide','PDF','95 KB','bi-file-earmark-pdf','#198754','downloads/payment_guide.pdf'],
    ['Bursary Application Form','PDF','210 KB','bi-file-earmark-pdf','#198754','downloads/bursary_form.pdf'],
  ],
  'Boarding' => [
    ['Boarding Requirements List','PDF','130 KB','bi-file-earmark-pdf','#ff9800','downloads/boarding_list.pdf'],
    ['Exeat Request Form','PDF','65 KB','bi-file-earmark-pdf','#ff9800','downloads/exeat_form.pdf'],
    ['Boarding Rules &amp; Guidelines','PDF','205 KB','bi-file-earmark-pdf','#ff9800','downloads/boarding_rules.pdf'],
  ],
  'Policies' => [
    ['School Rules &amp; Code of Conduct','PDF','340 KB','bi-file-earmark-pdf','#9c27b0','downloads/school_rules.pdf'],
    ['Anti-Bullying Policy','PDF','155 KB','bi-file-earmark-pdf','#9c27b0','downloads/anti_bullying.pdf'],
    ['Child Safeguarding Policy','PDF','420 KB','bi-file-earmark-pdf','#9c27b0','downloads/safeguarding.pdf'],
    ['Data Protection Policy','PDF','280 KB','bi-file-earmark-pdf','#9c27b0','downloads/data_protection.pdf'],
  ],
];
?>
<?php include __DIR__ . '/public/layout/header.php'; ?>

<div class="page-header">
  <div class="container position-relative" style="z-index:1">
    <nav aria-label="breadcrumb"><ol class="breadcrumb mb-2">
      <li class="breadcrumb-item"><a href="<?= $appBase ?>/index.php">Home</a></li>
      <li class="breadcrumb-item active">Downloads</li>
    </ol></nav>
    <h1 class="page-title">Downloads &amp; Resources</h1>
    <p class="mt-2" style="color:rgba(255,255,255,.7)">Forms, guides, policies and school documents available for download</p>
  </div>
</div>

<section class="section">
  <div class="container">

    <!-- Search -->
    <div class="row justify-content-center mb-5 reveal">
      <div class="col-lg-6">
        <div class="input-group shadow-sm">
          <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
          <input type="text" id="dlSearch" class="form-control border-start-0 py-3" placeholder="Search documents…">
        </div>
      </div>
    </div>

    <?php foreach ($categories as $cat => $docs): ?>
    <div class="mb-5 reveal dl-category">
      <div class="d-flex align-items-center gap-3 mb-3">
        <h4 class="fw-bold mb-0"><?= $cat ?></h4>
        <span class="badge bg-success"><?= count($docs) ?> documents</span>
      </div>
      <div class="row g-3">
        <?php foreach ($docs as $i => $doc): ?>
        <div class="col-lg-6 dl-item">
          <div class="download-item">
            <div class="download-icon" style="background:<?= $doc[4] ?>22;color:<?= $doc[4] ?>">
              <i class="bi <?= $doc[3] ?>"></i>
            </div>
            <div class="flex-grow-1">
              <div class="download-name"><?= $doc[0] ?></div>
              <div class="download-meta">
                <span class="tag me-1" style="background:<?= $doc[4] ?>22;color:<?= $doc[4] ?>;padding:2px 8px;font-size:.68rem"><?= $doc[1] ?></span>
                <span><?= $doc[2] ?></span>
              </div>
            </div>
            <a href="<?= $appBase ?>/<?= $doc[5] ?>" download class="download-btn" title="Download">
              <i class="bi bi-download"></i>
            </a>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>

    <!-- Note -->
    <div class="p-4 rounded-4 reveal" style="background:#fff8e1;border-left:4px solid var(--gold)">
      <h6 class="fw-bold mb-1"><i class="bi bi-info-circle-fill text-warning me-2"></i>Note on Downloads</h6>
      <p class="text-muted small mb-0">
        Some documents require Adobe Reader or Microsoft Office to open. If you cannot find what you're looking for,
        please <a href="<?= $appBase ?>/contact.php" class="text-success fw-semibold">contact our office</a> and we'll be happy to assist.
        Documents are updated at the start of each academic year.
      </p>
    </div>

  </div>
</section>

<script>
document.getElementById('dlSearch').addEventListener('input', function() {
  const q = this.value.toLowerCase();
  document.querySelectorAll('.dl-item').forEach(item => {
    const name = item.querySelector('.download-name')?.textContent.toLowerCase() || '';
    item.style.display = name.includes(q) ? '' : 'none';
  });
});
</script>

<?php include __DIR__ . '/public/layout/footer.php'; ?>
