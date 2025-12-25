<?php
/**
 * Chapel Services Page (Chaplain's religious services management)
 * HTML structure only - logic will be in js/pages/chapel_services.js
 * Embedded in app_layout.php
 */
?>

<div class="card shadow-sm">
    <div class="card-header bg-gradient bg-secondary text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="fas fa-church"></i> Chapel Services</h4>
            <button class="btn btn-light btn-sm" id="addServiceBtn" data-permission="chapel_manage">
                <i class="bi bi-plus-circle"></i> Schedule Service
            </button>
        </div>
    </div>

    <div class="card-body">
        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-primary">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Services This Month</h6>
                        <h3 class="text-primary mb-0" id="servicesMonth">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Avg Attendance</h6>
                        <h3 class="text-success mb-0" id="avgAttendance">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-info">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Upcoming Services</h6>
                        <h3 class="text-info mb-0" id="upcomingServices">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-warning">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Active Programs</h6>
                        <h3 class="text-warning mb-0" id="activePrograms">0</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Row -->
        <div class="row mb-3">
            <div class="col-md-3">
                <input type="text" class="form-control" id="searchBox" placeholder="Search services...">
            </div>
            <div class="col-md-2">
                <select class="form-select" id="typeFilter">
                    <option value="">All Types</option>
                    <option value="sunday_service">Sunday Service</option>
                    <option value="midweek">Midweek Service</option>
                    <option value="prayer_meeting">Prayer Meeting</option>
                    <option value="bible_study">Bible Study</option>
                    <option value="special">Special Event</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" id="statusFilter">
                    <option value="">All Status</option>
                    <option value="scheduled">Scheduled</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            <div class="col-md-2">
                <input type="month" class="form-control" id="monthFilter">
            </div>
        </div>

        <!-- Services Table -->
        <div class="table-responsive">
            <table class="table table-hover" id="servicesTable">
                <thead class="table-light">
                    <tr>
                        <th>Date/Time</th>
                        <th>Service Type</th>
                        <th>Theme</th>
                        <th>Speaker</th>
                        <th>Attendance</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Dynamic content -->
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <nav>
            <ul class="pagination justify-content-center" id="pagination">
                <!-- Dynamic pagination -->
            </ul>
        </nav>
    </div>
</div>

<!-- Add/Edit Service Modal -->
<div class="modal fade" id="serviceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="serviceModalTitle">Schedule Service</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="serviceForm">
                    <input type="hidden" id="serviceId">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Service Type*</label>
                            <select class="form-select" id="serviceType" required>
                                <option value="sunday_service">Sunday Service</option>
                                <option value="midweek">Midweek Service</option>
                                <option value="prayer_meeting">Prayer Meeting</option>
                                <option value="bible_study">Bible Study</option>
                                <option value="youth_service">Youth Service</option>
                                <option value="special">Special Event</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Date*</label>
                            <input type="date" class="form-control" id="serviceDate" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Start Time*</label>
                            <input type="time" class="form-control" id="startTime" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">End Time</label>
                            <input type="time" class="form-control" id="endTime">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Theme/Topic*</label>
                        <input type="text" class="form-control" id="theme" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Scripture Reading</label>
                        <input type="text" class="form-control" id="scripture" placeholder="e.g., John 3:16-21">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Speaker/Preacher*</label>
                            <input type="text" class="form-control" id="speaker" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Worship Leader</label>
                            <input type="text" class="form-control" id="worshipLeader">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Location*</label>
                        <select class="form-select" id="location" required>
                            <option value="main_chapel">Main Chapel</option>
                            <option value="hall">School Hall</option>
                            <option value="outdoor">Outdoor Area</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Expected Attendance</label>
                        <input type="number" class="form-control" id="expectedAttendance" min="0">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes/Special Instructions</label>
                        <textarea class="form-control" id="notes" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status*</label>
                        <select class="form-select" id="status" required>
                            <option value="scheduled">Scheduled</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveServiceBtn">Save Service</button>
            </div>
        </div>
    </div>
</div>

<!-- Record Attendance Modal -->
<div class="modal fade" id="attendanceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title">Record Attendance</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="attendanceForm">
                    <input type="hidden" id="attendanceServiceId">
                    <div class="mb-3">
                        <p><strong>Service:</strong> <span id="attendanceServiceName"></span></p>
                        <p><strong>Date:</strong> <span id="attendanceDate"></span></p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Total Attendance*</label>
                        <input type="number" class="form-control" id="totalAttendance" required min="0">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Students</label>
                            <input type="number" class="form-control" id="studentsCount" min="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Staff</label>
                            <input type="number" class="form-control" id="staffCount" min="0">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Remarks</label>
                        <textarea class="form-control" id="attendanceRemarks" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveAttendanceBtn">Save Attendance</button>
            </div>
        </div>
    </div>
</div>

<!-- View Service Details Modal -->
<div class="modal fade" id="viewServiceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title">Service Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p><strong>Type:</strong> <span id="viewType"></span></p>
                        <p><strong>Date:</strong> <span id="viewDate"></span></p>
                        <p><strong>Time:</strong> <span id="viewTime"></span></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Location:</strong> <span id="viewLocation"></span></p>
                        <p><strong>Status:</strong> <span id="viewStatus"></span></p>
                        <p><strong>Attendance:</strong> <span id="viewAttendance"></span></p>
                    </div>
                </div>
                <div class="mb-3">
                    <p><strong>Theme:</strong> <span id="viewTheme"></span></p>
                    <p><strong>Scripture:</strong> <span id="viewScripture"></span></p>
                    <p><strong>Speaker:</strong> <span id="viewSpeaker"></span></p>
                    <p><strong>Worship Leader:</strong> <span id="viewWorship"></span></p>
                </div>
                <div class="mb-3">
                    <strong>Notes:</strong>
                    <p id="viewNotes" class="mt-2"></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="editFromViewBtn">
                    <i class="bi bi-pencil"></i> Edit
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // TODO: Implement chapelServicesController in js/pages/chapel_services.js
    console.log('Chapel Services page loaded');
});
</script>
