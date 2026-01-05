/**
 * Staff Page Controller
 * Initializes DataTable for staff management with create/edit/delete/assignment operations
 */

let staffTable = null;
let staffModal = null;

// StaffManagementController for manage_staff.php - handles staff CRUD with file uploads
const staffManagementController = {
    allStaff: [],
    filteredStaff: [],
    departments: [],
    roles: [],
    currentFilters: {},

    init: async function() {
        try {
            await Promise.all([
                this.loadStaff(),
                this.loadDepartments(),
                this.loadRoles(),
                this.loadSupervisors()
            ]);
            this.loadStatistics();
        } catch (error) {
            console.error('Error initializing staff management:', error);
        }
    },

    loadStaff: async function() {
        try {
            const response = await window.API.apiCall('/staff/index', 'GET');
            this.allStaff = response.data || response || [];
            this.filteredStaff = [...this.allStaff];
            this.renderStaffTables();
        } catch (error) {
            console.error('Error loading staff:', error);
        }
    },

    loadSupervisors: async function() {
        try {
            // Load all staff members as potential supervisors
            const response = await window.API.apiCall('/staff/index', 'GET');
            const supervisors = response.data || response || [];
            const el = document.getElementById('staffSupervisor');
            if (el) {
                el.innerHTML = '<option value="">-- Select Supervisor --</option>';
                supervisors.forEach(s => {
                    el.innerHTML += `<option value="${s.id}">${s.first_name} ${s.last_name} (${s.staff_no || s.position || 'Staff'})</option>`;
                });
            }
        } catch (error) {
            console.warn('Error loading supervisors:', error);
        }
    },

    loadDepartments: async function() {
        try {
            const response = await window.API.apiCall('/staff/departments-get', 'GET');
            this.departments = response.data || response || [];
            this.populateDepartmentDropdowns();
        } catch (error) {
            console.error('Error loading departments:', error);
        }
    },

    loadRoles: async function() {
        try {
            const response = await API.users.getRoles();
            this.roles = response.data || response || [];
            this.populateRoleDropdowns();
        } catch (error) {
            console.error('Error loading roles:', error);
        }
    },

    populateDepartmentDropdowns: function() {
        const selects = ['staffDepartment', 'departmentFilter'];
        selects.forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                const isFilter = id.includes('Filter');
                el.innerHTML = isFilter ? '<option value="">All Departments</option>' : '<option value="">Select Department</option>';
                this.departments.forEach(dept => {
                    el.innerHTML += `<option value="${dept.id}">${dept.name}</option>`;
                });
            }
        });
    },

    populateRoleDropdowns: function() {
        const el = document.getElementById('staffRole');
        if (el) {
            el.innerHTML = '<option value="">Select Role</option>';
            this.roles.forEach(role => {
                el.innerHTML += `<option value="${role.role_id || role.id}">${role.role_name || role.name}</option>`;
            });
        }
        const filterEl = document.getElementById('roleFilter');
        if (filterEl) {
            filterEl.innerHTML = '<option value="">All Roles</option>';
            this.roles.forEach(role => {
                filterEl.innerHTML += `<option value="${role.role_id || role.id}">${role.role_name || role.name}</option>`;
            });
        }
    },

    loadStatistics: function() {
        const total = this.allStaff.length;
        const teaching = this.allStaff.filter(s => s.staff_type === 'teaching').length;
        const nonTeaching = this.allStaff.filter(s => s.staff_type === 'non-teaching').length;
        const onLeave = this.allStaff.filter(s => s.status === 'on_leave').length;

        document.getElementById('totalStaffCount').textContent = total;
        document.getElementById('teachingStaffCount').textContent = teaching;
        document.getElementById('nonTeachingCount').textContent = nonTeaching;
        document.getElementById('onLeaveCount').textContent = onLeave;
    },

    renderStaffTables: function() {
        this.renderStaffTable('allStaffTable', this.filteredStaff);
        this.renderStaffTable('teachingStaffTable', this.filteredStaff.filter(s => s.staff_type === 'teaching'));
        this.renderStaffTable('nonTeachingStaffTable', this.filteredStaff.filter(s => s.staff_type === 'non-teaching'));
    },

    renderStaffTable: function(containerId, staffList) {
        const container = document.getElementById(containerId);
        if (!container) return;

        if (staffList.length === 0) {
            container.innerHTML = '<div class="alert alert-info">No staff found</div>';
            return;
        }

        let html = `
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Photo</th>
                            <th>Name</th>
                            <th>Staff No</th>
                            <th>Department</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        staffList.forEach(staff => {
            const photo = staff.profile_pic_url || '/Kingsway/images/default-avatar.png';
            const statusBadge = this.getStatusBadge(staff.status);
            const typeBadge = this.getTypeBadge(staff.staff_type);

            html += `
                <tr>
                    <td><img src="${photo}" class="rounded-circle" width="40" height="40" onerror="this.src='/Kingsway/images/default-avatar.png'"></td>
                    <td><strong>${staff.first_name || ''} ${staff.last_name || ''}</strong><br><small class="text-muted">${staff.email || ''}</small></td>
                    <td>${staff.staff_no || staff.employee_number || '-'}</td>
                    <td>${staff.department_name || '-'}</td>
                    <td>${typeBadge}</td>
                    <td>${statusBadge}</td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-info" onclick="staffManagementController.viewStaff(${staff.id})" title="View">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button class="btn btn-warning" onclick="staffManagementController.editStaff(${staff.id})" title="Edit" data-permission="staff_edit">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-danger" onclick="staffManagementController.deleteStaff(${staff.id})" title="Delete" data-permission="staff_delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });

        html += '</tbody></table></div>';
        container.innerHTML = html;
    },

    getStatusBadge: function(status) {
        const badges = {
            'active': '<span class="badge bg-success">Active</span>',
            'inactive': '<span class="badge bg-secondary">Inactive</span>',
            'on_leave': '<span class="badge bg-warning">On Leave</span>'
        };
        return badges[status] || '<span class="badge bg-secondary">Unknown</span>';
    },

    getTypeBadge: function(type) {
        const badges = {
            'teaching': '<span class="badge bg-primary">Teaching</span>',
            'non-teaching': '<span class="badge bg-info">Non-Teaching</span>',
            'admin': '<span class="badge bg-dark">Admin</span>'
        };
        return badges[type] || '<span class="badge bg-secondary">Unknown</span>';
    },

    showStaffModal: function(staff = null) {
        const form = document.getElementById('staffForm');
        form.reset();
        document.getElementById('staffId').value = staff?.id || '';
        document.getElementById('staffModalLabel').textContent = staff ? 'Edit Staff Member' : 'Add Staff Member';

        if (staff) {
            // Personal Information
            document.getElementById('staffFirstName').value = staff.first_name || '';
            document.getElementById('staffMiddleName').value = staff.middle_name || '';
            document.getElementById('staffLastName').value = staff.last_name || '';
            document.getElementById('staffGender').value = staff.gender || '';
            document.getElementById('staffDOB').value = staff.date_of_birth || '';
            document.getElementById('staffNationalId').value = staff.national_id || '';
            document.getElementById('staffMaritalStatus').value = staff.marital_status || '';
            
            // Employment Information
            document.getElementById('staffNumber').value = staff.staff_no || staff.employee_number || '';
            document.getElementById('staffType').value = staff.staff_type || '';
            document.getElementById('staffDepartment').value = staff.department_id || '';
            document.getElementById('staffRole').value = staff.role_id || '';
            document.getElementById('staffPosition').value = staff.position || '';
            document.getElementById('employmentDate').value = staff.employment_date || '';
            document.getElementById('staffContractType').value = staff.contract_type || '';
            document.getElementById('staffSupervisor').value = staff.supervisor_id || '';
            document.getElementById('staffTscNo').value = staff.tsc_no || '';
            document.getElementById('staffStatus').value = staff.status || 'active';
            document.getElementById('staffPassword').value = ''; // Never pre-fill password
            
            // Statutory Information
            document.getElementById('staffNssfNo').value = staff.nssf_no || '';
            document.getElementById('staffKraPin').value = staff.kra_pin || '';
            document.getElementById('staffNhifNo').value = staff.nhif_no || '';
            
            // Financial Information
            document.getElementById('staffBankAccount').value = staff.bank_account || '';
            document.getElementById('staffSalary').value = staff.salary || '';
            
            // Contact Information
            document.getElementById('staffEmail').value = staff.email || '';
            document.getElementById('staffPhone').value = staff.phone || '';
            document.getElementById('staffAddress').value = staff.address || 'N/A';
        }

        const modal = new bootstrap.Modal(document.getElementById('staffModal'));
        modal.show();
    },

    saveStaff: async function(event) {
        event.preventDefault();

        try {
            const staffId = document.getElementById('staffId').value;
            const profilePicFile = document.getElementById('staffProfilePic')?.files[0];

            // Build FormData for multipart upload
            const formData = new FormData();
            
            // Personal Information
            formData.append('first_name', document.getElementById('staffFirstName').value);
            formData.append('middle_name', document.getElementById('staffMiddleName').value || '');
            formData.append('last_name', document.getElementById('staffLastName').value);
            formData.append('gender', document.getElementById('staffGender').value);
            formData.append('date_of_birth', document.getElementById('staffDOB').value);
            formData.append('national_id', document.getElementById('staffNationalId').value || 'N/A');
            formData.append('marital_status', document.getElementById('staffMaritalStatus').value || 'Single');
            
            // Employment Information
            formData.append('staff_type', document.getElementById('staffType').value);
            formData.append('department_id', document.getElementById('staffDepartment').value);
            formData.append('role_id', document.getElementById('staffRole').value);
            formData.append('position', document.getElementById('staffPosition').value);
            formData.append('employment_date', document.getElementById('employmentDate').value);
            formData.append('contract_type', document.getElementById('staffContractType').value || '');
            formData.append('supervisor_id', document.getElementById('staffSupervisor').value || '');
            formData.append('tsc_no', document.getElementById('staffTscNo').value || 'N/A');
            formData.append('status', document.getElementById('staffStatus').value);
            
            // Password (only if provided)
            const password = document.getElementById('staffPassword').value;
            if (password) {
                formData.append('password', password);
            }
            
            // Statutory Information
            formData.append('nssf_no', document.getElementById('staffNssfNo').value);
            formData.append('kra_pin', document.getElementById('staffKraPin').value);
            formData.append('nhif_no', document.getElementById('staffNhifNo').value);
            
            // Financial Information
            formData.append('bank_account', document.getElementById('staffBankAccount').value);
            formData.append('salary', document.getElementById('staffSalary').value);
            
            // Contact Information
            formData.append('email', document.getElementById('staffEmail').value);
            formData.append('phone', document.getElementById('staffPhone').value);
            formData.append('address', document.getElementById('staffAddress').value || 'N/A');

            if (profilePicFile) {
                formData.append('profile_pic', profilePicFile);
            }

            let endpoint = '/staff/staff';
            let method = 'POST';

            if (staffId) {
                endpoint = `/staff/staff/${staffId}`;
                method = 'PUT';
                formData.append('id', staffId);
            }

            await window.API.apiCall(endpoint, method, formData, {}, { isFile: true });

            showNotification(staffId ? 'Staff updated successfully' : 'Staff created successfully', 'success');
            bootstrap.Modal.getInstance(document.getElementById('staffModal')).hide();
            await this.loadStaff();
            this.loadStatistics();
        } catch (error) {
            console.error('Error saving staff:', error);
            showNotification('Failed to save staff: ' + (error.message || 'Unknown error'), 'error');
        }
    },

    viewStaff: async function(staffId) {
        try {
            const staff = await window.API.apiCall(`/staff/staff/${staffId}`, 'GET');
            const photo = staff.profile_pic_url || '/Kingsway/images/default-avatar.png';

            const html = `
                <div class="row">
                    <div class="col-md-4 text-center">
                        <img src="${photo}" class="img-fluid rounded mb-3" style="max-width: 150px" onerror="this.src='/Kingsway/images/default-avatar.png'">
                        <h5>${staff.first_name || ''} ${staff.last_name || ''}</h5>
                        <p class="text-muted">${staff.staff_no || ''}</p>
                    </div>
                    <div class="col-md-8">
                        <h6>Personal Information</h6>
                        <p><strong>Email:</strong> ${staff.email || '-'}</p>
                        <p><strong>Phone:</strong> ${staff.phone || '-'}</p>
                        <p><strong>Gender:</strong> ${staff.gender || '-'}</p>
                        <p><strong>Date of Birth:</strong> ${staff.date_of_birth || '-'}</p>
                        <hr>
                        <h6>Employment Information</h6>
                        <p><strong>Department:</strong> ${staff.department_name || '-'}</p>
                        <p><strong>Type:</strong> ${staff.staff_type || '-'}</p>
                        <p><strong>Status:</strong> ${this.getStatusBadge(staff.status)}</p>
                        <p><strong>Employment Date:</strong> ${staff.employment_date || '-'}</p>
                    </div>
                </div>
            `;

            document.getElementById('viewStaffContent').innerHTML = html;
            new bootstrap.Modal(document.getElementById('viewStaffModal')).show();
        } catch (error) {
            console.error('Error loading staff:', error);
            showNotification('Failed to load staff details', 'error');
        }
    },

    editStaff: async function(staffId) {
        try {
            const staff = await window.API.apiCall(`/staff/staff/${staffId}`, 'GET');
            this.showStaffModal(staff);
        } catch (error) {
            console.error('Error loading staff for edit:', error);
            showNotification('Failed to load staff details', 'error');
        }
    },

    deleteStaff: async function(staffId) {
        if (!confirm('Are you sure you want to delete this staff member?')) return;

        try {
            await window.API.apiCall(`/staff/staff/${staffId}`, 'DELETE');
            showNotification('Staff deleted successfully', 'success');
            await this.loadStaff();
            this.loadStatistics();
        } catch (error) {
            console.error('Error deleting staff:', error);
            showNotification('Failed to delete staff', 'error');
        }
    },

    searchStaff: function(query) {
        const q = query.toLowerCase();
        this.filteredStaff = this.allStaff.filter(s =>
            (s.first_name || '').toLowerCase().includes(q) ||
            (s.last_name || '').toLowerCase().includes(q) ||
            (s.email || '').toLowerCase().includes(q) ||
            (s.staff_no || '').toLowerCase().includes(q)
        );
        this.renderStaffTables();
    },

    filterByDepartment: function(deptId) {
        this.currentFilters.department_id = deptId;
        this.applyFilters();
    },

    filterByType: function(type) {
        this.currentFilters.staff_type = type;
        this.applyFilters();
    },

    filterByStatus: function(status) {
        this.currentFilters.status = status;
        this.applyFilters();
    },

    filterByRole: function(roleId) {
        this.currentFilters.role_id = roleId;
        this.applyFilters();
    },

    applyFilters: function() {
        this.filteredStaff = this.allStaff.filter(s => {
            if (this.currentFilters.department_id && s.department_id != this.currentFilters.department_id) return false;
            if (this.currentFilters.staff_type && s.staff_type !== this.currentFilters.staff_type) return false;
            if (this.currentFilters.status && s.status !== this.currentFilters.status) return false;
            if (this.currentFilters.role_id && s.role_id != this.currentFilters.role_id) return false;
            return true;
        });
        this.renderStaffTables();
    },

    showBulkImportModal: function() {
        new bootstrap.Modal(document.getElementById('bulkImportModal')).show();
    },

    bulkImport: async function(event) {
        event.preventDefault();

        const fileInput = document.getElementById('bulkImportStaffFile');
        if (!fileInput.files[0]) {
            showNotification('Please select a file', 'warning');
            return;
        }

        try {
            const formData = new FormData();
            formData.append('file', fileInput.files[0]);

            await window.API.apiCall('/staff/bulk-import', 'POST', formData, {}, { isFile: true });
            showNotification('Staff imported successfully', 'success');
            bootstrap.Modal.getInstance(document.getElementById('bulkImportModal')).hide();
            await this.loadStaff();
            this.loadStatistics();
        } catch (error) {
            console.error('Error importing staff:', error);
            showNotification('Failed to import staff: ' + (error.message || 'Unknown error'), 'error');
        }
    },

    exportStaff: async function() {
        try {
            const response = await window.API.apiCall('/staff/export', 'GET', null, {}, { isDownload: true, filename: 'staff_export.xlsx' });
            showNotification('Staff exported successfully', 'success');
        } catch (error) {
            console.error('Error exporting staff:', error);
            showNotification('Failed to export staff', 'error');
        }
    }
};

