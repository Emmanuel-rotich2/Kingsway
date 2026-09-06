<?php
/**
 * Timetable Management Page – Production UI
 * All logic handled in: js/pages/manage_timetable.js (timetableController)
 * UI Theme: Green / White (Academic Professional)
 *
 * Role-based access:
 * - Subject Teacher: View own teaching timetable only
 * - Class Teacher: View class timetable, can report conflicts
 * - HOD: View department timetables, request changes
 * - Deputy Head Academic: Generate, edit, approve timetables
 * - Headteacher/Admin: Full control
 */
?>

<style>
/* =========================================================
   DESIGN TOKENS
========================================================= */
:root {
    --acad-primary: #198754;
    --acad-primary-dark: #146c43;
    --acad-primary-soft: #d1e7dd;
    --acad-bg-light: #f8f9fa;
    --acad-white: #ffffff;
    --acad-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}

.academic-header {
    background: linear-gradient(135deg, var(--acad-primary), var(--acad-primary-dark));
    color: #fff;
    border-radius: 12px;
    padding: 1.75rem 2rem;
    margin-bottom: 2rem;
    box-shadow: var(--acad-shadow);
}

.academic-card {
    background: var(--acad-white);
    border-radius: 12px;
    border-left: 4px solid var(--acad-primary);
    box-shadow: var(--acad-shadow);
    margin-bottom: 1.75rem;
}

.stat-card {
    background: var(--acad-primary-soft);
    border-radius: 10px;
    padding: 1.2rem;
    height: 100%;
}

.stat-number {
    font-size: 1.9rem;
    font-weight: 700;
    color: var(--acad-primary-dark);
}

#classTeacherSummaryCards .stat-card { border: 1px solid rgba(15, 23, 42, .08); }
#classTeacherSummaryCards .stat-number { color: #123b5d; }
#classTeacherSummaryCards small { color: #17324d; font-weight: 700; }
#classTeacherSummaryCards .text-muted { color: #36566e !important; }
#timetableCard.class-teacher-timetable .table-responsive { overflow-x: auto; }
#timetableCard.class-teacher-timetable table { min-width: 1180px; table-layout: fixed; }
#timetableCard.class-teacher-timetable th,
#timetableCard.class-teacher-timetable td { font-size: .72rem; vertical-align: middle; }
#timetableCard.class-teacher-timetable .class-stream-cell { width: 112px; white-space: nowrap; }
#timetableCard.class-teacher-timetable .lesson-copy { font-size: .68rem; line-height: 1.12; }
#timetableCard.class-teacher-timetable .break-merged { min-width: 40px; width: 40px; padding: .3rem .1rem; }
#timetableCard.class-teacher-timetable .break-merged strong { display: inline-flex; flex-direction: column; align-items: center; row-gap: .06rem; font-size: .8rem; font-weight: 800; line-height: 1; letter-spacing: .08em; }
#timetableCard.class-teacher-timetable .break-merged .vertical-word-gap { height: .35rem; }
#timetableCard.class-teacher-timetable th.break-time-header { min-width: 40px !important; width: 40px !important; padding-left: .1rem; padding-right: .1rem; white-space: nowrap; }

.btn-academic {
    background: var(--acad-primary);
    color: #fff;
    border: none;
}

.btn-academic:hover {
    background: var(--acad-primary-dark);
    color: #fff;
}

.table-academic thead {
    background: var(--acad-primary);
    color: #fff;
}

.nav-tabs {
    border-bottom: none;
}

.nav-tabs .nav-link {
    border: none;
    font-weight: 500;
    color: var(--acad-primary-dark);
    padding: 0.75rem 1.1rem;
}

.nav-tabs .nav-link.active {
    border-bottom: 3px solid var(--acad-primary);
    color: var(--acad-primary);
    background: transparent;
}
</style>

<!-- =======================================================
 HEADER
