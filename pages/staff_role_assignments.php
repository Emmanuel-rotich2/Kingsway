<?php
/**
 * Staff Role Assignments Page - Pure UI/UX Layout
 * Controller: staff_role_assignments.js
 * Authentication: JWT via api.js + backend middleware
 * Role-based access: JavaScript AuthContext + permission system
 */
?>
<div class="staff-role-assignments-container">
    <div class="mb-4">
        <h3 class="mb-1"><i class="bi bi-person-gear me-2"></i>Staff Role Assignments</h3>
        <p class="text-muted mb-0">Assign or revoke system roles for staff-linked user accounts.</p>
    </div>
    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <input id="roleStaffSearch" class="form-control" placeholder="Search staff">
                </div>
                <div class="list-group list-group-flush" id="roleStaffList">
                    <div class="p-4 text-center">Loading…</div>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <div>
                        <strong id="selectedStaffName">Select a staff member</strong>
                        <div class="small text-muted" id="selectedStaffMeta"></div>
                    </div>
                    <button id="refreshRolesBtn" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                </div>
                <div class="card-body">
                    <div id="roleAssignmentEmpty" class="text-muted text-center py-5">
                        Choose a staff member to manage roles.
                    </div>
                    <div id="roleAssignmentPanel" hidden>
                        <div class="mb-4">
                            <h6>Assigned roles</h6>
                            <div id="assignedRoles" class="d-flex flex-wrap gap-2"></div>
                        </div>
                        <hr>
                        <div class="row g-2 align-items-end">
                            <div class="col-md-8">
                                <label class="form-label">Add role</label>
                                <select id="availableRoleId" class="form-select">
                                    <option value="">Select role</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <button id="assignRoleBtn" class="btn btn-primary w-100">
                                    <i class="bi bi-plus-lg"></i> Assign
                                </button>
                            </div>
                        </div>
                        <div class="alert alert-warning mt-4 mb-0">
                            <i class="bi bi-shield-exclamation me-1"></i>
                            Role changes affect navigation and backend permissions immediately after the user refreshes or signs in again.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?= $appBase ?>/js/pages/staff_role_assignments.js"></script>