document.addEventListener('DOMContentLoaded', async () => {
    if (!AuthContext.isAuthenticated()) {
        window.location.href = '/Kingsway/index.php';
        return;
    }

    initializeStaffTable();
    initializeStaffModals();
    loadStaffStatistics();
    loadStaffFilterOptions();
    attachStaffEventListeners();
});

function initializeStaffTable() {
    staffTable = new DataTable('staffTable', {
        apiEndpoint: '/staff/index',
        pageSize: 10,
        columns: [
            { field: 'id', label: '#' },
            { field: 'first_name', label: 'First Name', sortable: true },
            { field: 'last_name', label: 'Last Name', sortable: true },
            { field: 'email', label: 'Email', sortable: true },
            { field: 'phone', label: 'Phone' },
            { 
                field: 'staff_type', 
                label: 'Type', 
                type: 'badge',
                badgeMap: {
                    'teaching': 'primary',
                    'non_teaching': 'secondary',
                    'admin': 'danger'
                }
            },
            { 
                field: 'status', 
                label: 'Status', 
                type: 'badge',
                badgeMap: {
                    'active': 'success',
                    'inactive': 'secondary',
                    'on_leave': 'warning'
                }
            }
        ],
        searchFields: ['first_name', 'last_name', 'email', 'phone', 'employee_number'],
        rowActions: [
            { id: 'view', label: 'View', icon: 'bi-eye', variant: 'info', permission: 'staff_view' },
            { id: 'edit', label: 'Edit', icon: 'bi-pencil', variant: 'warning', permission: 'staff_edit' },
            { id: 'assign', label: 'Assign', icon: 'bi-link', variant: 'primary', permission: 'staff_assign' },
            { id: 'delete', label: 'Delete', icon: 'bi-trash', variant: 'danger', permission: 'staff_delete' }
        ],
        onRowAction: handleStaffRowAction
    });
}

