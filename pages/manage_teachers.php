<?php
/**
 * Manage Teachers Page
 * HTML structure only - all logic in js/pages/staff.js (manageTeachersController)
 * Embedded in app_layout.php
 */
?>

<div class="card shadow">
  <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
    <h2 class="mb-0">👨‍🏫 Manage Teachers</h2>
    <button class="btn btn-light btn-sm" onclick="manageTeachersController.showCreateForm()">+ Add Teacher</button>
  </div>
  <div class="card-body">
    <!-- Search and Filter -->
    <div class="row mb-3">
      <div class="col-md-6">
        <input type="text" id="searchTeachers" class="form-control" placeholder="Search teachers..." 
               onkeyup="manageTeachersController.search(this.value)">
      </div>
      <div class="col-md-3">
        <select id="departmentFilter" class="form-select" onchange="manageTeachersController.filterByDepartment(this.value)">
          <option value="">-- All Departments --</option>
        </select>
      </div>
      <div class="col-md-3">
        <select id="statusFilter" class="form-select" onchange="manageTeachersController.filterByStatus(this.value)">
          <option value="">-- All Status --</option>
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </select>
      </div>
    </div>

    <!-- Teachers Table -->
    <div id="teachersTableContainer">
      <p class="text-muted">Loading teachers...</p>
    </div>
  </div>
</div>

<!-- Create/Edit Modal -->
<div class="modal fade" id="teacherModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="teacherModalLabel">Add Teacher</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="teacherForm">
        <div class="modal-body">
          <input type="hidden" id="teacherId">
          <div class="mb-3">
            <label class="form-label">First Name</label>
            <input type="text" id="firstName" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Last Name</label>
            <input type="text" id="lastName" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" id="email" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Department/Subject</label>
            <select id="departmentSelect" class="form-select" required>
              <option value="">-- Select Department --</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Status</label>
            <select id="statusSelect" class="form-select" required>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
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