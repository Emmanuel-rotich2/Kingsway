<?php
/**
 * parents/_foot.php — shared bottom of every parent-portal page.
 *
 * Closes the main area, renders the footer, the shared "Apply for Admission"
 * and M-Pesa modals, and loads the common parent scripts plus the page
 * controller referenced by $parentPageScript. Always include AFTER the page
 * body markup.
 */
declare(strict_types=1);

$appBase = $appBase ?? '';
$parentPageScript = $parentPageScript ?? 'parents/dashboard';
$familyStaffMode = $familyStaffMode ?? (($_GET['staff'] ?? '') === '1');
$ppTerms  = $ppTerms  ?? [];
$ppGrades = $ppGrades ?? [];
$ppAdminGradeOptions = $ppAdminGradeOptions ?? ($ppGrades ?: ['PP1','PP2','Grade 1','Grade 2','Grade 3','Grade 4','Grade 5','Grade 6','Grade 7','Grade 8','Grade 9']);
?>
</main><!-- /.pp-main -->

<footer class="pp-footer no-print">
  <span>© <?= date('Y') ?> Kingsway Preparatory School · “In God We Soar”</span>
  <span>
    <a href="<?= $appBase ?>/index.php?route=contact">Contact school office</a> ·
    <a href="<?= $appBase ?>/index.php">Public website</a> ·
    <a href="<?= $appBase ?>/parent_portal.php?route=uniform-catalog<?= $familyStaffMode ? '&staff=1' : '' ?>">Uniform Store</a>
  </span>
</footer>

<!-- ═══════ M-Pesa Payment Modal (shared) ═════════════════════════════════ -->
<div class="modal fade" id="mpesaPaymentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow rounded-4">
      <div class="modal-header pp-mpesa-header text-white">
        <h5 class="modal-title"><i class="bi bi-phone me-2"></i>M-Pesa Payment</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
        <div id="mpesaPaymentForm">
          <input type="hidden" id="mpesaPurpose" value="fees">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="badge bg-success px-3 py-2" id="mpesaPurposeBadge"><i class="bi bi-phone me-1"></i>Fees payment</span>
            <small class="text-muted" id="mpesaSubtitle">You'll receive an M-Pesa prompt on your phone — enter your PIN to complete.</small>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Learner</label>
            <select id="mpesaStudent" class="form-select"></select>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Amount (KES)</label>
            <input type="number" id="mpesaAmount" class="form-control" min="1" step="any">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">M-Pesa Phone Number</label>
            <input type="tel" id="mpesaPhone" class="form-control" placeholder="2547XXXXXXXX" data-phone-canonical>
            <div class="form-text">Edit if you'd rather pay from a different registered M-Pesa number</div>
          </div>
          <div id="mpesaError" class="alert alert-danger d-none"></div>
          <button class="btn btn-success w-100 py-2 fw-semibold" type="button" id="btnMpesaPay">
            <span class="spinner-border spinner-border-sm me-2 d-none" id="mpesaSpinner"></span>
            <i class="bi bi-send me-2"></i><span id="mpesaPayLabel">Pay now</span>
          </button>
        </div>
        <div id="mpesaWaiting" class="text-center py-4" style="display:none">
          <div class="spinner-border text-success mb-3" style="width:3rem;height:3rem"></div>
          <h6>STK Push Sent!</h6>
          <p class="text-muted small">Please check your phone and enter your M-Pesa PIN to complete the payment.</p>
          <div id="mpesaPollingStatus" class="text-muted small">Waiting for confirmation...</div>
          <button class="btn btn-outline-secondary btn-sm mt-3" type="button" id="btnMpesaDone"><i class="bi bi-check-lg me-1"></i>I've Completed the Payment</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ═══════ Apply for Admission Modal (shared) ════════════════════════════ -->
