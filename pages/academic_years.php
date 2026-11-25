<?php

/**
 * Academic Years Registry Page
 *
 * Purpose: View and manage academic years and their terms.
 * Creation of new years + terms + calendar happens exclusively in
 * Year Transition (Rollover) — this page does NOT create years or terms.
 * Features:
 * - View all years (current + previous)
 * - Edit year metadata (name, start/end dates)
 * - View each year's terms (read-only)
 * - Export year list
 */
?>
<link rel="stylesheet" href="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/css/pages/academic-years.css?v=<?= asset_version('css/pages/academic-years.css') ?>">

<div class="academic-years-page kw-clean" id="academicYearsPage">
    <p class="ay-print-context" id="ayPrintContext"></p>

    <header class="ay-page-header">
        <div>
            <p class="ay-eyebrow">Academic register</p>
            <h1>Academic Years</h1>
        </div>
        <div class="ay-actions no-print" aria-label="Academic year actions">
            <button class="ay-quiet-button" type="button" id="ayBulkBtn" data-year-write data-no-print disabled>
                <i class="bi bi-calendar2-range" aria-hidden="true"></i> Bulk edit dates<span id="ayBulkCount"></span>
            </button>
            <button class="ay-quiet-button" type="button" id="ayPrintBtn" data-no-print>
                <i class="bi bi-printer" aria-hidden="true"></i> Print
            </button>
            <button class="ay-quiet-button" type="button" id="exportYearsBtn" data-no-print>
                <i class="bi bi-filetype-csv" aria-hidden="true"></i> Export CSV
            </button>
        </div>
    </header>

    <section class="ay-overview" aria-label="Current academic position">
        <div class="ay-overview-panel ay-current-panel">
            <p class="ay-panel-label">Current academic year</p>
            <div id="currentYearInfo" aria-live="polite">
                <p class="ay-overview-message">Loading the current year…</p>
            </div>
        </div>
        <div class="ay-overview-panel ay-term-panel">
            <p class="ay-panel-label">Term position</p>
            <div id="currentTermInfo" aria-live="polite">
                <p class="ay-overview-message">Loading the current term…</p>
            </div>
        </div>
    </section>

    <section class="ay-register" aria-labelledby="ayRegisterTitle">
        <div class="ay-register-toolbar">
            <div>
                <h2 id="ayRegisterTitle">Year register</h2>
                <p>Opening and closing dates, term count, and record status.</p>
            </div>
            <div class="ay-tools no-print">
                <div class="ay-search">
                    <i class="bi bi-search" aria-hidden="true"></i>
                    <label class="visually-hidden" for="aySearch">Search academic years</label>
                    <input type="search" id="aySearch" name="ay_search" placeholder="Search year or status…" autocomplete="off">
                </div>
                <div class="ay-filter-group" role="group" aria-label="Filter academic years">
                    <button class="ay-filter-button" type="button" data-view="all" aria-pressed="true">
                        All <span class="ay-filter-count" data-count="all">0</span>
                    </button>
                    <button class="ay-filter-button" type="button" data-view="active" aria-pressed="false">
                        Active <span class="ay-filter-count" data-count="active">0</span>
                    </button>
                    <button class="ay-filter-button" type="button" data-view="past" aria-pressed="false">
                        Past <span class="ay-filter-count" data-count="past">0</span>
                    </button>
                </div>
            </div>
        </div>

        <div class="ay-table-scroll" role="region" aria-labelledby="ayTableCaption" tabindex="0">
            <table id="academicYearsTable">
                <caption class="visually-hidden" id="ayTableCaption">Academic years on record</caption>
                <thead>
                    <tr>
                        <th scope="col" class="ay-select-col">
                            <label class="visually-hidden" for="aySelectAll">Select all editable academic years</label>
                            <input class="form-check-input" type="checkbox" id="aySelectAll" disabled>
                        </th>
                        <th scope="col" data-type="text" aria-sort="ascending">
                            <button type="button" commandfor="academicYearsTable" command="--sort">Academic year<span class="ay-sort-icon" aria-hidden="true"></span></button>
                        </th>
                        <th scope="col" data-type="date">
                            <button type="button" commandfor="academicYearsTable" command="--sort">Opens<span class="ay-sort-icon" aria-hidden="true"></span></button>
                        </th>
                        <th scope="col" data-type="date">
                            <button type="button" commandfor="academicYearsTable" command="--sort">Closes<span class="ay-sort-icon" aria-hidden="true"></span></button>
                        </th>
                        <th scope="col" data-type="number">
                            <button type="button" commandfor="academicYearsTable" command="--sort">Terms<span class="ay-sort-icon" aria-hidden="true"></span></button>
                        </th>
                        <th scope="col" data-type="text">
                            <button type="button" commandfor="academicYearsTable" command="--sort">Status<span class="ay-sort-icon" aria-hidden="true"></span></button>
                        </th>
                        <th scope="col">Record</th>
                    </tr>
                </thead>
                <tbody id="ayYearsBody">
                    <tr>
                        <td colspan="7" class="ay-empty">
                            <div>
                                <p>Loading the academic year register…</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p class="visually-hidden" id="ayTableStatus" role="status" aria-live="polite"></p>
    </section>
