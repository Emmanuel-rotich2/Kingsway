<!-- Fee Structure Admin Component - Full management features -->

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-1">Fee Structure Management</h2>

    </div>
    <div class="btn-group">
        <button class="btn btn-outline-primary" onclick="exportFeeStructures()">📥 Export All</button>
        <button class="btn btn-warning" onclick="showDuplicateModal()">📑 Duplicate for New Year</button>
        <button class="btn btn-success" onclick="showCreateFeeStructureModal()">➕ Create Structure</button>
    </div>
</div>

<!-- Stats Row - 5 cards -->
<div class="row g-3 mb-4">
    <div class="col-md-2">
        <div class="card border-primary">
            <div class="card-body text-center">
                <h6 class="text-muted mb-2">📋 Total Structures</h6>
                <h3 class="text-primary mb-0" id="totalStructures">0</h3>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card border-success">
            <div class="card-body text-center">
                <h6 class="text-muted mb-2">✅ Active</h6>
                <h3 class="text-success mb-0" id="activeStructures">0</h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-warning">
            <div class="card-body text-center">
                <h6 class="text-muted mb-2">⏳ Pending Approval</h6>
                <h3 class="text-warning mb-0" id="pendingApproval">0</h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-info">
            <div class="card-body text-center">
                <h6 class="text-muted mb-2">💰 Expected Revenue</h6>
                <h3 class="text-info mb-0" id="totalExpectedRevenue">KES 0</h3>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card border-secondary">
            <div class="card-body text-center">
                <h6 class="text-muted mb-2">📊 Students</h6>
                <h3 class="text-secondary mb-0" id="affectedStudents">0</h3>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">📈 Fee Distribution by Level</h5>
            </div>
            <div class="card-body">
                <canvas id="feeDistributionChart" height="200"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">💰 Projected Revenue by Term</h5>
            </div>
            <div class="card-body">
                <canvas id="revenueProjectionChart" height="200"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Filters Section -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">Filter Fee Structures</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-2">
                <label class="form-label">Academic Year</label>
                <select class="form-select" id="academicYearFilter">
                    <option value="">All Years</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">School Level</label>
                <select class="form-select" id="schoolLevelFilter">
                    <option value="">All Levels</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Class</label>
                <select class="form-select" id="classFilter">
                    <option value="">All Classes</option>
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label">Term</label>
                <select class="form-select" id="termFilter">
                    <option value="">All</option>
                    <option value="1">Term 1</option>
                    <option value="2">Term 2</option>
                    <option value="3">Term 3</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select class="form-select" id="statusFilter">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="draft">Draft</option>
                    <option value="pending_approval">Pending</option>
                    <option value="archived">Archived</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Search</label>
                <input type="text" class="form-control" id="searchFeeStructure" placeholder="Search...">
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <button class="btn btn-outline-secondary w-100" onclick="clearFilters()">Clear</button>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Actions -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="form-check">
        <input class="form-check-input" type="checkbox" id="selectAllCheckbox"
            onchange="selectAllStructures(this.checked)">
        <label class="form-check-label" for="selectAllCheckbox">
            Select All (<span id="selectedCount">0</span> selected)
        </label>
    </div>
    <div class="btn-group">
        <button class="btn btn-sm btn-success" onclick="bulkActivate()" disabled id="bulkActivateBtn">✅
            Activate</button>
        <button class="btn btn-sm btn-warning" onclick="bulkArchive()" disabled id="bulkArchiveBtn">📦 Archive</button>
        <button class="btn btn-sm btn-danger" onclick="bulkDelete()" disabled id="bulkDeleteBtn">🗑️ Delete</button>
    </div>
</div>