<div class="modal fade" id="applyAdmissionModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content border-0 shadow rounded-4">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Apply for Admission</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
        <form id="applyAdmissionForm" enctype="multipart/form-data">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">Child's Full Name <span class="text-danger">*</span></label>
              <input type="text" name="child_name" class="form-control" placeholder="As on birth certificate" required data-kw-validate="name">
            </div>
            <div class="col-md-3">
              <label class="form-label fw-semibold">Date of Birth</label>
              <input type="date" name="child_dob" class="form-control" data-kw-validate="dob">
            </div>
            <div class="col-md-3">
              <label class="form-label fw-semibold">Gender <span class="text-danger">*</span></label>
              <select name="child_gender" class="form-select" required>
                <option value="">Select</option>
                <option value="male">Male</option>
                <option value="female">Female</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Grade Applying For <span class="text-danger">*</span></label>
              <select name="grade_applying" id="ppGradeSelect" class="form-select" required>
                <option value="">Select grade</option>
                <?php foreach ($ppAdminGradeOptions as $grade): ?>
                <option value="<?= htmlspecialchars((string) $grade, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $grade, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Admission Application Window <span class="text-danger">*</span></label>
              <select name="admission_window_id" id="ppAdmissionWindow" class="form-select" required>
                <?php if (!$ppTerms): ?>
                <option value="">No admission windows open right now</option>
                <?php else: foreach ($ppTerms as $term): ?>
                <option value="<?= (int) ($term['admission_window_id'] ?? $term['id'] ?? 0) ?>"
                        data-term-id="<?= (int) ($term['target_term_id'] ?? $term['id'] ?? 0) ?>">
                  <?= htmlspecialchars((string) ($term['admission_window_label'] ?? (($term['name'] ?? 'Term') . ' ' . ($term['year'] ?? ''))), ENT_QUOTES, 'UTF-8') ?>
                </option>
                <?php endforeach; endif; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Day Scholar or Boarding</label>
              <select name="boarding_preference" class="form-select">
                <option value="day">Day Scholar</option>
                <option value="full_boarding">Full Boarding (Mon – Fri)</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Parent / Guardian</label>
              <input type="text" name="parent_name" class="form-control" id="ppParentName" readonly aria-readonly="true">
              <div class="form-text">Your saved portal profile will be used automatically.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Relationship to Child <span class="text-danger">*</span></label>
              <select name="parent_relationship" class="form-select" required>
                <option value="">Select</option>
                <option value="mother">Mother</option>
                <option value="father">Father</option>
                <option value="guardian">Guardian</option>
                <option value="step_mother">Step-mother</option>
                <option value="step_father">Step-father</option>
                <option value="grandparent">Grandparent</option>
                <option value="uncle">Uncle</option>
                <option value="aunt">Aunt</option>
                <option value="sibling">Sibling</option>
                <option value="other">Other</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Phone Number</label>
              <input type="tel" name="parent_phone" class="form-control" id="ppParentPhone" readonly aria-readonly="true">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Email Address</label>
              <input type="email" name="parent_email" class="form-control" id="ppParentEmail" readonly aria-readonly="true">
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Residential Address</label>
              <input type="text" name="parent_address" class="form-control" id="ppParentAddress" readonly aria-readonly="true">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Birth Certificate <span class="text-danger">*</span></label>
              <input type="text" name="birth_certificate_no" class="form-control mb-2" maxlength="64" autocomplete="off" placeholder="Birth certificate number">
              <input type="file" name="doc_birth_certificate" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Passport Photo <span class="text-danger">*</span></label>
              <input type="file" name="doc_passport_photo" class="form-control" accept=".jpg,.jpeg,.png" required>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Special Medical / Learning / Dietary Needs</label>
              <textarea name="special_needs" class="form-control" rows="2" placeholder="Optional. Leave blank if none."></textarea>
            </div>
            <div class="col-12">
              <div class="form-check">
                <input type="checkbox" id="ppDeclaration" class="form-check-input" required>
                <label class="form-check-label small text-muted" for="ppDeclaration">
                  I confirm the information provided is accurate and complete.
                </label>
              </div>
              <div id="ppApplyMsg" class="alert d-none mt-2 mb-0"></div>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="submit" form="applyAdmissionForm" class="btn btn-success" id="ppApplySubmit">
          <i class="bi bi-send me-1"></i>Submit Application
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Shared parent scripts + page controller -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<?php asset_script($appBase, 'js/api.js'); ?>
<?php asset_script($appBase, 'js/core/frontend_logger.js'); ?>
<?php asset_script($appBase, 'js/core/grading_scale.js'); ?>
<?php asset_script($appBase, 'js/utils/file_lifecycle.js'); ?>
<?php asset_script($appBase, 'js/utils/print_manager.js'); ?>
<?php asset_script($appBase, 'js/utils/form-validation.js'); ?>
<?php asset_script($appBase, 'js/core/realtime_dispatch.js'); ?>
<?php asset_script($appBase, 'js/core/realtime_sse.js'); ?>
<?php
// Parent realtime: the server mints ONLY family:<studentId> channels for a
// parent's linked learners (RealtimeScopeResolver::scopesForUser), so the
// stream can never carry staff traffic to a guardian. A parent with no
// linked learners gets a 403 capability refusal and the stream stays off.
$parentRealtimeBase = '';
try {
    $parentRealtimeBase = \App\API\Services\RealtimeGatewayPublisher::browserStreamBase();
} catch (\Throwable $parentRealtimeError) {
    $parentRealtimeBase = '';
}
if ($parentRealtimeBase !== '') {
    echo '<script>window.KINGSWAY_REALTIME_SSE_ENABLED = true;</script>';
    echo '<script>document.addEventListener("DOMContentLoaded", function () { window.RealtimeSSE?.initialize?.(); });</script>';
}
?>
<?php asset_script($appBase, 'js/core/parent_common.js'); ?>
<?php asset_script($appBase, 'js/pages/' . ltrim($parentPageScript, '/') . '.js'); ?>
<script>
  window.AppLogger?.init?.();
  if (window.FormValidation) {
    document.addEventListener('DOMContentLoaded', function () {
      FormValidation.bindAllForms();
    });
  }
</script>
</body>
</html>
