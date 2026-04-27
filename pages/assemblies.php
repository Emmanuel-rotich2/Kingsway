<?php
/**
 * Assemblies
 *
 * Purpose: Manage assembly schedule and themes
 * Features:
 * - Data display and filtering
 * - Search and export
 */
?>

<div>
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1"><i class="fas fa-bullhorn me-2"></i>Assemblies</h4>
                    <p class="text-muted mb-0">Manage assembly schedule and themes</p>
                </div>
                <button class="btn btn-primary" onclick="AssembliesController.showAddModal()"><i class="fas fa-plus me-1"></i> Add New</button>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center">
                <div class="rounded-circle bg-primary bg-opacity-10 p-3 me-3"><i class="fas fa-bullhorn text-primary fa-lg"></i></div>
                <div><h6 class="text-muted mb-1">Total Assemblies</h6><h4 class="mb-0" id="statTotal">0</h4></div>
            </div></div></div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center">
                <div class="rounded-circle bg-success bg-opacity-10 p-3 me-3"><i class="fas fa-calendar text-success fa-lg"></i></div>
                <div><h6 class="text-muted mb-1">This Term</h6><h4 class="mb-0" id="statTerm">0</h4></div>
            </div></div></div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center">
                <div class="rounded-circle bg-warning bg-opacity-10 p-3 me-3"><i class="fas fa-clock text-warning fa-lg"></i></div>
                <div><h6 class="text-muted mb-1">Upcoming</h6><h4 class="mb-0" id="statUpcoming">0</h4></div>
            </div></div></div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center">
                <div class="rounded-circle bg-info bg-opacity-10 p-3 me-3"><i class="fas fa-microphone text-info fa-lg"></i></div>
                <div><h6 class="text-muted mb-1">Speakers</h6><h4 class="mb-0" id="statSpeakers">0</h4></div>
            </div></div></div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4"><input type="text" class="form-control" id="searchInput" placeholder="Search..."></div>
                <div class="col-md-3"><select class="form-select" id="filterSelect"><option value="">All</option></select></div>
                <div class="col-md-3"><input type="date" class="form-control" id="dateFilter"></div>
                <div class="col-md-2"><button class="btn btn-outline-secondary w-100" onclick="AssembliesController.refresh()"><i class="fas fa-sync-alt"></i></button></div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="fas fa-table me-2"></i>Assemblies</h6>
            <button class="btn btn-sm btn-outline-success" onclick="AssembliesController.exportCSV()"><i class="fas fa-file-csv me-1"></i> Export</button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="dataTable">
                    <thead class="table-light"><tr><th>#</th><th>Date</th><th>Day</th><th>Time</th><th>Theme/Topic</th><th>Speaker</th><th>Class Responsible</th><th>Type</th><th>Actions</th></tr></thead>
                    <tbody><tr><td colspan="9" class="text-center text-muted py-4">Loading...</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="formModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="formModalTitle"><i class="fas fa-bullhorn me-2"></i>Add Record</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><form id="recordForm"><input type="hidden" id="recordId">
        <div class="mb-3"><label class="form-label">Name <span class="text-danger">*</span></label><input type="text" class="form-control" id="recordName" required></div>
        <div class="mb-3"><label class="form-label">Description</label><textarea class="form-control" id="recordDescription" rows="3"></textarea></div>
        <div class="mb-3"><label class="form-label">Date</label><input type="date" class="form-control" id="recordDate"></div>
        <div class="mb-3"><label class="form-label">Status</label><select class="form-select" id="recordStatus"><option value="active">Active</option><option value="inactive">Inactive</option><option value="pending">Pending</option></select></div>
    </form></div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-primary" onclick="AssembliesController.saveRecord()"><i class="fas fa-save me-1"></i> Save</button></div>
</div></div></div>

<script src="<?= $appBase ?>/js/pages/assemblies.js?v=<?php echo time(); ?>"></script>