<!-- Data Table -->
<div class="table-responsive">
    <table class="table table-hover" id="feeStructuresTable">
        <thead class="table-light">
            <tr>
                <th width="40"><input type="checkbox" id="selectAllHeader"></th>
                <th>ID</th>
                <th>Academic Year</th>
                <th>Level</th>
                <th>Class</th>
                <th>Term</th>
                <th>Total Amount</th>
                <th>Fee Items</th>
                <th>Students</th>
                <th>Expected Revenue</th>
                <th>Status</th>
                <th>Effective Date</th>
                <th>Created By</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="feeStructuresBody">
            <tr>
                <td colspan="14" class="text-center py-4 text-muted">Loading fee structures...</td>
            </tr>
        </tbody>
    </table>
</div>

<!-- Pagination -->
<div class="d-flex justify-content-between align-items-center mt-3">
    <span class="text-muted" id="paginationInfo">Showing 0 of 0</span>
    <div class="btn-group" id="paginationControls"></div>
</div>

<!-- Create/Edit Fee Structure Modal -->
<div class="modal" id="feeStructureModal">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Create New Fee Structure</h3>
                <button class="btn-close" onclick="closeModal('feeStructureModal')">×</button>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Form will be loaded here -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('feeStructureModal')">Cancel</button>
                <button class="btn btn-primary" onclick="saveFeeStructure()" id="saveBtn">💾 Save</button>
            </div>
        </div>
    </div>
</div>

<!-- View Fee Structure Details Modal -->
<div class="modal" id="viewFeeStructureModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Fee Structure Details</h3>
                <button class="btn-close" onclick="closeModal('viewFeeStructureModal')">×</button>
            </div>
            <div class="modal-body" id="viewModalBody">
                <!-- Details will be loaded here -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('viewFeeStructureModal')">Close</button>
                <button class="btn btn-warning" onclick="editFromView()" id="editFromViewBtn">✏️ Edit</button>
                <button class="btn btn-success" onclick="approveFromView()" id="approveFromViewBtn">✅
                    Approve</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal" id="deleteConfirmModal">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Confirm Delete</h3>
                <button class="btn-close" onclick="closeModal('deleteConfirmModal')">×</button>
            </div>
            <div class="modal-body">
                <p>⚠️ Are you sure you want to delete this fee structure?</p>
                <p class="text-danger"><strong>This action cannot be undone.</strong></p>
                <p id="deleteWarning"></p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('deleteConfirmModal')">Cancel</button>
                <button class="btn btn-danger" onclick="confirmDelete()" id="confirmDeleteBtn">🗑️ Delete</button>
            </div>
        </div>
    </div>
</div>

<!-- Duplicate Structure Modal -->
<div class="modal" id="duplicateStructureModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Duplicate Fee Structure</h3>
                <button class="btn-close" onclick="closeModal('duplicateStructureModal')">×</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Target Academic Year *</label>
                    <select class="form-select" id="duplicateTargetYear">
                        <option value="">Select Year</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Price Adjustment (%)</label>
                    <input type="number" class="form-input" id="priceAdjustment" value="0" step="0.5" min="-50"
                        max="100">
                    <small class="form-text">Positive values increase fees, negative values decrease</small>
                </div>
                <div class="form-group">
                    <label>Apply to:</label>
                    <div class="radio-group">
                        <label><input type="radio" name="duplicateScope" value="single" checked> Selected structure
                            only</label>
                        <label><input type="radio" name="duplicateScope" value="class"> All structures for this
                            class</label>
                        <label><input type="radio" name="duplicateScope" value="level"> All structures for this
                            level</label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('duplicateStructureModal')">Cancel</button>
                <button class="btn btn-primary" onclick="confirmDuplicate()" id="confirmDuplicateBtn">📑
                    Duplicate</button>
            </div>
        </div>
    </div>
</div>

<script src="/Kingsway/js/pages/fee_structure_admin.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof window.FeeStructureAdminController !== 'undefined') {
            window.FeeStructureAdminController.init();
        } else {
            console.error('FeeStructureAdminController not found');
        }
    });
</script>