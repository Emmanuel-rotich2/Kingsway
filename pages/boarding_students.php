<?php
/**
 * Boarding Students Page
 * Boarding operations dashboard for Boarding Master / Matron / Housemother
 * Embedded in app_layout.php
 */

// Ensure $appBase is available for script loading
if (!isset($appBase)) {
    $appBase = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
    if ($appBase === '.' || $appBase === '/') {
        $appBase = '';
    }
}
?>

<div class="container-fluid py-4" id="boardingStudentsPage">

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-success text-white">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h4 class="mb-0">
                        <i class="fas fa-bed me-2"></i>
                        Boarding Students
                    </h4>
                    <small id="scopeSubtitle">Manage dormitory allocation, roll call, exeats, and boarding welfare</small>
                </div>
                <div class="btn-group">
                    <button class="btn btn-light btn-sm" id="refreshBtn">
                        <i class="bi bi-arrow-clockwise"></i> Refresh
                    </button>
                    <button class="btn btn-outline-light btn-sm" id="assignDormBtn">
                        <i class="bi bi-house me-1"></i> Assign Dormitory
                    </button>
                    <button class="btn btn-outline-light btn-sm" id="rollCallBtn">
                        <i class="bi bi-clipboard-check me-1"></i> Take Roll Call
                    </button>
                    <button class="btn btn-outline-light btn-sm" id="createExeatBtn">
                        <i class="bi bi-door-open me-1"></i> Create Exeat
                    </button>
                    <button class="btn btn-outline-light btn-sm" id="printBoardingListBtn">
                        <i class="bi bi-printer me-1"></i> Print List
                    </button>
                    <button class="btn btn-light btn-sm" id="exportBoardingSheetBtn">
                        <i class="bi bi-download me-1"></i> Export Sheet
                    </button>
                </div>
            </div>
        </div>

        <div class="card-body">

            <!-- Filters -->
            <div class="row g-3 mb-4">
                <div class="col-xl-2 col-md-4">
                    <label class="form-label fw-semibold">Academic Year</label>
                    <select class="form-select" id="academicYearFilter">
                        <option value="">All Years</option>
                    </select>
                </div>

                <div class="col-xl-2 col-md-4">
                    <label class="form-label fw-semibold">Class</label>
                    <select class="form-select" id="classFilter">
                        <option value="">All Classes</option>
                    </select>
                </div>

                <div class="col-xl-2 col-md-4">
                    <label class="form-label fw-semibold">Stream</label>
                    <select class="form-select" id="streamFilter">
                        <option value="">All Streams</option>
                    </select>
                </div>

                <div class="col-xl-2 col-md-4">
                    <label class="form-label fw-semibold">Gender</label>
                    <select class="form-select" id="genderFilter">
                        <option value="">All</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                    </select>
                </div>

                <div class="col-xl-2 col-md-4">
                    <label class="form-label fw-semibold">Dormitory</label>
                    <select class="form-select" id="dormitoryFilter">
                        <option value="">All Dormitories</option>
                    </select>
                </div>

                <div class="col-xl-2 col-md-4">
                    <label class="form-label fw-semibold">Bed Status</label>
                    <select class="form-select" id="bedStatusFilter">
                        <option value="">All</option>
                        <option value="assigned">Assigned</option>
                        <option value="unassigned">Unassigned</option>
                    </select>
                </div>

                <div class="col-xl-2 col-md-4">
                    <label class="form-label fw-semibold">Boarding Status</label>
                    <select class="form-select" id="boardingStatusFilter">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="on_leave">On Leave</option>
                        <option value="sick">Sick Bay</option>
                        <option value="checked_out">Checked Out</option>
                        <option value="suspended">Suspended</option>
                    </select>
                </div>

                <div class="col-xl-2 col-md-4">
                    <label class="form-label fw-semibold">Roll Call Status</label>
                    <select class="form-select" id="rollCallStatusFilter">
                        <option value="">All</option>
                        <option value="present">Present</option>
                        <option value="absent">Absent</option>
                        <option value="late">Late</option>
                        <option value="excused">Excused</option>
                    </select>
                </div>

                <div class="col-xl-3 col-md-6">
                    <label class="form-label fw-semibold">Search</label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-search"></i>
                        </span>
                        <input type="text" class="form-control" id="searchBox"
                               placeholder="Search by name, admission number, UPI, dormitory, bed">
                    </div>
                </div>

                <div class="col-xl-2 col-md-4 d-flex align-items-end">
                    <button class="btn btn-success w-100" id="applyFiltersBtn">
                        <i class="fas fa-filter me-1"></i> Apply
                    </button>
                </div>

                <div class="col-xl-2 col-md-4 d-flex align-items-end">
                    <button class="btn btn-outline-secondary w-100" id="resetFiltersBtn">
                        <i class="fas fa-undo me-1"></i> Reset
                    </button>
                </div>
            </div>

            <!-- Summary cards -->
            <div class="row g-3 mb-4">
                <div class="col-xl-2 col-md-4">
                    <div class="card border-0 bg-light h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle bg-success text-white p-3">
                                    <i class="fas fa-bed"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Total Boarders</small>
                                    <h4 class="mb-0" id="totalBoarders">0</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4">
                    <div class="card border-0 bg-light h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle bg-primary text-white p-3">
                                    <i class="fas fa-male"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Boys</small>
                                    <h4 class="mb-0" id="boysBoarders">0</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4">
                    <div class="card border-0 bg-light h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle bg-info text-white p-3">
                                    <i class="fas fa-female"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Girls</small>
                                    <h4 class="mb-0" id="girlsBoarders">0</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4">
                    <div class="card border-0 bg-light h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle bg-warning text-dark p-3">
                                    <i class="fas fa-door-open"></i>
                                </div>
                                <div>
                                    <small class="text-muted">On Exeat</small>
                                    <h4 class="mb-0" id="onExeatCount">0</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4">
                    <div class="card border-0 bg-light h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle bg-danger text-white p-3">
                                    <i class="fas fa-user-times"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Absent</small>
                                    <h4 class="mb-0" id="absentCount">0</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4">
                    <div class="card border-0 bg-light h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle bg-secondary text-white p-3">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Special Alerts</small>
                                    <h4 class="mb-0" id="specialAlertsCount">0</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- States -->
            <div id="studentsLoading" class="alert alert-info d-none">
                <i class="fas fa-spinner fa-spin me-2"></i> Loading boarding students...
            </div>

            <div id="studentsError" class="alert alert-danger d-none"></div>

            <div id="studentsForbidden" class="alert alert-warning d-none">
                <i class="fas fa-exclamation-triangle me-2"></i> You do not have permission to access boarding data.
            </div>

            <div id="studentsEmpty" class="alert alert-warning d-none">
                <i class="fas fa-info-circle me-2"></i> No boarding students found for the selected filters.
            </div>

            <!-- Main Table -->
            <div class="card border-0 shadow-sm" id="studentsCard">
                <div class="card-header bg-white">
                    <strong>
                        <i class="fas fa-list me-2 text-success"></i>
                        Boarding Students
                    </strong>
                </div>

                <div class="card-body table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th><input type="checkbox" id="selectAll"></th>
                                <th>Adm No</th>
                                <th>Student Name</th>
                                <th>Class</th>
                                <th>Stream</th>
                                <th>Gender</th>
                                <th>Dormitory</th>
                                <th>Room/Bed</th>
                                <th>Boarding Status</th>
                                <th>Roll Call</th>
                                <th>Exeat</th>
                                <th>Alert</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="studentsTableBody">
                            <tr>
                                <td class="text-center text-muted">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Boarding Profile Modal -->
