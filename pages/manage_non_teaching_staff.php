<?php
include __DIR__ . '/../components/tables/table.php';

// Example: Fetch staff from DB (replace with real DB logic)
$staffHeaders = ['No', 'Name', 'Staff Number', 'Department', 'Role', 'Status'];
$staffRows = [
    [1, 'Jane Wambui', 'STF001', 'Accounts', 'Bursar', 'Active'],
    [2, 'Peter Njoroge', 'STF002', 'Maintenance', 'Caretaker', 'Active'],
    [3, 'Lucy Atieno', 'STF003', 'Administration', 'Secretary', 'Inactive'],
    [4, 'Samuel Kiprotich', 'STF004', 'Security', 'Guard', 'Active'],
    [5, 'Agnes Mwikali', 'STF005', 'Kitchen', 'Cook', 'Active'],
];
// Actions for admin: Edit, Assign Role, Set Permissions, Activate, Deactivate, Delete, View Profile
$actionOptions = ['Edit', 'Assign Role', 'Set Permissions', 'Activate', 'Deactivate', 'Delete', 'View Profile'];
?>


<div class="container mt-5">
    <h2 class="mb-4 d-flex justify-content-between align-items-center">
        Non-Teaching Staff Management
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStaffModal">
            <i class="bi bi-person-plus"></i> Add Staff
        </button>
    </h2>

    <?php renderTable("Staff List", $staffHeaders, $staffRows, true, $actionOptions); ?>
</div>

<!-- Add Staff Modal -->
<div class="modal fade" id="addStaffModal" tabindex="-1" aria-labelledby="addStaffModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <form id="add-staff-form">
                <div class="modal-header">
                    <h5 class="modal-title" id="addStaffModalLabel">Register New Staff</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <ul class="nav nav-tabs mb-3" id="staffTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="basic-tab" data-bs-toggle="tab" data-bs-target="#basic" type="button" role="tab">Basic Details</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="contact-tab" data-bs-toggle="tab" data-bs-target="#contact" type="button" role="tab">Contact Details</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="department-tab" data-bs-toggle="tab" data-bs-target="#department" type="button" role="tab">Department & Role</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="demographic-tab" data-bs-toggle="tab" data-bs-target="#demographic" type="button" role="tab">Demographic Data</button>
                        </li>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Add Staff AJAX (replace with real API endpoint)
    document.getElementById('add-staff-form').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        fetch('/Kingsway/api/add_staff.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    location.reload(); // Reload to show new staff (or use AJAX to update table)
                }
            });
    });

    // Action handler for admin actions
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.action-option').forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                const action = this.getAttribute('data-action');
                const rowData = JSON.parse(this.getAttribute('data-row'));
                if (action === 'Edit') {
                    alert('Edit staff: ' + rowData[1]);
                    // Open edit modal or form here
                } else if (action === 'Assign Role') {
                    alert('Assign role to: ' + rowData[1]);
                    // Open role assignment modal here
                } else if (action === 'Set Permissions') {
                    alert('Set permissions for: ' + rowData[1]);
                    // Open permissions modal here
                } else if (action === 'Activate') {
                    alert('Activate staff: ' + rowData[1]);
                    // Implement activate logic here
                } else if (action === 'Deactivate') {
                    alert('Deactivate staff: ' + rowData[1]);
                    // Implement deactivate logic here
                } else if (action === 'Delete') {
                    if (confirm('Delete staff: ' + rowData[1] + '?')) {
                        // Call delete API here
                        alert('Deleted: ' + rowData[1]);
                    }
                } else if (action === 'View Profile') {
                    alert('View profile for: ' + rowData[1]);
                    // Redirect or show modal
                }
            });
        });
    });
</script>