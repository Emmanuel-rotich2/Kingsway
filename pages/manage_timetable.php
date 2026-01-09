<?php
/**
 * Timetable Management Page
 * 
 * Role-based access:
 * - Subject Teacher: View own teaching timetable only
 * - Class Teacher: View class timetable, can report conflicts
 * - HOD: View department timetables, request changes
 * - Deputy Head Academic: Generate, edit, approve timetables
 * - Headteacher/Admin: Full control
 */
?>

<div class="container mt-1">
  <h2 class="mb-4 d-flex justify-content-between align-items-center">
    <span><i class="bi bi-calendar3"></i> Timetable Management</span>
    <div class="btn-group">
      <!-- Generate Timetable - Deputy Head, Admin only -->
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#generateTimetableModal"
              data-permission="timetable_generate"
              data-role="deputy_head_academic,headteacher,admin">
        <i class="bi bi-gear"></i> Generate Timetable
      </button>
      <!-- Edit Timetable - Deputy Head, Admin only -->
      <button class="btn btn-outline-primary" onclick="timetableController.enterEditMode()"
              data-permission="timetable_edit"
              data-role="deputy_head_academic,admin">
        <i class="bi bi-pencil"></i> Edit
      </button>
      <!-- Export - Academic leadership -->
      <button class="btn btn-outline-secondary" onclick="timetableController.exportTimetable()"
              data-permission="timetable_export"
              data-role="deputy_head_academic,headteacher,class_teacher,admin">
        <i class="bi bi-download"></i> Export
      </button>
      <!-- Print My Timetable - Teachers -->
      <button class="btn btn-outline-info" onclick="timetableController.printMyTimetable()"
              data-role="subject_teacher,class_teacher,intern">
        <i class="bi bi-printer"></i> Print My Timetable
      </button>
      <!-- Report Conflict - Teachers -->
      <button class="btn btn-outline-warning" onclick="timetableController.showConflictReportModal()"
              data-role="subject_teacher,class_teacher,hod,intern">
        <i class="bi bi-exclamation-triangle"></i> Report Conflict
      </button>
    </div>
  </h2>
  
  <!-- Filter row -->
  <div class="row mb-4">
    <!-- Class filter - Hidden from teachers viewing own timetable -->
    <div class="col-md-3">
      <label class="form-label">Select Class</label>
      <select class="form-select" id="classFilter">
        <option value="">All Classes</option>
      </select>
    </div>
    <!-- Teacher filter - Only for academic leadership -->
    <div class="col-md-3" data-role="deputy_head_academic,headteacher,hod,admin">
      <label class="form-label">Select Teacher</label>
      <select class="form-select" id="teacherFilter">
        <option value="">All Teachers</option>
      </select>
    </div>
    <!-- Subject filter - HODs see subjects in department -->
    <div class="col-md-3" data-role="deputy_head_academic,headteacher,hod,admin">
      <label class="form-label">Select Subject</label>
      <select class="form-select" id="subjectFilter">
        <option value="">All Subjects</option>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label">View Type</label>
      <select class="form-select" id="viewTypeFilter">
        <option value="weekly">Weekly View</option>
        <option value="daily">Daily View</option>
        <option value="monthly">Monthly View</option>
      </select>
    </div>
  </div>
  
  <!-- Quick Actions - Academic leadership only -->
  <div class="row mb-3" data-role="deputy_head_academic,headteacher,admin">
    <div class="col-12">
      <div class="alert alert-info d-flex align-items-center">
        <i class="bi bi-info-circle me-2"></i>
        <span><strong>Quick Actions:</strong></span>
        <button class="btn btn-sm btn-outline-info ms-3" onclick="timetableController.checkConflicts()">
          <i class="bi bi-check-circle"></i> Check Conflicts
        </button>
        <button class="btn btn-sm btn-outline-info ms-2" onclick="timetableController.showTeacherWorkload()">
          <i class="bi bi-person-lines-fill"></i> Teacher Workload
        </button>
        <button class="btn btn-sm btn-outline-info ms-2" onclick="timetableController.showRoomUtilization()">
          <i class="bi bi-door-open"></i> Room Utilization
        </button>
        <span class="badge bg-warning ms-auto" id="pendingConflictsCount" style="display:none;">
          <i class="bi bi-exclamation-triangle"></i> <span>0</span> Pending Conflicts
        </span>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h5 class="mb-0">Weekly Timetable - Form 4 East</h5>
    </div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-bordered">
          <thead class="table-light">
            <tr>
              <th>Time</th>
              <th>Monday</th>
              <th>Tuesday</th>
              <th>Wednesday</th>
              <th>Thursday</th>
              <th>Friday</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><strong>8:00 - 9:00</strong></td>
              <td>Mathematics<br><small>John Kamau</small></td>
              <td>English<br><small>Mary Wanjiru</small></td>
              <td>Science<br><small>Peter Ochieng</small></td>
              <td>Kiswahili<br><small>Jane Akinyi</small></td>
              <td>Mathematics<br><small>John Kamau</small></td>
            </tr>
            <tr>
              <td><strong>9:00 - 10:00</strong></td>
              <td>English<br><small>Mary Wanjiru</small></td>
              <td>Mathematics<br><small>John Kamau</small></td>
              <td>History<br><small>David Mwangi</small></td>
              <td>Science<br><small>Peter Ochieng</small></td>
              <td>English<br><small>Mary Wanjiru</small></td>
            </tr>
            <tr>
              <td><strong>10:00 - 10:30</strong></td>
              <td colspan="5" class="table-warning text-center"><strong>BREAK</strong></td>
            </tr>
            <tr>
              <td><strong>10:30 - 11:30</strong></td>
              <td>Science<br><small>Peter Ochieng</small></td>
              <td>Kiswahili<br><small>Jane Akinyi</small></td>
              <td>Mathematics<br><small>John Kamau</small></td>
              <td>English<br><small>Mary Wanjiru</small></td>
              <td>Science<br><small>Peter Ochieng</small></td>
            </tr>
            <tr>
              <td><strong>11:30 - 12:30</strong></td>
              <td>Geography<br><small>David Mwangi</small></td>
              <td>Science<br><small>Peter Ochieng</small></td>
              <td>English<br><small>Mary Wanjiru</small></td>
              <td>Mathematics<br><small>John Kamau</small></td>
              <td>Kiswahili<br><small>Jane Akinyi</small></td>
            </tr>
            <tr>
              <td><strong>12:30 - 1:30</strong></td>
              <td colspan="5" class="table-info text-center"><strong>LUNCH BREAK</strong></td>
            </tr>
            <tr>
              <td><strong>1:30 - 2:30</strong></td>
              <td>PE<br><small>Sports Master</small></td>
              <td>History<br><small>David Mwangi</small></td>
              <td>Life Skills<br><small>Counselor</small></td>
              <td>Computer<br><small>ICT Teacher</small></td>
              <td>Games & Sports</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
