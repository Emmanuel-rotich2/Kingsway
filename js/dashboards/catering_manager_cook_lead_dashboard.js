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
        defaultPeriod: 'today',

        async apiMethod(filters = {}) {
            try {
                const date = filters.date_to || today();
                const [statsResponse, menuResponse, stockResponse] = await Promise.all([
                    window.API.catering.getStats({ ...filters, date }),
                    window.API.catering.getMenu({ ...filters, date }),
                    window.API.catering.getFoodStock({ low_stock: 1, limit: 10 })
                ]);

                const stats = unwrap(statsResponse) || {};
                const stockRows = Array.isArray(unwrap(stockResponse)) ? unwrap(stockResponse) : [];
                const menuRows = Array.isArray(unwrap(menuResponse)) ? unwrap(menuResponse) : [];

                const statusCounts = menuRows.reduce((counts, row) => {
                    const status = String(row.status || 'planned').toLowerCase();
                    counts[status] = (counts[status] || 0) + 1;
                    return counts;
                }, {});

                const plannedServings = Number(stats.planned_servings || 0);
                const costPerMeal = plannedServings > 0
                    ? Number(stats.daily_cost || 0) / plannedServings
                    : 0;
                const quantityUsed = Number(stats.quantity_used || 0);
                const wastePercent = quantityUsed > 0
                    ? (Number(stats.waste_quantity || 0) / quantityUsed) * 100
                    : 0;

                return {
                    meta: { scope_label: date, period: filters.period },
                    cards: {
                        students_to_feed: Number(stats.students_to_feed || plannedServings || 0),
                        meals_served: Number(stats.prepared_meals || stats.meals_served || 0),
                        low_food_stock: Number(stats.low_stock || stockRows.length || 0),
                        cost_per_meal: costPerMeal,
                        waste_percent: wastePercent
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
                        consumption_trend: {
                            labels: menuRows.map(row => row.meal_type || row.menu_item || 'Meal'),
                            datasets: [
                                { label: 'Planned', data: menuRows.map(row => Number(row.planned_servings || 0)), borderWidth: 2 },
                                { label: 'Prepared', data: menuRows.map(row => Number(row.prepared_quantity || 0)), borderWidth: 2 },
                                { label: 'Served', data: menuRows.map(row => Number(row.actual_servings || 0)), borderWidth: 2 }
                            ]
                        }
                    },
                    menu: menuRows,
                    low_stock: stockRows
                };
            } catch (e) {
                return {
                    meta: { scope_label: 'Catering', period: filters.period },
                    cards: { students_to_feed: 0, meals_served: 0, low_food_stock: 0, cost_per_meal: 0, waste_percent: 0 },
                    charts: { meal_readiness: { labels: [], data: [] }, consumption_trend: { labels: [], datasets: [] } },
                    menu: [],
                    low_stock: []
                };
            }
        },

        cards: [
            { id: 'catStudentsToFeed', path: 'cards.students_to_feed', subtitleId: 'catStudentsSub', subtitle: 'Boarders + day' },
            { id: 'catMealsServed', path: 'cards.meals_served', subtitleId: 'catMealsServedSub', subtitle: 'Across all meals' },
            { id: 'catLowStock', path: 'cards.low_food_stock', subtitleId: 'catLowStockSub', subtitle: 'Food items needing replenishment' },
            { id: 'catCostPerMeal', path: 'cards.cost_per_meal', subtitleId: 'catCostPerMealSub', subtitle: 'KES average', format: 'currency' },
            { id: 'catWastePercent', path: 'cards.waste_percent', subtitleId: 'catWasteSub', subtitle: '% of production', format: 'percent' }
        ],
        chartDefinitions: [
            {
                id: 'catStockChart',
                data: (data) => {
                    const rows = Array.isArray(data.low_stock) ? data.low_stock : [];
                    return {
                        labels: rows.map(row => row.name || 'Item'),
                        datasets: [
                            { label: 'Current', data: rows.map(row => Number(row.current_quantity || 0)) },
                            { label: 'Minimum', data: rows.map(row => Number(row.minimum_quantity || 0)) }
                        ]
                    };
                },
                label: 'Stock',
                type: 'bar',
                showLegend: true
            },
            { id: 'catConsumptionChart', path: 'charts.consumption_trend', label: 'Servings', type: 'bar', showLegend: true }
        ],

        afterRender(data) {
            const menu = Array.isArray(data.menu) ? data.menu : [];
            this.renderTodaysMenu(menu);
            this.renderWeeklyMenu(menu);
            this.fillEmptyRows([
                ['catPendingDeliveriesBody', 4, 'No pending deliveries.'],
                ['catRecentDeliveriesBody', 4, 'No recent deliveries recorded.'],
                ['catStaffBody', 4, 'No kitchen staff assignments for this period.'],
                ['catMealDemandBody', 5, 'No meal demand data for this period.'],
                ['catNutritionDetailBody', 4, 'No nutrition compliance data for this period.']
            ]);
        },

        renderTodaysMenu(rows) {
            const byMeal = { breakfast: [], lunch: [], dinner: [], snack: [] };
            rows.forEach((row) => {
                const meal = String(row.meal_type || '').toLowerCase();
                if (byMeal[meal]) {
                    byMeal[meal].push(row);
                }
            });

            ['breakfast', 'lunch', 'dinner'].forEach((meal) => {
                const key = meal.charAt(0).toUpperCase() + meal.slice(1);
                const listEl = document.getElementById(`cat${key}Items`);
                const statusEl = document.getElementById(`cat${key}Status`);
                const timeEl = document.getElementById(`cat${key}Time`);
                if (!listEl) {
                    return;
                }
                const items = byMeal[meal];
                if (!items.length) {
                    listEl.innerHTML = '<li class="mb-1"><i class="bi bi-circle-fill text-muted" style="font-size:0.4rem"></i> No items planned.</li>';
                    if (statusEl) statusEl.textContent = 'Not planned';
                    if (timeEl) timeEl.textContent = '—';
                    return;
                }
                listEl.innerHTML = items.map((row) => `
                    <li class="mb-1"><i class="bi bi-circle-fill text-muted" style="font-size:0.4rem"></i> ${this.escapeHtml(row.menu_item || 'Item')}</li>
                `).join('');
                if (statusEl) statusEl.textContent = items[0].status || 'Planned';
                if (timeEl) timeEl.textContent = String(items[0].prepared_at || items[0].plan_date || '').slice(0, 10) || '—';
            });
        },

        renderWeeklyMenu(rows) {
            const body = document.getElementById('catWeeklyMenuBody');
            if (!body) {
                return;
            }
            const byDate = new Map();
            rows.forEach((row) => {
                const day = String(row.plan_date || '').slice(0, 10);
                if (!day) {
                    return;
                }
                if (!byDate.has(day)) {
                    byDate.set(day, { breakfast: [], lunch: [], dinner: [] });
                }
                const meal = String(row.meal_type || '').toLowerCase();
                const bucket = byDate.get(day);
                if (bucket[meal]) {
                    bucket[meal].push(row.menu_item || 'Item');
                }
            });

            const days = [...byDate.keys()].sort();
            if (!days.length) {
                body.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">No menu planned for this period.</td></tr>';
                return;
            }
            body.innerHTML = days.map((day) => {
                const bucket = byDate.get(day);
                return `<tr>
                    <td>${this.escapeHtml(day)}</td>
                    <td>${this.escapeHtml(bucket.breakfast.join(', ')) || '—'}</td>
                    <td>${this.escapeHtml(bucket.lunch.join(', ')) || '—'}</td>
                    <td>${this.escapeHtml(bucket.dinner.join(', ')) || '—'}</td>
                </tr>`;
            }).join('');
        },

        fillEmptyRows(definitions) {
            definitions.forEach(([bodyId, colspan, message]) => {
                const body = document.getElementById(bodyId);
                if (!body) {
                    return;
                }
                body.innerHTML = `<tr><td colspan="${colspan}" class="text-center text-muted py-4">${this.escapeHtml(message)}</td></tr>`;
            });
        }
    });

    window.CateringManagerDashboardController = controller;
    window.cateringDashboardController = controller;
    DashboardBaseController.boot(controller, 'CateringManagerDashboardController');
})();