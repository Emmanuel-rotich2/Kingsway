/**
 * Staff Page Controller
 * Initializes DataTable for staff management with create/edit/delete/assignment operations
 */

let staffTable = null;
let staffModal = null;

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
