/**
 * Inventory Page Controller
 * Manages inventory items, categories, purchase orders, and workflows
 */

let inventoryTable = null;
let purchaseOrderTable = null;

document.addEventListener('DOMContentLoaded', async () => {
    if (!AuthContext.isAuthenticated()) {
        window.location.href = '/Kingsway/index.php';
        return;
    }

    initializeInventoryTables();
    loadInventoryStatistics();
    attachInventoryEventListeners();
});

function initializeInventoryTables() {
    // Inventory items table
    inventoryTable = new DataTable('inventoryTable', {
        apiEndpoint: '/inventory/items-list',
        pageSize: 10,
        columns: [
            { field: 'id', label: '#' },
            { field: 'item_name', label: 'Item Name', sortable: true },
            { field: 'category_name', label: 'Category' },
            { field: 'quantity_on_hand', label: 'Quantity', type: 'number' },
            { field: 'reorder_level', label: 'Reorder Level', type: 'number' },
            { 
                field: 'quantity_status', 
                label: 'Status', 
                type: 'badge',
                badgeMap: { 
                    'critical': 'danger', 
                    'low': 'warning', 
                    'adequate': 'success' 
                }
            },
            { field: 'unit_cost', label: 'Unit Cost', type: 'currency' }
        ],
        searchFields: ['item_name', 'category_name', 'item_code'],
        rowActions: [
            { id: 'view', label: 'View', icon: 'bi-eye', variant: 'info', permission: 'inventory_view' },
            { id: 'edit', label: 'Edit', icon: 'bi-pencil', variant: 'warning', permission: 'inventory_edit' },
            { id: 'adjust', label: 'Adjust', icon: 'bi-arrow-left-right', variant: 'primary', permission: 'inventory_adjust' },
            { id: 'delete', label: 'Delete', icon: 'bi-trash', variant: 'danger', permission: 'inventory_delete' }
        ],
        onRowAction: handleInventoryRowAction
    });

    // Purchase orders table
    purchaseOrderTable = new DataTable('purchaseOrderTable', {
        apiEndpoint: '/inventory/purchase-orders-list',
        pageSize: 10,
        columns: [
            { field: 'id', label: 'PO #' },
            { field: 'supplier_name', label: 'Supplier', sortable: true },
            { field: 'po_date', label: 'Date', type: 'date' },
            { field: 'total_amount', label: 'Amount', type: 'currency' },
            { field: 'items_count', label: 'Items', type: 'number' },
            { field: 'status', label: 'Status', type: 'badge', badgeMap: { 
                'pending': 'warning', 
                'approved': 'info', 
                'received': 'success',
                'cancelled': 'danger'
            }}
        ],
        rowActions: [
            { id: 'view', label: 'View', icon: 'bi-eye', variant: 'info' },
            { id: 'receive', label: 'Receive', icon: 'bi-inbox', variant: 'success', permission: 'inventory_receive' },
            { id: 'cancel', label: 'Cancel', icon: 'bi-x-circle', variant: 'danger', permission: 'inventory_approve' }
        ]
    });
}

async function handleInventoryRowAction(actionId, rowIds, row) {
    if (actionId === 'view') {
        await viewInventoryDetails(rowIds[0]);
    } else if (actionId === 'adjust') {
        showAdjustStockDialog(row);
    } else if (actionId === 'receive') {
        await receivePurchaseOrder(rowIds[0]);
    }
}

async function viewInventoryDetails(itemId) {
    try {
        const item = await window.API.apiCall(`/inventory/items-with-stock?item_id=${itemId}`, 'GET');
        const history = await window.API.apiCall(`/inventory/items-history?item_id=${itemId}`, 'GET');

        const html = `
            <div class="row">
                <div class="col-md-6">
                    <h6>Item Information</h6>
                    <p><strong>Name:</strong> ${item.item_name}</p>
                    <p><strong>Category:</strong> ${item.category_name}</p>
                    <p><strong>Item Code:</strong> ${item.item_code}</p>
                    <p><strong>Unit Cost:</strong> KES ${parseFloat(item.unit_cost).toFixed(2)}</p>
                </div>
                <div class="col-md-6">
                    <h6>Stock Information</h6>
                    <p><strong>Quantity on Hand:</strong> ${item.quantity_on_hand}</p>
                    <p><strong>Reorder Level:</strong> ${item.reorder_level}</p>
                    <p><strong>Status:</strong> ${ActionButtons.createStatusBadge(item.quantity_status)}</p>
                    <p><strong>Stock Value:</strong> KES ${(item.quantity_on_hand * item.unit_cost).toFixed(2)}</p>
                </div>
            </div>
            <hr>
            <h6>Recent Movements</h6>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Quantity</th>
                            <th>Reference</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${history?.slice(0, 5).map(h => `
                            <tr>
                                <td>${new Date(h.date).toLocaleDateString()}</td>
                                <td>${h.movement_type}</td>
                                <td>${h.quantity}</td>
                                <td>${h.reference || '-'}</td>
                            </tr>
                        `).join('') || '<tr><td colspan="4" class="text-center">No history</td></tr>'}
                    </tbody>
                </table>
            </div>
        `;

        document.getElementById('viewInventoryContent').innerHTML = html;
        new bootstrap.Modal(document.getElementById('viewInventoryModal')).show();
    } catch (error) {
        window.API.showNotification('Failed to load inventory details', NOTIFICATION_TYPES.ERROR);
    }
}

async function receivePurchaseOrder(poId) {
    const confirmed = await ActionButtons.confirm('Mark this PO as received?', 'Receive');
    if (!confirmed) return;

    try {
        await window.API.apiCall('/inventory/purchase-orders-receive', 'POST', { purchase_order_id: poId });
        window.API.showNotification('Purchase order received successfully', NOTIFICATION_TYPES.SUCCESS);
        await purchaseOrderTable.refresh();
        loadInventoryStatistics();
    } catch (error) {
        window.API.showNotification(error.message, NOTIFICATION_TYPES.ERROR);
    }
}

function showAdjustStockDialog(item) {
    // Show modal to adjust stock for this item
    const modalId = 'adjustStockModal';
    document.getElementById('adjustItemName').textContent = item.item_name;
    document.getElementById('adjustCurrentQty').value = item.quantity_on_hand;
    const modal = new bootstrap.Modal(document.getElementById(modalId));
    modal.show();
}

async function loadInventoryStatistics() {
    try {
        const stats = await window.API.apiCall('/reports/inventory-stock-levels', 'GET');
        if (stats) {
            document.getElementById('totalItems').textContent = stats.total_items || 0;
            document.getElementById('lowStockItems').textContent = stats.low_stock_count || 0;
            document.getElementById('totalStockValue').textContent = 'KES ' + parseFloat(stats.total_value || 0).toFixed(2);
            document.getElementById('pendingOrders').textContent = stats.pending_orders || 0;
        }
    } catch (error) {
        console.error('Failed to load statistics:', error);
    }
}

function attachInventoryEventListeners() {
    document.getElementById('createItemBtn')?.addEventListener('click', () => {
        const modal = new bootstrap.Modal(document.getElementById('itemModal'));
        modal.show();
    });

    document.getElementById('createPOBtn')?.addEventListener('click', () => {
        const modal = new bootstrap.Modal(document.getElementById('purchaseOrderModal'));
        modal.show();
    });

    document.getElementById('inventorySearchInput')?.addEventListener('keyup', (e) => {
        inventoryTable.search(e.target.value);
    });

    document.getElementById('categoryFilter')?.addEventListener('change', (e) => {
        inventoryTable.applyFilters({ category_id: e.target.value });
    });
}
