<?php
/**
 * Academic Calendar Page
 *
 * Read-only view of the school calendar. The shared AcademicEventsTable and
 * UpcomingEventsWidget keep this page consistent with the rest of the system.
 * Day-type and event management happen on the School Events / Year Calendar
 * pages, which only appear for staff with edit permissions.
 */
?>
<link rel="stylesheet" href="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/css/pages/academic-calendar.css?v=<?= asset_version('css/pages/academic-calendar.css') ?>">

<div class="academic-calendar-page kw-clean" id="academicCalendarPage">
    <header class="page-header">
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-3">
            <div>
                <p class="text-uppercase small fw-semibold mb-1">School calendar</p>
                <h1 class="mb-1">Academic Calendar</h1>
                <p class="text-muted mb-0">Term dates, holidays, examinations and scheduled events for the selected academic year.</p>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2 no-print" aria-label="Academic calendar actions">
                <label class="text-muted small mb-0 text-nowrap" for="academicYearFilter">Academic year</label>
                <select class="form-select form-select-sm" id="academicYearFilter" style="min-width: 15rem" aria-label="Select academic year">
                    <option>Loading…</option>
                </select>
                <button class="btn btn-sm btn-outline-success" id="exportCalendarCsv" type="button">
                    <i class="bi bi-file-csv me-1" aria-hidden="true"></i> Export CSV
                </button>
                <button class="btn btn-sm btn-outline-secondary" id="printCalendar" type="button">
                    <i class="bi bi-printer me-1" aria-hidden="true"></i> Print
                </button>
                <button class="btn btn-sm btn-outline-primary" id="calendarPdf" type="button" title="The official academic calendar for the selected year">
                    <i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i> Calendar PDF
                </button>
            </div>
        </div>
    </header>

    <p class="academic-calendar-print-context" id="academicCalendarPrintContext"></p>

    <!-- Shared events table: filters, rows, loading, empty and error states. -->
    <div id="calendarEventsTable"></div>

    <section class="card mt-4 no-print" aria-labelledby="upcomingEventsTitle">
        <div class="card-header">
            <h2 class="h5 mb-0" id="upcomingEventsTitle"><i class="bi bi-clock me-2" aria-hidden="true"></i>Upcoming Events</h2>
        </div>
        <div class="card-body">
            <ul class="list-group list-group-flush" id="upcomingEvents" aria-live="polite">
                <li class="list-group-item text-center text-muted py-4">
                    <div class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></div>Loading upcoming events…
                </li>
            </ul>
        </div>
    </section>
</div>

<?php asset_script($appBase, 'js/components/academic_events.js'); ?>
<?php asset_script($appBase, 'js/pages/academic_calendar.js'); ?>
