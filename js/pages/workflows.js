/**
 * Workflows Page Controller
 * Manages academic workflows (promotions, transfers, terminations)
 */

let promotionsTable = null;
let transfersTable = null;
let terminationsTable = null;

document.addEventListener('DOMContentLoaded', async () => {
    if (!AuthContext.isAuthenticated()) {
        window.location.href = '/Kingsway/index.php';
        return;
    }

    initializeWorkflowsTables();
    loadWorkflowsStatistics();
    attachWorkflowsEventListeners();
});

function initializeWorkflowsTables() {
    // Promotions Table
    promotionsTable = new DataTable('promotionsTable', {
        apiEndpoint: '/workflows/promotions',
        pageSize: 10,
        columns: [
            { field: 'id', label: '#' },
            { field: 'student_name', label: 'Student' },
            { field: 'current_class', label: 'Current Class' },
            { field: 'promoted_class', label: 'Promoted To' },
            { field: 'academic_year', label: 'Year' },
            { 
                field: 'status', 
                label: 'Status', 
                type: 'badge',
                badgeMap: { 
                    'pending': 'warning', 
                    'approved': 'success',
                    'executed': 'info',
                    'rejected': 'danger'
                }
            }
        ],
        searchFields: ['student_name', 'current_class'],
        rowActions: [
            { id: 'view', label: 'View', icon: 'bi-eye', permission: 'workflows_view' },
            { id: 'approve', label: 'Approve', icon: 'bi-check-circle', variant: 'success', permission: 'workflows_approve' },
            { id: 'reject', label: 'Reject', icon: 'bi-x-circle', variant: 'danger', permission: 'workflows_approve' }
        ]
    });

    // Transfers Table
    transfersTable = new DataTable('transfersTable', {
        apiEndpoint: '/workflows/transfers',
        pageSize: 10,
        columns: [
            { field: 'id', label: '#' },
            { field: 'student_name', label: 'Student' },
            { field: 'from_school', label: 'From School' },
            { field: 'to_school', label: 'To School' },
            { field: 'transfer_date', label: 'Date', type: 'date', sortable: true },
            { 
                field: 'status', 
                label: 'Status', 
                type: 'badge',
                badgeMap: { 
                    'pending': 'warning', 
                    'approved': 'success',
                    'transferred': 'info',
                    'rejected': 'danger'
                }
            }
        ],
        searchFields: ['student_name', 'from_school'],
        rowActions: [
            { id: 'view', label: 'View', icon: 'bi-eye', permission: 'workflows_view' },
            { id: 'approve', label: 'Approve', icon: 'bi-check-circle', variant: 'success', permission: 'workflows_approve' }
        ]
    });

    // Terminations Table
    terminationsTable = new DataTable('terminationsTable', {
        apiEndpoint: '/workflows/terminations',
        pageSize: 10,
        columns: [
            { field: 'id', label: '#' },
            { field: 'student_name', label: 'Student' },
            { field: 'current_class', label: 'Class' },
            { field: 'termination_reason', label: 'Reason' },
            { field: 'termination_date', label: 'Date', type: 'date', sortable: true },
            { 
                field: 'status', 
                label: 'Status', 
                type: 'badge',
                badgeMap: { 
                    'pending': 'warning', 
                    'approved': 'success',
                    'executed': 'danger'
                }
            }
        ],
        searchFields: ['student_name', 'current_class'],
        rowActions: [
            { id: 'view', label: 'View', icon: 'bi-eye', permission: 'workflows_view' },
            { id: 'approve', label: 'Approve', icon: 'bi-check-circle', variant: 'success', permission: 'workflows_approve' }
        ]
    });
}

async function loadWorkflowsStatistics() {
    try {
        const stats = await window.API.apiCall('/reports/workflows-stats', 'GET');
        if (stats) {
            document.getElementById('pendingPromotions').textContent = stats.pending_promotions || 0;
            document.getElementById('pendingTransfers').textContent = stats.pending_transfers || 0;
            document.getElementById('pendingTerminations').textContent = stats.pending_terminations || 0;
            document.getElementById('totalExecuted').textContent = stats.total_executed || 0;
        }
    } catch (error) {
        console.error('Failed to load statistics:', error);
    }
}

function attachWorkflowsEventListeners() {
    document.getElementById('initiatePromotionBtn')?.addEventListener('click', () => {
        const modal = new bootstrap.Modal(document.getElementById('promotiomModal'));
        modal.show();
    });

    document.getElementById('initiateTransferBtn')?.addEventListener('click', () => {
        const modal = new bootstrap.Modal(document.getElementById('transferModal'));
        modal.show();
    });

    document.getElementById('initiateTerminationBtn')?.addEventListener('click', () => {
        const modal = new bootstrap.Modal(document.getElementById('terminationModal'));
        modal.show();
    });

    document.getElementById('promotionsSearchInput')?.addEventListener('keyup', (e) => {
        promotionsTable.search(e.target.value);
    });

    document.getElementById('transfersSearchInput')?.addEventListener('keyup', (e) => {
        transfersTable.search(e.target.value);
    });

    document.getElementById('terminationsSearchInput')?.addEventListener('keyup', (e) => {
        terminationsTable.search(e.target.value);
    });
}