function initializeStaffModals() {
    staffModal = new ModalForm('staffModal', {
        title: 'Staff Information',
        apiEndpoint: '/staff',
        submitButtonLabel: 'Save Staff',
        size: 'lg'
    });
}

async function handleStaffRowAction(actionId, rowIds, row) {
    if (actionId === 'view') {
        await viewStaffDetails(rowIds[0]);
    } else if (actionId === 'edit') {
        staffModal.open(row, `Edit: ${row.first_name} ${row.last_name}`);
    } else if (actionId === 'assign') {
        // Show assignment dialog
        showStaffAssignmentDialog(row);
    } else if (actionId === 'delete') {
        await deleteStaff(rowIds[0]);
    }
}

async function viewStaffDetails(staffId) {
    try {
        const staff = await window.API.apiCall(`/staff/${staffId}`, 'GET');
        const html = `
            <div class="row">
                <div class="col-md-6">
                    <h6>Personal Information</h6>
                    <p><strong>Name:</strong> ${staff.first_name} ${staff.last_name}</p>
                    <p><strong>Email:</strong> ${staff.email || '-'}</p>
                    <p><strong>Phone:</strong> ${staff.phone || '-'}</p>
                    <p><strong>Type:</strong> ${ActionButtons.createStatusBadge(staff.staff_type)}</p>
                </div>
                <div class="col-md-6">
                    <h6>Employment Information</h6>
                    <p><strong>Status:</strong> ${ActionButtons.createStatusBadge(staff.status)}</p>
                    <p><strong>Department:</strong> ${staff.department_name || '-'}</p>
                    <p><strong>Hire Date:</strong> ${staff.hire_date ? new Date(staff.hire_date).toLocaleDateString() : '-'}</p>
                </div>
            </div>
        `;
        document.getElementById('viewStaffContent').innerHTML = html;
        new bootstrap.Modal(document.getElementById('viewStaffModal')).show();
    } catch (error) {
        window.API.showNotification('Failed to load staff details', NOTIFICATION_TYPES.ERROR);
    }
}

