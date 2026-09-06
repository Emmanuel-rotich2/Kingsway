<?php
$appBase = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
/**
 * Academic Years Registry Page
 *
 * Purpose: View and manage academic years and their terms.
 * Creation of new years + terms + calendar happens exclusively in
 * Year Transition (Rollover) — this page does NOT create years or terms.
 * Features:
 * - View all years (current + previous)
 * - Edit year metadata (name, start/end dates)
 * - View each year's terms (read-only)
 * - Export year list
 */
?>

<div>
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1"><i class="bi bi-calendar me-2"></i>Academic Years</h4>
                    <p class="text-muted mb-0">View and manage academic years. New years are created via Year Transition (Rollover).</p>
                </div>
                <button class="btn btn-outline-success" id="exportYearsBtn">
                    <i class="bi bi-download me-1"></i> Export
                </button>
            </div>
        </div>
    </div>

    <!-- Current Academic Year Card -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card border-primary">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-star me-2"></i>Current Academic Year</h5>
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
                    <h5 class="mb-0"><i class="bi bi-clock me-2"></i>Current Term</h5>
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
            <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i>All Academic Years</h5>
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
                            <th scope="col">Year</th>
                            <th scope="col">Start Date</th>
                            <th scope="col">End Date</th>
                            <th scope="col">Terms</th>
                            <th scope="col">Status</th>
                            <th scope="col">Actions</th>
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

<!-- Edit Academic Year Modal -->
<div class="modal fade" id="editYearModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Academic Year</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addAcademicYearForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Year Name</label>
                        <input type="text" class="form-control" name="year_name" required>
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
                    <div class="alert alert-info py-2 small mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        Term dates and structure are managed in Manage Terms / Year Rollover.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php asset_script($appBase, 'js/pages/academic_years.js'); ?>
