<?php /* PARTIAL — no DOCTYPE/html/head/body */ ?>
<div class="container-fluid py-4" id="page-year-rollover">

  <!-- Pre-flight Status -->
  <div class="row g-3 mb-4">
    <div class="col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <i class="bi bi-calendar-check fs-3 mb-1" id="yrTermsIcon" style="color:gray"></i>
          <div class="fw-semibold" id="yrTermsStatus">Checking…</div>
          <div class="text-muted small">Outgoing Year Terms</div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <i class="bi bi-file-earmark-check fs-3 mb-1" id="yrResultsIcon" style="color:gray"></i>
          <div class="fw-semibold" id="yrResultsStatus">Checking…</div>
          <div class="text-muted small">Learners Missing Results</div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <i class="bi bi-person-check fs-3 mb-1" id="yrPromotionsIcon" style="color:gray"></i>
          <div class="fw-semibold" id="yrPromotionsStatus">Checking…</div>
          <div class="text-muted small">Promotions Done</div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <i class="bi bi-cash-coin fs-3 mb-1" id="yrFeesIcon" style="color:gray"></i>
          <div class="fw-semibold" id="yrFeesStatus">Checking…</div>
          <div class="text-muted small">Learners with Balances</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Workflow Card -->
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-primary text-white fw-semibold">
      <i class="bi bi-list-ol me-2"></i>Rollover workflow <span class="float-end small" id="yrWfInfo"></span>
    </div>
    <div class="card-body">
      <section class="mb-4 p-3 border rounded bg-body-tertiary" aria-labelledby="yrTargetHeading">
        <div class="row align-items-end g-3">
          <div class="col-md-8">
            <h5 class="mb-1" id="yrTargetHeading">Target academic year</h5>
          </div>
          <div class="col-md-4">
            <label for="yrTargetYear" class="form-label fw-semibold">Target academic year</label>
            <select id="yrTargetYear" class="form-select" aria-describedby="yrTargetHelp"></select>
            <div id="yrTargetHelp" class="form-text">The current year remains the outgoing year until final cutover.</div>
          </div>
        </div>
        <div id="yrTargetSummary" class="small mt-3"></div>
      </section>

      <ul class="nav nav-tabs flex-nowrap overflow-auto mb-3" id="yrPhaseTabs" role="tablist">
        <?php foreach ([
          'year' => 'Year & Calendar', 'classes' => 'Classes & Streams', 'curriculum' => 'Learning Areas',
          'staff' => 'Staff Continuity', 'fees' => 'Fees',
          'promotions' => 'Promotions', 'finance' => 'Finance & Cutover',
        ] as $phase => $label): ?>
        <li class="nav-item" role="presentation"><button class="nav-link<?= $phase === 'year' ? ' active' : '' ?>" type="button" role="tab" data-yr-phase="<?= $phase ?>"><?= htmlspecialchars($label) ?></button></li>
        <?php endforeach; ?>
      </ul>
      <div class="tab-content" id="yrPhasePanels">
        <?php foreach (['year', 'classes', 'curriculum', 'staff', 'fees', 'promotions', 'finance'] as $phase): ?>
        <section class="tab-pane<?= $phase === 'year' ? ' active show' : '' ?>" id="yrPhase-<?= $phase ?>" data-yr-phase-panel="<?= $phase ?>" role="tabpanel">
          <div class="list-group mb-3" id="yrStages-<?= $phase ?>"></div>
          <?php if ($phase === 'year'): ?>

      <!-- Create form -->
      <div id="yrCreateForm">
        <div class="alert alert-light border">
          <h6 class="fw-semibold mb-3"><i class="bi bi-calendar-plus me-2"></i>Create academic year and calendar</h6>
          <p class="small text-muted mb-3">Dates, weekdays and weeks use Kenya time. Kenyan public holidays load automatically; lunar dates are estimates until officially confirmed.</p>
          <div class="row g-3">
            <div class="col-md-3">
              <label class="form-label fw-semibold">New Year Code</label>
              <input type="text" id="yrToYear" class="form-control" placeholder="2027/2028" pattern="[0-9]{4}(/[0-9]{4})?">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Year Start Date</label>
              <input type="date" id="yrStartDate" class="form-control" required><small class="text-muted" id="yrStartDateWeekday"></small>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Year End Date</label>
              <input type="date" id="yrEndDate" class="form-control" required><small class="text-muted" id="yrEndDateWeekday"></small>
            </div>
          </div>
          <div class="table-responsive mt-3">
            <table class="table table-sm align-middle">
              <thead class="table-light">
                <tr>
                  <th>Term</th><th>Opening Date</th><th>Closing Date</th><th>Half-term break (optional)</th>
                </tr>
              </thead>
              <tbody>
                <?php for ($termNo = 1; $termNo <= 3; $termNo++): ?>
                <tr>
                  <td class="fw-semibold">Term <?= $termNo ?></td>
                  <td><input type="date" id="yrT<?= $termNo ?>Start" class="form-control" required><small class="text-muted" id="yrT<?= $termNo ?>StartWeekday"></small></td>
                  <td><input type="date" id="yrT<?= $termNo ?>End" class="form-control" required><small class="text-muted" id="yrT<?= $termNo ?>EndWeekday"></small></td>
                  <td>
                    <div class="form-check">
                      <input class="form-check-input yr-half-term-toggle" type="checkbox" id="yrT<?= $termNo ?>HasHalfTerm" data-term="<?= $termNo ?>">
                      <label class="form-check-label small" for="yrT<?= $termNo ?>HasHalfTerm">Has half-term break</label>
                    </div>
                    <div class="yr-half-term-fields d-none mt-2" id="yrT<?= $termNo ?>HalfTermFields">
                      <div class="input-group input-group-sm">
                        <span class="input-group-text">From</span>
                        <input type="date" id="yrT<?= $termNo ?>HalfStart" class="form-control" aria-label="Term <?= $termNo ?> half-term start"><small class="text-muted" id="yrT<?= $termNo ?>HalfStartWeekday"></small>
                        <span class="input-group-text">to</span>
                        <input type="date" id="yrT<?= $termNo ?>HalfEnd" class="form-control" aria-label="Term <?= $termNo ?> half-term end"><small class="text-muted" id="yrT<?= $termNo ?>HalfEndWeekday"></small>
                      </div>
                    </div>
                  </td>
                </tr>
                <?php endfor; ?>
              </tbody>
            </table>
          </div>
          <button class="btn btn-primary" id="yrStartBtn" data-kw-onclick="yearRolloverController.createNewYear()" data-year-write>
            <i class="bi bi-calendar-plus me-1"></i> Create New Academic Year &amp; Calendar
          </button>
          <span class="ms-2 small text-muted" id="yrCreateMsg"></span>
        </div>
      </div>
      <div class="card border mb-3" id="yrCalendarSummary" aria-live="polite">
        <div class="card-body py-2 text-muted small">Select a target year to view its saved calendar and holidays.</div>
      </div>

          <?php endif; ?>
          <?php if ($phase === 'classes'): ?>
          <div class="card border mb-3">
            <div class="card-body">
              <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                <div>
                  <h5 class="mb-1" id="yrClassDraftTitle">Current-year class structure</h5>
                  <p class="text-muted small mb-0" id="yrClassDraftHelp">Adjust the target streams and capacity using the current-year structure as a starting point.</p>
                </div>
                <button type="button" class="btn btn-outline-primary btn-sm" id="yrReloadClassDraft"><i class="bi bi-arrow-clockwise me-1"></i>Reload structure</button>
              </div>
              <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                  <thead id="yrClassDraftHead"><tr><th>Current-year class</th><th>Target-year class</th><th>Current streams</th><th>Target streams (comma separated)</th><th style="width:130px">Capacity per stream</th><th>Learner types offered</th></tr></thead>
                  <tbody id="yrClassDraftBody"><tr><td colspan="6" class="text-center text-muted py-4">Select a target year to load the current-year structure.</td></tr></tbody>
                </table>
              </div>
              <div class="form-text mt-2">Streams, capacity, and Day/Board availability apply only to the target year. Learners are assigned later.</div>
              <div class="small text-muted mt-1" id="yrClassDraftSaveState">Changes autosave in this browser until applied to the target year.</div>
            </div>
          </div>
          <?php endif; ?>
          <?php if (in_array($phase, ['curriculum', 'staff', 'fees'], true)): ?>
          <div class="alert alert-light border d-none" id="yrSetupContext-<?= $phase ?>" aria-live="polite"></div>
          <?php if ($phase !== 'fees'): ?>
          <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
            <span class="small text-muted" id="yrDraftSaveState-<?= $phase ?>">Target-year draft not loaded.</span>
          </div>
          <?php endif; ?>
          <?php if ($phase === 'curriculum'): ?>
          <p class="small text-muted">Confirm the learning areas offered in each class. Subject teachers set strands and substrands later.</p>
          <div class="d-flex justify-content-end mb-2"><button type="button" class="btn btn-sm btn-outline-primary" id="yrAddLearningArea" data-year-write><i class="bi bi-plus-lg me-1"></i>Add learning area</button></div>
          <div class="table-responsive"><table class="table table-sm table-hover align-middle"><thead id="yrCurriculumDraftHead"><tr><th>Learning area</th><th>Target-year classes</th></tr></thead><tbody id="yrCurriculumDraftBody"><tr><td colspan="2" class="text-center text-muted">Load a target year and prepare its classes first.</td></tr></tbody></table></div>
          <?php elseif ($phase === 'staff'): ?>
          <p class="small text-muted">Confirm which current staff are expected to continue. Class teachers, subject assignments and timetables are set up later for each active term.</p>
          <div class="table-responsive"><table class="table table-sm table-hover align-middle"><thead><tr><th>Staff member</th><th>Next-year status</th></tr></thead><tbody id="yrStaffContinuityBody"></tbody></table></div>
          <?php elseif ($phase === 'fees'): ?>
          <h6 class="mt-2">School fees</h6>
          <div class="table-responsive"><table class="table table-sm table-hover align-middle"><thead id="yrFeeDraftHead"><tr><th>Class</th><th>Learner type</th><th>Term 1</th><th>Term 2</th><th>Term 3</th><th>Total</th></tr></thead><tbody id="yrFeeDraftBody"></tbody></table></div>
          <div class="d-flex justify-content-between align-items-center mt-4 mb-2"><div><h6 class="mb-0">Fee structure charges</h6><small class="text-muted">Registration, interview and other published fees. Individual and class charges are managed separately.</small></div><button type="button" class="btn btn-sm btn-outline-primary" id="yrAddExtraCharge" data-year-write><i class="bi bi-plus-lg me-1"></i>Add fee</button></div>
          <div class="table-responsive"><table class="table table-sm table-hover align-middle"><thead><tr><th style="min-width:180px">Charge</th><th style="min-width:150px">Applies to</th><th style="min-width:380px">Pricing (KES)</th><th style="width:180px">Source</th></tr></thead><tbody id="yrExtraChargesBody"><tr><td colspan="4" class="text-center text-muted py-3">No published charges set up in either year.</td></tr></tbody></table></div>
          <?php endif; ?>
          <?php endif; ?>
          <?php if ($phase === 'finance'): ?>
          <div class="card border mb-3" id="yrFinancePreview">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                <div>
                  <h6 class="mb-1">Rollover summary</h6>
                  <p class="small text-muted mb-2" id="yrRolloverSummaryNote">Fees were billed and balances carried forward when each promotion decision was saved. Review the totals, then finish the closing steps.</p>
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="yrRefreshRolloverSummary"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
              </div>

              <div class="row g-2 mb-3" id="yrRolloverSummaryCards">
                <div class="col-6 col-lg-3"><div class="border rounded p-2 h-100"><div class="small text-muted">Promoted</div><div class="fw-bold fs-5" id="yrSumPromoted">—</div></div></div>
                <div class="col-6 col-lg-3"><div class="border rounded p-2 h-100"><div class="small text-muted">Retained</div><div class="fw-bold fs-5" id="yrSumRetained">—</div></div></div>
                <div class="col-6 col-lg-3"><div class="border rounded p-2 h-100"><div class="small text-muted">Graduated</div><div class="fw-bold fs-5" id="yrSumGraduated">—</div></div></div>
                <div class="col-6 col-lg-3"><div class="border rounded p-2 h-100"><div class="small text-muted">Learners billed</div><div class="fw-bold fs-5" id="yrSumBilled">—</div></div></div>
                <div class="col-6 col-lg-3"><div class="border rounded p-2 h-100"><div class="small text-muted">Obligations created</div><div class="fw-bold fs-5" id="yrSumObligations">—</div></div></div>
                <div class="col-6 col-lg-3"><div class="border rounded p-2 h-100"><div class="small text-muted">Billed amount</div><div class="fw-bold fs-5" id="yrSumAmount">—</div></div></div>
                <div class="col-6 col-lg-3"><div class="border rounded p-2 h-100"><div class="small text-muted">Arrears carried</div><div class="fw-bold fs-5" id="yrSumArrears">—</div></div></div>
                <div class="col-6 col-lg-3"><div class="border rounded p-2 h-100"><div class="small text-muted">Credits applied</div><div class="fw-bold fs-5" id="yrSumCredits">—</div></div></div>
              </div>

              <div class="row g-3">
                <div class="col-lg-6">
                  <h6 class="small text-muted text-uppercase">Target-year billing by term</h6>
                  <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Term</th><th class="text-end">Obligations</th><th class="text-end">Amount</th></tr></thead>
                    <tbody id="yrRolloverTermBody"><tr><td colspan="3" class="text-center text-muted py-3">Load the rollover to see term billing.</td></tr></tbody>
                  </table>
                </div>
                <div class="col-lg-6">
                  <h6 class="small text-muted text-uppercase">Fee structures awaiting approval</h6>
                  <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Status</th><th class="text-end">Rows</th></tr></thead>
                    <tbody id="yrRolloverFeeStatusBody"><tr><td colspan="2" class="text-center text-muted py-3">Load the rollover to see fee status.</td></tr></tbody>
                  </table>
                  <p class="small text-muted mt-2 mb-0" id="yrRolloverGraduateNote"></p>
                </div>
              </div>
            </div>
          </div>

          <div class="card border mb-3" id="yrHandoverPanel" aria-live="polite">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                <div>
                  <h6 class="mb-1">Year-to-year handover</h6>
                  <p class="small text-muted mb-0">The outgoing year stays operational until its last day. The target year is prepared alongside it and switches automatically on its start date.</p>
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="yrRefreshHandover"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
              </div>
              <div class="row g-2" id="yrHandoverCards">
                <div class="col-md-6 col-lg-3"><div class="border rounded p-2 h-100"><div class="small text-muted">Operational year</div><div class="fw-bold fs-6" id="yrHandoverOperational">—</div><div class="small text-muted" id="yrHandoverOperationalMeta"></div></div></div>
                <div class="col-md-6 col-lg-3"><div class="border rounded p-2 h-100"><div class="small text-muted">Target year</div><div class="fw-bold fs-6" id="yrHandoverPrepared">—</div><div class="small text-muted" id="yrHandoverPreparedMeta"></div></div></div>
                <div class="col-md-6 col-lg-3"><div class="border rounded p-2 h-100"><div class="small text-muted">Switch date</div><div class="fw-bold fs-6" id="yrHandoverSwitchDate">—</div><div class="small text-muted" id="yrHandoverSwitchMeta"></div></div></div>
                <div class="col-md-6 col-lg-3"><div class="border rounded p-2 h-100"><div class="small text-muted">Readiness</div><div class="fw-bold fs-6" id="yrHandoverReadiness">—</div><div class="small text-muted" id="yrHandoverReadinessMeta"></div></div></div>
              </div>
              <p class="small mb-0 mt-2" id="yrHandoverNotice"></p>
            </div>
          </div>
          <?php endif; ?>
          <?php if ($phase === 'promotions'): ?>

      <div id="yrPromotionBoard" class="d-none mt-4">
        <div class="alert alert-info mb-3">
          <strong>Administrator promotion decisions</strong>
          <div class="small">Map whole classes or streams in bulk, then adjust individual learners where needed. Missing results are advisory.</div>
        </div>
        <div class="card border mb-3">
          <div class="card-body">
            <div class="fw-semibold mb-2">Bulk class and stream mapping</div>
            <div class="row g-2 align-items-end">
              <div class="col-md-3"><label class="form-label" for="yrBulkSourceClass">Current class</label><select class="form-select" id="yrBulkSourceClass"><option value="">Choose class</option></select></div>
              <div class="col-md-2"><label class="form-label" for="yrBulkSourceStream">Current stream</label><select class="form-select" id="yrBulkSourceStream"><option value="">All streams</option></select></div>
              <div class="col-md-2"><label class="form-label" for="yrBulkDecision">Decision</label><select class="form-select" id="yrBulkDecision"><option value="promote">Promote</option><option value="retain">Retain</option><option value="graduate">Graduate</option></select></div>
              <div class="col-md-3"><label class="form-label" for="yrBulkTarget">Target class / stream</label><select class="form-select" id="yrBulkTarget"><option value="">Choose destination</option></select></div>
              <div class="col-md-2 d-grid"><button type="button" class="btn btn-outline-primary" id="yrAddPromotionRule" data-year-write><i class="bi bi-plus-lg me-1"></i>Add mapping</button></div>
            </div>
            <div class="table-responsive mt-3" id="yrPromotionRulesWrap">
              <table class="table table-sm align-middle mb-2"><thead><tr><th>Current group</th><th>Decision</th><th>Target</th><th>Learners</th><th></th></tr></thead><tbody id="yrPromotionRulesBody"><tr><td colspan="5" class="text-muted">No bulk mappings added.</td></tr></tbody></table>
            </div>
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
              <span class="small text-muted" id="yrPromotionRuleSummary">Add mappings, then apply them to matching learners.</span>
              <button type="button" class="btn btn-primary btn-sm" id="yrApplyPromotionRules"><i class="bi bi-check2-square me-1"></i>Apply mappings and save</button>
            </div>
          </div>
        </div>
        <div class="row g-2 mb-3 align-items-end">
          <div class="col-md-4"><label class="form-label" for="yrPromotionSearch">Find learner</label><input class="form-control" id="yrPromotionSearch" type="search" placeholder="Name or admission number"></div>
          <div class="col-md-3"><label class="form-label" for="yrPromotionClassFilter">Current class</label><select class="form-select" id="yrPromotionClassFilter"><option value="">All classes</option></select></div>
          <div class="col-md-2"><label class="form-label" for="yrPromotionStreamFilter">Current stream</label><select class="form-select" id="yrPromotionStreamFilter"><option value="">All streams</option></select></div>
          <div class="col-md-3"><label class="form-label" for="yrPromotionStatusFilter">Assignment status</label><select class="form-select" id="yrPromotionStatusFilter"><option value="pending">Needs decision</option><option value="assigned">Assigned</option><option value="all">All learners</option></select></div>
        </div>
        <div class="d-flex justify-content-between align-items-center mb-2">
          <span class="fw-semibold" id="yrPromotionProgress">Loading learners…</span>
          <span class="small text-muted" id="yrPromotionSelectionCount">0 selected</span>
          <button class="btn btn-primary btn-sm" id="yrSavePromotionAssignments" data-kw-onclick="yearRolloverController.savePromotionAssignments()" data-year-write><i class="bi bi-save me-1"></i>Save selected assignments</button>
        </div>
        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle">
            <thead class="table-light"><tr><th><input type="checkbox" class="form-check-input" id="yrPromotionSelectVisible" aria-label="Select visible learners"></th><th>Learner</th><th>Current class/stream</th><th>Results</th><th>Previous-year balance</th><th>Decision</th><th>Target class/stream</th><th>Status</th></tr></thead>
            <tbody id="yrPromotionBoardBody"><tr><td colspan="8" class="text-muted text-center">Loading…</td></tr></tbody>
          </table>
        </div>
      </div>
          <?php endif; ?>
          <?php if ($phase !== 'fees'): ?><div class="alert alert-light border small mb-0" id="yrPhaseNote-<?= $phase ?>"></div><?php endif; ?>
          <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3 pt-3 border-top" data-phase-navigation="<?= $phase ?>">
            <button type="button" class="btn btn-outline-secondary" data-phase-back="<?= $phase ?>"><i class="bi bi-arrow-left me-1"></i>Back</button>
            <div class="d-flex flex-wrap gap-2">
              <span class="small text-muted align-self-center" id="yrPhaseDraftStatus-<?= $phase ?>" aria-live="polite"></span>
              <button type="button" class="btn btn-outline-primary" data-phase-save-draft="<?= $phase ?>"><i class="bi bi-cloud-arrow-down me-1"></i>Save draft</button>
              <button type="button" class="btn btn-primary" data-phase-save="<?= $phase ?>"><i class="bi bi-save me-1"></i>Save</button>
              <button type="button" class="btn btn-outline-primary" data-phase-next="<?= $phase ?>">Next<i class="bi bi-arrow-right ms-1"></i></button>
            </div>
          </div>
        </section>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Rollover Log -->
  <div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent fw-semibold">
      <i class="bi bi-journal-text me-2"></i>Transition Audit Log
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th scope="col">Stage</th><th scope="col">Status</th><th scope="col">Details</th><th scope="col">Performed</th>
            </tr>
          </thead>
          <tbody id="yrLogBody">
            <tr><td colspan="4" class="text-center text-muted py-3">No transition activity yet.</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php asset_script($appBase, 'js/pages/year_rollover.js'); ?>
