<?php
/**
 * Academic Years Management Page
 * 
 * Purpose: Manage academic years, terms, and school calendar
 * Features:
 * - View and manage academic years
 * - Configure terms within each year
 * - Set term dates and holidays
 * - Track year history
 */
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1"><i class="fas fa-calendar me-2"></i>Academic Years</h4>
                    <p class="text-muted mb-0">Manage academic years, terms, and school calendar</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAcademicYearModal">
                    <i class="fas fa-plus me-1"></i> Add Academic Year
                </button>
            </div>
        </div>
    </div>

    <!-- Current Academic Year Card -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card border-primary">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-star me-2"></i>Current Academic Year</h5>
                </div>
                <div class="card-body" id="currentYearInfo">
                    <div class="text-center py-3">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Current Term</h5>
                </div>
                <div class="card-body" id="currentTermInfo">
                    <div class="text-center py-3">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Academic Years Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-list me-2"></i>All Academic Years</h5>
            <div class="btn-group btn-group-sm">
                <button class="btn btn-outline-secondary active" data-view="all">All</button>
                <button class="btn btn-outline-secondary" data-view="active">Active</button>
                <button class="btn btn-outline-secondary" data-view="past">Past</button>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="academicYearsTable">
                    <thead>
                        <tr>
                            <th>Year</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Terms</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="6" class="text-center">
                                <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                Loading academic years...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Academic Year Modal -->
<div class="modal fade" id="addAcademicYearModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Academic Year</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addAcademicYearForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Year Name</label>
                        <input type="text" class="form-control" name="year_name" placeholder="e.g., 2026" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Start Date</label>
                            <input type="date" class="form-control" name="start_date" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" name="end_date" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Number of Terms</label>
                        <select class="form-select" name="num_terms" required>
                            <option value="3">3 Terms</option>
                            <option value="2">2 Semesters</option>
                            <option value="4">4 Quarters</option>
                        </select>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="set_current" id="setCurrentYear">
                        <label class="form-check-label" for="setCurrentYear">Set as current academic year</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Academic Year</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="js/pages/academic_years.js"></script>