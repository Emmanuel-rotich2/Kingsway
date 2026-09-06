<?php
/**
 * Manage Terms
 *
 * Purpose: View and manage academic terms and their dates (single terms page,
 * absorbed the former term_dates page). Terms are created for a year by Year
 * Rollover; this page lets you adjust dates/status or add a missing term.
 * Features:
 * - Data display and filtering by academic year
 * - Search, status filter and export
 */
?>

<div>
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1"><i class="bi bi-list-ul-ol me-2"></i>Manage Terms</h4>
                    <p class="text-muted mb-0">View and manage academic terms and dates</p>
                </div>
                <button class="btn btn-primary" onclick="ManageTermsController.showAddModal()"><i class="bi bi-plus-lg me-1"></i> Add New</button>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center">
                <div class="rounded-circle bg-primary bg-opacity-10 p-3 me-3"><i class="bi bi-list-ul text-primary fa-lg"></i></div>
                <div><h6 class="text-muted mb-1">Total Terms</h6><h4 class="mb-0" id="statTotal">0</h4></div>
            </div></div></div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center">
                <div class="rounded-circle bg-success bg-opacity-10 p-3 me-3"><i class="bi bi-play-circle text-success fa-lg"></i></div>
                <div><h6 class="text-muted mb-1">Active Term</h6><h4 class="mb-0" id="statActive">0</h4></div>
            </div></div></div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center">
                <div class="rounded-circle bg-info bg-opacity-10 p-3 me-3"><i class="bi bi-check-lg-circle text-info fa-lg"></i></div>
                <div><h6 class="text-muted mb-1">Completed</h6><h4 class="mb-0" id="statCompleted">0</h4></div>
            </div></div></div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center">
                <div class="rounded-circle bg-warning bg-opacity-10 p-3 me-3"><i class="bi bi-skip-forward text-warning fa-lg"></i></div>
                <div><h6 class="text-muted mb-1">Upcoming</h6><h4 class="mb-0" id="statUpcoming">0</h4></div>
            </div></div></div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3"><input type="text" class="form-control" id="searchInput" placeholder="Search..."></div>
                <div class="col-md-3"><select class="form-select" id="yearFilter"><option value="">All Academic Years</option></select></div>
                <div class="col-md-2"><select class="form-select" id="filterSelect"><option value="">All Statuses</option></select></div>
                <div class="col-md-2"><input type="date" class="form-control" id="dateFilter"></div>
                <div class="col-md-2"><button class="btn btn-outline-secondary w-100" onclick="ManageTermsController.refresh()"><i class="bi bi-arrow-clockwise"></i></button></div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="bi bi-table me-2"></i>Manage Terms</h6>
            <button class="btn btn-sm btn-outline-success" onclick="ManageTermsController.exportCSV()"><i class="bi bi-file-csv me-1"></i> Export</button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="dataTable">
                    <thead class="table-light"><tr><th scope="col">#</th><th scope="col">Term Name</th><th scope="col">Academic Year</th><th scope="col">Start Date</th><th scope="col">End Date</th><th scope="col">Weeks</th><th scope="col">Status</th><th scope="col">Actions</th></tr></thead>
                    <tbody><tr><td colspan="8" class="text-center text-muted py-4">Loading...</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="formModal" tabindex="-1"><div class="modal-dialog modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="formModalTitle"><i class="bi bi-list-ul-ol me-2"></i>Add Record</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><form id="recordForm"><input type="hidden" id="recordId">
        <div class="mb-3"><label class="form-label">Term Name <span class="text-danger">*</span></label><input type="text" class="form-control" id="recordName" placeholder="e.g., Term 1" required></div>
        <div class="mb-3"><label class="form-label">Academic Year</label><select class="form-select" id="recordYear"></select></div>
        <div class="row">
            <div class="col-md-6 mb-3"><label class="form-label">Start Date</label><input type="date" class="form-control" id="recordStartDate"></div>
            <div class="col-md-6 mb-3"><label class="form-label">End Date</label><input type="date" class="form-control" id="recordEndDate"></div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3"><label class="form-label">Half-Term Start <span class="text-muted small">(blank = no half-term)</span></label><input type="date" class="form-control" id="recordHalfTermStart"></div>
            <div class="col-md-6 mb-3"><label class="form-label">Half-Term End</label><input type="date" class="form-control" id="recordHalfTermEnd"></div>
        </div>
        <div class="mb-3"><label class="form-label">Status</label><select class="form-select" id="recordStatus"><option value="upcoming">Upcoming</option><option value="current">Current</option><option value="completed">Completed</option></select></div>
    </form></div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-primary" onclick="ManageTermsController.saveRecord()"><i class="bi bi-check-lg me-1"></i> Save</button></div>
</div></div></div>

<script src="<?= $appBase ?>/js/pages/manage_terms.js?v=<?php echo time(); ?>"></script>
