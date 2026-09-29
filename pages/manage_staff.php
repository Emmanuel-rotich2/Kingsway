<?php
/**
 * Manage Staff Page - Pure UI/UX Layout
 * Controller: staff_production_ui.js
 * Authentication: JWT via api.js + backend middleware
 * Role-based access: JavaScript AuthContext + permission system
 */
if (!isset($staffPageTitle)) {
    $staffPageTitle = 'Staff Management';
}
if (!isset($staffPageDescription)) {
    $staffPageDescription = 'Manage all staff members and their assignments';
}
if (!isset($staffPageIcon)) {
    $staffPageIcon = 'bi bi-person-workspace';
}
if (isset($staffPageContext) && is_array($staffPageContext)) {
    echo '<script>window.STAFF_PAGE_CONTEXT = ' .
        json_encode($staffPageContext, JSON_UNESCAPED_SLASHES) .
        ';</script>' . PHP_EOL;
}
?>
<nav class="nav nav-tabs mb-3" id="staffWorkspaceTabs" aria-label="Staff management sections">
    <button class="nav-link active" type="button" data-staff-workspace="directory">Directory</button>
    <button class="nav-link" type="button" data-staff-workspace="onboarding">Onboarding</button>
    <button class="nav-link" type="button" data-staff-workspace="lifecycle">Lifecycle</button>
    <button class="nav-link" type="button" data-staff-workspace="setup" data-permission="staff.directory.manage">Setup</button>
