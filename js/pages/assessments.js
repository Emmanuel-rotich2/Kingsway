/**
 * Assessment Page Controller
 * Manages assessments, tests, exams, and grading
 */

let assessmentsTable = null;
let resultMarksTable = null;

document.addEventListener('DOMContentLoaded', async () => {
    if (!AuthContext.isAuthenticated()) {
        window.location.href = '/Kingsway/index.php';
        return;
    }

    initializeAssessmentTables();
    loadAssessmentStatistics();
    attachAssessmentEventListeners();
});

function initializeAssessmentTables() {
    // Assessments Table
    assessmentsTable = new DataTable('assessmentsTable', {
        apiEndpoint: '/academic/assessments',
        pageSize: 10,
        columns: [
            { field: 'id', label: '#' },
            { field: 'title', label: 'Assessment', sortable: true },
            { field: 'class_name', label: 'Class' },
            { field: 'assessment_type', label: 'Type' },
            { field: 'total_marks', label: 'Total Marks', type: 'number' },
            { field: 'scheduled_date', label: 'Scheduled', type: 'date', sortable: true },
            { 
                field: 'status', 
                label: 'Status', 
                type: 'badge',
                badgeMap: { 
                    'planned': 'secondary', 
                    'in-progress': 'warning', 
                    'completed': 'success',
                    'marked': 'info'
                }
            }
        ],
        searchFields: ['title', 'class_name'],
        rowActions: [
            { id: 'view', label: 'View', icon: 'bi-eye', permission: 'assessments_view' },
            { id: 'edit', label: 'Edit', icon: 'bi-pencil', variant: 'warning', permission: 'assessments_edit' },
            { id: 'conduct', label: 'Conduct', icon: 'bi-play', variant: 'primary', permission: 'assessments_conduct' },
            { id: 'mark', label: 'Mark', icon: 'bi-pencil-square', variant: 'info', permission: 'assessments_mark' }
        ]
    });

    // Result Marks Table
    resultMarksTable = new DataTable('resultMarksTable', {
        apiEndpoint: '/academic/result-marks',
        pageSize: 10,
        columns: [
            { field: 'id', label: '#' },
            { field: 'assessment_title', label: 'Assessment' },
            { field: 'student_name', label: 'Student' },
            { field: 'marks_obtained', label: 'Marks', type: 'number' },
            { field: 'total_marks', label: 'Total', type: 'number' },
            { 
                field: 'percentage', 
                label: 'Percentage', 
                type: 'custom',
                formatter: (value) => `${value?.toFixed(1) || 0}%`
            },
            { 
                field: 'grade', 
                label: 'Grade', 
                type: 'badge',
                badgeMap: {
                    'A': 'success',
                    'B': 'info',
                    'C': 'warning',
                    'D': 'danger',
                    'E': 'dark'
                }
            }
        ],
        searchFields: ['assessment_title', 'student_name'],
        rowActions: [
            { id: 'view', label: 'View', icon: 'bi-eye', permission: 'assessments_view' },
            { id: 'edit', label: 'Edit Mark', icon: 'bi-pencil', variant: 'warning', permission: 'assessments_mark' }
        ]
    });
}

async function loadAssessmentStatistics() {
    try {
        const stats = await window.API.apiCall('/reports/assessment-stats', 'GET');
        if (stats) {
            document.getElementById('totalAssessments').textContent = stats.total_assessments || 0;
            document.getElementById('completedAssessments').textContent = stats.completed || 0;
            document.getElementById('pendingMarking').textContent = stats.pending_marking || 0;
            document.getElementById('avgPerformance').textContent = (stats.avg_performance || 0).toFixed(1) + '%';
        }
    } catch (error) {
        console.error('Failed to load statistics:', error);
    }
}

function attachAssessmentEventListeners() {
    document.getElementById('createAssessmentBtn')?.addEventListener('click', () => {
        const modal = new bootstrap.Modal(document.getElementById('createAssessmentModal'));
        modal.show();
    });

    document.getElementById('assessmentsSearchInput')?.addEventListener('keyup', (e) => {
        assessmentsTable.search(e.target.value);
    });

    document.getElementById('assessmentTypeFilter')?.addEventListener('change', (e) => {
        assessmentsTable.applyFilters({ assessment_type: e.target.value });
    });

    document.getElementById('resultMarksSearchInput')?.addEventListener('keyup', (e) => {
        resultMarksTable.search(e.target.value);
    });
}
