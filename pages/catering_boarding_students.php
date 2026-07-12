<?php
/**
 * Catering Boarding Students Page
 * Daily boarding student meal counts, special diets, food quantities, and catering planning
 * Embedded in app_layout.php
 */

// Ensure $appBase is available for script loading
if (!isset($appBase)) {
    $appBase = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
    if ($appBase === '.' || $appBase === '/') {
        $appBase = '';
    }
}
?>

<div class="container-fluid py-4" id="cateringBoardingPage">

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-warning text-dark">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h4 class="mb-0">
                        <i class="fas fa-utensils me-2"></i>
                        Boarding Meal Planning
                    </h4>
                    <small id="scopeSubtitle">Daily boarding student meal counts, special diets, food quantities, and catering planning</small>
                </div>
                <div class="btn-group">
                    <button class="btn btn-dark btn-sm" id="refreshBtn">
                        <i class="bi bi-arrow-clockwise"></i> Refresh
                    </button>
                    <button class="btn btn-outline-dark btn-sm" id="planTodayBtn">
                        <i class="bi bi-calendar-check me-1"></i> Plan Today's Meals
                    </button>
                    <button class="btn btn-outline-dark btn-sm" id="printMealCountBtn">
                        <i class="bi bi-printer me-1"></i> Print Meal Count
                    </button>
                    <button class="btn btn-outline-dark btn-sm" id="exportMealSheetBtn">
                        <i class="bi bi-download me-1"></i> Export Meal Sheet
                    </button>
                    <button class="btn btn-dark btn-sm" id="foodStoreRequisitionBtn">
                        <i class="bi bi-box-seam me-1"></i> Food Store Requisition
                    </button>
                </div>
            </div>
        </div>

        <div class="card-body">

            <!-- Filters -->
            <div class="row g-3 mb-4">
                <div class="col-xl-2 col-md-4">
                    <label class="form-label fw-semibold">Date</label>
                    <input type="date" class="form-control" id="dateFilter">
                </div>

                <div class="col-xl-2 col-md-4">
                    <label class="form-label fw-semibold">Meal</label>
                    <select class="form-select" id="mealFilter">
                        <option value="">All Meals</option>
                        <option value="breakfast">Breakfast</option>
                        <option value="lunch">Lunch</option>
                        <option value="supper">Supper</option>
                        <option value="snack">Snack</option>
                    </select>
                </div>

                <div class="col-xl-2 col-md-4">
                    <label class="form-label fw-semibold">Class</label>
                    <select class="form-select" id="classFilter">
                        <option value="">All Classes</option>
                    </select>
                </div>

                <div class="col-xl-2 col-md-4">
                    <label class="form-label fw-semibold">Stream</label>
                    <select class="form-select" id="streamFilter">
                        <option value="">All Streams</option>
                    </select>
                </div>

                <div class="col-xl-2 col-md-4">
                    <label class="form-label fw-semibold">Gender</label>
                    <select class="form-select" id="genderFilter">
                        <option value="">All</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                    </select>
                </div>

                <div class="col-xl-2 col-md-4">
                    <label class="form-label fw-semibold">Dormitory</label>
                    <select class="form-select" id="dormitoryFilter">
                        <option value="">All Dormitories</option>
                    </select>
                </div>

                <div class="col-xl-2 col-md-4">
                    <label class="form-label fw-semibold">Boarding Status</label>
                    <select class="form-select" id="boardingStatusFilter">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="on_leave">On Leave</option>
                        <option value="sick">Sick</option>
                        <option value="suspended">Suspended</option>
                        <option value="checked_out">Checked Out</option>
                    </select>
                </div>

                <div class="col-xl-2 col-md-4">
                    <label class="form-label fw-semibold">Diet Type</label>
                    <select class="form-select" id="dietTypeFilter">
                        <option value="">All</option>
                        <option value="normal">Normal</option>
                        <option value="vegetarian">Vegetarian</option>
                        <option value="diabetic">Diabetic</option>
                        <option value="allergy">Allergy</option>
                        <option value="medical">Medical</option>
                        <option value="religious">Religious</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <div class="col-xl-3 col-md-6">
                    <label class="form-label fw-semibold">Search</label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-search"></i>
                        </span>
                        <input type="text" class="form-control" id="searchBox"
                               placeholder="Search by student name, admission number, or UPI">
                    </div>
                </div>

                <div class="col-xl-2 col-md-4 d-flex align-items-end">
                    <button class="btn btn-warning w-100" id="applyFiltersBtn">
                        <i class="fas fa-filter me-1"></i> Apply
                    </button>
                </div>

                <div class="col-xl-2 col-md-4 d-flex align-items-end">
                    <button class="btn btn-outline-secondary w-100" id="resetFiltersBtn">
                        <i class="fas fa-undo me-1"></i> Reset
                    </button>
                </div>
            </div>

            <!-- Summary cards -->
            <div class="row g-3 mb-4">
                <div class="col-xl-2 col-md-4">
                    <div class="card border-0 bg-light h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle bg-warning text-dark p-3">
                                    <i class="fas fa-bed"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Total Boarders</small>
                                    <h4 class="mb-0" id="totalBoarders">0</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4">
                    <div class="card border-0 bg-light h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle bg-success text-white p-3">
                                    <i class="fas fa-sun"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Breakfast</small>
                                    <h4 class="mb-0" id="breakfastCount">0</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4">
                    <div class="card border-0 bg-light h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle bg-info text-white p-3">
                                    <i class="fas fa-cloud-sun"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Lunch</small>
                                    <h4 class="mb-0" id="lunchCount">0</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4">
                    <div class="card border-0 bg-light h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle bg-primary text-white p-3">
                                    <i class="fas fa-moon"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Supper</small>
                                    <h4 class="mb-0" id="supperCount">0</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4">
                    <div class="card border-0 bg-light h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle bg-danger text-white p-3">
                                    <i class="fas fa-utensils"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Special Diet</small>
                                    <h4 class="mb-0" id="specialDietCount">0</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4">
                    <div class="card border-0 bg-light h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle bg-secondary text-white p-3">
                                    <i class="fas fa-user-clock"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Absent/Leave</small>
                                    <h4 class="mb-0" id="absentLeaveCount">0</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4">
                    <div class="card border-0 bg-light h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle bg-danger text-white p-3">
                                    <i class="fas fa-procedures"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Sick Bay</small>
                                    <h4 class="mb-0" id="sickBayCount">0</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4">
                    <div class="card border-0 bg-light h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle bg-warning text-dark p-3">
                                    <i class="fas fa-box-open"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Store Items</small>
                                    <h4 class="mb-0" id="storeItemsCount">0</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- States -->
            <div id="studentsLoading" class="alert alert-info d-none">
                <i class="fas fa-spinner fa-spin me-2"></i> Loading boarding students...
            </div>

            <div id="studentsError" class="alert alert-danger d-none"></div>

            <div id="studentsForbidden" class="alert alert-warning d-none">
                <i class="fas fa-exclamation-triangle me-2"></i> You do not have permission to access catering/boarding data.
            </div>

            <div id="studentsEmpty" class="alert alert-warning d-none">
                <i class="fas fa-info-circle me-2"></i> No boarding students found for the selected filters.
            </div>

            <!-- Breakdown Section -->
            <div class="card border-0 shadow-sm mb-4" id="breakdownCard">
                <div class="card-header bg-white">
                    <strong>
                        <i class="fas fa-chart-pie me-2 text-warning"></i>
                        Meal Count Breakdown
                    </strong>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <h6 class="fw-semibold">By Class</h6>
                            <div id="breakdownByClass">
                                <p class="text-muted">Loading...</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6 class="fw-semibold">By Diet Type</h6>
                            <div id="breakdownByDiet">
                                <p class="text-muted">Loading...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Table -->
            <div class="card border-0 shadow-sm" id="studentsCard">
                <div class="card-header bg-white">
                    <strong>
                        <i class="fas fa-list me-2 text-warning"></i>
                        Boarding Meal Students
                    </strong>
                </div>

                <div class="card-body table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Adm No</th>
                                <th>Student Name</th>
                                <th>Class</th>
                                <th>Stream</th>
                                <th>Gender</th>
                                <th>Dormitory</th>
                                <th>Boarding Status</th>
                                <th>Breakfast</th>
                                <th>Lunch</th>
                                <th>Supper</th>
                                <th>Diet Type</th>
                                <th>Restriction</th>
                                <th>Today's Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="studentsTableBody">
                            <tr>
                                <td class="text-center text-muted">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Meal Profile Modal -->
