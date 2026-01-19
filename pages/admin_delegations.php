<?php
/**
 * Admin — User Delegations Management
 */
?>

<div class="card shadow">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <h2 class="mb-0"><i class="bi bi-person-lines-fill"></i> Delegations — User-level</h2>
        <button class="btn btn-light" onclick="delegationsController.showCreateModal()"><i
                class="bi bi-plus-circle"></i> New Delegation</button>
    </div>
    <div class="card-body">
        <div class="row mb-3">
            <div class="col-md-4">
                <input id="delegationsSearch" class="form-control" placeholder="Search by user or menu item..."
                    onkeyup="delegationsController.handleSearch(this.value)">
            </div>
            <div class="col-md-2">
                <select id="delegationsActiveFilter" class="form-select"
                    onchange="delegationsController.handleActiveFilter(this.value)">
                    <option value="">All</option>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>
        </div>

        <div id="delegationsTableContainer">
            <p class="text-muted">Loading delegations...</p>
        </div>
    </div>
</div>

<!-- Create/Edit Modal -->
<div class="modal fade" id="delegationModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 id="delegationModalLabel" class="modal-title">Create Delegation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="delegationForm">
                    <input type="hidden" id="delegationId">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Delegator (user id)</label>
                            <input id="delegatorUserId" class="form-control" type="number" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Delegate (user id)</label>
                            <input id="delegateUserId" class="form-control" type="number" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-8">
                            <label class="form-label">Menu Item ID</label>
                            <input id="menuItemId" class="form-control" type="number" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Expires At</label>
                            <input id="expiresAt" class="form-control" type="datetime-local">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" onclick="delegationsController.save()">Save</button>
            </div>
        </div>
    </div>
</div>

<script src="js/pages/admin_delegations.js?v=<?php echo time(); ?>"></script>