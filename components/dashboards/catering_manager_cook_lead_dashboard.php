<?php
/**
 * Catering Manager / Cook Lead Dashboard
 * Role: Cateress (ID 16) — menu planning, food stock, meal tracking
 */
?>
<div class="container-fluid py-3" id="catering-dashboard">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1"><i class="bi bi-egg-fried me-2 text-warning"></i>Catering Dashboard</h4>
            <p class="text-muted mb-0">Menu planning, food inventory, and meal management</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-warning btn-sm" onclick="cateringDashboardController.navigate('menu_planning')">
                <i class="bi bi-calendar-plus me-1"></i>Plan Menu
            </button>
            <button class="btn btn-outline-secondary btn-sm" onclick="cateringDashboardController.refresh()">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
        </div>
    </div>

    <!-- Stat Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body py-3">
                    <div class="fs-2 mb-1">🍽️</div>
                    <h4 class="mb-0 text-primary" id="mealsToday">0</h4>
                    <small class="text-muted">Meals Today</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body py-3">
                    <div class="fs-2 mb-1">🧺</div>
                    <h4 class="mb-0 text-success" id="foodItems">0</h4>
                    <small class="text-muted">Food Items</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body py-3">
                    <div class="fs-2 mb-1">⚠️</div>
                    <h4 class="mb-0 text-danger" id="lowFoodStock">0</h4>
                    <small class="text-muted">Low Stock</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body py-3">
                    <div class="fs-2 mb-1">💰</div>
                    <h4 class="mb-0 text-warning small" id="dailyCost">KES 0</h4>
                    <small class="text-muted">Daily Cost</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <!-- Today's Menu -->
        <div class="col-md-5">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-journal-text me-2"></i>Today's Menu</h6>
                    <a href="#" onclick="cateringDashboardController.navigate('menu_planning')" class="btn btn-sm btn-outline-warning">Edit</a>
                </div>
                <div class="list-group list-group-flush" id="todaysMenuList">
                    <div class="text-center text-muted py-3">Loading menu...</div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card shadow-sm mt-3">
                <div class="card-header bg-white"><h6 class="mb-0">Quick Actions</h6></div>
                <div class="card-body d-grid gap-2">
                    <button class="btn btn-outline-warning btn-sm" onclick="cateringDashboardController.navigate('food_store')">
                        <i class="bi bi-box-seam me-1"></i>Food Store
                    </button>
                    <button class="btn btn-outline-primary btn-sm" onclick="cateringDashboardController.navigate('manage_menus')">
                        <i class="bi bi-list-ul me-1"></i>Manage Menus
                    </button>
                </div>
            </div>
        </div>

        <!-- Food Stock Levels -->
        <div class="col-md-7">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
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

            <!-- Weekly Menu Summary -->
            <div class="card shadow-sm mt-3">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="bi bi-calendar-week me-2"></i>This Week's Meals</h6>
                </div>
                <div class="card-body p-0" id="weeklyMenuSummary">
                    <div class="text-center text-muted py-3">Loading...</div>
                </div>
            </div>
        </div>
    </div>
</div>
