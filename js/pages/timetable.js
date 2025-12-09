/**
 * Timetable Page Controller
 * Manages class timetables and schedules
 */

let timetableTable = null;
let roomScheduleTable = null;

document.addEventListener('DOMContentLoaded', async () => {
    if (!AuthContext.isAuthenticated()) {
        window.location.href = '/Kingsway/index.php';
        return;
    }

    initializeTimetableTables();
    loadTimetableStatistics();
    attachTimetableEventListeners();
});

function initializeTimetableTables() {
    // Class Timetable
    timetableTable = new DataTable('timetableTable', {
        apiEndpoint: '/academic/timetables',
        pageSize: 10,
        columns: [
            { field: 'id', label: '#' },
            { field: 'class_name', label: 'Class', sortable: true },
            { field: 'day_of_week', label: 'Day' },
            { field: 'start_time', label: 'Start Time', type: 'time' },
            { field: 'end_time', label: 'End Time', type: 'time' },
            { field: 'subject_name', label: 'Subject' },
            { field: 'teacher_name', label: 'Teacher' },
            { field: 'room_number', label: 'Room' }
        ],
        searchFields: ['class_name', 'subject_name', 'teacher_name'],
        rowActions: [
            { id: 'edit', label: 'Edit', icon: 'bi-pencil', variant: 'warning', permission: 'timetable_edit' },
            { id: 'delete', label: 'Delete', icon: 'bi-trash', variant: 'danger', permission: 'timetable_delete' }
        ]
    });

    // Room Schedule
    roomScheduleTable = new DataTable('roomScheduleTable', {
        apiEndpoint: '/academic/room-schedules',
        pageSize: 10,
        columns: [
            { field: 'id', label: '#' },
            { field: 'room_number', label: 'Room', sortable: true },
            { field: 'day_of_week', label: 'Day' },
            { field: 'start_time', label: 'Start Time', type: 'time' },
            { field: 'end_time', label: 'End Time', type: 'time' },
            { field: 'class_name', label: 'Class' },
            { field: 'activity_type', label: 'Activity' }
        ],
        searchFields: ['room_number', 'class_name'],
        rowActions: [
            { id: 'edit', label: 'Edit', icon: 'bi-pencil', variant: 'warning', permission: 'timetable_edit' }
        ]
    });
}

async function loadTimetableStatistics() {
    try {
        const stats = await window.API.apiCall('/reports/timetable-stats', 'GET');
        if (stats) {
            document.getElementById('totalClasses').textContent = stats.total_classes || 0;
            document.getElementById('totalRooms').textContent = stats.total_rooms || 0;
            document.getElementById('conflictsCount').textContent = stats.conflicts || 0;
            document.getElementById('availableSlots').textContent = stats.available_slots || 0;
        }
    } catch (error) {
        console.error('Failed to load statistics:', error);
    }
}

function attachTimetableEventListeners() {
    document.getElementById('addTimetableBtn')?.addEventListener('click', () => {
        const modal = new bootstrap.Modal(document.getElementById('addTimetableModal'));
        modal.show();
    });

    document.getElementById('generateTimetableBtn')?.addEventListener('click', () => {
        if (confirm('Auto-generate timetable? This will override existing entries.')) {
            generateTimetable();
        }
    });

    document.getElementById('classFilter')?.addEventListener('change', (e) => {
        timetableTable.applyFilters({ class_name: e.target.value });
    });

    document.getElementById('roomFilter')?.addEventListener('change', (e) => {
        roomScheduleTable.applyFilters({ room_number: e.target.value });
    });
}

async function generateTimetable() {
    try {
        const result = await window.API.apiCall('/academic/generate-timetable', 'POST', {});
        if (result.success) {
            alert('Timetable generated successfully!');
            timetableTable.refresh();
        }
    } catch (error) {
        console.error('Failed to generate timetable:', error);
    }
}
