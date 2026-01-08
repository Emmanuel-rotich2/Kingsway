<?php
/**
 * Academic Calendar Page
 * 
 * Purpose: View and manage the school academic calendar
 * Features:
 * - Calendar view of academic events
 * - Manage term dates
 * - Add holidays and events
 * - Track important dates
 */
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1"><i class="fas fa-calendar-alt me-2"></i>Academic Calendar</h4>
                    <p class="text-muted mb-0">View and manage school calendar, events, and important dates</p>
                </div>
                <div class="btn-group">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEventModal">
                        <i class="fas fa-plus me-1"></i> Add Event
                    </button>
                    <button class="btn btn-outline-secondary" id="printCalendar">
                        <i class="fas fa-print me-1"></i> Print
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Calendar Controls -->
    <div class="row mb-4">
        <div class="col-md-4">
            <select class="form-select" id="academicYearFilter">
                <option value="">Select Academic Year</option>
            </select>
        </div>
        <div class="col-md-4">
            <select class="form-select" id="termFilter">
                <option value="">All Terms</option>
                <option value="1">Term 1</option>
                <option value="2">Term 2</option>
                <option value="3">Term 3</option>
            </select>
        </div>
        <div class="col-md-4">
            <div class="btn-group w-100">
                <button class="btn btn-outline-primary active" data-view="month">Month</button>
                <button class="btn btn-outline-primary" data-view="week">Week</button>
                <button class="btn btn-outline-primary" data-view="list">List</button>
            </div>
        </div>
    </div>

    <!-- Calendar Legend -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="d-flex flex-wrap gap-3">
                <span class="badge bg-primary"><i class="fas fa-circle me-1"></i> Term Dates</span>
                <span class="badge bg-danger"><i class="fas fa-circle me-1"></i> Holidays</span>
                <span class="badge bg-success"><i class="fas fa-circle me-1"></i> Exams</span>
                <span class="badge bg-warning text-dark"><i class="fas fa-circle me-1"></i> Events</span>
                <span class="badge bg-info"><i class="fas fa-circle me-1"></i> Meetings</span>
            </div>
        </div>
    </div>

    <!-- Calendar Container -->
    <div class="card">
        <div class="card-body">
            <div id="calendarContainer" style="min-height: 600px;">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading calendar...</span>
                    </div>
                    <p class="mt-2">Loading calendar...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Upcoming Events Sidebar -->
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Upcoming Events</h5>
                </div>
                <div class="card-body" id="upcomingEvents">
                    <div class="list-group list-group-flush">
                        <div class="text-center py-3">
                            <span class="text-muted">Loading upcoming events...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-umbrella-beach me-2"></i>Holidays This Term</h5>
                </div>
                <div class="card-body" id="holidaysList">
                    <div class="list-group list-group-flush">
                        <div class="text-center py-3">
                            <span class="text-muted">Loading holidays...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Event Modal -->
<div class="modal fade" id="addEventModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Calendar Event</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addEventForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Event Title</label>
                        <input type="text" class="form-control" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Event Type</label>
                        <select class="form-select" name="event_type" required>
                            <option value="">Select Type</option>
                            <option value="term">Term Date</option>
                            <option value="holiday">Holiday</option>
                            <option value="exam">Examination</option>
                            <option value="event">School Event</option>
                            <option value="meeting">Meeting</option>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Start Date</label>
                            <input type="date" class="form-control" name="start_date" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" name="end_date">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3"></textarea>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="all_day" id="allDayEvent" checked>
                        <label class="form-check-label" for="allDayEvent">All day event</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Event</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="js/pages/academic_calendar.js"></script>