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
          <div class="mb-3">
            <label class="form-label fw-semibold">What are you paying for?</label>
            <select id="mpesaPurpose" class="form-select">
              <option value="fees">School fees</option>
              <option value="transport">Transport</option>
              <option value="uniforms">Uniforms</option>
            </select>
            <div class="form-text">The reference is routed to the correct ledger automatically. Unmatched money never becomes a fee.</div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Payment provider</label>
            <select id="mpesaProvider" class="form-select">
              <option value="daraja">Safaricom Daraja</option>
              <option value="buni">KCB Buni M-Pesa Express</option>
            </select>
            <div class="form-text">Both providers create an M-Pesa prompt; confirmation is recorded by the school system.</div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Student</label>
            <select id="mpesaStudent" class="form-select"></select>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Amount (KES)</label>
            <input type="number" id="mpesaAmount" class="form-control" min="1" step="any">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">M-Pesa Phone Number</label>
            <input type="tel" id="mpesaPhone" class="form-control" placeholder="2547XXXXXXXX">
            <div class="form-text">Enter the phone number registered with M-Pesa</div>
          </div>
          <div id="mpesaError" class="alert alert-danger d-none"></div>
          <button class="btn btn-success w-100 py-2 fw-semibold" type="button" id="btnMpesaPay">
            <span class="spinner-border spinner-border-sm me-2 d-none" id="mpesaSpinner"></span>
            <i class="bi bi-send me-2"></i>Pay with M-Pesa
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
              <input type="text" name="child_name" class="form-control" placeholder="As on birth certificate" required>
            </div>
            <div class="col-md-3">
              <label class="form-label fw-semibold">Date of Birth</label>
              <input type="date" name="child_dob" class="form-control">
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
              <label class="form-label fw-semibold">Preferred Start Term</label>
              <select name="preferred_start" id="ppPreferredStart" class="form-select">
                <?php if (!$ppTerms): ?>
                <option value="">No intake terms open right now</option>
                <?php else: foreach ($ppTerms as $term): ?>
                <option value="<?= htmlspecialchars((string) ($term['name'] ?? '') . ' ' . (string) ($term['year'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        data-term-id="<?= (int) ($term['id'] ?? 0) ?>">
                  <?= htmlspecialchars((string) ($term['name'] ?? 'Term') . ' ' . (string) ($term['year'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                </option>
                <?php endforeach; endif; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Day Scholar or Boarding</label>
              <select name="boarding_preference" class="form-select">
                <option value="day">Day Scholar</option>
                <option value="full_boarding">Full Boarding (Mon – Fri)</option>
                <option value="weekly_boarding">Weekly Boarding (Mon – Fri)</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Parent / Guardian Name <span class="text-danger">*</span></label>
              <input type="text" name="parent_name" class="form-control" id="ppParentName" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Relationship to Child <span class="text-danger">*</span></label>
              <select name="parent_relationship" class="form-select" required>
                <option value="">Select</option>
                <option value="Mother">Mother</option>
                <option value="Father">Father</option>
                <option value="Guardian">Guardian</option>
                <option value="Sponsor">Sponsor</option>
                <option value="Other">Other</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Phone Number <span class="text-danger">*</span></label>
              <input type="tel" name="parent_phone" class="form-control" id="ppParentPhone" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Email Address</label>
              <input type="email" name="parent_email" class="form-control" id="ppParentEmail">
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Residential Address</label>
              <input type="text" name="parent_address" class="form-control" id="ppParentAddress" placeholder="Town, Sub-county, County">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Birth Certificate <span class="text-danger">*</span></label>
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
<?php asset_script($appBase, 'js/core/parent_common.js'); ?>
<?php asset_script($appBase, 'js/pages/' . ltrim($parentPageScript, '/') . '.js'); ?>
<script>window.AppLogger?.init?.();</script>
</body>
</html>