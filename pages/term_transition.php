<?php /* PARTIAL — no DOCTYPE/html/head/body */ ?>
<?php $ttBase = htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8'); ?>
<link rel="stylesheet" href="<?= $ttBase ?>/css/pages/term-transition.css?v=<?= asset_version('css/pages/term-transition.css') ?>">

<div class="tt-page kw-clean" id="page-term_transition">
    <p class="tt-print-context" id="ttPrintContext"></p>
    <p class="visually-hidden" id="ttAnnouncement" role="status" aria-live="polite"></p>

    <header class="tt-page-header">
        <div>
            <p class="tt-eyebrow">Academic handover</p>
            <h1>Term Transition</h1>
            <p class="tt-intro">Close one term and open the next with the dates, timetable and records checked before the final handover.</p>
        </div>
        <div class="tt-header-actions">
            <p class="tt-state-badge" id="ttCurrentTermBadge" data-state="loading">Reading the calendar…</p>
            <button class="tt-quiet no-print" type="button" id="ttPrintBtn">
                <i class="bi bi-printer" aria-hidden="true"></i> Print handover
            </button>
        </div>
    </header>

    <section class="tt-state-sheet" id="ttStatePanel" data-state="loading" aria-labelledby="ttStateTitle">
        <div class="tt-state-story">
            <p class="tt-panel-label" id="ttStateEyebrow">School today</p>
            <h2 id="ttStateTitle">Checking the handover…</h2>
            <p class="tt-state-detail" id="ttStateDetail">Reading the live term, calendar period and year-transition workflow.</p>
        </div>
        <dl class="tt-state-facts">
            <div>
                <dt>Outgoing</dt>
                <dd id="ttStateFrom">—</dd>
            </div>
            <div>
                <dt>Incoming</dt>
                <dd id="ttStateTo">—</dd>
            </div>
            <div>
                <dt>Cutover</dt>
                <dd id="ttStateSwitch">—</dd>
            </div>
            <div>
                <dt>Stage</dt>
                <dd id="ttStateStage">—</dd>
            </div>
        </dl>
        <a class="tt-state-link no-print" id="ttStateLink" href="<?= $ttBase ?>/home.php?route=year_rollover">
            <span id="ttStateLinkLabel">Open Year Rollover</span>
            <i class="bi bi-arrow-right" aria-hidden="true"></i>
        </a>
    </section>

    <section class="tt-handover" aria-labelledby="ttHandoverTitle">
        <div class="tt-handover-head">
            <h2 id="ttHandoverTitle">The handover at a glance</h2>
            <p id="ttHandoverNote">Reading the term register…</p>
        </div>
        <ol class="tt-ledger-row">
            <li class="tt-ledger-entry" id="ttRecentEntry">
                <p class="tt-entry-label">Recently completed</p>
                <h3 id="ttRecentName">—</h3>
                <p class="tt-entry-meta" id="ttRecentDates">—</p>
            </li>
            <li class="tt-ledger-arrow" aria-hidden="true"><span></span></li>
            <li class="tt-ledger-entry" id="ttIncomingEntry">
                <p class="tt-entry-label">Next in line</p>
                <h3 id="ttIncomingName">—</h3>
                <p class="tt-entry-meta" id="ttIncomingDates">—</p>
            </li>
        </ol>
    </section>

    <div id="ttWizard" hidden>
        <nav class="tt-progress no-print" aria-label="Transition steps">
            <ol id="ttStepBar">
                <li class="tt-step" data-step="1" aria-current="step"><span class="tt-step-num" aria-hidden="true">1</span><span class="tt-step-label" id="ttStepLbl1">Review outgoing term</span></li>
                <li class="tt-step" data-step="2"><span class="tt-step-num" aria-hidden="true">2</span><span class="tt-step-label">Confirm closure</span></li>
                <li class="tt-step" data-step="3"><span class="tt-step-num" aria-hidden="true">3</span><span class="tt-step-label">Rollover timetable</span></li>
                <li class="tt-step" data-step="4"><span class="tt-step-num" aria-hidden="true">4</span><span class="tt-step-label" id="ttStepLbl4">Set incoming dates</span></li>
                <li class="tt-step" data-step="5"><span class="tt-step-num" aria-hidden="true">5</span><span class="tt-step-label">Activate</span></li>
            </ol>
        </nav>

        <div class="tt-workspace">
            <div class="tt-panels">
                <section class="tt-panel" id="ttStep1" aria-labelledby="ttStep1Title">
                    <h2 id="ttStep1Title" tabindex="-1">Review <span id="ttStep1Name">the outgoing term</span></h2>
                    <p class="tt-panel-intro">Confirm that the term ending now has its timetable and lesson-plan record before anything is closed.</p>
                    <dl class="tt-ledger" id="ttReviewStats"></dl>
                    <div class="tt-review-columns">
                        <section aria-labelledby="ttCompletedTitle">
                            <h3 id="ttCompletedTitle">Ready</h3>
                            <ul class="tt-checklist" id="ttCompletedList"></ul>
                        </section>
                        <section aria-labelledby="ttPendingTitle">
                            <h3 id="ttPendingTitle">Needs attention</h3>
                            <ul class="tt-checklist" id="ttPendingList"></ul>
                        </section>
                    </div>
                    <div class="tt-panel-actions no-print">
                        <button class="tt-primary" type="button" id="ttProceedBtn">Continue to closure <i class="bi bi-arrow-right" aria-hidden="true"></i></button>
                    </div>
                </section>

                <section class="tt-panel" id="ttStep2" aria-labelledby="ttStep2Title" hidden>
                    <h2 id="ttStep2Title" tabindex="-1">Close <span id="ttCurrentTermName">the current term</span></h2>
                    <p class="tt-panel-intro">This prepares the term for closure. The official status changes only during activation.</p>
                    <label class="tt-check-row" for="ttConfirmClose">
                        <input type="checkbox" id="ttConfirmClose" name="confirm_close" required data-error="ttConfirmCloseError" aria-invalid="false">
                        <span>I confirm that results, attendance and lesson plans for <strong id="ttConfirmTermName">this term</strong> are finalised.</span>
                    </label>
                    <p class="tt-field-error" id="ttConfirmCloseError" hidden>Confirm the term is finalised before continuing.</p>
                    <div class="tt-notice" id="ttResultsReadiness" hidden></div>
                    <p class="tt-error" id="ttCloseError" role="alert" hidden></p>
                    <div class="tt-panel-actions no-print">
                        <button class="tt-quiet" type="button" id="ttBackStep1Btn"><i class="bi bi-arrow-left" aria-hidden="true"></i> Back</button>
                        <button class="tt-danger" type="button" id="ttCloseTermBtn" data-year-write>Confirm and continue</button>
                    </div>
                </section>

                <section class="tt-panel" id="ttStep3" aria-labelledby="ttStep3Title" hidden>
                    <h2 id="ttStep3Title" tabindex="-1">Roll over the timetable</h2>
                    <p class="tt-panel-intro">Copy the current class schedule into the incoming term. The copy runs with activation; individual slots stay editable afterwards.</p>
                    <form id="ttRolloverForm" aria-labelledby="ttStep3Title">
                        <fieldset class="tt-fieldset">
                            <legend>Timetable source</legend>
                            <dl class="tt-ledger tt-ledger-compact" id="ttTimetableStats">
                                <div><dt>Slots</dt><dd id="ttSlotCount">—</dd></div>
                                <div><dt>Classes</dt><dd id="ttClassCount">—</dd></div>
                                <div><dt>Teachers</dt><dd id="ttTeacherCount">—</dd></div>
                                <div><dt>Status</dt><dd id="ttRolloverStatus">Ready</dd></div>
                            </dl>
                        </fieldset>
                        <fieldset class="tt-fieldset">
                            <legend>Keep assignments</legend>
                            <label class="tt-check-row" for="ttKeepTeachers">
                                <input type="checkbox" id="ttKeepTeachers" name="keep_teachers" checked>
                                <span>Keep the same teacher assignments</span>
                            </label>
                            <label class="tt-check-row" for="ttKeepRooms">
                                <input type="checkbox" id="ttKeepRooms" name="keep_rooms" checked>
                                <span>Keep the same room assignments</span>
                            </label>
                        </fieldset>
                        <p class="tt-error" id="ttRolloverError" role="alert" hidden></p>
                        <div class="tt-panel-actions no-print">
                            <button class="tt-quiet" type="button" id="ttBackStep2Btn"><i class="bi bi-arrow-left" aria-hidden="true"></i> Back</button>
                            <button class="tt-quiet" type="button" id="ttSkipRolloverBtn">Skip — no timetable yet</button>
                            <button class="tt-primary" type="submit" id="ttRolloverBtn">Roll over timetable <i class="bi bi-arrow-right" aria-hidden="true"></i></button>
                        </div>
                    </form>
                </section>

                <section class="tt-panel" id="ttStep4" aria-labelledby="ttStep4Title" hidden>
                    <h2 id="ttStep4Title" tabindex="-1">Set dates for <span id="ttNextTermHeader">the incoming term</span></h2>
                    <p class="tt-panel-intro">Check the opening and closing dates carried from the academic calendar before activation.</p>
                    <form id="ttSetupForm" aria-labelledby="ttStep4Title" novalidate>
                        <fieldset class="tt-fieldset">
                            <legend id="ttNextTermChecklistTitle">Incoming term dates</legend>
                            <div class="tt-field-grid">
                                <div class="tt-field">
                                    <label for="ttTerm2Start"><span id="ttNextTermStartLabel">Opening date</span> <span aria-hidden="true">*</span></label>
                                    <input type="date" id="ttTerm2Start" name="start_date" required data-error="ttTerm2StartError" aria-invalid="false">
                                    <p class="tt-field-error" id="ttTerm2StartError" hidden>Enter the opening date.</p>
                                </div>
                                <div class="tt-field">
                                    <label for="ttTerm2End"><span id="ttNextTermEndLabel">Closing date</span> <span aria-hidden="true">*</span></label>
                                    <input type="date" id="ttTerm2End" name="end_date" required data-error="ttTerm2EndError" aria-invalid="false">
                                    <p class="tt-field-error" id="ttTerm2EndError" hidden>Enter a closing date after the opening date.</p>
                                </div>
                                <div class="tt-field">
                                    <label for="ttMidtermStart">Mid-term break opens</label>
                                    <input type="date" id="ttMidtermStart" name="half_term_start">
                                </div>
                                <div class="tt-field">
                                    <label for="ttMidtermEnd">Mid-term break closes</label>
                                    <input type="date" id="ttMidtermEnd" name="half_term_end">
                                </div>
                            </div>
                        </fieldset>
                        <ul class="tt-checklist tt-checklist-inline" id="ttSetupChecklist"></ul>
                        <p class="tt-error" id="ttSetupError" role="alert" hidden></p>
                        <div class="tt-panel-actions no-print">
                            <button class="tt-quiet" type="button" id="ttBackStep3Btn"><i class="bi bi-arrow-left" aria-hidden="true"></i> Back</button>
                            <button class="tt-success" type="submit" id="ttSaveSetupBtn" data-year-write>Save dates and review activation</button>
                        </div>
                    </form>
                </section>

                <section class="tt-panel" id="ttStep5" aria-labelledby="ttStep5Title" hidden>
                    <h2 id="ttStep5Title" tabindex="-1">Activate <span id="ttActivateHeader">the incoming term</span></h2>
                    <p class="tt-panel-intro">This is the final atomic action. Review what will change, then confirm in the dialog.</p>
                    <dl class="tt-ledger" id="ttActivateSummary"></dl>
                    <div class="tt-notice">
                        <strong>Activation will:</strong>
                        <ul>
                            <li>set the incoming term to current and mark the outgoing term completed;</li>
                            <li>copy the timetable if one was selected;</li>
                            <li>open lesson planning and live teacher timetables for the incoming term.</li>
                        </ul>
                    </div>
                    <p class="tt-error" id="ttActivateError" role="alert" hidden></p>
                    <div class="tt-panel-actions no-print">
                        <button class="tt-quiet" type="button" id="ttBackStep4Btn"><i class="bi bi-arrow-left" aria-hidden="true"></i> Back</button>
                        <button class="tt-success" type="button" id="ttActivateBtn" data-year-write>Close outgoing &amp; activate incoming</button>
                    </div>
                </section>

                <section class="tt-panel tt-done" id="ttDone" aria-labelledby="ttDoneTitle" hidden>
                    <h2 id="ttDoneTitle" tabindex="-1">The next term is active</h2>
                    <p class="tt-panel-intro">The handover is complete. Teachers can now plan lessons and use the new timetable.</p>
                    <div class="tt-done-links">
                        <a class="tt-primary" href="<?= $ttBase ?>/home.php?route=manage_timetable">View timetable</a>
                        <a class="tt-quiet" href="<?= $ttBase ?>/home.php?route=manage_lesson_plans">Start lesson plans</a>
                        <a class="tt-quiet" href="<?= $ttBase ?>/home.php?route=exam_schedule">Plan exams</a>
                    </div>
                </section>
            </div>

            <aside class="tt-aside" aria-labelledby="ttAsideTitle">
                <h2 id="ttAsideTitle">Handover notes</h2>
                <p>Year creation and the end-of-year rollover stay in the year workflow. This page only moves an already configured term pair.</p>
                <ul class="tt-related">
                    <li><a href="<?= $ttBase ?>/home.php?route=manage_terms">Manage term dates</a></li>
                    <li><a href="<?= $ttBase ?>/home.php?route=year_rollover">Year rollover checklist</a></li>
                    <li><a href="<?= $ttBase ?>/home.php?route=academic_years">Academic years</a></li>
                </ul>
                <p class="tt-aside-foot">No status is written until the final activation is confirmed.</p>
            </aside>
        </div>
    </div>

    <dialog class="tt-dialog" id="ttConfirmDialog" aria-labelledby="ttConfirmTitle" aria-describedby="ttConfirmDetail">
        <h1 id="ttConfirmTitle">Activate this handover?</h1>
        <p id="ttConfirmDetail">The outgoing term will be closed and the incoming term will become current.</p>
        <dl class="tt-ledger tt-ledger-compact" id="ttConfirmSummary"></dl>
        <form method="dialog">
            <div class="tt-dialog-actions">
                <button class="tt-quiet" type="submit" value="cancel" autofocus>Cancel</button>
                <button class="tt-success" type="submit" value="confirm" data-year-write>Activate handover</button>
            </div>
        </form>
    </dialog>
</div>

<?php asset_script($appBase, 'js/pages/term_transition.js'); ?>
