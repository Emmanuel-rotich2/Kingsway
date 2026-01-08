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

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1"><i class="fas fa-calendar-alt me-2"></i>School Events</h4>
                    <p class="text-muted mb-0">Plan and manage school events and activities</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEventModal">
                    <i class="fas fa-plus me-1"></i> New Event
                </button>
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
                        <option value="academic">Academic</option>
                        <option value="sports">Sports</option>
                        <option value="cultural">Cultural</option>
                        <option value="religious">Religious</option>
                        <option value="meeting">Meeting</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <select class="form-select" id="filterEventStatus">
                        <option value="">All Status</option>
                        <option value="upcoming">Upcoming</option>
                        <option value="ongoing">Ongoing</option>
                        <option value="completed">Completed</option>
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
                            <th>Event Name</th>
                            <th>Type</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Venue</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="js/pages/school_events.js"></script>