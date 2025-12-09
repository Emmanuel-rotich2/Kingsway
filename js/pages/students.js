/**
 * Students Page Controller
 * Initializes DataTable, Modal forms, and handles all student management interactions
 */

let studentTable = null;
let studentModal = null;
let viewStudentModal = null;

// Initialize page on load
document.addEventListener('DOMContentLoaded', async () => {
    console.log('Initializing Students page...');

    // Check authentication
    if (!AuthContext.isAuthenticated()) {
        window.location.href = '/Kingsway/index.php';
        return;
    }

    // Initialize components
    initializeDataTable();
    initializeModals();
    loadStatistics();
    loadFilterOptions();
    attachEventListeners();

    console.log('Students page initialized');
});

/**
 * Initialize the students data table
 */
function initializeDataTable() {
    studentTable = new DataTable('studentTable', {
        apiEndpoint: '/students/index',
        pageSize: 10,
        columns: [
            { field: 'id', label: '#', sortable: true },
            { field: 'first_name', label: 'First Name', sortable: true },
            { field: 'last_name', label: 'Last Name', sortable: true },
            { field: 'admission_number', label: 'Admission Number', sortable: true },
            { field: 'class_name', label: 'Class', sortable: true },
            { 
                field: 'status', 
                label: 'Status', 
                type: 'badge',
                badgeMap: {
                    'active': 'success',
                    'inactive': 'secondary',
                    'suspended': 'danger'
                }
            },
            { 
                field: 'created_at', 
                label: 'Created Date', 
                type: 'date',
                sortable: true
            }
        ],
        searchFields: ['first_name', 'last_name', 'admission_number', 'email', 'phone'],
        rowActions: [
            {
                id: 'view',
                label: 'View',
                icon: 'bi-eye',
                variant: 'info',
                permission: 'students_view'
            },
            {
                id: 'edit',
                label: 'Edit',
                icon: 'bi-pencil',
                variant: 'warning',
                permission: 'students_edit'
            },
            {
                id: 'delete',
                label: 'Delete',
                icon: 'bi-trash',
                variant: 'danger',
                permission: 'students_delete'
            }
        ],
        bulkActions: [
            {
                id: 'bulk-approve',
                label: 'Approve',
                variant: 'success',
                permission: 'students_approve'
            },
            {
                id: 'bulk-delete',
                label: 'Delete',
                variant: 'danger',
                permission: 'students_delete'
            }
        ],
        onRowAction: handleRowAction
    });
}

/**
 * Initialize modal forms
 */
function initializeModals() {
    // Create/Edit Modal
    studentModal = new ModalForm('studentModal', {
        title: 'Student Information',
        apiEndpoint: '/students/student',
        submitButtonLabel: 'Save Student',
        size: 'lg',
        onSubmit: handleStudentFormSubmit
    });

    // View Modal (read-only)
    const viewModalEl = document.getElementById('viewStudentModal');
    viewStudentModal = new bootstrap.Modal(viewModalEl);
}

/**
 * Handle custom form submission for student modal
 */
async function handleStudentFormSubmit(formData, isEditing) {
    try {
        let endpoint = '/students/student';
        let method = isEditing ? 'PUT' : 'POST';

        if (isEditing) {
            endpoint = `/students/student/${formData.id}`;
        }

        const response = await window.API.apiCall(endpoint, method, formData);

        window.API.showNotification(
            isEditing ? 'Student updated successfully' : 'Student created successfully',
            NOTIFICATION_TYPES.SUCCESS
        );

        // Close modal and refresh table
        studentModal.close();
        await studentTable.refresh();
        loadStatistics();

        return true;
    } catch (error) {
        console.error('Form submission error:', error);
        window.API.showNotification(error.message, NOTIFICATION_TYPES.ERROR);
        return false;
    }
}

/**
 * Handle row action clicks (view, edit, delete)
 */
async function handleRowAction(actionId, rowIds, row) {
    console.log('Row action:', actionId, rowIds, row);

    if (actionId === 'view') {
        await viewStudentDetails(rowIds[0]);
    } else if (actionId === 'edit') {
        editStudent(row);
    } else if (actionId === 'delete') {
        await deleteStudent(rowIds[0]);
    }
}

/**
 * View student details in modal
 */
