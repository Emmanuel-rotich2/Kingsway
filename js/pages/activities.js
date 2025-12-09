/**
 * Activities Page Controller
 * Manages co-curricular and extra-curricular activities
 */

let activitiesTable = null;
let participationTable = null;

document.addEventListener('DOMContentLoaded', async () => {
    if (!AuthContext.isAuthenticated()) {
        window.location.href = '/Kingsway/index.php';
        return;
    }

    initializeActivitiesTables();
    loadActivitiesStatistics();
    attachActivitiesEventListeners();
});

function initializeActivitiesTables() {
    // Activities Table
    activitiesTable = new DataTable('activitiesTable', {
        apiEndpoint: '/activities/index',
        pageSize: 10,
        columns: [
            { field: 'id', label: '#' },
            { field: 'activity_name', label: 'Activity', sortable: true },
            { field: 'activity_type', label: 'Type' },
            { field: 'incharge_name', label: 'In-Charge' },
            { field: 'meeting_day', label: 'Meeting Day' },
            { field: 'start_time', label: 'Start Time', type: 'time' },
            { 
                field: 'status', 
                label: 'Status', 
                type: 'badge',
                badgeMap: { 'active': 'success', 'inactive': 'secondary', 'suspended': 'warning' }
            }
        ],
        searchFields: ['activity_name', 'incharge_name'],
        rowActions: [
            { id: 'view', label: 'View', icon: 'bi-eye', permission: 'activities_view' },
            { id: 'edit', label: 'Edit', icon: 'bi-pencil', variant: 'warning', permission: 'activities_edit' },
            { id: 'members', label: 'Members', icon: 'bi-people', variant: 'info', permission: 'activities_view' },
            { id: 'delete', label: 'Delete', icon: 'bi-trash', variant: 'danger', permission: 'activities_delete' }
        ]
    });

    // Participation Table
    participationTable = new DataTable('participationTable', {
        apiEndpoint: '/activities/participation',
        pageSize: 10,
        columns: [
            { field: 'id', label: '#' },
            { field: 'student_name', label: 'Student', sortable: true },
            { field: 'activity_name', label: 'Activity' },
            { field: 'join_date', label: 'Joined', type: 'date', sortable: true },
            { field: 'role_held', label: 'Role' },
            { 
                field: 'participation_status', 
                label: 'Status', 
                type: 'badge',
                badgeMap: { 'active': 'success', 'inactive': 'secondary', 'graduated': 'info' }
            }
        ],
        searchFields: ['student_name', 'activity_name'],
        rowActions: [
            { id: 'view', label: 'View', icon: 'bi-eye', permission: 'activities_view' },
            { id: 'edit', label: 'Edit', icon: 'bi-pencil', variant: 'warning', permission: 'activities_edit' },
            { id: 'remove', label: 'Remove', icon: 'bi-x-circle', variant: 'danger', permission: 'activities_edit' }
        ]
    });
}

async function loadActivitiesStatistics() {
    try {
        const stats = await window.API.apiCall('/reports/activities-stats', 'GET');
        if (stats) {
            document.getElementById('totalActivities').textContent = stats.total_activities || 0;
            document.getElementById('totalParticipants').textContent = stats.total_participants || 0;
            document.getElementById('activeActivities').textContent = stats.active_activities || 0;
            document.getElementById('withoutActivity').textContent = stats.without_activity || 0;
        }
    } catch (error) {
        console.error('Failed to load statistics:', error);
    }
}

function attachActivitiesEventListeners() {
    document.getElementById('createActivityBtn')?.addEventListener('click', () => {
        const modal = new bootstrap.Modal(document.getElementById('createActivityModal'));
        modal.show();
    });

    document.getElementById('enrollStudentBtn')?.addEventListener('click', () => {
        const modal = new bootstrap.Modal(document.getElementById('enrollModal'));
        modal.show();
    });

    document.getElementById('activitiesSearchInput')?.addEventListener('keyup', (e) => {
        activitiesTable.search(e.target.value);
    });

    document.getElementById('typeFilter')?.addEventListener('change', (e) => {
        activitiesTable.applyFilters({ activity_type: e.target.value });
    });

    document.getElementById('participationSearchInput')?.addEventListener('keyup', (e) => {
        participationTable.search(e.target.value);
    });
}