</div>

<!-- Academic Year Detail Drawer -->
<div class="offcanvas offcanvas-end ay-detail-drawer" tabindex="-1" id="ayDetailDrawer" aria-labelledby="ayDetailTitle">
    <div class="offcanvas-header">
        <div>
            <p class="ay-panel-label">Year detail</p>
            <h2 class="offcanvas-title" id="ayDetailTitle">Academic year</h2>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close detail"></button>
    </div>
    <div class="offcanvas-body" id="ayDetailBody" aria-live="polite">
        <p class="ay-overview-message">Select a year to view its terms.</p>
    </div>
</div>

<!-- Bulk Date Edit Modal -->
<div class="modal fade ay-modal" id="ayBulkModal" tabindex="-1" aria-labelledby="ayBulkTitle">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="ayBulkTitle">Bulk edit dates</h5>
                    <p class="modal-subtitle">Shift opening and closing dates together; term structure is untouched.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="ayBulkForm">
                <div class="modal-body">
                    <p class="ay-bulk-summary" id="ayBulkSummary"></p>
                    <div class="ay-bulk-field">
                        <label class="form-label" for="ayBulkOffset">Days to shift</label>
                        <input class="form-control" id="ayBulkOffset" name="offset" type="number" step="1" min="-365" max="365" value="0" required autocomplete="off" aria-describedby="ayBulkHint">
                        <div class="form-text" id="ayBulkHint">Use a negative number to move dates earlier. Each year keeps its current span.</div>
                    </div>
                    <div class="ay-bulk-preview" id="ayBulkPreview"></div>
                    <div id="ayBulkResult"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="ayBulkSubmit" disabled>Apply to 0 years</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Academic Year Modal -->
<div class="modal fade ay-modal" id="editYearModal" tabindex="-1" aria-labelledby="ayEditTitle">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="ayEditTitle">Edit academic year</h5>
                    <p class="modal-subtitle">Adjust the recorded opening and closing dates for this year.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addAcademicYearForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="ayYearName">Academic year</label>
                        <input class="form-control" id="ayYearName" type="text" name="year_name" required readonly aria-describedby="ayYearNameHint">
                        <div class="form-text" id="ayYearNameHint">Derived from the opening date and cannot be changed here.</div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="ayStartDate">Opening date</label>
                            <input class="form-control" id="ayStartDate" type="date" name="start_date" required autocomplete="off">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="ayEndDate">Closing date</label>
                            <input class="form-control" id="ayEndDate" type="date" name="end_date" required autocomplete="off">
                        </div>
                    </div>
                    <p class="ay-form-hint">
                        <i class="bi bi-info-circle" aria-hidden="true"></i>
                        <span>Term dates and structure are managed in Manage Terms or Year Transition.</span>
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" data-year-write>Save changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php asset_script($appBase, 'js/pages/academic_years.js'); ?>
