<?php
/**
 * Year Calendar
 *
 * Purpose: Full-year academic calendar view with day editing
 * Features:
 * - Data display and filtering
 * - Mark days as holidays, closures or special events (emergency/national
 *   holidays, government-declared off days) - preserved across calendar
 *   regenerations
 * - Search and export
 */
?>

<div>
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1"><i class="bi bi-calendar-alt me-2"></i>Year Calendar</h4>
                    <p class="text-muted mb-0">Full-year academic calendar - mark holidays, closures and special days</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center">
                <div class="rounded-circle bg-primary bg-opacity-10 p-3 me-3"><i class="bi bi-calendar-day text-primary fa-lg"></i></div>
                <div><h6 class="text-muted mb-1">Total Days</h6><h4 class="mb-0" id="statEvents">0</h4></div>
            </div></div></div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center">
                <div class="rounded-circle bg-success bg-opacity-10 p-3 me-3"><i class="bi bi-building text-success fa-lg"></i></div>
                <div><h6 class="text-muted mb-1">School Days</h6><h4 class="mb-0" id="statTermDays">0</h4></div>
            </div></div></div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center">
                <div class="rounded-circle bg-warning bg-opacity-10 p-3 me-3"><i class="bi bi-brightness-high text-warning fa-lg"></i></div>
                <div><h6 class="text-muted mb-1">Holidays</h6><h4 class="mb-0" id="statHolidays">0</h4></div>
            </div></div></div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center">
                <div class="rounded-circle bg-danger bg-opacity-10 p-3 me-3"><i class="bi bi-pencil text-danger fa-lg"></i></div>
                <div><h6 class="text-muted mb-1">Exam Days</h6><h4 class="mb-0" id="statExamDays">0</h4></div>
            </div></div></div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3"><input type="text" class="form-control" id="searchInput" placeholder="Search..."></div>
                <div class="col-md-3"><select class="form-select" id="filterSelect"><option value="">All Types</option></select></div>
                <div class="col-md-2"><select class="form-select" id="termFilter"><option value="">All Terms</option></select></div>
                <div class="col-md-2"><input type="date" class="form-control" id="dateFilter"></div>
                <div class="col-md-2"><button class="btn btn-outline-secondary w-100" onclick="YearCalendarController.refresh()"><i class="bi bi-arrow-clockwise"></i></button></div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="bi bi-table me-2"></i>Year Calendar</h6>
            <button class="btn btn-sm btn-outline-success" onclick="YearCalendarController.exportCSV()"><i class="bi bi-file-csv me-1"></i> Export</button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="dataTable">
                    <thead class="table-light"><tr><th scope="col">#</th><th scope="col">Term</th><th scope="col">Week</th><th scope="col">Date</th><th scope="col">Day</th><th scope="col">Event</th><th scope="col">Type</th><th scope="col">Actions</th></tr></thead>
                    <tbody><tr><td colspan="8" class="text-center text-muted py-4">Loading...</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="dayModal" tabindex="-1"><div class="modal-dialog modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-calendar2-day me-2"></i>Edit Calendar Day</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
        <input type="hidden" id="dayId">
        <p class="text-muted small mb-3" id="dayContext"></p>
        <div class="mb-3"><label class="form-label">Day Type</label>
            <select class="form-select" id="dayType">
                <option value="school_day">School Day</option>
                <option value="half_day">Half Day</option>
                <option value="exam_day">Exam Day</option>
                <option value="special_event">Special Event</option>
                <option value="holiday">Holiday (School Closed)</option>
                <option value="public_holiday">Public / National Holiday</option>
                <option value="school_holiday">School Holiday (no classes)</option>
            </select>
        </div>
        <div class="mb-3"><label class="form-label">Title <span class="text-muted small">(e.g. National Mourning Day, School Closure)</span></label>
            <input type="text" class="form-control" id="dayTitle" maxlength="100"></div>
        <div class="mb-3"><label class="form-label">Reason / Notes</label>
            <textarea class="form-control" id="dayDescription" rows="3" maxlength="500"></textarea></div>
        <div class="border-top pt-3 mt-3">
            <label class="form-label fw-semibold">Meetings &amp; Events on this day</label>
            <div id="dayEventsList" class="mb-2"></div>
            <p class="text-muted small mb-0"><i class="bi bi-info-circle me-1"></i>Events are created and managed from the <strong>School Events</strong> page.</p>
            <div class="row g-2 d-none">
                <div class="col-12"><input type="text" class="form-control" id="eventTitle" maxlength="150" placeholder="Event / meeting title (e.g. Sports Day, AGM, Prayer Day, Exams)"></div>
                <div class="col-6"><select class="form-select" id="eventType">
                    <option value="Academic">Academic</option>
                    <option value="Meeting">Meeting</option>
                    <option value="Ceremony">Ceremony</option>
                    <option value="Sports">Sports</option>
                    <option value="Religious">Religious</option>
                    <option value="Exam">Exam</option>
                    <option value="Other">Other</option>
                </select></div>
                <div class="col-6"><input type="text" class="form-control" id="eventLocation" maxlength="100" placeholder="Location (optional)"></div>
                <div class="col-6"><input type="time" class="form-control" id="eventStartTime"></div>
                <div class="col-6"><input type="time" class="form-control" id="eventEndTime"></div>
                <div class="col-6"><input type="date" class="form-control" id="eventEndDate" title="End date (blank = same day; use for multi-day events like exams)"></div>
                <div class="col-12"><button class="btn btn-outline-primary w-100" onclick="YearCalendarController.addEvent()"><i class="bi bi-plus-lg me-1"></i> Add Event</button></div>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-danger" onclick="YearCalendarController.markExamWeek()"><i class="bi bi-pencil-square me-1"></i> Mark Whole Week as Exam Week</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="YearCalendarController.saveDay()"><i class="bi bi-check-lg me-1"></i> Save</button>
    </div>
</div></div></div>

<script src="<?= $appBase ?>/js/pages/year_calendar.js?v=<?php echo time(); ?>"></script>
