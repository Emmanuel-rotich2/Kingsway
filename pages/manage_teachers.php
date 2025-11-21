<?php
// filepath: /home/opt/lampp/htdocs/Kingsway/pages/manage_students.php
include __DIR__ . '/../components/tables/table.php';

// Example: Fetch teachers from DB (replace with real DB logic)
$teacherHeaders = ['No', 'Name', 'Staff Number', 'Subject', 'Role', 'Status'];
$teacherRows = [
  [1, 'Mary Achieng', 'TCH001', 'Mathematics', 'Teacher', 'Active'],
  [2, 'John Kamau', 'TCH002', 'English', 'Head of Department', 'Active'],
  [3, 'Ali Hussein', 'TCH003', 'Science', 'Teacher', 'Inactive'],
  [4, 'Faith Wanjiru', 'TCH004', 'Kiswahili', 'Teacher', 'Active'],
  [5, 'Brian Otieno', 'TCH005', 'Geography', 'Deputy Principal', 'Active'],
];
// Actions for admin: Edit, Assign Role, Set Permissions, Activate, Deactivate, Delete, View Profile
$actionOptions = ['Edit', 'Assign Role', 'Set Permissions', 'Activate', 'Deactivate', 'Delete', 'View Profile'];
?>


<div class="container mt-5">
  <h2 class="mb-4 d-flex justify-content-between align-items-center">
    Teacher Management
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTeacherModal">
      <i class="bi bi-person-plus"></i> Add Teacher
    </button>
  </h2>
  <div id="teacher-table-container"></div>
</div>

<!-- Add Teacher Modal -->
<div class="modal fade" id="addTeacherModal" tabindex="-1" aria-labelledby="addTeacherModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <form id="add-teacher-form">
        <div class="modal-header">
          <h5 class="modal-title" id="addTeacherModalLabel">Register New Teacher</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <ul class="nav nav-tabs mb-3" id="teacherTab" role="tablist">
            <li class="nav-item" role="presentation">
              <button class="nav-link active" id="basic-tab" data-bs-toggle="tab" data-bs-target="#basic" type="button" role="tab">Basic Details</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="contact-tab" data-bs-toggle="tab" data-bs-target="#contact" type="button" role="tab">Contact Details</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="academic-tab" data-bs-toggle="tab" data-bs-target="#academic" type="button" role="tab">Academic Details</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="demographic-tab" data-bs-toggle="tab" data-bs-target="#demographic" type="button" role="tab">Demographic Data</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="accounts-tab" data-bs-toggle="tab" data-bs-target="#accounts" type="button" role="tab">Accounts & Statutory</button>
            </li>
          </ul>
          <div class="tab-content" id="teacherTabContent">
            <!-- Basic Details -->
            <div class="tab-pane fade show active" id="basic" role="tabpanel">
              <div class="row mb-3">
                <div class="col-md-4 mb-3">
                  <label for="name" class="form-label">Full Name</label>
                  <input type="text" class="form-control" id="name" name="name" required>
                </div>
                <div class="col-md-4 mb-3">
                  <label for="staff_no" class="form-label">Staff Number</label>
                  <input type="text" class="form-control" id="staff_no" name="staff_no" required>
                </div>
                <div class="col-md-4 mb-3">
                  <label for="role" class="form-label">Role</label>
                  <select class="form-select" id="role" name="role" required>
                    <option value="Teacher">Teacher</option>
                    <option value="Head of Department">Head of Department</option>
                    <option value="Deputy Principal">Deputy Principal</option>
                    <option value="Principal">Principal</option>
                  </select>
                </div>
              </div>
            </div>
            <!-- Contact Details -->
            <div class="tab-pane fade" id="contact" role="tabpanel">
              <div class="row mb-3">
                <div class="col-md-4 mb-3">
                  <label for="phone" class="form-label">Phone Number</label>
                  <input type="text" class="form-control" id="phone" name="phone">
                </div>
                <div class="col-md-4 mb-3">
                  <label for="email" class="form-label">Email Address</label>
                  <input type="email" class="form-control" id="email" name="email">
                </div>
                <div class="col-md-4 mb-3">
                  <label for="address" class="form-label">Address</label>
                  <input type="text" class="form-control" id="address" name="address">
                </div>
              </div>
            </div>
            <!-- Academic Details -->
            <div class="tab-pane fade" id="academic" role="tabpanel">
              <div class="row mb-3">
                <div class="col-md-4 mb-3">
                  <label for="subject" class="form-label">Subject</label>
                  <input type="text" class="form-control" id="subject" name="subject">
                </div>
                <div class="col-md-4 mb-3">
                  <label for="qualification" class="form-label">Highest Qualification</label>
                  <input type="text" class="form-control" id="qualification" name="qualification">
                </div>
                <div class="col-md-4 mb-3">
                  <label for="experience" class="form-label">Years of Experience</label>
                  <input type="number" class="form-control" id="experience" name="experience" min="0">
                </div>
              </div>
            </div>
            <!-- Demographic Data -->
            <div class="tab-pane fade" id="demographic" role="tabpanel">
              <div class="row mb-3">
                <div class="col-md-4 mb-3">
                  <label for="dob" class="form-label">Date of Birth</label>
                  <input type="date" class="form-control" id="dob" name="dob">
                </div>
                <div class="col-md-4 mb-3">
                  <label for="gender" class="form-label">Gender</label>
                  <select class="form-select" id="gender" name="gender">
                    <option value="">Select</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                    <option value="Other">Other</option>
                  </select>
                </div>
                <div class="col-md-4 mb-3">
                  <label for="national_id" class="form-label">National ID</label>
                  <input type="text" class="form-control" id="national_id" name="national_id">
                </div>
              </div>
            </div>
            <!-- Accounts & Statutory -->
            <div class="tab-pane fade" id="accounts" role="tabpanel">
              <div class="row mb-3">
                <div class="col-md-4 mb-3">
                  <label for="bank_account" class="form-label">Bank Account No.</label>
                  <input type="text" class="form-control" id="bank_account" name="bank_account">
                </div>
                <div class="col-md-4 mb-3">
                  <label for="kra_pin" class="form-label">KRA PIN</label>
                  <input type="text" class="form-control" id="kra_pin" name="kra_pin">
                </div>
                <div class="col-md-4 mb-3">
                  <label for="nssf" class="form-label">NSSF No.</label>
                  <input type="text" class="form-control" id="nssf" name="nssf">
                </div>
                <div class="col-md-4 mb-3">
                  <label for="nhif" class="form-label">NHIF No.</label>
                  <input type="text" class="form-control" id="nhif" name="nhif">
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" name="add_teacher" class="btn btn-primary">Save Teacher</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
const teacherHeaders = <?php echo json_encode($teacherHeaders); ?>;
const actionOptions = <?php echo json_encode($actionOptions); ?>;

