<?php
/**
 * Manage Non-Teaching Staff Page
 * HTML structure only - all logic in js/pages/staff.js (manageNonTeachingStaffController)
 * Embedded in app_layout.php
 */
?>

<div class="card shadow">
  <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
    <h2 class="mb-0">👨‍💼 Manage Non-Teaching Staff</h2>
    <button class="btn btn-light btn-sm" onclick="manageNonTeachingStaffController.showCreateForm()">+ Add Staff</button>
  </div>
  <div class="card-body">
    <!-- Search and Filter -->
    <div class="row mb-3">
      <div class="col-md-6">
        <input type="text" id="searchStaff" class="form-control" placeholder="Search staff..." 
               onkeyup="manageNonTeachingStaffController.search(this.value)">
      </div>
      <div class="col-md-3">
        <select id="departmentFilter" class="form-select" onchange="manageNonTeachingStaffController.filterByDepartment(this.value)">
          <option value="">-- All Departments --</option>
        </select>
      </div>
      <div class="col-md-3">
        <select id="statusFilter" class="form-select" onchange="manageNonTeachingStaffController.filterByStatus(this.value)">
          <option value="">-- All Status --</option>
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </select>
      </div>
    </div>

    <!-- Staff Table -->
    <div id="staffTableContainer">
      <p class="text-muted">Loading staff records...</p>
    </div>
  </div>
</div>

<!-- Create/Edit Modal -->
<div class="modal fade" id="staffModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="staffModalLabel">Add Staff</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="staffForm">
        <div class="modal-body">
          <input type="hidden" id="staffId">
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
            <label class="form-label">Department</label>
            <select id="departmentSelect" class="form-select" required>
              <option value="">-- Select Department --</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Role</label>
            <input type="text" id="role" class="form-control" required>
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
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="accounts-tab" data-bs-toggle="tab" data-bs-target="#accounts" type="button" role="tab">Accounts & Statutory</button>
                        </li>
                    </ul>
                    <div class="tab-content" id="staffTabContent">
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
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status" required>
                                        <option value="Active">Active</option>
                                        <option value="Inactive">Inactive</option>
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
                        <!-- Department & Role -->
                        <div class="tab-pane fade" id="department" role="tabpanel">
                            <div class="row mb-3">
                                <div class="col-md-6 mb-3">
                                    <label for="department" class="form-label">Department</label>
                                    <input type="text" class="form-control" id="department" name="department">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="role" class="form-label">Role</label>
                                    <input type="text" class="form-control" id="role" name="role">
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
                    <button type="submit" name="add_staff" class="btn btn-primary">Save Staff</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const staffHeaders = <?php echo json_encode($staffHeaders); ?>;
const actionOptions = <?php echo json_encode($actionOptions); ?>;

function renderStaffTable(rows) {
    let html = `<table class="table table-bordered table-hover">
        <thead><tr>`;
    staffHeaders.forEach(h => html += `<th>${h}</th>`);
    if (actionOptions.length) html += '<th>Actions</th>';
    html += `</tr></thead><tbody>`;
    if (!rows.length) {
        html += `<tr><td colspan="${staffHeaders.length + 1}" class="text-center">No staff found.</td></tr>`;
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
    document.getElementById('staff-table-container').innerHTML = html;
}

function reloadStaffTable() {
    if (!window.StaffAPI) return;
    StaffAPI.list().then(res => {
        let rows = [];
        if (res && res.data && Array.isArray(res.data.staff)) {
            rows = res.data.staff.map((s, i) => [
                i + 1,
                s.name || (s.first_name + ' ' + s.last_name),
                s.staff_no,
                s.department,
                s.role,
                s.status
            ]);
        }
        // Use dummy data if no real data
        if (!rows.length) {
            rows = [
                [1, 'Jane Wambui', 'STF001', 'Accounts', 'Bursar', 'Active'],
                [2, 'Peter Njoroge', 'STF002', 'Maintenance', 'Caretaker', 'Active'],
                [3, 'Lucy Atieno', 'STF003', 'Administration', 'Secretary', 'Inactive'],
                [4, 'Samuel Kiprotich', 'STF004', 'Security', 'Guard', 'Active'],
                [5, 'Agnes Mwikali', 'STF005', 'Kitchen', 'Cook', 'Active'],
            ];
        }
        renderStaffTable(rows);
        attachStaffActionHandlers();
    }).catch(() => {
        renderStaffTable([]);
    });
}

function attachStaffActionHandlers() {
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
    reloadStaffTable();
    setInterval(reloadStaffTable, 30000);
    handleFormSubmit('#add-staff-form', (data, files) => StaffAPI.create(data, files), (result) => {
        showNotification('Staff added successfully!', 'success');
        reloadStaffTable();
        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('addStaffModal'));
        modal.hide();
    });
});
</script>