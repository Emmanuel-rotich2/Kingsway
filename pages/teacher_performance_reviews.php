<?php
/**
 * Teacher Performance Reviews Page - Pure UI/UX Layout
 * Controller: teacher_performance_reviews.js
 * Authentication: JWT via api.js + backend middleware
 * Role-based access: JavaScript AuthContext + permission system
 */
?>
<div class="teacher-performance-reviews-container">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1"><i class="bi bi-star-half-alt me-2"></i>Teacher Performance Reviews</h4>
                    <p class="text-muted mb-0">View and manage teacher performance review records</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-primary bg-opacity-10 p-3 me-3">
                            <i class="bi bi-clipboard-data text-primary fa-lg"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Total Reviews</h6>
                            <h4 class="mb-0" id="statTotal">0</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-warning bg-opacity-10 p-3 me-3">
                            <i class="bi bi-star text-warning fa-lg"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Avg Rating</h6>
                            <h4 class="mb-0" id="statAvg">0</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-success bg-opacity-10 p-3 me-3">
                            <i class="bi bi-award text-success fa-lg"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Excellent (4+)</h6>
                            <h4 class="mb-0" id="statExcellent">0</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-danger bg-opacity-10 p-3 me-3">
                            <i class="bi bi-exclamation-triangle text-danger fa-lg"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Needs Improvement</h6>
                            <h4 class="mb-0" id="statLow">0</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <input type="text" class="form-control" id="searchInput" placeholder="Search...">
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="filterSelect">
                        <option value="">All</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="date" class="form-control" id="dateFilter">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-outline-secondary w-100" data-kw-onclick="TeacherPerformanceReviewsController.refresh()">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="bi bi-table me-2"></i>Teacher Performance Reviews</h6>
            <button class="btn btn-sm btn-outline-success" data-kw-onclick="TeacherPerformanceReviewsController.exportCSV()" data-permission-module="staff" data-permission-action="export">
                <i class="bi bi-file-csv me-1"></i> Export
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="dataTable">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">#</th>
                            <th scope="col">Teacher</th>
                            <th scope="col">Subject</th>
                            <th scope="col">Review Date</th>
                            <th scope="col">Reviewer</th>
                            <th scope="col">Rating</th>
                            <th scope="col">Category</th>
                            <th scope="col">Remarks</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td colspan="9" class="text-center text-muted py-4">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php asset_script($appBase, 'js/pages/teacher_performance_reviews.js'); ?>