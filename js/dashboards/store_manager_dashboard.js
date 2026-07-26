/**
 * Inventory Manager Dashboard Controller
 * Uses the canonical InventoryAPI dashboard summary backed by inventory views.
 */
(() => {
    const unwrap = (response) => {
        let value = response;
        for (let depth = 0; depth < 4; depth += 1) {
            if (value && typeof value === 'object' && !Array.isArray(value)
                && Object.prototype.hasOwnProperty.call(value, 'data')) {
                value = value.data;
                continue;
            }
            break;
        }
        return value;
    };

    const controller = DashboardBaseController.create({
        controllerName: 'StoreManagerDashboardController',
        rootId: 'inventoryDashboard',
        refreshButtonId: 'inventoryDashboardRefresh',
        stateId: 'inventoryDashboardState',
        scopeId: 'inventoryDashboardScope',
        lastUpdatedId: 'inventoryDashboardLastUpdated',

        async apiMethod() {
            const source = unwrap(await window.API.inventory.getDashboard({})) || {};
            const summary = source.summary || {};
            const categories = Array.isArray(source.by_category) ? source.by_category : [];
            const health = Array.isArray(source.stock_health) ? source.stock_health : [];

            return {
                meta: { scope_label: 'Inventory' },
                cards: {
                    active_items: Number(summary.active_items || 0),
                    low_stock: Number(summary.low_stock || 0),
                    out_of_stock: Number(summary.out_of_stock || 0),
                    inventory_value: Number(summary.inventory_value || 0)
                },
                charts: {
                    by_category: {
                        labels: categories.map((row) => row.category || 'Uncategorised'),
                        data: categories.map((row) => Number(row.item_count || 0))
                    },
                    health: {
                        labels: health.map((row) => row.stock_status || 'Unknown'),
                        data: health.map((row) => Number(row.item_count || 0))
                    }
                },
                tables: {
                    low_stock: Array.isArray(source.low_stock_items)
                        ? source.low_stock_items
                        : [],
                    requisitions: Array.isArray(source.pending_requisitions)
                        ? source.pending_requisitions
                        : []
                }
            };
        },

        cards: [
            { id: 'invItems', path: 'cards.active_items', subtitleId: 'invItemsSub', subtitle: 'Items available for issue' },
            { id: 'invLowStock', path: 'cards.low_stock', subtitleId: 'invLowStockSub', subtitle: 'At or below reorder level' },
            { id: 'invOutStock', path: 'cards.out_of_stock', subtitleId: 'invOutStockSub', subtitle: 'Require replenishment' },
            { id: 'invValue', path: 'cards.inventory_value', format: 'currency', subtitleId: 'invValueSub', subtitle: 'Current stock valuation' }
        ],
        chartDefinitions: [
            { id: 'invCategoryChart', path: 'charts.by_category', label: 'Items', type: 'doughnut', showLegend: true },
            { id: 'invStatusChart', path: 'charts.health', label: 'Items', type: 'bar' }
        ],
        tableDefinitions: [
            {
                bodyId: 'invLowStockBody',
                path: 'tables.low_stock',
                emptyText: 'No low-stock items.',
                columns: [
                    { key: 'name' },
                    { key: 'category' },
                    { key: 'current_quantity', format: 'number' },
                    { key: 'minimum_quantity', format: 'number' }
                ]
            },
            {
                bodyId: 'invRequisitionsBody',
                path: 'tables.requisitions',
                emptyText: 'No pending requisitions.',
                columns: [
                    { key: 'requisition_number' },
                    { key: 'department' },
                    {
                        key: 'priority',
                        render: (value, row, instance) => instance.badge(value, {
                            low: 'secondary', normal: 'info', high: 'warning', urgent: 'danger'
                        })
                    },
                    { key: 'required_date', format: 'date' }
                ]
            }
        ]
    });

    window.StoreManagerDashboardController = controller;
    window.storeDashboardController = controller;
    DashboardBaseController.boot(controller, 'StoreManagerDashboardController');
})();
