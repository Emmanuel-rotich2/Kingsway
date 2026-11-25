<?php
/**
 * Manage Terms
 *
 * Purpose: View and manage academic terms and their dates (single terms page,
 * absorbed the former term_dates page). Terms are created for a year by Year
 * Rollover; this page lets you adjust dates/status or add a missing term.
 * Features:
 * - School-today phase: in session, holiday, waiting for cutover or schedule
 * - Term sequence overview for the selected scope, defaulting to the current year
 * - Sorting, search, status and date filters that load without a submit button
 * - Native dialog editor, CSV export and print layout
 */
?>
<link rel="stylesheet" href="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/css/pages/manage-terms.css?v=<?= asset_version('css/pages/manage-terms.css') ?>">

<div class="manage-terms-page kw-clean" id="manageTermsPage">
    <p class="mt-print-context" id="mtPrintContext"></p>

    <header class="mt-page-header">
        <div>
            <p class="mt-eyebrow">Academic register</p>
            <h1>Manage Terms</h1>
        </div>
        <div class="mt-actions no-print" aria-label="Term actions">
            <button class="mt-primary-button" type="button" id="mtAddBtn" data-year-write>
                <i class="bi bi-plus-lg" aria-hidden="true"></i> Add term
            </button>
            <button class="mt-quiet-button" type="button" id="mtExportBtn" data-no-print>
                <i class="bi bi-filetype-csv" aria-hidden="true"></i> CSV
            </button>
            <button class="mt-quiet-button" type="button" id="mtPrintBtn" data-no-print>
                <i class="bi bi-printer" aria-hidden="true"></i> Print
            </button>
        </div>
    </header>

    <section class="mt-overview" aria-labelledby="mtOverviewTitle">
        <div class="mt-overview-head">
            <div>
                <p class="mt-panel-label" id="mtOverviewTitle">Term sequence</p>
                <strong class="mt-overview-year" id="mtOverviewYear">Loading…</strong>
            </div>
            <p class="mt-overview-note">Opening, closing and half-term dates are editable here.</p>
        </div>
        <ol class="mt-term-band" id="mtTermBand" aria-label="Term sequence for the selected scope"></ol>
        <dl class="mt-metrics">
            <div><dt>Terms</dt><dd id="mtStatTotal">0</dd></div>
            <div><dt>In session</dt><dd id="mtStatActive">0</dd></div>
            <div><dt>Completed</dt><dd id="mtStatCompleted">0</dd></div>
            <div><dt>Upcoming</dt><dd id="mtStatUpcoming">0</dd></div>
        </dl>
    </section>

    <section class="mt-phase" id="mtPhasePanel" aria-labelledby="mtPhaseTitle" data-phase="loading">
        <p class="visually-hidden" id="mtPhaseAnnouncement" role="status" aria-live="polite"></p>
        <div class="mt-phase-main">
            <p class="mt-panel-label" id="mtPhaseEyebrow">School today</p>
            <h2 id="mtPhaseTitle">Checking the calendar…</h2>
            <p class="mt-phase-detail" id="mtPhaseDetail">Reading the live academic period and transition state.</p>
        </div>
        <div class="mt-phase-context" aria-label="Adjacent terms">
            <article class="mt-phase-card" id="mtPhaseRecentCard">
                <p class="mt-phase-card-label">Recently completed</p>
                <strong class="mt-phase-card-title" id="mtPhaseRecentTitle">—</strong>
                <p class="mt-phase-card-dates" id="mtPhaseRecentDates"></p>
            </article>
            <article class="mt-phase-card" id="mtPhaseNextCard">
                <p class="mt-phase-card-label">Immediate incoming</p>
                <strong class="mt-phase-card-title" id="mtPhaseNextTitle">—</strong>
                <p class="mt-phase-card-dates" id="mtPhaseNextDates"></p>
            </article>
        </div>
        <a class="mt-phase-link no-print" id="mtPhaseLink" href="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/home.php?route=term_transition" hidden>
            <span id="mtPhaseLinkLabel">Open Term Transition</span>
            <i class="bi bi-arrow-right" aria-hidden="true"></i>
        </a>
    </section>

    <section class="mt-filter-panel no-print" aria-labelledby="mtFiltersTitle">
        <h2 class="visually-hidden" id="mtFiltersTitle">Filter terms</h2>
        <div class="mt-filter-grid">
            <div class="mt-field">
                <label for="searchInput">Search</label>
                <input type="search" id="searchInput" placeholder="Term or year" autocomplete="off">
            </div>
            <div class="mt-field">
                <label for="yearFilter">Academic year</label>
                <select id="yearFilter"><option value="all">All academic years</option></select>
            </div>
            <div class="mt-field">
                <label for="filterSelect">Status</label>
                <select id="filterSelect">
                    <option value="">All statuses</option>
                    <option value="current">Current</option>
                    <option value="completed">Completed</option>
                    <option value="upcoming">Upcoming</option>
                </select>
            </div>
            <div class="mt-field">
                <label for="dateFilter">Date in term</label>
                <input type="date" id="dateFilter">
            </div>
            <button class="mt-clear-button" type="button" id="mtClearFilters" hidden>Clear filters</button>
        </div>
        <div class="mt-year-banner-slot" id="mtYearBannerSlot" aria-live="polite"></div>
    </section>

    <section class="mt-register" aria-labelledby="mtRegisterTitle">
        <div class="mt-register-head">
            <div>
                <p class="mt-panel-label">Register</p>
                <h2 id="mtRegisterTitle">Term dates</h2>
            </div>
            <p class="mt-result-count" id="mtResultCount">Loading…</p>
        </div>
        <div class="mt-table-scroll" role="region" aria-labelledby="mtTableCaption" tabindex="0">
            <table id="dataTable">
                <caption class="visually-hidden" id="mtTableCaption">Academic terms in the selected scope</caption>
                <thead>
                    <tr>
                        <th scope="col" data-type="number"><button type="button" commandfor="dataTable" command="--sort">No.<span class="mt-sort-icon" aria-hidden="true"></span></button></th>
                        <th scope="col" data-type="text"><button type="button" commandfor="dataTable" command="--sort">Term<span class="mt-sort-icon" aria-hidden="true"></span></button></th>
                        <th scope="col" data-type="text"><button type="button" commandfor="dataTable" command="--sort">Academic year<span class="mt-sort-icon" aria-hidden="true"></span></button></th>
                        <th scope="col" data-type="date" aria-sort="ascending"><button type="button" commandfor="dataTable" command="--sort">Opens<span class="mt-sort-icon" aria-hidden="true"></span></button></th>
                        <th scope="col" data-type="date"><button type="button" commandfor="dataTable" command="--sort">Closes<span class="mt-sort-icon" aria-hidden="true"></span></button></th>
                        <th scope="col" data-type="number"><button type="button" commandfor="dataTable" command="--sort">Weeks<span class="mt-sort-icon" aria-hidden="true"></span></button></th>
                        <th scope="col" data-type="text"><button type="button" commandfor="dataTable" command="--sort">Status<span class="mt-sort-icon" aria-hidden="true"></span></button></th>
                        <th scope="col">Record</th>
                    </tr>
                </thead>
                <tbody id="mtTermsBody">
                    <tr id="mtTermsState"><td colspan="8" class="mt-empty"><p>Loading the term register…</p></td></tr>
                </tbody>
            </table>
            <p class="visually-hidden" id="mtTableStatus" role="status" aria-live="polite"></p>
        </div>
        <template id="mtTermRowTemplate">
            <tr data-term-row>
                <td data-label="" data-fill="number"></td>
                <th scope="row" data-fill="name"></th>
                <td data-label="Academic year" data-fill="year"></td>
                <td data-label="Opens" data-fill="start"></td>
                <td data-label="Closes" data-fill="end"></td>
                <td data-label="Weeks" data-fill="weeks"></td>
                <td data-label="Status"><span class="mt-status" data-fill="status"></span></td>
                <td data-label="Record">
                    <button class="mt-edit-button" type="button" data-action="edit" data-year-write>
                        <i class="bi bi-pencil" aria-hidden="true"></i><span>Edit</span>
                    </button>
                </td>
            </tr>
        </template>
    </section>