function renderTeacherTable(rows) {
    let html = `<table class="table table-bordered table-hover">
        <thead><tr>`;
    teacherHeaders.forEach(h => html += `<th>${h}</th>`);
    if (actionOptions.length) html += '<th>Actions</th>';
    html += `</tr></thead><tbody>`;
    if (!rows.length) {
        html += `<tr><td colspan="${teacherHeaders.length + 1}" class="text-center">No teachers found.</td></tr>`;
    } else {
        rows.forEach((row, idx) => {
            html += '<tr>';
            row.forEach(cell => html += `<td>${cell}</td>`);
            // Actions
            html += '<td>';
            actionOptions.forEach(opt => {
                html += `<button class="btn btn-sm btn-outline-primary me-1 action-option" data-action="${opt}" data-row='${JSON.stringify(row)}'>${opt}</button>`;
            });
            html += '</td></tr>';
        });
    }
    html += '</tbody></table>';
    document.getElementById('teacher-table-container').innerHTML = html;
}

function reloadTeacherTable() {
    if (!window.StaffAPI) return;
    StaffAPI.list({role: 'teacher'}).then(res => {
        let rows = [];
        if (res && res.data && Array.isArray(res.data.staff)) {
            rows = res.data.staff.filter(s => s.role && s.role.toLowerCase().includes('teacher')).map((s, i) => [
                i + 1,
                s.name || (s.first_name + ' ' + s.last_name),
                s.staff_no,
                s.subject || '-',
                s.role,
                s.status
            ]);
        }
        // Use dummy data if no real data
        if (!rows.length) {
            rows = [
                [1, 'Mary Achieng', 'TCH001', 'Mathematics', 'Teacher', 'Active'],
                [2, 'John Kamau', 'TCH002', 'English', 'Head of Department', 'Active'],
                [3, 'Ali Hussein', 'TCH003', 'Science', 'Teacher', 'Inactive'],
                [4, 'Faith Wanjiru', 'TCH004', 'Kiswahili', 'Teacher', 'Active'],
                [5, 'Brian Otieno', 'TCH005', 'Geography', 'Deputy Principal', 'Active'],
            ];
        }
        renderTeacherTable(rows);
        attachTeacherActionHandlers();
    }).catch(() => {
        renderTeacherTable([]);
    });
}

function attachTeacherActionHandlers() {
    document.querySelectorAll('.action-option').forEach(item => {
        item.onclick = function(e) {
        e.preventDefault();
        const action = this.getAttribute('data-action');
        const rowData = JSON.parse(this.getAttribute('data-row'));
            showNotification(`Action: ${action} on ${rowData[1]}`, 'info');
            // TODO: Implement real action logic
        };
    });
}

document.addEventListener('DOMContentLoaded', function() {
    reloadTeacherTable();
    setInterval(reloadTeacherTable, 30000);
    handleFormSubmit('#add-teacher-form', (data, files) => StaffAPI.create(data, files), (result) => {
        showNotification('Teacher added successfully!', 'success');
        reloadTeacherTable();
        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('addTeacherModal'));
        modal.hide();
    });
  });
</script>