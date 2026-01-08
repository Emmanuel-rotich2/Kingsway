<?php
/**
 * Timetable Page
 * 
 * Purpose: View and manage school timetables
 * Features:
 * - Class timetables
 * - Teacher schedules
 * - Room allocation
 */
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1"><i class="fas fa-calendar-week me-2"></i>Timetable</h4>
                    <p class="text-muted mb-0">View and manage class and teacher timetables</p>
                </div>
                <div class="btn-group">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#generateTimetableModal">
                        <i class="fas fa-magic me-1"></i> Generate
                    </button>
                    <button class="btn btn-outline-secondary" id="printTimetable">
                        <i class="fas fa-print me-1"></i> Print
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- View Selector -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">View Type</label>
                    <select class="form-select" id="viewType">
                        <option value="class">By Class</option>
                        <option value="teacher">By Teacher</option>
                        <option value="room">By Room</option>
                    </select>
                </div>
                <div class="col-md-3" id="classSelector">
                    <label class="form-label">Select Class</label>
                    <select class="form-select" id="selectClass">
                        <option value="">Choose class...</option>
                    </select>
                </div>
                <div class="col-md-3 d-none" id="teacherSelector">
                    <label class="form-label">Select Teacher</label>
                    <select class="form-select" id="selectTeacher">
                        <option value="">Choose teacher...</option>
                    </select>
                </div>
                <div class="col-md-3 d-none" id="roomSelector">
                    <label class="form-label">Select Room</label>
                    <select class="form-select" id="selectRoom">
                        <option value="">Choose room...</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-primary w-100" id="loadTimetable">
                        <i class="fas fa-eye me-1"></i> View Timetable
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Timetable Display -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0" id="timetableTitle">Select a class/teacher to view timetable</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="timetableGrid">
                    <thead>
                        <tr>
                            <th style="width: 100px;">Time</th>
                            <th>Monday</th>
                            <th>Tuesday</th>
                            <th>Wednesday</th>
                            <th>Thursday</th>
                            <th>Friday</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="6" class="text-center text-muted">
                                <i class="fas fa-calendar-alt fa-3x mb-2"></i>
                                <p>Select a class or teacher to view the timetable</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="js/pages/timetable.js"></script>