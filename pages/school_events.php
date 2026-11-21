<?php
/**
 * School Events Page
 * 
 * Purpose: Manage school events and activities
 * Features:
 * - Event calendar
 * - Event planning and scheduling
 * - Participation tracking
 */
?>

<div>
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1"><i class="bi bi-calendar-alt me-2"></i>School Events</h4>
                    <p class="text-muted mb-0">Plan and manage school events and activities (synced with the academic calendar)</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-secondary" data-kw-onclick="SchoolEventsController.syncNow()"><i class="bi bi-arrow-repeat me-1"></i> Sync Calendar</button>
                    <button class="btn btn-primary" id="addEventBtn" data-kw-onclick="SchoolEventsController.openAddModal()" data-year-write>
                        <i class="bi bi-plus-lg me-1"></i> New Event
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Upcoming Events -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Upcoming Events</h5>
                </div>
                <div class="card-body">
                    <div id="eventsCalendar"></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Next 5 Events</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush" id="upcomingEventsList">
                        <li class="list-group-item text-center">Loading...</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Events List -->
    <div class="card">
        <div class="card-header">
            <div class="row">
                <div class="col-md-4">
                    <select class="form-select" id="filterEventType">
                        <option value="">All Types</option>
                        <option value="exam">Exam</option>
                        <option value="holiday">Holiday</option>
                        <option value="school_holiday">School Holiday</option>
                        <option value="public_holiday">Public Holiday</option>
                        <option value="half_day">Half Day</option>
                        <option value="special_event">Special Event</option>
                        <option value="opening">Term Opening</option>
                        <option value="closing">Term Closing</option>
                        <option value="sports">Sports</option>
                        <option value="cultural">Cultural</option>
                        <option value="meeting">Meeting</option>
                        <option value="general">General</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <select class="form-select" id="filterEventPeriod" aria-label="Period of the academic year">
                        <option value="">Whole academic year</option>
                    </select>
                </div>
                <div class="col-md-4 mt-2 mt-md-0">
                    <select class="form-select" id="filterEventStatus">
                        <option value="">All Status</option>
                        <option value="upcoming">Upcoming</option>
                        <option value="ongoing">Ongoing</option>
                        <option value="past">Completed / Past</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="eventsTable">
                    <thead>
                        <tr>
                            <th scope="col">Event Name</th>
                            <th scope="col">Type</th>
                            <th scope="col">Period / Week</th>
                            <th scope="col">Date</th>
                            <th scope="col">Time</th>
                            <th scope="col">Venue</th>
                            <th scope="col">Status</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php asset_script($appBase, 'js/pages/school_events.js'); ?>