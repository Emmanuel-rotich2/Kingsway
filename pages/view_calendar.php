<?php
/**
 * View Calendar Page
 * Purpose: Display academic calendar with events, holidays, and activities
 * Features: Monthly calendar view, event filtering, event creation, event type categorization
 */
?>

<div>
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1"><i class="bi bi-calendar3"></i> Academic Calendar</h4>
            <small class="text-muted">View and manage school events, holidays, and activities</small>
        </div>
        <div class="btn-group">
            <button class="btn btn-outline-primary btn-sm" id="exportCalendarBtn">
                <i class="bi bi-download"></i> Export
            </button>
            <button class="btn btn-success btn-sm" id="addEventBtn">
                <i class="bi bi-plus-circle"></i> Add Event
            </button>
        </div>
    </div>

    <!-- KPI Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-primary">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Total Events</h6>
                    <h3 class="text-primary mb-0" id="totalEvents">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-success">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">This Month</h6>
                    <h3 class="text-success mb-0" id="thisMonthEvents">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-info">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Academic Events</h6>
                    <h3 class="text-info mb-0" id="academicEvents">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-warning">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Extra-Curricular</h6>
                    <h3 class="text-warning mb-0" id="extraCurricularEvents">0</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label">Month</label>
                    <select class="form-select" id="monthFilter">
                        <option value="0">January</option>
                        <option value="1">February</option>
                        <option value="2">March</option>
                        <option value="3">April</option>
                        <option value="4">May</option>
                        <option value="5">June</option>
                        <option value="6">July</option>
                        <option value="7">August</option>
                        <option value="8">September</option>
                        <option value="9">October</option>
                        <option value="10">November</option>
                        <option value="11">December</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Year</label>
                    <select class="form-select" id="yearFilter"></select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Event Type</label>
                    <select class="form-select" id="eventTypeFilter">
                        <option value="">All Types</option>
                        <option value="academic">Academic</option>
                        <option value="exam">Examination</option>
                        <option value="holiday">Holiday</option>
                        <option value="meeting">Meeting</option>
                        <option value="sports">Sports</option>
                        <option value="extracurricular">Extra-Curricular</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input type="text" class="form-control" id="searchEvents" placeholder="Search events...">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary w-100" id="navigateCalendarBtn">
                        <i class="bi bi-arrow-right"></i> Go
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Calendar Grid -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <button class="btn btn-sm btn-outline-secondary" id="prevMonthBtn"><i class="bi bi-chevron-left"></i></button>
                    <h5 class="mb-0" id="calendarTitle">January 2026</h5>
                    <button class="btn btn-sm btn-outline-secondary" id="nextMonthBtn"><i class="bi bi-chevron-right"></i></button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0" id="calendarGrid">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center text-danger">Sun</th>
                                    <th class="text-center">Mon</th>
                                    <th class="text-center">Tue</th>
                                    <th class="text-center">Wed</th>
                                    <th class="text-center">Thu</th>
                                    <th class="text-center">Fri</th>
                                    <th class="text-center text-primary">Sat</th>
                                </tr>
                            </thead>
                            <tbody id="calendarBody">
                                <!-- Dynamic calendar cells -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Upcoming Events Sidebar -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="bi bi-clock"></i> Upcoming Events</h6>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush" id="upcomingEventsList">
                        <!-- Dynamic upcoming events -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Event Modal -->
<div class="modal fade" id="eventModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="eventModalLabel">Add Event</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="eventForm">
                    <input type="hidden" id="eventId">
                    <div class="mb-3">
                        <label class="form-label">Event Title *</label>
                        <input type="text" class="form-control" id="eventTitle" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Start Date *</label>
                            <input type="date" class="form-control" id="eventStartDate" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" id="eventEndDate">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Event Type *</label>
                            <select class="form-select" id="eventType" required>
                                <option value="academic">Academic</option>
                                <option value="exam">Examination</option>
                                <option value="holiday">Holiday</option>
                                <option value="meeting">Meeting</option>
                                <option value="sports">Sports</option>
                                <option value="extracurricular">Extra-Curricular</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Color</label>
                            <select class="form-select" id="eventColor">
                                <option value="primary">Blue</option>
                                <option value="success">Green</option>
                                <option value="danger">Red</option>
                                <option value="warning">Yellow</option>
                                <option value="info">Cyan</option>
                                <option value="secondary">Gray</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" id="eventDescription" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Venue</label>
                        <input type="text" class="form-control" id="eventVenue" placeholder="Event location">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveEventBtn">Save Event</button>
            </div>
        </div>
    </div>
</div>

<script src="<?= $appBase ?>js/pages/view_calendar.js?v=<?php echo time(); ?>"></script>