async function deleteStaff(staffId) {
    const confirmed = await ActionButtons.confirm('Delete this staff member?', 'Delete', true);
    if (!confirmed) return;

    try {
        await window.API.apiCall(`/staff/${staffId}`, 'DELETE');
        window.API.showNotification('Staff deleted successfully', NOTIFICATION_TYPES.SUCCESS);
        await staffTable.refresh();
    } catch (error) {
        window.API.showNotification(error.message, NOTIFICATION_TYPES.ERROR);
    }
}

async function loadStaffStatistics() {
    try {
        const stats = await window.API.apiCall('/reports/total-staff', 'GET');
        if (stats) {
            document.getElementById('totalStaff').textContent = stats.total || 0;
            document.getElementById('teachingStaff').textContent = stats.teaching || 0;
            document.getElementById('nonTeachingStaff').textContent = stats.non_teaching || 0;
            document.getElementById('presentStaff').textContent = stats.present || 0;
        }
    } catch (error) {
        console.error('Failed to load statistics:', error);
    }
}

async function loadStaffFilterOptions() {
    try {
        // Load departments
        const departments = await window.API.apiCall('/staff/departments-get', 'GET');
        if (Array.isArray(departments)) {
            const select = document.getElementById('departmentFilter');
            departments.forEach(dept => {
                const option = new Option(dept.name, dept.id);
                select.add(option);
            });
        }
    } catch (error) {
        console.error('Failed to load filter options:', error);
    }
}

function attachStaffEventListeners() {
    document.getElementById('createStaffBtn')?.addEventListener('click', () => {
        staffModal.reset();
        staffModal.open(null, 'Add New Staff Member');
    });

    document.getElementById('staffSearchInput')?.addEventListener('keyup', (e) => {
        staffTable.search(e.target.value);
    });

    document.getElementById('staffResetFilters')?.addEventListener('click', () => {
        document.getElementById('staffSearchInput').value = '';
        document.getElementById('departmentFilter').value = '';
        staffTable.search('');
        staffTable.applyFilters({});
    });
}

function showStaffAssignmentDialog(staff) {
    // Show modal for assigning classes/subjects
    const modalId = 'staffAssignmentModal';
    const modal = new bootstrap.Modal(document.getElementById(modalId));
    modal.show();
}
