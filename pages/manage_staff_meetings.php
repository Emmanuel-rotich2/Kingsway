<?php
/**
 * Manage Staff Meetings
 *
 * Purpose: Internal staff meetings scheduled by heads (director, school admin,
 * HODs to department members or selected members, deputies, class teachers,
 * etc.), fully integrated with the academic calendar:
 * - Every meeting creates a linked calendar event (school_events, type Meeting)
 *   so it appears on the Year Calendar / academic calendar / events pages with
 *   the venue tagged and the online meeting link attached.
 * - Attendees are invited with RSVP (accepted / declined / maybe).
 * - Attendees get in-app notifications when scheduled, updated or reminded.
 * Features:
 * - List, filter (type/status/department), search, export
 * - Schedule / edit / cancel / delete meetings
 * - RSVP for invited staff, remind attendees
 */
?>

<div>
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h4 class="mb-1"><i class="bi bi-people me-2"></i>Staff Meetings</h4>
                    <p class="text-muted mb-0">Internal meetings - appear on the academic calendar with venue / online link, attendee RSVP and reminders</p>
                </div>
                <button class="btn btn-primary" onclick="StaffMeetingsController.showAddModal()"><i class="bi bi-plus-lg me-1"></i> Schedule Meeting</button>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center">
                <div class="rounded-circle bg-primary bg-opacity-10 p-3 me-3"><i class="bi bi-calendar-check text-primary fa-lg"></i></div>
                <div><h6 class="text-muted mb-1">Total</h6><h4 class="mb-0" id="statTotal">0</h4></div>
            </div></div></div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center">
                <div class="rounded-circle bg-success bg-opacity-10 p-3 me-3"><i class="bi bi-clock text-success fa-lg"></i></div>
                <div><h6 class="text-muted mb-1">Upcoming</h6><h4 class="mb-0" id="statUpcoming">0</h4></div>
            </div></div></div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center">
                <div class="rounded-circle bg-info bg-opacity-10 p-3 me-3"><i class="bi bi-calendar-week text-info fa-lg"></i></div>
                <div><h6 class="text-muted mb-1">This Week</h6><h4 class="mb-0" id="statWeek">0</h4></div>
            </div></div></div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center">
                <div class="rounded-circle bg-secondary bg-opacity-10 p-3 me-3"><i class="bi bi-clock-history text-secondary fa-lg"></i></div>
                <div><h6 class="text-muted mb-1">Past / Held</h6><h4 class="mb-0" id="statPast">0</h4></div>
            </div></div></div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3"><input type="text" class="form-control" id="searchInput" placeholder="Search title / venue / organizer..."></div>
                <div class="col-md-2"><select class="form-select" id="statusFilter"><option value="">All Status</option><option value="upcoming">Upcoming</option><option value="ongoing">Ongoing</option><option value="completed">Past / Held</option><option value="cancelled">Cancelled</option></select></div>
                <div class="col-md-2"><select class="form-select" id="typeFilter"><option value="">All Types</option></select></div>
                <div class="col-md-2"><select class="form-select" id="departmentFilter"><option value="">All Departments</option></select></div>
                <div class="col-md-1"><button class="btn btn-outline-secondary w-100" onclick="StaffMeetingsController.refresh()"><i class="bi bi-arrow-clockwise"></i></button></div>
                <div class="col-md-2"><button class="btn btn-outline-success w-100" onclick="StaffMeetingsController.exportCSV()"><i class="bi bi-file-csv me-1"></i> Export</button></div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="bi bi-table me-2"></i>Meetings</h6>
            <span class="text-muted small"><i class="bi bi-info-circle me-1"></i>Meetings automatically appear on the academic calendar (venue tagged / online link attached).</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="dataTable">
                    <thead class="table-light"><tr><th scope="col">#</th><th scope="col">Meeting</th><th scope="col">When</th><th scope="col">Venue / Link</th><th scope="col">Organizer</th><th scope="col">Attendees</th><th scope="col">My RSVP</th><th scope="col">Status</th><th scope="col">Actions</th></tr></thead>
                    <tbody><tr><td colspan="9" class="text-center text-muted py-4">Loading...</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="formModal" tabindex="-1"><div class="modal-dialog modal-dialog-scrollable modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="formModalTitle"><i class="bi bi-calendar-plus me-2"></i>Schedule Meeting</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><form id="recordForm"><input type="hidden" id="recordId">
        <div class="mb-3"><label class="form-label">Meeting Title <span class="text-danger">*</span></label><input type="text" class="form-control" id="recordTitle" required placeholder="e.g., Departmental Meeting - Academics"></div>
        <div class="row g-3">
            <div class="col-md-4 mb-3"><label class="form-label">Meeting Type</label><select class="form-select" id="recordType">
                <option value="general">General</option>
                <option value="departmental">Departmental</option>
                <option value="administrative">Administrative</option>
                <option value="heads">Heads</option>
                <option value="class_teachers">Class Teachers</option>
                <option value="assembly">Assembly</option>
                <option value="other">Other</option>
            </select></div>
            <div class="col-md-4 mb-3"><label class="form-label">Date <span class="text-danger">*</span></label><input type="date" class="form-control" id="recordDate" required></div>
            <div class="col-md-4 mb-3"><label class="form-label">Department (optional - invite whole department)</label><select class="form-select" id="recordDepartment"><option value="">-- No department --</option></select></div>
        </div>
        <div class="row g-3">
            <div class="col-md-4 mb-3"><label class="form-label">Start Time <span class="text-danger">*</span></label><input type="time" class="form-control" id="recordStartTime" required></div>
            <div class="col-md-4 mb-3"><label class="form-label">End Time</label><input type="time" class="form-control" id="recordEndTime"></div>
            <div class="col-md-4 mb-3"><label class="form-label">Venue <span class="text-muted small">(physical location)</span></label><input type="text" class="form-control" id="recordVenue" placeholder="e.g., Staff Room, Board Room"></div>
        </div>
        <div class="mb-3"><label class="form-label">Online Meeting Link <span class="text-muted small">(Zoom / Google Meet / Teams - shown on the calendar event)</span></label><input type="url" class="form-control" id="recordLink" placeholder="https://meet.google.com/..."></div>
        <div class="mb-3"><label class="form-label">Attendees <span class="text-muted small">(select staff - use Ctrl/Cmd to multi-select)</span></label>
            <select class="form-select" id="recordAttendees" multiple size="6"></select>
        </div>
        <div class="mb-3"><label class="form-label">Agenda</label><textarea class="form-control" id="recordAgenda" rows="2" placeholder="1. ..."></textarea></div>
        <div class="mb-3"><label class="form-label">Description / Notes</label><textarea class="form-control" id="recordDescription" rows="2"></textarea></div>
    </form></div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-primary" onclick="StaffMeetingsController.saveRecord()"><i class="bi bi-check-lg me-1"></i> Save Meeting</button></div>
</div></div></div>

<div class="modal fade" id="detailModal" tabindex="-1"><div class="modal-dialog modal-dialog-scrollable modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="detailModalTitle"><i class="bi bi-people me-2"></i>Meeting</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body" id="detailModalBody"></div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
</div></div></div>

<script src="<?= $appBase ?>/js/pages/manage_staff_meetings.js?v=<?php echo time(); ?>"></script>
