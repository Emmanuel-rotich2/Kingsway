/**
 * Attendance Page Controller
 * Manages student and staff attendance marking and reports
 */

let attendanceTable = null;

document.addEventListener('DOMContentLoaded', async () => {
    if (!AuthContext.isAuthenticated()) {
        window.location.href = '/Kingsway/index.php';
        return;
    }

    initializeAttendanceTables();
    loadAttendanceStatistics();
    attachAttendanceEventListeners();
});

function initializeAttendanceTables() {
    attendanceTable = new DataTable('attendanceTable', {
        apiEndpoint: '/attendance/index',
        pageSize: 10,
        columns: [
            { field: 'id', label: '#' },
            { field: 'student_name', label: 'Student', sortable: true },
            { field: 'class_name', label: 'Class' },
            { field: 'date', label: 'Date', type: 'date', sortable: true },
            { 
                field: 'status', 
                label: 'Status', 
                type: 'badge',
                badgeMap: { 
                    'present': 'success', 
                    'absent': 'danger', 
                    'late': 'warning' 
                }
            },
            { field: 'remarks', label: 'Remarks' }
        ],
        searchFields: ['student_name', 'class_name'],
        rowActions: [
            { id: 'edit', label: 'Edit', icon: 'bi-pencil', variant: 'warning', permission: 'attendance_edit' }
        ]
    });
}

async function loadAttendanceStatistics() {
    try {
        const stats = await window.API.apiCall('/reports/attendance-rates', 'GET');
        if (stats) {
            document.getElementById('todayPresent').textContent = stats.present_today || 0;
            document.getElementById('todayAbsent').textContent = stats.absent_today || 0;
            document.getElementById('averageRate').textContent = (stats.average_rate || 0).toFixed(1) + '%';
            document.getElementById('chronicAbsentees').textContent = stats.chronic_absentees || 0;
        }
    } catch (error) {
        console.error('Failed to load statistics:', error);
    }
}

function attachAttendanceEventListeners() {
    document.getElementById('markAttendanceBtn')?.addEventListener('click', () => {
        const modal = new bootstrap.Modal(document.getElementById('markAttendanceModal'));
        modal.show();
    });

    document.getElementById('attendanceSearchInput')?.addEventListener('keyup', (e) => {
        attendanceTable.search(e.target.value);
    });

    document.getElementById('attendanceDateFilter')?.addEventListener('change', (e) => {
        attendanceTable.applyFilters({ date: e.target.value });
    });
}