======================================================= -->
<div class="academic-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
        <h2 class="mb-1">
            <i class="bi bi-calendar3 me-2"></i>Timetable Management
        </h2>
        <small class="opacity-75">
            Create, view, and manage class timetables
        </small>
    </div>
    <div class="btn-group flex-wrap">
        <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#generateTimetableModal"
                data-role="deputy_head_academic,headteacher,admin">
            <i class="bi bi-gear me-1"></i>Generate
        </button>
        <button id="matrixDraftBtn" class="btn btn-warning btn-sm" onclick="timetableController.openMatrixDraft()"
                data-role="school_admin,deputy_head_academic,headteacher,class_teacher,admin">
            <i class="bi bi-grid-3x3-gap me-1"></i>Matrix Draft
        </button>
        <button class="btn btn-light btn-sm" onclick="timetableController.exportTimetable()"
                data-permission="schedules_view"
                data-role="deputy_head_academic,headteacher,class_teacher,admin">
            <i class="bi bi-download me-1"></i>Export
        </button>
        <button class="btn btn-light btn-sm" onclick="timetableController.printMyTimetable()"
                data-role="subject_teacher,class_teacher,intern">
            <i class="bi bi-printer me-1"></i>Print
        </button>
        <button class="btn btn-light btn-sm" onclick="timetableController.printMasterTimetable()"
                data-permission="schedules_view" data-role="school_admin,deputy_head_academic,headteacher,admin,director">
            <i class="bi bi-printer-fill me-1"></i>Print Master
        </button>
        <button id="reviewTimetableDraftsBtn" class="btn btn-light btn-sm" onclick="timetableController.openDraftReview()"
                data-role="school_admin,deputy_head_academic,headteacher,admin,director">
            <i class="bi bi-clipboard-check me-1"></i>Review Drafts
        </button>
        <button class="btn btn-light btn-sm" onclick="timetableController.showConflictReportModal()"
                data-role="subject_teacher,class_teacher,hod,intern">
            <i class="bi bi-exclamation-triangle me-1"></i>Report Conflict
        </button>
    </div>
</div>

<!-- =======================================================
 STATISTICS
======================================================= -->
<div id="timetableManagementStats" class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-number" id="ttTotalLessons">--</div>
            <small>Total Lessons</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-number" id="ttTeachingStaff">--</div>
            <small>Teaching Staff</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-number" id="ttRoomsUsed">--</div>
            <small>Rooms Used</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-number" id="ttConflicts">0</div>
            <small>Pending Conflicts</small>
        </div>
    </div>
</div>

<!-- Lower-primary class-teacher summary. Populated only for the assigned
     class-teacher view; leadership and subject-teacher views use the cards above. -->
<div id="classTeacherSummaryCards" class="row g-3 mb-4 d-none">
    <div class="col-sm-6 col-xl-3"><div class="stat-card bg-primary-subtle"><div class="stat-number" id="ttAssignedStreams">0</div><small>Assigned Streams</small><div class="text-muted small mt-1">Streams you coordinate</div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="stat-card bg-success-subtle"><div class="stat-number" id="ttClassLearningAreas">0</div><small>Learning Areas</small><div class="text-muted small mt-1">Areas available for planning</div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="stat-card bg-warning-subtle"><div class="stat-number" id="ttTimetableCompletion">0%</div><small>Timetable Completion</small><div class="text-muted small mt-1">Across your assigned streams</div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="stat-card bg-info-subtle"><div class="stat-number" id="ttClassDraftStatus">Not started</div><small>Draft Status</small><div class="text-muted small mt-1">Submit for academic review</div></div></div>
</div>

<!-- =======================================================
 FILTER BAR
======================================================= -->
<div class="academic-card p-3 mb-4">
    <div class="row g-3">
        <div class="col-md-3" data-role="school_admin,deputy_head_academic,headteacher,admin,director">
            <label class="form-label fw-semibold">View Scope</label>
            <select class="form-select" id="viewScopeFilter">
                <option value="master">Master Timetable</option>
                <option value="level">School Level</option>
                <option value="class_stream">Class / Stream</option>
                <option value="teacher">Teacher</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold" id="classFilterLabel">Class / Stream</label>
            <select class="form-select" id="classFilter">
                <option value="">Select a class stream</option>
            </select>
        </div>
        <div class="col-md-3" data-role="school_admin,deputy_head_academic,headteacher,admin,director">
            <label class="form-label fw-semibold">School Level</label>
            <select class="form-select" id="schoolLevelFilter">
                <option value="">Select school level</option>
            </select>
        </div>
        <div class="col-md-3" data-role="deputy_head_academic,headteacher,hod,admin">
            <label class="form-label fw-semibold">Select Teacher</label>
            <select class="form-select" id="teacherFilter">
                <option value="">All Teachers</option>
            </select>
        </div>
        <div class="col-md-3" data-role="deputy_head_academic,headteacher,hod,admin">
            <label class="form-label fw-semibold">Select Subject</label>
            <select class="form-select" id="subjectFilter">
                <option value="">All Subjects</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold">View Type</label>
            <select class="form-select" id="viewTypeFilter">
                <option value="weekly">Weekly View</option>
                <option value="daily">Daily View</option>
            </select>
        </div>
    </div>
