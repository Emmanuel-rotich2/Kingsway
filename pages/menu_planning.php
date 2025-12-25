<?php
/**
 * Menu Planning Page (Cafeteria menu for boarding school)
 * HTML structure only - logic will be in js/pages/menu_planning.js
 * Embedded in app_layout.php
 */
?>

<div class="card shadow-sm">
    <div class="card-header bg-gradient bg-success text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="fas fa-utensils"></i> Menu Planning</h4>
            <div class="btn-group">
                <button class="btn btn-light btn-sm" id="addMenuBtn" data-permission="menu_manage">
                    <i class="bi bi-plus-circle"></i> Add Menu
                </button>
                <button class="btn btn-outline-light btn-sm" id="printMenuBtn">
                    <i class="bi bi-printer"></i> Print Menu
                </button>
            </div>
        </div>
    </div>

    <div class="card-body">
        <!-- Filter Row -->
        <div class="row mb-4">
            <div class="col-md-3">
                <label class="form-label">Week</label>
                <select class="form-select" id="weekSelect"></select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Term</label>
                <select class="form-select" id="termSelect">
                    <option value="1">Term 1</option>
                    <option value="2">Term 2</option>
                    <option value="3">Term 3</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Meal Type</label>
                <select class="form-select" id="mealTypeFilter">
                    <option value="">All Meals</option>
                    <option value="breakfast">Breakfast</option>
                    <option value="lunch">Lunch</option>
                    <option value="supper">Supper</option>
                    <option value="snack">Snacks</option>
                </select>
            </div>
        </div>

        <!-- Weekly Menu Calendar -->
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Weekly Menu <span id="weekRange" class="text-muted"></span></h5>
                <div class="table-responsive">
                    <table class="table table-bordered" id="weeklyMenuTable">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 12%;">Day</th>
                                <th style="width: 22%;">Breakfast</th>
                                <th style="width: 22%;">Lunch</th>
                                <th style="width: 22%;">Supper</th>
                                <th style="width: 22%;">Snacks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="fw-bold">Monday</td>
                                <td id="mon_breakfast" class="menu-cell" data-day="monday" data-meal="breakfast">
                                    <small class="text-muted">Click to add</small>
                                </td>
                                <td id="mon_lunch" class="menu-cell" data-day="monday" data-meal="lunch">
                                    <small class="text-muted">Click to add</small>
                                </td>
                                <td id="mon_supper" class="menu-cell" data-day="monday" data-meal="supper">
                                    <small class="text-muted">Click to add</small>
                                </td>
                                <td id="mon_snack" class="menu-cell" data-day="monday" data-meal="snack">
                                    <small class="text-muted">Click to add</small>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Tuesday</td>
                                <td id="tue_breakfast" class="menu-cell" data-day="tuesday" data-meal="breakfast">
                                    <small class="text-muted">Click to add</small>
                                </td>
                                <td id="tue_lunch" class="menu-cell" data-day="tuesday" data-meal="lunch">
                                    <small class="text-muted">Click to add</small>
                                </td>
                                <td id="tue_supper" class="menu-cell" data-day="tuesday" data-meal="supper">
                                    <small class="text-muted">Click to add</small>
                                </td>
                                <td id="tue_snack" class="menu-cell" data-day="tuesday" data-meal="snack">
                                    <small class="text-muted">Click to add</small>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Wednesday</td>
                                <td id="wed_breakfast" class="menu-cell" data-day="wednesday" data-meal="breakfast">
                                    <small class="text-muted">Click to add</small>
                                </td>
                                <td id="wed_lunch" class="menu-cell" data-day="wednesday" data-meal="lunch">
                                    <small class="text-muted">Click to add</small>
                                </td>
                                <td id="wed_supper" class="menu-cell" data-day="wednesday" data-meal="supper">
                                    <small class="text-muted">Click to add</small>
                                </td>
                                <td id="wed_snack" class="menu-cell" data-day="wednesday" data-meal="snack">
                                    <small class="text-muted">Click to add</small>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Thursday</td>
                                <td id="thu_breakfast" class="menu-cell" data-day="thursday" data-meal="breakfast">
                                    <small class="text-muted">Click to add</small>
                                </td>
                                <td id="thu_lunch" class="menu-cell" data-day="thursday" data-meal="lunch">
                                    <small class="text-muted">Click to add</small>
                                </td>
                                <td id="thu_supper" class="menu-cell" data-day="thursday" data-meal="supper">
                                    <small class="text-muted">Click to add</small>
                                </td>
                                <td id="thu_snack" class="menu-cell" data-day="thursday" data-meal="snack">
                                    <small class="text-muted">Click to add</small>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Friday</td>
                                <td id="fri_breakfast" class="menu-cell" data-day="friday" data-meal="breakfast">
                                    <small class="text-muted">Click to add</small>
                                </td>
                                <td id="fri_lunch" class="menu-cell" data-day="friday" data-meal="lunch">
                                    <small class="text-muted">Click to add</small>
                                </td>
                                <td id="fri_supper" class="menu-cell" data-day="friday" data-meal="supper">
                                    <small class="text-muted">Click to add</small>
                                </td>
                                <td id="fri_snack" class="menu-cell" data-day="friday" data-meal="snack">
                                    <small class="text-muted">Click to add</small>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Saturday</td>
                                <td id="sat_breakfast" class="menu-cell" data-day="saturday" data-meal="breakfast">
                                    <small class="text-muted">Click to add</small>
                                </td>
                                <td id="sat_lunch" class="menu-cell" data-day="saturday" data-meal="lunch">
                                    <small class="text-muted">Click to add</small>
                                </td>
                                <td id="sat_supper" class="menu-cell" data-day="saturday" data-meal="supper">
                                    <small class="text-muted">Click to add</small>
                                </td>
                                <td id="sat_snack" class="menu-cell" data-day="saturday" data-meal="snack">
                                    <small class="text-muted">Click to add</small>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Sunday</td>
                                <td id="sun_breakfast" class="menu-cell" data-day="sunday" data-meal="breakfast">
                                    <small class="text-muted">Click to add</small>
                                </td>
                                <td id="sun_lunch" class="menu-cell" data-day="sunday" data-meal="lunch">
                                    <small class="text-muted">Click to add</small>
                                </td>
                                <td id="sun_supper" class="menu-cell" data-day="sunday" data-meal="supper">
                                    <small class="text-muted">Click to add</small>
                                </td>
                                <td id="sun_snack" class="menu-cell" data-day="sunday" data-meal="snack">
                                    <small class="text-muted">Click to add</small>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Nutritional Requirements -->
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Weekly Nutritional Overview</h5>
                <div class="row">
                    <div class="col-md-6">
                        <canvas id="nutritionChart"></canvas>
                    </div>
                    <div class="col-md-6">
                        <ul class="list-group">
                            <li class="list-group-item d-flex justify-content-between">
                                <span>Average Calories/Day</span>
                                <span class="badge bg-primary" id="avgCalories">0 kcal</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span>Average Protein/Day</span>
                                <span class="badge bg-success" id="avgProtein">0 g</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span>Cost Per Student/Week</span>
                                <span class="badge bg-info" id="costPerStudent">KES 0</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Menu Modal -->
<div class="modal fade" id="menuModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="menuModalTitle">Add Menu Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="menuForm">
                    <input type="hidden" id="menuId">
                    <input type="hidden" id="menuDay">
                    <input type="hidden" id="menuMealType">
                    <div class="mb-3">
                        <label class="form-label">Day</label>
                        <input type="text" class="form-control" id="dayDisplay" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Meal Type</label>
                        <input type="text" class="form-control" id="mealDisplay" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Menu Items*</label>
                        <textarea class="form-control" id="menuItems" rows="3" required
                            placeholder="e.g., Ugali, Beef Stew, Cabbage, Fruit"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Estimated Cost (KES)</label>
                        <input type="number" class="form-control" id="estimatedCost" step="0.01">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" id="menuNotes" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveMenuBtn">Save Menu</button>
            </div>
        </div>
    </div>
</div>

<style>
    .menu-cell {
        cursor: pointer;
        min-height: 60px;
        vertical-align: top;
        padding: 8px;
    }

    .menu-cell:hover {
        background-color: #f8f9fa;
    }

    .menu-content {
        font-size: 0.9rem;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // TODO: Implement menuPlanningController in js/pages/menu_planning.js
        console.log('Menu Planning page loaded');
    });
</script>