</nav>
<style>
@media print {
    @page { size: A4 landscape; margin: 12mm; }
    body.staff-directory-printing [data-staff-directory-page] .page-header .btn-group,
    body.staff-directory-printing [data-staff-directory-page] .card:not(:has(#staffTable)),
    body.staff-directory-printing #staffBulkActions,
    body.staff-directory-printing #staffTablePagination,
    body.staff-directory-printing #staffTable th:has(#selectAllStaffOnPage),
    body.staff-directory-printing #staffTable td:has([data-select-staff]),
    body.staff-directory-printing #staffTable .staff-actions-column { display: none !important; }
    body.staff-directory-printing [data-staff-directory-page] { width: 100%; }
    body.staff-directory-printing #staffTable { font-size: 9pt; }
    body.staff-setup-printing [data-staff-directory-page], body.staff-setup-printing #staffWorkspaceTabs,
    body.staff-setup-printing [data-staff-workspace-panel]:not(#staffWorkspace-setup),
    body.staff-setup-printing #staffWorkspace-setup .btn, body.staff-setup-printing #staffWorkspace-setup form { display: none !important; }
    body.staff-setup-printing #staffWorkspace-setup { display: block !important; }
}
</style>
    <section class="staff-management-container" id="staffWorkspace-directory" data-staff-workspace-panel="directory" data-staff-directory-page>
    <!-- Page Header -->
    <div class="page-header mb-4">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-1"><i class="<?= htmlspecialchars($staffPageIcon, ENT_QUOTES, 'UTF-8') ?> me-2"></i><?= htmlspecialchars($staffPageTitle, ENT_QUOTES, 'UTF-8') ?></h4>
                <p class="text-muted mb-0"><?= htmlspecialchars($staffPageDescription, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <div class="btn-group">
                <button class="btn btn-primary" id="addStaffBtn" data-permission-module="staff" data-permission-action="create">
                    <i class="bi bi-plus-lg me-1"></i>Add Existing Staff
                </button>
                <button class="btn btn-outline-primary" type="button" data-staff-workspace="import" data-requires-staff-import="true" data-permission="staff.directory.manage">
                    <i class="bi bi-file-import me-1"></i>Import Staff
                </button>
                <button class="btn btn-outline-secondary" id="exportStaffBtn" data-permission-module="staff" data-permission-action="export">
                    <i class="bi bi-download me-1"></i>Export
                </button>
                <button class="btn btn-outline-secondary" id="printStaffBtn" data-permission-module="staff" data-permission-action="print">
                    <i class="bi bi-printer me-1"></i>Print
                </button>
            </div>
        </div>
    </div>

    <!-- Role-specific summary cards -->
    <div class="row mb-4" id="staffStatsRow"></div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-2">
                <div class="col-md-3">
                    <input type="text" class="form-control" id="searchStaff" placeholder="Search staff...">
                </div>
                <div class="col-md-2">
                    <select class="form-select" id="filterDepartment">
                        <option value="">All Departments</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" id="filterStaffType">
                        <option value="">All Types</option>
                        <option value="1">Teaching</option>
                        <option value="2">Non-Teaching</option>
                        <option value="3">Admin</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" id="filterStatus">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="on_leave">On Leave</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-outline-secondary w-100" id="resetFilters">
                        <i class="bi bi-arrow-clockwise me-1"></i>Reset Filters
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Staff Table -->
    <div class="card">
        <div class="card-body">
            <div id="staffBulkActions" class="d-none align-items-center flex-wrap gap-2 mb-3" aria-live="polite">
                <span id="staffSelectionCount" class="fw-semibold small"></span>
                <button type="button" class="btn btn-sm btn-outline-success" id="bulkActivateStaffBtn">Activate accounts</button>
                <button type="button" class="btn btn-sm btn-outline-warning" id="bulkDeactivateStaffBtn">Deactivate accounts</button>
                <button type="button" class="btn btn-sm btn-outline-primary" id="bulkManageStaffRolesBtn">Set roles</button>
                <button type="button" class="btn btn-sm btn-outline-danger" id="bulkResetStaffPasswordsBtn">Send password reset links</button>
                <button type="button" class="btn btn-sm btn-link" id="clearStaffSelectionBtn">Clear selection</button>
            </div>
            <div class="table-responsive">
                <table class="table table-hover" id="staffTable">
                    <thead>
                        <tr id="staffTableHead">
                            <th scope="col">Staff</th>
                            <th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody id="staffTableBody">
                        <tr>
                            <td colspan="7" class="text-center py-4">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div id="staffTablePagination" class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3"></div>
        </div>
    </div>
</section>

<div class="modal fade" id="staffRoleManagerModal" tabindex="-1" aria-labelledby="staffRoleManagerTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="staffRoleManagerTitle">Manage staff roles</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted" id="staffRoleManagerDescription">Select the system roles this staff member should have.</p>
                <div id="staffRoleManagerChoices" class="list-group"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveStaffRolesBtn">Save roles</button>
            </div>
        </div>
    </div>
</div>

<!-- Staff Editor Modal -->
<div class="modal fade" id="staffModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="staffModalTitle">Add Staff</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info small">Use this form for a person already employed by the school. Its fields follow the existing-staff import template; staff number is generated automatically and account status is managed from the directory. Use Staff Appointments for applicants and walk-in candidates.</div>
                <form id="staffForm">
                    <input type="hidden" id="staffId">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">First Name *</label>
                            <input type="text" class="form-control" id="firstName" required data-kw-validate="name">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Last Name *</label>
                            <input type="text" class="form-control" id="lastName" required data-kw-validate="name">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email *</label>
                            <input type="email" class="form-control" id="email" required data-kw-validate="email">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Department *</label>
                            <select class="form-select" id="department">
                                <option value="">Select Department</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Staff Type *</label>
                            <select class="form-select" id="staff_type_id">
                                <option value="">Select Type</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Staff Category *</label>
                            <select class="form-select" id="staff_category_id" required>
                                <option value="">Select category</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Primary System Role *</label>
                            <select class="form-select" id="roleId">
                                <option value="">Select Role</option>
                            </select>
                            <div class="form-text">Controls the account dashboard and permissions. The primary role also supplies the default salary rate; set individual exceptions in Payroll.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contract Type *</label>
                            <select class="form-select" id="contractType">
                                <option value="permanent">Permanent</option>
                                <option value="contract">Contract</option>
                                <option value="temporary">Temporary</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveStaffBtn">Save</button>
            </div>
        </div>
    </div>
</div>


<!-- Staff Profile Modal -->
<div class="modal fade" id="staffViewModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="staffViewModalTitle">Staff Profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="staffViewModalBody">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="editFromViewBtn">
                    <i class="bi bi-pencil me-1"></i>Edit Staff
                </button>
            </div>
        </div>
    </div>
</div>

<section id="staffWorkspace-import" data-staff-workspace-panel="import" hidden>
    <div class="d-flex justify-content-end mb-2">
        <button class="btn btn-outline-secondary" type="button" data-staff-workspace="directory"><i class="bi bi-arrow-left me-1"></i>Back to Directory</button>
    </div>
    <?php include __DIR__ . '/import_existing_staff.php'; ?>
</section>
<section id="staffWorkspace-onboarding" data-staff-workspace-panel="onboarding" hidden>
    <?php include __DIR__ . '/staff_onboarding.php'; ?>
</section>
<section id="staffWorkspace-lifecycle" data-staff-workspace-panel="lifecycle" hidden>
    <?php include __DIR__ . '/staff_lifecycle.php'; ?>
</section>
<section id="staffWorkspace-setup" data-staff-workspace-panel="setup" hidden>
    <div class="page-header mb-4 d-flex justify-content-between align-items-start"><div><h4 class="mb-1">Staff setup</h4><p class="text-muted mb-0">Manage the departments and employment positions used by staff records, forms and import templates.</p></div><div class="btn-group"><button class="btn btn-outline-secondary" id="exportStaffSetupBtn" type="button">Export CSV</button><button class="btn btn-outline-secondary" id="printStaffSetupBtn" type="button">Print / PDF</button></div></div>
    <div class="row g-4">
      <div class="col-lg-6"><div class="card h-100"><div class="card-header d-flex justify-content-between align-items-center"><h5 class="mb-0">Departments</h5></div><div class="card-body">
        <form id="staffDepartmentForm" class="row g-2 mb-3"><input type="hidden" id="staffDepartmentId"><div class="col-md-5"><label class="form-label" for="staffDepartmentName">Name</label><input class="form-control" id="staffDepartmentName" maxlength="100" required></div><div class="col-md-3"><label class="form-label" for="staffDepartmentCode">Code</label><input class="form-control" id="staffDepartmentCode" maxlength="20" required></div><div class="col-md-4 d-flex align-items-end gap-2"><button class="btn btn-primary" type="submit">Save</button><button class="btn btn-outline-secondary" type="button" id="resetStaffDepartmentForm">Clear</button></div><div class="col-12 form-check ms-2"><input class="form-check-input" type="checkbox" id="staffDepartmentActive" checked><label class="form-check-label" for="staffDepartmentActive">Active for new assignments</label></div></form>
        <div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Department</th><th>Code</th><th>Status</th><th></th></tr></thead><tbody id="staffDepartmentsList"><tr><td colspan="4" class="text-muted">Loading…</td></tr></tbody></table></div>
      </div></div></div>
      <div class="col-lg-6"><div class="card h-100"><div class="card-header"><h5 class="mb-0">Employment positions</h5></div><div class="card-body">
        <form id="staffPositionForm" class="row g-2 mb-3"><input type="hidden" id="staffPositionId"><div class="col-md-6"><label class="form-label" for="staffPositionName">Position name</label><input class="form-control" id="staffPositionName" maxlength="120" required></div><div class="col-md-6"><label class="form-label" for="staffPositionType">Staff type</label><select class="form-select" id="staffPositionType"><option value="">Any type</option></select></div><div class="col-md-6"><label class="form-label" for="staffPositionCategory">Staff category</label><select class="form-select" id="staffPositionCategory"><option value="">Any category</option></select></div><div class="col-md-6"><label class="form-label" for="staffPositionRoles">Related system roles</label><select class="form-select" id="staffPositionRoles" multiple size="3" aria-describedby="staffPositionRolesHelp"><option value="">Any role</option></select><div class="form-text" id="staffPositionRolesHelp">Select one or more roles this position can serve.</div></div><div class="col-md-6"><label class="form-label" for="staffPositionDefaultRoles">Default position for roles</label><select class="form-select" id="staffPositionDefaultRoles" multiple size="3" aria-describedby="staffPositionDefaultRolesHelp"><option value="">Select roles</option></select><div class="form-text" id="staffPositionDefaultRolesHelp">New staff with a selected primary role start with this position. The School Administrator can change it later.</div></div><div class="col-12 form-check ms-2"><input class="form-check-input" type="checkbox" id="staffPositionActive" checked><label class="form-check-label" for="staffPositionActive">Active for new staff and imports</label></div><div class="col-12"><button class="btn btn-primary" type="submit">Save position</button><button class="btn btn-outline-secondary ms-2" type="button" id="resetStaffPositionForm">Clear</button></div></form>
        <div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Position</th><th>Applies to</th><th>Status</th><th></th></tr></thead><tbody id="staffPositionsList"><tr><td colspan="4" class="text-muted">Loading…</td></tr></tbody></table></div>
      </div></div></div>
    </div>
</section>
<script>
(() => {
    const tabs = [...document.querySelectorAll('[data-staff-workspace]')];
    const panels = [...document.querySelectorAll('[data-staff-workspace-panel]')];
    const aliases = {staff_onboarding: 'onboarding', staff_lifecycle: 'lifecycle', import_existing_staff: 'import'};
    const initial = aliases[window.REQUESTED_ROUTE] || new URLSearchParams(location.search).get('staff_tab') || 'directory';
    let canImport = false;
    const activate = (name, updateUrl = true) => {
        const requested = name === 'import' && !canImport ? 'directory' : name;
        const selected = panels.some(panel => panel.dataset.staffWorkspacePanel === requested) ? requested : 'directory';
        panels.forEach(panel => { panel.hidden = panel.dataset.staffWorkspacePanel !== selected; });
        tabs.forEach(tab => {
            const active = tab.dataset.staffWorkspace === selected;
            tab.classList.toggle('active', active);
            tab.setAttribute('aria-current', active ? 'page' : 'false');
        });
        if (updateUrl) {
            const url = new URL(location.href);
            url.searchParams.set('staff_tab', selected);
            history.replaceState(null, '', url);
        }
    };
    const initialize = async () => {
        if (window.AuthContext?.ready) await window.AuthContext.ready();
        canImport = Boolean(window.AuthContext?.hasPermission?.('staff_import'));
        tabs.filter(tab => tab.dataset.requiresStaffImport === 'true').forEach(tab => { tab.hidden = !canImport; });
        tabs.filter(tab => tab.dataset.permission === 'staff.directory.manage').forEach(tab => { tab.hidden = !window.AuthContext?.canEdit?.('staff') && !window.AuthContext?.canCreate?.('staff'); });
        activate(initial, false);
    };
    tabs.forEach(tab => tab.addEventListener('click', () => activate(tab.dataset.staffWorkspace)));
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => { void initialize(); }, { once: true });
    } else {
        void initialize();
    }
})();
</script>
<?php asset_script($appBase, 'js/pages/staff_access.js'); ?>
<?php asset_script($appBase, 'js/pages/staff_production_ui.js'); ?>
