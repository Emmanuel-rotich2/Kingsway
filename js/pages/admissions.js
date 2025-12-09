/**
 * Admissions Page Controller
 * Manages student admissions workflow
 */

let applicationsTable = null;
let admittedStudentsTable = null;

document.addEventListener('DOMContentLoaded', async () => {
    if (!AuthContext.isAuthenticated()) {
        window.location.href = '/Kingsway/index.php';
        return;
    }

    initializeAdmissionsTables();
    loadAdmissionsStatistics();
    attachAdmissionsEventListeners();
});

function initializeAdmissionsTables() {
    // Applications Table
    applicationsTable = new DataTable('applicationsTable', {
        apiEndpoint: '/admissions/applications',
        pageSize: 10,
        columns: [
            { field: 'id', label: '#' },
            { field: 'reference_number', label: 'Reference', sortable: true },
            { field: 'applicant_name', label: 'Applicant Name' },
            { field: 'application_date', label: 'Applied', type: 'date', sortable: true },
            { field: 'class_applied', label: 'Class Applied' },
            { 
                field: 'status', 
                label: 'Status', 
                type: 'badge',
                badgeMap: {
                    'submitted': 'secondary',
                    'under-review': 'warning',
                    'interview-scheduled': 'info',
                    'interviewed': 'primary',
                    'admitted': 'success',
                    'rejected': 'danger'
                }
            }
        ],
        searchFields: ['reference_number', 'applicant_name'],
        rowActions: [
            { id: 'view', label: 'View', icon: 'bi-eye', permission: 'admissions_view' },
            { id: 'review', label: 'Review', icon: 'bi-clipboard-check', variant: 'primary', permission: 'admissions_review' },
            { id: 'interview', label: 'Schedule Interview', icon: 'bi-calendar-event', variant: 'info', permission: 'admissions_interview' },
            { id: 'admit', label: 'Admit', icon: 'bi-check-circle', variant: 'success', permission: 'admissions_approve' },
            { id: 'reject', label: 'Reject', icon: 'bi-x-circle', variant: 'danger', permission: 'admissions_reject' }
        ]
    });

    // Admitted Students Table
    admittedStudentsTable = new DataTable('admittedStudentsTable', {
        apiEndpoint: '/admissions/admitted-students',
        pageSize: 10,
        columns: [
            { field: 'id', label: '#' },
            { field: 'admission_number', label: 'Admission #', sortable: true },
            { field: 'student_name', label: 'Name' },
            { field: 'class_assigned', label: 'Class Assigned' },
            { field: 'admission_date', label: 'Admitted', type: 'date', sortable: true },
            { 
                field: 'registration_status', 
                label: 'Registration', 
                type: 'badge',
                badgeMap: { 'pending': 'warning', 'registered': 'success' }
            }
        ],
        searchFields: ['admission_number', 'student_name'],
        rowActions: [
            { id: 'view', label: 'View', icon: 'bi-eye', permission: 'admissions_view' },
            { id: 'register', label: 'Register', icon: 'bi-check-lg', variant: 'success', permission: 'admissions_register' }
        ]
    });
}

async function loadAdmissionsStatistics() {
    try {
        const stats = await window.API.apiCall('/reports/admissions-stats', 'GET');
        if (stats) {
            document.getElementById('pendingApplications').textContent = stats.pending_applications || 0;
            document.getElementById('totalAdmitted').textContent = stats.total_admitted || 0;
            document.getElementById('pendingRegistration').textContent = stats.pending_registration || 0;
            document.getElementById('acceptanceRate').textContent = (stats.acceptance_rate || 0).toFixed(1) + '%';
        }
    } catch (error) {
        console.error('Failed to load statistics:', error);
    }
}

function attachAdmissionsEventListeners() {
    document.getElementById('newApplicationBtn')?.addEventListener('click', () => {
        const modal = new bootstrap.Modal(document.getElementById('newApplicationModal'));
        modal.show();
    });

    document.getElementById('applicationsSearchInput')?.addEventListener('keyup', (e) => {
        applicationsTable.search(e.target.value);
    });

    document.getElementById('statusFilter')?.addEventListener('change', (e) => {
        applicationsTable.applyFilters({ status: e.target.value });
    });

    document.getElementById('admittedSearchInput')?.addEventListener('keyup', (e) => {
        admittedStudentsTable.search(e.target.value);
    });
}