</div>

<!-- =======================================================
 QUICK ACTIONS (Academic leadership only)
======================================================= -->
<div id="timetableQuickActions" class="academic-card p-3 mb-4" data-role="deputy_head_academic,headteacher,admin">
    <div class="d-flex align-items-center flex-wrap gap-2">
        <i class="bi bi-lightning-fill text-warning me-1"></i>
        <strong>Quick Actions:</strong>
        <button class="btn btn-sm btn-outline-success ms-2" onclick="timetableController.checkConflicts()">
            <i class="bi bi-check-circle me-1"></i>Check Conflicts
        </button>
        <button class="btn btn-sm btn-outline-success ms-1" onclick="timetableController.showTeacherWorkload()">
            <i class="bi bi-person-lines-fill me-1"></i>Teacher Workload
        </button>
        <button class="btn btn-sm btn-outline-success ms-1" onclick="timetableController.showRoomUtilization()">
            <i class="bi bi-door-open me-1"></i>Room Utilization
        </button>
        <span class="badge bg-warning ms-auto" id="pendingConflictsCount" style="display:none;">
            <i class="bi bi-exclamation-triangle"></i> <span>0</span> Pending Conflicts
        </span>
    </div>
</div>

<!-- =======================================================
 TIMETABLE GRID
======================================================= -->
<div class="card" id="timetableCard">
    <div class="card-header" style="background: var(--acad-primary); color: #fff; border-radius: 12px 12px 0 0;">
        <h5 class="mb-0"><i class="bi bi-table me-2"></i>Weekly Timetable - All Classes</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-academic">
                <thead>
                    <tr id="timetableHeader"><th scope="col">Day</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            <div class="spinner-border spinner-border-sm text-success me-2"></div>
                            Loading timetable... Select a class to view.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- =======================================================
 GENERATE TIMETABLE MODAL
======================================================= -->
<div class="modal fade" id="generateTimetableModal" tabindex="-1" aria-labelledby="generateTimetableLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header" style="background: var(--acad-primary); color: #fff;">
                <h5 class="modal-title" id="generateTimetableLabel">
                    <i class="bi bi-gear me-2"></i>Generate Timetable
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Select Class</label>
                    <select class="form-select" id="generateClass">
                        <option value="">All Classes</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Algorithm</label>
                    <select class="form-select" id="generateAlgorithm">
                        <option value="auto">Auto (Recommended)</option>
                        <option value="balanced">Balanced Load</option>
                        <option value="compact">Compact Schedule</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Academic Term</label>
                    <select class="form-select" id="generateTerm">
                        <option value="">Current Term</option>
                        <option value="Term 1">Term 1</option>
                        <option value="Term 2">Term 2</option>
                        <option value="Term 3">Term 3</option>
                    </select>
                </div>
                <div class="alert alert-info border-0 mb-0" style="background: var(--acad-primary-soft); color: var(--acad-primary-dark);">
                    <i class="bi bi-info-circle me-2"></i>
                    The auto algorithm considers teacher availability, room capacity, and subject sequencing for optimal results.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-academic" onclick="timetableController.generateTimetable && timetableController.generateTimetable()">
                    <i class="bi bi-gear me-1"></i>Generate
                </button>
            </div>
        </div>
    </div>
</div>

<!-- =======================================================
 TOAST NOTIFICATIONS
======================================================= -->
<div class="position-fixed top-0 end-0 p-3" style="z-index: 11000;">
    <div id="academicToast" class="toast">
        <div class="toast-header">
            <strong id="toastTitle" class="me-auto">Notice</strong>
            <button class="btn-close" data-bs-dismiss="toast"></button>
        </div>
        <div class="toast-body" id="toastBody"></div>
    </div>
</div>

<!-- =======================================================
 SCRIPTS
======================================================= -->
<?php asset_script($appBase, 'js/pages/manage_timetable.js'); ?>
