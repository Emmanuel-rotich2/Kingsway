<?php
/**
 * PTA Management
 *
 * Purpose: Manage PTA members and meetings
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
                    <h4 class="mb-1"><i class="bi bi-people-cog me-2"></i>PTA Management</h4>
                    <p class="text-muted mb-0">Manage PTA members and meetings</p>
                </div>
                <button class="btn btn-primary" onclick="PTAManagementController.showAddModal()"><i class="bi bi-plus-lg me-1"></i> Add New</button>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center">
                <div class="rounded-circle bg-primary bg-opacity-10 p-3 me-3"><i class="bi bi-people text-primary fa-lg"></i></div>
                <div><h6 class="text-muted mb-1">Total Members</h6><h4 class="mb-0" id="statMembers">0</h4></div>
            </div></div></div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center">
                <div class="rounded-circle bg-success bg-opacity-10 p-3 me-3"><i class="bi bi-handshake text-success fa-lg"></i></div>
                <div><h6 class="text-muted mb-1">Meetings Held</h6><h4 class="mb-0" id="statMeetings">0</h4></div>
            </div></div></div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center">
                <div class="rounded-circle bg-warning bg-opacity-10 p-3 me-3"><i class="bi bi-calendar-check text-warning fa-lg"></i></div>
                <div><h6 class="text-muted mb-1">Upcoming Meetings</h6><h4 class="mb-0" id="statUpcoming">0</h4></div>
            </div></div></div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center">
                <div class="rounded-circle bg-info bg-opacity-10 p-3 me-3"><i class="bi bi-person-check text-info fa-lg"></i></div>
                <div><h6 class="text-muted mb-1">Active Members</h6><h4 class="mb-0" id="statActive">0</h4></div>
            </div></div></div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4"><input type="text" class="form-control" id="searchInput" placeholder="Search..."></div>
                <div class="col-md-3"><select class="form-select" id="filterSelect"><option value="">All</option></select></div>
                <div class="col-md-3"><input type="date" class="form-control" id="dateFilter"></div>
                <div class="col-md-2"><button class="btn btn-outline-secondary w-100" onclick="PTAManagementController.refresh()"><i class="bi bi-arrow-clockwise"></i></button></div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="bi bi-table me-2"></i>PTA Management</h6>
            <button class="btn btn-sm btn-outline-success" onclick="PTAManagementController.exportCSV()"><i class="bi bi-file-csv me-1"></i> Export</button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="dataTable">
                    <thead class="table-light"><tr><th scope="col">#</th><th scope="col">Name</th><th scope="col">Role</th><th scope="col">Phone</th><th scope="col">Email</th><th scope="col">Status</th><th scope="col">Actions</th></tr></thead>
                    <tbody><tr><td colspan="7" class="text-center text-muted py-4">Loading...</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="formModal" tabindex="-1"><div class="modal-dialog modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="formModalTitle"><i class="bi bi-people-cog me-2"></i>Add Record</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><form id="recordForm"><input type="hidden" id="recordId">
        <div class="mb-3"><label class="form-label">Parent / Guardian <span class="text-danger">*</span></label><select class="form-select" id="recordParent" required><option value="">Loading parents...</option></select></div>
        <div class="mb-3"><label class="form-label">PTA Role</label><select class="form-select" id="recordRole"><option>Member</option><option>Chairperson</option><option>Vice Chairperson</option><option>Secretary</option><option>Treasurer</option></select></div>
        <div class="mb-3"><label class="form-label">Membership Status</label><select class="form-select" id="recordStatus"><option value="active">Active</option><option value="inactive">Inactive</option><option value="pending">Pending</option></select></div>
    </form></div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-primary" onclick="PTAManagementController.saveRecord()"><i class="bi bi-check-lg me-1"></i> Save</button></div>
</div></div></div>

<script src="<?= $appBase ?>/js/pages/pta_management.js?v=<?php echo time(); ?>"></script>