<div class="modal fade" id="boardingProfileModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content border-0 shadow">

            <div class="modal-header bg-success text-white">
                <div>
                    <h5 class="modal-title mb-0">
                        <i class="fas fa-bed me-2"></i>
                        Boarding Profile
                    </h5>
                    <small id="modalSubtitle">Student boarding details</small>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div id="modalLoading" class="alert alert-info d-none">
                    <i class="fas fa-spinner fa-spin me-2"></i> Loading boarding profile...
                </div>

                <div id="modalError" class="alert alert-danger d-none"></div>

                <div id="modalBoardingContent">
                    <!-- Boarding profile details will be rendered here -->
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <button class="btn btn-warning" id="assignDormModalBtn">
                    <i class="bi bi-house me-1"></i> Assign Dormitory
                </button>
                <button class="btn btn-info" id="addBoardingNoteBtn">
                    <i class="bi bi-plus-circle me-1"></i> Add Note
                </button>
            </div>

        </div>
    </div>
</div>

<!-- Assign Dormitory Modal -->
<div class="modal fade" id="assignDormModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">

            <div class="modal-header bg-success text-white">
                <h5 class="modal-title mb-0">
                    <i class="fas fa-house me-2"></i>
                    Assign Dormitory
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <form id="assignDormForm">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Student</label>
                        <input type="text" class="form-control" id="assignStudentName" readonly>
                        <input type="hidden" id="assignStudentId">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Dormitory / House</label>
                        <select class="form-select" id="assignDormitory" required>
                            <option value="">Select Dormitory</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Room</label>
                        <input type="text" class="form-control" id="assignRoom" placeholder="e.g., Room 101">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Bed Number</label>
                        <input type="text" class="form-control" id="assignBed" placeholder="e.g., Bed A1">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Allocation Date</label>
                        <input type="date" class="form-control" id="assignDate" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Notes</label>
                        <textarea class="form-control" id="assignNotes" rows="2"></textarea>
                    </div>
                </form>
            </div>

            <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-success" id="saveAssignDormBtn">
                    <i class="bi bi-check-circle me-1"></i> Save Assignment
                </button>
            </div>

        </div>
    </div>
