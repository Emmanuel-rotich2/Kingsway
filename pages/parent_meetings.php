<?php
/**
 * Parent Meetings Page
 * 
 * Purpose: Schedule and manage parent meetings
 * Features:
 * - Meeting scheduling
 * - Attendance tracking
 * - Minutes and follow-ups
 */
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1"><i class="fas fa-handshake me-2"></i>Parent Meetings</h4>
                    <p class="text-muted mb-0">Schedule and manage parent-teacher meetings</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#scheduleMeetingModal">
                    <i class="fas fa-plus me-1"></i> Schedule Meeting
                </button>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link active" data-bs-toggle="tab" href="#upcomingTab">Upcoming</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#pastTab">Past Meetings</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#calendarTab">Calendar View</a>
        </li>
    </ul>

    <div class="tab-content">
        <!-- Upcoming Meetings -->
        <div class="tab-pane fade show active" id="upcomingTab">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="upcomingMeetingsTable">
                            <thead>
                                <tr>
                                    <th>Date & Time</th>
                                    <th>Type</th>
                                    <th>Title</th>
                                    <th>Participants</th>
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

        <!-- Past Meetings -->
        <div class="tab-pane fade" id="pastTab">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table" id="pastMeetingsTable">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Title</th>
                                    <th>Attendance</th>
                                    <th>Minutes</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Calendar View -->
        <div class="tab-pane fade" id="calendarTab">
            <div class="card">
                <div class="card-body">
                    <div id="meetingsCalendar"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Schedule Meeting Modal -->
<div class="modal fade" id="scheduleMeetingModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Schedule Meeting</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="scheduleMeetingForm">
                    <div class="mb-3">
                        <label class="form-label">Meeting Type</label>
                        <select class="form-select" name="type" required>
                            <option value="">Select Type</option>
                            <option value="individual">Individual Parent</option>
                            <option value="class">Class Meeting</option>
                            <option value="general">General Meeting</option>
                            <option value="pta">PTA Meeting</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Title</label>
                        <input type="text" class="form-control" name="title" required>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label">Date</label>
                            <input type="date" class="form-control" name="date" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Time</label>
                            <input type="time" class="form-control" name="time" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Venue</label>
                        <input type="text" class="form-control" name="venue">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Agenda</label>
                        <textarea class="form-control" name="agenda" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="scheduleMeetingForm" class="btn btn-primary">Schedule</button>
            </div>
        </div>
    </div>
</div>

<script src="js/pages/parent_meetings.js"></script>