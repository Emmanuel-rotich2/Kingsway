<?php
/**
 * Catering Manager Dashboard — Menus, nutrition, stock and supplier tracking.
 * Role: Catering Manager / Cook Lead
 */
$rootId = 'cateringDashboard';
$periods = [
    ['key' => 'today', 'label' => 'Today'],
    ['key' => 'week', 'label' => 'This Week'],
    ['key' => 'term', 'label' => 'This Term'],
];
$default = 'today';

$escape = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};
?>

<div class="container-fluid py-4 role-dashboard dashboard-surface dashboard-culinary" id="<?= $escape($rootId) ?>">
    <?php require __DIR__ . '/partials/period_selector.php'; ?>

    <!-- Meta Bar -->
    <div class="dash-meta-bar mb-4 d-flex align-items-center justify-content-end gap-3 flex-wrap">
        <span class="dash-badge bg-success" id="<?= $escape($rootId) ?>MealBadge">—</span>
        <span class="small text-muted">Updated: <span id="<?= $escape($rootId) ?>LastUpdated">—</span></span>
        <button class="btn btn-sm btn-outline-success" id="<?= $escape($rootId) ?>Refresh">
            <i class="bi bi-arrow-clockwise me-1"></i>Refresh
        </button>
    </div>

    <!-- ROW 1: TODAY'S MENU — full-width hero -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card dash-card h-100 border-start border-4 border-warning">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h6 class="mb-0"><i class="bi bi-sunrise me-2 text-warning"></i>Breakfast</h6>
                        <span class="badge bg-secondary" id="catBreakfastStatus">Pending</span>
                    </div>
                    <div class="small text-muted mb-2">Serving: <strong id="catBreakfastTime">—</strong></div>
                    <ul class="list-unstyled small mb-0" id="catBreakfastItems">
                        <li class="mb-1"><i class="bi bi-circle-fill text-muted" style="font-size:0.4rem"></i> Loading...</li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card dash-card h-100 border-start border-4 border-success">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h6 class="mb-0"><i class="bi bi-cup-hot me-2 text-success"></i>Lunch</h6>
                        <span class="badge bg-secondary" id="catLunchStatus">Pending</span>
                    </div>
                    <div class="small text-muted mb-2">Serving: <strong id="catLunchTime">—</strong></div>
                    <ul class="list-unstyled small mb-0" id="catLunchItems">
                        <li class="mb-1"><i class="bi bi-circle-fill text-muted" style="font-size:0.4rem"></i> Loading...</li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card dash-card h-100 border-start border-4 border-info">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h6 class="mb-0"><i class="bi bi-moon me-2 text-info"></i>Dinner</h6>
                        <span class="badge bg-secondary" id="catDinnerStatus">Pending</span>
                    </div>
                    <div class="small text-muted mb-2">Serving: <strong id="catDinnerTime">—</strong></div>
                    <ul class="list-unstyled small mb-0" id="catDinnerItems">
                        <li class="mb-1"><i class="bi bi-circle-fill text-muted" style="font-size:0.4rem"></i> Loading...</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- ROW 2: KPIs — 6 -->
    <div class="row g-3 mb-3">
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-blue">
                <i class="bi bi-people dash-stat-icon"></i>
                <div class="dash-stat-value" id="catStudentsToFeed">—</div>
                <div class="dash-stat-label">Students to Feed</div>
                <div class="dash-stat-sub" id="catStudentsSub">boarders + day</div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-green">
                <i class="bi bi-check2-circle dash-stat-icon"></i>
                <div class="dash-stat-value" id="catMealsServed">—</div>
                <div class="dash-stat-label">Meals Served Today</div>
                <div class="dash-stat-sub" id="catMealsServedSub">across all meals</div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-red">
                <i class="bi bi-exclamation-triangle dash-stat-icon"></i>
                <div class="dash-stat-value" id="catLowStock">—</div>
                <div class="dash-stat-label">Low Stock Items</div>
                <div class="dash-stat-sub" id="catLowStockSub">need reorder</div>
            </div>
        </div>
    </div>
    <div class="row g-3 mb-4">
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-amber">
                <i class="bi bi-cash-coin dash-stat-icon"></i>
                <div class="dash-stat-value" id="catCostPerMeal">—</div>
                <div class="dash-stat-label">Cost Per Meal</div>
                <div class="dash-stat-sub" id="catCostPerMealSub">KES average</div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-teal">
                <i class="bi bi-leaf dash-stat-icon"></i>
                <div class="dash-stat-value" id="catNutritionCompliance">—</div>
                <div class="dash-stat-label">Nutrition Compliance</div>
                <div class="dash-stat-sub" id="catNutritionSub">% of target</div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="dash-stat dsc-orange">
                <i class="bi bi-trash dash-stat-icon"></i>
                <div class="dash-stat-value" id="catWastePercent">—</div>
                <div class="dash-stat-label">Waste</div>
                <div class="dash-stat-sub" id="catWasteSub">% of production</div>
            </div>
        </div>
    </div>

    <!-- ROW 3: STOCK & CONSUMPTION — 2 panels -->
    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card dash-card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-graph-up me-2 text-primary"></i>Consumption Trend</h6>
                    <small class="text-muted">Last 7 days</small>
                </div>
                <div class="card-body">
                    <div class="dash-chart-wrap-lg"><canvas id="catConsumptionChart"></canvas></div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card dash-card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-bar-chart me-2 text-success"></i>Food Stock Levels</h6>
                    <small class="text-muted">Current vs minimum</small>
                </div>
                <div class="card-body">
                    <div class="dash-chart-wrap-lg"><canvas id="catStockChart"></canvas></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ROW 4: MENU PLANNING — 2 panels -->
    <div class="row g-3 mb-4">
        <div class="col-lg-7">
            <div class="card dash-card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-calendar-week me-2 text-info"></i>Weekly Menu Calendar</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Day</th>
                                    <th scope="col">Breakfast</th>
                                    <th scope="col">Lunch</th>
                                    <th scope="col">Dinner</th>
                                </tr>
                            </thead>
                            <tbody id="catWeeklyMenuBody">
                                <tr><td colspan="4" class="text-center text-muted py-4">Loading weekly menu...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card dash-card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-leaf me-2 text-success"></i>Nutrition Compliance</h6>
                    <small class="text-muted">By meal type</small>
                </div>
                <div class="card-body">
                    <div class="dash-chart-wrap-lg"><canvas id="catNutritionChart"></canvas></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ROW 5: SUPPLIER STATUS — 2 panels -->
    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card dash-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-truck me-2 text-warning"></i>Pending Deliveries</h6>
                    <button class="btn btn-sm btn-outline-warning" data-route="vendors">All Suppliers</button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Supplier</th>
                                    <th scope="col">Items</th>
                                    <th scope="col">Expected</th>
                                    <th scope="col">Status</th>
                                </tr>
                            </thead>
                            <tbody id="catPendingDeliveriesBody">
                                <tr><td colspan="4" class="text-center text-muted py-4">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card dash-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-box-arrow-in-down me-2 text-success"></i>Recent Deliveries</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Date</th>
                                    <th scope="col">Supplier</th>
                                    <th scope="col">Amount</th>
                                    <th scope="col">Status</th>
                                </tr>
                            </thead>
                            <tbody id="catRecentDeliveriesBody">
                                <tr><td colspan="4" class="text-center text-muted py-4">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Kitchen Staff Assignments -->
    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card dash-card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-person-badge me-2 text-primary"></i>Kitchen Staff Today</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Staff</th>
                                    <th scope="col">Role</th>
                                    <th scope="col">Shift</th>
                                    <th scope="col">Status</th>
                                </tr>
                            </thead>
                            <tbody id="catStaffBody">
                                <tr><td colspan="4" class="text-center text-muted py-4">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card dash-card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-clipboard-data me-2 text-success"></i>Weekly Cost Summary</h6>
                </div>
                <div class="card-body">
                    <div class="dash-chart-wrap-lg"><canvas id="catCostChart"></canvas></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Meal Production Summary -->
    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card dash-card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-people me-2 text-primary"></i>Meal Demand by Type</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Meal</th>
                                    <th scope="col">Boarders</th>
                                    <th scope="col">Day Students</th>
                                    <th scope="col">Total</th>
                                    <th scope="col">Served</th>
                                </tr>
                            </thead>
                            <tbody id="catMealDemandBody">
                                <tr><td colspan="5" class="text-center text-muted py-4">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card dash-card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-list-check me-2 text-success"></i>Nutrition Compliance Detail</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Nutrient</th>
                                    <th scope="col">Target</th>
                                    <th scope="col">Actual</th>
                                    <th scope="col">Status</th>
                                </tr>
                            </thead>
                            <tbody id="catNutritionDetailBody">
                                <tr><td colspan="4" class="text-center text-muted py-4">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ROW 6: QUICK ACTIONS -->
    <div class="row g-3 mb-3">
        <div class="col-12">
            <div class="card dash-card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-lightning-charge me-2 text-warning"></i>Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-md-3 col-lg">
                            <a href="#" data-route="todays_menu" class="dash-quick-link">
                                <i class="bi bi-card-list ql-icon bg-warning text-white"></i>
                                <span>Today's Menu</span><i class="bi bi-chevron-right ql-arrow"></i>
                            </a>
                        </div>
                        <div class="col-md-3 col-lg">
                            <a href="#" data-route="food_store" class="dash-quick-link">
                                <i class="bi bi-warehouse ql-icon bg-primary text-white"></i>
                                <span>Food Store</span><i class="bi bi-chevron-right ql-arrow"></i>
                            </a>
                        </div>
                        <div class="col-md-3 col-lg">
                            <a href="#" data-route="meal_statistics" class="dash-quick-link">
                                <i class="bi bi-people ql-icon bg-success text-white"></i>
                                <span>Meal Allocations</span><i class="bi bi-chevron-right ql-arrow"></i>
                            </a>
                        </div>
                        <div class="col-md-3 col-lg">
                            <a href="#" data-route="vendors" class="dash-quick-link">
                                <i class="bi bi-truck ql-icon bg-info text-white"></i>
                                <span>Suppliers</span><i class="bi bi-chevron-right ql-arrow"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<?php asset_script($appBase, 'js/dashboards/dashboard_base_controller.js'); ?>
<?php asset_script($appBase, 'js/dashboards/catering_manager_cook_lead_dashboard.js'); ?>
