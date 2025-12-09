/**
 * Lesson Plans Page Controller
 * Manages lesson plan creation, submission, and approval
 */

let lessonPlansTable = null;

document.addEventListener('DOMContentLoaded', async () => {
    if (!AuthContext.isAuthenticated()) {
        window.location.href = '/Kingsway/index.php';
        return;
    }

    initializeLessonPlansTables();
    loadLessonPlansStatistics();
    attachLessonPlansEventListeners();
});

function initializeLessonPlansTables() {
    // Lesson Plans Table
    lessonPlansTable = new DataTable('lessonPlansTable', {
        apiEndpoint: '/academic/lesson-plans',
        pageSize: 10,
        columns: [
            { field: 'id', label: '#' },
            { field: 'topic', label: 'Topic', sortable: true },
            { field: 'class_name', label: 'Class' },
            { field: 'teacher_name', label: 'Teacher' },
            { field: 'subject_name', label: 'Subject' },
            { field: 'planned_date', label: 'Planned Date', type: 'date', sortable: true },
            { 
                field: 'status', 
                label: 'Status', 
                type: 'badge',
                badgeMap: { 
                    'draft': 'secondary',
                    'submitted': 'warning',
                    'approved': 'success',
                    'taught': 'info',
                    'rejected': 'danger'
                }
            }
        ],
        searchFields: ['topic', 'teacher_name', 'class_name'],
        rowActions: [
            { id: 'view', label: 'View', icon: 'bi-eye', permission: 'lesson_plans_view' },
            { id: 'edit', label: 'Edit', icon: 'bi-pencil', variant: 'warning', permission: 'lesson_plans_edit' },
            { id: 'approve', label: 'Approve', icon: 'bi-check-circle', variant: 'success', permission: 'lesson_plans_approve' },
            { id: 'reject', label: 'Reject', icon: 'bi-x-circle', variant: 'danger', permission: 'lesson_plans_approve' },
            { id: 'delete', label: 'Delete', icon: 'bi-trash', variant: 'danger', permission: 'lesson_plans_delete' }
        ]
    });
}

async function loadLessonPlansStatistics() {
    try {
        const stats = await window.API.apiCall('/reports/lesson-plans-stats', 'GET');
        if (stats) {
            document.getElementById('totalLessonPlans').textContent = stats.total_plans || 0;
            document.getElementById('draftPlans').textContent = stats.draft_plans || 0;
            document.getElementById('pendingApproval').textContent = stats.pending_approval || 0;
            document.getElementById('completedLesson').textContent = stats.completed_lessons || 0;
        }
    } catch (error) {
        console.error('Failed to load statistics:', error);
    }
}

function attachLessonPlansEventListeners() {
    document.getElementById('createLessonPlanBtn')?.addEventListener('click', () => {
        const modal = new bootstrap.Modal(document.getElementById('createLessonPlanModal'));
        modal.show();
    });

    document.getElementById('lessonPlansSearchInput')?.addEventListener('keyup', (e) => {
        lessonPlansTable.search(e.target.value);
    });

    document.getElementById('statusFilter')?.addEventListener('change', (e) => {
        lessonPlansTable.applyFilters({ status: e.target.value });
    });

    document.getElementById('classFilter')?.addEventListener('change', (e) => {
        lessonPlansTable.applyFilters({ class_name: e.target.value });
    });
}
