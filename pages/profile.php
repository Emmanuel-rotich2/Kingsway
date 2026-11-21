<script nonce="<?= csp_nonce() ?>">
window.location.replace((window.APP_BASE || '') + '/home.php?route=account_settings&section=profile');
</script>
<noscript><meta http-equiv="refresh" content="0;url=home.php?route=account_settings&amp;section=profile"></noscript>
<div class="alert alert-info">Opening the unified Account Centre…</div>
<?php /* Legacy profile markup retained below temporarily for rollback safety. */ ?>
<div class="container-fluid py-3 d-none" id="profilePage">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="h3 mb-0" id="profileGreeting">Welcome</h2>
            <p class="text-muted mb-0">Your profile and account information</p>
        </div>
        <div>
            <button class="btn btn-outline-primary d-none" type="button" id="profileEditBtn">
                <i class="bi bi-pencil me-1"></i>Edit Details
            </button>
            <button class="btn btn-outline-secondary d-none" type="button" id="profileCancelBtn">
                <i class="bi bi-x me-1"></i>Cancel
            </button>
            <button class="btn btn-primary d-none" type="button" id="profileSaveBtn">
                <i class="bi bi-check me-1"></i>Save Changes
            </button>
        </div>
    </div>

    <div class="row g-3">
        <!-- ── Profile Summary Card ────────────────────────────────── -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm text-center h-100">
                <div class="card-body">
                    <div class="app-user-avatar app-user-avatar-xl mx-auto mb-3"
                         style="width:80px;height:80px;font-size:2rem;
                                background:var(--kw-primary);color:#fff;
                                border-radius:50%;display:flex;align-items:center;
                                justify-content:center;overflow:hidden"
                         id="profileAvatar">U</div>
                    <h5 class="card-title mb-1" id="profileName">User</h5>
                    <p class="text-muted small mb-2" id="profileEmail">—</p>
                    <span class="badge bg-success mb-3" id="profileRole">User</span>
                    <div id="profileDomainBadge"></div>
                    <hr>
                    <div class="text-start small">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Phone</span>
                            <strong id="profilePhone">—</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2 d-none" id="profileEmployeeRow">
                            <span class="text-muted">Staff Number</span>
                            <strong id="profileEmployeeId">—</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2 d-none" id="profileDepartmentRow">
                            <span class="text-muted">Department</span>
                            <strong id="profileDepartment">—</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Academic Year</span>
                            <strong id="currentAcademicYear">—</strong>
                        </div>
                        <div class="d-flex justify-content-between d-none" id="profileTenureRow">
                            <span class="text-muted">With Kingsway</span>
                            <strong id="profileTenure">—</strong>
                        </div>
                        <div class="d-flex justify-content-between mt-2">
                            <span class="text-muted">Last Login</span>
                            <strong id="profileLastLogin">—</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Personal / Employment / Statutory Cards ─────────────── -->
        <div class="col-lg-8">
            <!-- Personal Information (rendered by controller, editable) -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white">
                    <strong>Personal Information</strong>
                </div>
                <div class="card-body" id="personalInfoFields">
                    <p class="text-muted small mb-0">Loading...</p>
                </div>
            </div>

            <!-- Employment Details (school domain only, read-only, rendered by controller) -->
            <div class="card border-0 shadow-sm mb-3 d-none" id="employmentCard">
                <div class="card-header bg-white">
                    <strong>Employment Details</strong>
                </div>
                <div class="card-body" id="employmentFields"></div>
            </div>

            <!-- Financial & Statutory (school domain only, read-only, rendered by controller) -->
            <div class="card border-0 shadow-sm mb-3 d-none" id="financialCard">
                <div class="card-header bg-white">
                    <strong>Financial & Statutory Information</strong>
                </div>
                <div class="card-body" id="financialFields"></div>
            </div>

            <!-- Roles -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <strong>Roles</strong>
                </div>
                <div class="card-body">
                    <div id="rolesList"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── KPI Summary (school domain only) ────────────────────────── -->
    <div class="row g-3 mt-1 d-none" id="kpiSection">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <strong>Performance Summary</strong>
                </div>
                <div class="card-body" id="kpiSummary">
                    <p class="text-muted small mb-0">Loading...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Login History ──────────────────────────────────────────── -->
    <div class="row g-3 mt-1">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <strong>Login History</strong>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="loginHistoryTable">
                        <thead class="table-light">
                            <tr>
                                <th>Date & Time</th>
                                <th>IP Address</th>
                                <th>Device</th>
                                <th>Browser</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="loginHistoryBody"><tr id="loginHistoryState"><td colspan="5" class="text-center text-muted py-3">Loading...</td></tr></tbody>
                    </table>
<template id="loginHistoryRowTemplate"><tr data-login-history-row><td data-fill="created"></td><td data-fill="ip"></td><td data-fill="device"></td><td data-fill="browser"></td><td><span class="badge" data-fill="status"></span></td></tr></template>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?= htmlspecialchars($appBase) ?>/js/pages/me.js?v=<?= asset_version('js/pages/me.js') ?>"></script>