<div class="modal fade" id="mealProfileModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content border-0 shadow">

            <div class="modal-header bg-warning text-dark">
                <div>
                    <h5 class="modal-title mb-0">
                        <i class="fas fa-utensils me-2"></i>
                        Meal Profile
                    </h5>
                    <small id="modalSubtitle">Student meal planning details</small>
                </div>
                <button type="button" class="btn-close btn-close-dark" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div id="modalLoading" class="alert alert-info d-none">
                    <i class="fas fa-spinner fa-spin me-2"></i> Loading meal profile...
                </div>

                <div id="modalError" class="alert alert-danger d-none"></div>

                <div id="modalMealContent">
                    <!-- Meal profile details will be rendered here -->
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <button class="btn btn-warning" id="markNotEatingBtn">
                    <i class="bi bi-x-circle me-1"></i> Mark Not Eating
                </button>
                <button class="btn btn-info" id="addDietNoteBtn">
                    <i class="bi bi-plus-circle me-1"></i> Add Diet Note
                </button>
            </div>

        </div>
    </div>
</div>

<!-- Meal Planning Modal -->
<div class="modal fade" id="mealPlanModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">

            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title mb-0">
                    <i class="fas fa-calendar-check me-2"></i>
                    Plan Today's Meals
                </h5>
                <button type="button" class="btn-close btn-close-dark" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <form id="mealPlanForm">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Date</label>
                        <input type="date" class="form-control" id="planDate" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Meal Type</label>
                        <select class="form-select" id="planMealType" required>
                            <option value="breakfast">Breakfast</option>
                            <option value="lunch">Lunch</option>
                            <option value="supper">Supper</option>
                            <option value="snack">Snack</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Menu Item</label>
                        <input type="text" class="form-control" id="planMenuItem" placeholder="e.g., Ugali, Rice, Chapati">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Expected Count</label>
                            <input type="number" class="form-control" id="planExpectedCount" min="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Special Diet Count</label>
                            <input type="number" class="form-control" id="planSpecialDietCount" min="0">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Portion Estimate</label>
                        <input type="text" class="form-control" id="planPortion" placeholder="e.g., 300g per student">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Notes</label>
                        <textarea class="form-control" id="planNotes" rows="2"></textarea>
                    </div>
                </form>
            </div>

            <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-warning" id="saveMealPlanBtn">
                    <i class="bi bi-check-circle me-1"></i> Save Plan
                </button>
            </div>

        </div>
    </div>
</div>

<!-- Food Store Requisition Section -->
<div class="card border-0 shadow-sm mb-4" id="foodStoreCard">
    <div class="card-header bg-white">
        <strong>
            <i class="fas fa-box-open me-2 text-warning"></i>
            Food Store Requisition
        </strong>
    </div>
    <div class="card-body">
        <div id="foodStoreLoading" class="alert alert-info d-none">
            <i class="fas fa-spinner fa-spin me-2"></i> Loading food store data...
        </div>
        <div id="foodStoreContent">
            <p class="text-muted">Select date and meal to view requisition requirements.</p>
        </div>
    </div>
</div>

<script src="<?php echo $appBase; ?>/js/pages/catering_boarding_students.js?v=<?php echo time(); ?>"></script>