</div>

<!-- Roll Call Modal -->
<div class="modal fade" id="rollCallModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content border-0 shadow">

            <div class="modal-header bg-success text-white">
                <h5 class="modal-title mb-0">
                    <i class="fas fa-clipboard-check me-2"></i>
                    Take Roll Call
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <form id="rollCallForm">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">Date</label>
                            <input type="date" class="form-control" id="rollCallDate" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">Session</label>
                            <select class="form-select" id="rollCallSession" required>
                                <option value="morning">Morning</option>
                                <option value="afternoon">Afternoon</option>
                                <option value="evening">Evening</option>
                                <option value="night">Night</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">Dormitory</label>
                            <select class="form-select" id="rollCallDormitory">
                                <option value="">All Dormitories</option>
                            </select>
                        </div>
                    </div>
                </form>
                <div id="rollCallStudentsList" class="mt-3">
                    <p class="text-muted">Select date and session to load students for roll call.</p>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-success" id="saveRollCallBtn">
                    <i class="bi bi-check-circle me-1"></i> Save Roll Call
                </button>
            </div>

        </div>
    </div>
</div>

<!-- Exeat Modal -->
<div class="modal fade" id="exeatModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">

            <div class="modal-header bg-success text-white">
                <h5 class="modal-title mb-0">
                    <i class="fas fa-door-open me-2"></i>
                    Create Exeat
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <form id="exeatForm">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Student</label>
                        <select class="form-select" id="exeatStudent" required>
                            <option value="">Select Student</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Exeat Type</label>
                        <select class="form-select" id="exeatType" required>
                            <option value="EXEAT">Exeat (Weekend Home)</option>
                            <option value="MEDICAL_APPT">Medical Appointment</option>
                            <option value="EMERGENCY">Family Emergency</option>
                            <option value="BEREAVEMENT">Bereavement</option>
                            <option value="RELIGIOUS">Religious Observance</option>
                            <option value="OTHER">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Reason</label>
                        <textarea class="form-control" id="exeatReason" rows="2" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Destination</label>
                        <input type="text" class="form-control" id="exeatDestination" placeholder="e.g., Home">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Leave Date/Time</label>
                            <input type="datetime-local" class="form-control" id="exeatLeaveAt" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Expected Return</label>
                            <input type="datetime-local" class="form-control" id="exeatExpectedReturn" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Guardian Contacted</label>
                        <select class="form-select" id="exeatGuardianContacted">
                            <option value="0">No</option>
                            <option value="1">Yes</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Notes</label>
                        <textarea class="form-control" id="exeatNotes" rows="2"></textarea>
                    </div>
                </form>
            </div>

            <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-success" id="saveExeatBtn">
                    <i class="bi bi-check-circle me-1"></i> Create Exeat
                </button>
            </div>

        </div>
    </div>
</div>

<!-- Add Boarding Note Modal -->
<div class="modal fade" id="addBoardingNoteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title mb-0">Add Boarding Note</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addBoardingNoteForm">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Note Type</label>
                        <select class="form-select" id="boardingNoteType">
                            <option value="general">General</option>
                            <option value="dormitory">Dormitory</option>
                            <option value="welfare">Welfare</option>
                            <option value="discipline">Discipline</option>
                            <option value="health">Health</option>
                            <option value="safety">Safety</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Note</label>
                        <textarea class="form-control" id="boardingNoteContent" rows="4" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Visibility</label>
                        <select class="form-select" id="boardingNoteVisibility">
                            <option value="boarding">Boarding Staff Only</option>
                            <option value="staff">All Staff</option>
                            <option value="all">All</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Priority</label>
                        <select class="form-select" id="boardingNotePriority">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-info" id="saveBoardingNoteBtn">
                    <i class="bi bi-check-circle me-1"></i> Save Note
                </button>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo $appBase; ?>/js/pages/boarding_students.js?v=<?php echo time(); ?>"></script>
