<?php
/**
 * Mark Attendance Page
 * HTML structure only - all logic in js/pages/attendance.js (markAttendanceController)
 * Embedded in app_layout.php
 */
?>

<div class="card shadow">
    <div class="card-header bg-success text-white">
        <h2 class="mb-0">✓ Mark Student Attendance</h2>
    </div>
    <div class="card-body">
        <form id="attendanceForm">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Class</label>
                    <select id="classSelect" class="form-select" required
                        onchange="markAttendanceController.loadStudents()">
                        <option value="">-- Select Class --</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Date</label>
                    <input type="date" id="attendanceDate" class="form-control" required>
                </div>
            </div>

            <div id="studentsContainer" class="mt-4">
                <p class="text-muted">Please select a class to load students</p>
            </div>

            <button type="submit" class="btn btn-success btn-lg w-100 mt-3">Submit Attendance</button>
        </form>
    </div>
</div>
<th style="padding: 10px; border: 1px solid #ddd;">Student Name</th>
<th style="padding: 10px; border: 1px solid #ddd;">Present</th>
</tr>
</thead>
<tbody>
    <!-- Students will be loaded here by JavaScript -->
</tbody>
</table>
</div>

<button type="submit" class="btn btn-success btn-lg w-100 mt-3">Submit Attendance</button>
</form>
</div>