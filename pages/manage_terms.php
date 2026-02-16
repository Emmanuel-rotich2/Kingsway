<?php
/**
 * Manage Terms
 *
 * Purpose: Create and manage academic terms
 * Features:
 * - Data display and filtering
 * - Search and export
 */
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1"><i class="fas fa-list-ol me-2"></i>Manage Terms</h4>
                    <p class="text-muted mb-0">Create and manage academic terms</p>
                </div>
                <button class="btn btn-primary" onclick="ManageTermsController.showAddModal()"><i class="fas fa-plus me-1"></i> Add New</button>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center">
                <div class="rounded-circle bg-primary bg-opacity-10 p-3 me-3"><i class="fas fa-list text-primary fa-lg"></i></div>
                <div><h6 class="text-muted mb-1">Total Terms</h6><h4 class="mb-0" id="statTotal">0</h4></div>
            </div></div></div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center">
                <div class="rounded-circle bg-success bg-opacity-10 p-3 me-3"><i class="fas fa-play-circle text-success fa-lg"></i></div>
                <div><h6 class="text-muted mb-1">Active Term</h6><h4 class="mb-0" id="statActive">0</h4></div>
            </div></div></div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center">
                <div class="rounded-circle bg-info bg-opacity-10 p-3 me-3"><i class="fas fa-check-circle text-info fa-lg"></i></div>
                <div><h6 class="text-muted mb-1">Completed</h6><h4 class="mb-0" id="statCompleted">0</h4></div>
            </div></div></div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center">
                <div class="rounded-circle bg-warning bg-opacity-10 p-3 me-3"><i class="fas fa-forward text-warning fa-lg"></i></div>
                <div><h6 class="text-muted mb-1">Upcoming</h6><h4 class="mb-0" id="statUpcoming">0</h4></div>
            </div></div></div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4"><input type="text" class="form-control" id="searchInput" placeholder="Search..."></div>
                <div class="col-md-3"><select class="form-select" id="filterSelect"><option value="">All</option></select></div>
                <div class="col-md-3"><input type="date" class="form-control" id="dateFilter"></div>
                <div class="col-md-2"><button class="btn btn-outline-secondary w-100" onclick="ManageTermsController.refresh()"><i class="fas fa-sync-alt"></i></button></div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="fas fa-table me-2"></i>Manage Terms</h6>
            <button class="btn btn-sm btn-outline-success" onclick="ManageTermsController.exportCSV()"><i class="fas fa-file-csv me-1"></i> Export</button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="dataTable">
                    <thead class="table-light"><tr><th>#</th><th>Term Name</th><th>Academic Year</th><th>Start Date</th><th>End Date</th><th>Weeks</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody><tr><td colspan="8" class="text-center text-muted py-4">Loading...</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="formModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="formModalTitle"><i class="fas fa-list-ol me-2"></i>Add Record</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><form id="recordForm"><input type="hidden" id="recordId">
        <div class="mb-3"><label class="form-label">Name <span class="text-danger">*</span></label><input type="text" class="form-control" id="recordName" required></div>
        <div class="mb-3"><label class="form-label">Description</label><textarea class="form-control" id="recordDescription" rows="3"></textarea></div>
        <div class="mb-3"><label class="form-label">Date</label><input type="date" class="form-control" id="recordDate"></div>
        <div class="mb-3"><label class="form-label">Status</label><select class="form-select" id="recordStatus"><option value="active">Active</option><option value="inactive">Inactive</option><option value="pending">Pending</option></select></div>
    </form></div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-primary" onclick="ManageTermsController.saveRecord()"><i class="fas fa-save me-1"></i> Save</button></div>
</div></div></div>

<script src="/Kingsway/js/pages/manage_terms.js?v=<?php echo time(); ?>"></script>