async function viewStudentDetails(studentId) {
    try {
        const student = await window.API.apiCall(`/students/student/${studentId}`, 'GET');
        
        let html = `
            <div class="row">
                <div class="col-md-6">
                    <h6>Personal Information</h6>
                    <p><strong>Name:</strong> ${student.first_name} ${student.last_name}</p>
                    <p><strong>Email:</strong> ${student.email || '-'}</p>
                    <p><strong>Phone:</strong> ${student.phone || '-'}</p>
                    <p><strong>Date of Birth:</strong> ${student.date_of_birth ? new Date(student.date_of_birth).toLocaleDateString() : '-'}</p>
                    <p><strong>Gender:</strong> ${student.gender || '-'}</p>
                </div>
                <div class="col-md-6">
                    <h6>Academic Information</h6>
                    <p><strong>Admission Number:</strong> ${student.admission_number}</p>
                    <p><strong>Class:</strong> ${student.class_name || '-'}</p>
                    <p><strong>Stream:</strong> ${student.stream_name || '-'}</p>
                    <p><strong>Status:</strong> ${ActionButtons.createStatusBadge(student.status, {
                        'active': 'success',
                        'inactive': 'secondary',
                        'suspended': 'danger'
                    })}</p>
                    <p><strong>Enrollment Date:</strong> ${student.created_at ? new Date(student.created_at).toLocaleDateString() : '-'}</p>
                </div>
            </div>
            
            <hr>
            
            <div class="row mt-4">
                <div class="col-md-6">
                    <h6>Attendance</h6>
                    ${student.attendance ? UIComponents.createAttendanceSummary(student.attendance) : '<p class="text-muted">No attendance data</p>'}
                </div>
                <div class="col-md-6">
                    <h6>Academic Performance</h6>
                    ${student.performance ? `
                        <p><strong>Average Grade:</strong> ${ActionButtons.createStatusBadge(student.performance.grade, {
                            'A': 'success', 'B': 'info', 'C': 'warning', 'D': 'danger', 'E': 'danger'
                        })}</p>
                        <p><strong>Overall Mark:</strong> ${student.performance.marks || '-'}%</p>
                    ` : '<p class="text-muted">No performance data</p>'}
                </div>
            </div>
        `;

        document.getElementById('viewStudentContent').innerHTML = html;
        viewStudentModal.show();
    } catch (error) {
        console.error('Failed to load student details:', error);
        window.API.showNotification('Failed to load student details', NOTIFICATION_TYPES.ERROR);
    }
}

/**
 * Open edit modal with student data
 */
function editStudent(student) {
    studentModal.open(student, `Edit Student: ${student.first_name} ${student.last_name}`);
}

/**
 * Delete a student with confirmation
 */
async function deleteStudent(studentId) {
    const confirmed = await ActionButtons.confirm(
        'Are you sure you want to delete this student? This action cannot be undone.',
        'Delete',
        true
    );

    if (!confirmed) return;

    try {
        await window.API.apiCall(`/students/student/${studentId}`, 'DELETE');
        
        window.API.showNotification('Student deleted successfully', NOTIFICATION_TYPES.SUCCESS);
        await studentTable.refresh();
        loadStatistics();
    } catch (error) {
        console.error('Delete error:', error);
        window.API.showNotification(error.message, NOTIFICATION_TYPES.ERROR);
    }
}

/**
 * Load filter options (classes, streams, etc.)
 */
async function loadFilterOptions() {
    try {
        // Load classes
        const classes = await window.API.apiCall('/academic/classes-list', 'GET');
        const classSelect = document.getElementById('classFilter');
        const classSelectModal = document.getElementById('classSelect');

        if (Array.isArray(classes) && classes.length > 0) {
            classes.forEach(cls => {
                const option = new Option(cls.name || cls.class_name, cls.id);
                classSelect.add(option);
                classSelectModal.add(option.cloneNode(true));
            });
        }
    } catch (error) {
        console.error('Failed to load filter options:', error);
    }
}

/**
 * Load and display statistics
 */
async function loadStatistics() {
    try {
        const stats = await window.API.apiCall('/reports/total-students', 'GET');

        if (stats) {
            document.getElementById('totalStudents').textContent = stats.total || 0;
            document.getElementById('activeStudents').textContent = stats.active || 0;

            if (stats.by_gender) {
                const male = stats.by_gender.male || 0;
                const female = stats.by_gender.female || 0;
                document.getElementById('genderBreakdown').textContent = `${male}/${female}`;
            }

            if (stats.attendance_rate) {
                document.getElementById('avgAttendance').textContent = `${(stats.attendance_rate * 100).toFixed(1)}%`;
            }
        }
    } catch (error) {
        console.error('Failed to load statistics:', error);
    }
}

/**
 * Attach event listeners
 */
function attachEventListeners() {
    // Create button
    document.getElementById('createStudentBtn').addEventListener('click', () => {
        studentModal.reset();
        studentModal.open(null, 'Add New Student');
    });

    // Search
    document.getElementById('searchInput').addEventListener('keyup', (e) => {
        studentTable.search(e.target.value);
    });

    // Filter by class
    document.getElementById('classFilter').addEventListener('change', (e) => {
        const filters = {};
        if (e.target.value) filters.class_id = e.target.value;
        if (document.getElementById('statusFilter').value) {
            filters.status = document.getElementById('statusFilter').value;
        }
        studentTable.applyFilters(filters);
    });

    // Filter by status
    document.getElementById('statusFilter').addEventListener('change', (e) => {
        const filters = {};
        if (document.getElementById('classFilter').value) {
            filters.class_id = document.getElementById('classFilter').value;
        }
        if (e.target.value) filters.status = e.target.value;
        studentTable.applyFilters(filters);
    });

    // Reset filters
    document.getElementById('resetFilters').addEventListener('click', () => {
        document.getElementById('searchInput').value = '';
        document.getElementById('classFilter').value = '';
        document.getElementById('statusFilter').value = '';
        studentTable.search('');
        studentTable.applyFilters({});
    });
}

// Callback for when modal closes successfully
window.onModalSuccess = async () => {
    await studentTable.refresh();
    loadStatistics();
};