</div>

<dialog class="mt-dialog" id="mtFormDialog" aria-labelledby="mtFormTitle" aria-describedby="mtFormIntro">
    <form id="recordForm" novalidate>
        <header class="mt-dialog-head">
            <div>
                <p class="mt-panel-label">Term record</p>
                <h1 id="mtFormTitle">Add term</h1>
                <p id="mtFormIntro">Dates apply to the selected academic year.</p>
            </div>
            <button class="mt-dialog-close" type="button" id="mtFormClose" aria-label="Close term editor">
                <i class="bi bi-x-lg" aria-hidden="true"></i>
            </button>
        </header>
        <p class="mt-form-error" id="mtFormError" role="alert" hidden></p>
        <div class="mt-form-grid">
            <div class="mt-field mt-field--wide">
                <label for="recordName">Term name <span aria-hidden="true">*</span></label>
                <input type="text" id="recordName" placeholder="e.g. Term 1" required autofocus>
            </div>
            <div class="mt-field mt-field--wide">
                <label for="recordYear">Academic year <span aria-hidden="true">*</span></label>
                <select id="recordYear" required></select>
            </div>
            <div class="mt-field">
                <label for="recordStartDate">Opens <span aria-hidden="true">*</span></label>
                <input type="date" id="recordStartDate" required>
            </div>
            <div class="mt-field">
                <label for="recordEndDate">Closes <span aria-hidden="true">*</span></label>
                <input type="date" id="recordEndDate" required>
            </div>
            <div class="mt-field">
                <label for="recordHalfTermStart">Half-term opens</label>
                <input type="date" id="recordHalfTermStart" aria-describedby="mtHalfTermHint">
                <p class="mt-hint" id="mtHalfTermHint">Leave both half-term dates blank when there is no break.</p>
            </div>
            <div class="mt-field">
                <label for="recordHalfTermEnd">Half-term closes</label>
                <input type="date" id="recordHalfTermEnd">
            </div>
            <div class="mt-field">
                <label for="recordStatus">Status</label>
                <select id="recordStatus">
                    <option value="upcoming">Upcoming</option>
                    <option value="current">Current</option>
                    <option value="completed">Completed</option>
                </select>
            </div>
        </div>
        <input type="hidden" id="recordId">
        <footer class="mt-dialog-foot">
            <button class="mt-quiet-button" type="button" id="mtFormCancel">Cancel</button>
            <button class="mt-primary-button" type="submit" id="mtFormSave" data-year-write>Save term</button>
        </footer>
    </form>
</dialog>

<script src="<?= $appBase ?>/js/pages/manage_terms.js?v=<?php echo asset_version('js/pages/manage_terms.js'); ?>"></script>
