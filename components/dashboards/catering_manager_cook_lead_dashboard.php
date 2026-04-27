<?php
/**
 * Catering Manager / Cook Lead Dashboard — Role ID 16
 */
?>
<div class="container-fluid py-3" id="catering-dashboard">

    <!-- Greeting Bar -->
    <div class="dash-greeting-bar mb-4">
        <div>
            <h5 id="cateringGreeting">Good morning!</h5>
            <p>Menu planning, food inventory, and meal management</p>
        </div>
        <div class="dash-meta">
            <button class="btn btn-sm btn-light" onclick="cateringDashboardController.navigate('menu_planning')">
                <i class="bi bi-calendar-plus me-1"></i>Plan Menu
            </button>
            <button class="dash-refresh-btn" onclick="cateringDashboardController.refresh()">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="dash-stat dsc-orange">
                <div class="dash-stat-value" id="mealsToday">0</div>
                <div class="dash-stat-label">Meals Today</div>
                <i class="bi bi-egg-fried dash-stat-icon"></i>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="dash-stat dsc-green">
                <div class="dash-stat-value" id="foodItems">0</div>
                <div class="dash-stat-label">Food Items</div>
                <i class="bi bi-basket3-fill dash-stat-icon"></i>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="dash-stat dsc-red">
                <div class="dash-stat-value" id="lowFoodStock">0</div>
                <div class="dash-stat-label">Low Stock</div>
                <i class="bi bi-exclamation-triangle-fill dash-stat-icon"></i>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="dash-stat dsc-amber">
                <div class="dash-stat-value small" id="dailyCost">KES 0</div>
                <div class="dash-stat-label">Daily Cost</div>
                <i class="bi bi-cash-coin dash-stat-icon"></i>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <!-- Today's Menu -->
        <div class="col-md-5">
            <div class="card dash-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-journal-text me-2"></i>Today's Menu</h6>
                    <a href="#" onclick="cateringDashboardController.navigate('menu_planning')" class="btn btn-sm btn-outline-warning">Edit</a>
                </div>
                <div class="list-group list-group-flush" id="todaysMenuList">
                    <div class="text-center text-muted py-3">Loading menu...</div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card dash-card mt-3">
                <div class="card-header"><h6 class="mb-0"><i class="bi bi-lightning me-2 text-warning"></i>Quick Actions</h6></div>
                <div class="card-body">
                    <a href="#" onclick="cateringDashboardController.navigate('food_store')" class="dash-quick-link">
                        <i class="bi bi-box-seam ql-icon bg-warning text-white"></i>
                        <span>Food Store</span><i class="bi bi-chevron-right ql-arrow"></i>
                    </a>
                    <a href="#" onclick="cateringDashboardController.navigate('manage_menus')" class="dash-quick-link">
                        <i class="bi bi-list-ul ql-icon bg-primary text-white"></i>
                        <span>Manage Menus</span><i class="bi bi-chevron-right ql-arrow"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Food Stock Levels -->
        <div class="col-md-7">
            <div class="card dash-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-exclamation-triangle text-danger me-2"></i>Food Stock Alerts</h6>
                    <a href="#" onclick="cateringDashboardController.navigate('food_store')" class="btn btn-sm btn-outline-danger">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr><th>Item</th><th>Current Qty</th><th>Unit</th><th>Status</th></tr>
                        </thead>
                        <tbody id="foodStockTableBody">
                            <tr><td colspan="4" class="text-center text-muted py-3">Loading stock...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card dash-card mt-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-calendar-week me-2"></i>This Week's Meals</h6>
                </div>
                <div class="card-body p-0" id="weeklyMenuSummary">
                    <div class="text-center text-muted py-3">Loading...</div>
                </div>
            </div>
        </div>
    </div>
</div>
