/**
 * Catering Manager Dashboard Controller
 * Composes existing CateringController/MealReportManager endpoints.
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

    const today = () => new Date().toISOString().slice(0, 10);

    const controller = DashboardBaseController.create({
        controllerName: 'CateringManagerDashboardController',
        rootId: 'cateringDashboard',
        refreshButtonId: 'cateringDashboardRefresh',
        stateId: 'cateringDashboardState',
        scopeId: 'cateringDashboardScope',
        lastUpdatedId: 'cateringDashboardLastUpdated',

        async apiMethod() {
            const date = today();
            const [statsResponse, menuResponse, stockResponse] = await Promise.all([
                window.API.catering.getStats({ date }),
                window.API.catering.getMenu({ date }),
                window.API.catering.getFoodStock({ low_stock: 1, limit: 10 })
            ]);

            const stats = unwrap(statsResponse) || {};
            const menu = unwrap(menuResponse);
            const stock = unwrap(stockResponse);
            const menuRows = Array.isArray(menu) ? menu : [];
            const stockRows = Array.isArray(stock) ? stock : [];

            const statusCounts = menuRows.reduce((counts, row) => {
                const status = String(row.status || 'planned').toLowerCase();
                counts[status] = (counts[status] || 0) + 1;
                return counts;
            }, {});

            return {
                meta: { scope_label: date },
                cards: {
                    meals_planned: Number(stats.meals_planned || 0),
                    planned_servings: Number(stats.planned_servings || 0),
                    prepared_meals: Number(stats.prepared_meals || 0),
                    low_food_stock: Number(stats.low_stock || stockRows.length || 0)
                },
                charts: {
                    meal_readiness: {
                        labels: ['Planned', 'Prepared', 'Served', 'Cancelled'],
                        data: [
                            Number(statusCounts.planned || 0),
                            Number(statusCounts.prepared || 0),
                            Number(statusCounts.served || 0),
                            Number(statusCounts.cancelled || 0)
                        ]
                    },
                    serving_progress: {
                        labels: menuRows.map((row) => row.meal_type || row.menu_item || 'Meal'),
                        datasets: [
                            {
                                label: 'Planned',
                                data: menuRows.map((row) => Number(row.planned_servings || 0)),
                                borderWidth: 2
                            },
                            {
                                label: 'Prepared',
                                data: menuRows.map((row) => Number(row.prepared_quantity || 0)),
                                borderWidth: 2
                            },
                            {
                                label: 'Served',
                                data: menuRows.map((row) => Number(row.actual_servings || 0)),
                                borderWidth: 2
                            }
                        ]
                    }
                },
                tables: {
                    meal_plan: menuRows,
                    low_stock: stockRows
                }
            };
        },

        cards: [
            { id: 'catMeals', path: 'cards.meals_planned', subtitleId: 'catMealsSub', subtitle: 'Breakfast, lunch, dinner and snacks' },
            { id: 'catServings', path: 'cards.planned_servings', subtitleId: 'catServingsSub', subtitle: 'Total planned portions' },
            { id: 'catPrepared', path: 'cards.prepared_meals', subtitleId: 'catPreparedSub', subtitle: 'Prepared or served' },
            { id: 'catLowStock', path: 'cards.low_food_stock', subtitleId: 'catLowStockSub', subtitle: 'Food items needing replenishment' }
        ],
        chartDefinitions: [
            { id: 'catMealChart', path: 'charts.meal_readiness', label: 'Meals', type: 'bar' },
            { id: 'catConsumptionChart', path: 'charts.serving_progress', label: 'Servings', type: 'bar', showLegend: true }
        ],
        tableDefinitions: [
            {
                bodyId: 'catMenuBody',
                path: 'tables.meal_plan',
                emptyText: 'No meals planned for today.',
                columns: [
                    { key: 'meal_type' },
                    { key: 'menu_item' },
                    { key: 'planned_servings', format: 'number' },
                    {
                        key: 'status',
                        render: (value, row, instance) => instance.badge(value, {
                            planned: 'primary', prepared: 'warning', served: 'success', cancelled: 'danger'
                        })
                    }
                ]
            },
            {
                bodyId: 'catStockBody',
                path: 'tables.low_stock',
                emptyText: 'No food stock alerts.',
                columns: [
                    { key: 'name' },
                    { key: 'category' },
                    { key: 'current_quantity', format: 'number' },
                    { key: 'minimum_quantity', format: 'number' }
                ]
            }
        ]
    });

    window.CateringManagerDashboardController = controller;
    window.cateringDashboardController = controller;
    DashboardBaseController.boot(controller, 'CateringManagerDashboardController');
})();
