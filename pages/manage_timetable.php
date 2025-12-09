<?php
// filepath: /home/prof_angera/Projects/php_pages/Kingsway/pages/manage_timetable.php
?>

<div class="container mt-1">
  <h2 class="mb-4 d-flex justify-content-between align-items-center">
    <span><i class="bi bi-calendar3"></i> Timetable Management</span>
    <div class="btn-group">
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#generateTimetableModal">
        <i class="bi bi-gear"></i> Generate Timetable
      </button>
      <button class="btn btn-outline-primary">
        <i class="bi bi-download"></i> Export
      </button>
    </div>
  </h2>
  
  <div class="row mb-4">
    <div class="col-md-4">
      <label class="form-label">Select Class</label>
      <select class="form-select">
        <option>All Classes</option>
        <option>Form 4 East</option>
        <option>Grade 6 A</option>
        <option>Form 2 West</option>
        <option>Grade 8 B</option>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label">Select Teacher</label>
      <select class="form-select">
        <option>All Teachers</option>
        <option>John Kamau</option>
        <option>Mary Wanjiru</option>
        <option>Peter Ochieng</option>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label">View Type</label>
      <select class="form-select">
        <option>Weekly View</option>
        <option>Daily View</option>
        <option>Monthly View</option>
      </select>
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
