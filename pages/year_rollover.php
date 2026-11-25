<?php /* PARTIAL — no DOCTYPE/html/head/body */ ?>
<?php $yrBase = htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8'); ?>
<link rel="stylesheet" href="<?= $yrBase ?>/css/pages/year-rollover.css?v=<?= asset_version('css/pages/year-rollover.css') ?>">

<div class="yr-page kw-clean" id="page-year-rollover">
  <p class="yr-print-context">Year rollover handover · outgoing year and incoming year read from the live academic calendar</p>

  <header class="yr-page-header">
    <div>
      <p class="yr-eyebrow">Academic handover</p>
      <h1>Year Rollover</h1>
      <p class="yr-intro">Prepare the incoming year while the outgoing year keeps serving the school. Nothing is archived; the switch is driven by the calendar start date.</p>
    </div>
    <div class="yr-header-actions">
      <p class="yr-state-badge" id="yrCurrentYearBadge" data-state="current">Reading the calendar…</p>
      <button class="yr-quiet no-print" type="button" data-kw-onclick="yearRolloverController.printTargetCalendar()">
        <i class="bi bi-printer" aria-hidden="true"></i> Print handover
      </button>
    </div>
  </header>

  <!-- Live handover sheet -->
  <section class="yr-handover-sheet" id="yrHandoverPanel" aria-live="polite" aria-labelledby="yrHandoverTitle">
    <div class="yr-sheet-head">
      <div>
        <p class="yr-panel-label">Live handover</p>
        <h2 id="yrHandoverTitle">The outgoing year stays in charge</h2>
        <p class="yr-sheet-note" id="yrHandoverNotice">Reading the activation handover…</p>
      </div>
      <button class="yr-quiet" type="button" id="yrRefreshHandover">
        <i class="bi bi-arrow-clockwise" aria-hidden="true"></i> Refresh
      </button>
    </div>
    <dl class="yr-state-facts" id="yrHandoverCards">
      <div>
        <dt>Operational year</dt>
        <dd id="yrHandoverOperational">—</dd>
        <p id="yrHandoverOperationalMeta"></p>
      </div>
      <div>
        <dt>Prepared year</dt>
        <dd id="yrHandoverPrepared">—</dd>
        <p id="yrHandoverPreparedMeta"></p>
      </div>
      <div>
        <dt>Switch date</dt>
        <dd id="yrHandoverSwitchDate">—</dd>
        <p id="yrHandoverSwitchMeta"></p>
      </div>
      <div>
        <dt>Readiness</dt>
        <dd id="yrHandoverReadiness">—</dd>
        <p id="yrHandoverReadinessMeta"></p>
      </div>
    </dl>
  </section>

  <!-- Outgoing-year pre-flight register -->
  <section class="yr-preflight" aria-labelledby="yrPreflightTitle">
    <div class="yr-section-head">
      <div>
        <p class="yr-panel-label">Outgoing year</p>
        <h2 id="yrPreflightTitle">Pre-flight register</h2>
      </div>
      <p>Four live checks from the current academic records. They guide the rollover; they do not write to it.</p>
    </div>
    <dl class="yr-check-grid">
      <div class="yr-check">
        <dt><i class="bi bi-calendar-check" id="yrTermsIcon" aria-hidden="true"></i><span>Outgoing Year Terms</span></dt>
        <dd id="yrTermsStatus">Checking…</dd>
      </div>
      <div class="yr-check">
        <dt><i class="bi bi-file-earmark-check" id="yrResultsIcon" aria-hidden="true"></i><span>Learners Missing Results</span></dt>
        <dd id="yrResultsStatus">Checking…</dd>
      </div>
      <div class="yr-check">
        <dt><i class="bi bi-person-check" id="yrPromotionsIcon" aria-hidden="true"></i><span>Promotions Done</span></dt>
        <dd id="yrPromotionsStatus">Checking…</dd>
      </div>
      <div class="yr-check">
        <dt><i class="bi bi-cash-coin" id="yrFeesIcon" aria-hidden="true"></i><span>Learners with Balances</span></dt>
        <dd id="yrFeesStatus">Checking…</dd>
      </div>
    </dl>
  </section>

  <!-- Rollover workflow workspace -->
  <div class="yr-workflow-card">
    <header class="yr-workflow-head">
      <div>
        <p class="yr-panel-label">Rollover workflow</p>
        <h2 id="yrTargetHeading">Target academic year</h2>
        <p id="yrTargetHelp">Choose the year being prepared. The current year remains the outgoing year until final cutover.</p>
      </div>
      <p class="yr-workflow-info" id="yrWfInfo"></p>
    </header>

    <section class="yr-target-sheet" aria-labelledby="yrTargetHeading">
      <div class="yr-field">
        <label for="yrTargetYear">Target academic year</label>
        <select id="yrTargetYear" class="form-select" aria-describedby="yrTargetHelp"></select>
      </div>
      <div class="yr-target-note" id="yrTargetSummary" aria-live="polite">Select a target year to continue its setup.</div>
    </section>

    <nav class="yr-phase-nav no-print" aria-label="Rollover phases">
      <ul class="nav nav-tabs" id="yrPhaseTabs" role="tablist">
        <?php foreach ([
          'year' => 'Year & Calendar', 'classes' => 'Classes & Streams', 'curriculum' => 'Learning Areas',
          'staff' => 'Staff Continuity', 'fees' => 'Fees',
          'promotions' => 'Promotions', 'finance' => 'Finance & Cutover',
        ] as $phase => $label): ?>
        <li class="nav-item" role="presentation">
          <button class="nav-link<?= $phase === 'year' ? ' active' : '' ?>" type="button" role="tab"
                  aria-selected="<?= $phase === 'year' ? 'true' : 'false' ?>"
                  aria-controls="yrPhase-<?= $phase ?>" data-yr-phase="<?= $phase ?>"><?= htmlspecialchars($label) ?></button>
        </li>
        <?php endforeach; ?>
      </ul>
    </nav>

    <div class="tab-content yr-phase-content" id="yrPhasePanels">
      <?php foreach (['year', 'classes', 'curriculum', 'staff', 'fees', 'promotions', 'finance'] as $phase): ?>
      <section class="tab-pane yr-phase-panel<?= $phase === 'year' ? ' active show' : '' ?>"
               id="yrPhase-<?= $phase ?>" data-yr-phase-panel="<?= $phase ?>" role="tabpanel"
               aria-labelledby="yrPhaseTabs">
        <div class="yr-stage-list" id="yrStages-<?= $phase ?>"></div>

        <?php if ($phase === 'year'): ?>
        <section class="yr-form-sheet" id="yrCreateForm" aria-labelledby="yrCreateTitle">
          <div class="yr-sheet-head">
            <div>
              <p class="yr-panel-label">Only when there is no future year</p>
              <h3 id="yrCreateTitle">Create academic year and calendar</h3>
              <p class="yr-sheet-note">Dates, weekdays and weeks use Kenya time. Public holidays load automatically; lunar dates stay estimated until confirmed.</p>
            </div>
          </div>
          <div class="yr-field-grid yr-year-fields">
            <div class="yr-field">
              <label for="yrToYear">New year code</label>
              <input type="text" id="yrToYear" class="form-control" placeholder="2027/2028" pattern="[0-9]{4}(/[0-9]{4})?">
            </div>
            <div class="yr-field">
              <label for="yrStartDate">Year opens</label>
              <input type="date" id="yrStartDate" class="form-control" required>
              <small class="yr-weekday" id="yrStartDateWeekday"></small>
            </div>
            <div class="yr-field">
              <label for="yrEndDate">Year closes</label>
              <input type="date" id="yrEndDate" class="form-control" required>
              <small class="yr-weekday" id="yrEndDateWeekday"></small>
            </div>
          </div>
          <div class="table-responsive yr-table-scroll">
            <table class="table yr-table align-middle">
              <caption class="visually-hidden">Three term dates for the new academic year</caption>
              <thead>
                <tr>
                  <th scope="col">Term</th><th scope="col">Opening date</th><th scope="col">Closing date</th><th scope="col">Half-term break (optional)</th>
                </tr>
              </thead>
              <tbody>
                <?php for ($termNo = 1; $termNo <= 3; $termNo++): ?>
                <tr>
                  <th scope="row">Term <?= $termNo ?></th>
                  <td>
                    <label class="visually-hidden" for="yrT<?= $termNo ?>Start">Term <?= $termNo ?> opening date</label>
                    <input type="date" id="yrT<?= $termNo ?>Start" class="form-control" required>
                    <small class="yr-weekday" id="yrT<?= $termNo ?>StartWeekday"></small>
                  </td>
                  <td>
                    <label class="visually-hidden" for="yrT<?= $termNo ?>End">Term <?= $termNo ?> closing date</label>
                    <input type="date" id="yrT<?= $termNo ?>End" class="form-control" required>
                    <small class="yr-weekday" id="yrT<?= $termNo ?>EndWeekday"></small>
                  </td>
                  <td>
                    <label class="yr-check-row" for="yrT<?= $termNo ?>HasHalfTerm">
                      <input class="yr-half-term-toggle" type="checkbox" id="yrT<?= $termNo ?>HasHalfTerm" data-term="<?= $termNo ?>">
                      <span>Has half-term break</span>
                    </label>
                    <div class="yr-half-term-fields d-none" id="yrT<?= $termNo ?>HalfTermFields">
                      <label class="visually-hidden" for="yrT<?= $termNo ?>HalfStart">Term <?= $termNo ?> half-term start</label>
                      <input type="date" id="yrT<?= $termNo ?>HalfStart" class="form-control">
                      <small class="yr-weekday" id="yrT<?= $termNo ?>HalfStartWeekday"></small>
                      <label class="visually-hidden" for="yrT<?= $termNo ?>HalfEnd">Term <?= $termNo ?> half-term end</label>
                      <input type="date" id="yrT<?= $termNo ?>HalfEnd" class="form-control">
                      <small class="yr-weekday" id="yrT<?= $termNo ?>HalfEndWeekday"></small>
                    </div>
                  </td>
                </tr>
                <?php endfor; ?>
              </tbody>
            </table>
          </div>
          <div class="yr-form-actions">
            <button class="yr-primary" type="button" id="yrStartBtn" data-kw-onclick="yearRolloverController.createNewYear()" data-year-write>
              <i class="bi bi-calendar-plus" aria-hidden="true"></i> Create year &amp; calendar
            </button>
            <span class="yr-inline-status" id="yrCreateMsg"></span>
          </div>
        </section>

        <section class="yr-calendar-sheet" id="yrCalendarSummary" aria-live="polite">
          <p class="yr-empty">Select a target year to view its saved calendar and holidays.</p>
        </section>
        <?php endif; ?>

        <?php if ($phase === 'classes'): ?>
        <section class="yr-table-sheet">
          <div class="yr-sheet-head">
            <div>
              <p class="yr-panel-label">Structure ledger</p>
              <h3 id="yrClassDraftTitle">Current-year class structure</h3>
              <p class="yr-sheet-note" id="yrClassDraftHelp">Adjust the target streams and capacity using the current-year structure as a starting point.</p>
            </div>
            <button class="yr-quiet" type="button" id="yrReloadClassDraft">
              <i class="bi bi-arrow-clockwise" aria-hidden="true"></i> Reload structure
            </button>
          </div>
          <div class="table-responsive yr-table-scroll">
            <table class="table yr-table table-hover align-middle mb-0">
              <caption class="visually-hidden">Class and stream structure carried into the target year</caption>
              <thead id="yrClassDraftHead">
                <tr><th scope="col">Current-year class</th><th scope="col">Target-year class</th><th scope="col">Current streams</th><th scope="col">Target streams (comma separated)</th><th scope="col">Capacity per stream</th><th scope="col">Learner types offered</th></tr>
              </thead>
              <tbody id="yrClassDraftBody"><tr><td colspan="6" class="yr-empty-cell">Select a target year to load the current-year structure.</td></tr></tbody>
            </table>
          </div>
          <p class="yr-footnote">Streams, capacity and Day/Board availability apply only to the target year. Learners are assigned later.</p>
          <p class="yr-footnote" id="yrClassDraftSaveState">Changes autosave in this browser until applied to the target year.</p>
        </section>
        <?php endif; ?>

        <?php if (in_array($phase, ['curriculum', 'staff', 'fees'], true)): ?>
        <div class="yr-notice d-none" id="yrSetupContext-<?= $phase ?>" aria-live="polite"></div>
        <p class="yr-draft-state" id="yrDraftSaveState-<?= $phase ?>">Target-year draft not loaded.</p>

        <?php if ($phase === 'curriculum'): ?>
        <p class="yr-phase-intro">Confirm the learning areas offered in each class. Subject teachers set strands and substrands later.</p>
        <div class="yr-inline-actions"><button class="yr-quiet" type="button" id="yrAddLearningArea" data-year-write><i class="bi bi-plus-lg" aria-hidden="true"></i> Add learning area</button></div>
        <div class="table-responsive yr-table-scroll">
          <table class="table yr-table table-hover align-middle">
            <caption class="visually-hidden">Learning-area coverage by target-year class</caption>
            <thead id="yrCurriculumDraftHead"><tr><th scope="col">Learning area</th><th scope="col">Target-year classes</th></tr></thead>
            <tbody id="yrCurriculumDraftBody"><tr><td colspan="2" class="yr-empty-cell">Load a target year and prepare its classes first.</td></tr></tbody>
          </table>
        </div>

        <?php elseif ($phase === 'staff'): ?>
        <p class="yr-phase-intro">Confirm which current staff are expected to continue. Class teachers, subject assignments and timetables are set up later for each active term.</p>
        <div class="table-responsive yr-table-scroll">
          <table class="table yr-table table-hover align-middle">
            <caption class="visually-hidden">Staff continuity for the incoming year</caption>
            <thead><tr><th scope="col">Staff member</th><th scope="col">Next-year status</th></tr></thead>
            <tbody id="yrStaffContinuityBody"></tbody>
          </table>
        </div>

        <?php elseif ($phase === 'fees'): ?>
        <section class="yr-table-sheet">
          <div class="yr-sheet-head">
            <div>
              <p class="yr-panel-label">School fees</p>
              <h3>Target-year fee structure</h3>
              <p class="yr-sheet-note">Amounts are prepared for the incoming year. Approval happens with the rollover stages.</p>
            </div>
          </div>
          <div class="table-responsive yr-table-scroll">
            <table class="table yr-table table-hover align-middle">
              <caption class="visually-hidden">Term fees by class and learner type</caption>
              <thead id="yrFeeDraftHead"><tr><th scope="col">Class</th><th scope="col">Learner type</th><th scope="col">Term 1</th><th scope="col">Term 2</th><th scope="col">Term 3</th><th scope="col">Total</th></tr></thead>
              <tbody id="yrFeeDraftBody"></tbody>
            </table>
          </div>
        </section>
        <div class="yr-sheet-head yr-charge-head">
          <div>
            <p class="yr-panel-label">Published charges</p>
            <h3>Fee structure charges</h3>
            <p class="yr-sheet-note">Registration, interview and other published fees. Individual and class charges are managed separately.</p>
          </div>
          <button class="yr-quiet" type="button" id="yrAddExtraCharge" data-year-write><i class="bi bi-plus-lg" aria-hidden="true"></i> Add fee</button>
        </div>
        <div class="table-responsive yr-table-scroll">
          <table class="table yr-table table-hover align-middle">
            <caption class="visually-hidden">Published extra charges and pricing tiers</caption>
            <thead><tr><th scope="col">Charge</th><th scope="col">Applies to</th><th scope="col">Pricing (KES)</th><th scope="col">Source</th></tr></thead>
            <tbody id="yrExtraChargesBody"><tr><td colspan="4" class="yr-empty-cell">No published charges set up in either year.</td></tr></tbody>
          </table>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <?php if ($phase === 'finance'): ?>
        <section class="yr-ledger-sheet" id="yrFinancePreview">
          <div class="yr-sheet-head">
            <div>
              <p class="yr-panel-label">Finance ledger</p>
              <h3>Rollover summary</h3>
              <p class="yr-sheet-note" id="yrRolloverSummaryNote">Fees were billed and balances carried forward when each promotion decision was saved. Review the totals, then finish the closing steps.</p>
            </div>
            <button class="yr-quiet" type="button" id="yrRefreshRolloverSummary">
              <i class="bi bi-arrow-clockwise" aria-hidden="true"></i> Refresh
            </button>
          </div>
          <dl class="yr-metric-grid" id="yrRolloverSummaryCards">
            <div><dt>Promoted</dt><dd id="yrSumPromoted">—</dd></div>
            <div><dt>Retained</dt><dd id="yrSumRetained">—</dd></div>
            <div><dt>Graduated</dt><dd id="yrSumGraduated">—</dd></div>
            <div><dt>Learners billed</dt><dd id="yrSumBilled">—</dd></div>
            <div><dt>Obligations created</dt><dd id="yrSumObligations">—</dd></div>
            <div><dt>Billed amount</dt><dd id="yrSumAmount">—</dd></div>
            <div><dt>Arrears carried</dt><dd id="yrSumArrears">—</dd></div>
            <div><dt>Credits applied</dt><dd id="yrSumCredits">—</dd></div>
          </dl>
          <div class="yr-ledger-columns">
            <section aria-labelledby="yrBillingTitle">
              <h4 id="yrBillingTitle">Target-year billing by term</h4>
              <div class="table-responsive yr-table-scroll">
                <table class="table yr-table align-middle mb-0">
                  <caption class="visually-hidden">Obligations and amounts billed by term</caption>
                  <thead><tr><th scope="col">Term</th><th scope="col" class="text-end">Obligations</th><th scope="col" class="text-end">Amount</th></tr></thead>
                  <tbody id="yrRolloverTermBody"><tr><td colspan="3" class="yr-empty-cell">Load the rollover to see term billing.</td></tr></tbody>
                </table>
              </div>
            </section>
            <section aria-labelledby="yrFeeStatusTitle">
              <h4 id="yrFeeStatusTitle">Fee structures awaiting approval</h4>
              <div class="table-responsive yr-table-scroll">
                <table class="table yr-table align-middle mb-0">
                  <caption class="visually-hidden">Target-year fee structure status counts</caption>
                  <thead><tr><th scope="col">Status</th><th scope="col" class="text-end">Rows</th></tr></thead>
                  <tbody id="yrRolloverFeeStatusBody"><tr><td colspan="2" class="yr-empty-cell">Load the rollover to see fee status.</td></tr></tbody>
                </table>
              </div>
              <p class="yr-footnote" id="yrRolloverGraduateNote"></p>
            </section>
          </div>
        </section>
        <?php endif; ?>

        <?php if ($phase === 'promotions'): ?>
        <section id="yrPromotionBoard" class="yr-promotion-board d-none">
          <div class="yr-notice">
            <strong>Administrator promotion decisions</strong>
            <span>Map whole classes or streams in bulk, then adjust individual learners. Missing results are advisory.</span>
          </div>

          <section class="yr-table-sheet">
            <div class="yr-sheet-head">
              <div>
                <p class="yr-panel-label">Bulk mapping</p>
                <h3>Class and stream decisions</h3>
              </div>
            </div>
            <div class="yr-field-grid yr-bulk-grid">
              <div class="yr-field"><label for="yrBulkSourceClass">Current class</label><select class="form-select" id="yrBulkSourceClass"><option value="">Choose class</option></select></div>
              <div class="yr-field"><label for="yrBulkSourceStream">Current stream</label><select class="form-select" id="yrBulkSourceStream"><option value="">All streams</option></select></div>
              <div class="yr-field"><label for="yrBulkDecision">Decision</label><select class="form-select" id="yrBulkDecision"><option value="promote">Promote</option><option value="retain">Retain</option><option value="graduate">Graduate</option></select></div>
              <div class="yr-field"><label for="yrBulkTarget">Target class / stream</label><select class="form-select" id="yrBulkTarget"><option value="">Choose destination</option></select></div>
              <div class="yr-field yr-bulk-action"><button class="yr-quiet" type="button" id="yrAddPromotionRule" data-year-write><i class="bi bi-plus-lg" aria-hidden="true"></i> Add mapping</button></div>
            </div>
            <div class="table-responsive yr-table-scroll" id="yrPromotionRulesWrap">
              <table class="table yr-table align-middle mb-2">
                <caption class="visually-hidden">Bulk promotion mappings added by staff</caption>
                <thead><tr><th scope="col">Current group</th><th scope="col">Decision</th><th scope="col">Target</th><th scope="col">Learners</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead>
                <tbody id="yrPromotionRulesBody"><tr><td colspan="5" class="yr-empty-cell">No bulk mappings added.</td></tr></tbody>
              </table>
            </div>
            <div class="yr-form-actions">
              <span class="yr-inline-status" id="yrPromotionRuleSummary">Add mappings, then apply them to matching learners.</span>
              <button class="yr-primary" type="button" id="yrApplyPromotionRules"><i class="bi bi-check2-square" aria-hidden="true"></i> Apply mappings &amp; save</button>
            </div>
          </section>

          <div class="yr-filter-grid">
            <div class="yr-field"><label for="yrPromotionSearch">Find learner</label><input class="form-control" id="yrPromotionSearch" type="search" placeholder="Name or admission number"></div>
            <div class="yr-field"><label for="yrPromotionClassFilter">Current class</label><select class="form-select" id="yrPromotionClassFilter"><option value="">All classes</option></select></div>
            <div class="yr-field"><label for="yrPromotionStreamFilter">Current stream</label><select class="form-select" id="yrPromotionStreamFilter"><option value="">All streams</option></select></div>
            <div class="yr-field"><label for="yrPromotionStatusFilter">Assignment status</label><select class="form-select" id="yrPromotionStatusFilter"><option value="pending">Needs decision</option><option value="assigned">Assigned</option><option value="all">All learners</option></select></div>
          </div>
          <div class="yr-board-actions">
            <strong id="yrPromotionProgress">Loading learners…</strong>
            <span id="yrPromotionSelectionCount">0 selected</span>
            <button class="yr-primary" type="button" id="yrSavePromotionAssignments" data-kw-onclick="yearRolloverController.savePromotionAssignments()" data-year-write>
              <i class="bi bi-save" aria-hidden="true"></i> Save selected assignments
            </button>
          </div>
          <div class="table-responsive yr-table-scroll">
            <table class="table yr-table table-hover align-middle">
              <caption class="visually-hidden">Learner promotion decisions for the incoming year</caption>
              <thead><tr>
                <th scope="col"><input type="checkbox" class="form-check-input" id="yrPromotionSelectVisible" aria-label="Select visible learners"></th>
                <th scope="col">Learner</th><th scope="col">Current class/stream</th><th scope="col">Results</th>
                <th scope="col">Previous-year balance</th><th scope="col">Decision</th><th scope="col">Target class/stream</th><th scope="col">Status</th>
              </tr></thead>
              <tbody id="yrPromotionBoardBody"><tr><td colspan="8" class="yr-empty-cell">Loading…</td></tr></tbody>
            </table>
          </div>
        </section>
        <?php endif; ?>

        <?php if ($phase !== 'fees'): ?><p class="yr-phase-note" id="yrPhaseNote-<?= $phase ?>"></p><?php endif; ?>
        <div class="yr-phase-actions" data-phase-navigation="<?= $phase ?>">
          <button class="yr-quiet" type="button" data-phase-back="<?= $phase ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> Back</button>
          <div class="yr-phase-actions-right">
            <span class="yr-draft-state" id="yrPhaseDraftStatus-<?= $phase ?>" aria-live="polite"></span>
            <button class="yr-quiet" type="button" data-phase-save-draft="<?= $phase ?>"><i class="bi bi-cloud-arrow-down" aria-hidden="true"></i> Save draft</button>
            <button class="yr-primary" type="button" data-phase-save="<?= $phase ?>"><i class="bi bi-save" aria-hidden="true"></i> Save</button>
            <button class="yr-quiet" type="button" data-phase-next="<?= $phase ?>">Next <i class="bi bi-arrow-right" aria-hidden="true"></i></button>
          </div>
        </div>
      </section>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Transition journal -->
  <section class="yr-log-sheet" aria-labelledby="yrLogTitle">
    <div class="yr-section-head">
      <div>
        <p class="yr-panel-label">Transition journal</p>
        <h2 id="yrLogTitle">Activity on this handover</h2>
      </div>
      <p>This journal records the steps taken on this page. The official workflow remains the source of truth.</p>
    </div>
    <div class="table-responsive yr-table-scroll">
      <table class="table yr-table align-middle mb-0">
        <caption class="visually-hidden">Year rollover activity journal</caption>
        <thead><tr><th scope="col">Stage</th><th scope="col">Status</th><th scope="col">Details</th><th scope="col">Performed</th></tr></thead>
        <tbody id="yrLogBody"><tr><td colspan="4" class="yr-empty-cell">No transition activity yet.</td></tr></tbody>
      </table>
    </div>
  </section>
</div>

<?php asset_script($appBase, 'js/pages/year_rollover.js'); ?>
