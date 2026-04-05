<?php
/**
 * Class Streams
 *
 * Purpose: View and manage class streams
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
                    <h4 class="mb-1"><i class="fas fa-stream me-2"></i>Class Streams</h4>
                    <p class="text-muted mb-0">View and manage class streams</p>
                </div>
                <button class="btn btn-primary" onclick="ClassStreamsController.showAddModal()"><i class="fas fa-plus me-1"></i> Add New</button>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center">
                <div class="rounded-circle bg-primary bg-opacity-10 p-3 me-3"><i class="fas fa-school text-primary fa-lg"></i></div>
                <div><h6 class="text-muted mb-1">Total Classes</h6><h4 class="mb-0" id="statClasses">0</h4></div>
            </div></div></div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center">
                <div class="rounded-circle bg-success bg-opacity-10 p-3 me-3"><i class="fas fa-stream text-success fa-lg"></i></div>
                <div><h6 class="text-muted mb-1">Total Streams</h6><h4 class="mb-0" id="statStreams">0</h4></div>
            </div></div></div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center">
                <div class="rounded-circle bg-info bg-opacity-10 p-3 me-3"><i class="fas fa-users text-info fa-lg"></i></div>
                <div><h6 class="text-muted mb-1">Avg Students/Stream</h6><h4 class="mb-0" id="statAvg">0</h4></div>
            </div></div></div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center">
                <div class="rounded-circle bg-warning bg-opacity-10 p-3 me-3"><i class="fas fa-expand-arrows-alt text-warning fa-lg"></i></div>
                <div><h6 class="text-muted mb-1">Max Capacity</h6><h4 class="mb-0" id="statCapacity">0</h4></div>
            </div></div></div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4"><input type="text" class="form-control" id="searchInput" placeholder="Search stream, class, teacher..."></div>
                <div class="col-md-3">
                    <select class="form-select" id="filterSelect">
                        <option value="">All Classes</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="statusFilter">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div class="col-md-2"><button class="btn btn-outline-secondary w-100" onclick="ClassStreamsController.refresh()"><i class="fas fa-sync-alt"></i></button></div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="fas fa-table me-2"></i>Class Streams</h6>
            <button class="btn btn-sm btn-outline-success" onclick="ClassStreamsController.exportCSV()"><i class="fas fa-file-csv me-1"></i> Export</button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="dataTable">
                    <thead class="table-light"><tr><th>#</th><th>Class (Grade)</th><th>Stream Name</th><th>Class Teacher</th><th>Students</th><th>Capacity</th><th>Utilization %</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody><tr><td colspan="9" class="text-center text-muted py-4">Loading...</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="formModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="formModalTitle"><i class="fas fa-stream me-2"></i>Add Record</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><form id="recordForm"><input type="hidden" id="recordId">
        <div class="mb-3">
            <label class="form-label">Class <span class="text-danger">*</span></label>
            <select class="form-select" id="recordClass" required>
                <option value="">Select class...</option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Stream Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="recordName" placeholder="e.g., A, East, Blue" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Capacity <span class="text-danger">*</span></label>
            <input type="number" class="form-control" id="recordCapacity" min="1" value="40" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Teacher</label>
            <select class="form-select" id="recordTeacher">
                <option value="">Not assigned</option>
            </select>
        </div>
        <div class="mb-3"><label class="form-label">Status</label><select class="form-select" id="recordStatus"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
    </form></div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-primary" onclick="ClassStreamsController.saveRecord()"><i class="fas fa-save me-1"></i> Save</button></div>
</div></div></div>

<script src="<?= $appBase ?>js/pages/class_streams.js?v=<?php echo time(); ?>"></script>
